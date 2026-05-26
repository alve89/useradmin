<?php

declare(strict_types=1);

final class Auth
{
    public static function login(array $config, string $username, string $password): bool
    {
        $expectedUser = (string)$config['admin']['username'];
        $hash = (string)$config['admin']['password_hash'];

        if (!hash_equals($expectedUser, $username)) {
            return false;
        }

        if ($hash === '' || $hash === 'HIER_PASSWORD_HASH_EINTRAGEN') {
            return false;
        }

        if (!password_verify($password, $hash)) {
            return false;
        }


        session_regenerate_id(true);


        $_SESSION['admin_user'] = $username;
        $_SESSION['login_time'] = time();

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function check(): bool
    {
        return isset($_SESSION['admin_user']) && is_string($_SESSION['admin_user']);
    }

    public static function requireLogin(array $config): void
    {
        if (!self::check()) {
            redirect_to($config, '/?r=login');
        }
    }

    public static function user(): ?string
    {
        return $_SESSION['admin_user'] ?? null;
    }
}
