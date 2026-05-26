<?php

declare(strict_types=1);

final class GroupRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        return $this->db
            ->query('SELECT * FROM sso_groups ORDER BY name ASC')
            ->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sso_groups WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): void
    {
        $stmt = $this->db->prepare('INSERT INTO sso_groups (name, description) VALUES (?, ?)');
        $stmt->execute([
            trim((string)$data['name']),
            trim((string)($data['description'] ?? '')) ?: null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE sso_groups SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([
            trim((string)$data['name']),
            trim((string)($data['description'] ?? '')) ?: null,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM sso_groups WHERE id = ?');
        $stmt->execute([$id]);
    }
}
