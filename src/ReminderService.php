<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

/**
 * Break reminders, run every minute by a scheduled task: one before the break starts
 * (minutes chosen by each person) and one shortly before it ends.
 */
final class ReminderService
{
    public const END_REMINDER_MINUTES = 1;

    public function __construct(
        private readonly PDO $db,
        private readonly PushService $push,
        private readonly FixedSlotService $fixed,
        private readonly SlotService $slots,
    ) {
    }

    /** @return list<string> one line per reminder sent, for the task log */
    public function run(DateTimeImmutable $now): array
    {
        $date = $now->format('Y-m-d');
        // Fixed breaks become bookings on the first page load of the day; do it here too,
        // so they get a reminder even if nobody has opened the app yet.
        $this->fixed->apply($date);

        return [...$this->startReminders($date, $now), ...$this->endReminders($date, $now)];
    }

    /** @return list<string> */
    private function startReminders(string $date, DateTimeImmutable $now): array
    {
        $log = [];
        foreach ($this->pending($date, 'reminded_at') as $booking) {
            $start = new DateTimeImmutable("$date {$booking['slot_start']}");
            if (!$this->isDue($now, $start->modify("-{$booking['reminder_minutes']} minutes"), $start)) {
                continue;
            }
            $this->mark($booking['id'], 'reminded_at', $now);

            $minutesLeft = self::minutesUntil($now, $start);
            $log[] = 'start ' . $this->deliver($booking, [
                'title' => $minutesLeft === 1 ? 'Tra 1 minuto sei in pausa' : "Tra $minutesLeft minuti sei in pausa",
                'body' => $this->startBody($date, $booking['slot_start'], (int) $booking['user_id']),
                'tag' => 'reminder-' . $booking['id'],
            ]);
        }

        return $log;
    }

    /** @return list<string> */
    private function endReminders(string $date, DateTimeImmutable $now): array
    {
        $log = [];
        foreach ($this->pending($date, 'end_reminded_at', 'u.end_reminder = 1') as $booking) {
            $endTime = $this->slots->endOf($booking['slot_start']);
            $end = new DateTimeImmutable("$date $endTime");
            if (!$this->isDue($now, $end->modify('-' . self::END_REMINDER_MINUTES . ' minutes'), $end)) {
                continue;
            }
            $this->mark($booking['id'], 'end_reminded_at', $now);

            $minutesLeft = self::minutesUntil($now, $end);
            $log[] = 'end ' . $this->deliver($booking, [
                'title' => $minutesLeft === 1 ? 'Tra 1 minuto finisce la pausa' : "Tra $minutesLeft minuti finisce la pausa",
                'body' => "Alle $endTime si torna al lavoro.",
                'tag' => 'end-' . $booking['id'],
            ]);
        }

        return $log;
    }

    /** Sends one reminder; a failure is reported in the log line instead of stopping the other reminders. */
    private function deliver(array $booking, array $message): string
    {
        $what = "booking {$booking['id']} ({$booking['slot_start']}, user {$booking['user_id']})";
        try {
            $reached = $this->push->send((int) $booking['user_id'], $message);
        } catch (\Throwable $e) {
            return "$what: ERROR " . $e->getMessage();
        }

        return "$what: $reached device(s)" . ($reached === 0 ? ' — ' . $this->push->lastError() : '');
    }

    /** Today's bookings still waiting for the reminder tracked in $column, for people with a device. */
    private function pending(string $date, string $column, string $extraCondition = '1 = 1'): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.id, b.user_id, b.slot_start, u.reminder_minutes
             FROM bookings b JOIN users u ON u.id = b.user_id
             WHERE b.date = ? AND b.$column IS NULL AND u.active = 1 AND $extraCondition
               AND EXISTS (SELECT 1 FROM push_subscriptions s WHERE s.user_id = b.user_id)"
        );
        $stmt->execute([$date]);

        return $stmt->fetchAll();
    }

    /** Due from $from until $until (exclusive): a late run still sends, a missed moment doesn't. */
    private function isDue(DateTimeImmutable $now, DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        return $now >= $from && $now < $until;
    }

    /** Marked before sending: a failed delivery must not turn into a reminder every minute. */
    private function mark(int|string $bookingId, string $column, DateTimeImmutable $now): void
    {
        $this->db->prepare("UPDATE bookings SET $column = ? WHERE id = ?")
            ->execute([$now->format('Y-m-d H:i:s'), $bookingId]);
    }

    private static function minutesUntil(DateTimeImmutable $now, DateTimeImmutable $moment): int
    {
        return max(1, (int) ceil(($moment->getTimestamp() - $now->getTimestamp()) / 60));
    }

    private function startBody(string $date, string $slot, int $userId): string
    {
        $stmt = $this->db->prepare(
            'SELECT u.name FROM bookings b JOIN users u ON u.id = b.user_id
             WHERE b.date = ? AND b.slot_start = ? AND b.user_id != ? ORDER BY b.seat'
        );
        $stmt->execute([$date, $slot, $userId]);
        $others = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $others !== []
            ? "Alle $slot con " . join_names($others) . '.'
            : "Alle $slot. Per ora non c'è nessun altro.";
    }
}
