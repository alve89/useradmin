<?php

declare(strict_types=1);

final class Auth
{
    public static function login(array $config, string $username, string $password): bool
    {
        $username = strtolower(trim($username));
        $admins = $config['admins'] ?? [];

        if ($username === '' || !isset($admins[$username])) {
            return false;
        }

        $hash = (string)($admins[$username]['password_hash'] ?? '');

        if ($hash === '' || $hash === 'HIER_PASSWORD_HASH_EINTRAGEN') {
            return false;
        }

        if (!password_verify($password, $hash)) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['admin_user'] = $username;
        $_SESSION['admin_display_name'] = (string)($admins[$username]['display_name'] ?? $username);
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

public static function displayName(): ?string
{
    return $_SESSION['admin_display_name'] ?? self::user();
}



}
