/* ==========================================================================
   build.mjs — erzeugt aus index.html die statischen Sprachseiten /de/ und /en/.

   Warum überhaupt: Die Umschaltung im Browser reicht Menschen, aber nicht
   Suchmaschinen. Google indexiert eine URL, nicht einen Zustand. Mit /de/ und
   /en/ gibt es je Sprache eine eigene Adresse, ein eigenes <title>, eine eigene
   Beschreibung — und hreflang verbindet sie zu einer Gruppe.

   Aufruf:  node build.mjs
   Danach:  /            → Italienisch (Standard, x-default)
            /de/, /en/   → statisch vorgerendert
   ========================================================================== */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { LANDESEITEN, LANDESEITEN_WORTE, LANDESEITEN_KURZ, LANDESEITEN_STAND } from './seiten/landeseiten.mjs';

const BASE = 'https://vecom-design.it';

/* ==========================================================================
   DAS BUENDEL FUER DIE STARTSEITE

   Die Startseite lud einundzwanzig Skript-Tags, davon zehn Dateien, die nur
   sie braucht — zwischen 578 Bytes und 11 KB, jede mit eigenem Kopf, eigenem
   Kompressionsstrom, eigener Anfrage. Gemessen: einzeln komprimiert 14.127
   Bytes, als eine Datei 11.492. Ein Fuenftel weniger, nur weil der Kompressor
   ueber alles zusammen laufen darf, plus neun Anfragen weniger.

   Die Quelldateien bleiben, wie sie sind — geaendert wird weiter an
   schau.js, nicht an einem Buendel. Diese Datei entsteht bei jedem Bau neu
   und liegt trotzdem im Repository, damit die Seite auch lokal laeuft, ohne
   dass jemand vorher baut.

   REIHENFOLGE IST INHALT: Die Dateien stehen hier genau so, wie sie vorher
   im HTML standen. Jede ist eine eigene Funktion, die sich sofort ausfuehrt
   — sie stoeren einander nicht, aber sie laufen in einer Ordnung, und die
   bleibt.

   Andere Seiten bekommen kein Buendel: Sie laden ein bis drei Skripte, da
   waere es Aufwand ohne Gewinn.
   ========================================================================== */
/* preise-live steht neben pakete-live, weil es dieselbe Sache tut: Zahlen aus
   der Verwaltung holen. Seit dem 12.09.2026 stehen auf der Startseite vier
   Beispielpreise — wuerden sie fest im HTML stehen, waeren sie beim ersten
   Preisschritt falsch, und zwar an einer Stelle, die niemand nachrechnet. Die
   Datei holt nur die vier Werte, wenn kein Listenblatt da ist. */
const BUENDEL_TEILE = [
  'screens', 'schau', 'polish', 'pakete-live', 'preise-live', 'stimmen-live',
  'vids', 'depth', 'sig', 'social', 'vergleich',
];
const BUENDEL_ZIEL = 'assets/js/start.js';

function buendeln() {
  const kopf = `/* ==========================================================================
   ERZEUGT — NICHT VON HAND AENDERN.

   Zusammengesetzt aus (in dieser Reihenfolge):
${BUENDEL_TEILE.map((t) => `     assets/js/${t}.js`).join('\n')}

   Geaendert wird an diesen Dateien. Diese hier entsteht bei jedem Deploy
   neu (build.mjs) und wird ueberschrieben.
   ========================================================================== */
`;
  const stuecke = BUENDEL_TEILE.map((t) => {
    const pfad = `assets/js/${t}.js`;
    if (!existsSync(pfad)) { throw new Error(`Buendel: ${pfad} fehlt.`); }
    return `\n/* ----- ${t}.js ----- */\n` + readFileSync(pfad, 'utf8');
  });
  const neu = kopf + stuecke.join('\n');
  const alt = existsSync(BUENDEL_ZIEL) ? readFileSync(BUENDEL_ZIEL, 'utf8') : '';
  if (neu !== alt) {
    writeFileSync(BUENDEL_ZIEL, neu);
    console.log(`geschrieben: ${BUENDEL_ZIEL} (${BUENDEL_TEILE.length} Dateien, ${(neu.length / 1024).toFixed(0)} KB)`);
  } else {
    console.log(`unveraendert: ${BUENDEL_ZIEL}`);
  }
}
buendeln();
const LANGS = { it: '', de: 'de/', en: 'en/' };
const LOCALES = { it: 'it_IT', de: 'de_DE', en: 'en_GB' };

/* DREI DATEIEN STATT EINER — AUCH HIER
   --------------------------------------------------------------------------
   Die Sprachdaten liegen seit dem 05.09.2026 je Sprache getrennt, damit eine
   Seite nicht zwei Sprachen laedt, die sie nie zeigt. Dieser Bau braucht
   dagegen alle drei: Er erzeugt aus einer Quelle drei Fassungen. Also werden
   hier alle geladen und zusammengefuegt — und in die gebaute Seite kommt
   nachher nur die eine, die sie braucht (siehe sprachdatei()). */
globalThis.window = {};
for (const sp of ['it', 'de', 'en']) {
  await import(`./assets/js/i18n-${sp}.js`);
}
const DICT = globalThis.window.VECOM_I18N;

const get = (lang, path) => path.split('.').reduce((o, k) => (o || {})[k], DICT[lang]);
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const escAttr = (s) => esc(s).replace(/"/g, '&quot;');

/* --------------------------------------------------------------------------
   Die Seiten, die dreisprachig ausgegeben werden.

   Bis hierher gab es genau eine: index.html. Mit der Preisseite sind es zwei,
   und ab zwei muss die Liste erklaerlich sein statt verstreut.

   quelle  Die italienische Fassung. Sie ist zugleich Quelle und Ziel — was
           schon gebaut ist, wird beim naechsten Lauf sauber ueberschrieben,
           deshalb sind alle Regeln unten auf eine bereits gebaute Seite
           anwendbar.
   ziele   Wohin je Sprache geschrieben wird. Die Adressen sind bewusst in der
           jeweiligen Sprache: /prezzi.html, /de/preise.html, /en/pricing.html.
           Eine Suchmaschine liest die Adresse mit, und ein deutscher Leser
           auch.
   meta    Der Ast im Woerterbuch, aus dem Titel und Beschreibung kommen.
   faq     Aus welchen Schluesseln das FAQ-Schema gebaut wird. null = keins.
   heim    Ob Verweise auf index.html auf die Startseite der jeweiligen Sprache
           umgeschrieben werden. Auf der Startseite selbst waere das unsinnig.
   -------------------------------------------------------------------------- */
const SEITEN = [
  {
    quelle: 'index.html',
    ziele: { it: 'index.html', de: 'de/index.html', en: 'en/index.html' },
    adressen: { it: '', de: 'de/', en: 'en/' },
    meta: { titel: 'meta.title', text: 'meta.desc' },
    faq: { ast: 'faq', von: 1, bis: 8 },
    heim: false,
  },
  {
    quelle: 'prezzi.html',
    ziele: { it: 'prezzi.html', de: 'de/preise.html', en: 'en/pricing.html' },
    adressen: { it: 'prezzi.html', de: 'de/preise.html', en: 'en/pricing.html' },
    meta: { titel: 'preise.metaTitle', text: 'preise.metaDesc' },
    faq: { ast: 'preise', von: 1, bis: 6 },
    heim: true,
  },
  {
    quelle: 'assistenza.html',
    ziele: { it: 'assistenza.html', de: 'de/betreuung.html', en: 'en/care.html' },
    adressen: { it: 'assistenza.html', de: 'de/betreuung.html', en: 'en/care.html' },
    meta: { titel: 'betreuungsseite.metaTitle', text: 'betreuungsseite.metaDesc' },
    faq: null,
    heim: true,
  },
  /* Der Showroom. Wie der Konfigurator eine Seite mit eigenem Stilblatt im
     Kopf; sie laedt weder app.css noch ein i18n-Skript, alle Texte stehen
     nach dem Bauen fest drin.

     Der Dateiname ist in allen drei Sprachen derselbe -- die Ausnahme in
     dieser Liste. "Showroom" ist im Italienischen und Deutschen dasselbe
     Wort wie im Englischen, eine Uebersetzung waere eine Erfindung. Weil die
     Datei ueberall gleich heisst und jeweils neben der Startseite ihrer
     Sprache liegt, braucht sie keine eigene Drehregel: Der blosse Name
     stimmt aus jeder der drei Ebenen. */
  {
    quelle: 'showroom.html',
    ziele: { it: 'showroom.html', de: 'de/showroom.html', en: 'en/showroom.html' },
    adressen: { it: 'showroom.html', de: 'de/showroom.html', en: 'en/showroom.html' },
    meta: { titel: 'showroom.metaTitle', text: 'showroom.metaDesc' },
    faq: null,
    heim: true,
  },
  /* Der Produktkonfigurator. Anders als die drei oben traegt er sein eigenes
     Stilblatt im Kopf und laedt kein i18n-Skript: Alle Texte stehen nach dem
     Bauen fest in der Seite. Das ist Absicht -- die Seite soll ohne app.js,
     ohne GSAP und ohne three.js auskommen, sonst waere die Behauptung
     "laeuft auf jedem Geraet gleich" nur halb wahr.
     Die Bilder unter assets/img/3d/tisch/ sind fuer alle drei Fassungen
     dieselben; der Pfad steht deshalb absolut im Skript. */
  /* Die Technikseite (Umbau 24.09.2026): Qualitaetsstufen, Technik, Vergleich
     und Streaming, von der Startseite hierher verlegt (Vorschlag K1). Sie
     traegt dieselben Skripte wie die Startseite. */
  {
    quelle: 'tecnica.html',
    ziele: { it: 'tecnica.html', de: 'de/technik.html', en: 'en/technology.html' },
    adressen: { it: 'tecnica.html', de: 'de/technik.html', en: 'en/technology.html' },
    meta: { titel: 'edel.tk_metaTitle', text: 'edel.tk_metaDesc' },
    faq: null,
    heim: true,
  },
  {
    quelle: 'tavolo.html',
    ziele: { it: 'tavolo.html', de: 'de/tisch.html', en: 'en/table.html' },
    adressen: { it: 'tavolo.html', de: 'de/tisch.html', en: 'en/table.html' },
    meta: { titel: 'tavolo.metaTitle', text: 'tavolo.metaDesc' },
    faq: null,
    heim: true,
  },];

function hreflang(seite) {
  return Object.keys(LANGS)
    .map((l) => `<link rel="alternate" hreflang="${l}" href="${BASE}/${seite.adressen[l]}">`)
    .concat(`<link rel="alternate" hreflang="x-default" href="${BASE}/${seite.adressen.it}">`)
    .join('\n');
}

// Sprachwahl als echte Links statt Knöpfe — nur so folgt eine Suchmaschine ihnen.
function langLinks(current, up, seite) {
  // Die Sprachwahl muss auf dieselbe Seite in der anderen Sprache zeigen, nicht
  // pauschal auf die Startseite. Wer auf der Preisseite DE anklickt, will die
  // Preisseite auf Deutsch und nicht wieder von vorn anfangen.
  const ziel = (l) => `${up || './'}${seite.adressen[l]}`;
  const item = (l, label) =>
    l === current
      ? `<span class="lang__current" aria-current="true">${label}</span>`
      : `<a href="${ziel(l)}" hreflang="${l}" lang="${l}">${label}</a>`;
  return `<div class="lang lang--links" role="group" aria-label="Lingua / Sprache / Language">
        ${item('it', 'IT')}${item('de', 'DE')}${item('en', 'EN')}
      </div>`;
}

/* --------------------------------------------------------------------------
   Fingerabdruck an jede eigene CSS- und JS-Datei haengen.

   Der Grund steht in PROJEKT.md: Am 01.09.2026 wurde eine kaputte qform.js
   ersetzt, die jede Anfrage still verschluckte — und der Browser eines
   wiederkehrenden Besuchers haette die alte noch stundenlang weiterbenutzt.
   Der Webspace schickt kein Cache-Control, also entscheidet der Browser nach
   Gutduenken. Aendert sich der Inhalt, aendert sich die Adresse: dann gibt es
   nichts mehr zu raten.

   Der Wert kommt aus dem Inhalt der Datei, nicht aus einem Datum — ein Deploy
   ohne Aenderung laesst die Adresse in Ruhe und wirft keinen Cache weg.
   -------------------------------------------------------------------------- */
const stempelSpeicher = new Map();
function stempel(pfad) {
  if (!stempelSpeicher.has(pfad)) {
    let wert = '';   // leer = Datei nicht da, dann lieber gar kein Stempel
    if (existsSync(pfad)) {
      wert = createHash('sha1').update(readFileSync(pfad)).digest('hex').slice(0, 8);
    }
    stempelSpeicher.set(pfad, wert);
  }
  return stempelSpeicher.get(pfad);
}

function fingerabdruecke(h) {
  // Greift auch auf eine bereits gestempelte Seite zu (index.html ist Quelle
  // und Ziel zugleich): ein vorhandenes ?v=... wird ersetzt, nicht ergaenzt.
  //
  // WARUM AUCH BILDER UND VIDEOS
  // Der Server sagt "public, max-age=2592000" — dreissig Tage. Solange die
  // Adresse gleich bleibt, holt kein wiederkehrender Besucher die Datei neu.
  // Als das Erklaervideo eine Tonspur bekam, lag auf dem Server die neue
  // Fassung und im Browser weiter die alte, stumme. Der Fingerabdruck steht
  // deshalb an allem, was sich aendern kann, nicht nur an CSS und Skripten.
  //
  // srcset und avif kamen am 14.09.2026 dazu. Das Standbild der Buehne lag
  // bis dahin als Hintergrundbild im CSS -- und in ein url() im Stilblatt
  // schreibt diese Funktion nicht hinein. Ergebnis: Der Server gab dem Bild
  // dreissig Tage, die Adresse blieb gleich, und ein Rueckkehrer haette das
  // alte Bild noch einen Monat behalten, obwohl das neue laengst oben lag.
  // Seitdem steht es als <picture> in der Seite, und damit auch in dieser
  // Zeile. srcset trifft hier genau eine Adresse ohne Deskriptor; eine
  // Breitenliste ("bild.webp 480w, ...") wuerde diese Regel nicht treffen
  // und braeuchte eine eigene.
  return h.replace(
    /((?:href|src|data-src|srcset)=")((?:\.\.\/)?(?:assets\/(?:css|js|img|vendor)|video)\/[A-Za-z0-9._\/-]+\.(?:css|js|mp4|webm|webp|avif|png|jpg|svg))(\?v=[A-Za-z0-9]*)?(")/g,
    (m, vorn, pfad, alt, hinten) => {
      const stempelwert = stempel(pfad.replace(/^\.\.\//, ''));
      // Fehlt die Datei, lieber ohne Stempel ausliefern als mit einem falschen.
      return stempelwert ? `${vorn}${pfad}?v=${stempelwert}${hinten}` : `${vorn}${pfad}${hinten}`;
    }
  );
}

/* Die Quelle laedt i18n-it.js. Die deutsche Fassung muss i18n-de.js laden —
   sonst stuende in /de/ deutscher Text neben italienischen Woerterbuchdaten,
   und alles, was erst im Browser gesetzt wird (Titel, Formularfehler, das
   Laufband), waere wieder italienisch. Der Pfad bleibt, wie er ist: Um das
   "../" kuemmert sich weiter unten der Schritt fuer die Unterordner. */
function sprachdatei(h, lang) {
  return h.replace(/((?:\.\.\/)?assets\/js\/)(i18n|legal)-(?:it|de|en)\.js/g,
                   (m, pfad, name) => `${pfad}${name}-${lang}.js`);
}

function build(lang, seite) {
  let h = readFileSync(seite.quelle, 'utf8');
  /* Titel- und Beschreibungsschluessel aus der Seitenliste, nicht von Hand
     (26.09.2026): tecnica.html trug den Schluessel der Startseite, und
     app.js ueberschrieb zur Laufzeit ihren Titel -- die Beschreibung jeder
     Unterseite ohnehin. Eine Quelle der Wahrheit: SEITEN[].meta. */
  if (seite.meta) {
    h = h.replace(/<html([^>]*)>/, (m, attr) => {
      attr = attr.replace(/\s+data-(?:title|desc)-key="[^"]*"/g, '');
      return `<html${attr} data-title-key="${seite.meta.titel}" data-desc-key="${seite.meta.text}">`;
    });
  }
  h = sprachdatei(h, lang);

  // 1. Texte in der Zielsprache fest einsetzen
  h = h.replace(/(<(\w+)[^>]*\bdata-i18n="([a-zA-Z0-9_.]+)"[^>]*>)([\s\S]*?)<\/\2>/g, (m, open, tag, key) => {
    const v = get(lang, key);
    return typeof v === 'string' ? `${open}${esc(v)}</${tag}>` : m;
  });
  h = h.replace(/(<(\w+)[^>]*\bdata-i18n-list="([a-zA-Z0-9_.]+)"[^>]*>)[\s\S]*?<\/\2>/g, (m, open, tag, key) => {
    const v = get(lang, key);
    if (typeof v !== 'string') return m;
    const items = v.split('|').map((i) => {
      const t = i.trim();
      return `\n          <li${t.endsWith(':') ? ' class="is-lead"' : ''}>${esc(t)}</li>`;
    }).join('');
    return `${open}${items}\n        </${tag}>`;
  });
  h = h.replace(/<[^>]*\bdata-i18n-attr="([^"]+)"[^>]*>/g, (whole, spec) => {
    let out = whole;
    spec.split(',').forEach((pair) => {
      const [a, k] = pair.split(':').map((x) => x.trim());
      const v = get(lang, k);
      if (typeof v !== 'string') return;
      /* ERST WEG, DANN SETZEN -- dieselbe Regel wie bei der Telefonzeile.
         Die Quelle ist zugleich das italienische Ziel. Beim deutschen
         Durchgang steht das Attribut also schon da, mit italienischem Text,
         und ein "nur ergaenzen, was fehlt" laesst es stehen. Genau so trug
         de/tisch.html am 16.09.2026 ein italienisches alt -- sichtbar nur
         fuer Vorleseprogramme und Suchmaschinen, also fuer niemanden, der
         es gemeldet haette. */
      out = out.replace(new RegExp(`\\s${a}="[^"]*"`), '');
      out = out.slice(0, -1) + ` ${a}="${escAttr(v)}"` + '>';
    });
    return out;
  });
  const mq = get(lang, 'marquee');
  h = h.replace(/<div class="marquee__track" data-marquee>[\s\S]*?<\/div>/,
    `<div class="marquee__track" data-marquee><span>${esc(mq)}</span><span aria-hidden="true">${esc(mq)}</span></div>`);

  // 1b. FAQ-Schema: Google zeigt die Fragen direkt im Suchergebnis an
  // Erst entfernen, dann setzen: die Quelle ist zugleich das Ziel — ohne das
  // sammelt sich bei jedem Lauf ein weiteres FAQ-Schema an.
  h = h.replace(/<script type="application\/ld\+json">\s*\{\s*"@context"[^<]*"FAQPage"[\s\S]*?<\/script>\s*/g, '');
  if (seite.faq) {
    const nummern = [];
    for (let n = seite.faq.von; n <= seite.faq.bis; n++) { nummern.push(n); }
    const faq = {
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      mainEntity: nummern.map((n) => ({
        '@type': 'Question',
        name: get(lang, `${seite.faq.ast}.q${n}`),
        acceptedAnswer: { '@type': 'Answer', text: get(lang, `${seite.faq.ast}.a${n}`) },
      })),
    };
    h = h.replace('</head>', `<script type="application/ld+json">\n${JSON.stringify(faq, null, 2)}\n</script>\n</head>`);
  }

  // Erklärvideo je Sprache — Datei UND Vorschaubild.
  //
  // Hier stand vorher nur eine Regel für die alten Dateinamen. Als das neue
  // Video dazukam, blieb sie stehen: /de/ und /en/ zeigten die italienische
  // Fassung, weil index.html die Quelle für alle drei Sprachen ist und der
  // Name unverändert mitwanderte. Auf dem Prüfstand war davon nichts zu
  // sehen — dort liegen die drei Dateien einzeln und richtig; erst der Build
  // baut sie auseinander. Deshalb weiter unten zusätzlich eine Prüfung.
  h = h.replace(/ablauf-[a-z]{2}\.mp4/g, `ablauf-${lang}.mp4`);
  h = h.replace(/video-ablauf-[a-z]{2}\.webp/g, `video-ablauf-${lang}.webp`);
  // Dashboard-Einblick (V1): echte Ansichten je Sprache
  h = h.replace(/einblick\/(h?\d)-[a-z]{2}\.webp/g, `einblick/$1-${lang}.webp`);

  // 2. Kopfdaten
  const url = `${BASE}/${seite.adressen[lang]}`;
  // Vorhandene data-lang-fixed mit einsammeln — sonst haengt sich bei jedem
  // Lauf ein weiteres an, weil index.html Quelle und Ziel zugleich ist.
  h = h.replace(/<html lang="[^"]*"(?:\s+data-lang-fixed="[^"]*")*/, `<html lang="${lang}" data-lang-fixed="${lang}"`);
  h = h.replace(/<title>[\s\S]*?<\/title>/, `<title>${esc(get(lang, seite.meta.titel))}</title>`);
  h = h.replace(/<meta name="description" content="[^"]*">/, `<meta name="description" content="${escAttr(get(lang, seite.meta.text))}">`);
  h = h.replace(/<link rel="canonical"[^>]*>/, `<link rel="canonical" href="${url}">`);
  h = h.replace(/<link rel="alternate"[\s\S]*?x-default"[^>]*>/, hreflang(seite));
  h = h.replace(/<meta property="og:url"[^>]*>/, `<meta property="og:url" content="${url}">`);
  h = h.replace(/<meta property="og:title"[^>]*>/, `<meta property="og:title" content="${escAttr(get(lang, seite.meta.titel))}">`);
  h = h.replace(/<meta property="og:description"[^>]*>/, `<meta property="og:description" content="${escAttr(get(lang, seite.meta.text))}">`);
  h = h.replace(/<meta property="og:locale"[^>]*>/, `<meta property="og:locale" content="${LOCALES[lang]}">`);

  // 3. Pfade und Sprachwahl
  const up = lang === 'it' ? '' : '../';
  if (up) {
    // srcset steht hier seit dem 14.09.2026 mit drin. Ohne sie bekam das
    // <picture> des Standbildes in /de/ und /en/ ein src mit "../" und ein
    // srcset ohne -- der Browser nimmt dann die AVIF-Quelle, findet sie
    // nicht, und zeigt gar nichts. Der <img> dahinter rettet das nicht: Wer
    // einmal eine passende <source> gewaehlt hat, geht nicht zurueck.
    h = h.replace(/(href|src|srcset|content|poster)="assets\//g, `$1="${up}assets/`);
    // Die Importmap steht als JSON im HTML — sie wird von der Regel oben nicht
    // erfasst und muss eigens umgeschrieben werden, sonst fehlt three.js in /de/.
    h = h.replace(/"\.\/assets\//g, `"${up}assets/`);
    /* Die Rechtsseite traegt die Sprache in der Adresse (23.09.2026):
       legal.html liegt nur einmal da und faellt ohne Hinweis auf
       Italienisch zurueck -- aus /de/ heraus waere das die falsche
       Fassung, ausgerechnet bei AGB und Datenschutz. */
    h = h.replace(/href="legal\.html(#[a-z]+)?"/g,
                  (m, anker) => `href="${up}legal.html?lang=${lang}${anker || ''}"`);
    // Unterseiten kennen die Sprache nur über ?lang= — sonst öffnen sie
    // italienisch, egal von welcher Sprachseite man kommt.
    h = h.replace(/href="pakete\.html#([a-z]+)"/g, `href="${up}pakete.html?lang=${lang}#$1"`);
    h = h.replace(/href="world\.html/g, `href="${up}world.html`);
    // Videopfade gehören ebenfalls eine Ebene höher — sonst suchen /de/ und
    // /en/ die Dateien in einem Unterordner, den es nicht gibt.
    h = h.replace(/(data-src|src|href|poster)="video\//g, `$1="${up}video/`);
    // data-img der Tiefenkarten wird von der href/src-Regel nicht erfasst
    h = h.replace(/data-img="assets\//g, `data-img="${up}assets/`);
    h = h.replace(/href="assets\/img\//g, `href="${up}assets/img/`);
    h = h.replace(/href="index\.html"/g, `href="${up}"`);
    h = h.replace(/href="#top"/g, 'href="#top"');
  }
  /* --------------------------------------------------------------------------
     Verweise von einer Unterseite zurueck auf die Startseite.

     Auf der Startseite selbst waere das falsch: dort ist "#work" ein Sprung
     innerhalb derselben Seite. Von der Preisseite aus ist es ein Verweis auf
     eine andere Seite und braucht deren Adresse davor — sonst sucht der
     Browser den Abschnitt auf der Preisseite und findet nichts.

     Deshalb absolut und nicht relativ: /de/preise.html und /de/index.html
     liegen zwar im selben Ordner, aber die italienische Fassung liegt eine
     Ebene hoeher als ihre Startseite nicht — eine Regel fuer beide Faelle
     gibt es nur ueber die volle Adresse.
     -------------------------------------------------------------------------- */
  if (seite.heim) {
    const heim = `/${LANGS[lang]}`;
    /* Die Muster fassen auch eine bereits gebaute Seite: prezzi.html ist
       Quelle und italienisches Ziel zugleich, steht beim naechsten Lauf also
       schon umgeschrieben da. Wer hier nur "index.html#" abfaengt, baut beim
       zweiten Durchgang eine deutsche Seite, deren Verweise auf die
       italienische Startseite zeigen — und merkt es nie, weil die Adresse
       ja funktioniert. */
    h = h.replace(/href="(?:index\.html|\/(?:de\/|en\/)?)#/g, `href="${heim}#`);
    h = h.replace(/href="\.\.\/"/g, `href="${heim}"`);
    // Der Baukasten liegt im Wurzelverzeichnis und kennt die Sprache nur
    // ueber ?lang= — ohne das oeffnet er italienisch, egal woher man kommt.
  }

  /* --------------------------------------------------------------------------
     Der Konfigurator kennt die Sprache nur ueber ?lang=.

     Ohne den Zusatz faellt bedarf.php auf Italienisch zurueck — die Knoepfe
     auf /de/ und /en/ schickten deutsche und englische Besucher also in den
     italienischen Konfigurator. Das galt fuer die Startseite seit dem Tag,
     an dem der Bedarfsweg dort steht, und ist niemandem aufgefallen, weil
     die Seite ja aufging.

     Deshalb steht die Regel hier und nicht im Block fuer Unterseiten: Sie
     gilt fuer jede Seite, die auf den Konfigurator zeigt.
     -------------------------------------------------------------------------- */
  /* Der Showroom haengt zusaetzlich &start=1 an: Wer aus dem Raum kommt, soll
     im Konfigurator nicht noch einmal auf "los" druecken muessen. Die Regel
     darunter faengt diese Form nicht, weil sie am Anfuehrungszeichen endet --
     deshalb hier eine eigene, und sie muss VOR der allgemeinen stehen. */
  h = h.replace(/href="(?:\/|\.\.\/)?bedarf\.php\?lang=[a-z]{2}&amp;start=1"/g, `href="/bedarf.php?lang=${lang}&amp;start=1"`);
  h = h.replace(/href="(?:\/|\.\.\/)?bedarf\.php(?:\?lang=[a-z]{2})?"/g, `href="/bedarf.php?lang=${lang}"`);
  /* Der E-Mail-Einstieg (24.09.2026) kennt die Sprache ebenfalls nur ueber
     ?lang= -- und steht auch als Formular-Ziel (action=) im Aufmacher und im
     Kontaktbereich. Ohne diese Regel ginge die deutsche Willkommensmail
     italienisch raus. */
  h = h.replace(/(href|action)="(?:\/|\.\.\/)?zugang\.php(?:\?lang=[a-z]{2})?"/g, (_, attr) => `${attr}="/zugang.php?lang=${lang}"`);
  // Dieselbe Regel fuer die Solo-Hosting-Seite: Auch sie kennt die Sprache
  // nur ueber ?lang= — ohne die Drehung zeigte der deutsche Hosting-Knopf
  // auf die italienische Fassung (so gefunden am 08.09., live).
  h = h.replace(/href="(?:\/|\.\.\/)?hosting\.php(?:\?lang=[a-z]{2})?"/g, `href="/hosting.php?lang=${lang}"`);
  /* --------------------------------------------------------------------------
     DIE RUFNUMMER JE SPRACHE

     Wer aus Italien anruft, soll eine italienische Nummer waehlen, und wer aus
     Deutschland anruft, eine deutsche. Eine Auslandsnummer auf der Seite ist
     eine Huerde, die man nicht sieht: Angerufen wird dann einfach nicht.

     Steht hier null, erscheint keine Zeile. Das ist Absicht und der wichtigere
     Teil der Regel: Eine Nummer, die ins Leere klingelt, ist schlechter als
     gar keine — der Anrufer haelt uns dann nicht fuer unerreichbar, sondern
     fuer unzuverlaessig.

     ERST WEG, DANN SETZEN — und die Zeile steht NICHT in index.html.
     index.html ist Vorlage und italienisches Ergebnis zugleich. Beim ersten
     Versuch stand die Zeile in der Vorlage, und der italienische Durchlauf
     (ohne Nummer) hat sie geloescht — aus der Datei, aus der Deutsch und
     Englisch danach lesen. Ergebnis: die Nummer war ueberall weg, auch auf
     der deutschen Seite, und beim naechsten Lauf war sie nicht wieder
     herstellbar. Deshalb wird die Zeile hier eingesetzt statt gefuellt, und
     vorher immer entfernt: Dann ist es egal, was in der Datei steht.

     Die italienische Nummer +39 0922 1795963 liegt bei Sonetel noch in der
     Pruefung. Sobald sie geschaltet ist und auf die deutsche weiterleitet,
     hier eintragen — mehr ist nicht noetig.
     -------------------------------------------------------------------------- */
  /* 26.09.2026, Uwe: die deutsche Nummer auf allen drei Sprachen. Sie
     klingelt wirklich (Manuela nimmt ab) -- eine Auslandsnummer ist eine
     Huerde, aber keine Nummer war auf der italienischen Seite die groessere:
     Gemessen gab es dort gar keinen Anruf-Link. Kommt die italienische
     (+39 0922 1795963, Sonetel), wird sie hier bei it/en eingetragen. */
  const TELEFON = {
    it: '+49 30 4397926082',
    de: '+49 30 4397926082',
    en: '+49 30 4397926082',
  };
  /* Genau EINE Zeile, bis zu ihrem eigenen </div> -- sie enthaelt kein
     weiteres div. Die fruehere erste Regel („…</div>\s*</div>“) lief ueber
     die Zeile hinaus bis zum naechsten Doppel-Schluss und loeschte auf der
     italienischen Vorlage den ganzen Kontaktblock samt E-Mail-Formular.
     Aufgefallen erst am 26.09.2026, als Italien eine Nummer bekam: Solange
     die Vorlage keine Zeile trug, fand die Regel nie etwas. */
  h = h.replace(/\s*<div class="kontakt-telzeile">(?:(?!<\/div>)[\s\S])*<\/div>/g, '');
  const telNr = TELEFON[lang] ?? null;
  if (telNr) {
    const wort = get(lang, 'contact.dt5') || 'Telefon';
    h = h.replace(/(<div><dt data-i18n="contact\.dt4">[\s\S]*?<\/div>)/,
      `$1\n        <div class="kontakt-telzeile"><dt data-i18n="contact.dt5">${esc(wort)}</dt>`
      + `<dd class="kontakt-tel"><a href="tel:${telNr.replace(/[^0-9+]/g, '')}">${esc(telNr)}</a></dd></div>`);
  }
  /* --------------------------------------------------------------------------
     Verweise auf die Preisseite.

     Sie heisst in jeder Sprache anders — prezzi.html, preise.html,
     pricing.html — und liegt jeweils neben der Startseite derselben Sprache.
     Deshalb genuegt der blosse Dateiname, und deshalb steht die Regel hier
     und nicht in einem der drei Seitenbloecke: Wer im Quelltext irgendeine
     der drei Schreibweisen verlinkt, bekommt die richtige.
     -------------------------------------------------------------------------- */
  /* Verweise auf die Landeseiten (26.09.2026): In der Vorlage stehen die
     italienischen Dateinamen -- die sind in index.html zugleich Ergebnis und
     bleiben darum stehen. Jede Sprache bekommt ihren eigenen. */
  for (const ls of LANDESEITEN) {
    const it = ls.ziele.it, ziel = ls.ziele[lang].split('/').pop();
    h = h.split(`href="${it}"`).join(`href="${ziel}"`);
  }
  const preisseite = SEITEN.find((x) => x.quelle === 'prezzi.html');
  if (preisseite) {
    const datei = preisseite.ziele[lang].split('/').pop();
    h = h.replace(/href="(?:prezzi|preise|pricing)\.html"/g, `href="${datei}"`);
  }
  /* --------------------------------------------------------------------------
     Waehrungsschreibweise der Preistabelle.

     Die Quelle ist italienisch und schreibt "345 – 400 €" — Zeichen hinten,
     wie im Deutschen und Italienischen ueblich. Englisch schreibt es
     andersherum: "€345 – 400". Die Kettenpruefung prueft genau das, und sie
     hat am 14.09.2026 gerissen: seit dem Commit vom 13.09. lag die englische
     Seite in italienischer Schreibweise oben, und weil eine gerissene Kette
     den Deploy anhaelt, ist seitdem ueberhaupt nichts mehr live gegangen.
     Ein Zeichen an der falschen Stelle hat die ganze Auslieferung angehalten.

     Deshalb steht die Regel jetzt hier und nicht in den Uebersetzungen: Sie
     gilt fuer jede Zelle mit data-preis, auch fuer die, die morgen dazukommt.
     -------------------------------------------------------------------------- */
  if (lang === 'en') {
    h = h.replace(/(data-preis="[^"]+">)([0-9.,]+(?:\s*[–-]\s*[0-9.,]+)?)\s*€(<)/g,
      (_, vor, zahlen, nach) => vor + '€' + zahlen.replace(/\./g, ',') + nach);
  }

  /* --------------------------------------------------------------------------
     Verweise auf den Produktkonfigurator.

     Dieselbe Mechanik wie bei der Preisseite: Er heisst in jeder Sprache
     anders -- tavolo.html, tisch.html, table.html -- und liegt jeweils neben
     der Startseite derselben Sprache. Wer im Quelltext irgendeine der drei
     Schreibweisen verlinkt, bekommt die richtige.

     Die Regel fasst auch die Form mit fuehrendem Schraegstrich: Bis zum
     16.09.2026 stand die Seite als einzelne deutsche Fassung unter
     /tisch.html und war von der Startseite genau so verlinkt.
     -------------------------------------------------------------------------- */
  const tischseite = SEITEN.find((x) => x.quelle === 'tavolo.html');
  if (tischseite) {
    const datei = tischseite.ziele[lang].split('/').pop();
    h = h.replace(/href="\/?(?:tavolo|tisch|table)\.html"/g, `href="${datei}"`);
  }

  // Die Technikseite (24.09.2026): tecnica.html, technik.html, technology.html
  const technikseite = SEITEN.find((x) => x.quelle === 'tecnica.html');
  if (technikseite) {
    const datei = technikseite.ziele[lang].split('/').pop();
    h = h.replace(/href="(?:tecnica|technik|technology)\.html/g, `href="${datei}`);
  }

  const betreuungsseite = SEITEN.find((x) => x.quelle === 'assistenza.html');
  if (betreuungsseite) {
    const datei = betreuungsseite.ziele[lang].split('/').pop();
    h = h.replace(/href="(?:assistenza|betreuung|care)\.html"/g, `href="${datei}"`);
  }

  // Muster passt auch auf eine bereits gebaute Seite — sonst erbt der zweite
  // Lauf die Sprachwahl des ersten (index.html ist zugleich Quelle und Ziel).
  h = h.replace(/<div class="lang[^"]*" role="group"[\s\S]*?<\/div>/, langLinks(lang, up, seite));

  // Zum Schluss, damit die Pfade schon eine Ebene hoeher zeigen.
  h = fingerabdruecke(h);

  return h;
}

/**
 * Nach dem Bauen: Zeigt jede Sprachseite auch wirklich auf ihre eigenen
 * Dateien — und gibt es die?
 *
 * Der Anlass steht oben bei der Videoregel: Eine vergessene Umbenennung
 * schickte /de/ und /en/ auf das italienische Video, und niemandem fiel es
 * auf. Ein falscher Pfad soll den Build abbrechen und nicht still
 * hochgeladen werden — hochgeladen wird er nämlich zuverlässig.
 */
function pruefen(h, lang, ziel) {
  const fehler = [];
  const tief = lang === 'it' ? '' : '../';

  for (const m of h.matchAll(/(?:data-src|src|href)="((?:\.\.\/)?(?:video|assets\/img)\/[^"?]*?-([a-z]{2})\.(?:mp4|webp))(?:\?v=[A-Za-z0-9]*)?"/g)) {
    const [, pfad, sprache] = m;
    // Nur Dateien, deren Name auf eine Sprache endet, sind gemeint.
    if (!Object.keys(LANGS).includes(sprache)) { continue; }
    if (sprache !== lang) { fehler.push(`${pfad} gehört zu "${sprache}", die Seite ist "${lang}"`); }
    if (!pfad.startsWith(tief)) { fehler.push(`${pfad} zeigt nicht ${tief ? 'eine Ebene höher' : 'ins Wurzelverzeichnis'}`); }
    if (!existsSync(pfad.replace(/^\.\.\//, ''))) { fehler.push(`${pfad} gibt es auf der Platte nicht`); }
  }
  // Zusaetzlich: jede relative Referenz auf video/ oder assets/ muss auf der
  // richtigen Ebene liegen. Die Regel oben prueft nur Dateien mit Sprachkuerzel
  // im Namen — auftakt.mp4 hat keins und rutschte deshalb ungeprueft nach /de/.
  for (const m of h.matchAll(/(?:data-src|src|href|srcset|poster|content)="((?:\.\.\/)?(?:video|assets)\/[^"?]+?)(?:\?v=[A-Za-z0-9]*)?"/g)) {
    const pfad = m[1];
    if (!pfad.startsWith(tief)) { fehler.push(`${pfad} zeigt nicht ${tief ? 'eine Ebene hoeher' : 'ins Wurzelverzeichnis'}`); }
    if (!existsSync(pfad.replace(/^\.\.\//, ''))) { fehler.push(`${pfad} gibt es auf der Platte nicht`); }
  }

  if (fehler.length) {
    console.error(`\nFEHLER in ${ziel}:`);
    fehler.forEach((f) => console.error('  ' + f));
    console.error('\nNichts wurde hochgeladen. Erst den Pfad richtigstellen.');
    process.exit(1);
  }
}

/* Was die Umschreibregeln nie verlieren duerfen: Formulare, Ueberschriften,
   Abschnitte. Am 26.09.2026 loeschte eine zu gierige Regel den Kontaktblock
   samt E-Mail-Formular -- still, weil jede Pfadpruefung gruen blieb. Weniger
   davon im Ergebnis als in der Quelle heisst: eine Regel frisst Inhalt. */
const TRAGEND = [/<form\b/g, /<section\b/g, /<h[1-3]\b/g, /data-zugang="/g];
function tragendZaehlen(html) { return TRAGEND.map((r) => (html.match(r) || []).length); }

for (const seite of SEITEN) {
  const quelleZahl = tragendZaehlen(readFileSync(seite.quelle, 'utf8'));
  for (const lang of Object.keys(LANGS)) {
    const out = build(lang, seite);
    const ziel = seite.ziele[lang];
    const ist = tragendZaehlen(out);
    if (ist.some((n, i) => n < quelleZahl[i])) {
      console.error(`\nFEHLER in ${ziel}: Inhalt verloren (Formulare/Abschnitte/Ueberschriften/Zugaenge ${quelleZahl.join('/')} → ${ist.join('/')}).`);
      console.error('Eine Umschreibregel frisst Inhalt. Nichts wurde hochgeladen.');
      process.exit(1);
    }
    pruefen(out, lang, ziel);
    if (lang !== 'it') { mkdirSync(lang, { recursive: true }); }
    writeFileSync(ziel, out);
    console.log(`geschrieben: ${ziel}${lang === 'it' ? ' (Italienisch, x-default)' : ''}`);
  }
}

/* --------------------------------------------------------------------------
   legal.html STEMPELN (26.09.2026)

   Die Seite liegt nur einmal da und wird nicht gebaut; sie laedt legal-it.js
   und i18n-it.js mit einem ?v= und holt die anderen Sprachen mit DEMSELBEN
   ?v= nach (app.js tauscht nur das Sprachkuerzel). Dateien mit ?v= haelt der
   Browser ein Jahr (.htaccess, FESTGENAGELT). Der Stempel stand von Hand da,
   war schon veraltet, und der neue Absatz zur Besucherzaehlung waere bei
   jedem, der die Seite kannte, ein Jahr lang nicht angekommen.

   Deshalb ein Stempel aus ALLEN drei Fassungen zusammen: Aendert sich eine
   davon, aendert sich die Adresse fuer alle drei.
   -------------------------------------------------------------------------- */
{
  const gemeinsam = (name) => createHash('sha1')
    .update(['it', 'de', 'en'].map((l) => existsSync(`assets/js/${name}-${l}.js`) ? readFileSync(`assets/js/${name}-${l}.js`) : '').join('\n'))
    .digest('hex').slice(0, 8);
  const vorher = readFileSync('legal.html', 'utf8');
  const nachher = vorher.replace(/(assets\/js\/(i18n|legal)-it\.js)(?:\?v=[A-Za-z0-9]*)?/g, (m, pfad, name) => `${pfad}?v=${gemeinsam(name)}`);
  if (nachher !== vorher) { writeFileSync('legal.html', nachher); console.log('gestempelt: legal.html'); }
}


/* --------------------------------------------------------------------------
   LANDESEITEN (26.09.2026): Provinz, Branchen, Ratgeber

   Inhalt aus seiten/landeseiten.mjs, Gerüst aus der eben gebauten Preisseite
   derselben Sprache -- Kopf, Fuß, Sprachwahl, Stil und Zählpixel sind damit
   dieselben wie überall, ohne zweite Vorlage, die auseinanderläuft. Nur Kopf-
   daten, Sprachwahl und <main> werden ersetzt. Keine data-i18n im Inhalt:
   Die Seite ist je Sprache fertig, app.js fasst Titel und Text nicht an
   (data-title-key="keiner").
   -------------------------------------------------------------------------- */
{
  const preis = SEITEN.find((x) => x.quelle === 'prezzi.html');
  const HL = { it: 'it_IT', de: 'de_DE', en: 'en_GB' };
  const sitemapEintraege = [];
  const rel = (von, zielPfad) => (von === 'it' ? './' : '../') + zielPfad;
  const gleichesVerz = (zielPfad) => zielPfad.split('/').pop();

  for (const ls of LANDESEITEN) {
    for (const lang of Object.keys(LANGS)) {
      const t = ls[lang], W = LANDESEITEN_WORTE[lang];
      let h = readFileSync(preis.ziele[lang], 'utf8');
      const url = `${BASE}/${ls.ziele[lang]}`;

      h = h.replace(/<html([^>]*)>/, (m, a) => `<html${a.replace(/\s+data-(?:title|desc)-key="[^"]*"/g, '')} data-title-key="keiner" data-desc-key="keiner">`);
      h = h.replace(/<title>[\s\S]*?<\/title>/, `<title>${esc(t.titel)}</title>`);
      h = h.replace(/<meta name="description" content="[^"]*">/, `<meta name="description" content="${escAttr(t.desc)}">`);
      h = h.replace(/<link rel="canonical" href="[^"]*">/, `<link rel="canonical" href="${url}">`);
      h = h.replace(/\s*<link rel="alternate" hreflang="[^"]*" href="[^"]*">/g, '');
      h = h.replace(/(<link rel="canonical" href="[^"]*">)/, `$1\n` + ['it', 'de', 'en'].map((l) =>
        `<link rel="alternate" hreflang="${l}" href="${BASE}/${ls.ziele[l]}">`).join('\n')
        + `\n<link rel="alternate" hreflang="x-default" href="${BASE}/${ls.ziele.it}">`);
      h = h.replace(/<meta property="og:url" content="[^"]*">/, `<meta property="og:url" content="${url}">`);
      h = h.replace(/<meta property="og:title" content="[^"]*">/, `<meta property="og:title" content="${escAttr(t.titel)}">`);
      h = h.replace(/<meta property="og:description" content="[^"]*">/, `<meta property="og:description" content="${escAttr(t.desc)}">`);
      h = h.replace(/<meta name="twitter:title" content="[^"]*">/, `<meta name="twitter:title" content="${escAttr(t.titel)}">`);
      h = h.replace(/<meta name="twitter:description" content="[^"]*">/, `<meta name="twitter:description" content="${escAttr(t.desc)}">`);
      h = h.replace(/\s*<script type="application\/ld\+json">[\s\S]*?<\/script>/g, '');
      h = h.replace(/\s*<script[^>]*preise-live\.js[^>]*><\/script>/g, '');

      const ld = [
        ls.art === 'Article'
          ? { '@context': 'https://schema.org', '@type': 'Article', headline: t.h1, description: t.desc, inLanguage: lang, url,
              author: { '@id': `${BASE}/#uwe-vetter` }, publisher: { '@id': `${BASE}/#studio` }, dateModified: LANDESEITEN_STAND }
          : { '@context': 'https://schema.org', '@type': 'Service', name: t.h1, description: t.desc, inLanguage: lang, url,
              provider: { '@id': `${BASE}/#studio` }, areaServed: [{ '@type': 'AdministrativeArea', name: 'Provincia di Agrigento' }, { '@type': 'AdministrativeArea', name: 'Sicilia' }] },
        { '@context': 'https://schema.org', '@type': 'FAQPage', inLanguage: lang,
          mainEntity: t.faq.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })) },
      ];
      h = h.replace('</head>', ld.map((j) => `<script type="application/ld+json">${JSON.stringify(j)}</script>`).join('\n') + '\n</head>');

      // Sprachwahl: auf dieselbe Landeseite in der anderen Sprache
      h = h.replace(/(<div class="lang lang--links"[\s\S]*?<\/div>)/, (block) =>
        block.replace(/href="[^"]*"(\s+hreflang="(it|de|en)")/g, (m, rest, l) => `href="${rel(lang, ls.ziele[l])}"${rest}`));

      const blocchi = t.blocchi.map(([kopf, text, liste]) => `
      <h2>${esc(kopf)}</h2>
      ${text ? `<p>${esc(text)}</p>` : ''}${liste ? `<ul class="landeseite__liste">${liste.map((x) => `<li>${esc(x)}</li>`).join('')}</ul>` : ''}`).join('\n');
      const auch = Object.keys(LANDESEITEN_KURZ).filter((k) => k !== ls.schluessel)
        .map((k) => { const z = LANDESEITEN.find((x) => x.schluessel === k); return `<a href="${gleichesVerz(z.ziele[lang])}">${esc(LANDESEITEN_KURZ[k][lang])}</a>`; }).join('');
      const verweise = [
        t.demo ? `<a class="btn" href="./${t.demo}">${esc(W.demo)}</a>` : '',
        t.guida ? `<a class="btn" href="${gleichesVerz(LANDESEITEN.find((x) => x.schluessel === t.guida).ziele[lang])}">${esc(W.guida)}</a>` : '',
        t.branche ? `<a class="btn" href="${gleichesVerz(LANDESEITEN.find((x) => x.schluessel === t.branche).ziele[lang])}">${esc(W.branche)}</a>` : '',
      ].filter(Boolean).join('\n        ');

      const main = `<main id="inhalt" class="preisseite landeseite">
  <section class="section preis-kopf">
    <div class="wrap">
      <p class="eyebrow">${esc(t.kicker)}</p>
      <h1>${esc(t.h1)}</h1>
      <p class="preis-lead">${esc(t.lead)}</p>
    </div>
  </section>
  <section class="section">
    <div class="wrap"><div class="landeseite__text">${blocchi}
      ${verweise ? `<p class="landeseite__verweise">\n        ${verweise}\n      </p>` : ''}
      <div class="antwort framed landeseite__cta">
        <p class="antwort__titel">${esc(W.cta_titel)}</p>
        <p class="antwort__text">${esc(W.cta_text)}</p>
        <p class="landeseite__knoepfe"><a class="btn btn--primary" href="./#contact">${esc(W.cta_knopf)}</a>
          <a class="btn" href="${gleichesVerz(preis.ziele[lang])}">${esc(W.preise)}</a></p>
      </div>
      <h2>${esc(W.faq)}</h2>
      ${t.faq.map(([q, a]) => `<details class="landeseite__faq"><summary>${esc(q)}</summary><p>${esc(a)}</p></details>`).join('\n      ')}
      <nav class="landeseite__auch" aria-label="${escAttr(W.auch)}"><span>${esc(W.auch)}:</span>${auch}</nav>
    </div></div>
  </section>
</main>`;
      h = h.replace(/<main[\s\S]*?<\/main>/, main);
      h = h.replace(/<body class="seite-preise">/, '<body class="seite-preise seite-landeseite">');

      const ziel = ls.ziele[lang];
      if (lang !== 'it') { mkdirSync(lang, { recursive: true }); }
      pruefen(h, lang, ziel);
      writeFileSync(ziel, h);
      console.log(`geschrieben: ${ziel} (Landeseite)`);
    }
    sitemapEintraege.push(ls);
  }

  /* Sitemap: nur der Abschnitt zwischen den Marken gehört dem Build; der
     Rest bleibt von Hand gepflegt, wie bisher. */
  const ANF = '<!-- landeseiten:anfang (build.mjs) -->', END = '<!-- landeseiten:ende -->';
  let sm = readFileSync('sitemap.xml', 'utf8');
  if (!sm.includes(ANF)) { sm = sm.replace('</urlset>', `  ${ANF}\n  ${END}\n</urlset>`); }
  const eintraege = sitemapEintraege.flatMap((ls) => ['it', 'de', 'en'].map((l) => `  <url>
    <loc>${BASE}/${ls.ziele[l]}</loc>
${['it', 'de', 'en'].map((x) => `    <xhtml:link rel="alternate" hreflang="${x}" href="${BASE}/${ls.ziele[x]}"/>`).join('\n')}
    <xhtml:link rel="alternate" hreflang="x-default" href="${BASE}/${ls.ziele.it}"/>
    <lastmod>${LANDESEITEN_STAND}</lastmod><priority>0.7</priority>
  </url>`)).join('\n');
  // Schnitt ohne RegExp: Die Marke enthält Klammern, die ein Muster missverstünde.
  const vor = sm.slice(0, sm.indexOf(ANF) + ANF.length), nach = sm.slice(sm.indexOf(END));
  const neu = `${vor}\n${eintraege}\n  ${nach}`;
  if (neu !== readFileSync('sitemap.xml', 'utf8')) { writeFileSync('sitemap.xml', neu); console.log('geschrieben: sitemap.xml (Landeseiten)'); }
}
