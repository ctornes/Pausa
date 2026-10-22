<?php use App\Csrf; ?>
<div class="gate">
    <p class="gate__brand">
        <span class="brand__mark brand__mark--lg" aria-hidden="true"><i></i><i></i></span>
        <?= e(config('app_name')) ?>
    </p>
    <h1 class="gate__title">Crea il primo amministratore</h1>
    <p class="gate__intro">Sarà la persona che aggiunge i colleghi e decide gli orari delle pause.</p>
    <form class="gate__form" method="post" action="<?= url('/setup.php') ?>">
        <?= Csrf::field() ?>
        <label class="field">
            <span class="field__label">Nome e cognome</span>
            <input type="text" name="name" value="<?= e($name) ?>" maxlength="60" required autofocus autocomplete="name">
        </label>
        <label class="field">
            <span class="field__label">PIN di 4 cifre</span>
            <input class="pin-input" type="password" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="new-password">
        </label>
        <label class="field">
            <span class="field__label">Ripeti il PIN</span>
            <input class="pin-input" type="password" name="pin_confirm" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="new-password">
        </label>
        <?php if ($error): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endif ?>
        <button class="button button--join button--block" type="submit">Crea e continua</button>
    </form>
</div>
