<?php

use App\Csrf;

$userId = (int) $user['id'];
$active = null;
foreach ($day['mine'] as $slot) {
    if ($slot['state'] !== 'past') {
        $active = $slot;
        break;
    }
}
$done = $active === null && $day['mine'] !== [];
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
        <p class="eyebrow"><?= $active['state'] === 'current' ? 'Sei in pausa adesso' : ($active['isFixed'] ? 'La tua pausa fissa' : 'La tua pausa') ?></p>
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
            <p class="hero__note">È una pausa fissa: per cambiarla o annullarla chiedi a un amministratore.</p>
        <?php endif ?>
    <?php elseif ($done): $last = end($day['mine']); ?>
        <p class="eyebrow">Pausa fatta</p>
        <p class="hero__time"><?= e($last['start']) ?><span class="hero__end">–<?= e($last['end']) ?></span></p>
        <p class="hero__lead">Per oggi sei a posto. Ci vediamo domani.</p>
    <?php else: ?>
        <p class="eyebrow">Non hai ancora una pausa</p>
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

<?php foreach ($day['windows'] as $i => $window): ?>
    <?php
    // Past slots nobody used are hidden; a window with nothing left to show is hidden too.
    $visible = array_filter($window['slots'], fn (array $s) => $s['state'] !== 'past' || $s['people'] !== []);
    if ($visible === []) {
        continue;
    }
    ?>
    <section class="window" aria-labelledby="window-<?= $i ?>">
        <h2 class="window__title" id="window-<?= $i ?>">
            <?= $window['start'] < '13:00' ? 'Mattina' : 'Pomeriggio' ?>
            <span class="window__range"><?= e($window['start']) ?>–<?= e($window['end']) ?></span>
        </h2>
        <ol class="slots">
            <?php foreach ($visible as $slot): ?>
                <li class="slot slot--<?= $slot['state'] ?><?= $slot['isMine'] ? ' slot--mine' : '' ?><?= $slot['free'] === 0 ? ' slot--full' : '' ?>"
                    <?= $slot['state'] === 'current' ? 'style="--progress: ' . round($slot['progress'], 3) . '"' : '' ?>>
                    <p class="slot__time">
                        <time><?= e($slot['start']) ?></time>
                        <span class="slot__end">–<?= e($slot['end']) ?></span>
                    </p>
                    <div class="slot__seats">
                        <?php foreach ($slot['people'] as $p): $me = $p['user_id'] === $userId; ?>
                            <span class="seat<?= $me ? ' seat--me' : '' ?>">
                                <?= avatar($p['user_id'], $p['name'], $p['photo']) ?>
                                <span class="seat__name"><?= $me ? 'Tu' : e($p['name']) ?></span>
                                <?php if ($p['fixed'] && !$me): ?><span class="seat__fixed" title="Pausa fissa">fissa</span><?php endif ?>
                            </span>
                        <?php endforeach ?>
                        <?php for ($s = 0; $s < $slot['free']; $s++): ?>
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
                        <?php elseif ($slot['isFixed']): ?>
                            <span class="tag tag--fixed">fissa</span>
                        <?php elseif ($slot['state'] === 'upcoming' && $slot['free'] === 0 && !$slot['isMine']): ?>
                            <span class="tag">pieno</span>
                        <?php endif ?>
                    </div>
                </li>
            <?php endforeach ?>
        </ol>
    </section>
<?php endforeach ?>
