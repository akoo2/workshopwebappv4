<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Config\Database;
use App\Security\Csrf;
use App\Security\SessionHandler;
use App\Security\AuditLogger;
use App\Security\RateLimiter;
use App\Security\Validator;
use App\Lang\Translator;

class StaffController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::getAppPdo();
        $stmt = $pdo->query("
            SELECT u.id, u.email, u.name, u.role, u.phone, u.language, u.is_active, 
                   u.must_change_password, u.is_locked, u.created_at,
                   (SELECT COUNT(*) FROM sessions s WHERE s.user_id = u.id) AS active_sessions_count
            FROM users u
            ORDER BY u.id ASC
        ");
        $staff = $stmt->fetchAll();

        // Check last backup time to show warning if older than 36h
        $backupWarning = false;
        $lastBackupTime = null;
        $backupFlagFile = __DIR__ . '/../../storage/backups/.last_backup';
        if (file_exists($backupFlagFile)) {
            $lastBackupTs = (int)file_get_contents($backupFlagFile);
            if ($lastBackupTs > 0) {
                $lastBackupTime = gmdate('Y-m-d H:i:s', $lastBackupTs);
                if ((time() - $lastBackupTs) > (36 * 3600)) {
                    $backupWarning = true;
                }
            }
        } else {
            $backupWarning = true;
        }

        return $this->view->render($response, 'staff/index.twig', [
            'staff' => $staff,
            'csrf_token' => Csrf::getToken(),
            'backup_warning' => $backupWarning,
            'last_backup_time' => $lastBackupTime
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'staff/form.twig', [
            'staff' => null,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $validator = Validator::validate($body, [
            'name' => ['required', 'min_len:2', 'max_len:100'],
            'email' => ['required', 'email', 'max_len:150'],
            'role' => ['required', 'in:TECH,MANAGER,ADMIN,OWNER'],
            'phone' => ['max_len:30'],
            'temp_password' => ['required', 'min_len:8']
        ]);

        if ($validator->fails()) {
            return $this->view->render($response->withStatus(400), 'staff/form.twig', [
                'staff' => $body,
                'error' => $validator->getFirstError(),
                'csrf_token' => Csrf::getToken()
            ]);
        }

        $data = $validator->getData();
        $pdo = Database::getAppPdo();

        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $checkStmt->execute(['email' => strtolower($data['email'])]);
        if ($checkStmt->fetch()) {
            return $this->view->render($response->withStatus(400), 'staff/form.twig', [
                'staff' => $body,
                'error' => 'A user with this email already exists.',
                'csrf_token' => Csrf::getToken()
            ]);
        }

        $hashAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $passwordHash = password_hash($data['temp_password'], $hashAlgo);

        $now = gmdate('Y-m-d H:i:s');
        $insertStmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, role, name, phone, language, is_active, must_change_password, is_locked, created_at, updated_at)
            VALUES (:email, :hash, :role, :name, :phone, 'en', 1, 1, 0, :now, :now)
        ");

        $insertStmt->execute([
            'email' => strtolower($data['email']),
            'hash' => $passwordHash,
            'role' => $data['role'],
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'now' => $now
        ]);

        $newUserId = (int)$pdo->lastInsertId();

        AuditLogger::log('USER', $newUserId, 'CREATE', null, [
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'name' => $data['name']
        ], (int)$currentUser['id']);

        return $response->withStatus(302)->withHeader('Location', '/staff?success=created');
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $pdo = Database::getAppPdo();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            return $response->withStatus(404)->withHeader('Location', '/staff');
        }

        // Active sessions
        $sessions = SessionHandler::getUserActiveSessions($id);

        // Owner private notes
        $notesStmt = $pdo->prepare("
            SELECT sn.*, u.name AS author_name 
            FROM staff_notes sn
            JOIN users u ON sn.author_user_id = u.id
            WHERE sn.staff_user_id = :id
            ORDER BY sn.created_at DESC
        ");
        $notesStmt->execute(['id' => $id]);
        $notes = $notesStmt->fetchAll();

        return $this->view->render($response, 'staff/show.twig', [
            'staff' => $targetUser,
            'sessions' => $sessions,
            'notes' => $notes,
            'csrf_token' => Csrf::getToken()
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $currentUser = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();

        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            return $response->withStatus(404);
        }

        $validator = Validator::validate($body, [
            'name' => ['required', 'min_len:2', 'max_len:100'],
            'phone' => ['max_len:30'],
            'role' => ['required', 'in:TECH,MANAGER,ADMIN,OWNER']
        ]);

        if ($validator->fails()) {
            return $response->withStatus(302)->withHeader('Location', "/staff/{$id}?error=" . urlencode($validator->getFirstError()));
        }

        $data = $validator->getData();

        // RULE: Nobody can change their own role!
        if ($id === (int)$currentUser['id'] && $data['role'] !== $targetUser['role']) {
            return $response->withStatus(302)->withHeader('Location', "/staff/{$id}?error=" . urlencode(Translator::trans('cant_change_own_role')));
        }

        // RULE: Last active OWNER cannot be demoted!
        if ($targetUser['role'] === 'OWNER' && $data['role'] !== 'OWNER') {
            if ($this->isLastActiveOwner($id)) {
                return $response->withStatus(302)->withHeader('Location', "/staff/{$id}?error=" . urlencode(Translator::trans('cant_disable_last_owner')));
            }
        }

        $roleChanged = ($data['role'] !== $targetUser['role']);

        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET name = :name, phone = :phone, role = :role, updated_at = :now 
            WHERE id = :id
        ");
        $updateStmt->execute([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'now' => gmdate('Y-m-d H:i:s'),
            'id' => $id
        ]);

        // If role changed, revoke all sessions for that user
        if ($roleChanged) {
            SessionHandler::revokeUserSessions($id);
        }

        AuditLogger::log('USER', $id, 'UPDATE', [
            'name' => $targetUser['name'],
            'role' => $targetUser['role']
        ], [
            'name' => $data['name'],
            'role' => $data['role']
        ], (int)$currentUser['id']);

        return $response->withStatus(302)->withHeader('Location', "/staff/{$id}?success=updated");
    }

    public function toggleStatus(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $currentUser = $request->getAttribute('user');

        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            return $response->withStatus(404);
        }

        $newStatus = empty($targetUser['is_active']) ? 1 : 0;

        // RULE: Cannot disable the last active Owner!
        if ($targetUser['role'] === 'OWNER' && $newStatus === 0) {
            if ($this->isLastActiveOwner($id)) {
                return $response->withStatus(302)->withHeader('Location', "/staff?error=" . urlencode(Translator::trans('cant_disable_last_owner')));
            }
        }

        $updateStmt = $pdo->prepare("UPDATE users SET is_active = :status, updated_at = :now WHERE id = :id");
        $updateStmt->execute([
            'status' => $newStatus,
            'now' => gmdate('Y-m-d H:i:s'),
            'id' => $id
        ]);

        // If disabling, purge all active sessions immediately
        if ($newStatus === 0) {
            SessionHandler::revokeUserSessions($id);
        }

        AuditLogger::log('USER', $id, 'STATUS_CHANGE', [
            'is_active' => $targetUser['is_active']
        ], [
            'is_active' => $newStatus
        ], (int)$currentUser['id']);

        return $response->withStatus(302)->withHeader('Location', '/staff?success=status_changed');
    }

    public function unlock(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $currentUser = $request->getAttribute('user');

        $rateLimiter = new RateLimiter();
        $unlocked = $rateLimiter->unlockAccount($id);

        if ($unlocked) {
            AuditLogger::log('USER', $id, 'UNLOCK', null, ['unlocked' => true], (int)$currentUser['id']);
        }

        return $response->withStatus(302)->withHeader('Location', "/staff/{$id}?success=unlocked");
    }

    public function resetPassword(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $currentUser = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();

        $tempPassword = (string)($body['temp_password'] ?? '');
        if (mb_strlen($tempPassword) < 8) {
            return $response->withStatus(302)->withHeader('Location', "/staff/{$id}?error=Password+must+be+at+least+8+characters");
        }

        $hashAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $hash = password_hash($tempPassword, $hashAlgo);

        $pdo = Database::getAppPdo();
        $update = $pdo->prepare("
            UPDATE users 
            SET password_hash = :hash, must_change_password = 1, is_locked = 0, updated_at = :now 
            WHERE id = :id
        ");
        $update->execute([
            'hash' => $hash,
            'now' => gmdate('Y-m-d H:i:s'),
            'id' => $id
        ]);

        // Revoke all sessions for this user so they must sign in with new password
        SessionHandler::revokeUserSessions($id);

        AuditLogger::log('USER', $id, 'PASSWORD_RESET', null, ['must_change_password' => 1], (int)$currentUser['id']);

        return $response->withStatus(302)->withHeader('Location', "/staff/{$id}?success=password_reset");
    }

    public function revokeSessions(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $currentUser = $request->getAttribute('user');

        SessionHandler::revokeUserSessions($id);

        AuditLogger::log('USER', $id, 'SESSIONS_REVOKE', null, ['all_revoked' => true], (int)$currentUser['id']);

        return $response->withStatus(302)->withHeader('Location', "/staff/{$id}?success=sessions_revoked");
    }

    public function addNote(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $currentUser = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $note = trim((string)($body['note'] ?? ''));

        if (!empty($note)) {
            $pdo = Database::getAppPdo();
            $stmt = $pdo->prepare("
                INSERT INTO staff_notes (staff_user_id, author_user_id, note, created_at)
                VALUES (:staff_id, :author_id, :note, :now)
            ");
            $stmt->execute([
                'staff_id' => $id,
                'author_id' => $currentUser['id'],
                'note' => $note,
                'now' => gmdate('Y-m-d H:i:s')
            ]);
        }

        return $response->withStatus(302)->withHeader('Location', "/staff/{$id}?success=note_added");
    }

    private function isLastActiveOwner(int $userId): bool
    {
        $pdo = Database::getAppPdo();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE role = 'OWNER' AND is_active = 1 AND id != :id
        ");
        $stmt->execute(['id' => $userId]);
        $remainingOwners = (int)$stmt->fetchColumn();
        return ($remainingOwners === 0);
    }
}
