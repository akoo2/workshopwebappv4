<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Config\Database;
use App\Security\Csrf;
use App\Security\AuditLogger;
use App\Security\Validator;

class PartRequestController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $pdo = Database::getAppPdo();

        $stmt = $pdo->query("
            SELECT pr.*, u.name AS requested_by_name, m.name AS managed_by_name,
                   r.ticket_number
            FROM part_requests pr
            JOIN users u ON pr.requested_by_user_id = u.id
            LEFT JOIN users m ON pr.managed_by_user_id = m.id
            LEFT JOIN repairs r ON pr.repair_id = r.id
            ORDER BY pr.created_at DESC
        ");
        $requests = $stmt->fetchAll();

        return $this->view->render($response, 'inventory/requests.twig', [
            'user' => $user,
            'requests' => $requests,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();

        $validator = Validator::validate($body, [
            'part_name' => ['required', 'min_len:2', 'max_len:150'],
            'description' => ['required', 'min_len:2'],
            'quantity' => ['required', 'integer']
        ]);

        if ($validator->fails()) {
            return $response->withStatus(302)->withHeader('Location', '/inventory/requests?error=' . urlencode($validator->getFirstError()));
        }

        $data = $validator->getData();
        $repairId = !empty($body['repair_id']) ? (int)$body['repair_id'] : null;

        $pdo = Database::getAppPdo();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO part_requests (repair_id, part_name, description, quantity, requested_by_user_id, status, created_at, updated_at)
            VALUES (:repair_id, :part_name, :desc, :qty, :user_id, 'REQUESTED', :now, :now)
        ");
        $stmt->execute([
            'repair_id' => $repairId,
            'part_name' => $data['part_name'],
            'desc' => $data['description'],
            'qty' => max(1, (int)$data['quantity']),
            'user_id' => $user['id'],
            'now' => $now
        ]);

        $requestId = (int)$pdo->lastInsertId();
        AuditLogger::log('PART_REQUEST', $requestId, 'CREATE', null, $data, (int)$user['id']);

        return $response->withStatus(302)->withHeader('Location', '/inventory/requests?success=requested');
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int)$args['id'];
        $body = (array)$request->getParsedBody();

        $newStatus = $body['status'] ?? '';
        $note = trim((string)($body['resolution_note'] ?? ''));

        if (!in_array($newStatus, ['REQUESTED', 'ORDERED', 'RECEIVED', 'CANCELLED'], true)) {
            return $response->withStatus(302)->withHeader('Location', '/inventory/requests?error=Invalid+status');
        }

        $pdo = Database::getAppPdo();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE part_requests
            SET status = :status, resolution_note = :note, managed_by_user_id = :manager_id, updated_at = :now
            WHERE id = :id
        ");
        $stmt->execute([
            'status' => $newStatus,
            'note' => $note ?: null,
            'manager_id' => $user['id'],
            'now' => $now,
            'id' => $id
        ]);

        AuditLogger::log('PART_REQUEST', $id, 'STATUS_UPDATE', null, ['status' => $newStatus, 'note' => $note], (int)$user['id']);

        return $response->withStatus(302)->withHeader('Location', '/inventory/requests?success=status_updated');
    }
}
