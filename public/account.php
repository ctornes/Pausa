<?php

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\PushService;
use App\UserError;

$user = Auth::requireUser();
$userId = (int) $user['id'];
$error = null;
$photoError = null;

if (is_post()) {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'photo') {
            $photo = photos()->fromRequest($_POST, $_FILES)
                ?? throw new UserError('Scatta o scegli una foto prima di salvare.');
            photos()->save($userId, $photo);
            flash('Foto aggiornata.');
        } elseif ($action === 'reminders') {
            push()->setReminderMinutes($userId, (int) ($_POST['reminder_minutes'] ?? 0));
            flash('Preferenze dei promemoria salvate.');
        } elseif ($action === 'remove_photo') {
            photos()->delete($userId);
            flash('Foto rimossa.');
        } else {
            if (!password_verify((string) ($_POST['current'] ?? ''), $user['pin_hash'])) {
                throw new UserError('Il PIN attuale non è corretto.');
            }
            if (($_POST['pin'] ?? '') !== ($_POST['pin_confirm'] ?? '')) {
                throw new UserError('I due nuovi PIN non coincidono.');
            }
            users()->setPin($userId, (string) $_POST['pin']);
            flash('PIN cambiato.');
        }
        redirect(url('/account.php'));
    } catch (UserError $e) {
        if (in_array($action, ['photo', 'remove_photo'], true)) {
            $photoError = $e->getMessage();
        } else {
            $error = $e->getMessage();
        }
    }
}

page('account', 'Il mio profilo', [
    'user' => $user,
    'error' => $error,
    'photoError' => $photoError,
    'photoUrl' => $user['photo_version'] !== null ? url("/photo.php?u=$userId&v={$user['photo_version']}") : null,
    'push' => PushService::available() ? [
        'key' => push()->publicKey(),
        'devices' => push()->deviceCount($userId),
        'minutes' => (int) $user['reminder_minutes'],
    ] : null,
    'scripts' => ['photo-field.js', 'push.js'],
]);
