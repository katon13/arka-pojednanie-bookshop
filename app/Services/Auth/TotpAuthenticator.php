<?php
namespace Book100\Services\Auth;

final class TotpAuthenticator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(int $bytes = 20): string
    {
        if ($bytes < 20) {
            throw new \InvalidArgumentException('Sekret TOTP musi mieć co najmniej 160 bitów.');
        }
        return self::base32Encode(random_bytes($bytes));
    }

    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $secret = self::normalizeSecret($secret);
        if (!self::isValidSecret($secret)) {
            throw new \InvalidArgumentException('Nieprawidłowy sekret TOTP.');
        }
        $issuer = trim($issuer);
        $account = trim($account);
        if ($issuer === '' || $account === '') {
            throw new \InvalidArgumentException('Wystawca i konto TOTP są wymagane.');
        }

        return 'otpauth://totp/'
            . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS
            . '&period=' . self::PERIOD;
    }

    public static function matchingCounter(
        string $secret,
        string $code,
        int $window = 1,
        ?int $timestamp = null
    ): ?int {
        if (!preg_match('/^\d{6}$/D', $code)) {
            return null;
        }
        $secret = self::normalizeSecret($secret);
        if (!self::isValidSecret($secret)) {
            return null;
        }
        $window = max(0, min(1, $window));
        $counter = intdiv($timestamp ?? time(), self::PERIOD);
        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = $counter + $offset;
            if ($candidate >= 0 && hash_equals(self::codeAtCounter($secret, $candidate), $code)) {
                return $candidate;
            }
        }
        return null;
    }

    public static function codeAtCounter(string $secret, int $counter): string
    {
        $key = self::base32Decode(self::normalizeSecret($secret));
        $high = intdiv($counter, 4294967296);
        $low = $counter % 4294967296;
        $binaryCounter = pack('N2', $high, $low);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
        return str_pad((string)($value % 1000000), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function normalizeSecret(string $secret): string
    {
        return strtoupper((string)preg_replace('/[\s=-]+/', '', trim($secret)));
    }

    public static function isValidSecret(string $secret): bool
    {
        return strlen($secret) >= 32 && preg_match('/^[A-Z2-7]+$/D', $secret) === 1;
    }

    private static function base32Encode(string $binary): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';
        $length = strlen($binary);
        for ($index = 0; $index < $length; $index++) {
            $buffer = ($buffer << 8) | ord($binary[$index]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }
        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }
        return $encoded;
    }

    private static function base32Decode(string $encoded): string
    {
        if (!self::isValidSecret($encoded)) {
            throw new \InvalidArgumentException('Nieprawidłowy sekret Base32.');
        }
        $map = array_flip(str_split(self::ALPHABET));
        $buffer = 0;
        $bits = 0;
        $decoded = '';
        foreach (str_split($encoded) as $character) {
            $buffer = ($buffer << 5) | $map[$character];
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 255);
                $buffer &= (1 << $bits) - 1;
            }
        }
        return $decoded;
    }
}
