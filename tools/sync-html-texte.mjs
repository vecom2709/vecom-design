/* ==========================================================================
   tools/sync-html-texte.mjs — zieht die eingebauten Texte in den drei
   index.html aus assets/js/i18n-data.js nach.

   WARUM ES DAS GIBT
   Jeder Text steht zweimal: einmal als Vorgabe im HTML, einmal in den
   Sprachdaten. Beim Bearbeiten aendert man die Sprachdaten — das HTML bleibt
   stehen. Mit JavaScript faellt das nie auf, weil die Sprachdaten die Vorgabe
   ueberschreiben. Ohne JavaScript schon: dann liest der Besucher die alte
   Fassung. Und jede Suchmaschine liest den Quelltext, nicht das Ergebnis.
   So stand im deutschen Quelltext monatelang "von Ihnen pflegbar", waehrend
   auf dem Bildschirm "von dir pflegbar" zu sehen war.

   Aufruf: node tools/sync-html-texte.mjs [--pruefen]
   --pruefen aendert nichts, sondern meldet nur die Abweichungen (fuer CI).
   ========================================================================== */
import { readFileSync, writeFileSync } from 'node:fs';

const NUR_PRUEFEN = process.argv.includes('--pruefen');
global.window = {};
await import('../assets/js/i18n-data.js');
const I18N = global.window.VECOM_I18N;

const escape = (s) => String(s)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const hole = (lang, pfad) => pfad.split('.').reduce((o, k) => (o || {})[k], I18N[lang]);

let abweichungen = 0;

for (const [datei, lang] of [['index.html', 'it'], ['de/index.html', 'de'], ['en/index.html', 'en']]) {
  let s = readFileSync(datei, 'utf8');
  const vorher = s;
  let n = 0;

  /* Einfache Textknoten: <tag data-i18n="pfad">Text</tag>
     Nur, wenn wirklich nur Text drinsteht — Elemente mit verschachtelten
     Tags werden uebersprungen, sonst zerschiesst man Markup. */
  s = s.replace(/(<([a-z0-9]+)\b[^>]*\sdata-i18n="([^"]+)"[^>]*>)([^<]*)(<\/\2>)/gi,
    (ganz, auf, tag, pfad, alt, zu) => {
      const wert = hole(lang, pfad);
      if (typeof wert !== 'string') { return ganz; }
      const neu = escape(wert);
      if (neu === alt) { return ganz; }
      n++;
      return auf + neu + zu;
    });

  /* Listen: <ul data-i18n-list="pfad"> … </ul>, Werte mit | getrennt */
  s = s.replace(/(<ul\b[^>]*\sdata-i18n-list="([^"]+)"[^>]*>)([\s\S]*?)(<\/ul>)/gi,
    (ganz, auf, pfad, alt, zu) => {
      const wert = hole(lang, pfad);
      if (typeof wert !== 'string') { return ganz; }
      /* Die erste Zeile mancher Listen traegt class="is-lead" ("Enthalten:").
         Sie steht aber AUCH in den Sprachdaten — wer sie zusaetzlich stehen
         laesst, hat sie danach zweimal. Also: Liste vollstaendig aus den
         Daten neu bauen und die Klasse nur auf die erste Zeile zurueckgeben,
         wenn sie vorher da war. */
      const hatteLead = /<li class="is-lead"/.test(alt);
      const einzug = '            ';
      const zeilen = wert.split('|').map((x, i) =>
        einzug + '<li' + (i === 0 && hatteLead ? ' class="is-lead"' : '') + '>' + escape(x) + '</li>');
      const neu = '\n' + zeilen.join('\n') + '\n          ';
      if (neu === alt) { return ganz; }
      n++;
      return auf + neu + zu;
    });

  if (s !== vorher) {
    abweichungen += n;
    if (NUR_PRUEFEN) {
      console.log(`${datei}: ${n} Stelle(n) weichen von den Sprachdaten ab`);
    } else {
      writeFileSync(datei, s);
      console.log(`${datei}: ${n} Stelle(n) nachgezogen`);
    }
  } else {
    console.log(`${datei}: stimmt überein`);
  }
}

if (NUR_PRUEFEN && abweichungen > 0) { process.exit(1); }
