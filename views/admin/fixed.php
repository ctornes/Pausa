<?php

use App\Csrf;
use App\FixedSlotService;

?>
<h1 class="page-title">Pause fisse</h1>

<section class="panel">
    <h2 class="section-title">Assegna una pausa fissa</h2>
    <p class="field__hint">Ogni giorno scelto la persona si ritrova prenotata in quello slot. Quel giorno può spostarla su un altro orario della stessa fascia (il giorno dopo torna all'orario fisso), ma non annullarla: per saltare un singolo giorno, toglila dallo Storico.</p>
    <form class="stack" method="post" action="<?= url('/admin/fixed.php') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">
        <div class="grid-2">
            <label class="field">
                <span class="field__label">Persona</span>
                <select name="user_id" required>
                    <option value="">Scegli…</option>
                    <?php foreach ($people as $person): ?>
                        <option value="<?= (int) $person['id'] ?>"><?= e($person['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <label class="field">
                <span class="field__label">Orario</span>
                <select name="slot" required>
                    <option value="">Scegli…</option>
                    <?php foreach ($windows as $window): ?>
                        <optgroup label="<?= e($window['start']) ?>–<?= e($window['end']) ?>">
                            <?php foreach ($window['slots'] as $slot): ?>
                                <option value="<?= e($slot['start']) ?>"><?= e($slot['start']) ?>–<?= e($slot['end']) ?></option>
                            <?php endforeach ?>
                        </optgroup>
                    <?php endforeach ?>
                </select>
            </label>
        </div>
        <fieldset class="weekdays">
            <legend class="field__label">Giorni</legend>
            <?php foreach (FixedSlotService::WEEKDAYS as $number => $label): ?>
                <label class="weekday">
                    <input type="checkbox" name="weekdays[]" value="<?= $number ?>" <?= $number <= 5 ? 'checked' : '' ?>>
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach ?>
        </fieldset>
        <div><button class="button button--join" type="submit">Assegna</button></div>
    </form>
</section>

<section class="panel">
    <h2 class="section-title">Pause fisse attive</h2>
    <?php if ($fixed === []): ?>
        <p class="empty">Nessuna pausa fissa per ora.</p>
    <?php else: ?>
        <table class="table table--stack">
            <thead><tr><th scope="col">Orario</th><th scope="col">Persona</th><th scope="col">Giorni</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($fixed as $row): ?>
                <tr>
                    <td class="num"><?= e($row['slot_start']) ?></td>
                    <td>
                        <span class="seat">
                            <?= avatar((int) $row['user_id'], $row['name'], $row['photo_version'], 'avatar--sm') ?>
                            <span class="seat__name"><?= e($row['name']) ?></span>
                        </span>
                        <?php if (!$row['active']): ?>
                            <div class="warning">Persona disattivata: la pausa non viene prenotata.</div>
                        <?php elseif (!$slots->exists($row['slot_start'])): ?>
                            <div class="warning">Questo orario non esiste più con gli orari attuali.</div>
                        <?php endif ?>
                    </td>
                    <td class="nowrap"><?= e(FixedSlotService::label($row['weekdays'])) ?></td>
                    <td>
                        <form method="post" action="<?= url('/admin/fixed.php') ?>">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button class="button button--danger button--small" type="submit"
                                    aria-label="Rimuovi la pausa fissa di <?= e($row['name']) ?> alle <?= e($row['slot_start']) ?>">Rimuovi</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>
