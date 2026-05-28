<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$configFile = $basePath . '/config/config.php';

if (!is_file($configFile)) {
    fwrite(STDERR, "Config fehlt: {$configFile}\n");
    exit(1);
}

$config = require $configFile;

require $basePath . '/src/Database.php';

$db = Database::connect($config);

$tablePrefix = (string)($config['database']['table_prefix'] ?? '');

if ($tablePrefix !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $tablePrefix)) {
    fwrite(STDERR, "Ungültiger table_prefix. Erlaubt sind nur Buchstaben, Zahlen und Unterstriche.\n");
    exit(1);
}

$migrationsTable = $tablePrefix . 'schema_migrations';

$db->exec(
    "CREATE TABLE IF NOT EXISTS `{$migrationsTable}` (
        `migration` VARCHAR(255) NOT NULL,
        `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$migrationDir = $basePath . '/migrations';
$files = glob($migrationDir . '/*.sql') ?: [];
sort($files);

if ($files === []) {
    echo "Keine Migrationen gefunden.\n";
    exit(0);
}

$executedStmt = $db->query("SELECT `migration` FROM `{$migrationsTable}`");
$executed = $executedStmt->fetchAll(PDO::FETCH_COLUMN);
$executed = array_flip($executed);

foreach ($files as $file) {
    $name = basename($file);

    if (isset($executed[$name])) {
        echo "Überspringe {$name} (bereits ausgeführt)\n";
        continue;
    }

    echo "Führe {$name} aus...\n";

    $sql = file_get_contents($file);

    if ($sql === false || trim($sql) === '') {
        echo "Überspringe {$name} (leer)\n";
        continue;
    }

    $sql = str_replace('{{prefix}}', $tablePrefix, $sql);

    try {
        $db->exec($sql);

        $stmt = $db->prepare("INSERT INTO `{$migrationsTable}` (`migration`) VALUES (?)");
        $stmt->execute([$name]);

        echo "OK: {$name}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "FEHLER in {$name}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Migrationen abgeschlossen.\n";