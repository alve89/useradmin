<?php
$isEdit = is_array($user);
$selectedGroups = $isEdit ? ($user['group_ids'] ?? []) : [];
$value = static fn(string $key, string $default = ''): string => h($isEdit ? ($user[$key] ?? $default) : $default);
?>

<div class="page-head">
    <h1><?= $isEdit ? 'Benutzer bearbeiten' : 'Benutzer anlegen' ?></h1>
    <a href="<?= h(app_url($config, '/?r=users')) ?>">Zurück</a>
</div>

<section class="card">
    <?php if (!empty($error)): ?>
        <div class="alert">
            <?= h($error) ?>
        </div>
    <?php endif; ?>
    <form method="post" data-user-form="1" data-is-edit="<?= $isEdit ? '1' : '0' ?>" data-mail-suffix="<?= h($mailSuffix) ?>">
        <?= Csrf::field($config) ?>

        <div class="grid">
            <div>
                <label>UID</label>
                <input
                    id="uid"
                    name="uid"
                    value="<?= $value('uid') ?>"
                    placeholder="vorname.nachname"
                    required
                >
            </div>

            <div>
                <label>Status</label>
                <label class="check">
                    <input type="checkbox" name="enabled" value="1" <?= (!$isEdit || (int)$user['enabled'] === 1) ? 'checked' : '' ?>>
                    aktiv
                </label>
            </div>

            <div>
                <label>Vorname</label>
                <input
                    id="given_name"
                    name="given_name"
                    value="<?= $value('given_name') ?>"
                    required
                >
            </div>

            <div>
                <label>Nachname</label>
                <input
                    id="family_name"
                    name="family_name"
                    value="<?= $value('family_name') ?>"
                    required
                >
            </div>

            <div>
                <label>Anzeigename</label>
                <input
                    id="display_name"
                    name="display_name"
                    value="<?= $value('display_name') ?>"
                    placeholder="wird sonst aus Vor-/Nachname gebildet"
                >
            </div>

            <div>
                <label>Quota</label>
                <input
                    id="quota"
                    name="quota"
                    value="<?= $value('quota', (string)$defaultQuota) ?>"
                    required
                >
            </div>

            <div>
                <label>E-Mail</label>
                <input
                    id="mail"
                    name="mail"
                    value="<?= $value('mail') ?>"
                    placeholder="vorname.nachname<?= h($mailSuffix) ?>"
                    type="email"
                    required
                >
            </div>

            <div>
                <label>IMAP-User</label>
                <input 
                    id="imap_user"
                    name="imap_user"
                    value="<?= $value('imap_user') ?>"
                    placeholder="vorname.nachname<?= h($mailSuffix) ?>"
                    type="email" 
                    required
                >
            </div>

            <div>
                <label for="password">Passwort</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    value=""
                    autocomplete="new-password"
                    placeholder="<?= $isEdit ? 'leer lassen = nicht ändern' : 'Pflichtfeld für neues Postfach' ?>"
                    <?= $isEdit ? '' : 'required' ?>
                >
            </div>

            <div>
                <label for="password_confirm">Passwort wiederholen</label>
                <input
                    id="password_confirm"
                    name="password_confirm"
                    type="password"
                    value=""
                    autocomplete="new-password"
                    placeholder="<?= $isEdit ? 'leer lassen = nicht ändern' : 'Passwort erneut eingeben' ?>"
                    <?= $isEdit ? '' : 'required' ?>
                >
            </div>
            <div>
                <label>&nbsp;</label>
                <p class="muted" style="margin-top: 10px;">
                    Das Passwort wird nicht gespeichert. Es wird nur an die KAS-API übergeben, um das IMAP-/Mailbox-Passwort zu ändern.
                </p>
            </div>
        </div>

        <label>Gruppen</label>
        <div class="group-list">
            <?php foreach ($groups as $group): ?>
                <label class="check">
                    <input
                        type="checkbox"
                        name="group_ids[]"
                        value="<?= (int)$group['id'] ?>"
                        <?= in_array((int)$group['id'], $selectedGroups, true) ? 'checked' : '' ?>
                    >
                    <?= h($group['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <label>Notizen</label>
        <textarea name="notes" rows="4"><?= $value('notes') ?></textarea>
        
        <input id="kas_2fa" name="kas_2fa" type="hidden" value="">

        <button id="saveUserButton" type="submit">Speichern</button>
    </form>
    <div id="kas2faModal" class="modal-backdrop" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="kas2faTitle">
            <h2 id="kas2faTitle">KAS-2FA-Code erforderlich</h2>

            <p class="muted">
                Für die Änderung des IMAP-Passworts wird ein aktueller KAS-2FA-Code benötigt.
                Der Code wird nicht gespeichert.
            </p>

            <label for="kas2faInput">2FA-Code</label>
            <input
                id="kas2faInput"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="123456"
                maxlength="12"
            >

            <div class="modal-actions">
                <button type="button" class="button-secondary" id="kas2faCancel">Abbrechen</button>
                <button type="button" id="kas2faConfirm">Fortfahren</button>
            </div>
        </div>
    </div>

</section>

