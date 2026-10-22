<?php

return [
    'app_name' => 'Pausa',
    'timezone' => 'Europe/Rome',
    // Prefix for links when the app is served from a sub-folder (e.g. '/pausa'). Empty at the web root.
    'base_url' => '',
    // PAUSA_DB (an absolute path) points tests at a throwaway database.
    'db_path' => getenv('PAUSA_DB') ?: __DIR__ . '/data/pausa.sqlite',
    'session_path' => __DIR__ . '/data/sessions',
    'session_days' => 30,
    // A 4-digit PIN is weak on its own: lock the account after a few wrong attempts.
    'pin_max_attempts' => 5,
    'pin_lock_minutes' => 5,
];
