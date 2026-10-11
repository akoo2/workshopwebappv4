<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Config\Database;
use App\Security\Csrf;
use App\Security\AuditLogger;
use App\Security\Validator;

class ExpenseController
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
            SELECT e.*, u.name AS recorded_by_name
            FROM expenses e
            JOIN users u ON e.recorded_by_user_id = u.id
            ORDER BY e.expense_date DESC, e.created_at DESC
        ");
        $expenses = $stmt->fetchAll();

        // Calculate total in IQD
        $totalIqd = 0;
        foreach ($expenses as $e) {
            $totalIqd += (int)$e['amount_iqd_equivalent'];
        }

        return $this->view->render($response, 'expenses/index.twig', [
            'user' => $user,
            'expenses' => $expenses,
            'total_iqd' => $totalIqd,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();

        $validator = Validator::validate($body, [
            'category' => ['required', 'in:RENT,ELECTRICITY_GENERATOR,TOOLS_EQUIPMENT,SALARIES_COMMISSIONS,SUPPLIES_CONSUMABLES,OTHER'],
            'amount' => ['required', 'numeric'],
            'currency' => ['in:USD,IQD'],
            'expense_date' => ['required']
        ]);

        if ($validator->fails()) {
            return $response->withStatus(302)->withHeader('Location', '/expenses?error=' . urlencode($validator->getFirstError()));
        }

        $data = $validator->getData();
        $amount = (int)$data['amount'];
        $curr = $data['currency'] ?? 'IQD';

        $pdo = Database::getAppPdo();
        $rateStmt = $pdo->query("SELECT rate_usd_to_iqd FROM exchange_rates ORDER BY effective_date DESC LIMIT 1");
        $rateSnapshot = (int)($rateStmt->fetchColumn() ?: 1510);
        $amountIqd = ($curr === 'USD') ? ($amount * $rateSnapshot) : $amount;

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO expenses (
                category, amount, currency, exchange_rate_snapshot, amount_iqd_equivalent,
                expense_date, notes, recorded_by_user_id, created_at
            ) VALUES (
                :cat, :amt, :curr, :rate, :iqd,
                :edate, :notes, :uid, :now
            )
        ");

        $stmt->execute([
            'cat' => $data['category'],
            'amt' => $amount,
            'curr' => $curr,
            'rate' => $rateSnapshot,
            'iqd' => $amountIqd,
            'edate' => $data['expense_date'],
            'notes' => trim($body['notes'] ?? '') ?: null,
            'uid' => $user['id'],
            'now' => $now
        ]);

        $expenseId = (int)$pdo->lastInsertId();
        AuditLogger::log('EXPENSE', $expenseId, 'CREATE', null, $data, (int)$user['id']);

        return $response->withStatus(302)->withHeader('Location', '/expenses?success=recorded');
    }

    public function exportCsv(Request $request, Response $response): Response
    {
        $pdo = Database::getAppPdo();
        $stmt = $pdo->query("
            SELECT e.id, e.category, e.amount, e.currency, e.amount_iqd_equivalent, e.expense_date, e.notes, u.name AS recorded_by
            FROM expenses e
            JOIN users u ON e.recorded_by_user_id = u.id
            ORDER BY e.expense_date DESC
        ");
        $rows = $stmt->fetchAll();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'Category', 'Amount', 'Currency', 'Amount (IQD Eq.)', 'Date', 'Notes', 'Recorded By']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['category'],
                $row['amount'],
                $row['currency'],
                $row['amount_iqd_equivalent'],
                $row['expense_date'],
                $row['notes'],
                $row['recorded_by']
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="workshop_expenses_' . date('Ymd') . '.csv"');
    }
}
