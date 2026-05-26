<?php

declare(strict_types=1);

return [
    'app' => [
        // Absoluter Installationspfad des Tools.
        'base_path' => '/www/htdocs/w013e4ea/rest/useradmin',

        // Öffentliche Basis-URL, ohne abschließenden Slash.
        // Beispiele:
        // 'https://useradmin.die-kerwe.de'
        // 'https://sso.die-kerwe.de/useradmin'
        'base_url' => 'https://useradmin.die-kerwe.de',

        'name' => 'Kerwe Benutzerverwaltung',
        'default_quota' => '512 MB',
        'mail_domain_suffix' => '@die-kerwe.de',
    ],

    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'DATENBANKNAME',
        'user' => 'DATENBANKUSER',
        'password' => 'DATENBANKPASSWORT',
        'charset' => 'utf8mb4',
    ],

    'admin' => [
        'username' => 'stefan.herzog',

        // Erzeugen mit:
        // php -r 'echo password_hash("DEIN_PASSWORT", PASSWORD_DEFAULT) . PHP_EOL;'
        'password_hash' => 'HIER_PASSWORD_HASH_EINTRAGEN',
    ],

    'security' => [
        'session_name' => 'KERWE_USERADMIN',
        'csrf_key' => 'kerwe_useradmin_csrf',
    ],
];
