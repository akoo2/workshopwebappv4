<?php

namespace App\Security;

use PDO;
use App\Config\Database;

class AuditLogger
{
    private static array $sensitiveKeys = [
        'password',
        'password_hash',
        'token',
        '_csrf_token',
        'session_id',
        'id_hash',
        'payload',
        'temp_password',
        'current_password',
        'new_password',
        'confirm_password'
    ];

    public static function log(
        string $entityType,
        int $entityId,
        string $action,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?int $userId = null
    ): void {
        try {
            $pdo = Database::getAppPdo();
            $actorId = $userId ?? ($_SESSION['user_id'] ?? null);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $sanitizedBefore = $beforeState !== null ? self::sanitize($beforeState) : null;
            $sanitizedAfter = $afterState !== null ? self::sanitize($afterState) : null;

            $stmt = $pdo->prepare("
                INSERT INTO audit_log (user_id, ip_address, entity_type, entity_id, action, before_state, after_state, created_at)
                VALUES (:user_id, :ip, :entity_type, :entity_id, :action, :before_state, :after_state, :created_at)
            ");

            $stmt->execute([
                'user_id' => $actorId,
                'ip' => $ip,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => strtoupper($action),
                'before_state' => $sanitizedBefore !== null ? json_encode($sanitizedBefore, JSON_UNESCAPED_UNICODE) : null,
                'after_state' => $sanitizedAfter !== null ? json_encode($sanitizedAfter, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => gmdate('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            // Audit log write failure must not crash operation, but log to error log
            error_log("Failed to write to audit_log: " . $e->getMessage());
        }
    }

    public static function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);
            if (in_array($lowerKey, self::$sensitiveKeys, true)) {
                continue; // Strictly omit sensitive fields
            }

            if (is_array($value)) {
                $clean[$key] = self::sanitize($value);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
}
