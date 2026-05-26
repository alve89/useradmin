<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title><?= h($config['app']['name'] ?? 'Benutzerverwaltung') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?= h(app_url($config, '/assets/app.css')) ?>" rel="stylesheet">
</head>
<body>
<header class="topbar">
    <div class="brand">
        <?= h($config['app']['name'] ?? 'Benutzerverwaltung') ?>
    </div>

    <?php
    $currentRoute = (string)($_GET['r'] ?? 'users');

    $isUsersActive = in_array($currentRoute, [
        'users',
        'user-new',
        'user-edit',
        'user-delete',
    ], true);

    $isGroupsActive = in_array($currentRoute, [
        'groups',
        'group-new',
        'group-edit',
        'group-delete',
    ], true);
    ?>

    <?php if (Auth::check()): ?>
        <nav>
            <a
                class="<?= $isUsersActive ? 'active' : '' ?>"
                href="<?= h(app_url($config, '/?r=users')) ?>"
            >
                Benutzer
            </a>

            <a
                class="<?= $isGroupsActive ? 'active' : '' ?>"
                href="<?= h(app_url($config, '/?r=groups')) ?>"
            >
                Gruppen
            </a>

            <a href="<?= h(app_url($config, '/?r=logout')) ?>">Abmelden</a>
        </nav>
    <?php endif; ?>
</header>

<main class="container">
    <?= $content ?>
</main>
<script src="<?= h(app_url($config, '/assets/app.js')) ?>" defer></script>
</body>
</html>
