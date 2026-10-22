<div class="today">
    <p class="today__date"><?= e(ucfirst(italian_date($now))) ?></p>
    <div data-day data-refresh="<?= url('/?partial=day') ?>">
        <?= view('partials/day', ['day' => $day, 'user' => $user]) ?>
    </div>
</div>
