// Progressive enhancement: every form also works without JavaScript.

const REFRESH_MS = 30_000;

const day = document.querySelector('[data-day]');
const toast = document.querySelector('[data-toast]');
let toastTimer;
let busy = false;

function showToast(message, ok = true) {
  if (!toast || !message) return;
  toast.textContent = message;
  toast.classList.toggle('toast--error', !ok);
  toast.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.hidden = true; }, 4500);
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

// Join / move / leave without a page reload.
document.addEventListener('submit', async (event) => {
  const form = event.target.closest('form[data-ajax]');
  if (!form || !day) return;
  event.preventDefault();

  const button = event.submitter ?? form.querySelector('button');
  if (button) button.disabled = true;
  busy = true;

  try {
    // Not form.action: the forms have a field named "action", which shadows that property.
    const res = await fetch(form.getAttribute('action') || location.href, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'fetch' },
    });
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
    showToast(data.message, data.ok);
    if (!data.ok && button) button.disabled = false;
  } catch {
    showToast('Connessione non riuscita: controlla la rete e riprova.', false);
    if (button) button.disabled = false;
  } finally {
    busy = false;
  }
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
