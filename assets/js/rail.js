/* Kapitelleiste rechts: zeigt, wo man ist. Kein Ersatz für das Menü oben,
   sondern eine Orientierung während des Scrollens. */
(function () {
  const rail = document.querySelector('.rail');
  if (!rail || !('IntersectionObserver' in window)) return;
  const links = [...rail.querySelectorAll('a')];
  const map = new Map(links.map((a) => [a.getAttribute('href').slice(1), a]));

  const io = new IntersectionObserver((entries) => {
    entries.forEach((en) => {
      const a = map.get(en.target.id);
      if (!a) return;
      if (en.isIntersecting) {
        links.forEach((l) => l.removeAttribute('aria-current'));
        a.setAttribute('aria-current', 'true');
      }
    });
  }, { rootMargin: '-45% 0px -50% 0px' });

  map.forEach((_, id) => { const el = document.getElementById(id); if (el) io.observe(el); });

  // Erst zeigen, wenn der Hero vorbei ist — im Aufmacher stört sie.
  const hero = document.querySelector('.hero');
  if (hero) new IntersectionObserver(([e]) => rail.classList.toggle('is-on', !e.isIntersecting),
    { threshold: 0.1 }).observe(hero);
})();
