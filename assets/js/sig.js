/* Der Namenszug schreibt sich, sobald er ins Bild kommt — einmal, nicht bei
   jedem Vorbeiscrollen. Ohne JS steht er einfach da. */
(function () {
  const sig = document.querySelector('[data-sig]');
  if (!sig || !('IntersectionObserver' in window)) { if (sig) sig.classList.add('is-writing'); return; }
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const io = new IntersectionObserver(([e]) => {
    if (!e.isIntersecting) return;
    sig.classList.add('is-writing');
    io.disconnect();
  }, { threshold: 0.6 });
  io.observe(sig);
})();
