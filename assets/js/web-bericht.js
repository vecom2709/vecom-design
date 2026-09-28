/* Rechner im Website-Bericht (28.09.2026, A6): rechnet mit den Zahlen des
   Inhabers, sobald er tippt oder schiebt. Ohne Skript rechnet der Server
   (Formular mit GET) -- dieselbe Formel: Kunden im Monat × 12 × Wert. */
(function () {
  var f = document.querySelector('.wb form.wb-rech');
  if (!f) { return; }
  var w = f.querySelector('input[name=wbw]'), m = f.querySelector('input[name=wbm]');
  var zeigeM = f.querySelector('[data-wb-m]'), aus = f.querySelector('[data-wb-ergebnis]'), bez = f.querySelector('[data-wb-bezahlt]');
  var knopf = f.querySelector('[data-wb-senden]');
  var sp = f.getAttribute('data-sprache') || 'it', preis = parseInt(f.getAttribute('data-preis') || '0', 10);
  function euro(n) {
    var s = Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, sp === 'en' ? ',' : '.');
    return sp === 'en' ? '€' + s : s + ' €';
  }
  function rechnen() {
    var wert = Math.max(0, Math.min(100000, parseInt(w.value || '0', 10) || 0));
    var mehr = Math.max(1, Math.min(30, parseInt(m.value || '1', 10) || 1));
    if (zeigeM) { zeigeM.textContent = String(mehr); }
    if (!wert) { aus.textContent = ''; if (bez) { bez.textContent = ''; } return; }
    aus.textContent = f.getAttribute('data-text').replace('{n}', String(mehr * 12)).replace('{summe}', euro(mehr * 12 * wert));
    if (bez && preis > 0) { bez.textContent = f.getAttribute('data-bezahlt').replace('{n}', String(Math.max(1, Math.ceil(preis / 100 / wert)))); }
  }
  w.addEventListener('input', rechnen);
  m.addEventListener('input', rechnen);
  if (knopf) { knopf.hidden = true; }
  rechnen();
})();
