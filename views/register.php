<?php use App\Csrf; ?>
<div class="gate">
    <p class="gate__brand">
        <span class="brand__mark brand__mark--lg" aria-hidden="true"><i></i><i></i></span>
        <?= e(config('app_name')) ?>
    </p>

    <?php if ($sent): ?>
        <h1 class="gate__title">Richiesta inviata</h1>
        <p class="gate__intro">Ora un amministratore deve attivarti. Appena lo fa, trovi il tuo nome nella pagina di accesso ed entri con il tuo PIN.</p>
        <p class="gate__switch"><a href="<?= url('/login.php') ?>">Vai alla pagina di accesso</a></p>
    <?php elseif (!$open): ?>
        <h1 class="gate__title">Registrati</h1>
        <p class="gate__intro">La registrazione è chiusa: chiedi a un amministratore di aggiungerti.</p>
        <p class="gate__switch"><a href="<?= url('/login.php') ?>">Hai già un account? Accedi</a></p>
    <?php else: ?>
        <h1 class="gate__title">Registrati</h1>
        <p class="gate__intro">Un amministratore attiverà il tuo account; poi ti basterà scegliere il tuo nome e digitare il PIN.</p>
        <form class="gate__form" method="post" action="<?= url('/register.php') ?>" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <?= view('partials/photo-field', ['label' => 'Foto profilo (facoltativa)', 'current' => null]) ?>
            <label class="field">
                <span class="field__label">Nome</span>
                <input type="text" name="first_name" value="<?= e($first) ?>" maxlength="20" required autofocus autocomplete="given-name">
            </label>
            <label class="field">
                <span class="field__label">Cognome</span>
                <input type="text" name="last_name" value="<?= e($last) ?>" maxlength="20" required autocomplete="family-name">
            </label>
            <label class="field">
                <span class="field__label">Scegli un PIN di 4 cifre</span>
                <input class="pin-input" type="password" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="new-password">
            </label>
            <label class="field">
                <span class="field__label">Ripeti il PIN</span>
                <input class="pin-input" type="password" name="pin_confirm" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="new-password">
            </label>
            <?php if ($error): ?>
                <p class="form-error"><?= e($error) ?></p>
            <?php endif ?>
            <button class="button button--join button--block" type="submit">Invia la richiesta</button>
        </form>
        <p class="gate__switch"><a href="<?= url('/login.php') ?>">Hai già un account? Accedi</a></p>
    <?php endif ?>
</div>
