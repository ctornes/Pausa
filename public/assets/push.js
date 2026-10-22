// Reminders on this device: asks permission, subscribes to Web Push and tells the server.

const root = document.querySelector('[data-push]');

function keyToBytes(base64url) {
  const base64 = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
  return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
}

async function init() {
  const $ = (name) => root.querySelector(`[data-push-${name}]`);
  const status = $('status');
  const on = $('on');
  const off = $('off');
  const test = $('test');

  const isIos = /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const installed = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

  if (!('serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window)) {
    status.textContent = isIos && !installed
      ? 'Su iPhone i promemoria funzionano solo dall\'app installata: segui le istruzioni qui sotto.'
      : 'Questo browser non supporta i promemoria: prova con Chrome, Edge, Firefox o Safari aggiornati.';
    return;
  }

  const registration = await navigator.serviceWorker.ready;

  async function post(action, subscription) {
    const body = new FormData();
    body.append('_csrf', root.dataset.csrf);
    body.append('action', action);
    if (subscription) {
      const json = subscription.toJSON();
      body.append('endpoint', json.endpoint);
      body.append('p256dh', json.keys?.p256dh ?? '');
      body.append('auth', json.keys?.auth ?? '');
    }
    const res = await fetch(root.dataset.endpoint, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } });
    return res.json().catch(() => ({ ok: false, message: `Il server ha risposto con un errore (${res.status}).` }));
  }

  async function render(message) {
    const subscription = await registration.pushManager.getSubscription();
    on.hidden = Boolean(subscription);
    off.hidden = !subscription;
    test.hidden = !subscription;
    if (message) {
      status.textContent = message;
    } else if (Notification.permission === 'denied') {
      status.textContent = 'Hai bloccato le notifiche per questo sito: riattivale dalle impostazioni del browser, poi ricarica la pagina.';
      on.hidden = true;
    } else {
      status.textContent = subscription ? 'Attivi su questo dispositivo.' : 'Non attivi su questo dispositivo.';
    }
    return subscription;
  }

  on.addEventListener('click', async () => {
    on.disabled = true;
    try {
      if (await Notification.requestPermission() !== 'granted') {
        await render();
        return;
      }
      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: keyToBytes(root.dataset.key),
      });
      const result = await post('subscribe', subscription);
      await render(result.message);
    } catch {
      await render('Non sono riuscito ad attivare i promemoria: riprova.');
    } finally {
      on.disabled = false;
    }
  });

  off.addEventListener('click', async () => {
    const subscription = await registration.pushManager.getSubscription();
    if (subscription) {
      await post('unsubscribe', subscription);
      await subscription.unsubscribe();
    }
    await render('Promemoria disattivati su questo dispositivo.');
  });

  test.addEventListener('click', async () => {
    test.disabled = true;
    const result = await post('test');
    status.textContent = result.message;
    test.disabled = false;
  });

  // Keep the server in sync if it forgot this device (e.g. after a database restore).
  const existing = await render();
  if (existing) {
    post('subscribe', existing);
  }
}

if (root) {
  init();
}
