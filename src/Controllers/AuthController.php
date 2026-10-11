<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Config\Database;
use App\Security\Csrf;
use App\Security\RateLimiter;
use App\Security\SessionHandler;
use App\Security\AuditLogger;
use App\Security\Validator;
use App\Lang\Translator;

class AuthController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function showLogin(Request $request, Response $response): Response
    {
        // If already logged in, redirect to dashboard
        if (!empty($_SESSION['user_id'])) {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        $queryParams = $request->getQueryParams();
        $errorMessage = null;
        if (isset($queryParams['error']) && $queryParams['error'] === 'account_disabled') {
            $errorMessage = Translator::trans('account_disabled');
        }

        return $this->view->render($response, 'auth/login.twig', [
            'csrf_token' => Csrf::getToken(),
            'error' => $errorMessage
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $email = strtolower(trim($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $rateLimiter = new RateLimiter();

        // 1. Check rate limit
        if ($rateLimiter->isLoginLocked($email, $ip)) {
            return $this->view->render($response->withStatus(429), 'auth/login.twig', [
                'csrf_token' => Csrf::regenerate(),
                'email' => $email,
                'error' => Translator::trans('account_locked')
            ]);
        }

        // 2. Query user from DB
        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("SELECT id, email, password_hash, role, name, language, is_active, must_change_password, is_locked FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        $hashAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

        // Verify credentials with constant-time check
        $valid = false;
        if ($user && !empty($user['password_hash'])) {
            $valid = password_verify($password, $user['password_hash']);
        }

        if (!$valid || !$user) {
            $rateLimiter->recordLoginAttempt($email, $ip, false);
            return $this->view->render($response->withStatus(401), 'auth/login.twig', [
                'csrf_token' => Csrf::regenerate(),
                'email' => $email,
                'error' => Translator::trans('invalid_credentials')
            ]);
        }

        // Check if disabled or locked
        if (empty($user['is_active'])) {
            return $this->view->render($response->withStatus(403), 'auth/login.twig', [
                'csrf_token' => Csrf::regenerate(),
                'error' => Translator::trans('account_disabled')
            ]);
        }

        if (!empty($user['is_locked'])) {
            return $this->view->render($response->withStatus(429), 'auth/login.twig', [
                'csrf_token' => Csrf::regenerate(),
                'error' => Translator::trans('account_locked')
            ]);
        }

        // Successful login
        $rateLimiter->recordLoginAttempt($email, $ip, true);

        // Check password rehash if algorithm cost changed
        if (password_needs_rehash($user['password_hash'], $hashAlgo)) {
            $newHash = password_hash($password, $hashAlgo);
            $rehashStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $rehashStmt->execute(['hash' => $newHash, 'id' => $user['id']]);
        }

        // Regenerate session ID and set session payload
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['language'] = $user['language'] ?? 'en';

        AuditLogger::log('USER', (int)$user['id'], 'LOGIN', null, [
            'email' => $user['email'],
            'ip' => $ip
        ], (int)$user['id']);

        if (!empty($user['must_change_password'])) {
            return $response->withStatus(302)->withHeader('Location', '/profile/password');
        }

        return $response->withStatus(302)->withHeader('Location', '/');
    }

    public function logout(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            AuditLogger::log('USER', (int)$userId, 'LOGOUT', null, null, (int)$userId);
            $hash = hash('sha256', session_id());
            SessionHandler::revokeSessionByHash($hash);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        return $response->withStatus(302)->withHeader('Location', '/login');
    }

    public function heartbeat(Request $request, Response $response): Response
    {
        $rateLimiter = new RateLimiter();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Heartbeat rate limit: max 60 calls per minute
        if (!$rateLimiter->checkRateLimit('hb_' . $ip, 60, 60)) {
            $response->getBody()->write(json_encode(['error' => 'Too Many Requests'], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(429)->withHeader('Content-Type', 'application/json');
        }

        // Minimal response without user payload
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function showProfile(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $sessions = SessionHandler::getUserActiveSessions((int)$user['id']);
        $currentHash = hash('sha256', session_id());

        return $this->view->render($response, 'auth/profile.twig', [
            'user' => $user,
            'sessions' => $sessions,
            'current_hash' => $currentHash,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function showChangePassword(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        return $this->view->render($response, 'auth/change_password.twig', [
            'user' => $user,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function updatePassword(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();

        $currentPass = (string)($body['current_password'] ?? '');
        $newPass = (string)($body['new_password'] ?? '');
        $confirmPass = (string)($body['confirm_password'] ?? '');

        // Validate current password
        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute(['id' => $user['id']]);
        $currentHash = $stmt->fetchColumn();

        if (!password_verify($currentPass, $currentHash)) {
            return $this->view->render($response->withStatus(400), 'auth/change_password.twig', [
                'user' => $user,
                'csrf_token' => Csrf::getToken(),
                'error' => 'Current password is incorrect.'
            ]);
        }

        if (mb_strlen($newPass) < 8) {
            return $this->view->render($response->withStatus(400), 'auth/change_password.twig', [
                'user' => $user,
                'csrf_token' => Csrf::getToken(),
                'error' => 'New password must be at least 8 characters long.'
            ]);
        }

        if ($newPass !== $confirmPass) {
            return $this->view->render($response->withStatus(400), 'auth/change_password.twig', [
                'user' => $user,
                'csrf_token' => Csrf::getToken(),
                'error' => 'New password and confirmation do not match.'
            ]);
        }

        // Common passwords check
        $commonPasswords = ['12345678', 'password', 'password123', 'admin123', 'workshop123'];
        if (in_array(strtolower($newPass), $commonPasswords, true)) {
            return $this->view->render($response->withStatus(400), 'auth/change_password.twig', [
                'user' => $user,
                'csrf_token' => Csrf::getToken(),
                'error' => 'This password is too common. Please choose a stronger password.'
            ]);
        }

        $hashAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $newHash = password_hash($newPass, $hashAlgo);

        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = :hash, must_change_password = 0, updated_at = :now 
            WHERE id = :id
        ");
        $updateStmt->execute([
            'hash' => $newHash,
            'now' => gmdate('Y-m-d H:i:s'),
            'id' => $user['id']
        ]);

        // Revoke all OTHER sessions for this user
        SessionHandler::revokeUserSessions((int)$user['id'], session_id());

        AuditLogger::log('USER', (int)$user['id'], 'PASSWORD_CHANGE', null, [
            'status' => 'password_updated'
        ], (int)$user['id']);

        return $response->withStatus(302)->withHeader('Location', '/profile?success=password_changed');
    }

    public function updateLanguage(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $lang = $body['language'] ?? 'en';

        if (in_array($lang, ['en', 'ar', 'ckb'], true)) {
            $pdo = Database::getAppPdo();
            $stmt = $pdo->prepare("UPDATE users SET language = :lang, updated_at = :now WHERE id = :id");
            $stmt->execute([
                'lang' => $lang,
                'now' => gmdate('Y-m-d H:i:s'),
                'id' => $user['id']
            ]);
            $_SESSION['language'] = $lang;
            Translator::setLocale($lang);
        }

        $referer = $request->getHeaderLine('Referer') ?: '/';
        return $response->withStatus(302)->withHeader('Location', $referer);
    }
}
