<?php

namespace WPIDE\App\Services\Snippets;

/**
 * Encryption at rest for API tokens and cloud credentials: libsodium secretbox with a key derived from
 * the site's salts. Changing the salts makes stored secrets unreadable (they must be entered again).
 */
class Secrets
{
    const PREFIX = 'enc1:';

    protected static function key(string $purpose): string
    {
        $material = (defined('AUTH_KEY') ? AUTH_KEY : '') . (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '') . $purpose;

        return hash('sha256', $material, true);
    }

    public static function encrypt(string $value, string $purpose): string
    {
        if ($value === '' || !function_exists('sodium_crypto_secretbox')) {
            return $value;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($value, $nonce, self::key($purpose)));
    }

    public static function decrypt(string $value, string $purpose): string
    {
        if (strpos($value, self::PREFIX) !== 0 || !function_exists('sodium_crypto_secretbox_open')) {
            return $value;
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key($purpose));

        return $plain === false ? '' : $plain;
    }
}
