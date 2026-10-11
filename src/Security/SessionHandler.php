<?php

namespace App\Security;

use SessionHandlerInterface;
use PDO;
use App\Config\Database;

class SessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $idleLifetimeSeconds;
    private int $absoluteLifetimeSeconds;
    private static bool $isHeartbeatRequest = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getAppPdo();
        $idleDays = (int)($_ENV['SESSION_IDLE_DAYS'] ?? 7);
        $absDays = (int)($_ENV['SESSION_ABSOLUTE_DAYS'] ?? 30);
        
        $this->idleLifetimeSeconds = $idleDays * 86400;
        $this->absoluteLifetimeSeconds = $absDays * 86400;
    }

    public static function setHeartbeatMode(bool $isHeartbeat): void
    {
        self::$isHeartbeatRequest = $isHeartbeat;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $hash = hash('sha256', $id);
        $stmt = $this->pdo->prepare("SELECT payload, last_activity, expires_at FROM sessions WHERE id_hash = :hash");
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();

        if (!$row) {
            return '';
        }

        $now = time();
        $lastActivity = strtotime($row['last_activity']);
        $expiresAt = strtotime($row['expires_at']);

        // Check absolute expiry or idle expiry
        if ($now > $expiresAt || ($now - $lastActivity) > $this->idleLifetimeSeconds) {
            $this->destroy($id);
            return '';
        }

        return (string)$row['payload'];
    }

    public function write(string $id, string $data): bool
    {
        $hash = hash('sha256', $id);
        $userId = $_SESSION['user_id'] ?? 0;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);

        // Check if session row exists
        $stmt = $this->pdo->prepare("SELECT created_at, last_activity, expires_at FROM sessions WHERE id_hash = :hash");
        $stmt->execute(['hash' => $hash]);
        $existing = $stmt->fetch();

        $nowUtc = gmdate('Y-m-d H:i:s');

        if ($existing) {
            // Heartbeat request does NOT refresh idle timer (last_activity)
            if (self::$isHeartbeatRequest) {
                $updateStmt = $this->pdo->prepare("
                    UPDATE sessions 
                    SET user_id = :user_id, ip_address = :ip, user_agent = :ua, payload = :payload
                    WHERE id_hash = :hash
                ");
                return $updateStmt->execute([
                    'user_id' => $userId,
                    'ip' => $ip,
                    'ua' => $ua,
                    'payload' => $data,
                    'hash' => $hash
                ]);
            }

            // Normal request refreshes last_activity
            $updateStmt = $this->pdo->prepare("
                UPDATE sessions 
                SET user_id = :user_id, ip_address = :ip, user_agent = :ua, last_activity = :now, payload = :payload
                WHERE id_hash = :hash
            ");
            return $updateStmt->execute([
                'user_id' => $userId,
                'ip' => $ip,
                'ua' => $ua,
                'now' => $nowUtc,
                'payload' => $data,
                'hash' => $hash
            ]);
        }

        // New session insertion
        $expiresAtUtc = gmdate('Y-m-d H:i:s', time() + $this->absoluteLifetimeSeconds);
        $insertStmt = $this->pdo->prepare("
            INSERT INTO sessions (id_hash, user_id, ip_address, user_agent, last_activity, created_at, expires_at, payload)
            VALUES (:hash, :user_id, :ip, :ua, :now, :now, :expires, :payload)
        ");

        return $insertStmt->execute([
            'hash' => $hash,
            'user_id' => $userId,
            'ip' => $ip,
            'ua' => $ua,
            'now' => $nowUtc,
            'expires' => $expiresAtUtc,
            'payload' => $data
        ]);
    }

    public function destroy(string $id): bool
    {
        $hash = hash('sha256', $id);
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id_hash = :hash");
        return $stmt->execute(['hash' => $hash]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $this->idleLifetimeSeconds);
        $absCutoff = gmdate('Y-m-d H:i:s', time());
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE last_activity < :cutoff OR expires_at < :absCutoff");
        $stmt->execute(['cutoff' => $cutoff, 'absCutoff' => $absCutoff]);
        return $stmt->rowCount();
    }

    public static function revokeUserSessions(int $userId, ?string $exceptSessionId = null): void
    {
        $pdo = Database::getAppPdo();
        if ($exceptSessionId) {
            $exceptHash = hash('sha256', $exceptSessionId);
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = :user_id AND id_hash != :except_hash");
            $stmt->execute(['user_id' => $userId, 'except_hash' => $exceptHash]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
        }
    }

    public static function revokeSessionByHash(string $idHash): void
    {
        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE id_hash = :hash");
        $stmt->execute(['hash' => $idHash]);
    }

    public static function getUserActiveSessions(int $userId): array
    {
        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("
            SELECT id_hash, ip_address, user_agent, last_activity, created_at, expires_at 
            FROM sessions 
            WHERE user_id = :user_id 
            ORDER BY last_activity DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
