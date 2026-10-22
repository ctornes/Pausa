// Progressive enhancement: every form also works without JavaScript.

const REFRESH_MS = 30_000;
const UNDO_MS = 6_000;

const day = document.querySelector('[data-day]');
const toast = document.querySelector('[data-toast]');
let toastTimer;
let busy = false;

function showToast(message, ok = true, onUndo = null) {
  if (!toast || !message) return;
  toast.replaceChildren(document.createTextNode(message));
  if (onUndo) {
    const undo = document.createElement('button');
    undo.type = 'button';
    undo.className = 'toast__undo';
    undo.textContent = 'Torna indietro';
    undo.addEventListener('click', () => {
      toast.hidden = true;
      onUndo();
    });
    toast.append(undo);
  }
  toast.classList.toggle('toast--error', !ok);
  toast.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.hidden = true; }, onUndo ? UNDO_MS : 4500);
}

async function refreshDay() {
  if (!day || busy || document.hidden) return;
  try {
    const res = await fetch(day.dataset.refresh, { headers: { 'X-Requested-With': 'fetch' } });
    if (res.status === 401) return location.reload();
    if (res.ok && !busy) day.innerHTML = await res.text();
  } catch {
    // Offline for a moment: the next tick retries.
  }
}

/** The request that reverses the last action, as described by the server's `undo`. */
function undoBody(original, undo) {
  const body = new FormData();
  body.append('_csrf', original.get('_csrf'));
  body.append('action', undo.action);
  body.append('slot', undo.slot);
  body.append('undo', '1');
  return body;
}

async function send(url, body, button = null) {
  if (button) button.disabled = true;
  busy = true;

  try {
    const res = await fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } });
    if (res.status === 401 || res.status === 419) {
      const data = await res.json().catch(() => ({}));
      showToast(data.message, false);
      return setTimeout(() => location.reload(), 1200);
    }
    const data = await res.json().catch(() => null);
    if (!data) {
      // The server answered, but not with our JSON: a crash, or a proxy/firewall page.
      showToast(`Il server ha risposto con un errore (${res.status}). Riprova; se continua, avvisa un amministratore.`, false);
      if (button) button.disabled = false;
      return;
    }
    if (data.html) day.innerHTML = data.html;
    showToast(data.message, data.ok, data.undo ? () => send(url, undoBody(body, data.undo)) : null);
    if (!data.ok && button) button.disabled = false;
  } catch {
    showToast('Connessione non riuscita: controlla la rete e riprova.', false);
    if (button) button.disabled = false;
  } finally {
    busy = false;
  }
}

// Join / move / leave without a page reload.
document.addEventListener('submit', (event) => {
  const form = event.target.closest('form[data-ajax]');
  if (!form || !day) return;
  event.preventDefault();
  // Not form.action: the forms have a field named "action", which shadows that property.
  send(form.getAttribute('action') || location.href, new FormData(form), event.submitter ?? form.querySelector('button'));
});

if (day) {
  setInterval(refreshDay, REFRESH_MS);
  document.addEventListener('visibilitychange', refreshDay);
}

// The PIN field logs in as soon as the 4th digit is typed.
const pin = document.querySelector('[data-pin-autosubmit]');
pin?.addEventListener('input', () => {
  pin.value = pin.value.replace(/\D/g, '').slice(0, 4);
  if (pin.value.length === 4) pin.form.requestSubmit();
});

// Installable app + reminders: the service worker lives at the app root (see sw.js).
if ('serviceWorker' in navigator && document.documentElement.dataset.sw) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register(document.documentElement.dataset.sw).catch(() => {});
  });
}
