<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

final class Db
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $path = config('db_path');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        // The base schema is idempotent; later changes live in numbered files under migrations/.
        $pdo->exec((string) file_get_contents(APP_ROOT . '/schema.sql'));
        self::$pdo = $pdo;
        self::migrate($pdo);

        return $pdo;
    }

    /** Applies each migrations/NNN_*.sql newer than the database's user_version, once. */
    private static function migrate(PDO $pdo): void
    {
        $current = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

        foreach (glob(APP_ROOT . '/migrations/*.sql') ?: [] as $file) {
            $version = (int) basename($file);
            if ($version <= $current) {
                continue;
            }
            self::transaction(function (PDO $db) use ($file, $version): void {
                // Another request may have migrated while we waited for the lock.
                if ((int) $db->query('PRAGMA user_version')->fetchColumn() >= $version) {
                    return;
                }
                $db->exec((string) file_get_contents($file));
                $db->exec("PRAGMA user_version = $version");
            });
        }
    }

    /**
     * Runs $fn inside an IMMEDIATE transaction: the write lock is taken up front, so
     * "check free seats, then insert" can't interleave with another request.
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::get();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $fn($pdo);
            $pdo->exec('COMMIT');

            return $result;
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }
}
