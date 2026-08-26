/* Videos erst laden, wenn jemand sie sehen will. Vorher liegt nur ein
   Vorschaubild da (10 KB statt 2,3 MB) — die Seite bleibt schnell. */
(function () {
  document.querySelectorAll('.vid').forEach((fig) => {
    const start = () => {
      if (fig.classList.contains('is-playing')) return;
      const v = document.createElement('video');
      v.src = fig.dataset.src;
      v.controls = true;
      v.autoplay = true;
      v.playsInline = true;
      v.preload = 'auto';
      fig.querySelector('img').replaceWith(v);
      fig.classList.add('is-playing');
    };
    fig.querySelector('.vid__play').addEventListener('click', start);
    fig.querySelector('img')?.addEventListener('click', start);
  });
})();
