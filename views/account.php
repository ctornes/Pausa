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
