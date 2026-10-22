<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;
use PDOException;

/** Breaks an admin pins to the same person and slot on chosen weekdays, booked automatically each day. */
final class FixedSlotService
{
    public const WEEKDAYS = [1 => 'lun', 2 => 'mar', 3 => 'mer', 4 => 'gio', 5 => 'ven', 6 => 'sab', 7 => 'dom'];
    private const WEEKDAY_NAMES = [1 => 'lunedì', 2 => 'martedì', 3 => 'mercoledì', 4 => 'giovedì',
        5 => 'venerdì', 6 => 'sabato', 7 => 'domenica'];

    public function __construct(
        private readonly PDO $db,
        private readonly Settings $settings,
        private readonly SlotService $slots,
    ) {
    }

    /** @return list<array> every fixed slot with the person's name, by time */
    public function all(): array
    {
        return $this->db->query(
            'SELECT f.*, u.name, u.active, u.photo_version FROM fixed_slots f JOIN users u ON u.id = f.user_id
             ORDER BY f.slot_start, u.name COLLATE NOCASE'
        )->fetchAll();
    }

    /**
     * @param list<int|string> $weekdays ISO weekday numbers (1 = Monday)
     * @throws UserError
     */
    public function create(int $userId, string $slot, array $weekdays, DateTimeImmutable $now): string
    {
        $user = users()->find($userId);
        if (!$user || !$user['active']) {
            throw new UserError('Scegli una persona dall\'elenco.');
        }
        if (!$this->slots->exists($slot)) {
            throw new UserError('Scegli un orario dall\'elenco.');
        }
        $days = array_values(array_intersect(array_keys(self::WEEKDAYS), array_map('intval', $weekdays)));
        if ($days === []) {
            throw new UserError('Scegli almeno un giorno della settimana.');
        }

        $today = $now->format('Y-m-d');
        // Today counts only if the slot hasn't started yet.
        $startsOn = new DateTimeImmutable("$today $slot") > $now ? $today : $now->modify('+1 day')->format('Y-m-d');

        return Db::transaction(function (PDO $db) use ($user, $slot, $days, $startsOn, $today): string {
            $sameSlot = $db->prepare('SELECT weekdays FROM fixed_slots WHERE slot_start = ?');
            $sameSlot->execute([$slot]);
            $sameSlot = $sameSlot->fetchAll(PDO::FETCH_COLUMN);

            $samePerson = $db->prepare('SELECT weekdays FROM fixed_slots WHERE user_id = ?');
            $samePerson->execute([$user['id']]);
            $samePerson = $samePerson->fetchAll(PDO::FETCH_COLUMN);

            foreach ($days as $day) {
                $name = self::WEEKDAY_NAMES[$day];
                if (self::countOn($sameSlot, $day) >= $this->settings->capacity()) {
                    throw new UserError("Il $name alle $slot i posti fissi sono già tutti occupati.");
                }
                if (self::countOn($samePerson, $day) >= $this->settings->maxPerDay()) {
                    throw new UserError("{$user['name']} ha già una pausa fissa il $name.");
                }
            }

            try {
                $db->prepare('INSERT INTO fixed_slots (user_id, slot_start, weekdays, starts_on) VALUES (?, ?, ?, ?)')
                    ->execute([$user['id'], $slot, implode('', $days), $startsOn]);
            } catch (PDOException $e) {
                throw str_contains($e->getMessage(), 'UNIQUE')
                    ? new UserError("{$user['name']} ha già una pausa fissa alle $slot.")
                    : $e;
            }

            $message = sprintf('Pausa fissa creata: %s alle %s, %s.', $user['name'], $slot, self::label(implode('', $days)));

            return $startsOn === $today ? $message : $message . ' Vale da domani, perché lo slot di oggi è già iniziato.';
        });
    }

    public function delete(int $id): string
    {
        // Today's booking, if any, stays but turns into an ordinary one (fixed_id → NULL).
        $this->db->prepare('DELETE FROM fixed_slots WHERE id = ?')->execute([$id]);

        return 'Pausa fissa rimossa. Se oggi era già prenotata, resta come pausa normale.';
    }

    /**
     * Turns the day's fixed slots into bookings. Runs before anyone can book on that day,
     * so a fixed seat is always free; each fixed slot is applied at most once per date.
     */
    public function apply(string $date): void
    {
        $weekday = (new DateTimeImmutable($date))->format('N');
        $pending = $this->db->prepare(
            'SELECT f.id FROM fixed_slots f JOIN users u ON u.id = f.user_id
             WHERE u.active = 1 AND f.starts_on <= ? AND instr(f.weekdays, ?) > 0
               AND NOT EXISTS (SELECT 1 FROM fixed_applied a WHERE a.fixed_id = f.id AND a.date = ?)'
        );
        $pending->execute([$date, $weekday, $date]);
        $ids = $pending->fetchAll(PDO::FETCH_COLUMN);
        if ($ids === []) {
            return;
        }

        Db::transaction(function (PDO $db) use ($ids, $date): void {
            foreach ($ids as $id) {
                $mark = $db->prepare('INSERT OR IGNORE INTO fixed_applied (fixed_id, date) VALUES (?, ?)');
                $mark->execute([$id, $date]);
                if ($mark->rowCount() === 0) {
                    continue; // applied by a concurrent request
                }

                $fixed = $db->prepare('SELECT user_id, slot_start FROM fixed_slots WHERE id = ?');
                $fixed->execute([$id]);
                ['user_id' => $userId, 'slot_start' => $slot] = $fixed->fetch();
                if (!$this->slots->exists($slot)) {
                    continue; // the break times changed since it was created
                }

                $mine = $db->prepare('SELECT slot_start FROM bookings WHERE user_id = ? AND date = ?');
                $mine->execute([$userId, $date]);
                $mine = $mine->fetchAll(PDO::FETCH_COLUMN);
                if (in_array($slot, $mine, true) || count($mine) >= $this->settings->maxPerDay()) {
                    continue;
                }

                $seats = $db->prepare('SELECT seat FROM bookings WHERE date = ? AND slot_start = ?');
                $seats->execute([$date, $slot]);
                $free = array_diff(range(1, $this->settings->capacity()), array_map('intval', $seats->fetchAll(PDO::FETCH_COLUMN)));
                if ($free === []) {
                    continue;
                }

                $db->prepare('INSERT INTO bookings (user_id, date, slot_start, seat, fixed_id) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$userId, $date, $slot, min($free), $id]);
            }
        });
    }

    /** "12345" → "lun–ven", "1234567" → "tutti i giorni", "135" → "lun, mer, ven". */
    public static function label(string $weekdays): string
    {
        $days = array_map('intval', str_split($weekdays));
        sort($days);
        if (count($days) === 7) {
            return 'tutti i giorni';
        }
        $isRun = count($days) >= 3 && $days === range($days[0], end($days));

        return $isRun
            ? self::WEEKDAYS[$days[0]] . '–' . self::WEEKDAYS[end($days)]
            : implode(', ', array_map(fn (int $d) => self::WEEKDAYS[$d], $days));
    }

    /** @param list<string> $weekdaySets */
    private static function countOn(array $weekdaySets, int $day): int
    {
        return count(array_filter($weekdaySets, fn (string $set) => str_contains($set, (string) $day)));
    }
}
