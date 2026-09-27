/* Land für das Stripe-Auszahlungskonto wählen (28.09.2026).
 *
 * Grundlage bleibt das normale <select> -- auf dem Handy öffnet es die
 * Auswahl des Systems, ohne Skript funktioniert es genauso. Das Skript legt
 * nur ein Suchfeld darüber (33 Länder sind zum Blättern zu viele) und zeigt
 * das gewählte Land deutlich darunter an.
 *
 * Gefiltert wird durch Neuaufbau der Optionen, nicht mit option.hidden:
 * Safari auf dem iPhone ignoriert hidden bei <option>.
 */
(function () {
  var box = document.querySelector('[data-land-wahl]');
  if (!box) { return; }
  var sel = box.querySelector('select');
  var suche = box.querySelector('[data-land-suche]');
  var gew = box.querySelector('[data-land-gewaehlt]');
  var keins = box.querySelector('[data-land-keins]');
  if (!sel) { return; }

  // Alle Optionen einmal merken (Platzhalter und Trennlinie inklusive).
  var alle = Array.prototype.map.call(sel.options, function (o) {
    return { wert: o.value, text: o.textContent, name: o.getAttribute('data-name') || '', aus: o.disabled };
  });

  function klein(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function zeigen() {
    var o = sel.options[sel.selectedIndex];
    var ok = o && o.value && o.value !== '-';
    if (gew) {
      gew.hidden = !ok;
      if (ok) { gew.textContent = (gew.getAttribute('data-muster') || '{land}').replace('{land}', o.textContent.trim()); }
    }
    // Die passende Stripe-Vereinbarung: Italien = Empfänger, sonst die volle.
    // (Nur die Wahl vor dem ersten Anlegen -- nicht das Feld „Land ändern“.)
    var it = document.querySelector('[data-agb="it"]');
    var voll = document.querySelector('[data-agb="voll"]');
    if (!box.hasAttribute('data-stripe-land')) {
      if (it) { it.hidden = !ok || o.value !== 'IT'; }
      if (voll) { voll.hidden = !ok || o.value === 'IT'; }
    }
    // Land nachträglich ändern: Die Bestätigung braucht es nur, wenn dadurch
    // ein neues Stripe-Konto entsteht (anderes Land als das des Kontos).
    var best = box.querySelector('[data-land-bestaetigung]');
    var stripeLand = box.getAttribute('data-stripe-land') || '';
    if (best) {
      var neu = ok && stripeLand !== '' && o.value !== stripeLand;
      best.hidden = !neu;
      var cb = best.querySelector('input');
      if (cb) { cb.required = neu; }
    }
  }

  function filtern() {
    var q = klein(suche.value.trim());
    var gewaehlt = sel.value;
    var treffer = 0;
    sel.innerHTML = '';
    alle.forEach(function (a) {
      // Platzhalter immer, Trennlinie nur ohne Suche, Länder nach Name oder ISO-Code.
      var passt = a.wert === '' ? true
                : a.wert === '-' ? !q
                : !q || klein(a.name).indexOf(q) !== -1 || klein(a.wert) === q;
      if (!passt) { return; }
      var o = document.createElement('option');
      o.value = a.wert; o.textContent = a.text; o.disabled = a.aus;
      if (a.name) { o.setAttribute('data-name', a.name); }
      if (a.wert === gewaehlt) { o.selected = true; }
      if (a.wert && a.wert !== '-') { treffer++; }
      sel.appendChild(o);
    });
    // Genau ein Treffer: gleich auswählen -- das spart den zweiten Griff.
    if (q && treffer === 1) { sel.selectedIndex = 1; }
    if (keins) { keins.hidden = treffer > 0; }
    zeigen();
  }

  if (suche) {
    suche.hidden = false;
    suche.addEventListener('input', filtern);
    // Enter im Suchfeld schickt nicht ab, sondern springt zur Auswahl.
    suche.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); sel.focus(); } });
  }
  sel.addEventListener('change', zeigen);
  zeigen();
})();
