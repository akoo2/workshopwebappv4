<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use App\Config\Database;
use App\Security\SessionHandler;
use App\Lang\Translator;

class AuthMiddleware implements MiddlewareInterface
{
    private bool $allowGuest;

    public function __construct(bool $allowGuest = false)
    {
        $this->allowGuest = $allowGuest;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $path = $request->getUri()->getPath();

        // Check if heartbeat request
        $isHeartbeat = ($path === '/api/heartbeat');
        if ($isHeartbeat) {
            SessionHandler::setHeartbeatMode(true);
        }

        $userId = $_SESSION['user_id'] ?? null;

        // If not logged in
        if (!$userId) {
            if ($isHeartbeat) {
                $response = new SlimResponse();
                $response->getBody()->write(json_encode(['error' => 'Unauthenticated'], JSON_UNESCAPED_UNICODE));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            if ($this->allowGuest) {
                return $handler->handle($request);
            }

            // Redirect to login
            $response = new SlimResponse();
            return $response->withStatus(302)->withHeader('Location', '/login');
        }

        // Query database on EVERY request to verify user is active and check current role
        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("SELECT id, email, name, role, language, is_active, must_change_password, is_locked FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        // If user deleted, disabled, or locked, revoke session instantly
        if (!$user || empty($user['is_active']) || !empty($user['is_locked'])) {
            // Destroy session in DB and PHP
            SessionHandler::revokeUserSessions((int)$userId);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            if ($isHeartbeat) {
                $response = new SlimResponse();
                $response->getBody()->write(json_encode(['error' => 'Account inactive or disabled'], JSON_UNESCAPED_UNICODE));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            $response = new SlimResponse();
            return $response->withStatus(302)->withHeader('Location', '/login?error=account_disabled');
        }

        // If user's stored role in session no longer matches the database role, update or force re-auth
        if (isset($_SESSION['role']) && $_SESSION['role'] !== $user['role']) {
            $_SESSION['role'] = $user['role'];
            session_regenerate_id(true);
        }

        // Set translator language to user's preference
        Translator::setLocale($user['language'] ?? 'en');

        // Check if user must change password
        if (!empty($user['must_change_password'])) {
            $allowedPaths = ['/profile/password', '/logout', '/api/heartbeat'];
            if (!in_array($path, $allowedPaths, true)) {
                $response = new SlimResponse();
                return $response->withStatus(302)->withHeader('Location', '/profile/password');
            }
        }

        // Pass authenticated user to request attributes
        $request = $request->withAttribute('user', $user);

        return $handler->handle($request);
    }
}
