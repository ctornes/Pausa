<?php

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\SlotService;
use App\UserError;

$user = Auth::requireAdmin();

if (is_post()) {
    Csrf::check();
    try {
        flash(match ($_POST['action'] ?? '') {
            'create' => fixed_slots()->create(
                (int) ($_POST['user_id'] ?? 0),
                (string) ($_POST['slot'] ?? ''),
                (array) ($_POST['weekdays'] ?? []),
                new DateTimeImmutable(),
            ),
            'delete' => fixed_slots()->delete((int) ($_POST['id'] ?? 0)),
            default => throw new UserError('Azione non riconosciuta.'),
        });
    } catch (UserError $e) {
        flash($e->getMessage(), false);
    }
    redirect(url('/admin/fixed.php'));
}

$slots = new SlotService(settings());

page('admin/fixed', 'Pause fisse', [
    'user' => $user,
    'fixed' => fixed_slots()->all(),
    'people' => users()->active(),
    'windows' => $slots->windows(),
    'slots' => $slots,
]);
