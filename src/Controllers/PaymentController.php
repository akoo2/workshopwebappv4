<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Config\Database;
use App\Security\Csrf;
use App\Security\AuditLogger;
use App\Security\Validator;
use Exception;

class PaymentController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function addPayment(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $repairId = (int)$args['id'];
        $body = (array)$request->getParsedBody();

        $amount = (int)($body['amount'] ?? 0);
        $curr = in_array($body['currency'] ?? '', ['USD', 'IQD'], true) ? $body['currency'] : 'IQD';
        $method = in_array($body['payment_method'] ?? '', ['CASH', 'CARD', 'TRANSFER', 'OTHER'], true) ? $body['payment_method'] : 'CASH';

        if ($amount <= 0) {
            return $response->withStatus(302)->withHeader('Location', "/repairs/{$repairId}?error=Payment+amount+must+be+greater+than+zero");
        }

        $pdo = Database::getAppPdo();

        // Get current exchange rate snapshot
        $rateStmt = $pdo->query("SELECT rate_usd_to_iqd FROM exchange_rates ORDER BY effective_date DESC LIMIT 1");
        $rateSnapshot = (int)($rateStmt->fetchColumn() ?: 1510);

        $amountIqd = ($curr === 'USD') ? ($amount * $rateSnapshot) : $amount;
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO payments (
                repair_id, received_by_user_id, amount, currency,
                exchange_rate_snapshot, amount_iqd_equivalent, payment_method,
                notes, is_voided, created_at
            ) VALUES (
                :rid, :uid, :amt, :curr,
                :rate, :iqd, :meth,
                :notes, 0, :now
            )
        ");

        $stmt->execute([
            'rid' => $repairId,
            'uid' => $user['id'],
            'amt' => $amount,
            'curr' => $curr,
            'rate' => $rateSnapshot,
            'iqd' => $amountIqd,
            'meth' => $method,
            'notes' => trim($body['notes'] ?? '') ?: null,
            'now' => $now
        ]);

        $paymentId = (int)$pdo->lastInsertId();

        AuditLogger::log('PAYMENT', $paymentId, 'CREATE', null, [
            'repair_id' => $repairId,
            'amount' => $amount,
            'currency' => $curr,
            'rate_snapshot' => $rateSnapshot
        ], (int)$user['id']);

        return $response->withStatus(302)->withHeader('Location', "/repairs/{$repairId}?success=payment_recorded");
    }

    public function voidPayment(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int)$args['id'];
        $body = (array)$request->getParsedBody();
        $reason = trim((string)($body['void_reason'] ?? ''));

        if (empty($reason)) {
            return $response->withStatus(302)->withHeader('Location', "/repairs?error=Void+reason+is+mandatory");
        }

        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id AND is_voided = 0");
        $stmt->execute(['id' => $id]);
        $payment = $stmt->fetch();

        if (!$payment) {
            return $response->withStatus(404);
        }

        $now = gmdate('Y-m-d H:i:s');
        $voidStmt = $pdo->prepare("
            UPDATE payments
            SET is_voided = 1, void_reason = :reason, voided_by_user_id = :uid, voided_at = :now
            WHERE id = :id
        ");
        $voidStmt->execute([
            'reason' => $reason,
            'uid' => $user['id'],
            'now' => $now,
            'id' => $id
        ]);

        AuditLogger::log('PAYMENT', $id, 'VOID', $payment, ['is_voided' => 1, 'reason' => $reason], (int)$user['id']);

        $repairId = $payment['repair_id'];
        return $response->withStatus(302)->withHeader('Location', "/repairs/{$repairId}?success=payment_voided");
    }

    public function debtList(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $pdo = Database::getAppPdo();

        $stmt = $pdo->query("
            SELECT dn.*, c.name AS customer_name, c.phone AS customer_phone,
                   r.ticket_number
            FROM debt_notes dn
            JOIN customers c ON dn.customer_id = c.id
            LEFT JOIN repairs r ON dn.repair_id = r.id
            WHERE dn.is_settled = 0 AND dn.is_written_off = 0
            ORDER BY dn.created_at DESC
        ");
        $debts = $stmt->fetchAll();

        return $this->view->render($response, 'payments/debts.twig', [
            'user' => $user,
            'debts' => $debts,
            'csrf_token' => Csrf::getToken()
        ]);
    }
}
