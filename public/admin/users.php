<?php

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\UserError;

$user = Auth::requireAdmin();

if (is_post()) {
    Csrf::check();
    try {
        $name = (string) ($_POST['name'] ?? '');
        $pin = trim((string) ($_POST['pin'] ?? ''));
        $isAdmin = isset($_POST['is_admin']);

        if (($_POST['action'] ?? '') === 'approve') {
            flash('Account di ' . users()->approve((int) $_POST['id']) . ' attivato: ora può accedere e prenotare.');
        } elseif (($_POST['action'] ?? '') === 'reject') {
            $id = (int) $_POST['id'];
            $rejected = users()->reject($id);
            photos()->delete($id);
            flash("Richiesta di $rejected rifiutata.");
        } elseif (isset($_POST['remove_photo'])) {
            photos()->delete((int) ($_POST['id'] ?? 0));
            flash('Foto di ' . trim($name) . ' rimossa.');
        } elseif (($_POST['action'] ?? '') === 'create') {
            users()->create($name, $pin, $isAdmin);
            flash("Aggiunta: " . trim($name) . ". Ricordati di comunicare il PIN.");
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            users()->update($id, $name, $isAdmin, isset($_POST['active']));
            if ($pin !== '') {
                users()->setPin($id, $pin);
            }
            flash('Modifiche salvate per ' . trim($name) . '.');
        }
    } catch (UserError $e) {
        flash($e->getMessage(), false);
    }
    redirect(url('/admin/users.php'));
}

page('admin/users', 'Persone', [
    'user' => $user,
    'people' => users()->all(),
    'pending' => users()->pending(),
]);
