<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Config\Database;

class FinancialReportTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $m1 = file_get_contents(__DIR__ . '/../migrations/001_create_initial_schema.sql');
        $this->pdo->exec($m1);
        $m2 = file_get_contents(__DIR__ . '/../migrations/002_create_inventory_and_repairs_schema.sql');
        $this->pdo->exec($m2);

        Database::setPdo($this->pdo);

        // Seed users, customers, and exchange rate
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, role, name, is_active)
            VALUES (1, 'owner@test.local', 'hash', 'OWNER', 'Owner User', 1),
                   (2, 'tech@test.local', 'hash', 'TECH', 'Hemin Tech', 1);

            INSERT INTO customers (id, name, phone)
            VALUES (1, 'Customer One', '07501112233');

            INSERT INTO exchange_rates (id, rate_usd_to_iqd, set_by_user_id, effective_date)
            VALUES (1, 1510, 1, '2026-10-10 00:00:00');
        ");
    }

    /**
     * Test 1: P&L calculation accurately balances revenue against operational expenses.
     */
    public function testProfitAndLossCalculation(): void
    {
        // 1. Record customer payments: $100 (@ 1510 = 151,000 IQD) + 50,000 IQD
        $this->pdo->exec("
            INSERT INTO repairs (id, ticket_number, customer_id, tv_brand, tv_model, status, currency, labor_fee, total_charged)
            VALUES (1, 'TICK-1', 1, 'Samsung', '55', 'DELIVERED_CLOSED', 'IQD', 100000, 201000);

            INSERT INTO payments (repair_id, received_by_user_id, amount, currency, exchange_rate_snapshot, amount_iqd_equivalent, is_voided)
            VALUES (1, 1, 100, 'USD', 1510, 151000, 0),
                   (1, 1, 50000, 'IQD', 1510, 50000, 0);
        ");

        $totalRevenueIqd = (int)$this->pdo->query("SELECT SUM(amount_iqd_equivalent) FROM payments WHERE is_voided = 0")->fetchColumn();
        $this->assertEquals(201000, $totalRevenueIqd);

        // 2. Record expenses: Generator diesel 50,000 IQD + Soldering tips $20 (@ 1510 = 30,200 IQD)
        $this->pdo->exec("
            INSERT INTO expenses (category, amount, currency, exchange_rate_snapshot, amount_iqd_equivalent, expense_date, recorded_by_user_id)
            VALUES ('ELECTRICITY_GENERATOR', 50000, 'IQD', 1510, 50000, '2026-10-10', 1),
                   ('TOOLS_EQUIPMENT', 20, 'USD', 1510, 30200, '2026-10-10', 1);
        ");

        $totalExpensesIqd = (int)$this->pdo->query("SELECT SUM(amount_iqd_equivalent) FROM expenses")->fetchColumn();
        $this->assertEquals(80200, $totalExpensesIqd);

        // 3. Net profit
        $netProfitIqd = $totalRevenueIqd - $totalExpensesIqd;
        $this->assertEquals(120800, $netProfitIqd);
    }

    /**
     * Test 2: Technician productivity aggregation.
     */
    public function testTechnicianProductivityAggregation(): void
    {
        $this->pdo->exec("
            INSERT INTO repairs (id, ticket_number, customer_id, technician_id, tv_brand, tv_model, status, currency, labor_fee, total_charged)
            VALUES (10, 'TICK-10', 1, 2, 'LG', 'OLED', 'DELIVERED_CLOSED', 'USD', 35, 35),
                   (11, 'TICK-11', 1, 2, 'Sony', '55', 'IN_PROGRESS', 'USD', 25, 25);
        ");

        $stmt = $this->pdo->prepare("
            SELECT COUNT(id) AS total_assigned,
                   SUM(CASE WHEN status = 'DELIVERED_CLOSED' THEN 1 ELSE 0 END) AS completed,
                   SUM(labor_fee) AS labor_generated
            FROM repairs
            WHERE technician_id = 2
        ");
        $stmt->execute();
        $stats = $stmt->fetch();

        $this->assertEquals(2, (int)$stats['total_assigned']);
        $this->assertEquals(1, (int)$stats['completed']);
        $this->assertEquals(60, (int)$stats['labor_generated']);
    }
}
