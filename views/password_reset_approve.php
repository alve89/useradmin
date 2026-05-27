<div class="narrow card">
    <h1>Passwortänderung freigeben</h1>

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

    <?php if (empty($done) && !empty($resetRequest)): ?>
        <p class="muted">
            Mit der Freigabe wird das IMAP-/KAS-Passwort des folgenden Benutzers geändert.
            Das neue Passwort wird nicht angezeigt.
        </p>

        <div class="delete-user-summary">
            <strong><?= h($resetRequest['display_name'] ?: $resetRequest['uid']) ?></strong>
            <span>UID: <?= h($resetRequest['uid']) ?></span>
            <span>E-Mail: <?= h($resetRequest['mail']) ?></span>
            <span>IMAP-User: <?= h($resetRequest['imap_user']) ?></span>
        </div>

        <form method="post">
            <?= Csrf::field($config) ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">

            <label for="kas_2fa">KAS-2FA-Code</label>
            <input
                id="kas_2fa"
                name="kas_2fa"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
                maxlength="12"
                placeholder="123456"
            >

            <div class="modal-actions" style="margin-top: 18px;">
                <a class="button-secondary" href="<?= h(app_url($config, '/?r=users')) ?>">Abbrechen</a>
                <button type="submit">Passwortänderung freigeben</button>
            </div>
        </form>
    <?php endif; ?>
</div>

