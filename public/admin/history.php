<?php

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\UserError;

$user = Auth::requireAdmin();
$today = new DateTimeImmutable('today');

$date = (string) ($_REQUEST['date'] ?? $today->format('Y-m-d'));
$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$parsed || $parsed->format('Y-m-d') !== $date) {
    $parsed = $today;
}
$date = $parsed->format('Y-m-d');

if (is_post()) {
    Csrf::check();
    try {
        flash(bookings()->remove((int) ($_POST['booking'] ?? 0)));
    } catch (UserError $e) {
        flash($e->getMessage(), false);
    }
    redirect(url('/admin/history.php?date=' . $date));
}

// Today's fixed breaks appear here even if nobody has opened the "today" page yet.
if ($date === $today->format('Y-m-d')) {
    fixed_slots()->apply($date);
}

page('admin/history', 'Storico', [
    'user' => $user,
    'date' => $date,
    'label' => italian_date($parsed),
    'canEdit' => $parsed >= $today,
    'bySlot' => bookings()->peopleBySlot($date),
    'stats' => bookings()->stats($today->modify('-29 days')->format('Y-m-d'), $today->format('Y-m-d')),
]);
