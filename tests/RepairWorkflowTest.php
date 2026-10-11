<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;
use App\Config\Database;

class RepairWorkflowTest extends TestCase
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

        // Seed customer, technician, part
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, role, name, is_active)
            VALUES (1, 'tech@test.local', 'hash', 'TECH', 'Hemin Tech', 1);

            INSERT INTO customers (id, name, phone)
            VALUES (1, 'Dler Omar', '07504445566');

            INSERT INTO part_categories (id, name) VALUES (1, 'Backlight');
            INSERT INTO parts (id, category_id, name, quantity, min_stock, cost_price, selling_price, selling_currency)
            VALUES (1, 1, 'Samsung 55 LED Strip', 5, 2, 15, 35, 'USD');
        ");
    }

    /**
     * Test 1: Repair ticket creation.
     */
    public function testRepairTicketCreation(): void
    {
        $ticketNo = 'TICK-261010-ABCD';
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO repairs (
                ticket_number, customer_id, technician_id, tv_brand, tv_model,
                fault_description, status, currency, labor_fee, parts_total, total_charged,
                created_at, updated_at
            ) VALUES (
                :ticket, 1, 1, 'Samsung', '55AU7000',
                'No picture, sound works', 'CHECKED_IN', 'USD', 25, 0, 25,
                :now, :now
            )
        ");
        $stmt->execute(['ticket' => $ticketNo, 'now' => $now]);

        $repairId = (int)$this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $repairId);

        $repair = $this->pdo->query("SELECT * FROM repairs WHERE id = {$repairId}")->fetch();
        $this->assertEquals('CHECKED_IN', $repair['status']);
        $this->assertEquals(25, (int)$repair['labor_fee']);
        $this->assertEquals(25, (int)$repair['total_charged']);
    }

    /**
     * Test 2: Part consumption deducts stock and recalculates repair total.
     */
    public function testPartConsumptionDeductsInventory(): void
    {
        // Create repair
        $this->pdo->exec("
            INSERT INTO repairs (id, ticket_number, customer_id, tv_brand, tv_model, fault_description, status, currency, labor_fee, parts_total, total_charged)
            VALUES (10, 'TICK-TEST-1', 1, 'Samsung', '55AU7000', 'Dark screen', 'IN_PROGRESS', 'USD', 25, 0, 25);
        ");

        $this->pdo->beginTransaction();

        $partId = 1;
        $qty = 2;
        $unitPrice = 35;
        $totalPrice = $unitPrice * $qty;

        // Deduct inventory
        $this->pdo->prepare("UPDATE parts SET quantity = quantity - :q WHERE id = :id")->execute(['q' => $qty, 'id' => $partId]);

        // Add to repair_parts
        $this->pdo->prepare("
            INSERT INTO repair_parts (repair_id, part_id, quantity, unit_price, total_price, currency)
            VALUES (10, :pid, :q, :up, :tp, 'USD')
        ")->execute(['pid' => $partId, 'q' => $qty, 'up' => $unitPrice, 'tp' => $totalPrice]);

        // Update repair totals
        $this->pdo->prepare("
            UPDATE repairs
            SET parts_total = parts_total + :p_add,
                total_charged = labor_fee + parts_total + :p_add
            WHERE id = 10
        ")->execute(['p_add' => $totalPrice]);

        $this->pdo->commit();

        // Verify remaining stock
        $remainingStock = (int)$this->pdo->query("SELECT quantity FROM parts WHERE id = 1")->fetchColumn();
        $this->assertEquals(3, $remainingStock); // Was 5, consumed 2 -> 3 left

        // Verify repair total
        $repair = $this->pdo->query("SELECT parts_total, total_charged FROM repairs WHERE id = 10")->fetch();
        $this->assertEquals(70, (int)$repair['parts_total']);
        $this->assertEquals(95, (int)$repair['total_charged']); // 25 labor + 70 parts = 95
    }

    /**
     * Test 3: Payment recording and payment voiding with reason.
     */
    public function testPaymentAndVoidWorkflow(): void
    {
        $this->pdo->exec("
            INSERT INTO payments (id, repair_id, received_by_user_id, amount, currency, exchange_rate_snapshot, amount_iqd_equivalent, is_voided)
            VALUES (1, 10, 1, 50, 'USD', 1510, 75500, 0);
        ");

        $activePaid = (int)$this->pdo->query("SELECT SUM(amount) FROM payments WHERE repair_id = 10 AND is_voided = 0")->fetchColumn();
        $this->assertEquals(50, $activePaid);

        // Void payment
        $this->pdo->prepare("
            UPDATE payments
            SET is_voided = 1, void_reason = 'Incorrect amount entered', voided_by_user_id = 1, voided_at = '2026-10-10 13:00:00'
            WHERE id = 1
        ")->execute();

        $activePaidAfterVoid = (int)$this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE repair_id = 10 AND is_voided = 0")->fetchColumn();
        $this->assertEquals(0, $activePaidAfterVoid);
    }
}
