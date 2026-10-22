<?php

use App\Csrf;

$user ??= null;
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$nav = $user ? array_filter([
    ['/', 'Oggi', true],
    ['/admin/users.php', 'Persone', $user['is_admin']],
    ['/admin/fixed.php', 'Pause fisse', $user['is_admin']],
    ['/admin/settings.php', 'Impostazioni', $user['is_admin']],
    ['/admin/history.php', 'Storico', $user['is_admin']],
], fn (array $item) => $item[2]) : [];
$isCurrent = fn (string $path) => $script === url($path === '/' ? '/index.php' : $path);
$badges = $user && $user['is_admin'] ? ['/admin/users.php' => users()->pendingCount()] : [];
$flash = flash();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title) ?> · <?= e(config('app_name')) ?></title>
    <link rel="icon" href="<?= asset('icon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
    <script src="<?= asset('app.js') ?>" defer></script>
    <?php foreach ($scripts ?? [] as $src): ?>
        <script src="<?= asset($src) ?>" defer></script>
    <?php endforeach ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if ($user): ?>
    <header class="topbar">
        <a class="brand" href="<?= url('/') ?>" aria-label="<?= e(config('app_name')) ?>: oggi">
            <span class="brand__mark" aria-hidden="true"><i></i><i></i></span>
            <span class="brand__name"><?= e(config('app_name')) ?></span>
        </a>
        <nav class="nav" aria-label="Sezioni">
            <?php foreach ($nav as [$path, $label]): ?>
                <a href="<?= url($path) ?>" <?= $isCurrent($path) ? 'aria-current="page"' : '' ?>><?= e($label) ?><?php if ($badges[$path] ?? 0): ?> <span class="badge" title="Persone da attivare"><?= $badges[$path] ?></span><?php endif ?></a>
            <?php endforeach ?>
        </nav>
        <div class="who">
            <a class="who__name" href="<?= url('/account.php') ?>" title="Il mio profilo: foto e PIN">
                <?= avatar((int) $user['id'], $user['name'], $user['photo_version'], 'avatar--sm') ?>
                <span><?= e($user['name']) ?></span>
            </a>
            <form method="post" action="<?= url('/logout.php') ?>">
                <?= Csrf::field() ?>
                <button class="link-button" type="submit">Esci</button>
            </form>
        </div>
    </header>
<?php endif ?>
<main class="main">
    <?php if ($flash): ?>
        <p class="notice <?= $flash['ok'] ? 'notice--ok' : 'notice--error' ?>" role="status"><?= e($flash['message']) ?></p>
    <?php endif ?>
    <?= $content ?>
</main>
<div class="toast" role="status" aria-live="polite" data-toast hidden></div>
</body>
</html>
