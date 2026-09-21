<?php
declare(strict_types=1);

/** Encrypts secrets (like the AI API key) with the app key from the private config. */
final class Crypto
{
    private static function key(): string
    {
        $k = base64_decode((string) Config::get("app_key"), true);
        if (!$k || strlen($k) !== 32) {
            throw new RuntimeException("App key missing.");
        }
        return $k;
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === "") {
            return "";
        }
        $key = self::key();
        if (function_exists("sodium_crypto_secretbox")) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return "s1:" . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
        }
        $iv  = random_bytes(12);
        $tag = "";
        $ct  = openssl_encrypt($plain, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag);
        return "o1:" . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === "") {
            return "";
        }
        $key = self::key();
        [$v, $b64] = array_pad(explode(":", $stored, 2), 2, "");
        $raw = base64_decode($b64, true) ?: "";
        if ($v === "s1" && function_exists("sodium_crypto_secretbox_open")) {
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $out   = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
            return $out === false ? "" : $out;
        }
        if ($v === "o1") {
            $out = openssl_decrypt(substr($raw, 28), "aes-256-gcm", $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            return $out === false ? "" : $out;
        }
        return "";
    }
}
