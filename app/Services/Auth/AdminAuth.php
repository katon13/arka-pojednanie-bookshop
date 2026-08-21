<?php
namespace Book100\Services\Auth;

use Book100\Core\AdminUrl;
use Book100\Core\Database;
use Book100\Core\Session;

final class AdminAuth
{
    private const SESSION_KEY = 'book100_admin_id';
    private const SECOND_FACTOR_KEY = 'book100_admin_2fa_pending_id';
    private const SECOND_FACTOR_STARTED_KEY = 'book100_admin_2fa_pending_started';
    private const SECOND_FACTOR_TTL = 600;
    private const ATTEMPT_LIMIT = 5;
    private const LOCK_SECONDS = 300;

    public static function start(): void
    {
        Session::start();
    }

    public static function user(): ?array
    {
        self::start();
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        if (!$id) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT id, email, role FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return 'authenticated'|'totp_required'|'invalid' */
    public static function attemptPassword(string $email, string $password): string
    {
        self::start();
        $now = time();
        if ((int)($_SESSION['book100_login_locked_until'] ?? 0) > $now) {
            return 'invalid';
        }

        AdminTwoFactor::ensureSchema();
        $stmt = Database::pdo()->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([trim($email)]);
        $admin = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$admin || !password_verify($password, (string)$admin['password_hash'])) {
            self::recordPasswordFailure($now);
            return 'invalid';
        }

        unset($_SESSION['book100_login_attempts'], $_SESSION['book100_login_locked_until']);
        if ((AdminTwoFactor::state((int)$admin['id'])['enabled'] ?? false) === true) {
            unset($_SESSION[self::SESSION_KEY]);
            $_SESSION[self::SECOND_FACTOR_KEY] = (int)$admin['id'];
            $_SESSION[self::SECOND_FACTOR_STARTED_KEY] = $now;
            unset(
                $_SESSION['book100_2fa_login_attempts'],
                $_SESSION['book100_2fa_login_locked_until']
            );
            session_regenerate_id(true);
            return 'totp_required';
        }

        self::completeLogin((int)$admin['id']);
        return 'authenticated';
    }

    public static function attempt(string $email, string $password): bool
    {
        return self::attemptPassword($email, $password) === 'authenticated';
    }

    public static function hasPendingSecondFactor(): bool
    {
        return self::pendingAdminId() !== null;
    }

    public static function completeSecondFactor(string $code): bool
    {
        self::start();
        $adminId = self::pendingAdminId();
        if ($adminId === null) {
            return false;
        }
        $now = time();
        if ((int)($_SESSION['book100_2fa_login_locked_until'] ?? 0) > $now) {
            return false;
        }
        if (!preg_match('/^\d{6}$/D', $code) || !AdminTwoFactor::verifyLoginCode($adminId, $code)) {
            $attempts = (int)($_SESSION['book100_2fa_login_attempts'] ?? 0) + 1;
            if ($attempts >= self::ATTEMPT_LIMIT) {
                $_SESSION['book100_2fa_login_locked_until'] = $now + self::LOCK_SECONDS;
                unset($_SESSION['book100_2fa_login_attempts']);
            } else {
                $_SESSION['book100_2fa_login_attempts'] = $attempts;
            }
            return false;
        }

        self::completeLogin($adminId);
        return true;
    }

    public static function cancelSecondFactor(): void
    {
        self::start();
        self::clearSecondFactorSession();
        session_regenerate_id(true);
    }

    public static function changePassword(string $currentPassword, string $newPassword): bool
    {
        $user = self::user();
        if (!$user || strlen($newPassword) < 12) {
            return false;
        }
        $stmt = Database::pdo()->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $stmt->execute([(int)$user['id']]);
        $hash = (string)$stmt->fetchColumn();
        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            return false;
        }
        Database::pdo()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
        session_regenerate_id(true);
        return true;
    }

    public static function logout(): void
    {
        self::start();
        unset($_SESSION[self::SESSION_KEY]);
        self::clearSecondFactorSession();
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . AdminUrl::route('/login'));
            exit;
        }
    }

    private static function pendingAdminId(): ?int
    {
        self::start();
        $adminId = (int)($_SESSION[self::SECOND_FACTOR_KEY] ?? 0);
        $started = (int)($_SESSION[self::SECOND_FACTOR_STARTED_KEY] ?? 0);
        if ($adminId <= 0 || $started <= 0 || $started + self::SECOND_FACTOR_TTL < time()) {
            self::clearSecondFactorSession();
            return null;
        }
        return $adminId;
    }

    private static function completeLogin(int $adminId): void
    {
        self::start();
        self::clearSecondFactorSession();
        $_SESSION[self::SESSION_KEY] = $adminId;
        session_regenerate_id(true);
    }

    private static function clearSecondFactorSession(): void
    {
        unset(
            $_SESSION[self::SECOND_FACTOR_KEY],
            $_SESSION[self::SECOND_FACTOR_STARTED_KEY],
            $_SESSION['book100_2fa_login_attempts'],
            $_SESSION['book100_2fa_login_locked_until']
        );
    }

    private static function recordPasswordFailure(int $now): void
    {
        $attempts = (int)($_SESSION['book100_login_attempts'] ?? 0) + 1;
        if ($attempts >= self::ATTEMPT_LIMIT) {
            $_SESSION['book100_login_locked_until'] = $now + self::LOCK_SECONDS;
            unset($_SESSION['book100_login_attempts']);
            return;
        }
        $_SESSION['book100_login_attempts'] = $attempts;
    }
}
