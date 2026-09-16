/* ==========================================================================
   DIE STILLE RECHNUNG

   Der Abschnitt steht direkt hinter dem Hero und macht aus einer Behauptung
   ("deine Seite soll Kunden bringen") eine Zahl, die der Besucher selbst
   eingestellt hat. Das ist der ganze Trick: Wer den Regler bewegt, hat die
   Rechnung nicht geglaubt, sondern gemacht.

   DREI REGLER KENNT ER, EINEN NICHT
   Besucher, heutige Quote und Auftragswert sind seine Zahlen. Nur "was
   moeglich waere" ist eine Annahme -- und die steht deshalb ebenfalls auf
   einem Regler und nicht in einer Fussnote. Eine Annahme, die man nicht
   verschieben kann, ist eine Behauptung.

   KEINE KORREKTURFAKTOREN
   Besucher x Quote x Auftragswert. Sonst nichts. Jede Branchenkurve, jeder
   Erfahrungswert waere eine Zahl von mir in einer Rechnung, die seine sein
   soll -- und genau das wuerde sie entwerten.
   ========================================================================== */

const kasten = document.querySelector('[data-rechnung]');

/* Woerter, die JavaScript setzt, stehen als data-wort-* im Markup und kommen
   damit aus dem Woerterbuch. Sonst waere die Ablesung auf allen drei
   Sprachfassungen deutsch. */
function wort(name, ersatz) {
  if (!kasten) { return ersatz; }
  const w = kasten.getAttribute('data-wort-' + name);
  return w || ersatz;
}

function zahl(name) {
  const r = kasten.querySelector('[data-regler="' + name + '"]');
  return r ? Number(r.value) : 0;
}

/* Waehrung nach Sprachfassung: 1.800 EUR liest sich in jedem der drei
   Laender anders, und eine falsch gesetzte Tausendertrennung macht aus
   einer ernsten Zahl eine unglaubwuerdige. Die Formate werden neu gesetzt,
   wenn oben rechts die Sprache wechselt. */
let geld, stueck, prozent;

function formateSetzen() {
  const sprache = document.documentElement.lang || 'it';
  geld = new Intl.NumberFormat(sprache, {
    style: 'currency', currency: 'EUR',
    minimumFractionDigits: 0, maximumFractionDigits: 0,
  });
  stueck = new Intl.NumberFormat(sprache, { maximumFractionDigits: 0 });
  prozent = new Intl.NumberFormat(sprache, {
    minimumFractionDigits: 1, maximumFractionDigits: 1,
  });
}
formateSetzen();

/* Der grosse Betrag zaehlt in einer knappen halben Sekunde hoch. Nicht als
   Spielerei: Eine Zahl, die springt, wird gelesen wie ein Etikett. Eine, die
   laeuft, wird gelesen wie ein Ergebnis. Wer Bewegung abbestellt hat,
   bekommt sie sofort. */
const ruhig = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
let laeuft = 0;
let stand = null;

function grossSetzen(feld, ziel) {
  if (stand === null || ruhig) {
    stand = ziel;
    feld.textContent = geld.format(ziel);
    return;
  }
  const von = stand;
  const start = performance.now();
  const dauer = 420;
  cancelAnimationFrame(laeuft);
  const schritt = (jetzt) => {
    const t = Math.min(1, (jetzt - start) / dauer);
    const e = 1 - Math.pow(1 - t, 3);
    const w = von + (ziel - von) * e;
    feld.textContent = geld.format(w);
    if (t < 1) { laeuft = requestAnimationFrame(schritt); } else { stand = ziel; }
  };
  laeuft = requestAnimationFrame(schritt);
}

function rechnen() {
  const besucher = zahl('besucher');
  const heuteQuote = zahl('quote') / 10;   // Regler in Zehnteln, damit 0,1 % geht
  const wert = zahl('wert');
  const zielQuote = zahl('ziel') / 10;

  const heute = besucher * (heuteQuote / 100) * wert;
  const moeglich = besucher * (zielQuote / 100) * wert;
  const luecke = Math.max(0, moeglich - heute);

  // Die Regler beschriften sich selbst -- ein Schieber ohne Zahl daneben ist
  // eine Behauptung.
  const anzeige = (name, text) => {
    const a = kasten.querySelector('[data-anzeige="' + name + '"]');
    if (a) { a.textContent = text; }
  };
  anzeige('besucher', stueck.format(besucher));
  anzeige('quote', prozent.format(heuteQuote) + ' %');
  anzeige('wert', geld.format(wert));
  anzeige('ziel', prozent.format(zielQuote) + ' %');

  const heuteFeld = kasten.querySelector('[data-rechnung-heute]');
  const grossFeld = kasten.querySelector('[data-rechnung-luecke]');
  const jahrFeld = kasten.querySelector('[data-rechnung-jahr]');
  const satzFeld = kasten.querySelector('[data-rechnung-satz]');

  if (heuteFeld) { heuteFeld.textContent = geld.format(heute); }
  if (grossFeld) { grossSetzen(grossFeld, luecke); }
  if (jahrFeld) { jahrFeld.textContent = geld.format(luecke * 12); }

  /* Steht das Ziel unter der heutigen Quote, gibt es keine Luecke. Dann
     jetzt keine erfundene Zahl und keinen traurigen Nuller, sondern der
     ehrliche Satz -- er verkauft besser als eine ausgedachte Luecke. */
  kasten.setAttribute('data-rechnung-leer', luecke <= 0 ? 'ja' : 'nein');
  if (satzFeld) {
    satzFeld.textContent = luecke <= 0 ? wort('null', '') : wort('satz', '');
  }
}

if (kasten) {
  kasten.addEventListener('input', (e) => {
    if (e.target.matches('[data-regler]')) { rechnen(); }
  });

  /* Beim Sprachwechsel neu formatieren UND neu setzen: die Saetze im Blatt
     schreibt JavaScript, das Woerterbuch erreicht sie also nur ueber diesen
     Weg. Ohne Sprung -- der Betrag bleibt derselbe, nur seine Schreibweise
     aendert sich. */
  document.addEventListener('vecom:sprache', () => {
    formateSetzen();
    stand = null;
    rechnen();
  });

  /* Erst rechnen, wenn die Woerterbuecher durch sind: sonst stuenden die
     Betraege einen Wimpernschlag lang in der falschen Sprache. Ist die
     Uebersetzung schon gelaufen, bevor dieses Modul geladen war, kam das
     Ereignis nie an -- dann greift die Abfrage am Wurzelelement. */
  if (document.documentElement.hasAttribute('data-i18n-fertig')) { rechnen(); }
}
