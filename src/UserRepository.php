<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** @return list<array> active people, for the login picker */
    public function active(): array
    {
        return $this->db->query('SELECT id, name FROM users WHERE active = 1 ORDER BY name COLLATE NOCASE')->fetchAll();
    }

    /** @return list<array> everyone already approved, active first */
    public function all(): array
    {
        return $this->db->query('SELECT * FROM users WHERE pending = 0 ORDER BY active DESC, name COLLATE NOCASE')->fetchAll();
    }

    /** @return list<array> self-registered people waiting for an admin, oldest first */
    public function pending(): array
    {
        return $this->db->query('SELECT * FROM users WHERE pending = 1 ORDER BY created_at, id')->fetchAll();
    }

    public function pendingCount(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users WHERE pending = 1')->fetchColumn();
    }

    public function isPendingName(string $name): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE pending = 1 AND name = ?');
        $stmt->execute([self::validName($name)]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Creates a person who can log in right away, or (with $pending) a self-registered
     * request that stays inactive until an admin approves it.
     *
     * @throws UserError
     */
    public function create(string $name, string $pin, bool $isAdmin, bool $pending = false): int
    {
        $name = self::validName($name);
        self::assertValidPin($pin);

        try {
            $this->db->prepare('INSERT INTO users (name, pin_hash, is_admin, active, pending) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, password_hash($pin, PASSWORD_DEFAULT), (int) $isAdmin, (int) !$pending, (int) $pending]);
        } catch (PDOException $e) {
            throw self::translate($e);
        }

        return (int) $this->db->lastInsertId();
    }

    /** @throws UserError */
    public function approve(int $id): string
    {
        $stmt = $this->db->prepare('UPDATE users SET active = 1, pending = 0 WHERE id = ? AND pending = 1');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new UserError('Questa richiesta non è più in attesa.');
        }

        return $this->find($id)['name'];
    }

    /** Deletes a pending request; approved people are deactivated instead, to keep their history. @throws UserError */
    public function reject(int $id): string
    {
        $user = $this->find($id);
        if (!$user || !$user['pending']) {
            throw new UserError('Questa richiesta non è più in attesa.');
        }
        $this->db->prepare('DELETE FROM users WHERE id = ? AND pending = 1')->execute([$id]);

        return $user['name'];
    }

    /** @throws UserError */
    public function update(int $id, string $name, bool $isAdmin, bool $active): void
    {
        $name = self::validName($name);
        $user = $this->find($id) ?? throw new UserError('Questa persona non esiste più.');

        $losesAdmin = $user['is_admin'] && $user['active'] && (!$isAdmin || !$active);
        if ($losesAdmin && $this->activeAdminCount() === 1) {
            throw new UserError('Serve almeno un amministratore attivo.');
        }

        try {
            $this->db->prepare('UPDATE users SET name = ?, is_admin = ?, active = ? WHERE id = ?')
                ->execute([$name, (int) $isAdmin, (int) $active, $id]);
        } catch (PDOException $e) {
            throw self::translate($e);
        }
    }

    /** @throws UserError */
    public function setPin(int $id, string $pin): void
    {
        self::assertValidPin($pin);
        $this->db->prepare('UPDATE users SET pin_hash = ?, failed_attempts = 0, locked_until = 0 WHERE id = ?')
            ->execute([password_hash($pin, PASSWORD_DEFAULT), $id]);
    }

    /** Counts a wrong PIN; returns the attempts left before the account locks (0 = just locked). */
    public function recordFailure(int $id, int $maxAttempts, int $lockSeconds): int
    {
        $user = $this->find($id);
        $failed = (int) $user['failed_attempts'] + 1;

        if ($failed >= $maxAttempts) {
            $this->db->prepare('UPDATE users SET failed_attempts = 0, locked_until = ? WHERE id = ?')
                ->execute([time() + $lockSeconds, $id]);

            return 0;
        }
        $this->db->prepare('UPDATE users SET failed_attempts = ? WHERE id = ?')->execute([$failed, $id]);

        return $maxAttempts - $failed;
    }

    public function clearFailures(int $id): void
    {
        $this->db->prepare('UPDATE users SET failed_attempts = 0, locked_until = 0 WHERE id = ?')->execute([$id]);
    }

    private function activeAdminCount(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users WHERE is_admin = 1 AND active = 1')->fetchColumn();
    }

    /** @throws UserError */
    private static function validName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) > 60) {
            throw new UserError('Il nome deve avere tra 1 e 60 caratteri.');
        }

        return $name;
    }

    /** @throws UserError */
    private static function assertValidPin(string $pin): void
    {
        if (!preg_match('/^\d{4}$/', $pin)) {
            throw new UserError('Il PIN deve essere di 4 cifre.');
        }
    }

    private static function translate(PDOException $e): UserError|PDOException
    {
        return str_contains($e->getMessage(), 'UNIQUE')
            ? new UserError('Esiste già una persona con questo nome.')
            : $e;
    }
}
