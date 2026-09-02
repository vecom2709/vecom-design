/* Videos erst laden, wenn jemand sie sehen will. Vorher liegt nur ein
   Vorschaubild da (10 KB statt 2,3 MB) — die Seite bleibt schnell. */
(function () {
  document.querySelectorAll('.vid').forEach((fig) => {
    const start = () => {
      if (fig.classList.contains('is-playing')) return;
      const v = document.createElement('video');
      v.src = fig.dataset.src;
      v.controls = true;
      v.playsInline = true;
      v.preload = 'auto';
      fig.querySelector('img').replaceWith(v);
      fig.classList.add('is-playing');
      /* Seit das Video eine Tonspur hat, genuegt das autoplay-Attribut nicht
         mehr: Safari laesst Ton nur zu, wenn das Abspielen aus einer echten
         Nutzeraktion heraus angestossen wird. Der Klick auf den Knopf ist
         eine — also hier abspielen und nicht dem Attribut ueberlassen.
         Weigert sich der Browser trotzdem, laeuft es stumm weiter, statt
         gar nicht zu starten. */
      const los = v.play();
      if (los && typeof los.catch === 'function') {
        los.catch(() => { v.muted = true; v.play().catch(() => {}); });
      }
    };
    fig.querySelector('.vid__play').addEventListener('click', start);
    fig.querySelector('img')?.addEventListener('click', start);
  });
})();
