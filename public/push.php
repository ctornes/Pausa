<?php

// JSON endpoint used by push.js to turn reminders on/off for this device and to send a test.

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\PushService;
use App\UserError;

$user = Auth::requireUser();
$userId = (int) $user['id'];

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Usa il pulsante nella pagina del profilo.'], 405);
}
Csrf::check();
if (!PushService::available()) {
    json_response(['ok' => false, 'message' => 'I promemoria non sono ancora attivi su questo server.'], 503);
}

$endpoint = (string) ($_POST['endpoint'] ?? '');

try {
    switch ($_POST['action'] ?? '') {
        case 'subscribe':
            push()->subscribe($userId, $endpoint, (string) ($_POST['p256dh'] ?? ''), (string) ($_POST['auth'] ?? ''));
            json_response(['ok' => true, 'message' => 'Promemoria attivati su questo dispositivo.']);
        case 'unsubscribe':
            push()->unsubscribe($userId, $endpoint);
            json_response(['ok' => true, 'message' => 'Promemoria disattivati su questo dispositivo.']);
        case 'test':
            // Report the real cause: this button is how people (and we) find out why reminders don't arrive.
            try {
                $reached = push()->send($userId, [
                    'title' => 'Promemoria attivi',
                    'body' => 'Riceverai un avviso ' . $user['reminder_minutes'] . ' min prima di ogni pausa.',
                    'tag' => 'test',
                ]);
            } catch (Throwable $e) {
                reminder_log()->error("user $userId: test notification failed: " . $e->getMessage());
                json_response(['ok' => false, 'message' => 'Invio non riuscito: ' . $e->getMessage()], 500);
            }
            json_response($reached > 0
                ? ['ok' => true, 'message' => "Notifica di prova inviata a $reached " . ($reached === 1 ? 'dispositivo' : 'dispositivi') . ': dovrebbe arrivare tra pochi secondi.']
                : ['ok' => false, 'message' => 'Nessun dispositivo raggiunto (' . push()->lastError() . '). Tocca "Disattiva", poi "Attiva su questo dispositivo" e riprova.']);
        default:
            throw new UserError('Azione non riconosciuta.');
    }
} catch (UserError $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
