/* ==========================================================================
   polish.js — Bewegung an der Oberfläche.

   Alles hier ist Zugabe: Ohne dieses Skript bleibt die Seite vollständig
   bedienbar und lesbar. Deshalb läuft nichts davon bei reduzierter Bewegung.
   ========================================================================== */
(function () {
  'use strict';
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const gsap = window.gsap;

  /* ---------- 1. Kinetische Überschrift im Hero -------------------------- */
  // Bewusst ohne GSAP: Der Auftritt der Titelzeilen darf nicht davon abhängen,
  // ob eine Bibliothek geladen und ihr Ticker aktiv ist. Zwei CSS-Klassen und
  // eine Verzögerung je Zeile reichen — und es läuft auch, wenn 3D ausfällt.
  if (!reduced) {
    const showHero = () => document.documentElement.classList.add('hero-in');
    requestAnimationFrame(() => requestAnimationFrame(showHero));
    // Sicherheitsnetz: Läuft die Bildschleife gedrosselt (schwaches Gerät,
    // 3D-Aufbau), käme der zweite Frame sonst erst nach Sekunden.
    setTimeout(showHero, 400);
  } else {
    document.documentElement.classList.add('hero-in');
  }

  /* ---------- 5. Überschriften wortweise (nur Überschriften) ------------- */
  // Bewusst nicht im Fließtext: Dort stört es beim zweiten Besuch und
  // zerlegt den Text für Vorleseprogramme in Einzelwörter.
  if (gsap && window.ScrollTrigger && !reduced) {
    document.querySelectorAll('.sechead h2, .contact h2').forEach((h) => {
      if (h.dataset.split) return;
      h.dataset.split = '1';
      const words = h.textContent.trim().split(/\s+/);
      h.setAttribute('aria-label', h.textContent.trim());
      h.innerHTML = words
        .map((w) => `<span class="w" aria-hidden="true"><i>${w}</i></span>`)
        .join(' ');
      gsap.set(h.querySelectorAll('i'), { yPercent: 105 });
      gsap.to(h.querySelectorAll('i'), {
        yPercent: 0, duration: 0.85, ease: 'expo.out', stagger: 0.045,
        scrollTrigger: { trigger: h, start: 'top 85%' },
      });
    });
  }

  /* ---------- 3. Projektkarten neigen sich zum Zeiger --------------------- */
  if (!reduced && window.matchMedia('(pointer: fine)').matches) {
    document.querySelectorAll('.card').forEach((card) => {
      card.addEventListener('pointermove', (e) => {
        const r = card.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5;
        const y = (e.clientY - r.top) / r.height - 0.5;
        card.style.setProperty('--rx', (-y * 5).toFixed(2) + 'deg');
        card.style.setProperty('--ry', (x * 6).toFixed(2) + 'deg');
        card.style.setProperty('--lx', ((x + 0.5) * 100).toFixed(1) + '%');
        card.style.setProperty('--ly', ((y + 0.5) * 100).toFixed(1) + '%');
        card.classList.add('is-tilted');
      });
      card.addEventListener('pointerleave', () => {
        card.classList.remove('is-tilted');
        card.style.setProperty('--rx', '0deg');
        card.style.setProperty('--ry', '0deg');
      });
    });
  }

  /* ---------- 4. Kennzahlen zählen hoch ---------------------------------- */
  if (!reduced && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (!en.isIntersecting) return;
        const el = en.target;
        io.unobserve(el);
        const raw = el.textContent.trim();
        const target = parseFloat(raw.replace(/[^\d.]/g, ''));
        if (!isFinite(target) || target === 0) return;   // „0" bleibt stehen
        const suffix = raw.replace(/[\d.,]/g, '');
        const t0 = performance.now();
        const dur = 1100;
        (function tick(now) {
          const p = Math.min(1, ((now || performance.now()) - t0) / dur);
          const e = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(target * e) + suffix;
          if (p < 1) requestAnimationFrame(tick);
        })();
        // Sicherheitsnetz: Läuft die Bildschleife gedrosselt (schwaches Gerät,
        // Hintergrund-Tab), bleibt der Zähler sonst auf einem Zwischenwert stehen.
        setTimeout(() => { el.textContent = target + suffix; }, dur + 250);
      });
    }, { threshold: 0.6 });
    document.querySelectorAll('.pillar strong').forEach((el) => io.observe(el));
  }

  /* ---------- 6. Preise: einmalig oder Gesamtkosten über 12 Monate -------- */
  const toggle = document.querySelector('[data-price-toggle]');
  if (toggle) {
    const plans = [...document.querySelectorAll('.plan')];
    // Zahlen einmal auslesen, damit später nur noch gerechnet wird.
    const data = plans.map((plan) => {
      const once = plan.querySelector('.plan__price > span');
      const month = plan.querySelector('.plan__month');
      const parse = (el) => {
        const m = (el ? el.textContent : '').replace(/\./g, '').match(/(\d+)/);
        return m ? parseInt(m[1], 10) : null;
      };
      return { once, month, o: parse(once), m: parse(month), oText: once ? once.textContent : '' };
    });
    const fmt = (n) => n.toLocaleString(document.documentElement.lang || 'de-DE');

    const render = (mode) => {
      data.forEach((d) => {
        if (!d.once || d.o === null) return;
        if (mode === 'year' && d.m !== null) {
          d.once.textContent = fmt(d.o + d.m * 12) + ' €';
        } else {
          d.once.textContent = d.oText;
        }
      });
      toggle.querySelectorAll('button').forEach((b) =>
        b.setAttribute('aria-pressed', String(b.dataset.mode === mode)));
      document.querySelectorAll('.plan__price small').forEach((s) => {
        s.textContent = s.dataset[mode] || s.textContent;
      });
    };

    toggle.addEventListener('click', (e) => {
      const b = e.target.closest('button');
      if (b) render(b.dataset.mode);
    });
    // Beschriftungen für beide Zustände merken, bevor sie überschrieben werden.
    document.querySelectorAll('.plan__price small').forEach((s) => {
      s.dataset.once = s.textContent;
      s.dataset.year = toggle.dataset.yearNote || s.textContent;
    });
    render('once');
  }

  /* ---------- 9. Sprachwechsel gestaffelt --------------------------------- */
  if (!reduced) {
    document.querySelectorAll('[data-lang]').forEach((b) => {
      b.addEventListener('click', () => {
        document.body.classList.add('is-swapping');
        setTimeout(() => document.body.classList.remove('is-swapping'), 420);
      });
    });
  }
})();
