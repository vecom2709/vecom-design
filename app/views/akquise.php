<?php
/** @var array $liste @var array $filter @var array $werte @var array $kz @var array $grenzen @var int $wartend
 *  @var array $suchen @var array $branchen */
/* DIE LISTE (26.09.2026, einfacher gemacht)
   Fuenf Spalten statt sieben, vier Filter sichtbar statt vierzehn, und in
   jeder Zeile genau ein naechster Schritt. Alles andere ist noch da --
   unter „Mehr Filter“ und auf der Firmenseite --, aber es steht nicht mehr
   zwischen Uwe und der Frage „wen spreche ich heute an?“. */
$akqTeil = '';
$wert = static fn(string $k): string => (string) ($filter[$k] ?? '');
$gewaehlt = static fn(string $k, string $v): string => (($filter[$k] ?? '') === $v) ? ' selected' : '';
$seitenUrl = static function (int $s) use ($filter): string {
    return url('akquise') . '?' . http_build_query($filter + ['seite' => $s]);
};
$mehrOffen = (bool) array_intersect_key(array_filter($filter, static fn($v) => $v !== ''),
    array_flip(['q', 'region', 'kreis', 'kontakt', 'compliance', 'audit', 'stufe', 'score_min', 'von', 'bis', 'sort', 'gesperrte']));
$kachel = static fn(string $k, string $v): string => url('akquise') . '?' . http_build_query([$k => $v]);
?>
<div class="kopf"><div><h1>Neue Kunden finden</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Betriebe, deren Website nachweislich Schwächen hat — gefunden und geprüft von deinem Rechner, nachts.
    Du entscheidest, wer angesprochen wird.</p></div>
</div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<div class="block akq-suchen">
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_suchen">
    <div class="akq-suchzeile">
      <div><label for="s_land">Land</label><select id="s_land" name="land"><option value="IT">Italien</option><option value="DE">Deutschland</option></select></div>
      <div class="breit"><label for="s_gebiet">Wo soll gesucht werden?</label>
        <input id="s_gebiet" name="gebiet" required maxlength="120" placeholder="Ort, Provinz oder Region — z. B. Sciacca, Agrigento, Sizilien"></div>
      <button class="knopf haupt">Jetzt suchen</button>
    </div>
    <details style="margin-top:8px"><summary class="akq-klein" style="cursor:pointer">Nur bestimmte Branchen</summary>
      <div class="akq-branchen">
        <?php foreach ($branchen as $k => $x): ?>
          <label><input type="checkbox" name="branchen[]" value="<?= Fmt::h((string) $k) ?>"> <?= Fmt::h((string) $x['de']) ?></label>
        <?php endforeach; ?>
      </div>
    </details>
  </form>
  <p class="akq-klein" style="margin-top:8px">
    <?php if ($suchen): ?>Vorgemerkt für heute Nacht:
      <?php foreach ($suchen as $s): ?><span class="akq-chip"><?= Fmt::h((string) $s['gebiet']) ?><?= $s['status'] === 'laeuft' ? ' · läuft' : '' ?></span><?php endforeach; ?>
    <?php else: ?>Dein Rechner sucht nachts: erst die Betriebe, dann prüft er jede Website. Morgens stehen sie hier.<?php endif; ?>
  </p>
</div>

<div class="karten akq-kacheln">
  <a class="karte" href="<?= Fmt::h($kachel('kontakt', 'bereit')) ?>"><h3>Bereit zum Ansprechen</h3><div class="wert"><?= (int) ($kz['bereit'] ?? 0) ?></div>
    <div class="neben">geprüft, gute Chance</div></a>
  <a class="karte" href="<?= Fmt::h($kachel('stark', '1')) ?>"><h3>Starke Chancen</h3><div class="wert"><?= (int) ($kz['stark'] ?? 0) ?></div>
    <div class="neben">Chance 71 oder mehr</div></a>
  <a class="karte" href="<?= Fmt::h($kachel('kontakt', 'kontaktiert')) ?>"><h3>Warten auf Antwort</h3><div class="wert"><?= (int) ($kz['warten'] ?? 0) ?></div>
    <div class="neben">angeschrieben oder angerufen</div></a>
  <a class="karte" href="<?= Fmt::h($kachel('kontakt', 'antwort')) ?>"><h3>Antworten</h3><div class="wert"><?= (int) ($kz['antworten'] ?? 0) ?></div>
    <div class="neben"><?= (int) ($kz['wartend'] ?? 0) ?> Websites noch ungeprüft</div></a>
</div>

<div class="block">
  <form method="get" action="<?= Fmt::h(url('akquise')) ?>">
    <div class="akq-filter">
      <div><label for="land">Land</label><select id="land" name="land"><option value="">alle</option>
        <option value="DE"<?= $gewaehlt('land', 'DE') ?>>Deutschland</option><option value="IT"<?= $gewaehlt('land', 'IT') ?>>Italien</option></select></div>
      <div><label for="stadt">Ort</label><select id="stadt" name="stadt"><option value="">alle</option>
        <?php foreach ($werte['stadt'] as $r): ?><option<?= $gewaehlt('stadt', (string) $r) ?>><?= Fmt::h((string) $r) ?></option><?php endforeach; ?></select></div>
      <div><label for="branche">Branche</label><select id="branche" name="branche"><option value="">alle</option>
        <?php foreach ($werte['branche'] as $r): ?><option value="<?= Fmt::h((string) $r) ?>"<?= $gewaehlt('branche', (string) $r) ?>><?= Fmt::h(Akquise::branchenName((string) $r)) ?></option><?php endforeach; ?></select></div>
      <div class="akq-stark"><label class="akq-haken"><input type="checkbox" name="stark" value="1"<?= !empty($filter['stark']) ? ' checked' : '' ?>> nur starke Chancen</label></div>
    </div>
    <details<?= $mehrOffen ? ' open' : '' ?>><summary class="akq-klein" style="cursor:pointer;margin-bottom:10px">Mehr Filter</summary>
      <div class="akq-filter">
        <div class="breit"><label for="q">Name, Domain oder Nummer</label><input id="q" name="q" value="<?= Fmt::h($wert('q')) ?>" placeholder="z. B. Bistrò, hotel-rosa.it, L-…"></div>
        <div><label for="kontakt">Stand</label><select id="kontakt" name="kontakt"><option value="">alle</option>
          <?php foreach (Akquise::STUFEN5 as $k => [$w]): ?><option value="<?= $k ?>"<?= $gewaehlt('kontakt', $k) ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
        <div><label for="region">Region</label><select id="region" name="region"><option value="">alle</option>
          <?php foreach ($werte['region'] as $r): ?><option<?= $gewaehlt('region', (string) $r) ?>><?= Fmt::h((string) $r) ?></option><?php endforeach; ?></select></div>
        <div><label for="kreis">Kreis / Provinz</label><select id="kreis" name="kreis"><option value="">alle</option>
          <?php foreach ($werte['kreis'] as $r): ?><option<?= $gewaehlt('kreis', (string) $r) ?>><?= Fmt::h((string) $r) ?></option><?php endforeach; ?></select></div>
        <div><label for="compliance">Ansprechen per E-Mail</label><select id="compliance" name="compliance"><option value="">egal</option>
          <?php foreach (AkquiseGate::STATUS as $k => $w): ?><option value="<?= $k ?>"<?= $gewaehlt('compliance', $k) ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
        <div><label for="audit">Prüfung</label><select id="audit" name="audit"><option value="">alle</option>
          <?php foreach (Akquise::AUDIT_STATUS as $k => $w): ?><option value="<?= $k ?>"<?= $gewaehlt('audit', $k) ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
        <div><label for="score_min">Chance ab</label><input id="score_min" name="score_min" type="number" min="0" max="100" value="<?= Fmt::h($wert('score_min')) ?>"></div>
        <div><label for="von">Gefunden ab</label><input id="von" name="von" type="date" value="<?= Fmt::h($wert('von')) ?>"></div>
        <div><label for="bis">bis</label><input id="bis" name="bis" type="date" value="<?= Fmt::h($wert('bis')) ?>"></div>
        <div><label for="sort">Reihenfolge</label><select id="sort" name="sort">
          <?php foreach (['score' => 'Beste Chance zuerst', 'neu' => 'Neueste zuerst', 'geprueft' => 'Zuletzt geprüft', 'name' => 'Name'] as $k => $w): ?>
            <option value="<?= $k ?>"<?= $gewaehlt('sort', $k) ?>><?= $w ?></option><?php endforeach; ?></select></div>
        <div><label class="akq-haken" style="margin-top:22px"><input type="checkbox" name="gesperrte" value="1"<?= !empty($filter['gesperrte']) ? ' checked' : '' ?>> gesperrte zeigen</label></div>
      </div>
    </details>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button class="knopf">Filtern</button>
      <?php if (array_filter($filter, static fn($v) => $v !== '')): ?><a class="knopf" href="<?= Fmt::h(url('akquise')) ?>">Alle zeigen</a><?php endif; ?>
      <span class="akq-klein" style="margin-left:auto"><?= (int) $liste['gesamt'] ?> Betriebe</span>
    </div>
  </form>
</div>

<div class="block">
  <?php if (!$liste['zeilen']): ?>
    <div class="leer">
      <?php if ((int) ($kz['gesamt'] ?? 0) === 0): ?>
        Noch keine Betriebe. Oben einen Ort eintragen und „Jetzt suchen“ — dein Rechner sucht heute Nacht.
      <?php else: ?>
        Kein Betrieb passt zu diesem Filter.
      <?php endif; ?>
    </div>
  <?php else: ?>
  <div class="tabellenrahmen"><table class="akq-tab">
    <thead><tr><th>Betrieb</th><th>Ort</th><th>Chance</th><th>Hauptproblem</th><th>Nächster Schritt</th></tr></thead>
    <tbody>
    <?php foreach ($liste['zeilen'] as $z):
        $top = json_decode((string) ($z['top_probleme'] ?? '[]'), true) ?: [];
        $amp = AkquiseGate::ampel($z);
        $schritt = Akquise::naechsterSchritt($z);
        $stufe = (int) $z['gesperrt'] === 1 ? 'erledigt' : Akquise::stufe5((string) $z['kontakt_status']);
        $score = $z['score'] !== null ? (int) $z['score'] : null; ?>
      <tr>
        <td><a href="<?= Fmt::h(url('akquise/' . (int) $z['id'])) ?>"><b><?= Fmt::h((string) $z['name']) ?></b></a>
          <div class="akq-klein"><?= Fmt::h((string) ($z['domain'] ?? 'keine Website')) ?> · <?= Fmt::h(Akquise::branchenName($z['branche'])) ?></div>
          <?php if ($stufe !== 'neu'): ?><span class="akq-stufe st-<?= $stufe ?>"><?= Fmt::h(Akquise::STUFEN5[$stufe][0]) ?></span><?php endif; ?></td>
        <td><?= Fmt::h((string) ($z['stadt'] ?? '—')) ?><div class="akq-klein"><?= Fmt::h((string) $z['land']) ?></div></td>
        <td><?php if ($score !== null): ?><span class="akq-chance s-<?= Fmt::h((string) $z['score_stufe']) ?>"><b><?= $score ?></b> <?= Fmt::h(Akquise::chanceWort($score)) ?></span>
            <?php else: ?><span class="akq-klein"><?= Fmt::h(Akquise::AUDIT_STATUS[(string) $z['audit_status']] ?? '—') ?></span><?php endif; ?></td>
        <td><?php if ($top): ?><?= Fmt::h((string) $top[0]) ?><?= count($top) > 1 ? ' <span class="akq-klein">+' . (count($top) - 1) . '</span>' : '' ?>
            <?php else: ?><span class="akq-klein">—</span><?php endif; ?></td>
        <td><div class="akq-los-zeile">
          <span class="akq-ampel klein <?= Fmt::h($amp['farbe']) ?>" title="<?= Fmt::h($amp['wort']) ?>"><i></i><span><?= Fmt::h($amp['wort']) ?></span></span>
          <?php if ($schritt === null): ?>
          <?php elseif ($schritt['art'] === 'post'): ?>
            <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="<?= Fmt::h($schritt['ziel']) ?>"><input type="hidden" name="firma" value="<?= (int) $z['id'] ?>">
              <input type="hidden" name="zurueck" value="akquise"><button class="knopf akq-los"><?= Fmt::h($schritt['wort']) ?></button></form>
          <?php elseif ($schritt['art'] === 'still'): ?>
            <span class="akq-klein"><?= Fmt::h($schritt['wort']) ?> …</span>
          <?php else: ?>
            <a class="knopf akq-los" href="<?= Fmt::h(url($schritt['ziel'])) ?>"<?= str_ends_with($schritt['ziel'], '/brief') ? ' target="_blank" rel="noopener"' : '' ?>><?= Fmt::h($schritt['wort']) ?></a>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php if ($liste['seiten'] > 1): ?>
      <div style="display:flex;gap:8px;justify-content:center;margin-top:14px;flex-wrap:wrap">
        <?php for ($s = 1; $s <= $liste['seiten']; $s++): ?>
          <a class="knopf<?= $s === $liste['seite'] ? ' an' : '' ?>" href="<?= Fmt::h($seitenUrl($s)) ?>"<?= $s === $liste['seite'] ? ' aria-current="page"' : '' ?>><?= $s ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
