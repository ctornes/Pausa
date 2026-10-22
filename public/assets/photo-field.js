// Profile photo picker: live camera (phone or webcam) or a file from the device.
// Every photo leaves the browser as a 400×400 JPEG in the hidden `photo_data` field.

const SIZE = 400;

const MESSAGES = {
  NotAllowedError: 'Accesso alla fotocamera negato: abilitalo nelle impostazioni del browser, oppure carica una foto.',
  NotFoundError: 'Nessuna fotocamera trovata: carica una foto dal dispositivo.',
  NotReadableError: 'La fotocamera è usata da un\'altra app: chiudila e riprova.',
};

/** Draws the centred square of `source` into a JPEG data URL, mirrored for selfies. */
function toSquareJpeg(source, width, height, mirror = false) {
  const canvas = document.createElement('canvas');
  canvas.width = SIZE;
  canvas.height = SIZE;
  const ctx = canvas.getContext('2d');
  const side = Math.min(width, height);
  if (mirror) {
    ctx.translate(SIZE, 0);
    ctx.scale(-1, 1);
  }
  ctx.drawImage(source, (width - side) / 2, (height - side) / 2, side, side, 0, 0, SIZE, SIZE);
  return canvas.toDataURL('image/jpeg', 0.85);
}

async function fileToJpeg(file) {
  if ('createImageBitmap' in window) {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const url = toSquareJpeg(bitmap, bitmap.width, bitmap.height);
    bitmap.close();
    return url;
  }
  const src = URL.createObjectURL(file);
  try {
    const img = new Image();
    img.src = src;
    await img.decode();
    return toSquareJpeg(img, img.naturalWidth, img.naturalHeight);
  } finally {
    URL.revokeObjectURL(src);
  }
}

function photoField(root) {
  const $ = (name) => root.querySelector(`[data-photo-${name}]`);
  const file = $('file');
  const data = $('data');
  const preview = $('preview');
  const status = $('status');
  const clear = $('clear');
  const dialog = $('dialog');
  const video = $('video');
  const error = $('error');
  const shoot = $('shoot');
  const initialPreview = preview.innerHTML;
  // getUserMedia needs a secure page (https or localhost); otherwise the phone's camera app takes over.
  const canStream = Boolean(navigator.mediaDevices?.getUserMedia) && window.isSecureContext;
  let stream = null;

  file.hidden = true;
  $('actions').hidden = false;

  function use(dataUrl, message) {
    data.value = dataUrl;
    file.value = '';
    preview.innerHTML = '';
    const img = new Image();
    img.alt = '';
    img.src = dataUrl;
    preview.append(img);
    clear.hidden = false;
    status.textContent = message;
  }

  function stopCamera() {
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
    video.srcObject = null;
  }

  async function openCamera() {
    if (!canStream) {
      // Opens the front camera directly on phones; desktops fall back to the file picker.
      file.setAttribute('capture', 'user');
      file.click();
      file.removeAttribute('capture');
      return;
    }
    error.hidden = true;
    shoot.disabled = true;
    dialog.showModal();
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
      video.srcObject = stream;
      await video.play();
      shoot.disabled = false;
    } catch (e) {
      error.textContent = MESSAGES[e.name] ?? 'Non riesco ad aprire la fotocamera: carica una foto dal dispositivo.';
      error.hidden = false;
    }
  }

  $('camera').addEventListener('click', openCamera);
  $('upload').addEventListener('click', () => file.click());
  $('cancel').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', stopCamera);

  shoot.addEventListener('click', () => {
    use(toSquareJpeg(video, video.videoWidth, video.videoHeight, true), 'Foto scattata.');
    dialog.close();
  });

  file.addEventListener('change', async () => {
    const picked = file.files?.[0];
    if (!picked) return;
    status.textContent = 'Preparo la foto…';
    try {
      use(await fileToJpeg(picked), 'Foto pronta.');
    } catch {
      file.value = '';
      status.textContent = 'Non riesco a leggere questa immagine: prova con una foto JPEG o PNG.';
    }
  });

  clear.addEventListener('click', () => {
    data.value = '';
    file.value = '';
    preview.innerHTML = initialPreview;
    clear.hidden = true;
    status.textContent = '';
  });
}

document.querySelectorAll('[data-photo-field]').forEach(photoField);
