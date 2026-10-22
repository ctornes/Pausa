<?php

// Usage: php bin/create-user.php "Nome Cognome" [--admin]
// Reads the 4-digit PIN from standard input, so it never ends up in the shell history.

require dirname(__DIR__) . '/src/bootstrap.php';

use App\UserError;

$name = $argv[1] ?? '';
if ($name === '' || str_starts_with($name, '--')) {
    fwrite(STDERR, "Uso: php bin/create-user.php \"Nome Cognome\" [--admin]\n");
    exit(1);
}

if (posix_isatty(STDIN)) {
    fwrite(STDOUT, 'PIN di 4 cifre: ');
    shell_exec('stty -echo');
}
$pin = trim((string) fgets(STDIN));
if (posix_isatty(STDIN)) {
    shell_exec('stty echo');
    fwrite(STDOUT, "\n");
}

try {
    $id = users()->create($name, $pin, in_array('--admin', $argv, true));
    fwrite(STDOUT, "Creato: $name (id $id)\n");
} catch (UserError $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
