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

$selected = isset($_REQUEST['u']) ? users()->find((int) $_REQUEST['u']) : null;
if ($selected && !$selected['active']) {
    $selected = null;
}
$error = null;

if (is_post() && $selected) {
    Csrf::check();
    try {
        Auth::attempt((int) $selected['id'], (string) ($_POST['pin'] ?? ''));
        redirect(url('/'));
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

page('login', 'Accedi', [
    'people' => users()->active(),
    'selected' => $selected,
    'error' => $error,
    'bodyClass' => 'is-gate',
]);
