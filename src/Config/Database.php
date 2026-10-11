<?php

namespace App\Config;

use PDO;
use Exception;

class Database
{
    private static ?PDO $appPdo = null;
    private static ?PDO $migrationPdo = null;

    public static function getAppPdo(): PDO
    {
        if (self::$appPdo === null) {
            self::$appPdo = self::createConnection(false);
        }
        return self::$appPdo;
    }

    public static function getMigrationPdo(): PDO
    {
        if (self::$migrationPdo === null) {
            self::$migrationPdo = self::createConnection(true);
        }
        return self::$migrationPdo;
    }

    public static function setPdo(?PDO $pdo): void
    {
        self::$appPdo = $pdo;
        self::$migrationPdo = $pdo;
    }

    private static function createConnection(bool $isMigration): PDO
    {
        $driver = $_ENV['DB_DRIVER'] ?? 'sqlite';

        if ($driver === 'sqlite') {
            $dbPath = $_ENV['DB_DATABASE'] ?? __DIR__ . '/../../storage/database.sqlite';
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            // Enable foreign keys in SQLite
            $pdo->exec("PRAGMA foreign_keys = ON;");
            return $pdo;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? 3306;
        $dbName = $_ENV['DB_DATABASE'] ?? 'tv_workshop';
        $user = $isMigration ? ($_ENV['DB_MIGRATION_USER'] ?? $_ENV['DB_USERNAME'] ?? 'tv_app_user')
                             : ($_ENV['DB_USERNAME'] ?? 'tv_app_user');
        $pass = $isMigration ? ($_ENV['DB_MIGRATION_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? '')
                             : ($_ENV['DB_PASSWORD'] ?? '');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        return new PDO($dsn, $user, $pass, $options);
    }
}
