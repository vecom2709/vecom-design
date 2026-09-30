<?php
/**
 * Marketing · Kampagnen (Growth Engine Phase 3 — 30.09.2026).
 *
 * Jede Kampagne ist ein eigener Link /k/CODE. Die Zahlen kommen aus der Spur:
 * Klick → Besuch → Preisrechner → Lead → Angebot → Kunde → Umsatz, dazu die
 * eingetragenen Kosten. Nichts geschätzt.
 *
 * Erwartet: $z (Zeitraum), $f (Filter plattform/status), $l (MkKampagne::liste).
 */
[$von, $bis, $zk] = $z;
$s = $l['summe'];
$geld = static fn(int $c): string => Fmt::geld($c);
$zahl = static fn(int $x): string => number_format($x, 0, ',', '.');
$datum = static fn(string $t): string => date('d.m.Y', strtotime($t));
$hier = static fn(array $mehr = []): string => url('kampagnen') . '?' . http_build_query(array_filter(array_merge(
    ['z' => $zk, 'von' => $zk === 'frei' ? $von : null, 'bis' => $zk === 'frei' ? $bis : null, 'plattform' => $f['plattform'] ?: null, 'status' => $f['status'] ?: null, 'branche' => $f['branche'] ?: null], $mehr),
    static fn($v) => $v !== null && $v !== ''));
$roas = static fn(int $umsatz, int $kosten): string => $kosten > 0 ? number_format($umsatz / $kosten, 1, ',', '.') : '—';
$jeLead = static fn(int $kosten, int $leads) => $kosten > 0 && $leads > 0 ? Fmt::geld(intdiv($kosten, $leads)) : '—';
$statusMarke = static fn(string $st): string => '<span class="marke2 ' . ($st === 'aktiv' ? 'gut' : ($st === 'pausiert' ? 'warnung' : '')) . '">' . Fmt::h(MkKampagne::STATUS[$st] ?? $st) . '</span>';
$leer = $l['kampagnen'] === [] && $f['plattform'] === '' && $f['status'] === '' && $f['branche'] === '';
$branchen = MkKampagne::branchen();
$lauf = ['vor' => 'startet am ', 'laeuft' => 'läuft', 'vorbei' => 'abgelaufen', 'offen' => ''];
/* Budget als kleiner Balken mit Farbe der Stufe. */
$budgetZeile = static function (?array $b): string {
    if ($b === null) { return ''; }
    $farbe = ['ok' => 'var(--gruen)', 'knapp' => 'var(--gelb)', 'erreicht' => 'var(--rot)'][$b['stufe']];
    return '<span class="mk-budget" title="' . Fmt::h(Fmt::geld($b['ausgegeben']) . ' von ' . Fmt::geld($b['grenze']) . ($b['art'] === 'monat' ? ' (dieser Monat)' : '')) . '">'
        . '<i style="width:' . min(100, (int) round($b['anteil'])) . '%;background:' . $farbe . '"></i></span><span class="mk-code">' . number_format($b['anteil'], 0, ',', '.') . ' % von ' . Fmt::h(Fmt::geld($b['grenze'])) . '</span>';
};
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1>Kampagnen</h1>
    <div class="weg"><?= Fmt::h($datum($von)) ?><?= $von !== $bis ? ' – ' . Fmt::h($datum($bis)) : '' ?> · ein eigener Link je Beitrag, Anzeige oder Flyer — jeder Klick, Lead und Euro landet bei seiner Kampagne</div>
  </div>
  <a class="knopf haupt" href="#neu">Neue Kampagne</a>
</div>

<?php if (!$leer): ?>
<form class="mk-filter" method="get" action="<?= Fmt::h(url('kampagnen')) ?>">
  <nav class="mk-chips" aria-label="Zeitraum">
    <?php foreach (MkKennzahlen::ZEITRAEUME as $zs => $zw): if ((string) $zs === 'frei') { continue; } ?>
      <a href="<?= Fmt::h($hier(['z' => (string) $zs, 'von' => null, 'bis' => null])) ?>"<?= $zk === (string) $zs ? ' aria-current="page"' : '' ?>><?= Fmt::h($zw) ?></a>
    <?php endforeach; ?>
  </nav>
  <input type="hidden" name="z" value="frei">
  <div class="feld"><label for="kp_von" class="leise" style="margin:0">von</label><input id="kp_von" type="date" name="von" value="<?= Fmt::h($von) ?>"></div>
  <div class="feld"><label for="kp_bis" class="leise" style="margin:0">bis</label><input id="kp_bis" type="date" name="bis" value="<?= Fmt::h($bis) ?>"></div>
  <select name="plattform" aria-label="Plattform" style="width:auto"><option value="">Alle Plattformen</option>
    <?php foreach (MkKampagne::PLATTFORMEN as $pk => $pw): ?><option value="<?= $pk ?>"<?= $f['plattform'] === $pk ? ' selected' : '' ?>><?= Fmt::h($pw) ?></option><?php endforeach; ?></select>
  <select name="branche" aria-label="Branche" style="width:auto"><option value="">Alle Branchen</option>
    <?php foreach ($branchen as $bk => $bw): ?><option value="<?= Fmt::h($bk) ?>"<?= $f['branche'] === $bk ? ' selected' : '' ?>><?= Fmt::h($bw) ?></option><?php endforeach; ?></select>
  <select name="status" aria-label="Status" style="width:auto"><option value="">Jeder Status</option>
    <?php foreach (MkKampagne::STATUS as $sk => $sw): ?><option value="<?= $sk ?>"<?= $f['status'] === $sk ? ' selected' : '' ?>><?= Fmt::h($sw) ?></option><?php endforeach; ?></select>
  <button class="knopf">Anzeigen</button>
</form>

<div class="karten mk-karten">
  <div class="karte"><h3>Klicks</h3><div class="wert"><?= $zahl($s['klicks']) ?></div><div class="neben"><?= $zahl($s['besuche']) ?> Besuche (ohne Mehrfachklicks)</div></div>
  <div class="karte"><h3>Leads</h3><div class="wert"><?= $zahl($s['leads']) ?></div><div class="neben"><?= $zahl($s['rechner']) ?> Preisrechner abgeschlossen</div></div>
  <div class="karte"><h3>Kunden</h3><div class="wert"><?= $zahl($s['kunden']) ?></div><div class="neben"><?= $zahl($s['angebote']) ?> Angebote · <?= $zahl($s['auftraege']) ?> Aufträge</div></div>
  <div class="karte"><h3>Umsatz</h3><div class="wert"><?= Fmt::h($geld($s['umsatz'])) ?></div><div class="neben">Kosten <?= Fmt::h($geld($s['kosten'])) ?> · ROAS <?= Fmt::h($roas($s['umsatz'], $s['kosten'])) ?> · pro Lead <?= Fmt::h($jeLead($s['kosten'], $s['leads'])) ?></div></div>
</div>

<div class="block">
  <h2>Alle Kampagnen <span class="mehr"><?= count($l['kampagnen']) ?></span></h2>
  <?php if (!$l['kampagnen']): ?>
    <p class="leise" style="margin:0">Keine Kampagne passt zu diesem Filter.</p>
  <?php else: ?>
  <div class="tabellenrahmen">
    <table class="mk-tab">
      <thead><tr><th>Kampagne</th><th>Plattform</th><th>Status</th><th class="num">Ziel</th><th class="num">Klicks</th><th class="num">Leads</th><th class="num">Kunden</th><th class="num">Umsatz</th><th class="num">Kosten / Budget</th><th class="num">pro Lead</th></tr></thead>
      <tbody>
        <?php foreach ($l['kampagnen'] as $k): ?>
          <tr>
            <td class="mk-name"><a href="<?= Fmt::h(url('kampagnen/' . (int) $k['id']) . ($zk !== '30' ? '?' . http_build_query(['z' => $zk, 'von' => $zk === 'frei' ? $von : null, 'bis' => $zk === 'frei' ? $bis : null]) : '')) ?>"><b><?= Fmt::h($k['name']) ?></b></a><br><span class="mk-code">/k/<?= Fmt::h($k['code']) ?><?= (int) $k['werbemittel'] > 0 ? ' · ' . (int) $k['werbemittel'] . ' Werbemittel' : '' ?></span>
              <?php if ($k['branche'] !== '' || $k['cta'] !== ''): ?><br><span class="mk-fein"><?= Fmt::h(implode(' · ', array_filter([$branchen[$k['branche']] ?? '', $k['cta'] === 'eigen' ? '„' . $k['cta_text'] . '“' : (MkKampagne::CTA[$k['cta']] ?? '')]))) ?></span><?php endif; ?></td>
            <td><?= Fmt::h(MkKampagne::PLATTFORMEN[$k['plattform']] ?? $k['plattform']) ?></td>
            <td><?= $statusMarke((string) $k['status']) ?><?php if ($k['laufzeit'] !== 'offen'): ?><br><span class="mk-code"><?= Fmt::h($lauf[$k['laufzeit']] . ($k['laufzeit'] === 'vor' ? $datum((string) $k['start_am']) : ($k['laufzeit'] === 'laeuft' && $k['ende_am'] ? ' bis ' . $datum((string) $k['ende_am']) : ''))) ?></span><?php endif; ?></td>
            <td class="num"><b><?= $zahl((int) $k['zielwert']) ?></b><br><span class="mk-code"><?= Fmt::h(MkKampagne::ZIEL_ARTEN[$k['ziel_art']][0] ?? '') ?></span></td>
            <td class="num"><?= $zahl((int) $k['klicks']) ?></td>
            <td class="num"><?= $zahl((int) $k['leads']) ?></td>
            <td class="num"><?= $zahl((int) $k['kunden']) ?></td>
            <td class="num"><?= Fmt::h($geld((int) $k['umsatz'])) ?><?= (int) $k['kosten'] > 0 ? '<br><span class="mk-code">ROAS ' . Fmt::h($roas((int) $k['umsatz'], (int) $k['kosten'])) . '</span>' : '' ?></td>
            <td class="num"><?= (int) $k['kosten'] > 0 ? Fmt::h($geld((int) $k['kosten'])) : '—' ?><?= $k['budget'] ? '<br>' . $budgetZeile($k['budget']) : '' ?></td>
            <td class="num"><?= Fmt::h($jeLead((int) $k['kosten'], (int) $k['leads'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php if (count($l['kampagnen']) > 1): ?>
<div class="block">
  <h2>Vergleich <span class="mehr">dieselben Zahlen nach Branche, Handlungsaufruf und Plattform</span></h2>
  <?php foreach (['branche' => ['Branche', $branchen], 'cta' => ['Handlungsaufruf', MkKampagne::CTA], 'plattform' => ['Plattform', MkKampagne::PLATTFORMEN]] as $gk => [$gw, $gNamen]): ?>
    <div class="tabellenrahmen" style="margin-bottom:14px"><table class="mk-tab">
      <thead><tr><th><?= Fmt::h($gw) ?></th><th class="num">Kampagnen</th><th class="num">Klicks</th><th class="num">Leads</th><th class="num">Kunden</th><th class="num">Umsatz</th><th class="num">Kosten</th><th class="num">pro Lead</th></tr></thead>
      <tbody>
        <?php foreach ($l['gruppen'][$gk] as $gs => $gz): ?>
          <tr><td class="mk-name"><?= Fmt::h($gs === '—' ? 'nicht festgelegt' : ($gNamen[$gs] ?? $gs)) ?></td><td class="num"><?= $zahl($gz['kampagnen']) ?></td><td class="num"><?= $zahl($gz['klicks']) ?></td>
            <td class="num"><?= $zahl($gz['leads']) ?></td><td class="num"><?= $zahl($gz['kunden']) ?></td><td class="num"><?= Fmt::h($geld($gz['umsatz'])) ?></td>
            <td class="num"><?= $gz['kosten'] > 0 ? Fmt::h($geld($gz['kosten'])) : '—' ?></td><td class="num"><?= Fmt::h($jeLead($gz['kosten'], $gz['leads'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endforeach; ?>
  <p class="mk-fein" style="margin:0">Bei wenigen Leads ist ein Unterschied Zufall — erst ab etwa 20 Leads je Zeile lohnt ein Schluss.</p>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="block" id="neu">
  <h2>Neue Kampagne</h2>
  <?php if ($leer): ?>
    <p style="margin:0 0 14px;max-width:62ch;line-height:1.6">Noch keine Kampagne. Leg für jede Maßnahme einen eigenen Link an — einen Instagram-Beitrag, eine Anzeige, einen Newsletter, einen Flyer. Wer darüber kommt, wird anonym mitgezählt: Klick, Preisrechner, Lead, Angebot, Kunde, Umsatz. So siehst du, was wirklich Kunden bringt.</p>
  <?php endif; ?>
  <form method="post" action="<?= Fmt::h(url('kampagnen')) ?>" class="mk-formular">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>">
    <input type="hidden" name="tat" value="kampagne_anlegen">
    <div class="feld"><label for="kn_name">Name</label><input id="kn_name" name="name" required maxlength="120" placeholder="z. B. Restaurants Herbst"></div>
    <div class="feld"><label for="kn_pl">Plattform</label><select id="kn_pl" name="plattform" required>
      <?php foreach (MkKampagne::PLATTFORMEN as $pk => $pw): ?><option value="<?= $pk ?>"><?= Fmt::h($pw) ?></option><?php endforeach; ?></select></div>
    <div class="feld"><label for="kn_ziel">Zielseite</label><input id="kn_ziel" name="ziel" list="kn_ziele" value="/" maxlength="180" required pattern="/[A-Za-z0-9/_.\-]*">
      <datalist id="kn_ziele"><?php foreach (MkKampagne::ZIELE as $zp => $zw): ?><option value="<?= Fmt::h($zp) ?>"><?= Fmt::h($zw) ?></option><?php endforeach; ?></datalist></div>
    <div class="feld"><label for="kn_code">Kurz-Code <span class="mk-fein">(frei lassen = aus dem Namen)</span></label><input id="kn_code" name="code" maxlength="24" pattern="[a-z0-9][a-z0-9\-]{2,23}" placeholder="restaurants-herbst"></div>
    <?php $kf = null; $kfId = 'kn'; require __DIR__ . '/mk_kampagne_felder.php'; ?>
    <div class="feld breit"><label for="kn_notiz">Notiz <span class="mk-fein">(nur für dich)</span></label><input id="kn_notiz" name="notiz" maxlength="500"></div>
    <div class="breit"><button class="knopf haupt">Kampagne anlegen und Link erzeugen</button></div>
  </form>
</div>
