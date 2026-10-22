<?php use App\Csrf; ?>
<h1 class="page-title">Persone</h1>

<?php if ($pending): ?>
    <section class="panel requests">
        <h2 class="section-title">Da attivare <span class="badge"><?= count($pending) ?></span></h2>
        <p class="field__hint">Si sono registrati da soli. Attivali se li conosci: solo allora potranno accedere.</p>
        <ul class="admin-list">
            <?php foreach ($pending as $request): ?>
                <li class="request">
                    <?= avatar((int) $request['id'], $request['name'], $request['photo_version'], 'avatar--lg') ?>
                    <div class="request__who">
                        <strong><?= e($request['name']) ?></strong>
                        <span class="field__hint">Richiesta del <?= e(date('d/m/Y \a\l\l\e H:i', strtotime($request['created_at'] . ' UTC'))) ?></span>
                    </div>
                    <div class="request__actions">
                        <form method="post" action="<?= url('/admin/users.php') ?>">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
                            <button class="button button--join button--small" type="submit">Attiva</button>
                        </form>
                        <form method="post" action="<?= url('/admin/users.php') ?>">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
                            <button class="button button--danger button--small" type="submit"
                                    aria-label="Rifiuta la richiesta di <?= e($request['name']) ?>">Rifiuta</button>
                        </form>
                    </div>
                </li>
            <?php endforeach ?>
        </ul>
    </section>
<?php endif ?>

<section class="panel">
    <h2 class="section-title">Aggiungi una persona</h2>
    <form class="stack" method="post" action="<?= url('/admin/users.php') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">
        <div class="grid-2">
            <label class="field">
                <span class="field__label">Nome e cognome</span>
                <input type="text" name="name" maxlength="40" required autocomplete="off">
            </label>
            <label class="field">
                <span class="field__label">PIN di 4 cifre</span>
                <input class="pin-input" type="text" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="off">
            </label>
        </div>
        <label class="check"><input type="checkbox" name="is_admin"> Amministratore (gestisce persone e orari)</label>
        <div><button class="button button--join" type="submit">Aggiungi</button></div>
    </form>
</section>

<h2 class="section-title">Tutte le persone</h2>
<p class="field__hint">Lascia vuoto il PIN per non cambiarlo. Chi viene disattivato non può più entrare, ma il suo storico resta.</p>
<ul class="admin-list">
    <?php foreach ($people as $person): $self = (int) $person['id'] === (int) $user['id']; ?>
        <li>
            <form class="user-row<?= $person['active'] ? '' : ' user-row--inactive' ?>" method="post" action="<?= url('/admin/users.php') ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
                <?= avatar((int) $person['id'], $person['name'], $person['photo_version']) ?>
                <div class="user-row__fields">
                    <input type="text" name="name" value="<?= e($person['name']) ?>" maxlength="40" required aria-label="Nome">
                    <input class="pin-input" type="text" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4"
                           placeholder="PIN" autocomplete="off" aria-label="Nuovo PIN per <?= e($person['name']) ?>">
                    <label class="check"><input type="checkbox" name="is_admin" <?= $person['is_admin'] ? 'checked' : '' ?>> Admin</label>
                    <?php if ($self): ?>
                        <input type="hidden" name="active" value="1">
                    <?php else: ?>
                        <label class="check"><input type="checkbox" name="active" <?= $person['active'] ? 'checked' : '' ?>> Attivo</label>
                    <?php endif ?>
                    <button class="button button--move button--small" type="submit">Salva</button>
                    <?php if ($person['photo_version'] !== null): ?>
                        <button class="link-button" type="submit" name="remove_photo" value="1">Rimuovi foto</button>
                    <?php endif ?>
                </div>
            </form>
        </li>
    <?php endforeach ?>
</ul>
