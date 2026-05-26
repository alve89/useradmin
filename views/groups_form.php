<?php
$isEdit = is_array($group);
$value = static fn(string $key, string $default = ''): string => h($isEdit ? ($group[$key] ?? $default) : $default);
?>

<div class="page-head">
    <h1><?= $isEdit ? 'Gruppe bearbeiten' : 'Gruppe anlegen' ?></h1>
    <a href="<?= h(app_url($config, '/?r=groups')) ?>">Zurück</a>
</div>

<section class="card">
    <form method="post">
        <?= Csrf::field($config) ?>

        <label>Name</label>
        <input name="name" value="<?= $value('name') ?>" required>

        <label>Beschreibung</label>
        <textarea name="description" rows="4"><?= $value('description') ?></textarea>

        <button type="submit">Speichern</button>
    </form>
</section>
