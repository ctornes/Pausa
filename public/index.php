<?php

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\UserError;

$user = Auth::requireUser();
$now = new DateTimeImmutable();
$userId = (int) $user['id'];

if (is_post()) {
    Csrf::check();
    $slot = (string) ($_POST['slot'] ?? '');
    // What app.js sends if the person taps "Torna indietro" (not offered when this request is itself an undo).
    $undo = null;
    try {
        switch ($_POST['action'] ?? '') {
            case 'join':
                ['message' => $message, 'movedFrom' => $movedFrom] = bookings()->join($userId, $slot, $now);
                $undo = $movedFrom !== null
                    ? ['action' => 'join', 'slot' => $movedFrom]
                    : ['action' => 'leave', 'slot' => $slot];
                break;
            case 'leave':
                $message = bookings()->leave($userId, $slot, $now);
                $undo = ['action' => 'join', 'slot' => $slot];
                break;
            default:
                throw new UserError('Azione non riconosciuta.');
        }
        $ok = true;
    } catch (UserError $e) {
        $message = $e->getMessage();
        $ok = false;
    }

    if (is_fetch()) {
        json_response([
            'ok' => $ok,
            'message' => $message,
            'undo' => $ok && !isset($_POST['undo']) ? $undo : null,
            'html' => view('partials/day', ['day' => bookings()->board($userId, $now), 'user' => $user]),
        ]);
    }
    flash($message, $ok);
    redirect(url('/'));
}

$day = bookings()->board($userId, $now);

// Polled by app.js to keep the board live.
if (($_GET['partial'] ?? '') === 'day') {
    echo view('partials/day', ['day' => $day, 'user' => $user]);
    exit;
}

page('today', 'Oggi', ['user' => $user, 'day' => $day, 'now' => $now]);
