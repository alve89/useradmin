<?php

declare(strict_types=1);

function config_value(array $config, string $key, mixed $default = null): mixed
{
    $parts = explode('.', $key);
    $value = $config;

    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function app_url(array $config, string $path = ''): string
{
    $base = rtrim((string)config_value($config, 'app.base_url', ''), '/');
    $path = '/' . ltrim($path, '/');

    return $base . ($path === '/' ? '' : $path);
}

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_to(array $config, string $path): never
{
    header('Location: ' . app_url($config, $path));
    exit;
}


function db_table(array $config, string $table): string
{
    $prefix = (string)($config['database']['table_prefix'] ?? '');

    if ($prefix !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
        throw new RuntimeException('Ungültiger database.table_prefix.');
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new RuntimeException('Ungültiger Tabellenname.');
    }

    return '`' . $prefix . $table . '`';
}