<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }
}

echo "Running database migrations...\n";

try {
    $pdo = Database::getMigrationPdo();

    // Ensure schema_migrations table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(255) PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $files = glob(__DIR__ . '/../migrations/*.sql');
    sort($files);

    foreach ($files as $file) {
        $version = basename($file);
        $checkStmt = $pdo->prepare("SELECT version FROM schema_migrations WHERE version = :version");
        $checkStmt->execute(['version' => $version]);
        if ($checkStmt->fetch()) {
            echo "Skipping already applied: {$version}\n";
            continue;
        }

        echo "Applying {$version}... ";
        $sql = file_get_contents($file);

        // Execute queries
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $recordStmt = $pdo->prepare("INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :now)");
            $recordStmt->execute([
                'version' => $version,
                'now' => gmdate('Y-m-d H:i:s')
            ]);
            $pdo->commit();
            echo "DONE\n";
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo "FAILED: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo "All migrations applied successfully.\n";
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
