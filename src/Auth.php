<?php

declare(strict_types=1);

namespace App;

/** Name + PIN login backed by the PHP session. */
final class Auth
{
    private static ?array $user = null;

    public static function user(): ?array
    {
        if (self::$user === null && isset($_SESSION['user_id'])) {
            $user = users()->find((int) $_SESSION['user_id']);
            // Re-read on every request so a deactivated person is logged out immediately.
            self::$user = $user && $user['active'] ? $user : null;
        }

        return self::$user;
    }

    /** @throws UserError */
    public static function attempt(int $userId, string $pin): void
    {
        $user = users()->find($userId);
        if (!$user || !$user['active']) {
            throw new UserError('Scegli il tuo nome dall\'elenco.');
        }

        $lockedFor = (int) $user['locked_until'] - time();
        if ($lockedFor > 0) {
            throw new UserError(sprintf('Troppi PIN sbagliati. Riprova tra %d minuti.', (int) ceil($lockedFor / 60)));
        }

        if (!password_verify($pin, $user['pin_hash'])) {
            $left = users()->recordFailure($userId, config('pin_max_attempts'), config('pin_lock_minutes') * 60);
            throw new UserError(match (true) {
                $left > 1 => "PIN sbagliato. Ti restano $left tentativi.",
                $left === 1 => 'PIN sbagliato. Ti resta un ultimo tentativo.',
                default => sprintf('PIN sbagliato troppe volte. Riprova tra %d minuti.', config('pin_lock_minutes')),
            });
        }

        users()->clearFailures($userId);
        if (password_needs_rehash($user['pin_hash'], PASSWORD_DEFAULT)) {
            users()->setPin($userId, $pin);
        }
        self::login($userId);
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        self::$user = null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = null;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if ($user !== null) {
            return $user;
        }
        if (is_fetch()) {
            json_response(['ok' => false, 'message' => 'Sessione scaduta: accedi di nuovo.'], 401);
        }
        redirect(url('/login.php'));
    }

    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if (!$user['is_admin']) {
            http_response_code(403);
            page('error', 'Accesso negato', [
                'user' => $user,
                'message' => 'Questa pagina è riservata agli amministratori.',
            ]);
        }

        return $user;
    }
}
