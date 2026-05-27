<div class="narrow card">
    <h1>Passwort vergessen?</h1>

    <?php if (!empty($message)): ?>
        <div class="alert">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <p class="muted">
        Gib deinen Benutzernamen ein.
        Falls ein passendes Konto existiert, senden wir dir einen Link zum Zurücksetzen des Passworts.
    </p>

    <form method="post">
        <?= Csrf::field($config) ?>

        <label for="identifier">Benutzername oder E-Mail-Adresse</label>
        <input
            id="identifier"
            name="identifier"
            required
            autocomplete="username"
            placeholder="max.mustermann"
        >

        <button type="submit" style="margin-top: 18px;">
            Reset-Link senden
        </button>
    </form>
</div>
