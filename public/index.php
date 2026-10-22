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
    try {
        $message = match ($_POST['action'] ?? '') {
            'join' => bookings()->join($userId, $slot, $now),
            'leave' => bookings()->leave($userId, $slot, $now),
            default => throw new UserError('Azione non riconosciuta.'),
        };
        $ok = true;
    } catch (UserError $e) {
        $message = $e->getMessage();
        $ok = false;
    }

    if (is_fetch()) {
        json_response([
            'ok' => $ok,
            'message' => $message,
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
