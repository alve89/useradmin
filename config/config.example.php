<?php

declare(strict_types=1);

return [
    'app' => [
        // Absolute installation path of the tool.
        // By default, this resolves to the project root directory.
        'base_path' => dirname(__DIR__) . '/',

        // Public base URL without a trailing slash.
        // Examples:
        // 'https://useradmin.example.com'
        // 'https://sso.example.com/useradmin'
        'base_url' => 'https://useradmin.example.com',

        // Display name of the application.
        'name' => 'User Administration',

        // Default quota for newly created users, relevant for e. g. Nextcloud.
        'default_quota' => '512 MB',

        // Mail domain suffix used for user mailboxes.
        // Example: username "john.doe" becomes "john.doe@example.com".
        'mail_domain_suffix' => '@example.com',

        // Absolute path to the application log file.
        'log_path' => '/absolute/path/to/useradmin/logs/useradmin.log',
    ],

    'db' => [
        // Database connection settings.
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'database_name',
        'user' => 'database_user',
        'password' => 'database_password',
        'charset' => 'utf8mb4',
    ],

    'admins' => [
        // Generate a password hash with:
        // php -r 'echo password_hash("YOUR_PASSWORD", PASSWORD_DEFAULT) . PHP_EOL;'

        'admin.user' => [
            'password_hash' => '$2y$10$...',
            'display_name' => 'Admin User',
        ],

        'second.admin' => [
            'password_hash' => '$2y$10$....',
            'display_name' => 'Second Admin',
        ],
    ],

    'security' => [
        // Custom PHP session name used by the application.
        'session_name' => 'USERADMIN',

        // Session key under which the generated CSRF token is stored.
        // This is only the session field name, not the CSRF token itself.
        'csrf_key' => 'useradmin_csrf',
    ],

    'bruteforce' => [
        // Enable or disable brute-force protection for login attempts.
        'enabled' => true,

        // Maximum number of failed login attempts allowed within the time window.
        'max_attempts' => 5,

        // Time window for failed login attempts, in minutes.
        'window_minutes' => 15,

        // Lockout duration after exceeding the maximum number of attempts, in minutes.
        'lock_minutes' => 15,
    ],

    'kas' => [
        // Enable or disable KAS API integration.
        'enabled' => true,

        // KAS login credentials.
        'login' => 'kas_login',
        'password' => 'kas_password',

        // KAS SOAP WSDL endpoints.
        'auth_wsdl' => 'https://kasapi.kasserver.com/soap/wsdl/KasAuth.wsdl',
        'api_wsdl' => 'https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl',

        // Authentication type used by the KAS API.
        'auth_type' => 'session',
    ],

    'password_reset' => [
        // Enable or disable the password reset workflow.
        'enabled' => true,

        // Lifetime of a password reset request token, in minutes.
        'request_token_lifetime_minutes' => 30,

        // Lifetime of an admin approval link, in hours.
        'approval_lifetime_hours' => 24,

        // Maximum number of password reset requests per IP address per hour.
        'max_requests_per_ip_per_hour' => 5,

        // Sender address used for password reset emails.
        'from_email' => 'support@example.com',
        'from_name' => 'User Administration',

        // Subject used for password reset emails.
        'subject' => 'Reset password',

        // Encryption key used for protecting sensitive reset data.
        // Generate a secure key before using this in production.
        'encryption_key' => 'replace_this_with_a_secure_base64_encoded_key',

        // Email addresses that should receive admin approval notifications.
        'admin_notify_emails' => [
            'admin@example.com',
        ],
    ],
];