/* Besucher zaehlen (26.09.2026) -- ohne IP, ohne Keks, nichts von fremden
   Servern (z.php). Die Seite schickt, woher der Besucher kam: Ein <img>
   traegt als Referer nur die eigene Seite, und damit war die Herkunft in
   jeder Zeile leer. Mit ?utm_source=instagram in einem geteilten Link
   erscheint „instagram“ als Kampagne. */
(function () {
  try {
    var q = new URLSearchParams(location.search);
    var s = q.get('utm_source') || q.get('ref') || '';
    new Image().src = '/z.php?r=' + encodeURIComponent(document.referrer || '')
      + '&p=' + encodeURIComponent(location.pathname) + '&s=' + encodeURIComponent(s);
  } catch (e) { /* Zaehlen ist Beiwerk */ }
})();
