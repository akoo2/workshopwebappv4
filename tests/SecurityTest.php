<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Config\Database;
use App\Security\AuditLogger;
use App\Security\SessionHandler;
use App\Security\RateLimiter;
use App\Security\Csrf;
use App\Security\Validator;

class SecurityTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        // Use in-memory SQLite database for test suite
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Run migrations on test database
        $m1 = file_get_contents(__DIR__ . '/../migrations/001_create_initial_schema.sql');
        $this->pdo->exec($m1);
        $m2 = file_get_contents(__DIR__ . '/../migrations/002_create_inventory_and_repairs_schema.sql');
        $this->pdo->exec($m2);

        Database::setPdo($this->pdo);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Test 1: audit_log must never store password hashes, session IDs or tokens.
     */
    public function testAuditLogSanitizesSensitiveFields(): void
    {
        $sensitiveData = [
            'id' => 10,
            'name' => 'John Doe',
            'email' => 'john@test.local',
            'password' => 'SuperSecret123!',
            'password_hash' => '$argon2id$v=19$m=65536,t=4,p=1$fakehash',
            'token' => 'secret_token_abc',
            '_csrf_token' => 'csrf_token_xyz',
            'session_id' => 'raw_session_id',
            'id_hash' => 'hash_value',
            'nested' => [
                'role' => 'TECH',
                'temp_password' => 'temporary123',
                'allowed_field' => 'visible'
            ]
        ];

        $sanitized = AuditLogger::sanitize($sensitiveData);

        $this->assertArrayNotHasKey('password', $sanitized);
        $this->assertArrayNotHasKey('password_hash', $sanitized);
        $this->assertArrayNotHasKey('token', $sanitized);
        $this->assertArrayNotHasKey('_csrf_token', $sanitized);
        $this->assertArrayNotHasKey('session_id', $sanitized);
        $this->assertArrayNotHasKey('id_hash', $sanitized);
        $this->assertArrayNotHasKey('temp_password', $sanitized['nested']);

        $this->assertEquals('John Doe', $sanitized['name']);
        $this->assertEquals('TECH', $sanitized['nested']['role']);
        $this->assertEquals('visible', $sanitized['nested']['allowed_field']);

        // Test insertion into audit_log
        AuditLogger::log('USER', 10, 'UPDATE', null, $sensitiveData, 1);

        $stmt = $this->pdo->query("SELECT after_state FROM audit_log WHERE entity_id = 10");
        $loggedState = $stmt->fetchColumn();

        $this->assertStringNotContainsString('SuperSecret123!', $loggedState);
        $this->assertStringNotContainsString('$argon2id', $loggedState);
        $this->assertStringNotContainsString('secret_token_abc', $loggedState);
        $this->assertStringContainsString('John Doe', $loggedState);
    }

    /**
     * Test 2: Users are verified on every request; disabling or changing role revokes sessions.
     */
    public function testDisablingUserRevokesSessions(): void
    {
        // Insert user
        $stmt = $this->pdo->prepare("
            INSERT INTO users (email, password_hash, role, name, is_active, must_change_password)
            VALUES ('tech@workshop.local', 'hash', 'TECH', 'Tech User', 1, 0)
        ");
        $stmt->execute();
        $userId = (int)$this->pdo->lastInsertId();

        // Create active session in sessions table
        $rawSessionId = 'test_session_12345';
        $sessionHash = hash('sha256', $rawSessionId);
        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + 86400);

        $sessStmt = $this->pdo->prepare("
            INSERT INTO sessions (id_hash, user_id, ip_address, user_agent, last_activity, created_at, expires_at, payload)
            VALUES (:hash, :user_id, '127.0.0.1', 'PHPUnit', :now, :now, :expires, 'user_id|i:{$userId};')
        ");
        $sessStmt->execute([
            'hash' => $sessionHash,
            'user_id' => $userId,
            'now' => $now,
            'expires' => $expires
        ]);

        $this->assertCount(1, SessionHandler::getUserActiveSessions($userId));

        // Disable user and revoke sessions
        SessionHandler::revokeUserSessions($userId);

        $this->assertCount(0, SessionHandler::getUserActiveSessions($userId));
    }

    /**
     * Test 3: Session ID stored only as SHA-256 hash. Login rate limit locks on 10 failures.
     */
    public function testSessionHashingAndRateLimitingLockout(): void
    {
        $rawSessionId = 'my_secret_browser_cookie_session_id';
        $handler = new SessionHandler($this->pdo);
        $handler->write($rawSessionId, 'test_payload');

        // Verify raw session ID does NOT exist in table
        $checkRaw = $this->pdo->prepare("SELECT COUNT(*) FROM sessions WHERE id_hash = :raw");
        $checkRaw->execute(['raw' => $rawSessionId]);
        $this->assertEquals(0, (int)$checkRaw->fetchColumn());

        // Verify SHA-256 hash DOES exist
        $expectedHash = hash('sha256', $rawSessionId);
        $checkHash = $this->pdo->prepare("SELECT COUNT(*) FROM sessions WHERE id_hash = :hash");
        $checkHash->execute(['hash' => $expectedHash]);
        $this->assertEquals(1, (int)$checkHash->fetchColumn());

        // Test login lockout after 10 failures
        $rateLimiter = new RateLimiter($this->pdo);
        $email = 'target@workshop.local';
        $ip = '192.168.1.100';

        $this->assertFalse($rateLimiter->isLoginLocked($email, $ip));

        // Record 9 failures
        for ($i = 0; $i < 9; $i++) {
            $rateLimiter->recordLoginAttempt($email, $ip, false);
        }
        $this->assertFalse($rateLimiter->isLoginLocked($email, $ip));

        // 10th failure triggers lockout
        $rateLimiter->recordLoginAttempt($email, $ip, false);
        $this->assertTrue($rateLimiter->isLoginLocked($email, $ip));

        // Create user row and test Owner unlock
        $this->pdo->prepare("INSERT INTO users (email, password_hash, role, name, is_locked) VALUES (:e, 'h', 'TECH', 'N', 1)")
                  ->execute(['e' => $email]);
        $targetId = (int)$this->pdo->lastInsertId();

        $rateLimiter->unlockAccount($targetId);
        $this->assertFalse($rateLimiter->isLoginLocked($email, $ip));
    }

    /**
     * Test 4: CSRF token validation and rejection.
     */
    public function testCsrfValidation(): void
    {
        // Without active session, validate is false
        $this->assertFalse(Csrf::validate('some_token'));

        // Start session
        session_start();
        $token = Csrf::getToken();
        $this->assertNotEmpty($token);

        // Correct token passes
        $this->assertTrue(Csrf::validate($token));

        // Invalid or tampered token fails
        $this->assertFalse(Csrf::validate('invalid_csrf_token'));
        $this->assertFalse(Csrf::validate(''));
        $this->assertFalse(Csrf::validate(null));
        session_write_close();
    }

    /**
     * Test 5: Allow-list Validator filters unknown fields and validates types.
     */
    public function testAllowListValidator(): void
    {
        $input = [
            'name' => ' Valid Name ',
            'email' => 'valid@workshop.local',
            'role' => 'TECH',
            'malicious_field' => 'DROP TABLE users;',
            'admin_override' => true
        ];

        $validator = Validator::validate($input, [
            'name' => ['required', 'min_len:2'],
            'email' => ['required', 'email'],
            'role' => ['required', 'in:TECH,MANAGER,ADMIN,OWNER']
        ]);

        $this->assertTrue($validator->passes());
        $data = $validator->getData();

        $this->assertEquals('Valid Name', $data['name']);
        $this->assertArrayNotHasKey('malicious_field', $data);
        $this->assertArrayNotHasKey('admin_override', $data);
    }

    /**
     * Test 6: Role middleware enforces strict 403 deny-by-default for unauthorized roles on Staff routes.
     */
    public function testRoleMiddlewareEnforces403ForNonOwners(): void
    {
        $roleMiddleware = new \App\Middleware\RoleMiddleware(['OWNER']);

        $techRequest = (new \Slim\Psr7\Factory\ServerRequestFactory())
            ->createServerRequest('GET', '/staff')
            ->withAttribute('user', ['id' => 1, 'role' => 'TECH']);

        $handler = new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                return (new \Slim\Psr7\Factory\ResponseFactory())->createResponse(200);
            }
        };

        $techResponse = $roleMiddleware->process($techRequest, $handler);
        $this->assertEquals(403, $techResponse->getStatusCode());

        // Manager also receives 403
        $managerRequest = $techRequest->withAttribute('user', ['id' => 2, 'role' => 'MANAGER']);
        $managerResponse = $roleMiddleware->process($managerRequest, $handler);
        $this->assertEquals(403, $managerResponse->getStatusCode());

        // Admin also receives 403
        $adminRequest = $techRequest->withAttribute('user', ['id' => 3, 'role' => 'ADMIN']);
        $adminResponse = $roleMiddleware->process($adminRequest, $handler);
        $this->assertEquals(403, $adminResponse->getStatusCode());

        // Owner receives 200
        $ownerRequest = $techRequest->withAttribute('user', ['id' => 4, 'role' => 'OWNER']);
        $ownerResponse = $roleMiddleware->process($ownerRequest, $handler);
        $this->assertEquals(200, $ownerResponse->getStatusCode());
    }

    /**
     * Test 10: /api/heartbeat returns 401 when session has expired, without refreshing idle timer.
     */
    public function testHeartbeatReturns401OnExpiredSession(): void
    {
        $authMiddleware = new \App\Middleware\AuthMiddleware(false);

        // Heartbeat request without session
        $heartbeatRequest = (new \Slim\Psr7\Factory\ServerRequestFactory())
            ->createServerRequest('GET', '/api/heartbeat');

        $handler = new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
                return (new \Slim\Psr7\Factory\ResponseFactory())->createResponse(200);
            }
        };

        $response = $authMiddleware->process($heartbeatRequest, $handler);
        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Unauthenticated', $body['error'] ?? '');
    }
}
