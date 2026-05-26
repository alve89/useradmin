<section class="card narrow">
    <h1>Anmeldung</h1>
    <p class="muted">Administrativer Zugriff auf die SSO-Benutzerverwaltung.</p>

    <?php if (!empty($error)): ?>
        <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= Csrf::field($config) ?>

        <label>Benutzername</label>
        <input name="username" type="text" autocomplete="username" autofocus required>

        <label>Passwort</label>
        <input name="password" type="password" autocomplete="current-password" required>

        <button type="submit">Anmelden</button>
    </form>
</section>
