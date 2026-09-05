<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * TOTP (RFC 6238, SHA-1, 30 s, 6 digits) with backup codes. Hand-rolled on
 * purpose: it is forty lines and one fewer dependency for the client to own.
 */
class TwoFactorAuthService
{
    private const int BACKUP_CODES_COUNT = 8;

    private const int BACKUP_CODES_LENGTH = 10;

    private const int PERIOD = 30;

    private const int DIGITS = 6;

    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Accepts the current TOTP code (±1 window) or an unused backup code, and
     * burns the backup code when that is what matched.
     */
    public function verifyForUser(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if (! is_string($secret)) {
            return false;
        }

        if ($this->verify($secret, $code)) {
            return true;
        }

        /** @var array<int, string> $codes */
        $codes = $user->two_factor_backup_codes ?? [];
        $normalized = strtoupper(str_replace([' ', '-'], '', $code));

        if (! in_array($normalized, $codes, true)) {
            return false;
        }

        $user->two_factor_backup_codes = array_values(array_filter($codes, fn (string $c) => $c !== $normalized));
        $user->save();

        return true;
    }

    public function verify(string $secret, string $code): bool
    {
        $now = time();

        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals($this->code($secret, $now + $i * self::PERIOD), $code)) {
                return true;
            }
        }

        return false;
    }

    public function otpauthUrl(string $issuer, string $account, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    /**
     * @return array<int, string>
     */
    public function generateBackupCodes(): array
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $codes = [];

        for ($i = 0; $i < self::BACKUP_CODES_COUNT; $i++) {
            $code = '';
            for ($j = 0; $j < self::BACKUP_CODES_LENGTH; $j++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = $code;
        }

        return $codes;
    }

    public function setGracePeriod(string $userId): void
    {
        Cache::put("totp-grace:{$userId}", true, now()->addMinutes((int) config('kit.totp_grace_minutes')));
    }

    public function hasGracePeriod(string $userId): bool
    {
        return Cache::has("totp-grace:{$userId}");
    }

    public function clearGracePeriod(string $userId): void
    {
        Cache::forget("totp-grace:{$userId}");
    }

    public function code(string $secret, int $timestamp): string
    {
        $key = $this->base32Decode($secret);
        $counter = pack('N*', 0).pack('N*', intdiv($timestamp, self::PERIOD));
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0xF;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function base32Decode(string $data): string
    {
        $bits = '';
        foreach (str_split(strtoupper($data)) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index !== false) {
                $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
            }
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}
