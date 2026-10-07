<?php
/** @var array $antwort @var array $verstanden @var string $q @var array $filter @var array $werte */
/* Assistent ohne KI-Kosten (27.09.2026). Eine Frage tippen oder einen der
   festen Knöpfe nehmen; jede Zeile ist ein Datensatz mit Link und der Ampel
   des Gates -- „gute Chance“ heißt nie „darf angeschrieben werden“. */
$akqTeil = 'assistent';
$link = static fn(array $x): string => url('akquise/assistent') . '?' . http_build_query(array_filter($x, static fn($v) => $v !== '' && $v !== null));
$branchen = Akquise::branchen();
?>
<style>
  .as-frage{display:grid;grid-template-columns:1fr auto;gap:10px;margin:0 0 12px}
  .as-frage input{font-size:17px;min-height:52px}
  .as-knoepfe{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 6px}
  .as-knoepfe a{padding:7px 12px;border-radius:999px;border:1px solid var(--linie);color:var(--dim);font-size:var(--fs-klein)}
  .as-knoepfe a.an{background:var(--flaeche2);color:var(--text);border-color:rgba(241,211,139,.5)}
  .as-filter{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:10px}
  .as-filter select{width:auto;min-width:150px}
  .as-erkannt{font-size:var(--fs-klein);color:var(--leise);margin:8px 0 0}
  @media (max-width:560px){ .as-frage{grid-template-columns:1fr} }
</style>
<div class="kopf"><div><h1>Assistent</h1>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">Frag in eigenen Worten oder nimm eine der festen Fragen. Die Antworten kommen aus deinen Daten — nichts wird geschätzt oder erfunden.</p></div></div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<div class="block">
  <form method="get" action="<?= Fmt::h(url('akquise/assistent')) ?>" class="as-frage">
    <input id="as-q" name="q" aria-label="Frage" value="<?= Fmt::h($q) ?>" placeholder="z. B. Zeig mir die besten Restaurants aus Sizilien">
    <button class="knopf haupt">Fragen</button>
  </form>
  <div class="as-knoepfe">
    <?php foreach (AkquiseAssistent::FRAGEN as $k => [$titel]): ?>
      <a href="<?= Fmt::h($link(['f' => $k] + $filter)) ?>"<?= $antwort['frage'] === $k ? ' class="an"' : '' ?>><?= Fmt::h($titel) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" action="<?= Fmt::h(url('akquise/assistent')) ?>" class="as-filter">
    <input type="hidden" name="f" value="<?= Fmt::h($antwort['frage']) ?>">
    <div><label class="akq-klein">Land</label><select name="land"><option value="">alle</option>
      <?php foreach (['IT' => 'Italien', 'DE' => 'Deutschland'] as $k => $w): ?><option value="<?= $k ?>"<?= ($filter['land'] ?? '') === $k ? ' selected' : '' ?>><?= $w ?></option><?php endforeach; ?></select></div>
    <div><label class="akq-klein">Region</label><select name="region"><option value="">alle</option>
      <?php foreach ($werte['region'] as $r): ?><option<?= ($filter['region'] ?? '') === $r ? ' selected' : '' ?>><?= Fmt::h((string) $r) ?></option><?php endforeach; ?></select></div>
    <div><label class="akq-klein">Branche</label><select name="branche"><option value="">alle</option>
      <?php foreach ($branchen as $k => $b): ?><option value="<?= Fmt::h((string) $k) ?>"<?= ($filter['branche'] ?? '') === $k ? ' selected' : '' ?>><?= Fmt::h((string) $b['de']) ?></option><?php endforeach; ?></select></div>
    <button class="knopf">Filtern</button>
  </form>
  <?php if ($verstanden): ?><p class="as-erkannt">Verstanden: <?= Fmt::h(implode(' · ', $verstanden)) ?></p><?php endif; ?>
</div>

<div class="block">
  <h2><?= Fmt::h($antwort['titel']) ?></h2>
  <p class="akq-klein" style="margin:0 0 10px"><?= Fmt::h($antwort['satz']) ?></p>
  <?php if ($antwort['zeilen']): ?>
  <div class="tabellenrahmen"><table class="akq-tab"><thead><tr><th>Betrieb</th><th>Ort</th><th><?= Fmt::h($antwort['spalte']) ?></th><th>Darf ich?</th></tr></thead><tbody>
    <?php foreach ($antwort['zeilen'] as $z): $amp = AkquiseGate::ampel($z);
      $wert = match ($antwort['frage']) {
          'warten', 'still', 'checks' => !empty($z['zusatz']) ? date('d.m.Y', strtotime((string) $z['zusatz'])) : '—',
          'probleme', 'dreid' => (string) (int) ($z['zusatz'] ?? 0),
          'folgen' => (string) ($z['zusatz'] ?? ''),
          'mail' => !empty($z['zusatz']) ? (string) $z['zusatz'] : 'nur E-Mail',
          default => $z['score'] !== null ? (int) $z['score'] . ' · ' . Akquise::chanceWort((int) $z['score']) : '—',
      }; ?>
      <tr><td><a href="<?= Fmt::h(url('akquise/' . (int) $z['id'])) ?>" style="color:var(--cyan)"><?= Fmt::h((string) $z['name']) ?></a>
            <div class="akq-klein"><?= Fmt::h((string) ($branchen[(string) $z['branche']]['de'] ?? '')) ?><?= $z['domain'] ? ' · ' . Fmt::h((string) $z['domain']) : '' ?></div></td>
          <td class="akq-klein"><?= Fmt::h(trim((string) ($z['stadt'] ?? '') . ', ' . (string) ($z['region'] ?? ''), ', ')) ?></td>
          <td><?= Fmt::h($wert) ?><?php if ($antwort['frage'] === 'mail'): ?><div class="akq-klein"><?= Fmt::h((string) $z['email']) ?></div><?php endif; ?></td>
          <td><span class="akq-ampel klein <?= Fmt::h($amp['farbe']) ?>"><i></i><span><?= Fmt::h($amp['wort']) ?></span></span></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
