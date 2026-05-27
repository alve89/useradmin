<div class="narrow card">
    <h1>Neues Passwort setzen</h1>

    <?php if (!empty($error)): ?>
        <div class="alert">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($done)): ?>
        <p class="muted">
            Gib dein neues Passwort zweimal ein. Die Änderung wird anschließend zur Freigabe an einen Admin gesendet.
        </p>

        <form method="post">
            <?= Csrf::field($config) ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">

            <label for="password">Neues Passwort</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">

            <label for="password_confirm">Passwort wiederholen</label>
            <input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password">

            <button type="submit" style="margin-top: 18px;">Passwortänderung anfordern</button>
        </form>
    <?php endif; ?>
</div>
