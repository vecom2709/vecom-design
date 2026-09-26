<?php
/** @var array $liste @var array $filter @var array $werte @var array $kz @var array $grenzen @var int $wartend */
$akqTeil = '';
$wert = static fn(string $k): string => (string) ($filter[$k] ?? '');
$gewaehlt = static fn(string $k, string $v): string => (($filter[$k] ?? '') === $v) ? ' selected' : '';
$seitenUrl = static function (int $s) use ($filter): string {
    return url('akquise') . '?' . http_build_query($filter + ['seite' => $s]);
};
?>
<div class="kopf"><div><h1>Akquise</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Betriebe mit belegten Schwächen auf ihrer Website — gefunden, geprüft und bewertet vom Worker.
    Qualität vor Menge: angesprochen wird nur, wer einen konkreten Anlass hat und wen das Gate lässt.</p></div>
</div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<div class="karten">
  <div class="karte"><h3>Firmen</h3><div class="wert"><?= (int) ($kz['gesamt'] ?? 0) ?></div>
    <div class="neben"><?= (int) ($kz['geprueft'] ?? 0) ?> geprüft · <?= (int) ($kz['wartend'] ?? 0) ?> warten</div></div>
  <div class="karte"><h3>Stark (Score ≥ 71)</h3><div class="wert"><?= (int) ($kz['stark'] ?? 0) ?></div>
    <div class="neben">sehr interessant oder Top</div></div>
  <div class="karte"><h3>Ohne eigene Website</h3><div class="wert"><?= (int) ($kz['ohne_website'] ?? 0) ?></div>
    <div class="neben">vor Ansprache prüfen</div></div>
  <div class="karte"><h3>Vorlagen · Kontaktiert</h3><div class="wert"><?= (int) ($kz['vorlagen'] ?? 0) ?> · <?= (int) ($kz['kontaktiert'] ?? 0) ?></div>
    <div class="neben"><?= (int) ($kz['gesperrt'] ?? 0) ?> gesperrt · <?= $wartend ?> Recherche offen</div></div>
</div>

<div class="block">
  <form method="get" action="<?= Fmt::h(url('akquise')) ?>">
    <div class="akq-filter">
      <div class="breit"><label for="q">Suche</label><input id="q" name="q" value="<?= Fmt::h($wert('q')) ?>" placeholder="Name, Domain, Ort, L-…"></div>
      <div><label for="land">Land</label><select id="land" name="land"><option value="">alle</option>
        <option value="DE"<?= $gewaehlt('land', 'DE') ?>>Deutschland</option><option value="IT"<?= $gewaehlt('land', 'IT') ?>>Italien</option></select></div>
      <div><label for="region">Region</label><select id="region" name="region"><option value="">alle</option>
        <?php foreach ($werte['region'] as $r): ?><option<?= $gewaehlt('region', (string) $r) ?>><?= Fmt::h((string) $r) ?></option><?php endforeach; ?></select></div>
      <div><label for="kreis">Kreis / Provinz</label><select id="kreis" name="kreis"><option value="">alle</option>
        <?php foreach ($werte['kreis'] as $r): ?><option<?= $gewaehlt('kreis', (string) $r) ?>><?= Fmt::h((string) $r) ?></option><?php endforeach; ?></select></div>
      <div><label for="stadt">Stadt</label><select id="stadt" name="stadt"><option value="">alle</option>
        <?php foreach ($werte['stadt'] as $r): ?><option<?= $gewaehlt('stadt', (string) $r) ?>><?= Fmt::h((string) $r) ?></option><?php endforeach; ?></select></div>
      <div><label for="branche">Branche</label><select id="branche" name="branche"><option value="">alle</option>
        <?php foreach ($werte['branche'] as $r): ?><option value="<?= Fmt::h((string) $r) ?>"<?= $gewaehlt('branche', (string) $r) ?>><?= Fmt::h(Akquise::branchenName((string) $r)) ?></option><?php endforeach; ?></select></div>
      <div><label for="score_min">Score ab</label><input id="score_min" name="score_min" type="number" min="0" max="100" value="<?= Fmt::h($wert('score_min')) ?>"></div>
      <div><label for="stufe">Stufe</label><select id="stufe" name="stufe"><option value="">alle</option>
        <?php foreach (AkquiseScore::STUFEN as $k => $w): ?><option value="<?= $k ?>"<?= $gewaehlt('stufe', $k) ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
      <div><label for="kontakt">Kontaktstatus</label><select id="kontakt" name="kontakt"><option value="">alle</option>
        <?php foreach (Akquise::KONTAKT_STATUS as $k => $w): ?><option value="<?= $k ?>"<?= $gewaehlt('kontakt', $k) ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
      <div><label for="compliance">Compliance</label><select id="compliance" name="compliance"><option value="">alle</option>
        <?php foreach (AkquiseGate::STATUS as $k => $w): ?><option value="<?= $k ?>"<?= $gewaehlt('compliance', $k) ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
      <div><label for="audit">Audit</label><select id="audit" name="audit"><option value="">alle</option>
        <?php foreach (Akquise::AUDIT_STATUS as $k => $w): ?><option value="<?= $k ?>"<?= $gewaehlt('audit', $k) ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
      <div><label for="von">Gefunden ab</label><input id="von" name="von" type="date" value="<?= Fmt::h($wert('von')) ?>"></div>
      <div><label for="bis">bis</label><input id="bis" name="bis" type="date" value="<?= Fmt::h($wert('bis')) ?>"></div>
      <div><label for="sort">Sortierung</label><select id="sort" name="sort">
        <?php foreach (['score' => 'Score', 'neu' => 'Neueste', 'geprueft' => 'Zuletzt geprüft', 'name' => 'Name'] as $k => $w): ?>
          <option value="<?= $k ?>"<?= $gewaehlt('sort', $k) ?>><?= $w ?></option><?php endforeach; ?></select></div>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button class="knopf haupt">Filtern</button>
      <a class="knopf" href="<?= Fmt::h(url('akquise')) ?>">Zurücksetzen</a>
      <label style="display:flex;gap:6px;align-items:center;font-size:13px;color:var(--dim)">
        <input type="checkbox" name="gesperrte" value="1" style="width:auto"<?= !empty($filter['gesperrte']) ? ' checked' : '' ?>> gesperrte zeigen</label>
      <span class="akq-klein" style="margin-left:auto"><?= (int) $liste['gesamt'] ?> Treffer</span>
    </div>
  </form>
</div>

<div class="block">
  <?php if (!$liste['zeilen']): ?>
    <div class="leer">
      <?php if ((int) ($kz['gesamt'] ?? 0) === 0): ?>
        Noch keine Firmen. Lege unter <a href="<?= Fmt::h(url('akquise/recherche')) ?>" style="color:var(--cyan)">Recherche</a> ein Gebiet an —
        der Worker auf deinem Rechner holt den Auftrag ab.
      <?php else: ?>
        Keine Firma passt zu diesem Filter.
      <?php endif; ?>
    </div>
  <?php else: ?>
  <div class="tabellenrahmen"><table class="akq-tab">
    <thead><tr><th>Firma</th><th>Ort</th><th>Branche</th><th>Score</th><th>Wichtigste Probleme</th>
      <th>Kontakt · Versand · Antwort</th><th>Compliance</th></tr></thead>
    <tbody>
    <?php foreach ($liste['zeilen'] as $z): $top = json_decode((string) ($z['top_probleme'] ?? '[]'), true) ?: []; ?>
      <tr>
        <td><a href="<?= Fmt::h(url('akquise/' . (int) $z['id'])) ?>"><b><?= Fmt::h((string) $z['name']) ?></b></a>
          <div class="akq-klein"><?= Fmt::h((string) ($z['domain'] ?? 'keine Website')) ?> · <?= Fmt::h((string) $z['kennung']) ?></div>
          <?php if (trim((string) ($z['notiz'] ?? '')) !== ''): ?><div class="akq-klein" style="margin-top:3px">✎ <?= Fmt::h(mb_strimwidth((string) $z['notiz'], 0, 60, '…')) ?></div><?php endif; ?></td>
        <td><?= Fmt::h((string) $z['land']) ?> · <?= Fmt::h((string) ($z['stadt'] ?? '—')) ?>
          <div class="akq-klein"><?= Fmt::h(trim(($z['kreis'] ?? '') . ', ' . ($z['region'] ?? ''), ', ')) ?></div></td>
        <td><?= Fmt::h(Akquise::branchenName($z['branche'])) ?></td>
        <td><?php if ($z['score'] !== null): ?><span class="akq-score s-<?= Fmt::h((string) $z['score_stufe']) ?>"
              title="<?= Fmt::h(AkquiseScore::STUFEN[(string) $z['score_stufe']] ?? '') ?>"><?= (int) $z['score'] ?></span>
            <?php else: ?><span class="akq-klein">—</span><?php endif; ?></td>
        <td><?php if ($top): ?><ul class="akq-probleme"><?php foreach ($top as $t): ?><li><?= Fmt::h((string) $t) ?></li><?php endforeach; ?></ul>
            <?php else: ?><span class="akq-klein"><?= Fmt::h(Akquise::AUDIT_STATUS[(string) $z['audit_status']] ?? '') ?></span><?php endif; ?></td>
        <td><?= Fmt::h(Akquise::KONTAKT_STATUS[(string) $z['kontakt_status']] ?? (string) $z['kontakt_status']) ?>
          <div class="akq-klein">Versand: <?= Fmt::h((string) $z['versand_status']) ?>
            <?= $z['antwort_status'] ? ' · ' . Fmt::h(AkquiseText::ANTWORT_KLASSEN[(string) $z['antwort_status']] ?? '') : '' ?>
            <?= $z['geprueft_am'] ? '<br>geprüft ' . Fmt::h(Fmt::datum((string) $z['geprueft_am'])) : '' ?></div></td>
        <td><span class="marke2 <?= ['CONTACT_ALLOWED' => 'gut', 'REVIEW_REQUIRED' => 'warnung', 'DO_NOT_EMAIL' => 'schlecht'][$z['compliance_status']] ?? '' ?>">
          <?= Fmt::h(AkquiseGate::STATUS[(string) $z['compliance_status']] ?? '') ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php if ($liste['seiten'] > 1): ?>
      <div style="display:flex;gap:8px;justify-content:center;margin-top:14px;flex-wrap:wrap">
        <?php for ($s = 1; $s <= $liste['seiten']; $s++): ?>
          <a class="knopf<?= $s === $liste['seite'] ? ' haupt' : '' ?>" href="<?= Fmt::h($seitenUrl($s)) ?>"><?= $s ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
