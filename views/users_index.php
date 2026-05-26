<div class="page-head">
    <h1>Benutzer</h1>
    <a class="button" href="<?= h(app_url($config, '/?r=user-new')) ?>">Benutzer anlegen</a>
</div>

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
                    <form method="post" action="<?= h(app_url($config, '/?r=user-delete')) ?>" onsubmit="return confirm('Benutzer wirklich löschen?');">
                        <?= Csrf::field($config) ?>
                        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                        <button class="link danger" type="submit">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
