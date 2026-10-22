<?php

declare(strict_types=1);

namespace App;

use PDO;

/** Booking rules editable by admins, stored as key/value rows. */
final class Settings
{
    /** @var array<string, string> */
    private array $values;

    public function __construct(private readonly PDO $db)
    {
        $this->values = $db->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @return list<array{0: int, 1: int}> break windows as [start, end] in minutes since midnight */
    public function windows(): array
    {
        return self::parseWindows($this->windowsText());
    }

    public function windowsText(): string
    {
        return $this->values['windows'] ?? '';
    }

    public function slotMinutes(): int
    {
        return (int) ($this->values['slot_minutes'] ?? 10);
    }

    public function capacity(): int
    {
        return (int) ($this->values['capacity'] ?? 2);
    }

    /** Whether people can create their own (non-admin) account from the login page. */
    public function selfSignup(): bool
    {
        return ($this->values['self_signup'] ?? '1') === '1';
    }

    /** @throws UserError */
    public function save(array $input): void
    {
        $slotMinutes = self::intInRange($input, 'slot_minutes', 5, 60, 'La durata della pausa');
        $windows = self::parseWindows((string) ($input['windows'] ?? ''));

        if ($windows === []) {
            throw new UserError('Inserisci almeno una fascia oraria.');
        }
        foreach ($windows as [$start, $end]) {
            if ($end - $start < $slotMinutes) {
                throw new UserError(sprintf(
                    'La fascia %s-%s è più corta di una pausa da %d minuti.',
                    Time::format($start), Time::format($end), $slotMinutes,
                ));
            }
        }

        $values = [
            'windows' => implode("\n", array_map(
                fn (array $w) => Time::format($w[0]) . '-' . Time::format($w[1]),
                $windows,
            )),
            'slot_minutes' => (string) $slotMinutes,
            'capacity' => (string) self::intInRange($input, 'capacity', 1, 10, 'Il numero di posti'),
            'self_signup' => empty($input['self_signup']) ? '0' : '1',
        ];

        Db::transaction(function (PDO $db) use ($values): void {
            $stmt = $db->prepare(
                'INSERT INTO settings (key, value) VALUES (?, ?)
                 ON CONFLICT (key) DO UPDATE SET value = excluded.value'
            );
            foreach ($values as $key => $value) {
                $stmt->execute([$key, $value]);
            }
        });

        $this->values = $values;
    }

    /**
     * Parses one "HH:MM-HH:MM" window per line, sorted, rejecting overlaps.
     *
     * @return list<array{0: int, 1: int}>
     * @throws UserError
     */
    private static function parseWindows(string $text): array
    {
        $windows = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^(\d{1,2}[:.]\d{2})\s*-\s*(\d{1,2}[:.]\d{2})$/', $line, $m)) {
                throw new UserError("La fascia \"$line\" non è valida: scrivila come 10:00-12:00.");
            }
            [$start, $end] = array_map(
                fn (string $t) => str_pad(str_replace('.', ':', $t), 5, '0', STR_PAD_LEFT),
                [$m[1], $m[2]],
            );
            if (!Time::isValid($start) || !Time::isValid($end) || $start >= $end) {
                throw new UserError("La fascia \"$line\" non è valida: l'inizio deve venire prima della fine.");
            }
            $windows[] = [Time::toMinutes($start), Time::toMinutes($end)];
        }

        usort($windows, fn (array $a, array $b) => $a[0] <=> $b[0]);
        for ($i = 1; $i < count($windows); $i++) {
            if ($windows[$i][0] < $windows[$i - 1][1]) {
                throw new UserError('Due fasce orarie si sovrappongono.');
            }
        }

        return $windows;
    }

    /** @throws UserError */
    private static function intInRange(array $input, string $key, int $min, int $max, string $label): int
    {
        $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            throw new UserError("$label deve essere un numero tra $min e $max.");
        }

        return $value;
    }
}
