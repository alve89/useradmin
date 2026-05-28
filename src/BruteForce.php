<?php

declare(strict_types=1);

final class BruteForce
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function isLocked(string $username, string $ipAddress): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $maxAttempts = (int)($this->config['bruteforce']['max_attempts'] ?? 5);
        $windowMinutes = (int)($this->config['bruteforce']['window_minutes'] ?? 15);
        $lockMinutes = (int)($this->config['bruteforce']['lock_minutes'] ?? 15);

        /*
         * Sperre greift, wenn innerhalb der Sperrdauer bereits zu viele
         * fehlgeschlagene Logins vorhanden sind.
         */
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt
             FROM admin_login_attempts
             WHERE username = ?
               AND ip_address = ?
               AND success = 0
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );

        $stmt->execute([
            $this->normalizeUsername($username),
            $ipAddress,
            max($windowMinutes, $lockMinutes),
        ]);

        return ((int)$stmt->fetchColumn()) >= $maxAttempts;
    }

    public function remainingLockSeconds(string $username, string $ipAddress): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $maxAttempts = (int)($this->config['bruteforce']['max_attempts'] ?? 5);
        $lockMinutes = (int)($this->config['bruteforce']['lock_minutes'] ?? 15);

        $stmt = $this->db->prepare(
            'SELECT attempted_at
             FROM admin_login_attempts
             WHERE username = ?
               AND ip_address = ?
               AND success = 0
             ORDER BY attempted_at DESC
             LIMIT ?'
        );

        $stmt->bindValue(1, $this->normalizeUsername($username), PDO::PARAM_STR);
        $stmt->bindValue(2, $ipAddress, PDO::PARAM_STR);
        $stmt->bindValue(3, $maxAttempts, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        if (count($rows) < $maxAttempts) {
            return 0;
        }

        $oldestRelevant = end($rows);
        $attemptTime = strtotime((string)$oldestRelevant['attempted_at']);

        if ($attemptTime === false) {
            return 0;
        }

        $unlockAt = $attemptTime + ($lockMinutes * 60);
        $remaining = $unlockAt - time();

        return max(0, $remaining);
    }

    public function record(string $username, string $ipAddress, bool $success): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO admin_login_attempts (username, ip_address, success)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            $this->normalizeUsername($username),
            $ipAddress,
            $success ? 1 : 0,
        ]);

        if ($success) {
            $this->clearFailures($username, $ipAddress);
        }

        $this->cleanup();
    }

    private function clearFailures(string $username, string $ipAddress): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM admin_login_attempts
             WHERE username = ?
               AND ip_address = ?
               AND success = 0'
        );

        $stmt->execute([
            $this->normalizeUsername($username),
            $ipAddress,
        ]);
    }

    private function cleanup(): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM admin_login_attempts
             WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );

        $stmt->execute();
    }

    private function isEnabled(): bool
    {
        return (bool)($this->config['bruteforce']['enabled'] ?? true);
    }

    private function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }
}
