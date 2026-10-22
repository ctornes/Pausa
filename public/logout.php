<?php

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Csrf;

if (is_post()) {
    Csrf::check();
    Auth::logout();
}
redirect(url('/login.php'));
