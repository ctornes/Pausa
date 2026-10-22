<?php

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\UserError;

// Only reachable until the first person exists.
if (users()->count() > 0) {
    redirect(url('/login.php'));
}

$error = null;
$name = '';

if (is_post()) {
    Csrf::check();
    $name = (string) ($_POST['name'] ?? '');
    $pin = (string) ($_POST['pin'] ?? '');
    try {
        if ($pin !== (string) ($_POST['pin_confirm'] ?? '')) {
            throw new UserError('I due PIN non coincidono.');
        }
        Auth::login(users()->create($name, $pin, true));
        flash('Fatto. Ora aggiungi i tuoi colleghi.');
        redirect(url('/admin/users.php'));
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

page('setup', 'Primo avvio', ['error' => $error, 'name' => $name, 'bodyClass' => 'is-gate']);
