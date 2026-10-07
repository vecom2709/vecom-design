<?php
/* Akquise › Pipeline (Akquise-CRM Modul C, 06.10.2026, Uwe: Drag & Drop).
   Daten: $spalten (AkquiseCrm::pipeline), $filter, $werte, $branchen.
   Ziehen ist eine Abkürzung — jede Karte hat zusätzlich „Verschieben nach …“ (Tastatur, Handy, ohne JavaScript). */
$akqTeil = 'pipeline';
$haupt = array_diff_key(AkquiseCrm::SPALTEN, array_flip(['spaeter', 'kein_interesse', 'verloren', 'gesperrt']));
$seite = array_intersect_key(AkquiseCrm::SPALTEN, array_flip(['spaeter', 'kein_interesse', 'verloren', 'gesperrt']));
$ziele = array_diff_key(AkquiseCrm::SPALTEN, array_flip(['gesperrt', 'kein_interesse']));
$fragen = ['gewonnen' => 'Als „Auftrag gewonnen“ markieren? Der Betrieb gilt dann als Kunde, Folge-Mails enden. Zurück geht es danach nicht mehr.',
           'verloren' => 'Als verloren markieren? Folge-Mails enden. Gesperrt wird der Betrieb dadurch nicht.'];
$gewaehlt = static fn(string $k, string $v): string => (($filter[$k] ?? '') === $v) ? ' selected' : '';
$karte = static function (array $z, string $sp) use ($ziele): string {
    $st = AkquisePrio::STUFEN[(string) ($z['prio_stufe'] ?? '')] ?? null;
    $optionen = '';
    foreach ($ziele as $k => $w) { if ($k !== $sp) { $optionen .= '<option value="' . $k . '">' . Fmt::h($w) . '</option>'; } }
    $abgeschlossen = in_array($sp, ['gewonnen', 'gesperrt', 'kein_interesse'], true);
    return '<article class="pl-karte" draggable="' . ($abgeschlossen ? 'false' : 'true') . '" data-firma="' . (int) $z['id'] . '">'
        . '<a href="' . Fmt::h(url('akquise/' . (int) $z['id'])) . '">' . Fmt::h((string) $z['name']) . '</a>'
        . '<span class="pl-unter">' . Fmt::h(implode(' · ', array_filter([(string) $z['stadt'], Akquise::branchenName($z['branche'])]))) . '</span>'
        . '<span class="pl-zeile">' . ($st ? '<span class="crm-prio p-' . Fmt::h((string) $z['prio_stufe']) . '">' . $st[0] . ' ' . (int) $z['prio_score'] . '</span>' : '')
        . (!empty($z['naechster_am']) ? '<span class="pl-unter">➜ ' . Fmt::h(date('d.m.', strtotime((string) $z['naechster_am']))) . '</span>' : '') . '</span>'
        . (!$abgeschlossen ? '<form method="post" action="' . Fmt::h(url('akquise')) . '" class="pl-schieben">' . Csrf::feld()
            . '<input type="hidden" name="tat" value="akq_stufe"><input type="hidden" name="firma" value="' . (int) $z['id'] . '"><input type="hidden" name="zurueck" value="akquise/pipeline">'
            . '<select name="ziel" aria-label="Verschieben nach">' . $optionen . '</select><button class="knopf klein">Verschieben</button></form>' : '')
        . '</article>';
};
?>
<style>
  .pl-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin:0 0 14px}
  .pl-filter .feld{margin:0}
  .pl-tafel{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(230px,1fr);gap:12px;overflow-x:auto;padding-bottom:12px;scroll-snap-type:x proximity}
  .pl-spalte{background:var(--flaeche);border:1px solid var(--linie);border-radius:14px;padding:10px;display:flex;flex-direction:column;gap:8px;min-height:180px;scroll-snap-align:start}
  .pl-spalte h3{margin:0 0 2px;font-size:var(--fs-klein);display:flex;flex-wrap:wrap;justify-content:space-between;gap:2px 6px;color:var(--dim);text-transform:uppercase;letter-spacing:.05em}
  .pl-spalte h3 span{flex:1;min-width:0}
  .pl-spalte h3 b{color:var(--text);font-variant-numeric:tabular-nums}
  .pl-spalte.ziel-an{border-color:var(--cyan);box-shadow:0 0 0 2px rgba(241,211,139,.25) inset}
  .pl-spalte.gerechnet h3::after{content:"rechnet das System";flex-basis:100%;font-size:var(--fs-klein);font-weight:400;letter-spacing:0;text-transform:none;color:var(--leise)}
  .pl-karte{background:var(--flaeche2);border:1px solid var(--linie);border-radius:10px;padding:9px 10px;display:flex;flex-direction:column;gap:4px;cursor:grab}
  .pl-karte[draggable=false]{cursor:default}
  .pl-karte.zieht{opacity:.45}
  .pl-karte a{color:var(--text);font-weight:600;font-size:13.5px;line-height:1.35}
  .pl-unter{font-size:var(--fs-klein);color:var(--leise)}
  .pl-zeile{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .pl-schieben{display:flex;gap:4px;margin:4px 0 0}
  .pl-schieben select{font-size:var(--fs-klein);padding:3px 6px;min-height:0;flex:1;min-width:0}
  .pl-mehr{font-size:var(--fs-klein);color:var(--leise);text-align:center}
  .pl-seite{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px;margin-top:14px}
  @media (hover:hover) and (pointer:fine){ .pl-schieben{display:none} .pl-karte:focus-within .pl-schieben,.pl-karte:hover .pl-schieben{display:flex} }
</style>
<div class="kopf"><div><h1>Akquise · Pipeline</h1>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">Gefunden bis Interesse rechnet das System aus den Daten; ab „Bedarf geklärt“ setzt du die Stufe — per Ziehen oder „Verschieben“. Sperren nur im Profil, mit Grund.</p></div></div>
<?php require __DIR__ . '/akquise_reiter.php'; ?>

<form class="pl-filter" method="get" action="<?= Fmt::h(url('akquise/pipeline')) ?>">
  <div class="feld"><label for="pl-q">Suche</label><input id="pl-q" name="q" value="<?= Fmt::h((string) ($filter['q'] ?? '')) ?>" placeholder="Name, Ort, Domain"></div>
  <div class="feld"><label for="pl-branche">Branche</label><select id="pl-branche" name="branche"><option value="">alle</option>
    <?php foreach ($werte['branche'] as $b): ?><option value="<?= Fmt::h((string) $b) ?>"<?= $gewaehlt('branche', (string) $b) ?>><?= Fmt::h(Akquise::branchenName((string) $b)) ?></option><?php endforeach; ?></select></div>
  <div class="feld"><label for="pl-stadt">Ort</label><select id="pl-stadt" name="stadt"><option value="">alle</option>
    <?php foreach ($werte['stadt'] as $o): ?><option<?= $gewaehlt('stadt', (string) $o) ?>><?= Fmt::h((string) $o) ?></option><?php endforeach; ?></select></div>
  <div class="feld"><label for="pl-prio">Priorität</label><select id="pl-prio" name="prio"><option value="">alle</option>
    <?php foreach (AkquisePrio::STUFEN as $k => [$z, $w]): ?><option value="<?= $k ?>"<?= $gewaehlt('prio', $k) ?>><?= $z . ' ' . Fmt::h($w) ?></option><?php endforeach; ?></select></div>
  <?php $kwInForm = true; $kampagne = $kampagne ?? null; $kampagnen = $kampagnen ?? []; require __DIR__ . '/akquise_kampagnenwahl.php'; /* Modul H */ ?>
  <button class="knopf">Filtern</button>
  <?php if (array_filter($filter) || !empty($kampagne)): ?><a class="knopf" href="<?= Fmt::h(url('akquise/pipeline?kampagne=0')) ?>">Zurücksetzen</a><?php endif; ?>
</form>

<?php /* Ein Formular für das Ziehen: Das Skript setzt Firma und Ziel und schickt es ab — die Rückfrage aus dem Rahmen greift wie bei jedem Formular. */ ?>
<form method="post" action="<?= Fmt::h(url('akquise')) ?>" id="pl-zieh" hidden><?= Csrf::feld() ?>
  <input type="hidden" name="tat" value="akq_stufe"><input type="hidden" name="firma" value=""><input type="hidden" name="ziel" value=""><input type="hidden" name="zurueck" value="akquise/pipeline">
</form>
<script type="application/json" id="pl-fragen"><?= json_encode($fragen, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="pl-tafel" aria-label="Pipeline">
  <?php foreach ($haupt as $sp => $wort): $sx = $spalten[$sp]; ?>
    <section class="pl-spalte<?= in_array($sp, AkquiseCrm::GERECHNET, true) ? ' gerechnet' : '' ?>" data-spalte="<?= $sp ?>" id="sp-<?= $sp ?>">
      <h3><span><?= Fmt::h($wort) ?></span> <b><?= (int) $sx['n'] ?></b></h3>
      <?php foreach ($sx['zeilen'] as $z): ?><?= $karte($z, $sp) ?><?php endforeach; ?>
      <?php if ($sx['n'] > count($sx['zeilen'])): ?><div class="pl-mehr">… und <?= (int) $sx['n'] - count($sx['zeilen']) ?> weitere (Filter nutzen)</div><?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
<div class="pl-seite">
  <?php foreach ($seite as $sp => $wort): $sx = $spalten[$sp]; ?>
    <section class="pl-spalte" data-spalte="<?= $sp ?>" id="sp-<?= $sp ?>"<?= in_array($sp, ['gesperrt', 'kein_interesse'], true) ? ' data-gesperrt="1"' : '' ?>>
      <h3><span><?= Fmt::h($wort) ?></span> <b><?= (int) $sx['n'] ?></b></h3>
      <?php foreach (array_slice($sx['zeilen'], 0, 8) as $z): ?><?= $karte($z, $sp) ?><?php endforeach; ?>
      <?php if ($sx['n'] > min(8, count($sx['zeilen']))): ?><div class="pl-mehr">… und <?= (int) $sx['n'] - min(8, count($sx['zeilen'])) ?> weitere</div><?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
<script src="/assets/js/akquise-pipeline.js" defer></script>
