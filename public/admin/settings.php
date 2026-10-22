<?php

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\SlotService;
use App\UserError;

$user = Auth::requireAdmin();
$error = null;

if (is_post()) {
    Csrf::check();
    try {
        settings()->save($_POST);
        flash('Impostazioni salvate.');
        redirect(url('/admin/settings.php'));
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

$settings = settings();

page('admin/settings', 'Impostazioni', [
    'user' => $user,
    'error' => $error,
    // After a failed save, show what the admin typed rather than the stored values.
    'values' => $error ? $_POST : [
        'windows' => $settings->windowsText(),
        'slot_minutes' => $settings->slotMinutes(),
        'capacity' => $settings->capacity(),
        'max_per_day' => $settings->maxPerDay(),
        'self_signup' => $settings->selfSignup(),
    ],
    'slotCount' => (new SlotService($settings))->count(),
    'capacity' => $settings->capacity(),
]);
