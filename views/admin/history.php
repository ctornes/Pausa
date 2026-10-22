<?php use App\Csrf; ?>
<h1 class="page-title">Storico</h1>

<section class="panel">
    <form class="history-head" method="get" action="<?= url('/admin/history.php') ?>">
        <label class="field">
            <span class="field__label">Giorno</span>
            <input type="date" name="date" value="<?= e($date) ?>">
        </label>
        <button class="button button--move" type="submit">Mostra</button>
    </form>

    <h2 class="section-title">Pause di <?= e($label) ?></h2>
    <?php if ($bySlot === []): ?>
        <p class="empty">Nessuna pausa prenotata in questo giorno.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th scope="col">Orario</th><th scope="col">Persone</th></tr></thead>
            <tbody>
            <?php foreach ($bySlot as $start => $people): ?>
                <tr>
                    <td class="num"><?= e($start) ?></td>
                    <td>
                        <?php foreach ($people as $p): ?>
                            <span class="seat">
                                <?= avatar($p['user_id'], $p['name'], $p['photo'], 'avatar--sm') ?>
                                <span class="seat__name"><?= e($p['name']) ?></span>
                                <?php if ($p['fixed']): ?><span class="seat__fixed">fissa</span><?php endif ?>
                                <?php if ($canEdit): ?>
                                    <form method="post" action="<?= url('/admin/history.php') ?>">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="date" value="<?= e($date) ?>">
                                        <input type="hidden" name="booking" value="<?= $p['id'] ?>">
                                        <button class="button button--danger button--small" type="submit"
                                                aria-label="Togli <?= e($p['name']) ?> dallo slot delle <?= e($start) ?>">Togli</button>
                                    </form>
                                <?php endif ?>
                            </span>
                        <?php endforeach ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>

<section class="panel">
    <h2 class="section-title">Ultimi 30 giorni</h2>
    <table class="table">
        <thead><tr><th scope="col">Persona</th><th scope="col">Pause</th></tr></thead>
        <tbody>
        <?php foreach ($stats as $row): ?>
            <tr><td><?= e($row['name']) ?></td><td class="num"><?= (int) $row['breaks'] ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
</section>
