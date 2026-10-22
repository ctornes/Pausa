<?php use App\Csrf; ?>
<div class="gate">
    <p class="gate__brand">
        <span class="brand__mark brand__mark--lg" aria-hidden="true"><i></i><i></i></span>
        <?= e(config('app_name')) ?>
    </p>

    <?php if ($selected): ?>
        <h1 class="gate__title">Ciao, <?= e(explode(' ', $selected['name'])[0]) ?></h1>
        <form class="gate__form" method="post" action="<?= url('/login.php') ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="u" value="<?= (int) $selected['id'] ?>">
            <label class="field">
                <span class="field__label">Il tuo PIN</span>
                <input class="pin-input" type="password" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4"
                       autocomplete="current-password" required autofocus data-pin-autosubmit
                       <?= $error ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>>
            </label>
            <?php if ($error): ?>
                <p class="form-error" id="login-error"><?= e($error) ?></p>
            <?php endif ?>
            <button class="button button--join button--block" type="submit">Entra</button>
        </form>
        <p class="gate__switch"><a href="<?= url('/login.php') ?>">Non sei <?= e($selected['name']) ?>? Scegli un altro nome</a></p>
    <?php else: ?>
        <h1 class="gate__title">Chi sei?</h1>
        <ul class="people">
            <?php foreach ($people as $person): ?>
                <li>
                    <a class="person" href="<?= url('/login.php?u=' . (int) $person['id']) ?>">
                        <?= avatar((int) $person['id'], $person['name'], null) /* photos are visible only after login */ ?>
                        <span><?= e($person['name']) ?></span>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
        <?php if (settings()->selfSignup()): ?>
            <p class="gate__switch">Non trovi il tuo nome? <a href="<?= url('/register.php') ?>">Registrati</a>. Se hai già inviato la richiesta, aspetta che un amministratore ti attivi.</p>
        <?php endif ?>
    <?php endif ?>
</div>
