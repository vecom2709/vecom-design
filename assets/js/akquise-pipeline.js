/* Akquise › Pipeline (06.10.2026): Karten ziehen. Das Ziehen füllt nur ein Formular und schickt es ab —
   Server-Prüfung, Rückfrage (Gewonnen, Verloren) und Prüfspur laufen wie bei jedem Knopf. */
(function () {
  var form = document.getElementById('pl-zieh');
  if (!form) { return; }
  var fragen = {};
  try { fragen = JSON.parse((document.getElementById('pl-fragen') || {}).textContent || '{}'); } catch (e) { fragen = {}; }
  var gezogen = null;

  document.querySelectorAll('.pl-karte[draggable="true"]').forEach(function (k) {
    k.addEventListener('dragstart', function (e) {
      gezogen = k;
      k.classList.add('zieht');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', k.getAttribute('data-firma'));
    });
    k.addEventListener('dragend', function () { k.classList.remove('zieht'); gezogen = null; });
  });

  /* „Verschieben nach …“: dieselbe Rückfrage wie beim Ziehen — gesetzt, bevor abgeschickt wird. */
  document.querySelectorAll('.pl-schieben select').forEach(function (sel) {
    var f = sel.form;
    var setzen = function () {
      if (fragen[sel.value]) { f.setAttribute('data-frage', fragen[sel.value]); f.setAttribute('data-ja', 'Ja'); }
      else { f.removeAttribute('data-frage'); }
    };
    sel.addEventListener('change', setzen);
    setzen();
  });

  document.querySelectorAll('.pl-spalte').forEach(function (sp) {
    if (sp.getAttribute('data-gesperrt') === '1') { return; }   // Sperren nur im Profil
    sp.addEventListener('dragover', function (e) { if (gezogen) { e.preventDefault(); sp.classList.add('ziel-an'); } });
    sp.addEventListener('dragleave', function () { sp.classList.remove('ziel-an'); });
    sp.addEventListener('drop', function (e) {
      e.preventDefault();
      sp.classList.remove('ziel-an');
      if (!gezogen) { return; }
      var ziel = sp.getAttribute('data-spalte');
      if (gezogen.closest('.pl-spalte') === sp) { return; }
      form.querySelector('[name=firma]').value = gezogen.getAttribute('data-firma');
      form.querySelector('[name=ziel]').value = ziel;
      if (fragen[ziel]) { form.setAttribute('data-frage', fragen[ziel]); form.setAttribute('data-ja', 'Ja'); }
      else { form.removeAttribute('data-frage'); }
      if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
    });
  });
})();
