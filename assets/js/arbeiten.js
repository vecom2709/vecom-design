/* ==========================================================================
   arbeiten.js — „Umgesetzte Arbeiten" als Fallstudien (Umbau 24.09.2026).

   Uwe: „Mache umgesetzte Arbeiten viel hyperrealistischer" — auf die
   Vorschläge alles Ja:
   R1/R2  Je Projekt ein Fotorender aus Blender (Cycles): markenloser Laptop
          und Telefon in einer Welt, die zur Branche passt.
   R3     Live im Foto: Über dem Bildschirm des Renders liegt die echte
          Kundenseite und scrollt — perspektivisch genau eingesetzt über eine
          Homographie aus den Bildschirmecken, die Blender mitschreibt
          (ecken.json). Darüber der Glanz-Durchgang (dieselbe Aufnahme mit
          dunklem Bildschirm, mix-blend-mode: screen), damit sich das Fenster
          auch auf der laufenden Seite spiegelt.
   R4     Beim ersten Zeigen eines Projekts eine 3-Sekunden-Kamerafahrt auf
          das Foto zu; danach übernimmt das Standbild mit Live-Bildschirm.
   F1–F4  Branche · Aufgabe · Lösung · Ergebnis, gemessene Kennzahlen,
          Vorher/Nachher (nur mit echtem altem Bild) und die Kundenstimme
          (nur, wenn Uwe eine freigegeben hat — stimmen-daten.php).
   Ohne JavaScript steht das Foto mit dem ersten Bildschirm da: Das ist
   keine Notlösung, sondern dasselbe Bild ohne Bewegung.
   ========================================================================== */
const L = (document.documentElement.lang || 'it').slice(0, 2);
const SPRACHE = ['it', 'de', 'en'].includes(L) ? L : 'it';
const BASIS = new URL('../img/arbeiten/', import.meta.url).href;
const STAND = '1';
const BEWEGUNG_AUS = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* Laptop: 1440 x 824 CSS-Pixel Seite (900 minus Browserrahmen 76);
   Telefon: 390 x 727 (844 minus Statusleiste 47 und Adressleiste 70). */
const SEITE = { laptop: [1440, 824], telefon: [390, 727] };

/* vorher: Dateiname eines echten Bildschirmfotos der alten Seite im
   Projektordner (z. B. 'vorher.webp'). Ohne echtes altes Bild kein Vergleich —
   ein nachgestelltes „Vorher" wäre eine erfundene Aussage über den Kunden. */
const PROJEKTE = [
  { id: 'cavaleri', name: 'Cavaleri Srl', url: 'https://cavaleri-trasporti.netlify.app', domain: 'cavaleri-trasporti.netlify.app', firma: /cavaleri/i, vorher: null },
  { id: 'jonika', name: 'Jonika Venturis', url: 'https://jonika-venturis.com', domain: 'jonika-venturis.com', firma: /jonika/i, vorher: null },
  { id: 'mensaena', name: 'Mensaena', url: 'https://mensaena.de', domain: 'mensaena.de', firma: /mensaena/i, vorher: null },
  { id: 'trendonix', name: 'Trendonix', url: 'https://www.trendonix-buecher.de', domain: 'trendonix-buecher.de', firma: /trendonix/i, vorher: null },
];

const T = {
  de: {
    felder: { aufgabe: 'Aufgabe', loesung: 'Lösung', ergebnis: 'Ergebnis' },
    fotoAlt: (n) => `Die Website von ${n} auf Laptop und Telefon, auf einem Schreibtisch`,
    live: 'Live ansehen', vorher: 'Vorher / Nachher', vorherAlt: 'Vorher', nachherAlt: 'Nachher',
    zeigen: 'Projekt zeigen', laeuft: 'Die echte Seite läuft im Bildschirm',
    p: {
      cavaleri: { branche: 'Transport & Logistik · Caltanissetta, seit 1974',
        aufgabe: 'Vier Geschäftsfelder — Transporte, Palettenverteilung, videoüberwachtes Lager, Baustoffhandel —, ohne dass der Kunde suchen muss, wo er richtig ist.',
        loesung: 'Eine Seite mit der eigenen Flotte statt Stockfotos, eine Anfrage für alles, in drei Sprachen für Kunden aus Italien und dem Ausland.',
        ergebnis: 'Ein Ansprechpartner, von der Rampe bis zur Baustelle.',
        zahlen: [['3', 'Sprachen'], ['4', 'Geschäftsfelder auf einer Seite'], ['0,7 MB', 'lädt die ganze Startseite']] },
      jonika: { branche: 'Autorin · Kinderbücher',
        aufgabe: 'Eine Kinderbuchreihe in zwei Bänden und zwei Malbücher so zeigen, dass Kinder neugierig werden und Eltern gern bestellen.',
        loesung: 'Die Seite ist das Dorf aus dem Buch am Abend: Beim Scrollen gehen die Lichter an, und eine Malbuchseite lässt sich gleich im Browser ausmalen.',
        ergebnis: 'Man betritt das Buch, bevor man es aufschlägt.',
        zahlen: [['4', 'Bücher auf einer Seite'], ['2', 'Altersstufen: ab 9 und 4 bis 8'], ['1', 'Malbuchseite zum Ausprobieren']] },
      mensaena: { branche: 'Gemeinnützige Plattform · Nachbarschaftshilfe',
        aufgabe: 'Hilfe anbieten und finden, mit Karte, Kategorien und Terminen — eine große Plattform, die auch Menschen ohne Technikerfahrung bedienen.',
        loesung: 'Große, ruhige Schrift, ein klarer erster Schritt und Datenschutz, der sichtbar ist statt versteckt.',
        ergebnis: 'Komplexer Aufbau, einfache Bedienung.',
        zahlen: [['0 €', 'für alle Nutzer'], ['2', 'Sprachen'], ['0', 'Werbe- und Tracking-Cookies']] },
      trendonix: { branche: 'Verlag · Sachbuchreihe',
        aufgabe: 'Eine dreibändige Reihe und einen Einzeltitel so zeigen, dass man die Welt der Bücher spürt — und sie direkt kaufen kann.',
        loesung: 'Dunkel und golden wie die Umschläge, jeder Band mit eigener Welt, die Belege zu jeder Aussage als Markenzeichen.',
        ergebnis: 'Eine Reihe, die man betreten kann.',
        zahlen: [['3', 'Bände der Reihe'], ['1', 'Einzeltitel'], ['0,8 MB', 'lädt die ganze Startseite']] },
    },
  },
  it: {
    felder: { aufgabe: 'Il compito', loesung: 'La soluzione', ergebnis: 'Il risultato' },
    fotoAlt: (n) => `Il sito di ${n} su laptop e telefono, sopra una scrivania`,
    live: 'Vedere dal vivo', vorher: 'Prima / dopo', vorherAlt: 'Prima', nachherAlt: 'Dopo',
    zeigen: 'Mostrare il progetto', laeuft: 'Il sito vero scorre nello schermo',
    p: {
      cavaleri: { branche: 'Trasporti e logistica · Caltanissetta, dal 1974',
        aufgabe: 'Quattro attività — trasporti, distribuzione pallet, deposito videosorvegliato, ingrosso edile — senza che il cliente debba cercare dove rivolgersi.',
        loesung: 'Una pagina con la flotta vera al posto delle foto di repertorio, una sola richiesta per tutto, in tre lingue per clienti italiani e stranieri.',
        ergebnis: 'Un solo interlocutore, dalla rampa al cantiere.',
        zahlen: [['3', 'lingue'], ['4', 'attività in una pagina'], ['0,7 MB', 'pesa l’intera home']] },
      jonika: { branche: 'Autrice · libri per bambini',
        aufgabe: 'Mostrare una serie per ragazzi in due volumi e due libri da colorare in modo che i bambini si incuriosiscano e i genitori ordinino volentieri.',
        loesung: 'Il sito è il villaggio del libro di sera: scorrendo si accendono le luci, e una pagina da colorare si può colorare subito nel browser.',
        ergebnis: 'Si entra nel libro prima di aprirlo.',
        zahlen: [['4', 'libri in una pagina'], ['2', 'fasce d’età: dai 9 e da 4 a 8'], ['1', 'pagina da colorare da provare']] },
      mensaena: { branche: 'Piattaforma senza scopo di lucro · aiuto tra vicini',
        aufgabe: 'Offrire e trovare aiuto, con mappa, categorie e appuntamenti — una piattaforma grande, usabile anche da chi non è pratico di tecnologia.',
        loesung: 'Caratteri grandi e calmi, un primo passo chiaro e una privacy visibile invece che nascosta.',
        ergebnis: 'Struttura complessa, uso semplice.',
        zahlen: [['0 €', 'per tutti gli utenti'], ['2', 'lingue'], ['0', 'cookie pubblicitari o di tracciamento']] },
      trendonix: { branche: 'Casa editrice · collana di saggistica',
        aufgabe: 'Presentare una collana in tre volumi e un titolo singolo in modo che si senta il mondo dei libri — e si possano comprare subito.',
        loesung: 'Scuro e dorato come le copertine, ogni volume con il suo mondo, le fonti di ogni affermazione come segno distintivo.',
        ergebnis: 'Una collana in cui si può entrare.',
        zahlen: [['3', 'volumi della collana'], ['1', 'titolo singolo'], ['0,8 MB', 'pesa l’intera home']] },
    },
  },
  en: {
    felder: { aufgabe: 'The brief', loesung: 'The solution', ergebnis: 'The result' },
    fotoAlt: (n) => `The ${n} website on a laptop and phone, on a desk`,
    live: 'See it live', vorher: 'Before / after', vorherAlt: 'Before', nachherAlt: 'After',
    zeigen: 'Show project', laeuft: 'The real site runs on the screen',
    p: {
      cavaleri: { branche: 'Transport & logistics · Caltanissetta, since 1974',
        aufgabe: 'Four lines of business — haulage, pallet distribution, video-monitored storage, building supplies — without customers having to search for the right one.',
        loesung: 'One page with the company’s own fleet instead of stock photos, one enquiry for everything, in three languages for customers in Italy and abroad.',
        ergebnis: 'One point of contact, from the loading dock to the building site.',
        zahlen: [['3', 'languages'], ['4', 'lines of business on one page'], ['0.7 MB', 'for the whole home page']] },
      jonika: { branche: 'Author · children’s books',
        aufgabe: 'Show a two-volume children’s series and two colouring books so that children get curious and parents are happy to order.',
        loesung: 'The site is the book’s village at night: the lights come on as you scroll, and a colouring page can be coloured right in the browser.',
        ergebnis: 'You step into the book before you open it.',
        zahlen: [['4', 'books on one page'], ['2', 'age groups: 9+ and 4 to 8'], ['1', 'colouring page to try']] },
      mensaena: { branche: 'Non-profit platform · neighbourhood help',
        aufgabe: 'Offering and finding help, with a map, categories and appointments — a large platform that people without tech experience can use too.',
        loesung: 'Large, calm type, one clear first step, and privacy that is visible instead of hidden.',
        ergebnis: 'Complex structure, simple to use.',
        zahlen: [['€0', 'for every user'], ['2', 'languages'], ['0', 'advertising or tracking cookies']] },
      trendonix: { branche: 'Publisher · non-fiction series',
        aufgabe: 'Present a three-volume series and a standalone title so that you feel the world of the books — and can buy them straight away.',
        loesung: 'Dark and golden like the covers, each volume with its own world, sources for every claim as a signature.',
        ergebnis: 'A series you can step into.',
        zahlen: [['3', 'volumes in the series'], ['1', 'standalone title'], ['0.8 MB', 'for the whole home page']] },
    },
  },
}[SPRACHE];

/* ---------------------------------------------------------------- Homographie
   Bildet das Rechteck (0,0)-(w,h) auf das Viereck p0..p3 ab (oben links,
   oben rechts, unten rechts, unten links) und gibt matrix3d() zurück. */
function homographie(w, h, p) {
  const [[x0, y0], [x1, y1], [x2, y2], [x3, y3]] = p;
  const dx1 = x1 - x2, dx2 = x3 - x2, dy1 = y1 - y2, dy2 = y3 - y2;
  const sx = x0 - x1 + x2 - x3, sy = y0 - y1 + y2 - y3;
  const det = dx1 * dy2 - dx2 * dy1;
  const g = (sx * dy2 - dx2 * sy) / det, hh = (dx1 * sy - sx * dy1) / det;
  const a = x1 - x0 + g * x1, b = x3 - x0 + hh * x3, c = x0;
  const d = y1 - y0 + g * y1, e = y3 - y0 + hh * y3, f = y0;
  // Einheitsquadrat -> Viereck; davor (w,h) -> Einheitsquadrat
  const m = [a / w, d / w, 0, g / w, b / h, e / h, 0, hh / h, 0, 0, 1, 0, c, f, 0, 1];
  return `matrix3d(${m.map((v) => +v.toFixed(10)).join(',')})`;
}

const sek = document.getElementById('work');
const buehne = sek && sek.querySelector('[data-fall-buehne]');
if (buehne) {
  const $ = (s, r = sek) => r.querySelector(s);
  const wahl = $('[data-fall-wahl]');
  const text = $('[data-fall-text]');
  const foto = $('[data-fall-foto]');
  const glanz = $('[data-fall-glanz]');
  const film = $('[data-fall-film]');
  const live = { laptop: $('[data-fall-live="laptop"]'), telefon: $('[data-fall-live="telefon"]') };
  const buehneInnen = $('[data-fall-innen]');
  let aktiv = null, ecken = null, gezeigt = new Set(), stimmen = null;
  const cache = new Map();

  const adr = (p, datei) => `${BASIS}${p.id}/${datei}?s=${STAND}`;

  /* Auf dem Telefon ist die Bühne ~350 px breit; das ganze Foto zeigte die
     Geräte dann briefmarkengroß (Probe 390, 24.09.). Dort wird auf die Geräte
     zugeschnitten: Rahmen um beide Bildschirme plus Luft, im Foto gehalten.
     Film und Glanz liegen in derselben Ebene und bekommen denselben Schnitt. */
  function massstab() {
    if (!ecken) return;
    const bw = buehne.clientWidth, bh = buehne.clientHeight;
    let s = bw / ecken.breite, x0 = 0, y0 = 0;
    if (bw < 640) {
      const pts = [...ecken.laptop, ...ecken.telefon];
      const xs = pts.map((q) => q[0]), ys = pts.map((q) => q[1]);
      const minx = Math.min(...xs), maxx = Math.max(...xs), miny = Math.min(...ys), maxy = Math.max(...ys);
      const w = Math.min(ecken.breite, Math.max((maxx - minx) * 1.14, (maxy - miny) * 1.18 * bw / bh));
      s = bw / w;
      const sichtH = bh / s;
      x0 = Math.min(Math.max((minx + maxx) / 2 - w / 2, 0), ecken.breite - w);
      y0 = Math.min(Math.max((miny + maxy) / 2 - sichtH / 2, 0), ecken.hoehe - sichtH);
    }
    buehneInnen.style.transform = `translate(${-x0 * s}px, ${-y0 * s}px) scale(${s})`;
  }
  new ResizeObserver(massstab).observe(buehne);

  async function eckenHolen(p) {
    if (!cache.has(p.id)) cache.set(p.id, fetch(adr(p, 'ecken.json')).then((r) => r.json()).catch(() => null));
    return cache.get(p.id);
  }

  function liveSetzen(p) {
    for (const [art, el] of Object.entries(live)) {
      const q = ecken && ecken[`${art}_seite`];
      if (!el || !q) { if (el) el.hidden = true; continue; }
      const [w, h] = SEITE[art];
      el.style.width = `${w}px`; el.style.height = `${h}px`;
      el.style.transform = homographie(w, h, q);
      const img = el.querySelector('img');
      img.onload = () => {
        // Scrollweg = Bahnhöhe in Seitenpixeln minus sichtbare Höhe
        const hoehe = img.naturalHeight * (w / img.naturalWidth);
        img.style.setProperty('--weg', `${-Math.max(0, hoehe - h)}px`);
        img.style.animation = 'none'; void img.offsetWidth; img.style.animation = '';
      };
      img.src = adr(p, `${art}-bahn.webp`);
      el.hidden = false;
    }
    // Der Glanz ist schon im Bild auf die Seitenflächen begrenzt (arbeiten-web.py)
    glanz.src = adr(p, 'aus.webp');
  }

  function textZeigen(p) {
    const t = T.p[p.id];
    const zahlen = t.zahlen.map(([w, l]) => `<div><dt>${w}</dt><dd>${l}</dd></div>`).join('');
    text.innerHTML = `
      <p class="studie__branche">${t.branche}</p>
      <h3 class="studie__name">${p.name}</h3>
      <dl class="studie__felder">
        <div><dt>${T.felder.aufgabe}</dt><dd>${t.aufgabe}</dd></div>
        <div><dt>${T.felder.loesung}</dt><dd>${t.loesung}</dd></div>
        <div><dt>${T.felder.ergebnis}</dt><dd class="studie__ergebnis">${t.ergebnis}</dd></div>
      </dl>
      <dl class="studie__zahlen">${zahlen}</dl>
      <figure class="studie__stimme" hidden><blockquote></blockquote><figcaption></figcaption></figure>
      ${p.vorher ? `<p class="studie__links"><button type="button" class="studie__vergleich-knopf" data-fall-vergleich-knopf aria-pressed="false">${T.vorher}</button></p>` : ''}
      <p class="studie__links"><a class="studie__besuch" href="${p.url}" target="_blank" rel="noopener" data-fall-besuch>${T.live} <span>${p.domain}</span></a></p>`;
    // Der Klick zur echten Seite ist die stärkste Aussage über eine Fallstudie
    text.querySelector('[data-fall-besuch]')?.addEventListener('click', () => zaehlen(`arbeit-besuch-${p.id}`));
    text.querySelector('[data-fall-vergleich-knopf]')?.addEventListener('click', (ev) => vergleichUmschalten(p, ev.currentTarget));
    vergleichAus();
    stimmeZeigen(p);
  }

  async function stimmeZeigen(p) {
    if (stimmen === null) {
      stimmen = await fetch(`/stimmen-daten.php?lang=${SPRACHE}`, { cache: 'no-store' })
        .then((r) => (r.ok ? r.json() : null)).then((d) => (d && d.stimmen) || []).catch(() => []);
    }
    const s = stimmen.find((x) => p.firma.test(`${x.firma || ''} ${x.name || ''}`));
    const fig = text.querySelector('.studie__stimme');
    if (!s || !fig || aktiv !== p) return;
    fig.querySelector('blockquote').textContent = s.text;
    fig.querySelector('figcaption').textContent = [s.name, s.firma].filter(Boolean).join(' · ');
    fig.hidden = false;
  }

  /* F3: Schieber über der Bühne. Links die alte Seite, rechts die neue,
     beide als flaches Bildschirmfoto gleicher Breite — ein Vergleich im
     schrägen Foto wäre schwer zu lesen. */
  const vergleich = $('[data-fall-vergleich]');
  function vergleichAus() {
    if (!vergleich) return;
    vergleich.hidden = true;
    text.querySelector('[data-fall-vergleich-knopf]')?.setAttribute('aria-pressed', 'false');
  }
  function vergleichUmschalten(p, knopf) {
    if (!vergleich || !p.vorher) return;
    const an = vergleich.hidden;
    if (!an) { vergleichAus(); return; }
    const [alt, neu] = vergleich.querySelectorAll('img');
    alt.src = adr(p, p.vorher); alt.alt = T.vorherAlt;
    neu.src = adr(p, 'laptop-bahn.webp'); neu.alt = T.nachherAlt;
    const regler = vergleich.querySelector('input');
    regler.value = 50; vergleich.style.setProperty('--teil', '50%');
    vergleich.hidden = false; knopf.setAttribute('aria-pressed', 'true');
    zaehlen(`arbeit-vergleich-${p.id}`);
  }
  vergleich?.querySelector('input')?.addEventListener('input', (e) => vergleich.style.setProperty('--teil', `${e.target.value}%`));

  async function zeigen(p, nutzer) {
    if (aktiv === p) return;
    aktiv = p;
    for (const b of wahl.querySelectorAll('button')) b.setAttribute('aria-pressed', String(b.dataset.id === p.id));
    textZeigen(p);
    buehne.classList.add('ist-wechsel');
    const e = await eckenHolen(p);
    if (aktiv !== p) return;
    // Ohne Bildschirmecken kein Foto dieses Projekts: das alte Bild bleibt
    // stehen, statt eines leeren Rahmens (tritt nur auf, wenn ein Render fehlt)
    if (!e) { buehne.classList.remove('ist-wechsel'); return; }
    ecken = e;
    const neu = new Image(); neu.src = adr(p, 'an.webp');
    try { await neu.decode(); } catch { /* dann eben ohne Vorab */ }
    if (aktiv !== p) return;
    foto.src = neu.src; foto.alt = T.fotoAlt(p.name);
    massstab(); liveSetzen(p);
    // R4: Kamerafahrt beim ersten Zeigen, danach sofort das Foto
    if (!BEWEGUNG_AUS && film && !gezeigt.has(p.id)) {
      gezeigt.add(p.id);
      film.src = adr(p, 'fahrt.mp4'); film.hidden = false; buehne.classList.add('ist-film');
      const ende = () => { buehne.classList.remove('ist-film'); film.hidden = true; };
      film.onended = ende; film.onerror = ende;
      film.play().catch(ende);
    }
    buehne.classList.remove('ist-wechsel');
    if (nutzer) zaehlen(`arbeit-${p.id}`);
  }

  wahl.replaceChildren(...PROJEKTE.map((p) => {
    const b = document.createElement('button');
    b.type = 'button'; b.dataset.id = p.id; b.textContent = p.name; b.setAttribute('aria-pressed', 'false');
    b.addEventListener('click', () => zeigen(p, true));
    return b;
  }));
  // Erst wenn der Abschnitt in Sicht kommt: Foto, Ecken, Kamerafahrt
  const beob = new IntersectionObserver(([e]) => {
    if (!e.isIntersecting) return;
    beob.disconnect(); aktiv = null; zeigen(PROJEKTE[0], false);
  }, { rootMargin: '200px 0px' });
  beob.observe(buehne);
  textZeigen(PROJEKTE[0]); aktiv = null;
  for (const b of wahl.querySelectorAll('button')) b.setAttribute('aria-pressed', String(b.dataset.id === PROJEKTE[0].id));
}

function zaehlen(e) {
  try { navigator.sendBeacon ? navigator.sendBeacon(`/d.php?e=${e}`) : fetch(`/d.php?e=${e}`, { method: 'POST', keepalive: true }); } catch { /* egal */ }
}
