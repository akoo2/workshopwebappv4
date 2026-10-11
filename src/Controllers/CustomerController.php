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

class CustomerController
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
        $query = trim($params['q'] ?? '');

        $pdo = Database::getAppPdo();

        if (!empty($query)) {
            $stmt = $pdo->prepare("
                SELECT c.*,
                       (SELECT COUNT(*) FROM repairs r WHERE r.customer_id = c.id AND r.deleted_at IS NULL) AS total_repairs
                FROM customers c
                WHERE c.deleted_at IS NULL AND (c.name LIKE :q OR c.phone LIKE :q OR c.alt_phone LIKE :q)
                ORDER BY c.name ASC
                LIMIT 50
            ");
            $stmt->execute(['q' => "%{$query}%"]);
        } else {
            $stmt = $pdo->query("
                SELECT c.*,
                       (SELECT COUNT(*) FROM repairs r WHERE r.customer_id = c.id AND r.deleted_at IS NULL) AS total_repairs
                FROM customers c
                WHERE c.deleted_at IS NULL
                ORDER BY c.created_at DESC
                LIMIT 50
            ");
        }
        $customers = $stmt->fetchAll();

        return $this->view->render($response, 'customers/index.twig', [
            'user' => $user,
            'customers' => $customers,
            'query' => $query,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        return $this->view->render($response, 'customers/form.twig', [
            'user' => $user,
            'customer' => null,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();

        $validator = Validator::validate($body, [
            'name' => ['required', 'min_len:2', 'max_len:100'],
            'phone' => ['required', 'min_len:7', 'max_len:30'],
            'alt_phone' => ['max_len:30'],
            'address' => ['max_len:255']
        ]);

        if ($validator->fails()) {
            return $this->view->render($response->withStatus(400), 'customers/form.twig', [
                'user' => $user,
                'customer' => $body,
                'error' => $validator->getFirstError(),
                'csrf_token' => Csrf::getToken()
            ]);
        }

        $data = $validator->getData();
        $pdo = Database::getAppPdo();

        // Check if customer with phone already exists
        $checkStmt = $pdo->prepare("SELECT id, name FROM customers WHERE phone = :phone AND deleted_at IS NULL");
        $checkStmt->execute(['phone' => $data['phone']]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            return $this->view->render($response->withStatus(400), 'customers/form.twig', [
                'user' => $user,
                'customer' => $body,
                'error' => "A customer with phone {$data['phone']} already exists ({$existing['name']}).",
                'csrf_token' => Csrf::getToken()
            ]);
        }

        $now = gmdate('Y-m-d H:i:s');
        $insert = $pdo->prepare("
            INSERT INTO customers (name, phone, alt_phone, address, created_at, updated_at)
            VALUES (:name, :phone, :alt_phone, :address, :now, :now)
        ");
        $insert->execute([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'alt_phone' => $data['alt_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'now' => $now
        ]);

        $customerId = (int)$pdo->lastInsertId();
        AuditLogger::log('CUSTOMER', $customerId, 'CREATE', null, $data, (int)$user['id']);

        return $response->withStatus(302)->withHeader('Location', "/customers/{$customerId}?success=created");
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int)$args['id'];
        $pdo = Database::getAppPdo();

        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $customer = $stmt->fetch();

        if (!$customer) {
            return $response->withStatus(404)->withHeader('Location', '/customers');
        }

        // Fetch customer repairs
        $repairsStmt = $pdo->prepare("
            SELECT r.*, u.name AS tech_name
            FROM repairs r
            LEFT JOIN users u ON r.technician_id = u.id
            WHERE r.customer_id = :id AND r.deleted_at IS NULL
            ORDER BY r.created_at DESC
        ");
        $repairsStmt->execute(['id' => $id]);
        $repairs = $repairsStmt->fetchAll();

        return $this->view->render($response, 'customers/show.twig', [
            'user' => $user,
            'customer' => $customer,
            'repairs' => $repairs,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int)$args['id'];
        $body = (array)$request->getParsedBody();
        $pdo = Database::getAppPdo();

        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $customer = $stmt->fetch();

        if (!$customer) {
            return $response->withStatus(404);
        }

        $validator = Validator::validate($body, [
            'name' => ['required', 'min_len:2', 'max_len:100'],
            'phone' => ['required', 'min_len:7', 'max_len:30'],
            'alt_phone' => ['max_len:30'],
            'address' => ['max_len:255']
        ]);

        if ($validator->fails()) {
            return $response->withStatus(302)->withHeader('Location', "/customers/{$id}?error=" . urlencode($validator->getFirstError()));
        }

        $data = $validator->getData();
        $now = gmdate('Y-m-d H:i:s');

        $update = $pdo->prepare("
            UPDATE customers
            SET name = :name, phone = :phone, alt_phone = :alt_phone, address = :address, updated_at = :now
            WHERE id = :id
        ");
        $update->execute([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'alt_phone' => $data['alt_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'now' => $now,
            'id' => $id
        ]);

        AuditLogger::log('CUSTOMER', $id, 'UPDATE', $customer, $data, (int)$user['id']);

        return $response->withStatus(302)->withHeader('Location', "/customers/{$id}?success=updated");
    }
}
