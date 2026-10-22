<?php

declare(strict_types=1);

namespace App;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    /** Stops the request when the form didn't come from one of our pages. */
    public static function check(): void
    {
        $sent = (string) ($_POST['_csrf'] ?? '');
        if (hash_equals(self::token(), $sent)) {
            return;
        }
        if (is_fetch()) {
            json_response(['ok' => false, 'message' => 'La pagina era scaduta: ricaricala e riprova.'], 419);
        }
        flash('La pagina era scaduta: riprova.', false);
        redirect($_SERVER['REQUEST_URI'] ?? url('/'));
    }
}
