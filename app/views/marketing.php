<?php
/**
 * Marketing · Überblick (Growth Engine, Phase 2 — 30.09.2026).
 *
 * Nur vorhandene Daten (siehe MkKennzahlen). Zwei Ansichten derselben Zahlen:
 * „Alles“ für die Arbeit, „Chef-Ansicht“ für den schnellen Blick — Umsatz,
 * Leads, Kunden, Conversion, Kosten, das Beste, Probleme, Empfehlungen.
 *
 * Erwartet: $z (von, bis, Schlüssel, Vergleich-von, Vergleich-bis), $d (MkKennzahlen::ueberblick), $sicht.
 */
[$von, $bis, $zk, $vv, $vb] = $z;
$chef = $sicht === 'chef';
$a = $d['aufrufe']; $l = $d['leads']; $s = $d['schritte']; $g = $d['geld']; $n = $d['nach']; $h = $d['hinweise'];
$kp = $d['kampagnen'] ?? ['leads' => [], 'klicks' => [], 'werbemittel' => [], 'werbemittel_klicks' => []];
/* Beste Kampagne / bestes Werbemittel: nach Leads; solange es keinen gibt, nach Klicks (und die Karte sagt es). */
$kpNachLeads = array_sum($kp['leads']) > 0; $wmNachLeads = array_sum($kp['werbemittel']) > 0;

$geld = static fn(int $c): string => Fmt::geld($c);
$zahl = static fn(int $x): string => number_format($x, 0, ',', '.');
$pz = static fn(?float $x, int $st = 1): string => $x === null ? '—' : number_format($x, $st, ',', '.') . ' %';
$datum = static fn(string $t): string => date('d.m.Y', strtotime($t));
$hier = static fn(array $mehr = []): string => url('zahlen') . '?' . http_build_query(array_filter(array_merge(
    ['z' => $zk, 'von' => $zk === 'frei' ? $von : null, 'bis' => $zk === 'frei' ? $bis : null, 'sicht' => $chef ? 'chef' : null], $mehr),
    static fn($v) => $v !== null && $v !== ''));
/* Veränderung zum Vergleichszeitraum: Pfeil, Zahl, Farbe. Bei Kosten ist „mehr“ nicht gut. */
$trend = static function (int $jetzt, int $vorher, bool $mehrIstGut = true): string {
    $t = MkKennzahlen::trend($jetzt, $vorher);
    if ($t === null) { return $jetzt > 0 ? '<span class="mk-trend">neu im Zeitraum</span>' : ''; }
    if (abs($t) < 0.5) { return '<span class="mk-trend">wie im Vorzeitraum</span>'; }
    $gut = ($t > 0) === $mehrIstGut;
    return '<span class="mk-trend ' . ($gut ? 'auf' : 'ab') . '">' . ($t > 0 ? '▲' : '▼') . ' ' . number_format(abs($t), 0, ',', '.') . ' % zum Vorzeitraum</span>';
};
$conv = MkKennzahlen::quote($g['kunden'], $l['neu']);
$roas = $g['kosten'] > 0 ? $g['umsatz'] / $g['kosten'] : null;

/* Eine Balkenliste aus wert => Zahl. */
$balken = static function (array $liste, string $leer, int $max = 8, ?callable $wort = null, ?array $zweit = null, string $zweitWort = ''): void {
    $liste = array_slice(array_filter($liste), 0, $max, true);
    if (!$liste) { echo '<p class="leise">' . Fmt::h($leer) . '</p>'; return; }
    $hoch = max($liste) ?: 1; ?>
    <div class="balkenliste">
      <?php foreach ($liste as $k => $v): $w = $wort ? $wort((string) $k) : (string) $k; ?>
        <div class="bl__zeile" title="<?= Fmt::h($w . ': ' . $v) ?>">
          <span class="bl__wort"><?= Fmt::h($w) ?><?php if ($zweit !== null): ?><i class="mk-zweit"><?= (int) ($zweit[$k] ?? 0) ?> <?= Fmt::h($zweitWort) ?></i><?php endif; ?></span>
          <span class="bl__spur"><i style="width:<?= round($v / $hoch * 100) ?>%"></i></span>
          <b class="bl__zahl"><?= number_format((int) $v, 0, ',', '.') ?></b>
        </div>
      <?php endforeach; ?>
    </div>
<?php };

/* Eine Karte „das Beste“: Sieger, Zahl, Zweiter, und ob die Menge reicht. */
$beste = static function (string $titel, ?array $b, string $einheit, string $leer, ?callable $wort = null, ?callable $fmt = null) use ($zahl): void {
    $fmt ??= static fn(int $x): string => $zahl($x); ?>
    <div class="mk-best">
      <h3><?= Fmt::h($titel) ?></h3>
      <?php if ($b === null): ?>
        <p class="mk-best__leer"><?= Fmt::h($leer) ?></p>
      <?php else: $nm = $wort ? $wort($b['name']) : $b['name']; ?>
        <b class="mk-best__name" title="<?= Fmt::h($nm) ?>"><?= Fmt::h($nm) ?></b>
        <span class="mk-best__zahl"><?= Fmt::h($fmt($b['zahl'])) ?> <?= Fmt::h($einheit) ?><?php if ($b['summe'] > $b['zahl']): ?> · <?= number_format($b['zahl'] / $b['summe'] * 100, 0, ',', '.') ?> %<?php endif; ?></span>
        <?php if ($b['zweiter'] !== null): ?><span class="mk-best__zweit">danach: <?= Fmt::h($wort ? $wort($b['zweiter']) : $b['zweiter']) ?> (<?= Fmt::h($fmt($b['zweiter_zahl'])) ?>)</span><?php endif; ?>
        <?php if ($b['wenig']): ?><span class="marke2 warnung mk-best__marke">wenige Daten</span><?php endif; ?>
      <?php endif; ?>
    </div>
<?php };
$ctaWort = static fn(string $k): string => MkKennzahlen::CTA[$k] ?? $k;
$sozial = array_intersect_key($a['plattformen'], array_flip(MkKennzahlen::SOZIAL));
/* „Direkt / unbekannt“ ist keine Quelle, sondern das Fehlen einer — als Stärkste wäre es eine leere Aussage. */
$quellen = array_diff_key($a['plattformen'], ['Direkt / unbekannt' => 0]);
?>
<?php require __DIR__ . '/mk_stil.php'; ?>
<?php /* G2: „Diese Woche werben“ steht seit 01.10.2026 auf Start (Schritt 2) — hier nur Zahlen. */ ?>

<div class="mk-kopf">
  <div>
    <h1>Marketing<?= $chef ? ' · Chef-Ansicht' : '' ?></h1>
    <div class="weg"><?= Fmt::h($datum($von)) ?><?= $von !== $bis ? ' – ' . Fmt::h($datum($bis)) : '' ?> · verglichen mit <?= Fmt::h($datum($vv)) ?><?= $vv !== $vb ? ' – ' . Fmt::h($datum($vb)) : '' ?></div>
  </div>
  <nav class="mk-chips mk-sicht" aria-label="Ansicht">
    <a href="<?= Fmt::h($hier(['sicht' => null])) ?>"<?= !$chef ? ' aria-current="page"' : '' ?>>Alles</a>
    <a href="<?= Fmt::h($hier(['sicht' => 'chef'])) ?>"<?= $chef ? ' aria-current="page"' : '' ?>>Chef-Ansicht</a>
  </nav>
</div>

<form class="mk-filter" method="get" action="<?= Fmt::h(url('zahlen')) ?>">
  <nav class="mk-chips" aria-label="Zeitraum">
    <?php foreach (MkKennzahlen::ZEITRAEUME as $zs => $zw): if ((string) $zs === 'frei') { continue; } ?>
      <a href="<?= Fmt::h($hier(['z' => (string) $zs, 'von' => null, 'bis' => null])) ?>"<?= $zk === (string) $zs ? ' aria-current="page"' : '' ?>><?= Fmt::h($zw) ?></a>
    <?php endforeach; ?>
  </nav>
  <input type="hidden" name="z" value="frei">
  <?php if ($chef): ?><input type="hidden" name="sicht" value="chef"><?php endif; ?>
  <div class="feld"><label for="mk_von" class="leise" style="margin:0">von</label><input id="mk_von" type="date" name="von" value="<?= Fmt::h($von) ?>"></div>
  <div class="feld"><label for="mk_bis" class="leise" style="margin:0">bis</label><input id="mk_bis" type="date" name="bis" value="<?= Fmt::h($bis) ?>"></div>
  <button class="knopf">Zeitraum anzeigen</button>
</form>

<div class="karten mk-karten<?= $chef ? ' mk-drei' : '' ?>">
  <div class="karte"><h3>Umsatz</h3><div class="wert"><?= Fmt::h($geld($g['umsatz'])) ?></div>
    <div class="neben"><?= $zahl($g['zahlungen']) ?> Zahlungen<?= $g['partner'] > 0 ? ' · ' . Fmt::h($geld($g['partner'])) . ' über Partner' : '' ?></div><?= $trend($g['umsatz'], $g['umsatz_vorher']) ?></div>
  <div class="karte"><h3>Neue Leads</h3><div class="wert"><?= $zahl($l['neu']) ?></div>
    <div class="neben"><?= $zahl($l['qualifiziert']) ?> qualifiziert (<?= Fmt::h($pz(MkKennzahlen::quote($l['qualifiziert'], $l['neu']), 0)) ?>)</div><?= $trend($l['neu'], $l['vorher']) ?></div>
  <div class="karte"><h3>Neue Kunden</h3><div class="wert"><?= $zahl($g['kunden']) ?></div>
    <div class="neben">erste Zahlung im Zeitraum</div><?= $trend($g['kunden'], $g['kunden_vorher']) ?></div>
  <div class="karte"><h3>Conversion</h3><div class="wert"><?= Fmt::h($pz($conv)) ?></div>
    <div class="neben">neue Kunden je neuem Lead</div></div>
  <?php if (!$chef): ?>
  <div class="karte"><h3>Aufrufe</h3><div class="wert"><?= $zahl($a['summe']) ?></div>
    <div class="neben">heute <?= $zahl($a['heute']) ?> · 7 Tage <?= $zahl($a['tage7']) ?> · 30 Tage <?= $zahl($a['tage30']) ?></div><?= $trend($a['summe'], $a['vorher']) ?></div>
  <div class="karte"><h3>Ø Auftragswert</h3><div class="wert"><?= $g['auftraege'] > 0 ? Fmt::h($geld($g['auftragswert'])) : '—' ?></div>
    <div class="neben"><?= $zahl($g['auftraege']) ?> Aufträge im Zeitraum</div></div>
  <?php endif; ?>
  <div class="karte"><h3>Marketingkosten</h3><div class="wert"><?= Fmt::h($geld($g['kosten'])) ?></div>
    <div class="neben">Ausgaben „Werbung“, netto</div><?= $g['kosten'] > 0 || $g['kosten_vorher'] > 0 ? $trend($g['kosten'], $g['kosten_vorher'], false) : '' ?></div>
  <div class="karte"><h3>Rendite</h3><div class="wert mk-klein"><?= $roas !== null ? 'ROAS ' . number_format($roas, 1, ',', '.') : '—' ?></div>
    <div class="neben"><?php if ($g['kosten'] > 0): ?>pro Lead <?= $l['neu'] > 0 ? Fmt::h($geld(intdiv($g['kosten'], $l['neu']))) : '—' ?> · pro Kunde <?= $g['kunden'] > 0 ? Fmt::h($geld(intdiv($g['kosten'], $g['kunden']))) : '—' ?><?php else: ?>erscheint, sobald Werbeausgaben erfasst sind<?php endif; ?></div></div>
</div>

<div class="mk-zwei">
  <div class="block">
    <h2>Was klemmt</h2>
    <?php if ($h['probleme']): ?><ul class="mk-liste probleme"><?php foreach ($h['probleme'] as $t): ?><li><?= Fmt::h($t) ?></li><?php endforeach; ?></ul>
    <?php else: ?><p class="leise" style="margin:0">Nichts, was gerade auf dich wartet.</p><?php endif; ?>
  </div>
  <div class="block">
    <h2>Was du tun kannst</h2>
    <?php if ($h['empfehlungen']): ?><ul class="mk-liste empf"><?php foreach ($h['empfehlungen'] as $t): ?><li><?= Fmt::h($t) ?></li><?php endforeach; ?></ul>
    <?php else: ?><p class="leise" style="margin:0">Keine Empfehlung aus den Zahlen dieses Zeitraums.</p><?php endif; ?>
  </div>
</div>

<div class="block">
  <h2>Das Beste im Zeitraum</h2>
  <div class="mk-besten<?= $chef ? ' mk-vier' : '' ?>">
    <?php $beste('Stärkste Quelle', MkKennzahlen::erster($quellen), 'Aufrufe', 'Noch keine Aufrufe mit erkennbarer Herkunft.'); ?>
    <?php $beste('Bester Weg zum Lead', MkKennzahlen::erster($l['wege_gut'], 10), 'qualifizierte Leads', 'Noch kein qualifizierter Lead im Zeitraum.'); ?>
    <?php $beste('Bester Partner', MkKennzahlen::erster(MkKennzahlen::alsListe($n['partner']), 3), '', 'Kein Partner-Umsatz im Zeitraum.', null, $geld); ?>
    <?php $beste('Beste Kampagne', MkKennzahlen::erster($kpNachLeads ? $kp['leads'] : $kp['klicks'], 10), $kpNachLeads ? 'Leads' : 'Klicks (noch kein Lead)', 'Noch kein Klick auf einen Kampagnenlink.'); ?>
    <?php if (!$chef): ?>
      <?php $beste('Bestes Werbemittel', MkKennzahlen::erster($wmNachLeads ? $kp['werbemittel'] : $kp['werbemittel_klicks'], 10), $wmNachLeads ? 'Leads' : 'Klicks (noch kein Lead)', 'Noch kein Klick auf den Link eines Werbemittels.'); ?>
      <?php $beste('Stärkste Social-Plattform', MkKennzahlen::erster($sozial), 'Aufrufe', 'Kein Aufruf aus sozialen Netzwerken.'); ?>
      <?php $beste('Meistbesuchte Branchenseite', MkKennzahlen::erster($a['landing']), 'Aufrufe', 'Keine Branchenseite aufgerufen.'); ?>
      <?php $beste('Bester Knopf zum Kontakt', MkKennzahlen::erster($d['knoepfe'], 10), 'Mal gedrückt', 'Kein Kontakt-Knopf gedrückt.', $ctaWort); ?>
      <?php $beste('Beste Branche', MkKennzahlen::erster(MkKennzahlen::alsListe($n['branche']), 3), '', 'Kein Umsatz mit hinterlegter Branche.', null, $geld); ?>
      <?php $beste('Bester Ort', MkKennzahlen::erster(MkKennzahlen::alsListe($n['ort']), 3), '', 'Kein Umsatz mit hinterlegtem Ort.', null, $geld); ?>
    <?php endif; ?>
  </div>
  <p class="leise" style="margin:12px 0 0">Quellen, Social-Plattformen und Branchenseiten sind nach Aufrufen gewertet (die anonyme Zählung kennt keine Leads). Kampagnen und Werbemittel nach Leads aus ihren eigenen Links — <a href="<?= Fmt::h(url('kampagnen')) ?>">Kampagnen</a>.</p>
</div>

<?php if (!$chef):
  $stufen = [
    ['Aufrufe der Website', $a['summe']], ['neue Leads (E-Mail hinterlassen)', $l['neu']], ['davon qualifiziert', $l['qualifiziert']],
    ['Angebote verschickt', $s['angebote']], ['Aufträge', $s['auftraege']], ['neue Kunden (erste Zahlung)', $g['kunden']],
  ];
  $max = max(1, $stufen[0][1]); ?>
<div class="block">
  <h2>Weg zum Kunden <span class="mehr">was im Zeitraum passiert ist</span></h2>
  <div class="trichter mk-trichter">
    <?php foreach ($stufen as $i => [$wort, $wert]): $vor = $i > 0 ? (int) $stufen[$i - 1][1] : 0; $q = $i > 0 ? MkKennzahlen::quote($wert, $vor) : null; ?>
      <div class="trichter__stufe">
        <span class="trichter__zahl"><?= $zahl($wert) ?></span>
        <span class="trichter__balken" aria-hidden="true"><span style="width:<?= max(1, round($wert / $max * 100)) ?>%"></span></span>
        <span class="trichter__wort"><?= Fmt::h($wort) ?><?php if ($i > 0 && $q !== null): ?> <i>· <?= $q <= 100 ? Fmt::h($pz($q)) . ' der Stufe davor' : 'mehr als die Stufe davor (aus früheren Zeiträumen)' ?></i><?php endif; ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="kacheln" style="margin:16px 0 0">
    <div class="kachel"><span>Website-Checks</span><b><?= $zahl($s['checks']) ?></b><span class="leise"><?= $zahl($s['checks_kontakt']) ?> mit Kontakt</span></div>
    <div class="kachel"><span>E-Mail-Einstiege</span><b><?= $zahl($s['zugaenge']) ?></b></div>
    <div class="kachel"><span>Preisrechner</span><b><?= $zahl($s['rechner_fertig']) ?></b><span class="leise">abgeschlossen · <?= $zahl($s['rechner_begonnen']) ?> begonnen</span></div>
    <div class="kachel"><span>Fragebögen</span><b><?= $zahl($s['fragebogen_fertig']) ?></b><span class="leise">abgeschickt · <?= $zahl($s['fragebogen_neu']) ?> angelegt</span></div>
    <div class="kachel"><span>Kontaktanfragen</span><b><?= $zahl($s['anfragen']) ?></b></div>
    <div class="kachel"><span>Termine</span><b><?= $zahl($s['termine']) ?></b><span class="leise">gebucht, nicht abgesagt</span></div>
  </div>
</div>

<div class="mk-zwei">
  <div class="block">
    <h2>Woher die Aufrufe kommen <a class="mehr" href="<?= Fmt::h(url('statistiken')) ?>">Besucher →</a></h2>
    <?php $balken($a['plattformen'], $a['da'] ? 'Keine Aufrufe im Zeitraum.' : 'Die Zähldatei besuche.csv gibt es hier noch nicht.'); ?>
    <?php if ($a['kampagnen']): ?><h3 class="leise" style="margin:16px 0 8px;font-size:12px">Kampagnen-Kennung (utm_source)</h3><?php $balken($a['kampagnen'], '', 6); ?><?php endif; ?>
  </div>
  <div class="block">
    <h2>Wie Leads hereinkommen</h2>
    <div class="mk-breit"><?php $balken($l['wege'], 'Kein neuer Lead im Zeitraum.', 8, null, $l['wege_gut'], 'qualifiziert'); ?></div>
    <p class="leise" style="margin:12px 0 0">Gezählt wird jede E-Mail-Adresse einmal, beim ersten Auftauchen. Qualifiziert heißt: Preisrechner abgeschlossen, Fragebogen abgeschickt, Termin gebucht, Anfrage geschrieben oder Angebot erhalten.</p>
  </div>
</div>

<?php
  /* Verlauf: bis zwei Monate je Tag, darüber je Woche (Montag). */
  $jeWoche = (strtotime($bis) - strtotime($von)) / 86400 > 62;
  $saeulen = []; $punkte = [];
  for ($t = strtotime($von); $t <= strtotime($bis); $t += 86400) {
      $tag = date('Y-m-d', $t);
      $k = $jeWoche ? date('Y-m-d', strtotime($tag . ' -' . ((int) date('N', $t) - 1) . ' days')) : $tag;
      $saeulen[$k] = ($saeulen[$k] ?? 0) + (int) ($a['tage'][$tag] ?? 0);
      $punkte[$k] = ($punkte[$k] ?? 0) + (int) ($l['tage'][$tag] ?? 0);
  }
  $sn = count($saeulen); $sHoch = max(1, max($saeulen ?: [0])); $pHoch = max(1, max($punkte ?: [0]));
  $breite = 100 / max(1, $sn); $heuteK = $jeWoche ? date('Y-m-d', strtotime('monday this week')) : date('Y-m-d');
?>
<div class="block">
  <h2>Verlauf <span class="leise mk-inline" style="font-weight:400;font-size:12.5px"><?= $jeWoche ? 'je Woche' : 'je Tag' ?></span>
    <span class="mk-legende"><span><i></i>Aufrufe</span><span><i class="p"></i>neue Leads</span></span></h2>
  <?php if ($sn > 1): ?>
  <svg class="mk-verlauf" viewBox="0 0 100 60" preserveAspectRatio="none" role="img" aria-label="Aufrufe und neue Leads im Verlauf">
    <?php $i = 0; foreach ($saeulen as $k => $v): $hh = $v / $sHoch * 52; ?>
      <rect class="<?= $k === $heuteK ? 'heute' : '' ?>" x="<?= round($i * $breite + $breite * .15, 3) ?>" y="<?= round(60 - $hh, 3) ?>" width="<?= round($breite * .7, 3) ?>" height="<?= round(max($v > 0 ? .6 : 0, $hh), 3) ?>"><title><?= Fmt::h(date('d.m.', strtotime($k)) . ': ' . $v . ' Aufrufe, ' . $punkte[$k] . ' Leads') ?></title></rect>
    <?php $i++; endforeach; ?>
  </svg>
  <?php /* Punkte als HTML darüber: Kreise in einem verzerrten SVG würden zu Ellipsen. */ ?>
  <div style="position:relative;height:0">
    <?php $i = 0; foreach ($punkte as $k => $p): if ($p > 0): ?>
      <span title="<?= Fmt::h(date('d.m.', strtotime($k)) . ': ' . $p . ' neue Leads') ?>" style="position:absolute;left:calc(<?= round(($i + .5) * $breite, 3) ?>% - 5px);bottom:<?= round(8 + $p / $pHoch * 120) ?>px;width:10px;height:10px;border-radius:50%;background:var(--gruen);box-shadow:0 0 0 3px rgba(74,222,128,.2)"></span>
    <?php endif; $i++; endforeach; ?>
  </div>
  <div class="mk-achse"><span><?= Fmt::h(date('d.m.', strtotime(array_key_first($saeulen)))) ?></span><span><?= Fmt::h(date('d.m.', strtotime(array_key_last($saeulen)))) ?></span></div>
  <?php else: ?><p class="leise" style="margin:0">Für einen Verlauf einen längeren Zeitraum wählen.</p><?php endif; ?>
</div>

<div class="mk-zwei">
  <div class="block">
    <h2>Akquise <a class="mehr" href="<?= Fmt::h(url('akquise')) ?>">Neue Kunden finden →</a></h2>
    <div class="kacheln" style="margin:0">
      <div class="kachel"><span>Betriebe geprüft</span><b><?= $zahl($d['akquise']['geprueft']) ?></b></div>
      <div class="kachel"><span>Einwilligungen bestätigt</span><b><?= $zahl($d['akquise']['eingewilligt']) ?></b></div>
      <div class="kachel"><span>Kontakt aus Website-Check</span><b><?= $zahl($d['akquise']['kontakt']) ?></b></div>
    </div>
  </div>
  <div class="block">
    <h2>Kampagnen, Partner und Kosten <a class="mehr" href="<?= Fmt::h(url('kampagnen')) ?>">Kampagnen →</a></h2>
    <div class="kacheln" style="margin:0 0 12px">
      <div class="kachel"><span>Umsatz über Kampagnen</span><b><?= Fmt::h($geld((int) ($g['kampagnen'] ?? 0))) ?></b><span class="leise"><?= Fmt::h($pz(MkKennzahlen::quote((int) ($g['kampagnen'] ?? 0), $g['umsatz']), 0)) ?> vom Umsatz</span></div>
      <div class="kachel"><span>Umsatz über Partner</span><b><?= Fmt::h($geld($g['partner'])) ?></b><span class="leise"><?= Fmt::h($pz(MkKennzahlen::quote($g['partner'], $g['umsatz']), 0)) ?> vom Umsatz · <a href="<?= Fmt::h(url('tracking')) ?>">Tracking</a></span></div>
    </div>
    <?php $balken(MkKennzahlen::alsListe($g['kosten_je_anbieter']), 'Keine Werbeausgaben im Zeitraum — unter Geld → Ausgaben mit der Kategorie „Werbung“ eintragen.', 6); ?>
  </div>
</div>

<details class="block mk-quelle">
  <summary>Woher diese Zahlen kommen</summary>
  <p style="margin:10px 0 0">Aufrufe: jede geöffnete Seite, gezählt ohne Keks und ohne IP (besuche.csv) — wer drei Seiten liest, zählt dreimal. Leads: jede freiwillig hinterlassene E-Mail-Adresse (E-Mail-Einstieg, Kontaktformular, Website-Check, Terminbuchung, Preisrechner), einmal je Adresse. Umsatz: bezahlte Zahlungen ohne Beispieldaten. Neue Kunden: erste bezahlte Zahlung im Zeitraum. Marketingkosten: Ausgaben der Kategorie „Werbung“ (netto). Partner-Umsatz: Zahlungen mit Partner-Provision. Knöpfe: demo.csv. Der Vergleich nutzt einen gleich langen Zeitraum direkt davor.</p>
</details>
<?php endif; ?>
