<?php

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\UserError;

if (users()->count() === 0) {
    redirect(url('/setup.php'));
}
if (Auth::user()) {
    redirect(url('/'));
}

$error = null;
$first = (string) ($_POST['first_name'] ?? '');
$last = (string) ($_POST['last_name'] ?? '');

if (is_post() && settings()->selfSignup()) {
    Csrf::check();
    try {
        if (trim($first) === '' || trim($last) === '') {
            throw new UserError('Inserisci sia il nome sia il cognome.');
        }
        $pin = (string) ($_POST['pin'] ?? '');
        if ($pin !== (string) ($_POST['pin_confirm'] ?? '')) {
            throw new UserError('I due PIN non coincidono.');
        }
        $name = trim($first) . ' ' . trim($last);
        if (users()->isPendingName($name)) {
            throw new UserError('Hai già inviato la richiesta: aspetta che un amministratore ti attivi.');
        }
        $photo = photos()->fromRequest($_POST, $_FILES);
        // Self-registered people are never admins and stay inactive until an admin approves them.
        $id = users()->create($name, $pin, false, pending: true);
        if ($photo !== null) {
            photos()->save($id, $photo);
        }
        redirect(url('/register.php?sent=1'));
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

page('register', 'Registrati', [
    'open' => settings()->selfSignup(),
    'sent' => isset($_GET['sent']),
    'error' => $error,
    'first' => $first,
    'last' => $last,
    'bodyClass' => 'is-gate',
    'scripts' => ['photo-field.js'],
]);
