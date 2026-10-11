<?php

namespace App\Security;

use PDO;
use App\Config\Database;

class RateLimiter
{
    private PDO $pdo;
    private const MAX_LOGIN_FAILURES = 10;
    private const LOCKOUT_MINUTES = 15;
    private const MAX_GLOBAL_IP_ATTEMPTS = 30;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getAppPdo();
    }

    public function recordLoginAttempt(string $email, string $ip, bool $success): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (email, ip_address, successful, attempted_at) 
            VALUES (:email, :ip, :success, :now)
        ");
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'ip' => $ip,
            'success' => $success ? 1 : 0,
            'now' => gmdate('Y-m-d H:i:s')
        ]);

        if (!$success) {
            // Check if failure threshold reached for this email+ip
            if ($this->getFailedAttemptsCount($email, $ip) >= self::MAX_LOGIN_FAILURES) {
                // Lock user record if exists
                $lockStmt = $this->pdo->prepare("UPDATE users SET is_locked = 1 WHERE email = :email");
                $lockStmt->execute(['email' => strtolower(trim($email))]);
            }
        }
    }

    public function isLoginLocked(string $email, string $ip): bool
    {
        $cleanEmail = strtolower(trim($email));

        // 1. Check if user row is explicitly marked locked in DB
        $userStmt = $this->pdo->prepare("SELECT is_locked FROM users WHERE email = :email");
        $userStmt->execute(['email' => $cleanEmail]);
        $user = $userStmt->fetch();
        if ($user && !empty($user['is_locked'])) {
            return true;
        }

        // 2. Check failed attempts for this email+ip in last 15 minutes
        if ($this->getFailedAttemptsCount($cleanEmail, $ip) >= self::MAX_LOGIN_FAILURES) {
            return true;
        }

        // 3. Check global IP attempts in last 15 minutes
        $since = gmdate('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));
        $ipStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total 
            FROM login_attempts 
            WHERE ip_address = :ip AND attempted_at >= :since AND successful = 0
        ");
        $ipStmt->execute(['ip' => $ip, 'since' => $since]);
        $globalCount = (int)$ipStmt->fetchColumn();

        return $globalCount >= self::MAX_GLOBAL_IP_ATTEMPTS;
    }

    public function getFailedAttemptsCount(string $email, string $ip): int
    {
        $since = gmdate('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE email = :email AND ip_address = :ip AND attempted_at >= :since AND successful = 0
        ");
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'ip' => $ip,
            'since' => $since
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function unlockAccount(int $userId): bool
    {
        // Fetch user email
        $stmt = $this->pdo->prepare("SELECT email FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return false;
        }

        // Unlock user
        $unlock = $this->pdo->prepare("UPDATE users SET is_locked = 0 WHERE id = :id");
        $unlock->execute(['id' => $userId]);

        // Clear failed attempts for this email
        $clear = $this->pdo->prepare("DELETE FROM login_attempts WHERE email = :email");
        $clear->execute(['email' => $user['email']]);

        return true;
    }

    /**
     * Generic rate limiter for API endpoints (e.g. heartbeat or write endpoints)
     */
    public function checkRateLimit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        // Simple in-session or db rate tracker
        $sessionKey = '_rl_' . $key;
        $now = time();
        $history = $_SESSION[$sessionKey] ?? [];
        
        // Filter out expired timestamps
        $history = array_filter($history, fn($ts) => ($now - $ts) < $windowSeconds);
        
        if (count($history) >= $maxRequests) {
            return false;
        }

        $history[] = $now;
        $_SESSION[$sessionKey] = $history;
        return true;
    }
}
