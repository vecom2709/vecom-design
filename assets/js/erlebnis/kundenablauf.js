/* ==========================================================================
   kundenablauf.js — „So sieht es Ihre Kundschaft": der Weg des Endkunden
   als kurze Vorführung in jeder Demo (24.09.2026).

   WARUM
   Uwe: „jede Branche möchte ihre Kunden ansprechen". Ein Juwelier kauft
   keine drehende Uhr, er kauft Anfragen. Deshalb endet jede Demo mit dem
   Schritt, den SEIN Kunde macht -- Probefahrt anfragen, Tisch reservieren,
   Termin mit Wunschfarbe -- und zeigt danach, wie die Anfrage beim Betrieb
   ankommt: mit genau der Auswahl, die der Kunde getroffen hat.

   WAS ES NICHT IST
   Kein echtes Formular. Es werden keine Namen, Nummern oder Adressen
   abgefragt und nichts verschickt; die Vorführung ist als Beispiel
   gekennzeichnet. Echt ist nur der letzte Knopf: die Anfrage an Vecom.

   Ohne Kopplung an andere Module (Lehre vom 14.09.2026: Modulgraphen aus
   zwei Zeitaltern): Die Demos schicken ein Ereignis 'vecom:kunde'.
   ========================================================================== */
const L = (document.documentElement.lang || 'it').slice(0, 2);
const SPRACHE = ['it', 'de', 'en'].includes(L) ? L : 'it';
const LOKAL = { it: 'it-IT', de: 'de-DE', en: 'en-GB' }[SPRACHE];
const T = {
  de: { kicker: 'Beispiel · so sieht es Ihre Kundschaft', tag: 'Tag', zeit: 'Uhrzeit', senden: 'Anfrage senden', zurueck: 'Zurück',
    eingang: 'So kommt es bei Ihnen an', neu: 'Neue Anfrage über Ihre Website', gerade: 'gerade eben', bestaetigen: 'Bestätigen', vorschlagen: 'Anderen Termin vorschlagen',
    hinweis: 'Vorführung — es wird nichts verschickt.', zu: 'Schließen', fuer: 'Das möchte ich für meinen Betrieb', menge: 'Anzahl', personen: (n) => `${n} ${n === 1 ? 'Person' : 'Personen'}` },
  it: { kicker: 'Esempio · così lo vedono i tuoi clienti', tag: 'Giorno', zeit: 'Ora', senden: 'Invia richiesta', zurueck: 'Indietro',
    eingang: 'Così ti arriva', neu: 'Nuova richiesta dal tuo sito', gerade: 'adesso', bestaetigen: 'Conferma', vorschlagen: 'Proponi un altro orario',
    hinweis: 'Dimostrazione — non viene inviato nulla.', zu: 'Chiudi', fuer: 'Lo voglio per la mia attività', menge: 'Quantità', personen: (n) => `${n} ${n === 1 ? 'persona' : 'persone'}` },
  en: { kicker: 'Example · what your customers see', tag: 'Day', zeit: 'Time', senden: 'Send request', zurueck: 'Back',
    eingang: 'This is how it reaches you', neu: 'New request from your website', gerade: 'just now', bestaetigen: 'Confirm', vorschlagen: 'Suggest another time',
    hinweis: 'Demonstration — nothing is sent.', zu: 'Close', fuer: 'I want this for my business', menge: 'Quantity', personen: (n) => `${n} ${n === 1 ? 'person' : 'people'}` },
}[SPRACHE];

const el = (tag, attr = {}, ...kinder) => {
  const n = document.createElement(tag);
  for (const [k, v] of Object.entries(attr)) { if (k === 'text') n.textContent = v; else if (k.startsWith('on')) n.addEventListener(k.slice(2), v); else if (v !== false && v != null) n.setAttribute(k, v === true ? '' : v); }
  n.append(...kinder.filter(Boolean)); return n;
};

let dlg = null;
function dialogHolen() {
  if (dlg) return dlg;
  dlg = el('dialog', { class: 'ka', 'aria-labelledby': 'ka-titel' });
  dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });   // Klick neben die Karte
  (document.querySelector('.erlebnis') || document.body).append(dlg);   // dort gelten Knöpfe und Farben
  return dlg;
}

/* Die nächsten Werktage (ohne Sonntag), als Knöpfe */
function tage(n = 6) {
  const out = []; const d = new Date(); d.setHours(12, 0, 0, 0);
  while (out.length < n) { d.setDate(d.getDate() + 1); if (d.getDay() !== 0) out.push(new Date(d)); }
  return out;
}
const tagText = (d) => d.toLocaleDateString(LOKAL, { weekday: 'short', day: 'numeric', month: 'numeric' });

/* o = { titel, zeilen: [..], termin: { zeiten: [...] } | null, personen: true|false,
         eingangTitel, ziel (URL der Vecom-Anfrage), zaehlen } */
function oeffnen(o) {
  const d = dialogHolen();
  const wahl = { tag: tage()[0], zeit: o.termin?.zeiten?.[0] || null, personen: 2 };
  const chipReihe = (werte, aktiv, text, setzen, label) => el('div', { class: 'ka-chips', role: 'group', 'aria-label': label },
    ...werte.map((w) => el('button', { type: 'button', 'aria-pressed': String(w === aktiv || (w instanceof Date && aktiv instanceof Date && w.getTime() === aktiv.getTime())), onclick: (e) => { setzen(w); for (const b of e.currentTarget.parentElement.children) b.setAttribute('aria-pressed', String(b === e.currentTarget)); }, text: text(w) })));

  function schritt1() {
    d.replaceChildren(el('div', { class: 'ka-karte' },
      el('button', { type: 'button', class: 'ka-zu', 'aria-label': T.zu, onclick: () => d.close(), text: '×' }),
      el('p', { class: 'ka-kicker', text: T.kicker }),
      el('h3', { id: 'ka-titel', text: o.titel }),
      el('ul', { class: 'ka-auswahl' }, ...o.zeilen.map((z) => el('li', { text: z }))),
      o.personen ? el('div', { class: 'ka-feld' }, el('span', { class: 'ka-name', text: T.menge }),
        chipReihe([1, 2, 3, 4, 6, 8], 2, T.personen, (w) => { wahl.personen = w; }, T.menge)) : null,
      o.termin ? el('div', { class: 'ka-feld' }, el('span', { class: 'ka-name', text: T.tag }), chipReihe(tage(), wahl.tag, tagText, (w) => { wahl.tag = w; }, T.tag)) : null,
      o.termin ? el('div', { class: 'ka-feld' }, el('span', { class: 'ka-name', text: T.zeit }), chipReihe(o.termin.zeiten, wahl.zeit, (w) => w, (w) => { wahl.zeit = w; }, T.zeit)) : null,
      el('div', { class: 'ka-aktion' }, el('button', { type: 'button', class: 'knopf knopf--voll', onclick: schritt2 }, o.senden || T.senden)),
      el('p', { class: 'ka-hinweis', text: T.hinweis })));
    d.querySelector('.knopf')?.focus();
  }
  function schritt2() {
    const zeilen = [...o.zeilen];
    if (o.personen) zeilen.push(T.personen(wahl.personen));
    if (o.termin) zeilen.push(`${tagText(wahl.tag)} · ${wahl.zeit}`);
    d.replaceChildren(el('div', { class: 'ka-karte' },
      el('button', { type: 'button', class: 'ka-zu', 'aria-label': T.zu, onclick: () => d.close(), text: '×' }),
      el('p', { class: 'ka-kicker', text: T.eingang }),
      el('div', { class: 'ka-eingang' },
        el('div', { class: 'ka-eingang__kopf' }, el('b', { text: T.neu }), el('span', { text: T.gerade })),
        el('strong', { text: o.eingangTitel || o.titel }),
        el('ul', {}, ...zeilen.map((z) => el('li', { text: z }))),
        o.termin ? el('div', { class: 'ka-eingang__knoepfe' }, el('span', { text: T.bestaetigen }), el('span', { text: T.vorschlagen })) : null),
      el('div', { class: 'ka-aktion' },
        el('a', { class: 'knopf knopf--voll', href: o.ziel, onclick: () => o.zaehlen && o.zaehlen('ka-cta') }, T.fuer),
        el('button', { type: 'button', class: 'knopf knopf--leer', onclick: schritt1 }, T.zurueck))));
    d.querySelector('.knopf')?.focus();
    o.zaehlen && o.zaehlen('ka-gesendet');
  }
  schritt1();
  if (!d.open) d.showModal();
}

document.addEventListener('vecom:kunde', (e) => oeffnen(e.detail));
window.vecomKunde = { oeffnen };
