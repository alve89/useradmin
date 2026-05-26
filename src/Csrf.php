<?php

declare(strict_types=1);

final class Csrf
{
    public static function token(array $config): string
    {
        $key = (string)($config['security']['csrf_key'] ?? 'csrf');

        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }

    public static function field(array $config): string
    {
        return '<input type="hidden" name="_csrf" value="' . h(self::token($config)) . '">';
    }

    public static function verify(array $config): void
    {
        $key = (string)($config['security']['csrf_key'] ?? 'csrf');
        $expected = $_SESSION[$key] ?? '';
        $actual = $_POST['_csrf'] ?? '';

        if (!is_string($actual) || !hash_equals((string)$expected, $actual)) {
            http_response_code(400);
            exit('Ungültiger CSRF-Token.');
        }
    }
}
