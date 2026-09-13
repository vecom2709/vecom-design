<?php
/**
 * Dateien — alles Hochgeladene an einer Stelle.
 *
 * WARUM HIER BILDER STEHEN UND NICHT NUR NAMEN
 *
 * Ein Kunde schickt "IMG_4711.jpg", "logo_final_v3.png" und "scan.pdf".
 * Aus den Namen geht nicht hervor, welches davon das Logo ist, ob das Foto
 * brauchbar ist und ob der Scan schief liegt. Bisher musste man jede Datei
 * einzeln herunterladen, um das zu sehen — bei zwoelf Dateien zwoelf Mal.
 *
 * Die Miniatur beantwortet das in einer Zeile. Was kein Bild ist, bekommt
 * ein Sinnbild seiner Art — kein gefaelschtes Vorschaubild.
 */

/** Ein Sinnbild fuer alles, was sich nicht als Bild zeigen laesst. */
$zeichen = static function (string $mime, string $name): array {
    $endung = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if ($mime === 'application/pdf')           { return ['PDF', 'var(--rot)']; }
    if ($mime === 'application/zip')           { return ['ZIP', 'var(--gelb)']; }
    if (str_starts_with($mime, 'video/'))      { return ['Film', 'var(--cyan)']; }
    if (str_starts_with($mime, 'audio/'))      { return ['Ton', 'var(--cyan)']; }
    if (str_starts_with($mime, 'image/'))      { return [strtoupper($endung ?: 'Bild'), 'var(--leise)']; }
    if (in_array($endung, ['doc', 'docx'], true)) { return ['DOC', 'var(--blau)']; }
    if (in_array($endung, ['xls', 'xlsx'], true)) { return ['XLS', 'var(--gruen)']; }
    if (in_array($endung, ['ppt', 'pptx'], true)) { return ['PPT', 'var(--gelb)']; }
    return [strtoupper($endung ?: 'Datei'), 'var(--leise)'];
};

/* Der Filter gehoert an den Rueckweg: Wer aus einer gefilterten Liste heraus
   loescht, will danach wieder dieselbe Liste sehen und nicht von vorn suchen. */
$zurueckZiel = 'dateien';
$anhang = [];
if ($fKunde > 0)   { $anhang[] = 'kunde=' . $fKunde; }
if ($fProjekt > 0) { $anhang[] = 'projekt=' . $fProjekt; }
if ($anhang) { $zurueckZiel .= '?' . implode('&', $anhang); }

/** Welche Arten sich wirklich als Bild zeigen lassen — dieselbe Liste wie in Ablage. */
$bildArten = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
?>

<div class="kopf">
  <div><h1>Dateien</h1>
    <p style="color:var(--leise);font-size:13px;margin-top:6px">
      Alles, was hochgeladen wurde — von dir oder vom Kunden. Hochladen im jeweiligen Projekt.</p></div>
  <div class="rechts">
    <span class="marke2"><?= count($liste) ?> <?= count($liste) === 1 ? 'Datei' : 'Dateien' ?></span>
  </div>
</div>

<?php if (!$bereit): ?>
  <div class="hinweis schlecht">Der Ordner <code>app/uploads/</code> lässt sich nicht beschreiben.
    Im KAS unter Dateiverwaltung die Schreibrechte für <code>app/</code> prüfen.</div>
<?php endif; ?>

<?php if (!$bilder): ?>
  <div class="hinweis">Auf diesem Server fehlt die Bildbibliothek (GD) — deshalb stehen hier
    keine Vorschaubilder, sondern nur die Dateiarten. Alles andere funktioniert.</div>
<?php endif; ?>

<?php /* ---------- Filter ----------
         Steht nur da, wenn es etwas zu filtern gibt. Ein Filter ueber drei
         Dateien ist ein Bedienelement ohne Aufgabe. */ ?>
<?php if ($kunden || $projekte): ?>
  <form class="dfilter" method="get" action="<?= Fmt::h(url('dateien')) ?>">
    <select name="kunde" onchange="this.form.submit()" aria-label="Nach Kunde filtern">
      <option value="0">Alle Kunden</option>
      <?php foreach ($kunden as $k): ?>
        <option value="<?= (int) $k['id'] ?>" <?= $fKunde === (int) $k['id'] ? 'selected' : '' ?>><?=
          Fmt::h((string) $k['wer']) ?> (<?= (int) $k['n'] ?>)</option>
      <?php endforeach; ?>
    </select>
    <select name="projekt" onchange="this.form.submit()" aria-label="Nach Projekt filtern">
      <option value="0">Alle Projekte</option>
      <?php foreach ($projekte as $pr): ?>
        <option value="<?= (int) $pr['id'] ?>" <?= $fProjekt === (int) $pr['id'] ? 'selected' : '' ?>><?=
          Fmt::h((string) $pr['name']) ?> (<?= (int) $pr['n'] ?>)</option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="knopf">Filtern</button></noscript>
    <?php if ($fKunde > 0 || $fProjekt > 0): ?>
      <a class="knopf" href="<?= Fmt::h(url('dateien')) ?>">Filter aufheben</a>
    <?php endif; ?>
  </form>
<?php endif; ?>

<div class="block">
  <?php if (!$liste): ?>
    <div class="leer"><?= ($fKunde > 0 || $fProjekt > 0)
      ? 'Zu dieser Auswahl liegt nichts.' : 'Noch keine Dateien.' ?></div>
  <?php else: ?>
    <table class="dtab"><thead><tr>
      <th style="width:76px"></th><th>Datei</th><th>Kunde</th><th>Projekt</th>
      <th>Größe</th><th>Von</th><th>Wann</th><th></th>
    </tr></thead><tbody>
    <?php foreach ($liste as $d): ?>
      <?php
        $id      = (int) $d['id'];
        $mime    = (string) $d['mime'];
        $name    = (string) $d['orig_name'];
        $istBild = $bilder && in_array($mime, $bildArten, true);
        [$kuerzel, $farbe] = $zeichen($mime, $name);
      ?>
      <tr>
        <td>
          <?php if ($istBild): ?>
            <?php /* Der Knopf traegt die Grossansicht in seinem eigenen
                     Datenfeld. So braucht die Seite keine zweite Liste in
                     JavaScript, die mit der Tabelle aus dem Tritt geraten
                     koennte, wenn hier einmal gefiltert oder sortiert wird. */ ?>
            <button type="button" class="dmini" title="Groß ansehen"
                    data-gross="<?= Fmt::h(url('dateien/' . $id) . '?art=gross') ?>"
                    data-name="<?= Fmt::h($name) ?>">
              <img src="<?= Fmt::h(url('dateien/' . $id) . '?art=vorschau') ?>"
                   alt="" loading="lazy" width="64" height="64">
            </button>
          <?php else: ?>
            <span class="dart" style="color:<?= $farbe ?>"><?= Fmt::h($kuerzel) ?></span>
          <?php endif; ?>
        </td>
        <td><a href="<?= Fmt::h(url('dateien/' . $id)) ?>"><?= Fmt::h($name) ?></a>
          <?php if ((string) ($d['rolle'] ?? 'material') === 'paket'): ?>
            <span class="marke2 gut" style="margin-left:6px">Website-Paket</span>
          <?php endif; ?>
          <br><small style="color:var(--leise)"><?= Fmt::h($mime) ?></small></td>
        <td><?= $d['customer_id']
              ? '<a href="' . Fmt::h(url('kunden/' . (int) $d['customer_id'])) . '">' . Fmt::h((string) ($d['firma'] ?: $d['kunde'])) . '</a>'
              : '—' ?></td>
        <td><?= $d['project_id']
              ? '<a href="' . Fmt::h(url('projekte/' . (int) $d['project_id'])) . '">' . Fmt::h((string) $d['projekt']) . '</a>'
              : '—' ?></td>
        <td style="white-space:nowrap"><?= Fmt::h(Fmt::bytes((int) $d['size_bytes'])) ?></td>
        <td><?= $d['uploaded_by'] === 'kunde' ? 'Kunde' : 'du' ?></td>
        <td style="white-space:nowrap;color:var(--leise)"><?= Fmt::h(Fmt::seit($d['created_at'])) ?></td>
        <td style="text-align:right;white-space:nowrap">
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="datei_weg">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="zurueck" value="<?= Fmt::h($zurueckZiel) ?>">
            <button class="knopf klein">Löschen</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php if (count($liste) >= 200): ?>
      <p style="color:var(--leise);font-size:12.5px;margin-top:12px">
        Es stehen 200 Dateien in der Liste — ältere findest du über den Filter.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php /* ---------- Die Großansicht ----------
         Ein Feld über der Seite, kein neues Fenster: Wer zehn Bilder
         durchsieht, will blättern und nicht zehnmal zurück. Escape schließt,
         die Pfeiltasten blättern. */ ?>
<div class="dgross" id="dgross" hidden>
  <div class="dgross__kopf">
    <span class="dgross__name" id="dgrossName"></span>
    <span class="dgross__zahl" id="dgrossZahl"></span>
    <button type="button" class="knopf" id="dgrossZu">Schließen</button>
  </div>
  <button type="button" class="dgross__pfeil links" id="dgrossZurueck" aria-label="Vorheriges Bild">&lsaquo;</button>
  <img id="dgrossBild" alt="">
  <button type="button" class="dgross__pfeil rechts" id="dgrossWeiter" aria-label="Nächstes Bild">&rsaquo;</button>
</div>

<script>
(function () {
  var kasten = document.getElementById('dgross');
  if (!kasten) { return; }
  var bild = document.getElementById('dgrossBild');
  var name = document.getElementById('dgrossName');
  var zahl = document.getElementById('dgrossZahl');
  /* Die Reihenfolge kommt aus der Tabelle selbst, nicht aus einer zweiten
     Liste: Was man sieht, ist auch das, was man durchblättert. */
  var knoepfe = Array.prototype.slice.call(document.querySelectorAll('.dmini'));
  if (!knoepfe.length) { return; }
  var bei = -1;

  function zeigen(i) {
    if (i < 0 || i >= knoepfe.length) { return; }
    bei = i;
    var k = knoepfe[i];
    bild.src = k.getAttribute('data-gross');
    bild.alt = k.getAttribute('data-name') || '';
    name.textContent = k.getAttribute('data-name') || '';
    zahl.textContent = (i + 1) + ' von ' + knoepfe.length;
    kasten.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function zu() {
    kasten.hidden = true;
    bild.removeAttribute('src');
    document.body.style.overflow = '';
    /* Zurück auf den Knopf, von dem aus geöffnet wurde — sonst steht die
       Tastatur danach wieder am Seitenanfang. */
    if (bei >= 0 && knoepfe[bei]) { knoepfe[bei].focus(); }
  }

  knoepfe.forEach(function (k, i) {
    k.addEventListener('click', function () { zeigen(i); });
  });
  document.getElementById('dgrossZu').addEventListener('click', zu);
  document.getElementById('dgrossZurueck').addEventListener('click', function () { zeigen(bei - 1); });
  document.getElementById('dgrossWeiter').addEventListener('click', function () { zeigen(bei + 1); });
  /* Klick auf den dunklen Grund schließt, Klick auf das Bild nicht. */
  kasten.addEventListener('click', function (e) { if (e.target === kasten) { zu(); } });
  document.addEventListener('keydown', function (e) {
    if (kasten.hidden) { return; }
    if (e.key === 'Escape')     { zu(); }
    if (e.key === 'ArrowLeft')  { zeigen(bei - 1); }
    if (e.key === 'ArrowRight') { zeigen(bei + 1); }
  });
})();
</script>
