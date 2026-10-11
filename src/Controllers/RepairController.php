<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Psr7\Response as SlimResponse;
use App\Config\Database;
use App\Security\Csrf;
use App\Security\AuditLogger;
use App\Security\UploadService;
use App\Security\Validator;
use Exception;

class RepairController
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
        $status = $params['status'] ?? '';
        $techId = isset($params['tech']) ? (int)$params['tech'] : 0;
        $search = trim($params['q'] ?? '');

        $pdo = Database::getAppPdo();

        // Fetch technicians for filter
        $techs = $pdo->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        $sql = "
            SELECT r.*, c.name AS customer_name, c.phone AS customer_phone, u.name AS tech_name,
                   (SELECT COALESCE(SUM(amount_iqd_equivalent), 0) FROM payments p WHERE p.repair_id = r.id AND p.is_voided = 0) AS amount_paid
            FROM repairs r
            JOIN customers c ON r.customer_id = c.id
            LEFT JOIN users u ON r.technician_id = u.id
            WHERE r.deleted_at IS NULL
        ";
        $sqlParams = [];

        if (!empty($status)) {
            $sql .= " AND r.status = :status";
            $sqlParams['status'] = $status;
        }

        if ($techId > 0) {
            $sql .= " AND r.technician_id = :tech_id";
            $sqlParams['tech_id'] = $techId;
        }

        if (!empty($search)) {
            $sql .= " AND (r.ticket_number LIKE :q OR r.tv_brand LIKE :q OR r.tv_model LIKE :q OR c.name LIKE :q OR c.phone LIKE :q)";
            $sqlParams['q'] = "%{$search}%";
        }

        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($sqlParams);
        $repairs = $stmt->fetchAll();

        return $this->view->render($response, 'repairs/index.twig', [
            'user' => $user,
            'repairs' => $repairs,
            'technicians' => $techs,
            'selected_status' => $status,
            'selected_tech' => $techId,
            'search' => $search,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $pdo = Database::getAppPdo();

        $customers = $pdo->query("SELECT id, name, phone FROM customers WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
        $techs = $pdo->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        $rateStmt = $pdo->query("SELECT rate_usd_to_iqd FROM exchange_rates ORDER BY effective_date DESC LIMIT 1");
        $exchangeRate = (int)($rateStmt->fetchColumn() ?: 1510);

        return $this->view->render($response, 'repairs/form.twig', [
            'user' => $user,
            'customers' => $customers,
            'technicians' => $techs,
            'exchange_rate' => $exchangeRate,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $pdo = Database::getAppPdo();

        $customerId = (int)($body['customer_id'] ?? 0);

        // If quick creating new customer
        if ($customerId === 0 && !empty($body['new_cust_name']) && !empty($body['new_cust_phone'])) {
            $check = $pdo->prepare("SELECT id FROM customers WHERE phone = :p AND deleted_at IS NULL");
            $check->execute(['p' => $body['new_cust_phone']]);
            $existingId = $check->fetchColumn();

            if ($existingId) {
                $customerId = (int)$existingId;
            } else {
                $cInsert = $pdo->prepare("INSERT INTO customers (name, phone, created_at, updated_at) VALUES (:n, :p, :t, :t)");
                $now = gmdate('Y-m-d H:i:s');
                $cInsert->execute(['n' => trim($body['new_cust_name']), 'p' => trim($body['new_cust_phone']), 't' => $now]);
                $customerId = (int)$pdo->lastInsertId();
            }
        }

        $validator = Validator::validate($body, [
            'tv_brand' => ['required', 'min_len:2', 'max_len:50'],
            'tv_model' => ['required', 'min_len:2', 'max_len:50'],
            'fault_description' => ['required', 'min_len:5'],
            'currency' => ['in:USD,IQD']
        ]);

        if ($validator->fails() || $customerId === 0) {
            return $response->withStatus(302)->withHeader('Location', '/repairs/new?error=' . urlencode($validator->getFirstError() ?: 'Customer is required.'));
        }

        $data = $validator->getData();
        $ticketNo = 'TICK-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $now = gmdate('Y-m-d H:i:s');

        // Accessories packaging
        $accessories = [];
        if (!empty($body['acc_power_cable'])) $accessories[] = 'Power Cable';
        if (!empty($body['acc_remote'])) $accessories[] = 'Remote Control';
        if (!empty($body['acc_stand'])) $accessories[] = 'Base Stand';
        if (!empty($body['acc_wall_mount'])) $accessories[] = 'Wall Mount Bracket';

        $stmt = $pdo->prepare("
            INSERT INTO repairs (
                ticket_number, customer_id, technician_id, tv_brand, tv_model, tv_serial,
                accessories_received, fault_description, cosmetic_condition, status,
                currency, labor_fee, parts_total, total_charged, created_at, updated_at
            ) VALUES (
                :ticket, :cust_id, :tech_id, :brand, :model, :serial,
                :accessories, :fault, :cosmetic, 'CHECKED_IN',
                :curr, :labor, 0, :labor, :now, :now
            )
        ");

        $stmt->execute([
            'ticket' => $ticketNo,
            'cust_id' => $customerId,
            'tech_id' => !empty($body['technician_id']) ? (int)$body['technician_id'] : null,
            'brand' => $data['tv_brand'],
            'model' => $data['tv_model'],
            'serial' => trim($body['tv_serial'] ?? '') ?: null,
            'accessories' => json_encode($accessories, JSON_UNESCAPED_UNICODE),
            'fault' => $data['fault_description'],
            'cosmetic' => trim($body['cosmetic_condition'] ?? '') ?: null,
            'curr' => $data['currency'] ?? 'IQD',
            'labor' => (int)($body['labor_fee'] ?? 0),
            'now' => $now
        ]);

        $repairId = (int)$pdo->lastInsertId();

        AuditLogger::log('REPAIR', $repairId, 'CHECK_IN', null, [
            'ticket' => $ticketNo,
            'customer_id' => $customerId,
            'brand' => $data['tv_brand']
        ], (int)$user['id']);

        return $response->withStatus(302)->withHeader('Location', "/repairs/{$repairId}?success=checked_in");
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int)$args['id'];
        $pdo = Database::getAppPdo();

        $stmt = $pdo->prepare("
            SELECT r.*, c.name AS customer_name, c.phone AS customer_phone, c.alt_phone, c.address AS customer_address,
                   u.name AS tech_name
            FROM repairs r
            JOIN customers c ON r.customer_id = c.id
            LEFT JOIN users u ON r.technician_id = u.id
            WHERE r.id = :id AND r.deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id]);
        $repair = $stmt->fetch();

        if (!$repair) {
            return $response->withStatus(404)->withHeader('Location', '/repairs');
        }

        // Fetch parts consumed on this repair
        $partsStmt = $pdo->prepare("
            SELECT rp.*, p.name AS part_name, p.serial_number
            FROM repair_parts rp
            JOIN parts p ON rp.part_id = p.id
            WHERE rp.repair_id = :id
            ORDER BY rp.created_at ASC
        ");
        $partsStmt->execute(['id' => $id]);
        $consumedParts = $partsStmt->fetchAll();

        // Fetch payments made
        $payStmt = $pdo->prepare("
            SELECT p.*, u.name AS recorded_by_name
            FROM payments p
            JOIN users u ON p.received_by_user_id = u.id
            WHERE p.repair_id = :id
            ORDER BY p.created_at DESC
        ");
        $payStmt->execute(['id' => $id]);
        $payments = $payStmt->fetchAll();

        // Calculate balance
        $paidTotalIQD = 0;
        foreach ($payments as $p) {
            if (empty($p['is_voided'])) {
                $paidTotalIQD += (int)$p['amount_iqd_equivalent'];
            }
        }

        // Exchange rate
        $rateStmt = $pdo->query("SELECT rate_usd_to_iqd FROM exchange_rates ORDER BY effective_date DESC LIMIT 1");
        $exchangeRate = (int)($rateStmt->fetchColumn() ?: 1510);

        // Fetch available spare parts for adding to bench
        $availParts = $pdo->query("SELECT id, name, quantity, selling_price, selling_currency FROM parts WHERE quantity > 0 AND deleted_at IS NULL ORDER BY name ASC")->fetchAll();
        $technicians = $pdo->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        // Fetch inspection photos
        $photosStmt = $pdo->prepare("SELECT * FROM repair_photos WHERE repair_id = :id ORDER BY created_at ASC");
        $photosStmt->execute(['id' => $id]);
        $photos = $photosStmt->fetchAll();

        return $this->view->render($response, 'repairs/show.twig', [
            'user' => $user,
            'repair' => $repair,
            'consumed_parts' => $consumedParts,
            'payments' => $payments,
            'paid_total_iqd' => $paidTotalIQD,
            'available_parts' => $availParts,
            'technicians' => $technicians,
            'photos' => $photos,
            'exchange_rate' => $exchangeRate,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int)$args['id'];
        $body = (array)$request->getParsedBody();
        $newStatus = $body['status'] ?? '';

        $allowed = ['CHECKED_IN', 'DIAGNOSING', 'PENDING_CUSTOMER_APPROVAL', 'IN_PROGRESS', 'TESTING_BURN_IN', 'READY_FOR_PICKUP', 'DELIVERED_CLOSED', 'DIDNT_AGREE_RETURNED', 'UNREPAIRABLE'];
        if (!in_array($newStatus, $allowed, true)) {
            return $response->withStatus(302)->withHeader('Location', "/repairs/{$id}?error=Invalid+status");
        }

        $pdo = Database::getAppPdo();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE repairs SET status = :status, updated_at = :now WHERE id = :id");
        $stmt->execute(['status' => $newStatus, 'now' => $now, 'id' => $id]);

        AuditLogger::log('REPAIR', $id, 'STATUS_CHANGE', null, ['status' => $newStatus], (int)$user['id']);

        return $response->withStatus(302)->withHeader('Location', "/repairs/{$id}?success=status_updated");
    }

    public function addPart(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $repairId = (int)$args['id'];
        $body = (array)$request->getParsedBody();

        $partId = (int)($body['part_id'] ?? 0);
        $qty = max(1, (int)($body['quantity'] ?? 1));

        $pdo = Database::getAppPdo();
        $pdo->beginTransaction();

        try {
            // Check part stock
            $pStmt = $pdo->prepare("SELECT name, quantity, selling_price, selling_currency, cost_price, cost_currency FROM parts WHERE id = :id AND deleted_at IS NULL");
            $pStmt->execute(['id' => $partId]);
            $part = $pStmt->fetch();

            if (!$part || (int)$part['quantity'] < $qty) {
                throw new Exception("Insufficient stock for {$part['name']}. Available: {$part['quantity']}");
            }

            $unitPrice = (int)$part['selling_price'];
            $totalPrice = $unitPrice * $qty;
            $newStock = (int)$part['quantity'] - $qty;
            $now = gmdate('Y-m-d H:i:s');

            // 1. Deduct part from stock
            $upPart = $pdo->prepare("UPDATE parts SET quantity = :q, updated_at = :now WHERE id = :id");
            $upPart->execute(['q' => $newStock, 'now' => $now, 'id' => $partId]);

            // 2. Append to stock_movements ledger
            $mvStmt = $pdo->prepare("
                INSERT INTO stock_movements (part_id, user_id, repair_id, quantity_change, quantity_after, movement_type, reason, created_at)
                VALUES (:pid, :uid, :rid, :change, :after, 'REPAIR_USE', :reason, :now)
            ");
            $mvStmt->execute([
                'pid' => $partId,
                'uid' => $user['id'],
                'rid' => $repairId,
                'change' => -$qty,
                'after' => $newStock,
                'reason' => "Used on repair ticket #{$repairId}",
                'now' => $now
            ]);

            // 3. Insert into repair_parts
            $rpStmt = $pdo->prepare("
                INSERT INTO repair_parts (repair_id, part_id, quantity, unit_price, total_price, currency, created_at)
                VALUES (:rid, :pid, :qty, :up, :tp, :curr, :now)
            ");
            $rpStmt->execute([
                'rid' => $repairId,
                'pid' => $partId,
                'qty' => $qty,
                'up' => $unitPrice,
                'tp' => $totalPrice,
                'curr' => $part['selling_currency'],
                'now' => $now
            ]);

            // 4. Update repair totals
            $recalc = $pdo->prepare("
                UPDATE repairs
                SET parts_total = parts_total + :p_add,
                    total_charged = labor_fee + parts_total + :p_add,
                    updated_at = :now
                WHERE id = :id
            ");
            $recalc->execute(['p_add' => $totalPrice, 'now' => $now, 'id' => $repairId]);

            AuditLogger::log('REPAIR', $repairId, 'PART_ADDED', null, ['part' => $part['name'], 'qty' => $qty], (int)$user['id']);
            $pdo->commit();

            return $response->withStatus(302)->withHeader('Location', "/repairs/{$repairId}?success=part_added");
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return $response->withStatus(302)->withHeader('Location', "/repairs/{$repairId}?error=" . urlencode($e->getMessage()));
        }
    }

    public function printTicket(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $pdo = Database::getAppPdo();

        $stmt = $pdo->prepare("
            SELECT r.*, c.name AS customer_name, c.phone AS customer_phone
            FROM repairs r
            JOIN customers c ON r.customer_id = c.id
            WHERE r.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $repair = $stmt->fetch();

        if (!$repair) {
            return $response->withStatus(404);
        }

        return $this->view->render($response, 'repairs/print.twig', [
            'repair' => $repair
        ]);
    }
}
