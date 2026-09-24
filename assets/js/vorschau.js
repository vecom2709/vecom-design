/* ==========================================================================
   vorschau.js — „Ihre Seite in 30 Sekunden" (N1, 24.09.2026).

   Der Besucher gibt Betriebsname und Branche ein und sieht sofort eine Skizze
   seiner künftigen Startseite auf Laptop und Telefon — im selben Fotorender
   wie die Fallstudien. Die Skizze ist echtes HTML, per Homographie in die
   Bildschirmflächen gelegt (dieselbe Rechnung wie arbeiten.js): scharf in
   jeder Größe, und der Name steht wirklich darin.

   Ehrlich bleiben: Es ist eine automatisch gesetzte Skizze aus Bild, Name und
   Branche, kein Entwurf. So steht es auch darunter. Der Weg weiter führt über
   dieselbe E-Mail-Adresse ins Dashboard (Quelle „vorschau").

   Nutzereingaben kommen nur über textContent ins Dokument, nie über HTML.
   ========================================================================== */
const L = ['it', 'de', 'en'].includes((document.documentElement.lang || 'it').slice(0, 2))
  ? (document.documentElement.lang || 'it').slice(0, 2) : 'it';
const IMG = new URL('../img/', import.meta.url).href;
const SZENE = `${IMG}arbeiten/cavaleri/`;
const SEITE = { laptop: [1440, 824], telefon: [390, 727] };
/* Der Laptop im Foto zeigt über der Seite eine Browserleiste — mit der
   Adresse der Kundenseite, die für das Foto gerendert wurde. Deshalb deckt
   die Skizze den ganzen Bildschirm ab (Ecken „laptop" statt „laptop_seite")
   und bringt eine eigene Leiste mit der Adresse des Besuchers mit.
   74 px = Abstand der beiden Oberkanten im Foto (43,6 von 490,4 px) auf 824. */
const LEISTE = 74;
const TLD = { it: '.it', de: '.de', en: '.com' }[L];
const domain = (name) => name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  .replace(/ß/g, 'ss').replace(/&/g, '-').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30) + TLD;

/* Bild, Akzentfarbe und Texte je Branche. Die Bilder sind die Cycles-Renders
   der Branchen-Demos — dieselbe Welt, die der Besucher dort schon gesehen hat. */
const BRANCHEN = {
  restaurant: { bild: 'erlebnis/branchen/gastro-weiss.webp', akzent: '#d4a64e' },
  friseur:    { bild: 'erlebnis/haar/poster.webp', akzent: '#d9a59a' },
  autohaus:   { bild: 'erlebnis/branchen/auto-karmin.webp', akzent: '#c9ccd1' },
  kueche:     { bild: 'erlebnis/branchen/kueche-modern.webp', akzent: '#c8a27a' },
  juwelier:   { bild: 'erlebnis/branchen/schmuck-gelbgold.webp', akzent: '#e3c27a' },
  weingut:    { bild: 'erlebnis/branchen/wein-rosso.webp', akzent: '#c98b86' },
  spedition:  { bild: 'erlebnis/branchen/lkw-rot.webp', akzent: '#d9d4c9' },
  moebel:     { bild: '3d/tisch/ansicht/gross/VD-T-V180-EIM.webp', akzent: '#caa57a' },
  mode:       { bild: 'erlebnis/branchen/schuh-rose.webp', akzent: '#e0b3a6' },
  immobilien: { bild: 'erlebnis/villa/ruhe-garten-nachmittag.webp', akzent: '#d8c3a0' },
};

/* [Name der Branche, Überschrift ({n} = Betrieb), Unterzeile, Knopf, Navigation] */
const T = {
  de: {
    restaurant: ['Restaurant', 'Ein Abend bei {n}.', 'Frische Küche, ein gedeckter Tisch und Ihr Platz — in zwei Klicks reserviert.', 'Tisch reservieren', ['Speisekarte', 'Über uns', 'Kontakt']],
    friseur: ['Friseursalon', 'Ihre Farbe. Bei {n}.', 'Die Wunschfarbe von allen Seiten ansehen und den Termin gleich dazu buchen.', 'Termin buchen', ['Leistungen', 'Team', 'Kontakt']],
    autohaus: ['Autohaus', '{n}. Ihr nächstes Auto.', 'Lack, Felgen und Ausstattung wählen — und die Probefahrt genau so anfragen.', 'Probefahrt anfragen', ['Fahrzeuge', 'Service', 'Kontakt']],
    kueche: ['Küchenstudio', 'Küchen nach Maß von {n}.', 'Mit Ihren Wandmaßen planen und den Plan direkt mitschicken.', 'Küche planen', ['Küchen', 'Planung', 'Kontakt']],
    juwelier: ['Juwelier', '{n}. Schmuck, der bleibt.', 'Jedes Stück aus der Nähe: Material, Karat, Gravur.', 'Termin vereinbaren', ['Kollektion', 'Atelier', 'Kontakt']],
    weingut: ['Weingut', 'Weine von {n}.', 'Die Kiste öffnet sich, der Wein fließt — bestellt direkt ab Hof.', 'Weine bestellen', ['Weine', 'Weingut', 'Kontakt']],
    spedition: ['Spedition', '{n}. Ihre Ladung, pünktlich.', 'Paletten und Lademeter selbst planen — das Angebot kommt in Minuten.', 'Transport anfragen', ['Leistungen', 'Flotte', 'Kontakt']],
    moebel: ['Möbel & Tischlerei', 'Möbel von {n}.', 'Holz, Maß und Gestell wählen — der Tisch steht sofort im Bild.', 'Tisch gestalten', ['Möbel', 'Werkstatt', 'Kontakt']],
    mode: ['Mode & Schuhe', '{n}. Die neue Kollektion.', 'Jedes Stück in der Hand drehen, jede Farbe echt.', 'Kollektion ansehen', ['Kollektion', 'Marke', 'Kontakt']],
    immobilien: ['Immobilien', '{n}. Häuser zum Betreten.', 'Durch das Haus gehen, bevor es steht — bei Tag und bei Nacht.', 'Besichtigung anfragen', ['Objekte', 'Projekte', 'Kontakt']],
    namePlatzhalter: 'Ihr Betrieb', erstellt: (n) => `Skizze für ${n} erstellt.`,
    logoLokal: 'Ihr Logo bleibt auf Ihrem Gerät — es wird nicht hochgeladen.', logoFehler: 'Bitte ein Bild als PNG, JPG, WebP oder SVG bis 5 MB.',
  },
  it: {
    restaurant: ['Ristorante', 'Una sera da {n}.', 'Cucina fresca, una tavola apparecchiata e il suo posto — prenotato in due clic.', 'Prenotare un tavolo', ['Menù', 'Chi siamo', 'Contatti']],
    friseur: ['Parrucchiere', 'Il suo colore. Da {n}.', 'Vedere il colore desiderato da ogni lato e prenotare subito l’appuntamento.', 'Prenotare', ['Servizi', 'Team', 'Contatti']],
    autohaus: ['Concessionaria', '{n}. La sua prossima auto.', 'Scegliere vernice, cerchi e allestimento — e chiedere il giro di prova così.', 'Giro di prova', ['Veicoli', 'Assistenza', 'Contatti']],
    kueche: ['Cucine', 'Cucine su misura da {n}.', 'Progettare con le misure delle proprie pareti e inviare subito il progetto.', 'Progettare la cucina', ['Cucine', 'Progetto', 'Contatti']],
    juwelier: ['Gioielleria', '{n}. Gioielli che restano.', 'Ogni pezzo da vicino: materiale, carati, incisione.', 'Fissare un appuntamento', ['Collezione', 'Laboratorio', 'Contatti']],
    weingut: ['Cantina', 'I vini di {n}.', 'La cassetta si apre, il vino scende nel bicchiere — ordinato direttamente in cantina.', 'Ordinare i vini', ['Vini', 'Cantina', 'Contatti']],
    spedition: ['Trasporti', '{n}. Il suo carico, puntuale.', 'Pianificare pallet e metri di carico da sé — l’offerta arriva in pochi minuti.', 'Richiedere un trasporto', ['Servizi', 'Flotta', 'Contatti']],
    moebel: ['Mobili & falegnameria', 'I mobili di {n}.', 'Scegliere legno, misura e base — il tavolo è subito nell’immagine.', 'Creare il tavolo', ['Mobili', 'Laboratorio', 'Contatti']],
    mode: ['Moda & scarpe', '{n}. La nuova collezione.', 'Ogni pezzo da girare in mano, ogni colore vero.', 'Vedere la collezione', ['Collezione', 'Marchio', 'Contatti']],
    immobilien: ['Immobili', '{n}. Case da attraversare.', 'Entrare nella casa prima che esista — di giorno e di notte.', 'Richiedere una visita', ['Immobili', 'Progetti', 'Contatti']],
    namePlatzhalter: 'La sua attività', erstellt: (n) => `Bozza per ${n} creata.`,
    logoLokal: 'Il suo logo resta sul suo dispositivo — non viene caricato.', logoFehler: 'Serve un’immagine PNG, JPG, WebP o SVG fino a 5 MB.',
  },
  en: {
    restaurant: ['Restaurant', 'An evening at {n}.', 'Fresh cooking, a table laid and your seat — booked in two clicks.', 'Book a table', ['Menu', 'About', 'Contact']],
    friseur: ['Hair salon', 'Your colour. At {n}.', 'See the colour you want from every side and book the appointment with it.', 'Book now', ['Services', 'Team', 'Contact']],
    autohaus: ['Car dealer', '{n}. Your next car.', 'Choose paint, wheels and trim — and request the test drive exactly so.', 'Request a test drive', ['Vehicles', 'Service', 'Contact']],
    kueche: ['Kitchen studio', 'Made-to-measure kitchens by {n}.', 'Plan to your own wall measurements and send the plan straight away.', 'Plan your kitchen', ['Kitchens', 'Planning', 'Contact']],
    juwelier: ['Jeweller', '{n}. Jewellery that stays.', 'Every piece up close: material, carat, engraving.', 'Book an appointment', ['Collection', 'Atelier', 'Contact']],
    weingut: ['Winery', 'Wines from {n}.', 'The case opens, the wine pours — ordered straight from the estate.', 'Order wines', ['Wines', 'Estate', 'Contact']],
    spedition: ['Haulage', '{n}. Your load, on time.', 'Plan pallets and loading metres yourself — the quote arrives in minutes.', 'Request transport', ['Services', 'Fleet', 'Contact']],
    moebel: ['Furniture & joinery', 'Furniture by {n}.', 'Choose wood, size and base — the table is in the picture at once.', 'Design your table', ['Furniture', 'Workshop', 'Contact']],
    mode: ['Fashion & shoes', '{n}. The new collection.', 'Turn every piece in your hand, every colour true.', 'See the collection', ['Collection', 'Brand', 'Contact']],
    immobilien: ['Real estate', '{n}. Homes to walk through.', 'Walk through the house before it is built — by day and by night.', 'Request a viewing', ['Properties', 'Projects', 'Contact']],
    namePlatzhalter: 'Your business', erstellt: (n) => `Sketch for ${n} created.`,
    logoLokal: 'Your logo stays on your device — it is not uploaded.', logoFehler: 'Please use a PNG, JPG, WebP or SVG image up to 5 MB.',
  },
}[L];

function homographie(w, h, p) {
  const [[x0, y0], [x1, y1], [x2, y2], [x3, y3]] = p;
  const dx1 = x1 - x2, dx2 = x3 - x2, dy1 = y1 - y2, dy2 = y3 - y2;
  const sx = x0 - x1 + x2 - x3, sy = y0 - y1 + y2 - y3;
  const det = dx1 * dy2 - dx2 * dy1;
  const g = (sx * dy2 - dx2 * sy) / det, hh = (dx1 * sy - sx * dy1) / det;
  const a = x1 - x0 + g * x1, b = x3 - x0 + hh * x3, c = x0;
  const d = y1 - y0 + g * y1, e = y3 - y0 + hh * y3, f = y0;
  const m = [a / w, d / w, 0, g / w, b / h, e / h, 0, hh / h, 0, 0, 1, 0, c, f, 0, 1];
  return `matrix3d(${m.map((v) => +v.toFixed(10)).join(',')})`;
}

const el = (tag, klasse, text) => {
  const e = document.createElement(tag);
  if (klasse) e.className = klasse;
  if (text != null) e.textContent = text;
  return e;
};

/* Die Überschrift trägt den Namen kursiv in der Akzentfarbe — die Stelle,
   an der der Besucher sich wiederfindet. */
function ueberschrift(vorlage, name) {
  const h = el('h1', 'vs-h1');
  const teile = vorlage.split('{n}');
  teile.forEach((t, i) => {
    if (t) h.append(document.createTextNode(t));
    if (i < teile.length - 1) h.append(el('em', null, name));
  });
  // Lange Namen: kleiner setzen statt umbrechen zu lassen
  const laenge = vorlage.replace('{n}', name).length;
  h.style.setProperty('--gr', String(Math.max(0.62, Math.min(1, 26 / laenge))));
  return h;
}

/* V4: Das Logo wird nur im Browser gelesen (Object-URL), nie hochgeladen.
   Aus seinen Pixeln kommen drei Entscheidungen, damit es auf dem dunklen
   Kopf der Skizze sofort stimmt:
   - eine Akzentfarbe, wenn das Logo eine kräftige Farbe hat (sonst bleibt
     die der Branche) -- der Moment, in dem die Skizze „seine" wird;
   - dunkle Logos (schwarze Wortmarken) werden hell gesetzt;
   - Logos mit hellem, deckendem Grund (JPG vom Briefkopf) bekommen ein Schild. */
const LOGO_TYPEN = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
const LOGO_MAX = 5 * 1024 * 1024;

function logoLesen(datei) {
  return new Promise((ok, fehler) => {
    if (!datei || !LOGO_TYPEN.includes(datei.type) || datei.size > LOGO_MAX) { fehler(new Error('typ')); return; }
    const url = URL.createObjectURL(datei);
    const img = new Image();
    img.decoding = 'async';
    img.onload = () => {
      const n = 64;
      const c = document.createElement('canvas'); c.width = n; c.height = n;
      const g = c.getContext('2d', { willReadFrequently: true });
      const w0 = img.naturalWidth || 300, h0 = img.naturalHeight || 150;
      const k = Math.min(n / w0, n / h0);
      g.drawImage(img, 0, 0, Math.max(1, w0 * k), Math.max(1, h0 * k));
      const bw = Math.max(1, Math.round(w0 * k)), bh = Math.max(1, Math.round(h0 * k));
      const px = g.getImageData(0, 0, bw, bh).data;
      let deck = 0, hell = 0, sum = 0, farbig = 0, fr = 0, fg = 0, fb = 0, fw = 0;
      for (let i = 0; i < px.length; i += 4) {
        const a = px[i + 3] / 255; if (a < 0.5) continue;
        const r = px[i] / 255, gg = px[i + 1] / 255, b = px[i + 2] / 255;
        const mx = Math.max(r, gg, b), mn = Math.min(r, gg, b), l = (mx + mn) / 2;
        const sat = mx === mn ? 0 : (mx - mn) / (1 - Math.abs(2 * l - 1));
        deck++; sum += 0.2126 * r + 0.7152 * gg + 0.0722 * b;
        if (l > 0.9) hell++;
        if (sat > 0.35 && l > 0.18 && l < 0.85) { const w = sat; farbig++; fr += r * w; fg += gg * w; fb += b * w; fw += w; }
      }
      const ecken = [[0, 0], [bw - 1, 0], [0, bh - 1], [bw - 1, bh - 1]].map(([x, y]) => {
        const i = (y * bw + x) * 4; return px[i + 3] > 240 && px[i] > 225 && px[i + 1] > 225 && px[i + 2] > 225;
      }).filter(Boolean).length;
      const schild = ecken >= 3;
      const mittel = deck ? sum / deck : 1;
      let akzent = null;
      const bunt = fw && farbig / Math.max(1, deck - hell) > 0.06;
      if (bunt) {
        // Auf dem dunklen Kopf lesbar: Helligkeit anheben, Farbton behalten
        let r = fr / fw, gg = fg / fw, b = fb / fw;
        const l = 0.2126 * r + 0.7152 * gg + 0.0722 * b;
        const f = l < 0.42 ? 0.42 / Math.max(0.05, l) : 1;
        // ein Fünftel zum warmen Weiss: kräftig, aber nicht grell
        r = Math.min(1, r * f) * 0.8 + 0.194; gg = Math.min(1, gg * f) * 0.8 + 0.19; b = Math.min(1, b * f) * 0.8 + 0.18;
        akzent = `rgb(${Math.round(r * 255)}, ${Math.round(gg * 255)}, ${Math.round(b * 255)})`;
      }
      // Hell setzen nur, wenn es keine Farbe zu verlieren gibt (schwarze Wortmarke)
      // Dunkle, farbige Logos (tiefrot, marineblau) verlieren sich auf dem dunklen Kopf -> Schild
      ok({ url, akzent, klasse: (schild || (bunt && mittel < 0.24)) ? 'vs-logo--schild' : (!bunt && mittel < 0.32 ? 'vs-logo--hell' : '') });
    };
    img.onerror = () => { URL.revokeObjectURL(url); fehler(new Error('bild')); };
    img.src = url;
  });
}

function seite(art, daten) {
  const [bez, titel, lead, knopf, nav] = T[daten.branche];
  const s = el('div', `vs-seite vs-seite--${art}`);
  if (art === 'laptop') {
    const leiste = el('div', 'vs-leiste');
    leiste.append(el('i'), el('i'), el('i'), el('span', 'vs-adresse', domain(daten.name)));
    s.append(leiste);
  }
  s.style.setProperty('--bild', `url("${IMG}${BRANCHEN[daten.branche].bild}")`);
  s.style.setProperty('--akzent', (daten.logo && daten.logo.akzent) || BRANCHEN[daten.branche].akzent);
  const kopf = el('header', 'vs-kopf');
  if (daten.logo) {
    const bild = el('img', `vs-logo ${daten.logo.klasse}`.trim());
    bild.src = daten.logo.url; bild.alt = daten.name;
    kopf.append(bild);
  } else {
    kopf.append(el('span', 'vs-marke', daten.name));
  }
  if (art === 'laptop') {
    const n = el('nav', 'vs-nav'); nav.forEach((x) => n.append(el('span', null, x)));
    kopf.append(n, el('span', 'vs-kopfknopf', knopf));
  } else {
    kopf.append(el('span', 'vs-burger'));
  }
  const held = el('div', 'vs-held');
  held.append(el('p', 'vs-eyebrow', daten.ort ? `${bez} · ${daten.ort}` : bez), ueberschrift(titel, daten.name), el('p', 'vs-lead', lead));
  const k = el('div', 'vs-knoepfe'); k.append(el('span', 'vs-btn', knopf));
  held.append(k);
  s.append(kopf, held);
  return s;
}

const sek = document.getElementById('vorschau');
if (sek) {
  const form = sek.querySelector('[data-vorschau-form]');
  const buehne = sek.querySelector('[data-vorschau-buehne]');
  const innen = sek.querySelector('[data-vorschau-innen]');
  const weiter = sek.querySelector('[data-vorschau-weiter]');
  const status = sek.querySelector('[data-vorschau-status]');
  const schirme = { laptop: sek.querySelector('[data-schirm="laptop"]'), telefon: sek.querySelector('[data-schirm="telefon"]') };
  const auswahl = form.querySelector('select[name="branche"]');
  for (const id of Object.keys(BRANCHEN)) {
    const o = document.createElement('option'); o.value = id; o.textContent = T[id][0]; auswahl.append(o);
  }
  let ecken = null, gezeigt = false, laden = null, logo = null;
  const logoFeld = form.querySelector('[data-vorschau-logo]');
  const logoZeile = form.querySelector('.vorschau__logo');
  const logoChip = form.querySelector('[data-vorschau-logochip]');
  const logoBild = form.querySelector('[data-vorschau-logobild]');
  const logoNote = form.querySelector('[data-vorschau-logonote]');

  const holeEcken = () => laden || (laden = fetch(`${SZENE}ecken.json`).then((r) => r.json()).then((j) => { ecken = j; }));

  /* Wie arbeiten.js: auf schmalen Bildschirmen auf die Geräte schneiden,
     sonst stünden Laptop und Telefon briefmarkengroß in einem Schreibtisch. */
  function massstab() {
    const b = buehne.clientWidth;
    const schmal = b < 640;
    const x0 = schmal ? 590 : 0, x1 = schmal ? 2180 : 2400, y0 = schmal ? 290 : 0, y1 = schmal ? 1240 : 1350;
    const k = b / (x1 - x0);
    buehne.style.height = `${Math.round((y1 - y0) * k)}px`;
    innen.style.transform = `translate(${-x0 * k}px, ${-y0 * k}px) scale(${k})`;
  }

  async function zeigen() {
    const fd = new FormData(form);
    const daten = {
      name: String(fd.get('betrieb') || '').trim().slice(0, 36) || T.namePlatzhalter,
      branche: BRANCHEN[fd.get('branche')] ? String(fd.get('branche')) : 'restaurant',
      ort: String(fd.get('ort') || '').trim().slice(0, 28),
      logo,
    };
    await holeEcken();
    for (const [art, ziel] of Object.entries(schirme)) {
      const [w, h0] = SEITE[art];
      const h = art === 'laptop' ? h0 + LEISTE : h0;
      ziel.style.width = `${w}px`; ziel.style.height = `${h}px`;
      ziel.style.transform = homographie(w, h, ecken[art === 'laptop' ? 'laptop' : 'telefon_seite']);
      ziel.replaceChildren(seite(art, daten));
    }
    buehne.hidden = false; weiter.hidden = false;
    massstab();
    if (status) status.textContent = T.erstellt(daten.name);
    if (!gezeigt) {
      gezeigt = true;
      buehne.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
      try { navigator.sendBeacon && navigator.sendBeacon('/d.php?e=vorschau-gezeigt'); } catch { /* egal */ }
    }
  }

  form.addEventListener('submit', (e) => { e.preventDefault(); zeigen(); });

  if (logoFeld) {
    const zuruecksetzen = () => {
      if (logo) URL.revokeObjectURL(logo.url);
      logo = null; logoFeld.value = ''; logoChip.hidden = true; logoBild.removeAttribute('src');
    };
    logoFeld.addEventListener('change', async () => {
      const datei = logoFeld.files && logoFeld.files[0];
      if (!datei) return;
      try {
        const neu = await logoLesen(datei);
        if (logo) URL.revokeObjectURL(logo.url);
        logo = neu;
        logoBild.src = logo.url; logoChip.hidden = false;
        delete logoZeile.dataset.fehler; logoNote.textContent = T.logoLokal;
        try { navigator.sendBeacon && navigator.sendBeacon('/d.php?e=vorschau-logo'); } catch { /* egal */ }
        if (gezeigt) zeigen();
      } catch {
        zuruecksetzen();
        logoZeile.dataset.fehler = ''; logoNote.textContent = T.logoFehler;
      }
    });
    form.querySelector('[data-vorschau-logoweg]').addEventListener('click', () => {
      zuruecksetzen(); if (gezeigt) zeigen(); logoFeld.focus();
    });
  }
  // Nach dem ersten Zeigen folgt die Skizze der Eingabe
  let takt = 0;
  form.addEventListener('input', () => {
    if (!gezeigt) return;
    clearTimeout(takt); takt = setTimeout(zeigen, 180);
  });
  window.addEventListener('resize', () => { if (!buehne.hidden) massstab(); }, { passive: true });
}
