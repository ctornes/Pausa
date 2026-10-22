<?php use App\Csrf; ?>
<h1 class="page-title">Impostazioni</h1>

<form method="post" action="<?= url('/admin/settings.php') ?>">
    <?= Csrf::field() ?>

    <section class="panel stack">
        <h2 class="section-title">Orari delle pause</h2>
        <label class="field">
            <span class="field__label">Fasce orarie</span>
            <textarea name="windows" rows="3" required><?= e((string) $values['windows']) ?></textarea>
            <span class="field__hint">Una per riga, nel formato 10:00-12:00. Ognuno può prenotare una sola pausa per fascia.</span>
        </label>
        <div class="grid-2">
            <label class="field">
                <span class="field__label">Durata di una pausa (minuti)</span>
                <input type="number" name="slot_minutes" min="5" max="60" value="<?= e((string) $values['slot_minutes']) ?>" required>
            </label>
            <label class="field">
                <span class="field__label">Persone per slot</span>
                <input type="number" name="capacity" min="1" max="10" value="<?= e((string) $values['capacity']) ?>" required>
            </label>
        </div>
        <p class="summary">
            Oggi ci sono <strong><?= $slotCount ?> slot</strong>, cioè fino a <strong><?= $slotCount * $capacity ?> pause</strong>.
            Se cambi durata o fasce durante la giornata, le prenotazioni già fatte su orari che spariscono non si vedono più:
            meglio farlo a fine giornata.
        </p>
    </section>

    <section class="panel stack">
        <h2 class="section-title">Registrazione</h2>
        <label class="check">
            <input type="checkbox" name="self_signup" value="1" <?= !empty($values['self_signup']) ? 'checked' : '' ?>>
            Le persone possono registrarsi da sole
        </label>
        <p class="field__hint">Chi si registra inserisce nome, cognome e PIN e resta in attesa finché un amministratore non lo attiva da Persone; non diventa mai amministratore. Chiudi la registrazione quando ci sono tutti; puoi sempre disattivare qualcuno da Persone.</p>
    </section>

    <div class="form-actions">
        <?php if ($error): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endif ?>
        <button class="button button--join" type="submit">Salva impostazioni</button>
    </div>
</form>
