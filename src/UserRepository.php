<?php

declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $users = $this->db
            ->query('SELECT * FROM sso_users ORDER BY family_name ASC, given_name ASC, uid ASC')
            ->fetchAll();

        foreach ($users as &$user) {
            $user['groups'] = $this->groupsForUser((int)$user['id']);
        }

        return $users;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sso_users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $user['group_ids'] = $this->groupIdsForUser($id);
        $user['groups'] = $this->groupsForUser($id);

        return $user;
    }

    public function findByUid(string $uid): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sso_users WHERE uid = ?');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $user['groups'] = $this->groupsForUser((int)$user['id']);

        return $user;
    }

    public function create(array $data): void
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO sso_users
                (uid, enabled, given_name, family_name, display_name, mail, imap_user, quota, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute($this->mapUserData($data));

            $id = (int)$this->db->lastInsertId();
            $this->syncGroups($id, $data['group_ids'] ?? []);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): void
    {
        $this->db->beginTransaction();

        try {
            $values = $this->mapUserData($data);
            $values[] = $id;

            $stmt = $this->db->prepare(
                'UPDATE sso_users SET
                    uid = ?,
                    enabled = ?,
                    given_name = ?,
                    family_name = ?,
                    display_name = ?,
                    mail = ?,
                    imap_user = ?,
                    quota = ?,
                    notes = ?
                 WHERE id = ?'
            );

            $stmt->execute($values);
            $this->syncGroups($id, $data['group_ids'] ?? []);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM sso_users WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function mapUserData(array $data): array
    {
        $uid = trim(strtolower((string)$data['uid']));
        $given = trim((string)$data['given_name']);
        $family = trim((string)$data['family_name']);
        $display = trim((string)($data['display_name'] ?? ''));
        $mail = trim(strtolower((string)$data['mail']));
        $imap = trim(strtolower((string)$data['imap_user']));
        $quota = trim((string)($data['quota'] ?? '512 MB'));

        if ($display === '') {
            $display = trim($given . ' ' . $family);
        }

        return [
            $uid,
            isset($data['enabled']) ? 1 : 0,
            $given,
            $family,
            $display,
            $mail,
            $imap,
            $quota !== '' ? $quota : '512 MB',
            trim((string)($data['notes'] ?? '')) ?: null,
        ];
    }

    private function syncGroups(int $userId, array $groupIds): void
    {
        $this->db->prepare('DELETE FROM sso_user_groups WHERE user_id = ?')->execute([$userId]);

        $stmt = $this->db->prepare('INSERT INTO sso_user_groups (user_id, group_id) VALUES (?, ?)');

        foreach ($groupIds as $groupId) {
            $stmt->execute([$userId, (int)$groupId]);
        }
    }

    private function groupIdsForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT group_id FROM sso_user_groups WHERE user_id = ?');
        $stmt->execute([$userId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'group_id'));
    }

    private function groupsForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.name
             FROM sso_groups g
             JOIN sso_user_groups ug ON ug.group_id = g.id
             WHERE ug.user_id = ?
             ORDER BY g.name ASC'
        );
        $stmt->execute([$userId]);

        return array_column($stmt->fetchAll(), 'name');
    }
}
