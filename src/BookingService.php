<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

/** Who sits in which slot: joining, leaving, and the day's board. */
final class BookingService
{
    public function __construct(
        private readonly PDO $db,
        private readonly Settings $settings,
        private readonly SlotService $slots,
        private readonly FixedSlotService $fixed,
    ) {
    }

    /**
     * Everything the "today" page needs, seen from $viewerId's side.
     *
     * @return array{
     *   windows: list<array{start: string, end: string, slots: list<array>}>,
     *   mine: list<array>,
     *   nextFree: ?string,
     *   waiting: list<array>,
     *   capacity: int,
     * }
     */
    public function board(int $viewerId, DateTimeImmutable $now): array
    {
        $date = $now->format('Y-m-d');
        $this->fixed->apply($date);
        $people = $this->peopleBySlot($date);
        $capacity = $this->settings->capacity();

        $mineFixed = []; // slot start => whether the viewer's booking there is a fixed one
        foreach ($people as $start => $list) {
            foreach ($list as $person) {
                if ($person['user_id'] === $viewerId) {
                    $mineFixed[$start] = $person['fixed'];
                }
            }
        }
        // One break per window: the viewer's booking in each window, if any.
        $mineByWindow = [];
        foreach ($mineFixed as $start => $fixed) {
            $window = $this->slots->windowOf($start);
            if ($window !== null) {
                $mineByWindow[$window] = ['start' => $start, 'fixed' => $fixed];
            }
        }

        $windows = [];
        $mine = [];
        $waiting = [];
        $nextFree = null;

        foreach ($this->slots->windows() as $windowIndex => $window) {
            $booked = $mineByWindow[$windowIndex] ?? null;
            // Picking another slot in the same window moves the booking until it starts (fixed ones too, for the day).
            $canMove = $booked !== null && $this->startsAt($date, $booked['start']) > $now;
            $slots = [];
            foreach ($window['slots'] as ['start' => $start, 'end' => $end]) {
                $list = $people[$start] ?? [];
                $starts = $this->startsAt($date, $start);
                $ends = $this->startsAt($date, $end);
                $state = match (true) {
                    $now >= $ends => 'past',
                    $now >= $starts => 'current',
                    default => 'upcoming',
                };
                $isMine = isset($mineFixed[$start]);
                $isFixed = $isMine && $mineFixed[$start];
                $free = max(0, $capacity - count($list));

                $action = null;
                if ($state === 'upcoming') {
                    $action = match (true) {
                        $isFixed => null,
                        $isMine => 'leave',
                        $free === 0 => null,
                        $booked === null => 'join',
                        $canMove => 'move',
                        default => null,
                    };
                }

                $slot = [
                    'start' => $start,
                    'end' => $end,
                    'state' => $state,
                    'progress' => $state === 'current'
                        ? ($now->getTimestamp() - $starts->getTimestamp()) / max(1, $ends->getTimestamp() - $starts->getTimestamp())
                        : 0,
                    'people' => $list,
                    'free' => $free,
                    'isMine' => $isMine,
                    'isFixed' => $isFixed,
                    'action' => $action,
                ];
                $slots[] = $slot;

                if ($isMine) {
                    $mine[] = $slot;
                }
                if ($action === 'join') {
                    $nextFree ??= $start;
                    if ($list !== []) {
                        $waiting[] = $slot;
                    }
                }
            }
            $windows[] = ['start' => $window['start'], 'end' => $window['end'], 'slots' => $slots];
        }

        return [
            'windows' => $windows,
            'mine' => $mine,
            'nextFree' => $nextFree,
            'waiting' => $waiting,
            'capacity' => $capacity,
        ];
    }

    /**
     * Takes the first free seat in $slot today. A person gets one break per window, so a booking
     * elsewhere in the same window (fixed ones included) is moved here.
     *
     * @throws UserError
     */
    public function join(int $userId, string $slot, DateTimeImmutable $now): string
    {
        $date = $now->format('Y-m-d');
        if (!$this->slots->exists($slot)) {
            throw new UserError('Questo orario non è uno slot di pausa.');
        }
        if ($this->startsAt($date, $slot) <= $now) {
            throw new UserError("Lo slot delle $slot è già iniziato.");
        }
        $this->fixed->apply($date);

        return Db::transaction(function (PDO $db) use ($userId, $slot, $date, $now): string {
            $stmt = $db->prepare('SELECT slot_start, fixed_id FROM bookings WHERE user_id = ? AND date = ? ORDER BY slot_start');
            $stmt->execute([$userId, $date]);
            $mineFixed = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $mine = array_keys($mineFixed);

            if (in_array($slot, $mine, true)) {
                throw new UserError("Sei già nello slot delle $slot.");
            }

            $window = $this->slots->windowOf($slot);
            $sameWindow = array_values(array_filter($mine, fn (string $s) => $this->slots->windowOf($s) === $window));
            $movingFrom = null;
            $fixedId = null;
            if ($sameWindow !== []) {
                $current = $sameWindow[0];
                if ($this->startsAt($date, $current) <= $now) {
                    throw new UserError('Hai già fatto la pausa della fascia ' . $this->slots->windowLabel($window) . '.');
                }
                $movingFrom = $current;
                // A moved fixed break stays fixed for the day (still not cancellable); tomorrow it's back at its usual time.
                $fixedId = $mineFixed[$current];
            }

            $stmt = $db->prepare('SELECT seat FROM bookings WHERE date = ? AND slot_start = ?');
            $stmt->execute([$date, $slot]);
            $taken = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $free = array_values(array_diff(range(1, $this->settings->capacity()), $taken));
            if ($free === []) {
                throw new UserError("Lo slot delle $slot è pieno: scegline un altro.");
            }

            if ($movingFrom !== null) {
                $db->prepare('DELETE FROM bookings WHERE user_id = ? AND date = ? AND slot_start = ?')
                    ->execute([$userId, $date, $movingFrom]);
            }
            $db->prepare('INSERT INTO bookings (user_id, date, slot_start, seat, fixed_id) VALUES (?, ?, ?, ?, ?)')
                ->execute([$userId, $date, $slot, $free[0], $fixedId]);

            $stmt = $db->prepare(
                'SELECT u.name FROM bookings b JOIN users u ON u.id = b.user_id
                 WHERE b.date = ? AND b.slot_start = ? AND b.user_id != ? ORDER BY b.seat'
            );
            $stmt->execute([$date, $slot, $userId]);
            $others = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $message = $movingFrom !== null ? "Pausa spostata alle $slot" : "Pausa prenotata alle $slot";

            return $message . ($others !== [] ? ' con ' . join_names($others) . '.' : '. Per ora non c\'è nessun altro.');
        });
    }

    /** @throws UserError */
    public function leave(int $userId, string $slot, DateTimeImmutable $now): string
    {
        $date = $now->format('Y-m-d');
        if (Time::isValid($slot) && $this->startsAt($date, $slot) <= $now) {
            throw new UserError("Lo slot delle $slot è già iniziato: non puoi più annullare la pausa.");
        }

        $stmt = $this->db->prepare('SELECT id, fixed_id FROM bookings WHERE user_id = ? AND date = ? AND slot_start = ?');
        $stmt->execute([$userId, $date, $slot]);
        $booking = $stmt->fetch();
        if (!$booking) {
            throw new UserError('Non eri in questo slot.');
        }
        if ($booking['fixed_id'] !== null) {
            throw new UserError("La tua pausa delle $slot è assegnata dall'amministrazione: puoi spostarla su un altro orario, ma per annullarla chiedi a un amministratore.");
        }
        $this->db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$booking['id']]);

        return "Pausa delle $slot annullata.";
    }

    /** Admin removal of anyone's booking. @throws UserError */
    public function remove(int $bookingId): string
    {
        $stmt = $this->db->prepare(
            'SELECT b.slot_start, b.date, b.fixed_id, u.name FROM bookings b JOIN users u ON u.id = b.user_id WHERE b.id = ?'
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            throw new UserError('Questa prenotazione non esiste più.');
        }

        $this->db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$bookingId]);

        $message = "Hai tolto {$booking['name']} dallo slot delle {$booking['slot_start']}.";

        // The fixed_applied marker keeps the fixed break from being recreated for this date.
        return $booking['fixed_id'] !== null ? $message . ' La pausa fissa resta per gli altri giorni.' : $message;
    }

    /**
     * All bookings of a day, grouped by slot (including slots no longer in the current settings).
     *
     * @return array<string, list<array{id: int, user_id: int, name: string, seat: int, fixed: bool, photo: ?int}>>
     */
    public function peopleBySlot(string $date): array
    {
        $stmt = $this->db->prepare(
            'SELECT b.id, b.slot_start, b.seat, b.fixed_id, u.id AS user_id, u.name, u.photo_version
             FROM bookings b JOIN users u ON u.id = b.user_id
             WHERE b.date = ? ORDER BY b.slot_start, b.seat'
        );
        $stmt->execute([$date]);

        $bySlot = [];
        foreach ($stmt as $row) {
            $bySlot[$row['slot_start']][] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'name' => $row['name'],
                'seat' => (int) $row['seat'],
                'fixed' => $row['fixed_id'] !== null,
                'photo' => $row['photo_version'] !== null ? (int) $row['photo_version'] : null,
            ];
        }

        return $bySlot;
    }

    /** @return list<array{name: string, breaks: int}> breaks per person between two dates (inclusive) */
    public function stats(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.name, COUNT(b.id) AS breaks
             FROM users u LEFT JOIN bookings b ON b.user_id = u.id AND b.date BETWEEN ? AND ?
             WHERE u.active = 1
             GROUP BY u.id ORDER BY breaks DESC, u.name'
        );
        $stmt->execute([$from, $to]);

        return $stmt->fetchAll();
    }

    private function startsAt(string $date, string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("$date $time");
    }
}
