<?php

declare(strict_types=1);

final class View
{
    public static function render(array $config, string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = rtrim((string)$config['app']['base_path'], '/') . '/views/' . $template . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found: ' . $template);
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require rtrim((string)$config['app']['base_path'], '/') . '/views/layout.php';
    }
}
