/* ==========================================================================
   zugang.js — Das E-Mail-Feld bleibt auf der Seite (24.09.2026, E1).

   Ohne JavaScript schickt das Formular ganz normal an zugang.php und landet
   auf einer ruhigen Seite mit derselben Rueckmeldung. Mit JavaScript bleibt
   der Besucher, wo er ist: Das Feld wird still, darunter steht der Satz.
   Der Satz kommt vom Server und ist fuer jede Adresse derselbe (E2) -- hier
   wird nichts daraus geschlossen, ob eine Adresse schon bekannt ist.
   ========================================================================== */
const L = (document.documentElement.lang || 'it').slice(0, 2);
const TEXT = {
  it: { fehler: 'Qualcosa non ha funzionato. Riprovi tra poco.' },
  de: { fehler: 'Etwas hat nicht geklappt. Versuchen Sie es gleich noch einmal.' },
  en: { fehler: 'Something went wrong. Please try again shortly.' },
}[['it', 'de', 'en'].includes(L) ? L : 'it'];

for (const form of document.querySelectorAll('form[data-zugang]')) {
  const ok = form.querySelector('.zugangsfeld__ok');
  const knopf = form.querySelector('button[type="submit"]');
  form.addEventListener('submit', async (ev) => {
    if (!window.fetch || !ok) return;             // dann eben klassisch
    ev.preventDefault();
    if (form.classList.contains('ist-sendet')) return;
    form.classList.add('ist-sendet'); knopf.disabled = true;
    try {
      const r = await fetch(form.action, { method: 'POST', body: new FormData(form),
        headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const d = await r.json();
      ok.textContent = d.meldung || TEXT.fehler;
      ok.hidden = false;
      form.classList.toggle('ist-fehler', !d.ok);
      form.classList.toggle('ist-gesendet', !!d.ok);
      if (d.ok) zaehlen(`zugang-${form.dataset.zugang}`);
      else { knopf.disabled = false; form.querySelector('input[type="email"]')?.focus(); }
    } catch {
      ok.textContent = TEXT.fehler; ok.hidden = false;
      form.classList.add('ist-fehler'); knopf.disabled = false;
    } finally {
      form.classList.remove('ist-sendet');
    }
  });
}

function zaehlen(e) {
  try { navigator.sendBeacon ? navigator.sendBeacon(`/d.php?e=${e}`) : fetch(`/d.php?e=${e}`, { method: 'POST', keepalive: true }); } catch { /* egal */ }
}
