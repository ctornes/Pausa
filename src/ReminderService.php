<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

/** Sends "your break starts in N minutes" reminders; run every minute by a scheduled task. */
final class ReminderService
{
    public function __construct(
        private readonly PDO $db,
        private readonly PushService $push,
        private readonly FixedSlotService $fixed,
    ) {
    }

    /** @return list<string> one line per reminder sent, for the task log */
    public function run(DateTimeImmutable $now): array
    {
        $date = $now->format('Y-m-d');
        // Fixed breaks become bookings on the first page load of the day; do it here too,
        // so they get a reminder even if nobody has opened the app yet.
        $this->fixed->apply($date);

        $stmt = $this->db->prepare(
            'SELECT b.id, b.user_id, b.slot_start, u.reminder_minutes
             FROM bookings b JOIN users u ON u.id = b.user_id
             WHERE b.date = ? AND b.reminded_at IS NULL AND u.active = 1
               AND EXISTS (SELECT 1 FROM push_subscriptions s WHERE s.user_id = b.user_id)'
        );
        $stmt->execute([$date]);

        $log = [];
        foreach ($stmt->fetchAll() as $booking) {
            $start = new DateTimeImmutable("$date {$booking['slot_start']}");
            $due = $start->modify("-{$booking['reminder_minutes']} minutes");
            if ($now < $due || $now >= $start) {
                continue;
            }

            // Mark first: a failed delivery must not turn into a reminder every minute.
            $this->db->prepare('UPDATE bookings SET reminded_at = ? WHERE id = ?')
                ->execute([$now->format('Y-m-d H:i:s'), $booking['id']]);

            $minutesLeft = max(1, (int) ceil(($start->getTimestamp() - $now->getTimestamp()) / 60));
            $reached = $this->push->send((int) $booking['user_id'], [
                'title' => $minutesLeft === 1 ? 'Tra 1 minuto sei in pausa' : "Tra $minutesLeft minuti sei in pausa",
                'body' => $this->body($date, $booking['slot_start'], (int) $booking['user_id']),
                'tag' => 'reminder-' . $booking['id'],
            ]);
            $log[] = "booking {$booking['id']} ({$booking['slot_start']}): $reached device(s)";
        }

        return $log;
    }

    private function body(string $date, string $slot, int $userId): string
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
