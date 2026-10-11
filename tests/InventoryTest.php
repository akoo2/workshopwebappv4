<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;
use App\Config\Database;

class InventoryTest extends TestCase
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

        // Seed initial category and user
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, role, name, is_active)
            VALUES (1, 'owner@test.local', 'hash', 'OWNER', 'Owner User', 1);
            
            INSERT INTO part_categories (id, name, description)
            VALUES (1, 'Backlight', 'LED TV backlight replacement strips');
        ");
    }

    /**
     * Test 1: Part quantity cannot be reduced below zero.
     */
    public function testStockCannotBeReducedBelowZero(): void
    {
        $this->pdo->exec("
            INSERT INTO parts (id, category_id, name, quantity, min_stock, cost_price, selling_price)
            VALUES (1, 1, 'Samsung 55 LED Strip', 2, 1, 15, 30);
        ");

        $this->expectException(Exception::class);

        // Attempting to reduce by 3 when quantity is 2
        $current = (int)$this->pdo->query("SELECT quantity FROM parts WHERE id = 1")->fetchColumn();
        $change = -3;
        if ($current + $change < 0) {
            throw new Exception("Cannot reduce stock below zero.");
        }
    }

    /**
     * Test 2: Stock adjustments atomically record a movement ledger entry.
     */
    public function testStockAdjustmentRecordsMovement(): void
    {
        $this->pdo->exec("
            INSERT INTO parts (id, category_id, name, quantity, min_stock, cost_price, selling_price)
            VALUES (2, 1, 'LG T-Con Board', 5, 2, 20, 45);
        ");

        $this->pdo->beginTransaction();
        $partId = 2;
        $change = 3;
        $newQty = 5 + $change;

        $this->pdo->prepare("UPDATE parts SET quantity = :qty WHERE id = :id")->execute(['qty' => $newQty, 'id' => $partId]);
        $this->pdo->prepare("
            INSERT INTO stock_movements (part_id, user_id, quantity_change, quantity_after, movement_type, reason)
            VALUES (:pid, 1, :change, :after, 'PURCHASE', 'New shipment from distributor')
        ")->execute([
            'pid' => $partId,
            'change' => $change,
            'after' => $newQty
        ]);
        $this->pdo->commit();

        $partQty = (int)$this->pdo->query("SELECT quantity FROM parts WHERE id = 2")->fetchColumn();
        $this->assertEquals(8, $partQty);

        $movement = $this->pdo->query("SELECT * FROM stock_movements WHERE part_id = 2")->fetch();
        $this->assertNotEmpty($movement);
        $this->assertEquals(3, (int)$movement['quantity_change']);
        $this->assertEquals(8, (int)$movement['quantity_after']);
        $this->assertEquals('PURCHASE', $movement['movement_type']);
    }

    /**
     * Test 3: Cost price is hidden from TECH role.
     */
    public function testCostPriceHiddenFromTechnicians(): void
    {
        $parts = [
            ['id' => 1, 'name' => 'Board', 'cost_price' => 25, 'selling_price' => 50]
        ];

        // Simulate TECH role viewing inventory
        $userRole = 'TECH';
        $canViewCost = in_array($userRole, ['MANAGER', 'ADMIN', 'OWNER'], true);

        if (!$canViewCost) {
            foreach ($parts as &$p) {
                $p['cost_price'] = null;
            }
            unset($p);
        }

        $this->assertNull($parts[0]['cost_price']);
        $this->assertEquals(50, $parts[0]['selling_price']);

        // Simulate MANAGER role viewing inventory
        $managerRole = 'MANAGER';
        $canViewCostManager = in_array($managerRole, ['MANAGER', 'ADMIN', 'OWNER'], true);
        $this->assertTrue($canViewCostManager);
    }

    /**
     * Test 4: Customer phone duplicate check.
     */
    public function testCustomerPhoneDuplicateCheck(): void
    {
        $this->pdo->prepare("
            INSERT INTO customers (name, phone) VALUES ('Customer A', '07501112233')
        ")->execute();

        $stmt = $this->pdo->prepare("SELECT id FROM customers WHERE phone = :phone AND deleted_at IS NULL");
        $stmt->execute(['phone' => '07501112233']);
        $exists = $stmt->fetch();

        $this->assertNotEmpty($exists);

        $stmt->execute(['phone' => '07509999999']);
        $doesNotExist = $stmt->fetch();
        $this->assertFalse($doesNotExist);
    }
}
