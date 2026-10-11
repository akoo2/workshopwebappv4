<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Config\Database;
use App\Security\Csrf;
use App\Security\AuditLogger;
use App\Security\Validator;
use App\Lang\Translator;
use Exception;

class InventoryController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $categoryId = isset($params['category']) ? (int)$params['category'] : 0;
        $lowStockOnly = !empty($params['low_stock']);
        $search = trim($params['q'] ?? '');

        $pdo = Database::getAppPdo();

        // Fetch categories
        $catsStmt = $pdo->query("SELECT * FROM part_categories ORDER BY name ASC");
        $categories = $catsStmt->fetchAll();

        // Fetch current exchange rate
        $rateStmt = $pdo->query("SELECT rate_usd_to_iqd FROM exchange_rates ORDER BY effective_date DESC LIMIT 1");
        $exchangeRate = (int)($rateStmt->fetchColumn() ?: 1510);

        // Build query
        $sql = "
            SELECT p.*, c.name AS category_name
            FROM parts p
            JOIN part_categories c ON p.category_id = c.id
            WHERE p.deleted_at IS NULL
        ";
        $sqlParams = [];

        if ($categoryId > 0) {
            $sql .= " AND p.category_id = :cat_id";
            $sqlParams['cat_id'] = $categoryId;
        }

        if ($lowStockOnly) {
            $sql .= " AND p.quantity <= p.min_stock";
        }

        if (!empty($search)) {
            $sql .= " AND (p.name LIKE :q OR p.serial_number LIKE :q)";
            $sqlParams['q'] = "%{$search}%";
        }

        $sql .= " ORDER BY p.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($sqlParams);
        $parts = $stmt->fetchAll();

        // Obscure cost price if role is TECH
        $canViewCost = in_array($user['role'] ?? '', ['MANAGER', 'ADMIN', 'OWNER'], true);
        if (!$canViewCost) {
            foreach ($parts as &$p) {
                $p['cost_price'] = null;
            }
            unset($p);
        }

        return $this->view->render($response, 'inventory/index.twig', [
            'user' => $user,
            'parts' => $parts,
            'categories' => $categories,
            'selected_category' => $categoryId,
            'low_stock_only' => $lowStockOnly,
            'search' => $search,
            'can_view_cost' => $canViewCost,
            'exchange_rate' => $exchangeRate,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $pdo = Database::getAppPdo();

        $cats = $pdo->query("SELECT * FROM part_categories ORDER BY name ASC")->fetchAll();

        return $this->view->render($response, 'inventory/form.twig', [
            'user' => $user,
            'categories' => $cats,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();

        $validator = Validator::validate($body, [
            'category_id' => ['required', 'integer'],
            'name' => ['required', 'min_len:2', 'max_len:150'],
            'serial_number' => ['max_len:100'],
            'quantity' => ['required', 'integer'],
            'min_stock' => ['required', 'integer'],
            'cost_price' => ['numeric'],
            'cost_currency' => ['in:USD,IQD'],
            'selling_price' => ['required', 'numeric'],
            'selling_currency' => ['in:USD,IQD']
        ]);

        if ($validator->fails()) {
            return $response->withStatus(302)->withHeader('Location', '/inventory/new?error=' . urlencode($validator->getFirstError()));
        }

        $data = $validator->getData();
        if ((int)$data['quantity'] < 0) {
            return $response->withStatus(302)->withHeader('Location', '/inventory/new?error=Quantity+cannot+be+negative');
        }

        $pdo = Database::getAppPdo();
        $pdo->beginTransaction();

        try {
            $now = gmdate('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                INSERT INTO parts (category_id, name, serial_number, quantity, min_stock, cost_price, cost_currency, selling_price, selling_currency, created_at, updated_at)
                VALUES (:cat_id, :name, :sn, :qty, :min_stock, :cost, :cost_curr, :sell, :sell_curr, :now, :now)
            ");
            $stmt->execute([
                'cat_id' => $data['category_id'],
                'name' => $data['name'],
                'sn' => $data['serial_number'] ?? null,
                'qty' => (int)$data['quantity'],
                'min_stock' => (int)$data['min_stock'],
                'cost' => !empty($data['cost_price']) ? (int)$data['cost_price'] : null,
                'cost_curr' => $data['cost_currency'] ?? 'USD',
                'sell' => (int)$data['selling_price'],
                'sell_curr' => $data['selling_currency'] ?? 'USD',
                'now' => $now
            ]);

            $partId = (int)$pdo->lastInsertId();

            // Record initial stock movement if quantity > 0
            if ((int)$data['quantity'] > 0) {
                $mvStmt = $pdo->prepare("
                    INSERT INTO stock_movements (part_id, user_id, repair_id, quantity_change, quantity_after, movement_type, reason, created_at)
                    VALUES (:part_id, :user_id, NULL, :change, :after, 'PURCHASE', 'Initial stock intake', :now)
                ");
                $mvStmt->execute([
                    'part_id' => $partId,
                    'user_id' => $user['id'],
                    'change' => (int)$data['quantity'],
                    'after' => (int)$data['quantity'],
                    'now' => $now
                ]);
            }

            AuditLogger::log('PART', $partId, 'CREATE', null, $data, (int)$user['id']);
            $pdo->commit();

            return $response->withStatus(302)->withHeader('Location', '/inventory?success=created');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return $response->withStatus(302)->withHeader('Location', '/inventory/new?error=' . urlencode($e->getMessage()));
        }
    }

    public function adjustStock(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $partId = (int)$args['id'];
        $body = (array)$request->getParsedBody();

        $change = (int)($body['quantity_change'] ?? 0);
        $reason = trim((string)($body['reason'] ?? ''));
        $type = in_array($body['movement_type'] ?? '', ['PURCHASE', 'MANUAL_ADJUST', 'REPAIR_RETURN'], true)
                ? $body['movement_type'] : 'MANUAL_ADJUST';

        if ($change === 0) {
            return $response->withStatus(302)->withHeader('Location', '/inventory?error=Quantity+change+cannot+be+zero');
        }

        if (empty($reason)) {
            return $response->withStatus(302)->withHeader('Location', '/inventory?error=Reason+is+required+for+stock+movements');
        }

        $pdo = Database::getAppPdo();
        $pdo->beginTransaction();

        try {
            // Lock part row for update
            $stmt = $pdo->prepare("SELECT quantity, name FROM parts WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute(['id' => $partId]);
            $part = $stmt->fetch();

            if (!$part) {
                throw new Exception("Part not found.");
            }

            $currentQty = (int)$part['quantity'];
            $newQty = $currentQty + $change;

            if ($newQty < 0) {
                throw new Exception("Cannot reduce stock below zero. Current stock is {$currentQty}.");
            }

            $now = gmdate('Y-m-d H:i:s');

            // Update parts table
            $upStmt = $pdo->prepare("UPDATE parts SET quantity = :new_qty, updated_at = :now WHERE id = :id");
            $upStmt->execute(['new_qty' => $newQty, 'now' => $now, 'id' => $partId]);

            // Append to stock_movements ledger
            $mvStmt = $pdo->prepare("
                INSERT INTO stock_movements (part_id, user_id, repair_id, quantity_change, quantity_after, movement_type, reason, created_at)
                VALUES (:part_id, :user_id, NULL, :change, :after, :type, :reason, :now)
            ");
            $mvStmt->execute([
                'part_id' => $partId,
                'user_id' => $user['id'],
                'change' => $change,
                'after' => $newQty,
                'type' => $type,
                'reason' => $reason,
                'now' => $now
            ]);

            AuditLogger::log('PART', $partId, 'STOCK_ADJUST', ['quantity' => $currentQty], ['quantity' => $newQty, 'reason' => $reason], (int)$user['id']);
            $pdo->commit();

            return $response->withStatus(302)->withHeader('Location', '/inventory?success=stock_adjusted');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return $response->withStatus(302)->withHeader('Location', '/inventory?error=' . urlencode($e->getMessage()));
        }
    }

    public function createCategory(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $name = trim((string)($body['name'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));

        if (empty($name)) {
            return $response->withStatus(302)->withHeader('Location', '/inventory?error=Category+name+is+required');
        }

        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("INSERT INTO part_categories (name, description) VALUES (:name, :desc)");
        try {
            $stmt->execute(['name' => $name, 'desc' => $description ?: null]);
            AuditLogger::log('CATEGORY', (int)$pdo->lastInsertId(), 'CREATE', null, ['name' => $name], (int)$user['id']);
        } catch (\Throwable $e) {
            return $response->withStatus(302)->withHeader('Location', '/inventory?error=Category+already+exists');
        }

        return $response->withStatus(302)->withHeader('Location', '/inventory?success=category_created');
    }
}
