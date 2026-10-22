<?php

declare(strict_types=1);

namespace App;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use PDO;

/** Web Push: remembers which devices want reminders and sends them notifications. */
final class PushService
{
    public const REMINDER_MINUTES = [1, 2, 5];

    private ?WebPush $webPush = null;

    public function __construct(
        private readonly PDO $db,
        private readonly string $keyFile,
        private readonly string $subject,
    ) {
    }

    /** False when the libraries aren't installed on this server (composer install not run). */
    public static function available(): bool
    {
        return class_exists(WebPush::class);
    }

    /** The server's public key, which browsers need to subscribe. */
    public function publicKey(): string
    {
        return $this->keys()['publicKey'];
    }

    /** @throws UserError */
    public function subscribe(int $userId, string $endpoint, string $p256dh, string $auth): void
    {
        if (!preg_match('#^https://#', $endpoint) || $p256dh === '' || $auth === '') {
            throw new UserError('Il browser ha mandato un\'iscrizione non valida: riprova.');
        }
        // The same device may switch person (shared tablet): the latest login owns it.
        $this->db->prepare(
            'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)
             ON CONFLICT (endpoint) DO UPDATE SET user_id = excluded.user_id, p256dh = excluded.p256dh, auth = excluded.auth'
        )->execute([$userId, $endpoint, $p256dh, $auth]);
    }

    public function unsubscribe(int $userId, string $endpoint): void
    {
        $this->db->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?')->execute([$userId, $endpoint]);
    }

    public function deviceCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /** @throws UserError */
    public function setPreferences(int $userId, int $minutes, bool $endReminder): void
    {
        if (!in_array($minutes, self::REMINDER_MINUTES, true)) {
            throw new UserError('Scegli 1, 2 o 5 minuti.');
        }
        $this->db->prepare('UPDATE users SET reminder_minutes = ?, end_reminder = ? WHERE id = ?')
            ->execute([$minutes, (int) $endReminder, $userId]);
    }

    /**
     * Sends a notification to every device of the person. Devices the push service reports
     * as gone (uninstalled app, revoked permission) are forgotten.
     *
     * @param array{title: string, body: string, tag?: string, url?: string} $message
     * @return int devices reached
     */
    public function send(int $userId, array $message): int
    {
        $stmt = $this->db->prepare('SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?');
        $stmt->execute([$userId]);
        $devices = $stmt->fetchAll();
        if ($devices === []) {
            return 0;
        }

        $webPush = $this->webPush();
        foreach ($devices as $device) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $device['endpoint'],
                    'publicKey' => $device['p256dh'],
                    'authToken' => $device['auth'],
                    'contentEncoding' => 'aes128gcm',
                ]),
                json_encode($message, JSON_UNESCAPED_UNICODE),
                ['TTL' => 300, 'urgency' => 'high'], // a reminder is useless after a few minutes
            );
        }

        $reached = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $reached++;
            } elseif ($report->isSubscriptionExpired()) {
                $this->db->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')->execute([$report->getEndpoint()]);
            } else {
                error_log('[pausa] push failed: ' . $report->getReason());
            }
        }

        return $reached;
    }

    private function webPush(): WebPush
    {
        return $this->webPush ??= new WebPush(['VAPID' => ['subject' => $this->subject] + $this->keys()]);
    }

    /**
     * The VAPID key pair, created on first use. It lives in data/ (never in git): changing it
     * would silently cut off every subscribed device.
     *
     * @return array{publicKey: string, privateKey: string}
     */
    private function keys(): array
    {
        if (!is_file($this->keyFile)) {
            $keys = VAPID::createVapidKeys();
            $handle = fopen($this->keyFile, 'x'); // fails if another request just created it
            if ($handle) {
                fwrite($handle, json_encode($keys));
                fclose($handle);
                chmod($this->keyFile, 0600);
            }
        }

        return json_decode((string) file_get_contents($this->keyFile), true, flags: JSON_THROW_ON_ERROR);
    }
}
