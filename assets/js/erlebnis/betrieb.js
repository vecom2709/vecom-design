/* ==========================================================================
   betrieb.js — „Mein Betrieb ist …" (Vorschlag K3, Umbau 24.09.2026).

   WARUM
   Die Seite zeigte zwölf Demos, aber der Besucher musste selbst herausfinden,
   welche ihn betrifft. Ein Restaurantbesitzer sucht nicht nach „Echtzeit“,
   er sucht sich selbst. Deshalb zuerst die Frage, die jeder sofort
   beantworten kann — die Antwort zeigt das passende Bild, einen Satz über
   das, was seine Kunden erleben würden, und öffnet mit einem Klick genau
   diese Demo in der Galerie darunter (erlebnis.js hängt an den Kacheln).

   Ein Klick oder Überfahren wählt, gerechnet wird nichts: nur das kleine
   Foto (~40 KB), das die Galerie ohnehin lädt.
   ========================================================================== */
const L = (document.documentElement.lang || 'it').slice(0, 2);
const SPRACHE = ['it', 'de', 'en'].includes(L) ? L : 'it';
const B = new URL('../../img/erlebnis/', import.meta.url).href;
const bild = (p) => `${B}${p}`;

/* demo: data-demo der Kachel in der Galerie; bild: -800-Fassung der Kachel */
const BETRIEBE = [
  { id: 'restaurant', demo: 'gastro', bild: 'branchen/gastro-weiss-800.webp' },
  { id: 'friseur', demo: 'salon', bild: 'haar/kachel-800.webp' },
  { id: 'autohaus', demo: 'auto', bild: 'branchen/auto-karmin-800.webp' },
  { id: 'kueche', demo: 'kueche', bild: 'branchen/kueche-nussbaum-800.webp' },
  { id: 'juwelier', demo: 'schmuck', bild: 'branchen/schmuck-gelbgold-800.webp' },
  { id: 'weingut', demo: 'wein', bild: 'branchen/wein-rosso-800.webp' },
  { id: 'spedition', demo: 'lkw', bild: 'branchen/lkw-rot-800.webp' },
  { id: 'moebel', demo: 'tisch', bild: '../3d/tisch/ansicht/klein/VD-T-V180-EIM.webp' },
  { id: 'mode', demo: 'shop', bild: 'branchen/schuh-rose-800.webp' },
  { id: 'immobilien', demo: 'villa', bild: 'villa/ruhe-garten-nachmittag-800.webp' },
];

const TEXTE = {
  de: {
    restaurant: ['Restaurant', 'Der Gast sieht den gedeckten Tisch, das Gericht kommt — und er reserviert gleich.'],
    friseur: ['Friseursalon', 'Die Kundin sieht ihre Wunschfarbe von allen Seiten und bucht den Termin damit.'],
    autohaus: ['Autohaus', 'Lack, Felgen, Innenraum wählen — und die Probefahrt genau so anfragen.'],
    kueche: ['Küchenstudio', 'Die Küche mit den eigenen Wandmaßen planen; der Plan kommt mit der Anfrage.'],
    juwelier: ['Juwelier', 'Die Uhr öffnet sich Teil für Teil, der Ring zeigt jedes Karat.'],
    weingut: ['Weingut', 'Die Kiste öffnet sich, der Wein fließt ins Glas — mit Ihrem Etikett.'],
    spedition: ['Spedition', 'Der Kunde plant seine Ladung selbst: Paletten, Lademeter, Auslastung.'],
    moebel: ['Möbel & Tischlerei', 'Holz, Länge und Gestell wählen — der Tisch steht sofort im Bild.'],
    mode: ['Mode & Schuhe', 'Das Produkt dreht sich in der Hand des Kunden, jede Farbe echt gerechnet.'],
    immobilien: ['Immobilien & Architektur', 'Durch das Haus gehen, bevor es steht — bei Tag und bei Nacht.'],
  },
  it: {
    restaurant: ['Ristorante', 'L’ospite vede la tavola apparecchiata, arriva il piatto — e prenota subito.'],
    friseur: ['Parrucchiere', 'La cliente vede il colore desiderato da ogni lato e prenota con quello.'],
    autohaus: ['Concessionaria', 'Scegliere vernice, cerchi, interni — e chiedere il giro di prova proprio così.'],
    kueche: ['Cucine', 'Progettare la cucina con le misure delle proprie pareti; il progetto arriva con la richiesta.'],
    juwelier: ['Gioielleria', 'L’orologio si apre pezzo per pezzo, l’anello mostra ogni carato.'],
    weingut: ['Cantina', 'La cassetta si apre, il vino scende nel bicchiere — con la sua etichetta.'],
    spedition: ['Trasporti', 'Il cliente pianifica da sé il carico: pallet, metri di carico, saturazione.'],
    moebel: ['Mobili & falegnameria', 'Scegliere legno, lunghezza e base — il tavolo è subito nell’immagine.'],
    mode: ['Moda & scarpe', 'Il prodotto gira nelle mani del cliente, ogni colore calcolato dal vero.'],
    immobilien: ['Immobili & architettura', 'Attraversare la casa prima che esista — di giorno e di notte.'],
  },
  en: {
    restaurant: ['Restaurant', 'The guest sees the table laid, the dish arrives — and books right away.'],
    friseur: ['Hair salon', 'The client sees her chosen colour from every side and books with it.'],
    autohaus: ['Car dealer', 'Choose paint, wheels and interior — and request the test drive exactly so.'],
    kueche: ['Kitchen studio', 'Plan the kitchen to your own wall measurements; the plan comes with the enquiry.'],
    juwelier: ['Jeweller', 'The watch opens part by part, the ring shows every carat.'],
    weingut: ['Winery', 'The case opens, the wine pours into the glass — with your label.'],
    spedition: ['Haulage', 'The customer plans the load: pallets, loading metres, utilisation.'],
    moebel: ['Furniture & joinery', 'Choose wood, length and base — the table is in the picture at once.'],
    mode: ['Fashion & shoes', 'The product turns in the customer’s hand, every colour truly rendered.'],
    immobilien: ['Real estate & architecture', 'Walk through the house before it is built — by day and by night.'],
  },
}[SPRACHE];

const sek = document.getElementById('betrieb');
if (sek) {
  const liste = sek.querySelector('[data-betrieb-liste]');
  const huelle = sek.querySelector('[data-betrieb-bild]');
  const satz = sek.querySelector('[data-betrieb-satz]');
  const oeffnen = sek.querySelector('[data-betrieb-oeffnen]');
  const anfrage = sek.querySelector('[data-betrieb-anfrage]');

  // Zwei gestapelte Bilder: das neue blendet über das alte, nie ein leerer Rahmen
  const a = new Image(), b = new Image();
  for (const x of [a, b]) { x.alt = ''; x.decoding = 'async'; x.width = 800; x.height = 450; huelle.append(x); }
  let oben = a, aktiv = null;
  function zeigen(src) {
    const unten = oben === a ? b : a;
    unten.src = src;
    unten.decode().catch(() => {}).then(() => {
      if (unten.getAttribute('src') !== src) return;
      unten.classList.add('ist-an'); oben.classList.remove('ist-an'); oben = unten;
    });
  }
  function waehlen(e, nutzer) {
    if (aktiv === e) return;
    aktiv = e;
    for (const k of liste.children) k.setAttribute('aria-pressed', String(k.dataset.id === e.id));
    zeigen(bild(e.bild));
    satz.textContent = TEXTE[e.id][1];
    const u = new URL(anfrage.getAttribute('href'), location.href);
    u.searchParams.set('branche', e.id);
    anfrage.href = u.pathname + u.search;
    if (nutzer) zaehlen(`betrieb-${e.id}`);
  }
  for (const e of BETRIEBE) {
    const k = document.createElement('button');
    k.type = 'button'; k.dataset.id = e.id; k.setAttribute('aria-pressed', 'false');
    const n = document.createElement('span'); n.textContent = TEXTE[e.id][0];
    k.append(n);
    k.addEventListener('click', () => waehlen(e, true));
    // Mit der Maus reicht Überfahren; auf dem Telefon entscheidet der Tipp
    k.addEventListener('pointerenter', (ev) => { if (ev.pointerType === 'mouse') waehlen(e, false); });
    liste.append(k);
  }
  // Öffnet genau diese Demo in der Galerie (dieselbe Kachel, die man sonst anklickt)
  oeffnen.addEventListener('click', () => {
    if (!aktiv) return;
    const kachel = document.querySelector(`.demo-kachel[data-demo="${aktiv.demo}"]`);
    if (!kachel) return;
    zaehlen(`betrieb-oeffnen-${aktiv.id}`);
    if (kachel.getAttribute('aria-expanded') !== 'true') kachel.click();
    kachel.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  });
  waehlen(BETRIEBE[0], false);
}

function zaehlen(e) {
  try { navigator.sendBeacon ? navigator.sendBeacon(`/d.php?e=${e}`) : fetch(`/d.php?e=${e}`, { method: 'POST', keepalive: true }); } catch { /* egal */ }
}

/* WhatsApp-Knopf (V4): erst sichtbar, wenn der Aufmacher vorbei ist — im
   ersten Bildschirm stehen Aussage und Anfrage allein. */
const wa = document.querySelector('[data-wa-knopf]');
const held = document.querySelector('.hero');
if (wa) {
  if (held && 'IntersectionObserver' in window) {
    new IntersectionObserver(([e]) => wa.classList.toggle('ist-an', !e.isIntersecting), { threshold: 0.35 }).observe(held);
  } else wa.classList.add('ist-an');
  wa.addEventListener('click', () => zaehlen('whatsapp'));
}
