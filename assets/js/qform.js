/* ==========================================================================
   qform.js — Anfrageformular in drei Schritten.

   Gedanke dahinter: Wer anfragt, soll klicken statt tippen. Deshalb stehen
   die Auswahlfragen vorn und die Kontaktdaten hinten — wer schon zwei
   Schritte investiert hat, bricht beim Namensfeld selten noch ab.

   Pflicht ist nur, was ich wirklich brauche: eine Angabe zum Vorhaben,
   Name und E-Mail. Alles andere ist freiwillig.
   ========================================================================== */
/* --------------------------------------------------------------------------
   Ziel des Formulars. Standard ist der eigene Endpunkt auf dem Webspace
   (formular.php), der die Anfrage über Brevo verschickt — dadurch bleibt der
   Schlüssel auf dem Server und die Daten laufen nicht über einen Dritten
   in den USA.

   Leer lassen, um wieder das E-Mail-Programm zu öffnen.
   -------------------------------------------------------------------------- */
window.VECOM_FORM_ENDPOINT = '/formular.php';

(function () {
  'use strict';
  const form = document.querySelector('.qform');
  if (!form) return;

  const dict = () => (window.VECOM_I18N || {})[document.documentElement.lang] || {};
  const t = (path) => path.split('.').reduce((o, k) => (o || {})[k], dict()) || '';

  const steps = [...form.querySelectorAll('.qform__step')];
  const bar = form.querySelector('.qform__bar i');
  const nowEl = form.querySelector('[data-step-now]');
  const nameEl = form.querySelector('[data-step-name]');
  const backBtn = form.querySelector('.qform__back');
  const nextBtn = form.querySelector('.qform__next');
  const nextLabel = nextBtn.querySelector('span[data-i18n]');
  const done = form.querySelector('.qform__done');
  const nav = form.querySelector('.qform__nav');
  const trust = form.querySelector('.qform__trust');
  let current = 1;

  /* ---------- Gewähltes Paket ------------------------------------------- */
  /* Wer auf einer Paketkarte anfragt, hat sich entschieden. Bisher ging diese
     Entscheidung auf dem Weg zum Formular verloren und musste unten im Freitext
     wiederholt werden. Sie reist jetzt über ?paket= mit, steht sichtbar über den
     Fragen und geht mit der Anfrage raus. Name und Preis kommen aus der Karte
     selbst — damit stimmt es auch, wenn die Pakete aus der Verwaltung kommen. */
  function gewaehltesPaket() {
    var slug = new URLSearchParams(location.search).get('paket');
    if (!slug || !/^[a-z0-9-]{1,40}$/.test(slug)) return null;
    var karte = document.querySelector('.plan[data-paket="' + slug + '"]');
    if (!karte) return null;
    var name = karte.querySelector('h3');
    var preis = karte.querySelector('.plan__price span');
    return {
      slug: slug,
      name: name ? name.textContent.trim() : slug,
      preis: preis ? preis.textContent.trim() : '',
    };
  }

  var paket = gewaehltesPaket();

  function paketZeigen() {
    if (!paket) return;
    var alt = document.querySelector('.qform__paket');
    if (alt) alt.remove();
    var box = document.createElement('p');
    box.className = 'qform__paket';
    var label = document.createElement('span');
    label.className = 'qform__paket-label';
    label.textContent = t('form.paketLabel') || 'Paket';
    var wert = document.createElement('strong');
    wert.textContent = paket.preis ? paket.name + ' · ' + paket.preis : paket.name;
    var wechseln = document.createElement('a');
    wechseln.href = '#plans';
    wechseln.className = 'qform__paket-wechsel';
    wechseln.textContent = t('form.paketWechseln') || 'ändern';
    box.append(label, wert, wechseln);
    form.prepend(box);   // in die Formularspalte, nicht als eigene Rasterzelle
  }

  /* ---------- Auswahlfelder aus den Sprachdaten bauen -------------------- */
  function buildChips() {
    form.querySelectorAll('[data-chips]').forEach((box) => {
      const raw = t(box.dataset.chips);
      if (!raw) return;
      const chosen = [...box.querySelectorAll('[aria-pressed="true"]')].map((b) => b.dataset.index);
      box.innerHTML = '';
      raw.split('|').forEach((label, i) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'chip-opt';
        b.dataset.index = String(i);
        b.textContent = label.trim();
        b.setAttribute('aria-pressed', chosen.includes(String(i)) ? 'true' : 'false');
        box.appendChild(b);
      });
    });
  }
  buildChips();
  paketZeigen();
  // Bei Sprachwechsel neu beschriften, Auswahl bleibt erhalten
  document.querySelectorAll('[data-lang]').forEach((b) => b.addEventListener('click', () => setTimeout(() => { buildChips(); paketZeigen(); }, 30)));

  form.addEventListener('click', (e) => {
    const chip = e.target.closest('.chip-opt');
    if (!chip) return;
    const box = chip.closest('[data-chips]');
    const multi = box.hasAttribute('data-multi');
    if (!multi) box.querySelectorAll('.chip-opt').forEach((c) => c.setAttribute('aria-pressed', 'false'));
    chip.setAttribute('aria-pressed', multi ? String(chip.getAttribute('aria-pressed') !== 'true') : 'true');
    box.classList.remove('is-missing');

    // „Ja“ bei der Website-Frage blendet das Adressfeld ein
    if (box.hasAttribute('data-reveal-url')) {
      const url = form.querySelector('.qform__url');
      url.hidden = !(chip.dataset.index === '0' && chip.getAttribute('aria-pressed') === 'true');
    }
  });

  /* ---------- Schrittwechsel -------------------------------------------- */
  function show(n) {
    current = n;
    steps.forEach((s, i) => s.classList.toggle('is-active', i + 1 === n));
    bar.style.setProperty('--p', (n / steps.length).toFixed(3));
    nowEl.textContent = String(n);
    nameEl.setAttribute('data-i18n', 'form.s' + n);
    nameEl.textContent = t('form.s' + n);
    backBtn.hidden = n === 1;
    nextLabel.setAttribute('data-i18n', n === steps.length ? 'form.send' : 'form.next');
    nextLabel.textContent = t(n === steps.length ? 'form.send' : 'form.next');
    const top = form.getBoundingClientRect().top + window.scrollY - 110;
    if (window.__vecomLenis) window.__vecomLenis.scrollTo(top, { duration: 0.7 });
    else window.scrollTo({ top, behavior: 'smooth' });
  }

  function validate(n) {
    let ok = true;
    const step = steps[n - 1];
    step.querySelectorAll('[data-chips][data-required]').forEach((box) => {
      const hit = box.querySelector('[aria-pressed="true"]');
      box.classList.toggle('is-missing', !hit);
      if (!hit) ok = false;
    });
    step.querySelectorAll('input[required]').forEach((inp) => {
      const bad = !inp.value.trim() || (inp.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(inp.value));
      inp.classList.toggle('is-missing', bad);
      if (bad) ok = false;
    });
    return ok;
  }

  backBtn.addEventListener('click', () => show(Math.max(1, current - 1)));
  nextBtn.addEventListener('click', () => {
    if (!validate(current)) return;
    if (current < steps.length) return show(current + 1);
    send();
  });
  form.addEventListener('input', (e) => e.target.classList.remove('is-missing'));

  /* ---------- Absenden --------------------------------------------------- */
  /* Die Bestätigung erscheint erst, wenn der Server sie bestätigt hat.

     Das ist die wichtigste Zeile in dieser Datei. Vorher wurde „deine Anfrage
     ist unterwegs" angezeigt, sobald das Formular abgeschickt war — ohne je
     nachzusehen, was zurückkam. Der Server antwortete mit einem Fehler, der
     Besucher sah einen Haken, und die Anfrage war weg. Monatelang.

     Ohne Endpunkt bleibt es beim E-Mail-Programm wie bisher. */

  function abschlussZeigen(el) {
    steps.forEach((s) => s.classList.remove('is-active'));
    nav.hidden = true;
    if (trust) trust.hidden = true;
    form.querySelector('.qform__count').hidden = true;
    bar.style.setProperty('--p', '1');
    el.hidden = false;
    const top = form.getBoundingClientRect().top + window.scrollY - 110;
    if (window.__vecomLenis) window.__vecomLenis.scrollTo(top, { duration: 0.6 });
    else window.scrollTo({ top, behavior: 'smooth' });
  }

  /* Der Fehlerkasten steht im HTML. Fehlt er — alte Seite im Cache —, wird er
     hier erzeugt, damit ein Fehler nie stumm bleibt. */
  function fehlerkasten() {
    let box = form.querySelector('.qform__fail');
    if (box) return box;
    box = document.createElement('div');
    box.className = 'qform__fail';
    box.hidden = true;
    box.innerHTML = '<h3 data-i18n="form.failHead"></h3><p data-i18n="form.failText"></p>'
      + '<a class="btn" data-i18n="form.failBtn" href="mailto:kontakt@vecom-design.it"></a>';
    form.appendChild(box);
    return box;
  }

  function fehlerZeigen(grund) {
    const box = fehlerkasten();
    const kopf = box.querySelector('[data-i18n="form.failHead"]');
    const text = box.querySelector('[data-i18n="form.failText"]');
    const knopf = box.querySelector('[data-i18n="form.failBtn"]');
    if (kopf && !kopf.textContent.trim()) kopf.textContent = t('form.failHead');
    if (text && !text.textContent.trim()) text.textContent = t('form.failText');
    if (knopf) {
      if (!knopf.textContent.trim()) knopf.textContent = t('form.failBtn');
      // Der Notausgang: der ganze Text wandert in eine vorbereitete E-Mail,
      // damit niemand alles noch einmal tippen muss.
      knopf.setAttribute('href', 'mailto:' + (form.dataset.mailto || 'kontakt@vecom-design.it')
        + '?subject=' + encodeURIComponent('Projektanfrage — ' + (letzterName || ''))
        + '&body=' + encodeURIComponent(letzterText || ''));
    }
    if (grund) box.setAttribute('data-grund', grund);

    // Anders als die Bestaetigung raeumt der Fehler das Formular NICHT ab.
    // Die Eingaben bleiben stehen und der Knopf bleibt da: Wer es noch einmal
    // versuchen will, soll das koennen, ohne alles neu zu tippen.
    box.hidden = false;
    box.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }

  let letzterName = '';
  let letzterText = '';

  function send() {
    const answers = [];
    if (paket) answers.push('Paket: ' + paket.name + (paket.preis ? ' (' + paket.preis + ')' : ''));
    form.querySelectorAll('[data-chips]').forEach((box) => {
      const picked = [...box.querySelectorAll('[aria-pressed="true"]')].map((b) => b.textContent);
      if (picked.length) answers.push(box.dataset.name + ': ' + picked.join(', '));
    });
    const val = (n) => (form.querySelector('[name="' + n + '"]') || {}).value || '';
    if (val('url')) answers.push('Website: ' + val('url'));
    if (val('text')) answers.push('\n' + val('text'));

    const body = [
      val('name'),
      val('email'),
      val('phone') ? 'Tel.: ' + val('phone') : '',
      '',
      ...answers,
    ].filter(Boolean).join('\n');

    letzterName = val('name');
    letzterText = body;

    const endpoint = window.VECOM_FORM_ENDPOINT;
    if (!endpoint) {
      window.location.href = 'mailto:' + (form.dataset.mailto || '') +
        '?subject=' + encodeURIComponent('Projektanfrage — ' + val('name')) +
        '&body=' + encodeURIComponent(body);
      abschlussZeigen(done);
      return;
    }

    const data = new FormData();
    data.append('name', val('name'));
    data.append('email', val('email'));
    if (val('phone')) data.append('telefon', val('phone'));
    data.append('nachricht', body);
    if (paket) { data.append('paket', paket.slug); data.append('paket_name', paket.name); }
    if (val('url')) data.append('seite', val('url'));
    data.append('sprache', document.documentElement.lang || 'it');
    data.append('website', '');            // Honigtopf gegen Bots, bleibt leer

    const altbox = form.querySelector('.qform__fail');
    if (altbox) altbox.hidden = true;      // beim neuen Versuch erst mal weg

    nextBtn.disabled = true;
    const vorher = nextLabel.textContent;
    nextLabel.textContent = t('form.sending') || vorher;

    // Ein hängender Server darf den Besucher nicht ewig warten lassen.
    const abbruch = new AbortController();
    const uhr = setTimeout(() => abbruch.abort(), 20000);

    fetch(endpoint, {
      method: 'POST', body: data,
      headers: { Accept: 'application/json' },
      signal: abbruch.signal,
    })
      .then((r) => r.json().catch(() => ({})).then((d) => ({ status: r.status, d: d })))
      .then((a) => {
        if (a.d && a.d.ok === true) { form.classList.add('is-sent'); abschlussZeigen(done); }
        else { fehlerZeigen((a.d && a.d.error) || ('http' + a.status)); }
      })
      .catch(() => fehlerZeigen('netz'))
      .finally(() => {
        clearTimeout(uhr);
        nextBtn.disabled = false;
        nextLabel.textContent = vorher;
      });
  }

  show(1);
})();
