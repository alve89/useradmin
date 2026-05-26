<div class="page-head">
    <h1>Gruppen</h1>
    <a class="button" href="<?= h(app_url($config, '/?r=group-new')) ?>">Gruppe anlegen</a>
</div>

<div class="card">
    <table>
        <thead>
        <tr>
            <th>Name</th>
            <th>Beschreibung</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($groups as $group): ?>
            <tr>
                <td><strong><?= h($group['name']) ?></strong></td>
                <td><?= h($group['description'] ?? '') ?></td>
                <td class="actions">
                    <a href="<?= h(app_url($config, '/?r=group-edit&id=' . (int)$group['id'])) ?>">Bearbeiten</a>
                    <form method="post" action="<?= h(app_url($config, '/?r=group-delete')) ?>" onsubmit="return confirm('Gruppe wirklich löschen?');">
                        <?= Csrf::field($config) ?>
                        <input type="hidden" name="id" value="<?= (int)$group['id'] ?>">
                        <button class="link danger" type="submit">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
