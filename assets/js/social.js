/* ==========================================================================
   social.js — Verweise auf die sozialen Kanäle.

   HIER die Adressen eintragen. Ein leerer Eintrag bedeutet: Das Symbol
   wird gar nicht angezeigt. Ein Symbol, das ins Leere führt, schadet mehr,
   als es nützt — deshalb erscheint nur, was auch wirklich existiert.

   Beispiel:  facebook: 'https://www.facebook.com/vecomdesign',
   ========================================================================== */
window.VECOM_SOCIAL = {
  facebook:  'https://www.facebook.com/vecomdesign',
  instagram: '',
  tiktok:    '',
  x:         'https://x.com/vecomdesign',
  youtube:   'https://www.youtube.com/@vecomdesign',
  // Der Bot (30.09.2026): Preis-Richtwert, Anfrage, persönliche Beratung.
  // ?start=web: In der Verwaltung ist sichtbar, dass jemand über die Website kam.
  telegram:  'https://t.me/VecomDesignBot?start=web',
};

(function () {
  const cfg = window.VECOM_SOCIAL || {};

  /* Symbole, die nicht fest im HTML der Fußzeile stehen. Fehlt der Anker
     für einen eingetragenen Kanal, wird er hier ergänzt — so reicht die
     Zeile oben, und keine der Vorlagen (IT/DE/EN) muss angefasst werden. */
  const ICONS = {
    youtube: '<svg viewBox="0 0 24 24" width="19" height="19" fill="currentColor" aria-hidden="true"><path d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12 31 31 0 0 0 1 16.8a3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8zM9.7 15.1V8.9l5.8 3.1z"/></svg>',
    telegram: '<svg viewBox="0 0 24 24" width="19" height="19" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3 18.7 19.4c-.2 1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.1-8.2c.4-.4-.1-.6-.6-.2L6.2 13.1l-4.8-1.5c-1-.3-1.1-1 .2-1.5L20.5 2.9c.9-.3 1.7.2 1.4 1.4z"/></svg>',
  };
  const LABELS = { youtube: 'YouTube', telegram: 'Telegram' };

  const list = document.querySelector('.social');
  if (list) {
    Object.keys(ICONS).forEach((name) => {
      if (!cfg[name] || list.querySelector(`[data-social="${name}"]`)) return;
      const a = document.createElement('a');
      a.className = 'social__a';
      a.dataset.social = name;
      a.target = '_blank';
      a.rel = 'noopener me';
      a.setAttribute('aria-label', LABELS[name] || name);
      a.innerHTML = ICONS[name];
      list.appendChild(a);
    });
  }

  document.querySelectorAll('[data-social]').forEach((a) => {
    const url = cfg[a.dataset.social];
    if (url) {
      a.href = url;
      a.hidden = false;
    } else {
      a.hidden = true;               // noch keine Adresse hinterlegt
    }
  });
  if (list && !list.querySelector('a:not([hidden])')) list.hidden = true;
})();
