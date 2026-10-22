<?php use App\Csrf; ?>
<div class="panel--narrow">
    <h1 class="page-title">Il mio profilo</h1>

    <section class="panel stack">
        <h2 class="section-title">Foto</h2>
        <form class="stack" method="post" action="<?= url('/account.php') ?>" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="photo">
            <?= view('partials/photo-field', ['label' => $photoUrl ? 'Cambia la tua foto' : 'Aggiungi una foto', 'current' => $photoUrl]) ?>
            <?php if ($photoError): ?>
                <p class="form-error"><?= e($photoError) ?></p>
            <?php endif ?>
            <div><button class="button button--join" type="submit">Salva foto</button></div>
        </form>
        <?php if ($photoUrl): ?>
            <form method="post" action="<?= url('/account.php') ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="remove_photo">
                <button class="link-button" type="submit">Rimuovi la foto</button>
            </form>
        <?php endif ?>
    </section>

    <?php if ($push): ?>
        <section class="panel stack" data-push data-key="<?= e($push['key']) ?>"
                 data-endpoint="<?= url('/push.php') ?>" data-csrf="<?= Csrf::token() ?>">
            <h2 class="section-title">Promemoria</h2>
            <p class="field__hint">Una notifica sul telefono o sul computer poco prima di ogni tua pausa.</p>
            <p class="push__status" data-push-status role="status">Controllo questo dispositivo…</p>
            <div class="push__actions">
                <button type="button" class="button button--join button--small" data-push-on hidden>Attiva su questo dispositivo</button>
                <button type="button" class="button button--leave button--small" data-push-off hidden>Disattiva su questo dispositivo</button>
                <button type="button" class="link-button" data-push-test hidden>Invia una notifica di prova</button>
            </div>
            <form class="push__minutes" method="post" action="<?= url('/account.php') ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="reminders">
                <label class="field">
                    <span class="field__label">Avvisami</span>
                    <select name="reminder_minutes">
                        <?php foreach (App\PushService::REMINDER_MINUTES as $minutes): ?>
                            <option value="<?= $minutes ?>" <?= $minutes === $push['minutes'] ? 'selected' : '' ?>>
                                <?= $minutes ?> <?= $minutes === 1 ? 'minuto' : 'minuti' ?> prima
                            </option>
                        <?php endforeach ?>
                    </select>
                </label>
                <button class="button button--move button--small" type="submit">Salva</button>
            </form>
            <?php if ($push['devices'] > 0): ?>
                <p class="field__hint">Promemoria attivi su <?= $push['devices'] ?> <?= $push['devices'] === 1 ? 'dispositivo' : 'dispositivi' ?>.</p>
            <?php endif ?>
            <details class="push__help">
                <summary>Come si fa su iPhone</summary>
                <p>Apri Pausa in Safari, tocca <strong>Condividi</strong> → <strong>Aggiungi alla schermata Home</strong>.
                    Poi apri l'app dall'icona, vai in questo profilo e tocca <strong>Attiva su questo dispositivo</strong>.
                    Serve iOS 16.4 o successivo.</p>
            </details>
        </section>
    <?php endif ?>

    <section class="panel">
        <h2 class="section-title">Cambia il PIN</h2>
        <form class="stack" method="post" action="<?= url('/account.php') ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="pin">
            <label class="field">
                <span class="field__label">PIN attuale</span>
                <input class="pin-input" type="password" name="current" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="current-password">
            </label>
            <label class="field">
                <span class="field__label">Nuovo PIN</span>
                <input class="pin-input" type="password" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="new-password">
            </label>
            <label class="field">
                <span class="field__label">Ripeti il nuovo PIN</span>
                <input class="pin-input" type="password" name="pin_confirm" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="new-password">
            </label>
            <?php if ($error): ?>
                <p class="form-error"><?= e($error) ?></p>
            <?php endif ?>
            <div><button class="button button--join" type="submit">Cambia PIN</button></div>
        </form>
    </section>
</div>
