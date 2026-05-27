<?php

declare(strict_types=1);

final class PasswordResetRequestRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function countRecentRequestsByIp(string $ipAddress, int $minutes): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM password_reset_requests
             WHERE requested_ip = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );

        $stmt->execute([$ipAddress, $minutes]);

        return (int)$stmt->fetchColumn();
    }

    public function createRequest(int $userId, string $requestTokenHash, string $ipAddress, int $lifetimeMinutes): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO password_reset_requests
                (user_id, request_token_hash, status, requested_ip, expires_at)
             VALUES
                (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
        );

        $stmt->execute([
            $userId,
            $requestTokenHash,
            'requested',
            $ipAddress,
            $lifetimeMinutes,
        ]);
    }

    public function cleanupExpired(): void
    {
        $stmt = $this->db->prepare(
            'UPDATE password_reset_requests
             SET status = ?
             WHERE status IN (?, ?)
               AND expires_at < NOW()'
        );

        $stmt->execute([
            'expired',
            'requested',
            'pending_admin',
        ]);
    }

public function findValidRequestByToken(string $token): ?array
{
    $tokenHash = hash('sha256', $token);

    $stmt = $this->db->prepare(
        'SELECT prr.*, u.uid, u.given_name, u.family_name, u.display_name, u.mail, u.imap_user
         FROM password_reset_requests prr
         INNER JOIN sso_users u ON u.id = prr.user_id
         WHERE prr.request_token_hash = ?
           AND prr.status = ?
           AND prr.expires_at > NOW()
         LIMIT 1'
    );

    $stmt->execute([
        $tokenHash,
        'requested',
    ]);

    $row = $stmt->fetch();

    return $row ?: null;
}


public function markPendingAdmin(
    int $id,
    string $approveTokenHash,
    string $encryptedPassword,
    string $encryptionNonce,
    int $approvalLifetimeHours
): void {
    $stmt = $this->db->prepare(
        'UPDATE password_reset_requests
         SET approve_token_hash = ?,
             encrypted_password = ?,
             encryption_nonce = ?,
             status = ?,
             expires_at = DATE_ADD(NOW(), INTERVAL ? HOUR)
         WHERE id = ?
           AND status = ?'
    );

    $stmt->execute([
        $approveTokenHash,
        $encryptedPassword,
        $encryptionNonce,
        'pending_admin',
        $approvalLifetimeHours,
        $id,
        'requested',
    ]);
}


public function findPendingApprovalByToken(string $token): ?array
{
    $tokenHash = hash('sha256', $token);

    $stmt = $this->db->prepare(
        'SELECT prr.*, u.uid, u.given_name, u.family_name, u.display_name, u.mail, u.imap_user
         FROM password_reset_requests prr
         INNER JOIN sso_users u ON u.id = prr.user_id
         WHERE prr.approve_token_hash = ?
           AND prr.status = ?
           AND prr.expires_at > NOW()
           AND prr.encrypted_password IS NOT NULL
           AND prr.encryption_nonce IS NOT NULL
         LIMIT 1'
    );

    $stmt->execute([
        $tokenHash,
        'pending_admin',
    ]);

    $row = $stmt->fetch();

    return $row ?: null;
}

public function markCompleted(int $id, string $approvedBy): void
{
    $stmt = $this->db->prepare(
        'UPDATE password_reset_requests
         SET status = ?,
             approved_by = ?,
             approved_at = NOW(),
             used_at = NOW(),
             encrypted_password = NULL,
             encryption_nonce = NULL
         WHERE id = ?
           AND status = ?'
    );

    $stmt->execute([
        'completed',
        $approvedBy,
        $id,
        'pending_admin',
    ]);
}

public function clearSensitiveData(int $id): void
{
    $stmt = $this->db->prepare(
        'UPDATE password_reset_requests
         SET encrypted_password = NULL,
             encryption_nonce = NULL
         WHERE id = ?'
    );

    $stmt->execute([$id]);
}















}