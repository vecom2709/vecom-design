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

const BASE = 'https://vecom-design.it';
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
];

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
  return h.replace(
    /((?:href|src|data-src)=")((?:\.\.\/)?(?:assets\/(?:css|js|img)|video)\/[A-Za-z0-9._\/-]+\.(?:css|js|mp4|webm|webp|png|jpg|svg))(\?v=[A-Za-z0-9]*)?(")/g,
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
      if (typeof v !== 'string' || out.includes(`${a}="`)) return;
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
    h = h.replace(/(href|src|content|poster)="assets\//g, `$1="${up}assets/`);
    // Die Importmap steht als JSON im HTML — sie wird von der Regel oben nicht
    // erfasst und muss eigens umgeschrieben werden, sonst fehlt three.js in /de/.
    h = h.replace(/"\.\/assets\//g, `"${up}assets/`);
    h = h.replace(/href="legal\.html/g, `href="${up}legal.html`);
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
  h = h.replace(/href="(?:\/|\.\.\/)?bedarf\.php(?:\?lang=[a-z]{2})?"/g, `href="/bedarf.php?lang=${lang}"`);
  /* --------------------------------------------------------------------------
     Verweise auf die Preisseite.

     Sie heisst in jeder Sprache anders — prezzi.html, preise.html,
     pricing.html — und liegt jeweils neben der Startseite derselben Sprache.
     Deshalb genuegt der blosse Dateiname, und deshalb steht die Regel hier
     und nicht in einem der drei Seitenbloecke: Wer im Quelltext irgendeine
     der drei Schreibweisen verlinkt, bekommt die richtige.
     -------------------------------------------------------------------------- */
  const preisseite = SEITEN.find((x) => x.quelle === 'prezzi.html');
  if (preisseite) {
    const datei = preisseite.ziele[lang].split('/').pop();
    h = h.replace(/href="(?:prezzi|preise|pricing)\.html"/g, `href="${datei}"`);
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
  for (const m of h.matchAll(/(?:data-src|src|href|poster|content)="((?:\.\.\/)?(?:video|assets)\/[^"?]+?)(?:\?v=[A-Za-z0-9]*)?"/g)) {
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

for (const seite of SEITEN) {
  for (const lang of Object.keys(LANGS)) {
    const out = build(lang, seite);
    const ziel = seite.ziele[lang];
    pruefen(out, lang, ziel);
    if (lang !== 'it') { mkdirSync(lang, { recursive: true }); }
    writeFileSync(ziel, out);
    console.log(`geschrieben: ${ziel}${lang === 'it' ? ' (Italienisch, x-default)' : ''}`);
  }
}
