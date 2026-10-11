<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Config\Database;
use App\Security\Csrf;

class ReportController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function financial(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $pdo = Database::getAppPdo();

        // Total payments collected (IQD equivalent)
        $revStmt = $pdo->query("SELECT COALESCE(SUM(amount_iqd_equivalent), 0) FROM payments WHERE is_voided = 0");
        $totalRevenueIqd = (int)$revStmt->fetchColumn();

        // Total expenses (IQD equivalent)
        $expStmt = $pdo->query("SELECT COALESCE(SUM(amount_iqd_equivalent), 0) FROM expenses");
        $totalExpensesIqd = (int)$expStmt->fetchColumn();

        // Current exchange rate
        $rateStmt = $pdo->query("SELECT rate_usd_to_iqd FROM exchange_rates ORDER BY effective_date DESC LIMIT 1");
        $exchangeRate = (int)($rateStmt->fetchColumn() ?: 1510);

        // Expense breakdown by category
        $catStmt = $pdo->query("
            SELECT category, SUM(amount_iqd_equivalent) as cat_total
            FROM expenses
            GROUP BY category
            ORDER BY cat_total DESC
        ");
        $expensesByCategory = $catStmt->fetchAll();

        // Net Profit in IQD and USD
        $netProfitIqd = $totalRevenueIqd - $totalExpensesIqd;
        $netProfitUsd = round($netProfitIqd / $exchangeRate, 2);

        return $this->view->render($response, 'reports/index.twig', [
            'user' => $user,
            'total_revenue_iqd' => $totalRevenueIqd,
            'total_expenses_iqd' => $totalExpensesIqd,
            'net_profit_iqd' => $netProfitIqd,
            'net_profit_usd' => $netProfitUsd,
            'exchange_rate' => $exchangeRate,
            'expenses_by_category' => $expensesByCategory,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function technicians(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $pdo = Database::getAppPdo();

        // Productivity per tech
        $stmt = $pdo->query("
            SELECT u.id, u.name,
                   COUNT(r.id) AS total_assigned,
                   SUM(CASE WHEN r.status = 'DELIVERED_CLOSED' THEN 1 ELSE 0 END) AS completed_count,
                   SUM(CASE WHEN r.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS in_progress_count,
                   COALESCE(SUM(r.labor_fee), 0) AS total_labor_generated
            FROM users u
            LEFT JOIN repairs r ON r.technician_id = u.id AND r.deleted_at IS NULL
            WHERE u.is_active = 1
            GROUP BY u.id, u.name
            ORDER BY completed_count DESC
        ");
        $techStats = $stmt->fetchAll();

        return $this->view->render($response, 'reports/technicians.twig', [
            'user' => $user,
            'technicians' => $techStats,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function aging(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $pdo = Database::getAppPdo();

        // Unpaid or partially paid tickets with date calculations
        $stmt = $pdo->query("
            SELECT r.*, c.name AS customer_name, c.phone AS customer_phone,
                   (julianday('now') - julianday(r.created_at)) AS days_open,
                   (SELECT COALESCE(SUM(amount_iqd_equivalent), 0) FROM payments p WHERE p.repair_id = r.id AND p.is_voided = 0) AS paid_iqd
            FROM repairs r
            JOIN customers c ON r.customer_id = c.id
            WHERE r.status != 'DELIVERED_CLOSED' AND r.deleted_at IS NULL
            ORDER BY r.created_at ASC
        ");
        $openTickets = $stmt->fetchAll();

        $agingBuckets = [
            '0_7' => [],
            '8_30' => [],
            '31_60' => [],
            '60_plus' => []
        ];

        foreach ($openTickets as $t) {
            $days = (int)($t['days_open'] ?? 0);
            if ($days <= 7) {
                $agingBuckets['0_7'][] = $t;
            } elseif ($days <= 30) {
                $agingBuckets['8_30'][] = $t;
            } elseif ($days <= 60) {
                $agingBuckets['31_60'][] = $t;
            } else {
                $agingBuckets['60_plus'][] = $t;
            }
        }

        return $this->view->render($response, 'reports/aging.twig', [
            'user' => $user,
            'buckets' => $agingBuckets,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function exportRepairsCsv(Request $request, Response $response): Response
    {
        $pdo = Database::getAppPdo();
        $stmt = $pdo->query("
            SELECT r.ticket_number, c.name AS customer_name, c.phone AS customer_phone,
                   r.tv_brand, r.tv_model, r.status, r.currency, r.labor_fee, r.parts_total, r.total_charged,
                   r.created_at
            FROM repairs r
            JOIN customers c ON r.customer_id = c.id
            WHERE r.deleted_at IS NULL
            ORDER BY r.created_at DESC
        ");
        $rows = $stmt->fetchAll();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Ticket #', 'Customer Name', 'Phone', 'Brand', 'Model', 'Status', 'Currency', 'Labor', 'Parts', 'Total Charged', 'Check-in Date']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['ticket_number'],
                $row['customer_name'],
                $row['customer_phone'],
                $row['tv_brand'],
                $row['tv_model'],
                $row['status'],
                $row['currency'],
                $row['labor_fee'],
                $row['parts_total'],
                $row['total_charged'],
                $row['created_at']
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="workshop_repairs_' . date('Ymd') . '.csv"');
    }

    public function exportInventoryCsv(Request $request, Response $response): Response
    {
        $pdo = Database::getAppPdo();
        $stmt = $pdo->query("
            SELECT p.id, c.name AS category, p.name, p.serial_number, p.quantity, p.min_stock,
                   p.cost_price, p.cost_currency, p.selling_price, p.selling_currency
            FROM parts p
            JOIN part_categories c ON p.category_id = c.id
            WHERE p.deleted_at IS NULL
            ORDER BY c.name ASC, p.name ASC
        ");
        $rows = $stmt->fetchAll();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'Category', 'Part Name', 'Serial / PN', 'Quantity in Stock', 'Min Stock Level', 'Cost Price', 'Cost Currency', 'Selling Price', 'Selling Currency']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['category'],
                $row['name'],
                $row['serial_number'],
                $row['quantity'],
                $row['min_stock'],
                $row['cost_price'],
                $row['cost_currency'],
                $row['selling_price'],
                $row['selling_currency']
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="workshop_inventory_' . date('Ymd') . '.csv"');
    }
}
