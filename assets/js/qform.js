/* ==========================================================================
   qform.js — Anfrageformular in drei Schritten.

   Gedanke dahinter: Wer anfragt, soll klicken statt tippen. Deshalb stehen
   die Auswahlfragen vorn und die Kontaktdaten hinten — wer schon zwei
   Schritte investiert hat, bricht beim Namensfeld selten noch ab.

   Pflicht ist nur, was ich wirklich brauche: eine Angabe zum Vorhaben,
   Name und E-Mail. Alles andere ist freiwillig.
   ========================================================================== */
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
  // Bei Sprachwechsel neu beschriften, Auswahl bleibt erhalten
  document.querySelectorAll('[data-lang]').forEach((b) => b.addEventListener('click', () => setTimeout(buildChips, 30)));

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
  // Noch über das E-Mail-Programm. Sobald ein Formulardienst eingerichtet ist,
  // wird hier ein fetch() daraus — der Rest bleibt unverändert.
  function send() {
    const answers = [];
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

    window.location.href = 'mailto:' + (form.dataset.mailto || '') +
      '?subject=' + encodeURIComponent('Projektanfrage — ' + val('name')) +
      '&body=' + encodeURIComponent(body);

    steps.forEach((s) => s.classList.remove('is-active'));
    nav.hidden = true;
    if (trust) trust.hidden = true;
    form.querySelector('.qform__count').hidden = true;
    bar.style.setProperty('--p', '1');
    done.hidden = false;
  }

  show(1);
})();
