<?php
/**
 * Profile photo picker. Without JavaScript it is a plain file input; photo-field.js adds a live
 * camera preview (phone camera or webcam) and turns any picked image into a square JPEG.
 *
 * @var string $label
 * @var ?string $current URL of the current photo, if any
 */
?>
<div class="photo-field" data-photo-field>
    <div class="photo-field__preview avatar avatar--xl" data-photo-preview>
        <?php if ($current ?? null): ?>
            <img src="<?= e($current) ?>" alt="">
        <?php else: ?>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
        <?php endif ?>
    </div>
    <div class="photo-field__body">
        <span class="field__label"><?= e($label) ?></span>
        <input class="photo-field__file" type="file" name="photo" accept="image/*" data-photo-file>
        <div class="photo-field__actions" data-photo-actions hidden>
            <button type="button" class="button button--move button--small" data-photo-camera>Scatta una foto</button>
            <button type="button" class="button button--leave button--small" data-photo-upload>Carica dal dispositivo</button>
            <button type="button" class="link-button" data-photo-clear hidden>Togli</button>
        </div>
        <input type="hidden" name="photo_data" data-photo-data>
        <p class="photo-field__status" data-photo-status role="status"></p>
    </div>

    <dialog class="camera" data-photo-dialog aria-label="Fotocamera">
        <div class="camera__frame">
            <video class="camera__video" playsinline muted data-photo-video></video>
        </div>
        <p class="camera__error" data-photo-error hidden></p>
        <div class="camera__actions">
            <button type="button" class="button button--join" data-photo-shoot>Scatta</button>
            <button type="button" class="button button--leave" data-photo-cancel>Annulla</button>
        </div>
    </dialog>
</div>
