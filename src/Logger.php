<?php

declare(strict_types=1);

final class Logger
{
    public static function info(array $config, string $message, array $context = []): void
    {
        self::write($config, 'INFO', $message, $context);
    }

    public static function warning(array $config, string $message, array $context = []): void
    {
        self::write($config, 'WARNING', $message, $context);
    }

    public static function error(array $config, string $message, array $context = []): void
    {
        self::write($config, 'ERROR', $message, $context);
    }

    public static function exception(array $config, Throwable $exception, array $context = []): void
    {
        self::error($config, $exception->getMessage(), array_merge($context, [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            // 'trace' => $exception->getTraceAsString(),
            'trace' => '[hidden]',
        ]));
    }

    private static function write(array $config, string $level, string $message, array $context = []): void
    {
        $logPath = (string)($config['app']['log_path'] ?? '');

        if ($logPath === '') {
            return;
        }

        $dir = dirname($logPath);

        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $entry = [
            'time' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => self::sanitize($context),
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ],
        ];

        @file_put_contents(
            $logPath,
            json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function sanitize(array $context): array
    {
        $sensitiveKeys = [
            'password',
            'password_hash',
            'db_password',
            'kas_password',
            'kas_auth_data',
            'sessionToken',
            'session_token',
            'token',
            'kas_2fa',
            'session_2fa',
            'code',
            'mail_password',
            'pass',
            'pwd',
        ];

        foreach ($context as $key => $value) {
            if (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
                $context[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $context[$key] = self::sanitize($value);
            }
        }

        return $context;
    }
}
