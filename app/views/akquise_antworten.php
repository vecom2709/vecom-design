<?php
/* Akquise-CRM Modul E (06.10.2026): Kurz zusammengefasst · Antwort vorbereiten · Nachfassen · Wiedervorlage.
   Eingebunden aus akquise_firma.php (Überblick, rechte Spalte, über „E-Mail“). Daten: $f, $fid, $post.
   Uwe: Vorlagen „fest + Ton“, Nachfassen „Erinnerung + fertiger Entwurf“, Wiedervorlage/Zusammenfassung „aus den Daten“.
   Eine Antwort ist keine Werbeeinwilligung: Senden läuft über dieselbe mailto-Strecke mit demselben Versandgrund-Schloss. */
require_once dirname(__DIR__) . '/src/AkquiseAntwort.php';
require_once dirname(__DIR__) . '/src/AkquiseMail.php';
$awH = static fn(?string $s): string => Fmt::h((string) $s);
$awZus = AkquiseAntwort::zusammenfassung($f);
$awOffen = AkquiseAntwort::offen($fid);
$awNach = $awOffen ? null : AkquiseAntwort::nachfassen($f);
$awKann = AkquiseMail::kann($f);
$awSp = in_array($_GET['awsp'] ?? '', ['it', 'de', 'en'], true) ? (string) $_GET['awsp'] : null;
$awVorlage = null;
if ($awOffen) { $awVorlage = isset(AkquiseAntwort::VORLAGEN_NAMEN[$_GET['vorlage'] ?? '']) ? (string) $_GET['vorlage'] : AkquiseAntwort::vorlageFuer($awOffen); }
elseif ($awNach) { $awVorlage = 'nachfassen' . $awNach['schritt']; }
$awE = $awVorlage ? AkquiseAntwort::entwurf($f, $awVorlage, $awSp, $awOffen) : null;
$awEinw = $awOffen ? AkquiseAntwort::einwaende((string) $awOffen['betreff'] . ' ' . (string) $awOffen['text']) : [];
?>
<style>
  .aw-zus{margin:0;padding:0 0 0 18px;font-size:13.5px;line-height:1.6;color:var(--dim)}
  .aw-zitat{border-left:3px solid var(--linie2);padding:6px 10px;margin:8px 0;font-size:13.5px;line-height:1.5;color:var(--text);white-space:pre-wrap;max-height:180px;overflow:auto;background:var(--flaeche2);border-radius:0 8px 8px 0}
  .aw-chips{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0}
  .aw-chip{font-size:var(--fs-klein);padding:3px 9px;border-radius:999px;border:1px solid rgba(255,200,90,.4);color:var(--gelb)}
  .aw-kopf{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0}
  .aw-kopf select{width:auto}
  .aw-wv{display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end}
  .aw-wv .feld{margin:0}
  .aw-wv select,.aw-wv input{width:auto}
</style>

<div class="block" id="aw">
  <h2>Kurz zusammengefasst</h2>
  <ul class="aw-zus"><?php foreach ($awZus as $z): ?><li><?= $awH($z) ?></li><?php endforeach; ?></ul>
  <p class="akq-klein" style="margin:8px 0 0">Nur aus Verlauf, Antworten und Einträgen — nichts geschätzt.</p>
</div>

<?php if ($awE): ?>
<div class="block akq-fokus" id="aw-entwurf">
  <?php if ($awOffen): ?>
    <h2>Antwort vorbereiten</h2>
    <p class="akq-klein" style="margin:0">Eingegangen am <?= $awH(date('d.m.Y H:i', strtotime((string) $awOffen['eingang_am']))) ?><?= $awOffen['von'] ? ' von ' . $awH((string) $awOffen['von']) : '' ?>
      · <?= $awH(AkquiseText::ANTWORT_KLASSEN[(string) $awOffen['klasse']] ?? (string) $awOffen['klasse']) ?></p>
    <?php if (trim((string) $awOffen['text']) !== ''): ?><div class="aw-zitat"><?= $awH(mb_substr((string) $awOffen['text'], 0, 2000)) ?></div><?php endif; ?>
    <?php if ($awEinw): ?><div class="aw-chips"><?php foreach ($awEinw as $e): ?><span class="aw-chip">Einwand: <?= $awH(AkquiseAntwort::EINWAENDE[$e][0]) ?></span><?php endforeach; ?></div><?php endif; ?>
  <?php else: ?>
    <h2>Nachfassen — <?= $awNach['schritt'] === 1 ? 'Tag 3' : 'Tag 7, letztes Mal' ?></h2>
    <p class="akq-klein" style="margin:0">Erste Nachricht am <?= $awH(date('d.m.Y', strtotime($awNach['seit']))) ?>, seitdem keine Antwort. Der Entwurf steht bereit — gesendet wird nur, wenn du ihn selbst öffnest.</p>
  <?php endif; ?>

  <form method="get" action="<?= $awH(url('akquise/' . $fid)) ?>#aw-entwurf" class="aw-kopf">
    <label class="akq-klein" for="aw-vorlage">Vorlage</label>
    <select id="aw-vorlage" name="vorlage"><?php foreach (AkquiseAntwort::VORLAGEN_NAMEN as $k => $w): if (!$awOffen && !str_starts_with($k, 'nachfassen')) { continue; } ?>
      <option value="<?= $k ?>"<?= $k === $awVorlage ? ' selected' : '' ?>><?= $awH($w) ?></option><?php endforeach; ?></select>
    <select name="awsp" aria-label="Sprache"><?php foreach (AkquiseText::SPRACHEN as $k => $w): ?><option value="<?= $k ?>"<?= $k === $awE['sprache'] ? ' selected' : '' ?>><?= $awH($w) ?></option><?php endforeach; ?></select>
    <button class="knopf klein">Laden</button>
  </form>

  <?php if ($awKann['senden']): ?>
  <form method="post" action="<?= $awH(url('akquise')) ?>" data-aw-mailto><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_mail_mailto"><input type="hidden" name="firma" value="<?= $fid ?>">
    <?php if ($awOffen): ?><input type="hidden" name="antwort" value="<?= (int) $awOffen['id'] ?>"><?php endif; ?>
  <?php endif; ?>
    <div class="feld"><label for="aw-betreff">Betreff</label><input id="aw-betreff" name="betreff" maxlength="200" value="<?= $awH($awE['betreff']) ?>"<?= $awKann['senden'] ? ' required' : '' ?>></div>
    <div class="feld"><label for="aw-text">Text</label><textarea id="aw-text" name="text" rows="12"<?= $awKann['senden'] ? ' required' : '' ?>><?= $awH($awE['text']) ?></textarea></div>
    <?php $ws = ['kanal' => 'email', 'senden' => $awKann['senden'], 'betreff' => $awE['betreff'], 'text' => $awE['text'], 'betreffFeld' => 'aw-betreff', 'textFeld' => 'aw-text'];
          require __DIR__ . '/akquise_werkstatt.php'; ?>
    <div class="an-knoepfe" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">
      <?php if ($awKann['senden']): ?><button class="knopf haupt">Im Mailprogramm öffnen</button><?php endif; ?>
      <button class="knopf" type="button" data-aw-kopieren>Text kopieren</button>
      <span class="akq-klein" data-aw-status></span>
    </div>
  <?php if ($awKann['senden']): ?></form><?php endif; ?>
  <?php if ($awKann['senden'] && !$awKann['freigabe']): ?>
    <p class="akq-klein" style="margin-top:8px">⚠ Kein Versandgrund dokumentiert, keine Einwilligung hinterlegt — das Mailprogramm öffnet trotzdem, vorher bestätigst du den Hinweis in der Prüfung. Du sendest aus deinem eigenen Programm und entscheidest selbst.</p>
  <?php endif; ?>
  <?php if (!$awKann['senden']): ?>
    <p class="akq-klein" style="margin-top:8px">Öffnen im Mailprogramm geht hier nicht: <?= $awH((string) ($awKann['grund'] ?? '')) ?></p>
  <?php endif; ?>
  <?php if ($awOffen): ?>
    <?= $post('akq_antwort_erledigt', '<input type="hidden" name="antwort" value="' . (int) $awOffen['id'] . '"><button class="knopf klein">Erledigt — nichts mehr zu tun</button>', ' style="margin-top:10px"') ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="block" id="aw-wv">
  <h2>Wiedervorlage</h2>
  <?php if (!empty($f['naechster_am'])): ?><p class="akq-klein" style="margin:0 0 8px">Steht: <?= $awH((string) ($f['naechster_schritt'] ?: 'Nächster Kontakt')) ?> — am <?= $awH(date('d.m.Y', strtotime((string) $f['naechster_am']))) ?>.</p><?php endif; ?>
  <?= $post('akq_wiedervorlage', '<div class="aw-wv">
      <div class="feld"><label for="aw-grund">Grund</label><select id="aw-grund" name="grund" required>' . implode('', array_map(static fn($k, $w) => '<option value="' . $k . '">' . Fmt::h($w) . '</option>', array_keys(AkquiseAntwort::WIEDERVORLAGE), AkquiseAntwort::WIEDERVORLAGE)) . '</select></div>
      <div class="feld"><label for="aw-notiz">Notiz</label><input id="aw-notiz" name="notiz" maxlength="100" placeholder="optional"></div>
      <button class="knopf klein" name="tage" value="3">+3 Tage</button><button class="knopf klein" name="tage" value="7">+7 Tage</button><button class="knopf klein" name="tage" value="30">+30 Tage</button>
      <div class="feld"><label for="aw-datum">oder am</label><input id="aw-datum" type="date" name="datum" min="' . date('Y-m-d') . '"></div><button class="knopf klein" name="tage" value="0">Am Datum vorlegen</button>
    </div>') ?>
</div>

<script>
(function () {
  var box = document.getElementById('aw-entwurf'); if (!box) return;
  var t = document.getElementById('aw-text'), b = document.getElementById('aw-betreff'), st = box.querySelector('[data-aw-status]');
  var k = box.querySelector('[data-aw-kopieren]');
  if (k) k.addEventListener('click', function () { if (navigator.clipboard) navigator.clipboard.writeText(b.value + '\n\n' + t.value).then(function () { k.textContent = '✓ Kopiert'; }); });
  /* Wie bei „Ansprechen“: der Server prüft und erzeugt den mailto-Link, gespeichert wird nur der Zeitpunkt. */
  var mf = box.querySelector('[data-aw-mailto]');
  if (mf && window.fetch) mf.addEventListener('submit', function (e) {
    e.preventDefault();
    var d = new FormData(mf); d.append('js', '1');
    fetch(mf.getAttribute('action'), { method: 'POST', body: d, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { st.textContent = '⛔ ' + (j.fehler || 'Nicht möglich.'); return; }
        if (j.lang && navigator.clipboard) navigator.clipboard.writeText(j.text).catch(function () {});
        st.textContent = '✓ Mailprogramm geöffnet — dort selbst auf Senden drücken.' + (d.get('antwort') ? ' Die Antwort ist als beantwortet vermerkt.' : '');
        window.location.href = j.link;
      })
      .catch(function () { mf.submit(); });
  });
})();
</script>
