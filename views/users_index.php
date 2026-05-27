<?php
    $deletedId = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;
?>
<div class="page-head">
    <h1>Benutzer</h1>
    <a class="button" href="<?= h(app_url($config, '/?r=user-new')) ?>">Benutzer anlegen</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
        <tr>
            <th>UID</th>
            <th>Name</th>
            <th>E-Mail</th>
            <th>Gruppen</th>
            <th>Quota</th>
            <th>Status</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><code><?= h($user['uid']) ?></code></td>
                <td><?= h($user['display_name']) ?></td>
                <td><?= h($user['mail']) ?></td>
                <td><?= h(implode(', ', $user['groups'] ?? [])) ?></td>
                <td><?= h($user['quota']) ?></td>
                <td><?= ((int)$user['enabled'] === 1) ? 'aktiv' : 'inaktiv' ?></td>
                <td class="actions">
                    <a href="<?= h(app_url($config, '/?r=user-edit&id=' . (int)$user['id'])) ?>">Bearbeiten</a>
                    <form method="post" action="<?= h(app_url($config, '/?r=user-delete')) ?>" class="delete-user-form">
                        <?= Csrf::field($config) ?>
                        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                        <input type="hidden" name="kas_2fa" value="">

                        <button
                            class="link danger delete-user-button"
                            type="submit"
                            data-user-label="<?= h($user['display_name'] ?: $user['uid']) ?>"
                            data-user-uid="<?= h($user['uid']) ?>"
                            data-user-mail="<?= h($user['mail']) ?>"
                        >
                            Löschen
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($deletedId > 0): ?>
    <div class="undo-toast" id="undoToast">
        <span>Benutzer wurde gelöscht.</span>

        <form method="post" action="<?= h(app_url($config, '/?r=user-restore')) ?>">
            <?= Csrf::field($config) ?>
            <input type="hidden" name="id" value="<?= $deletedId ?>">
            <button type="submit" class="undo-button">Rückgängig</button>
        </form>

        <button type="button" class="undo-close" aria-label="Hinweis schließen" onclick="document.getElementById('undoToast').remove();">
            ×
        </button>
    </div>
<?php endif; ?>

<div id="deleteUserModal" class="modal-backdrop" hidden>
    <div class="modal-card danger-modal" role="dialog" aria-modal="true" aria-labelledby="deleteUserTitle">
        <h2 id="deleteUserTitle">Benutzer endgültig löschen?</h2>

        <p>
            Du bist dabei, diesen Benutzer endgültig zu löschen:
        </p>

        <div class="delete-user-summary">
            <strong id="deleteUserLabel"></strong>
            <span id="deleteUserUid"></span>
            <span id="deleteUserMail"></span>
        </div>

        <p class="alert">
            Achtung: Dabei wird auch das zugehörige IMAP-Postfach über die KAS-API gelöscht.
            Diese Aktion kann nicht rückgängig gemacht werden.
        </p>

        <label for="deleteKas2faInput">KAS-2FA-Code</label>
        <input
            id="deleteKas2faInput"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            placeholder="123456"
            maxlength="12"
        >

        <div class="modal-actions">
            <button type="button" class="button-secondary" id="deleteUserCancel">Abbrechen</button>
            <button type="button" class="danger-button" id="deleteUserConfirm">Endgültig löschen</button>
        </div>
    </div>
</div>