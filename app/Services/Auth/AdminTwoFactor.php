<?php
namespace Book100\Services\Auth;

use Book100\Core\Database;
use Book100\Core\Env;
use Book100\Core\Session;
use PDO;
use RuntimeException;

final class AdminTwoFactor
{
    private const AAD = 'arka-admin-totp-v1';
    private const ATTEMPT_LIMIT = 5;
    private const LOCK_SECONDS = 300;
    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $pdo = Database::pdo();
        try {
            $pdo->query(
                'SELECT totp_secret_encrypted, totp_pending_secret_encrypted, totp_enabled_at, totp_last_counter FROM admins LIMIT 0'
            );
            self::$schemaReady = true;
            return;
        } catch (\Throwable) {
        }

        $columns = [
            'totp_secret_encrypted' => 'TEXT NULL',
            'totp_pending_secret_encrypted' => 'TEXT NULL',
            'totp_enabled_at' => 'DATETIME NULL',
            'totp_last_counter' => 'BIGINT NULL',
        ];
        foreach ($columns as $name => $definition) {
            try {
                $pdo->exec("ALTER TABLE admins ADD COLUMN {$name} {$definition}");
            } catch (\Throwable) {
                // Równoległe żądanie mogło już dodać kolumnę.
            }
        }
        $pdo->query(
            'SELECT totp_secret_encrypted, totp_pending_secret_encrypted, totp_enabled_at, totp_last_counter FROM admins LIMIT 0'
        );
        self::$schemaReady = true;
    }

    public static function state(int $adminId): array
    {
        $row = self::adminRow($adminId);
        return [
            'enabled' => self::rowIsEnabled($row),
            'pending' => trim((string)($row['totp_pending_secret_encrypted'] ?? '')) !== '',
            'enabled_at' => $row['totp_enabled_at'] ?? null,
        ];
    }

    public static function setupData(int $adminId): ?array
    {
        $row = self::adminRow($adminId);
        $encrypted = trim((string)($row['totp_pending_secret_encrypted'] ?? ''));
        if ($encrypted === '') {
            return null;
        }
        $secret = self::decryptSecret($encrypted, $adminId);
        $issuer = 'Księgarnia Arka';
        return [
            'secret' => $secret,
            'uri' => TotpAuthenticator::provisioningUri($secret, (string)$row['email'], $issuer),
            'issuer' => $issuer,
            'account' => (string)$row['email'],
        ];
    }

    public static function beginSetup(int $adminId, string $password, string $currentCode = ''): void
    {
        self::assertRateLimit('manage');
        $row = self::adminRow($adminId);
        if (!password_verify($password, (string)$row['password_hash'])) {
            self::recordFailure('manage');
            throw new RuntimeException('Nieprawidłowe hasło administratora.');
        }
        if (self::rowIsEnabled($row) && !self::verifyAndConsume($row, $currentCode)) {
            self::recordFailure('manage');
            throw new RuntimeException('Podaj poprawny, aktualny 6-cyfrowy kod z aplikacji.');
        }

        $secret = TotpAuthenticator::generateSecret();
        $encrypted = self::encryptSecret($secret, $adminId);
        Database::pdo()->prepare(
            'UPDATE admins SET totp_pending_secret_encrypted = ? WHERE id = ?'
        )->execute([$encrypted, $adminId]);
        self::clearFailures('manage');
    }

    public static function confirmSetup(int $adminId, string $code): bool
    {
        self::assertRateLimit('confirm');
        if (!preg_match('/^\d{6}$/D', $code)) {
            self::recordFailure('confirm');
            return false;
        }
        $row = self::adminRow($adminId);
        $pending = trim((string)($row['totp_pending_secret_encrypted'] ?? ''));
        if ($pending === '') {
            return false;
        }
        $secret = self::decryptSecret($pending, $adminId);
        $counter = TotpAuthenticator::matchingCounter($secret, $code, 1);
        if ($counter === null) {
            self::recordFailure('confirm');
            return false;
        }
        $statement = Database::pdo()->prepare(
            'UPDATE admins
             SET totp_secret_encrypted = totp_pending_secret_encrypted,
                 totp_pending_secret_encrypted = NULL,
                 totp_enabled_at = ?,
                 totp_last_counter = ?
             WHERE id = ? AND totp_pending_secret_encrypted = ?'
        );
        $statement->execute([date('Y-m-d H:i:s'), $counter, $adminId, $pending]);
        if ($statement->rowCount() !== 1) {
            self::recordFailure('confirm');
            return false;
        }
        self::clearFailures('confirm');
        return true;
    }

    public static function cancelPending(int $adminId): void
    {
        self::ensureSchema();
        Database::pdo()->prepare(
            'UPDATE admins SET totp_pending_secret_encrypted = NULL WHERE id = ?'
        )->execute([$adminId]);
        self::clearFailures('confirm');
    }

    public static function disable(int $adminId, string $password, string $code): bool
    {
        self::assertRateLimit('manage');
        $row = self::adminRow($adminId);
        if (!self::rowIsEnabled($row)
            || !password_verify($password, (string)$row['password_hash'])
            || !preg_match('/^\d{6}$/D', $code)
        ) {
            self::recordFailure('manage');
            return false;
        }
        $secret = self::decryptSecret((string)$row['totp_secret_encrypted'], $adminId);
        $counter = TotpAuthenticator::matchingCounter($secret, $code, 1);
        if ($counter === null || $counter <= (int)($row['totp_last_counter'] ?? -1)) {
            self::recordFailure('manage');
            return false;
        }
        $statement = Database::pdo()->prepare(
            'UPDATE admins
             SET totp_secret_encrypted = NULL,
                 totp_pending_secret_encrypted = NULL,
                 totp_enabled_at = NULL,
                 totp_last_counter = NULL
             WHERE id = ? AND (totp_last_counter IS NULL OR totp_last_counter < ?)'
        );
        $statement->execute([$adminId, $counter]);
        if ($statement->rowCount() !== 1) {
            self::recordFailure('manage');
            return false;
        }
        self::clearFailures('manage');
        return true;
    }

    public static function verifyLoginCode(int $adminId, string $code): bool
    {
        if (!preg_match('/^\d{6}$/D', $code)) {
            return false;
        }
        return self::verifyAndConsume(self::adminRow($adminId), $code);
    }

    private static function verifyAndConsume(array $row, string $code): bool
    {
        if (!self::rowIsEnabled($row) || !preg_match('/^\d{6}$/D', $code)) {
            return false;
        }
        $adminId = (int)$row['id'];
        $secret = self::decryptSecret((string)$row['totp_secret_encrypted'], $adminId);
        $counter = TotpAuthenticator::matchingCounter($secret, $code, 1);
        if ($counter === null || $counter <= (int)($row['totp_last_counter'] ?? -1)) {
            return false;
        }
        $statement = Database::pdo()->prepare(
            'UPDATE admins SET totp_last_counter = ?
             WHERE id = ? AND (totp_last_counter IS NULL OR totp_last_counter < ?)'
        );
        $statement->execute([$counter, $adminId, $counter]);
        return $statement->rowCount() === 1;
    }

    private static function adminRow(int $adminId): array
    {
        self::ensureSchema();
        $statement = Database::pdo()->prepare(
            'SELECT id, email, password_hash, totp_secret_encrypted,
                    totp_pending_secret_encrypted, totp_enabled_at, totp_last_counter
             FROM admins WHERE id = ? LIMIT 1'
        );
        $statement->execute([$adminId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Nie znaleziono konta administratora.');
        }
        return $row;
    }

    private static function rowIsEnabled(array $row): bool
    {
        return trim((string)($row['totp_secret_encrypted'] ?? '')) !== ''
            && trim((string)($row['totp_enabled_at'] ?? '')) !== '';
    }

    private static function encryptSecret(string $secret, int $adminId): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('Serwer nie udostępnia bezpiecznego szyfrowania OpenSSL.');
        }
        $key = self::encryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $secret,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD . ':' . $adminId,
            16
        );
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Nie udało się bezpiecznie zapisać sekretu 2FA.');
        }
        return 'v1.' . self::base64UrlEncode($iv . $tag . $ciphertext);
    }

    private static function decryptSecret(string $encrypted, int $adminId): string
    {
        if (!str_starts_with($encrypted, 'v1.')) {
            throw new RuntimeException('Sekret 2FA ma nieobsługiwany format.');
        }
        $payload = self::base64UrlDecode(substr($encrypted, 3));
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Sekret 2FA jest uszkodzony.');
        }
        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);
        $secret = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD . ':' . $adminId
        );
        if ($secret === false || !TotpAuthenticator::isValidSecret($secret)) {
            throw new RuntimeException('Nie można odczytać sekretu 2FA.');
        }
        return $secret;
    }

    private static function encryptionKey(): string
    {
        $appKey = trim((string)Env::get('APP_KEY', ''));
        if (strlen($appKey) < 16) {
            throw new RuntimeException('APP_KEY musi być ustawiony przed konfiguracją 2FA.');
        }
        return hash('sha256', $appKey, true);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($value, '-_', '+/'), true);
    }

    private static function assertRateLimit(string $bucket): void
    {
        Session::start();
        $lockedUntil = (int)($_SESSION[self::rateKey($bucket, 'locked_until')] ?? 0);
        if ($lockedUntil > time()) {
            throw new RuntimeException('Zbyt wiele prób. Odczekaj 5 minut i spróbuj ponownie.');
        }
    }

    private static function recordFailure(string $bucket): void
    {
        Session::start();
        $attemptKey = self::rateKey($bucket, 'attempts');
        $attempts = (int)($_SESSION[$attemptKey] ?? 0) + 1;
        if ($attempts >= self::ATTEMPT_LIMIT) {
            $_SESSION[self::rateKey($bucket, 'locked_until')] = time() + self::LOCK_SECONDS;
            unset($_SESSION[$attemptKey]);
            return;
        }
        $_SESSION[$attemptKey] = $attempts;
    }

    private static function clearFailures(string $bucket): void
    {
        Session::start();
        unset(
            $_SESSION[self::rateKey($bucket, 'attempts')],
            $_SESSION[self::rateKey($bucket, 'locked_until')]
        );
    }

    private static function rateKey(string $bucket, string $suffix): string
    {
        return 'book100_2fa_' . $bucket . '_' . $suffix;
    }
}
