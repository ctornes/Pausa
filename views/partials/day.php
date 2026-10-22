<?php

use App\Csrf;

$userId = (int) $user['id'];
// The hero shows the viewer's next break; with one per window there can be another one later.
$upcomingMine = array_values(array_filter($day['mine'], fn (array $s) => $s['state'] !== 'past'));
$active = $upcomingMine[0] ?? null;
$later = $upcomingMine[1] ?? null;
$canStillBook = $day['waiting'] !== [] || $day['nextFree'] !== null;
$done = $active === null && $day['mine'] !== [] && !$canStillBook;
$others = fn (array $slot) => array_values(array_filter($slot['people'], fn (array $p) => $p['user_id'] !== $userId));
$labels = ['join' => 'Unisciti', 'move' => 'Sposta qui', 'leave' => 'Annulla'];

$actionForm = function (array $slot, string $class = '') use ($labels): string {
    $label = $slot['action'] === 'join' && $slot['people'] === [] ? 'Prenota' : $labels[$slot['action']];
    $verb = $slot['action'] === 'leave' ? 'leave' : 'join';

    return sprintf(
        '<form method="post" action="%s" data-ajax>%s<input type="hidden" name="action" value="%s">'
        . '<input type="hidden" name="slot" value="%s"><button class="button button--%s %s" type="submit" aria-label="%s">%s</button></form>',
        url('/'), Csrf::field(), $verb, e($slot['start']), $slot['action'], $class,
        e("$label: slot delle {$slot['start']}"), e($label),
    );
};
?>
<section class="hero<?= $active ? ' hero--booked' : '' ?><?= $active && $active['state'] === 'current' ? ' hero--now' : '' ?>"
         <?= $active && $active['state'] === 'current' ? 'style="--progress: ' . round($active['progress'], 3) . '"' : '' ?>>
    <?php if ($active): $with = $others($active); ?>
        <p class="eyebrow"><?= $active['state'] === 'current' ? 'Sei in pausa adesso' : 'La tua pausa' ?></p>
        <p class="hero__time"><?= e($active['start']) ?><span class="hero__end">–<?= e($active['end']) ?></span></p>
        <div class="hero__pair" aria-hidden="true">
            <?= avatar($userId, $user['name'], $user['photo_version'], 'avatar--lg') ?>
            <?php foreach ($with as $p): ?>
                <?= avatar($p['user_id'], $p['name'], $p['photo'], 'avatar--lg') ?>
            <?php endforeach ?>
            <?php for ($i = count($with) + 1; $i < $day['capacity']; $i++): ?>
                <span class="avatar avatar--lg avatar--empty"></span>
            <?php endfor ?>
        </div>
        <p class="hero__lead">
            <?php if ($with): ?>
                Con <strong><?= e(join_names(array_column($with, 'name'))) ?></strong>.
            <?php else: ?>
                Per ora non c'è nessun altro: chi vuole può unirsi a te.
            <?php endif ?>
        </p>
        <?php if ($active['action'] === 'leave'): ?>
            <div class="hero__actions"><?= $actionForm($active, 'button--quiet') ?></div>
        <?php elseif ($active['isFixed'] && $active['state'] === 'upcoming'): ?>
            <p class="hero__note">Pausa assegnata dall'amministrazione: oggi puoi spostarla con "Sposta qui" su un altro orario della stessa fascia; per annullarla chiedi a un amministratore.</p>
        <?php endif ?>
        <?php if ($later): ?>
            <p class="hero__note">Hai un'altra pausa alle <strong><?= e($later['start']) ?></strong>.</p>
        <?php endif ?>
    <?php elseif ($done): $last = end($day['mine']); ?>
        <p class="eyebrow">Pausa fatta</p>
        <p class="hero__time"><?= e($last['start']) ?><span class="hero__end">–<?= e($last['end']) ?></span></p>
        <p class="hero__lead">Per oggi sei a posto. Ci vediamo domani.</p>
    <?php else: ?>
        <p class="eyebrow"><?= $day['mine'] === [] ? 'Non hai ancora una pausa' : 'Puoi prenotare un\'altra pausa' ?></p>
        <?php if ($day['waiting']): $first = $day['waiting'][0]; ?>
            <p class="hero__lead hero__lead--big">
                <strong><?= e(join_names(array_column($first['people'], 'name'))) ?></strong>
                aspetta qualcuno alle <strong class="nowrap"><?= e($first['start']) ?></strong>.
            </p>
            <?php if ($first['action']): ?>
                <div class="hero__actions"><?= $actionForm($first) ?></div>
            <?php endif ?>
        <?php elseif ($day['nextFree']): ?>
            <p class="hero__lead hero__lead--big">Il primo slot libero è alle <strong class="nowrap"><?= e($day['nextFree']) ?></strong>.</p>
        <?php else: ?>
            <p class="hero__lead hero__lead--big">Per oggi non ci sono più posti liberi.</p>
        <?php endif ?>
    <?php endif ?>
</section>

<?php
// Past slots nobody used are hidden; a window with nothing left to show is hidden too.
$shown = [];
foreach ($day['windows'] as $i => $window) {
    $visible = array_filter($window['slots'], fn (array $s) => $s['state'] !== 'past' || $s['people'] !== []);
    if ($visible !== []) {
        $shown[$i] = ['window' => $window, 'slots' => $visible];
    }
}
$windowName = fn (array $window) => $window['start'] < '13:00' ? 'Mattina' : 'Pomeriggio';
// The switcher highlights the window you're most likely after: the first one not over yet.
$nowWindow = null;
foreach ($shown as $i => $entry) {
    foreach ($entry['slots'] as $s) {
        if ($s['state'] !== 'past') {
            $nowWindow ??= $i;
        }
    }
}
?>
<?php if (count($shown) > 1): ?>
    <nav class="jump" aria-label="Vai alla fascia">
        <?php foreach ($shown as $i => ['window' => $window]): ?>
            <a href="#window-<?= $i ?>" <?= $i === $nowWindow ? 'aria-current="true"' : '' ?>>
                <?= $windowName($window) ?> <span><?= e($window['start']) ?>–<?= e($window['end']) ?></span>
            </a>
        <?php endforeach ?>
    </nav>
<?php endif ?>

<?php foreach ($shown as $i => ['window' => $window, 'slots' => $visible]): ?>
    <section class="window" id="window-<?= $i ?>" aria-labelledby="window-title-<?= $i ?>">
        <h2 class="window__title" id="window-title-<?= $i ?>">
            <?= $windowName($window) ?>
            <span class="window__range"><?= e($window['start']) ?>–<?= e($window['end']) ?></span>
        </h2>
        <ol class="slots">
            <?php foreach ($visible as $slot): $empty = $slot['people'] === []; ?>
                <li class="slot slot--<?= $slot['state'] ?><?= $slot['isMine'] ? ' slot--mine' : '' ?><?= $slot['free'] === 0 ? ' slot--full' : '' ?><?= $empty ? ' slot--empty' : '' ?>"
                    <?= $slot['state'] === 'current' ? 'style="--progress: ' . round($slot['progress'], 3) . '"' : '' ?>>
                    <p class="slot__time">
                        <time><?= e($slot['start']) ?></time>
                        <span class="slot__end">–<?= e($slot['end']) ?></span>
                    </p>
                    <div class="slot__seats">
                        <?php if ($empty): ?>
                            <span class="slot__vacancy">
                                <span class="slot__dots" aria-hidden="true"><?= str_repeat('<i></i>', min($slot['free'], 4)) ?></span>
                                <?= $slot['free'] === 1 ? '1 posto libero' : $slot['free'] . ' posti liberi' ?>
                            </span>
                        <?php endif ?>
                        <?php foreach ($slot['people'] as $p): $me = $p['user_id'] === $userId; ?>
                            <span class="seat<?= $me ? ' seat--me' : '' ?>">
                                <?= avatar($p['user_id'], $p['name'], $p['photo']) ?>
                                <span class="seat__name" title="<?= e($p['name']) ?>"><?= $me ? 'Tu' : e($p['name']) ?></span>
                            </span>
                        <?php endforeach ?>
                        <?php for ($s = 0; !$empty && $s < $slot['free']; $s++): ?>
                            <span class="seat seat--free">
                                <span class="avatar avatar--empty" aria-hidden="true"></span>
                                <span class="seat__name">libero</span>
                            </span>
                        <?php endfor ?>
                    </div>
                    <div class="slot__action">
                        <?php if ($slot['action']): ?>
                            <?= $actionForm($slot) ?>
                        <?php elseif ($slot['state'] === 'current'): ?>
                            <span class="tag tag--now">adesso</span>
                        <?php elseif ($slot['state'] === 'upcoming' && $slot['free'] === 0 && !$slot['isMine']): ?>
                            <span class="tag">pieno</span>
                        <?php endif ?>
                    </div>
                </li>
            <?php endforeach ?>
        </ol>
    </section>
<?php endforeach ?>
