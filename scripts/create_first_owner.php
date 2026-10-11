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

if (php_sapi_name() !== 'cli') {
    echo "This script can only be run via CLI.\n";
    exit(1);
}

$pdo = Database::getAppPdo();

// Check if an Owner already exists
$checkStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'OWNER'");
$ownerCount = (int)$checkStmt->fetchColumn();

if ($ownerCount > 0 && !in_array('--force', $argv, true)) {
    echo "An Owner account already exists in the system. Use --force if you intentionally want to create another.\n";
    exit(0);
}

$email = $argv[1] ?? 'owner@workshop.local';
$password = $argv[2] ?? 'OwnerSecret2026!';
$name = $argv[3] ?? 'Workshop Owner';

echo "Creating initial Owner account:\n";
echo "Name: {$name}\n";
echo "Email: {$email}\n";

$hashAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
$hash = password_hash($password, $hashAlgo);

$now = gmdate('Y-m-d H:i:s');
$stmt = $pdo->prepare("
    INSERT INTO users (email, password_hash, role, name, phone, language, is_active, must_change_password, is_locked, created_at, updated_at)
    VALUES (:email, :hash, 'OWNER', :name, NULL, 'en', 1, 0, 0, :now, :now)
");

try {
    $stmt->execute([
        'email' => strtolower(trim($email)),
        'hash' => $hash,
        'name' => $name,
        'now' => $now
    ]);
    echo "Initial Owner account created successfully! You may now sign in at /login.\n";
} catch (\Throwable $e) {
    echo "Error creating Owner: " . $e->getMessage() . "\n";
    exit(1);
}
