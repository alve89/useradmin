<?php

declare(strict_types=1);

final class PasswordCrypto
{
    public static function encrypt(array $config, string $plainText): array
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('PHP sodium extension ist nicht verfügbar.');
        }

        $keyBase64 = (string)($config['password_reset']['encryption_key'] ?? '');
        $key = base64_decode($keyBase64, true);

        if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('password_reset.encryption_key ist ungültig.');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox($plainText, $nonce, $key);

        return [
            'encrypted_password' => base64_encode($cipherText),
            'encryption_nonce' => base64_encode($nonce),
        ];
    }

    public static function decrypt(array $config, string $encryptedPassword, string $nonceBase64): string
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('PHP sodium extension ist nicht verfügbar.');
        }

        $key = base64_decode((string)($config['password_reset']['encryption_key'] ?? ''), true);
        $cipherText = base64_decode($encryptedPassword, true);
        $nonce = base64_decode($nonceBase64, true);

        if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('password_reset.encryption_key ist ungültig.');
        }

        if (!is_string($cipherText) || !is_string($nonce)) {
            throw new RuntimeException('Verschlüsselte Passwortdaten sind ungültig.');
        }

        $plainText = sodium_crypto_secretbox_open($cipherText, $nonce, $key);

        if (!is_string($plainText)) {
            throw new RuntimeException('Passwort konnte nicht entschlüsselt werden.');
        }

        return $plainText;
    }
}
