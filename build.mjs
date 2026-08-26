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
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const BASE = 'https://vecom-design.it';
const LANGS = { it: '', de: 'de/', en: 'en/' };
const LOCALES = { it: 'it_IT', de: 'de_DE', en: 'en_GB' };

globalThis.window = {};
await import('./assets/js/i18n-data.js');
const DICT = globalThis.window.VECOM_I18N;

const get = (lang, path) => path.split('.').reduce((o, k) => (o || {})[k], DICT[lang]);
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const escAttr = (s) => esc(s).replace(/"/g, '&quot;');

const src = readFileSync('index.html', 'utf8');

function hreflang(prefixDepth) {
  return Object.keys(LANGS)
    .map((l) => `<link rel="alternate" hreflang="${l}" href="${BASE}/${LANGS[l]}">`)
    .concat(`<link rel="alternate" hreflang="x-default" href="${BASE}/">`)
    .join('\n');
}

// Sprachwahl als echte Links statt Knöpfe — nur so folgt eine Suchmaschine ihnen.
function langLinks(current, up) {
  const item = (l, label) =>
    l === current
      ? `<span class="lang__current" aria-current="true">${label}</span>`
      : `<a href="${up}${LANGS[l]}" hreflang="${l}" lang="${l}">${label}</a>`;
  return `<div class="lang lang--links" role="group" aria-label="Lingua / Sprache / Language">
        ${item('it', 'IT')}${item('de', 'DE')}${item('en', 'EN')}
      </div>`;
}

function build(lang) {
  let h = src;

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
  const faq = {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: [1, 2, 3, 4, 5, 6, 7, 8].map((n) => ({
      '@type': 'Question',
      name: get(lang, `faq.q${n}`),
      acceptedAnswer: { '@type': 'Answer', text: get(lang, `faq.a${n}`) },
    })),
  };
  // Erst entfernen, dann setzen: index.html ist Quelle und Ziel zugleich —
  // ohne das sammelt sich bei jedem Lauf ein weiteres FAQ-Schema an.
  h = h.replace(/<script type="application\/ld\+json">\s*\{\s*"@context"[^<]*"FAQPage"[\s\S]*?<\/script>\s*/g, '');
  h = h.replace('</head>', `<script type="application/ld+json">\n${JSON.stringify(faq, null, 2)}\n</script>\n</head>`);

  // Das Rundgang-Video hat noch deutsche Titelkarten — deshalb erscheint es
  // vorerst nur auf der deutschen Seite. Sobald es in allen Sprachen vorliegt,
  // diese fünf Zeilen entfernen.
  if (lang !== 'de') {
    h = h.replace(/<figure class="vid framed"[^>]*data-src="[^"]*webseite-erstellen[^"]*"[\s\S]*?<\/figure>\s*/, '');
  }

  // Erklärvideo je Sprache: erklaervideo-it/de/en.mp4
  h = h.replace(/erklaervideo-[a-z]{2}\.mp4/g, `erklaervideo-${lang}.mp4`);

  // 2. Kopfdaten
  const url = `${BASE}/${LANGS[lang]}`;
  h = h.replace(/<html lang="[^"]*"/, `<html lang="${lang}" data-lang-fixed="${lang}"`);
  h = h.replace(/<title>[\s\S]*?<\/title>/, `<title>${esc(get(lang, 'meta.title'))}</title>`);
  h = h.replace(/<meta name="description" content="[^"]*">/, `<meta name="description" content="${escAttr(get(lang, 'meta.desc'))}">`);
  h = h.replace(/<link rel="canonical"[^>]*>/, `<link rel="canonical" href="${url}">`);
  h = h.replace(/<link rel="alternate"[\s\S]*?x-default"[^>]*>/, hreflang());
  h = h.replace(/<meta property="og:url"[^>]*>/, `<meta property="og:url" content="${url}">`);
  h = h.replace(/<meta property="og:title"[^>]*>/, `<meta property="og:title" content="${escAttr(get(lang, 'meta.title'))}">`);
  h = h.replace(/<meta property="og:description"[^>]*>/, `<meta property="og:description" content="${escAttr(get(lang, 'meta.desc'))}">`);
  h = h.replace(/<meta property="og:locale"[^>]*>/, `<meta property="og:locale" content="${LOCALES[lang]}">`);

  // 3. Pfade und Sprachwahl
  const up = lang === 'it' ? '' : '../';
  if (up) {
    h = h.replace(/(href|src|content)="assets\//g, `$1="${up}assets/`);
    // Die Importmap steht als JSON im HTML — sie wird von der Regel oben nicht
    // erfasst und muss eigens umgeschrieben werden, sonst fehlt three.js in /de/.
    h = h.replace(/"\.\/assets\//g, `"${up}assets/`);
    h = h.replace(/href="legal\.html/g, `href="${up}legal.html`);
    h = h.replace(/href="world\.html/g, `href="${up}world.html`);
    // Videopfade gehören ebenfalls eine Ebene höher — sonst suchen /de/ und
    // /en/ die Dateien in einem Unterordner, den es nicht gibt.
    h = h.replace(/data-src="video\//g, `data-src="${up}video/`);
    h = h.replace(/href="index\.html"/g, `href="${up}"`);
    h = h.replace(/href="#top"/g, 'href="#top"');
  }
  // Muster passt auch auf eine bereits gebaute Seite — sonst erbt der zweite
  // Lauf die Sprachwahl des ersten (index.html ist zugleich Quelle und Ziel).
  h = h.replace(/<div class="lang[^"]*" role="group"[\s\S]*?<\/div>/, langLinks(lang, up || './'));

  return h;
}

for (const lang of Object.keys(LANGS)) {
  const out = build(lang);
  if (lang === 'it') {
    writeFileSync('index.html', out);
    console.log('geschrieben: index.html (Italienisch, x-default)');
  } else {
    mkdirSync(lang, { recursive: true });
    writeFileSync(`${lang}/index.html`, out);
    console.log(`geschrieben: ${lang}/index.html`);
  }
}
