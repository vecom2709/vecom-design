/* ==========================================================================
   social.js — Verweise auf die sozialen Kanäle.

   HIER die vier Adressen eintragen. Ein leerer Eintrag bedeutet: Das Symbol
   wird gar nicht angezeigt. Ein Symbol, das ins Leere führt, schadet mehr,
   als es nützt — deshalb erscheint nur, was auch wirklich existiert.

   Beispiel:  facebook: 'https://www.facebook.com/vecomdesign',
   ========================================================================== */
window.VECOM_SOCIAL = {
  facebook:  '',
  instagram: '',
  tiktok:    '',
  x:         '',
};

(function () {
  const cfg = window.VECOM_SOCIAL || {};
  document.querySelectorAll('[data-social]').forEach((a) => {
    const url = cfg[a.dataset.social];
    if (url) {
      a.href = url;
      a.hidden = false;
    } else {
      a.hidden = true;               // noch keine Adresse hinterlegt
    }
  });
  const list = document.querySelector('.social');
  if (list && !list.querySelector('a:not([hidden])')) list.hidden = true;
})();
