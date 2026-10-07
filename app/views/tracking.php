<?php
/**
 * Partner-Tracking (30.09.2026, Uwe: „Alles“).
 *
 * Nur echte Daten: jede Zahl und jeder Schritt der Journey stammt aus
 * spur_besuche / spur_ereignisse (ältere Tage aus spur_tage). Ein Besucher
 * heißt „VIS-…“, bis er freiwillig seine E-Mail einträgt — dann steht der
 * Kunde daneben, vorher nie ein Name.
 *
 * Erwartet: $z (Zeitraum [von, bis, schlüssel]), $f (Filter), $k (Kennzahlen),
 * $heute (Partner mit Zahlen heute), $tabelle, $funnel, $herkunft, $besuche,
 * $live, $partnerListe, $detail (null|array), $journey (null|array), $einst.
 */
[$von, $bis, $zk] = $z;
$geld = static fn(int $c): string => Rechte::betrag($c);   // Phase 9: Mitarbeit sieht keine Beträge
$pz = static fn(float $x): string => number_format($x, 2, ',', '.') . ' %';
$datumKurz = static fn(string $d): string => date('d.m.', strtotime($d));
$uhr = static fn(string $d): string => date('H:i', strtotime($d));
$dauer = static function (string $a, string $b): string {
    $s = max(0, strtotime($b) - strtotime($a));
    return sprintf('%02d:%02d', intdiv($s, 60), $s % 60) . ($s >= 3600 ? ' (' . intdiv($s, 3600) . ' h)' : '');
};
$hier = static fn(array $mehr = []): string => url('tracking') . '?' . http_build_query(array_filter(array_merge(
    ['z' => $zk, 'von' => $zk === 'frei' ? $von : null, 'bis' => $zk === 'frei' ? $bis : null,
     'partner' => $f['partner'] ?: null, 'land' => $f['land'] ?: null, 'geraet' => $f['geraet'] ?: null, 'quelle' => $f['quelle'] ?: null, 'status' => $f['status'] ?: null],
    $mehr), static fn($v) => $v !== null && $v !== ''));
$balken = static function (array $zeilen, callable $wort, string $leer): void {
    if (!$zeilen) { echo '<p class="leise">' . Fmt::h($leer) . '</p>'; return; }
    $max = max(array_map(static fn($r) => (int) $r['n'], $zeilen)) ?: 1; ?>
    <div class="balkenliste">
      <?php foreach ($zeilen as $r): $w = $wort((string) $r['wert']); ?>
        <div class="bl__zeile" title="<?= Fmt::h($w . ': ' . $r['n']) ?>">
          <span class="bl__wort"><?= Fmt::h($w) ?></span>
          <span class="bl__spur"><i style="width:<?= round((int) $r['n'] / $max * 100) ?>%"></i></span>
          <b class="bl__zahl"><?= (int) $r['n'] ?></b>
        </div>
      <?php endforeach; ?>
    </div>
<?php };
$statusMarke = static fn(string $s): string => '<span class="marke2 ' . (in_array($s, ['kunde', 'abgeschlossen'], true) ? 'gut' : (in_array($s, ['anfrage', 'angebot', 'rechner'], true) ? 'warnung' : '')) . '">' . Fmt::h(Spur::STATUS[$s] ?? $s) . '</span>';
$trichter = static function (array $stufen): void { $max = max(1, (int) ($stufen[0]['n'] ?? 0)); ?>
  <div class="trichter st-trichter">
    <?php foreach ($stufen as $i => $s): ?>
      <div class="trichter__stufe">
        <span class="trichter__zahl"><?= (int) $s['n'] ?></span>
        <span class="trichter__balken" aria-hidden="true"><span style="width:<?= max(1, round($s['n'] / $max * 100)) ?>%"></span></span>
        <span class="trichter__wort"><?= Fmt::h($s['name']) ?><?php if ($i > 0): ?> <i>· <?= number_format($s['quote'], 1, ',', '') ?> % weiter, <?= number_format($s['abbruch'], 1, ',', '') ?> % Abbruch</i><?php endif; ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php };
?>
<style>
  .st-filter{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin:0 0 16px}
  .st-chips{display:flex;flex-wrap:wrap;gap:6px}
  .st-chips a{padding:6px 12px;border-radius:999px;border:1px solid var(--linie2);color:var(--dim);font-size:var(--fs-klein);text-decoration:none}
  .st-chips a[aria-current]{border-color:transparent;background:var(--metall);color:#16120b;font-weight:650}
  .st-filter select,.st-filter input[type=date]{width:auto;min-width:0;padding:7px 10px;font-size:var(--fs-klein)}
  .st-filter .feld{margin:0}
  .st-live{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px}
  .st-live article{border:1px solid var(--linie);border-radius:12px;padding:12px 14px;background:var(--flaeche2);font-size:var(--fs-klein);line-height:1.6}
  .st-live article b{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:var(--fs-klein)}
  .st-live dl{display:grid;grid-template-columns:auto 1fr;gap:0 10px;margin:6px 0 0}
  .st-live dt{color:var(--leise)} .st-live dd{margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .st-punkt{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--gruen);margin-right:6px;box-shadow:0 0 0 3px rgba(74,222,128,.18)}
  .st-drei{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
  .st-zeit{list-style:none;margin:0;padding:0;border-left:2px solid var(--linie2)}
  .st-zeit li{position:relative;padding:4px 0 10px 18px;font-size:13.5px}
  .st-zeit li::before{content:"";position:absolute;left:-6px;top:9px;width:10px;height:10px;border-radius:50%;background:var(--flaeche);border:2px solid var(--leise)}
  .st-zeit li.wichtig::before{border-color:var(--gruen);background:var(--gruen)}
  .st-zeit time{color:var(--leise);font-variant-numeric:tabular-nums;margin-right:8px}
  .st-saetze{margin:0;padding:0;list-style:none;display:grid;gap:6px;font-size:14px}
  .st-kopf{display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start}
  .st-kopf svg{width:112px;height:112px;background:#fff;border-radius:8px;padding:6px;flex:0 0 auto}
  #partner .kachel b{font-size:22px;white-space:nowrap}
  .st-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
  td a.st-mono{color:var(--text)}
  .st-trichter .trichter__stufe{grid-template-columns:48px minmax(0,1fr) minmax(0,19rem)}
  .st-trichter .trichter__wort{white-space:normal}
  @media(max-width:900px){.zwei{grid-template-columns:minmax(0,1fr)}}
  @media(max-width:700px){.st-filter select{flex:1 1 45%}
    .st-trichter .trichter__stufe{grid-template-columns:40px minmax(0,1fr);row-gap:2px}
    .st-trichter .trichter__wort{grid-column:2}}
</style>

<div class="kopf"><div><h1>Partner-Tracking</h1>
  <div class="weg"><?= Fmt::h(date('d.m.Y', strtotime($von))) ?> – <?= Fmt::h(date('d.m.Y', strtotime($bis))) ?> · nur Besuche über Partnerlinks · anonym, bis jemand selbst seine E-Mail einträgt</div></div>
  <?php if (!Spur::an()): ?><span class="marke2 warnung">Tracking ist ausgeschaltet</span><?php endif; ?>
</div>

<form class="st-filter" method="get" action="<?= Fmt::h(url('tracking')) ?>">
  <nav class="st-chips" aria-label="Zeitraum">
    <?php foreach (Spur::ZEITRAEUME as $zs => $zw): if ((string) $zs === 'frei') { continue; } ?>
      <a href="<?= Fmt::h($hier(['z' => $zs, 'von' => null, 'bis' => null])) ?>"<?= $zk === (string) $zs ? ' aria-current="page"' : '' ?>><?= Fmt::h($zw) ?></a>
    <?php endforeach; ?>
  </nav>
  <input type="hidden" name="z" value="frei">
  <div class="feld"><label for="st_von" class="leise" style="margin:0">von</label><input id="st_von" type="date" name="von" value="<?= Fmt::h($von) ?>"></div>
  <div class="feld"><label for="st_bis" class="leise" style="margin:0">bis</label><input id="st_bis" type="date" name="bis" value="<?= Fmt::h($bis) ?>"></div>
  <select name="partner" aria-label="Partner"><option value="">Alle Partner</option>
    <?php foreach ($partnerListe as $pl): ?><option value="<?= (int) $pl['id'] ?>"<?= (int) $f['partner'] === (int) $pl['id'] ? ' selected' : '' ?>><?= Fmt::h($pl['name']) ?></option><?php endforeach; ?></select>
  <select name="land" aria-label="Land"><option value="">Alle Länder</option>
    <?php foreach ($herkunft['land'] as $r): if ($r['wert'] === '') { continue; } ?><option value="<?= Fmt::h($r['wert']) ?>"<?= $f['land'] === $r['wert'] ? ' selected' : '' ?>><?= Fmt::h(Geo::landName($r['wert'])) ?></option><?php endforeach; ?></select>
  <select name="geraet" aria-label="Gerät"><option value="">Alle Geräte</option>
    <?php foreach (['smartphone', 'tablet', 'desktop'] as $g): ?><option value="<?= $g ?>"<?= $f['geraet'] === $g ? ' selected' : '' ?>><?= Spur::geraetName($g) ?></option><?php endforeach; ?></select>
  <select name="quelle" aria-label="Quelle"><option value="">Alle Quellen</option>
    <?php foreach (Spur::QUELLEN as $q => $qw): ?><option value="<?= $q ?>"<?= $f['quelle'] === $q ? ' selected' : '' ?>><?= Fmt::h($qw) ?></option><?php endforeach; ?></select>
  <select name="status" aria-label="Status"><option value="">Jeder Status</option>
    <?php foreach (Spur::STATUS as $s => $sw): ?><option value="<?= $s ?>"<?= $f['status'] === $s ? ' selected' : '' ?>><?= Fmt::h($sw) ?></option><?php endforeach; ?></select>
  <button class="knopf">Anzeigen</button>
  <?php if (array_filter($f) || $zk !== '30'): ?><a class="knopf stumm" href="<?= Fmt::h(url('tracking')) ?>">Zurücksetzen</a><?php endif; ?>
</form>

<?php if ($journey !== null): $jb = $journey['besuch']; ?>
<div class="block" id="journey">
  <h2>Journey <span class="st-mono"><?= Fmt::h($jb['visitor_id']) ?></span> <?= $statusMarke((string) $jb['status']) ?>
    <a class="mehr" href="<?= Fmt::h($hier(['besuch' => null])) ?>">schließen</a></h2>
  <div class="st-drei" style="margin-bottom:12px;font-size:13.5px;line-height:1.7">
    <div><span class="leise" style="margin:0">Partner</span><br><a href="<?= Fmt::h($hier(['partner' => (int) $jb['partner_id'], 'besuch' => null])) ?>"><?= Fmt::h($jb['partner']) ?></a></div>
    <div><span class="leise" style="margin:0">Quelle</span><br><?= Fmt::h(Spur::quelleName((string) $jb['quelle'])) ?><?= $jb['utm_campaign'] !== '' ? ' · ' . Fmt::h($jb['utm_campaign']) : '' ?></div>
    <div><span class="leise" style="margin:0">Land / Region / Stadt</span><br><?= Fmt::h(Geo::landName((string) $jb['land'])) ?><?= $jb['region'] !== '' ? ' · ' . Fmt::h($jb['region']) : '' ?><?= ($jb['stadt'] ?? '') !== '' ? ' · ' . Fmt::h((string) $jb['stadt']) : '' ?></div>
    <div><span class="leise" style="margin:0">Gerät</span><br><?= Fmt::h(Spur::geraetName((string) $jb['geraet']) . ' · ' . $jb['browser'] . ' · ' . $jb['system']) ?></div>
    <div><span class="leise" style="margin:0">Besuch</span><br><?= Fmt::h(date('d.m.Y H:i', strtotime((string) $jb['start_am']))) ?> · <?= Fmt::h($dauer((string) $jb['start_am'], (string) $jb['zuletzt_am'])) ?> · <?= (int) $jb['neu'] === 1 ? 'neu' : 'wiederkehrend' ?><?= (int) $jb['verdacht'] === 1 ? ' · <span class="marke2 schlecht">Mehrfachklick-Verdacht</span>' : '' ?></div>
    <div><span class="leise" style="margin:0">Person</span><br><?php if (!empty($jb['customer_id'])): ?><a href="<?= Fmt::h(url('kunden/' . (int) $jb['customer_id'])) ?>">Kunde #<?= (int) $jb['customer_id'] ?> (hat selbst die E-Mail eingetragen)</a><?php elseif (!empty($journey['zugang'])): ?><?= Fmt::h((string) $journey['zugang']['email']) ?> (hat seine E-Mail eingetragen)<?php else: ?>unbekannt — anonymer Besuch<?php endif; ?></div>
  </div>
  <p style="margin:0 0 10px;line-height:1.55"><b>Stand „<?= Fmt::h(Spur::STATUS[(string) $jb['status']] ?? (string) $jb['status']) ?>“:</b> <?= Fmt::h(Spur::STATUS_ERKLAERT[(string) $jb['status']] ?? '') ?>.
    <span class="leise" style="display:inline">Darunter Schritt für Schritt, was diese Person auf der Website getan hat (Uhrzeit links).</span></p>
  <?php if (!empty($journey['zugang']) && $journey['zugang']['customer_id'] === null): $az = $journey['zugang']; $azZurueck = 'tracking?besuch=' . (int) $jb['id'] . '#journey'; ?>
    <?php require_once dirname(__DIR__) . '/src/Zugang.php'; require __DIR__ . '/anfrage_karte.php'; ?>
  <?php elseif (!empty($journey['zugang']['customer_id']) && empty($jb['customer_id'])): ?>
    <p><a class="knopf klein" href="<?= Fmt::h(url('kunden/' . (int) $journey['zugang']['customer_id'])) ?>">Zum Kunden — antworten und Angebot schicken</a></p>
  <?php endif; ?>
  <ol class="st-zeit">
    <?php foreach ($journey['schritte'] as $s):
      $wort = Spur::satz((string) $s['event_type'], (string) ($s['seite'] ?? ''), (string) $jb['partner'], str_contains((string) $s['meta'], 'wiederholt'));
      $wichtig = !in_array($s['event_type'], ['page_view', 'partner_visit'], true); ?>
      <li class="<?= $wichtig ? 'wichtig' : '' ?>"><time datetime="<?= Fmt::h($s['created_at']) ?>"><?= Fmt::h($uhr((string) $s['created_at'])) ?></time><?= Fmt::h($wort) ?><?= $s['betrag_cents'] !== null ? ' · ' . Fmt::h($geld((int) $s['betrag_cents'])) : '' ?></li>
    <?php endforeach; ?>
    <?php foreach ($journey['danach'] as $s): ?>
      <li class="wichtig"><time datetime="<?= Fmt::h($s['created_at']) ?>"><?= Fmt::h(date('d.m. H:i', strtotime((string) $s['created_at']))) ?></time><?= Fmt::h(Spur::satz((string) $s['event_type'])) ?><?= $s['betrag_cents'] !== null ? ' · ' . Fmt::h($geld((int) $s['betrag_cents'])) : '' ?> <span class="leise" style="display:inline">(später, am Kunden)</span></li>
    <?php endforeach; ?>
  </ol>
  <?php if ($journey['andere']): ?>
    <p class="leise" style="margin-top:8px">Weitere Besuche dieses Besuchers (nur mit Einwilligung erkennbar):
      <?php foreach ($journey['andere'] as $a): ?><a href="<?= Fmt::h($hier(['besuch' => (int) $a['id']])) ?>"><?= Fmt::h(date('d.m. H:i', strtotime((string) $a['start_am']))) ?></a> <?php endforeach; ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="karten">
  <div class="karte"><h3>Besucher über Partner</h3><div class="wert"><?= (int) $k['sitzungen'] ?></div><div class="neben"><?= (int) $k['neu'] ?> neu · <?= (int) $k['wieder'] ?> wiederkehrend<?= $k['verdacht'] ? ' · ' . (int) $k['verdacht'] . ' verdächtig (nicht gezählt)' : '' ?></div></div>
  <div class="karte"><h3>Partner-Klicks</h3><div class="wert"><?= (int) $k['klicks'] ?></div><div class="neben">jeder echte Klick, auch erneute</div></div>
  <div class="karte"><h3>Preisrechner gestartet</h3><div class="wert"><?= (int) $k['rechner_gestartet'] ?></div><div class="neben"><?= (int) $k['rechner_geoeffnet'] ?> geöffnet</div></div>
  <div class="karte"><h3>Preisrechner abgeschlossen</h3><div class="wert"><?= (int) $k['rechner_fertig'] ?></div><div class="neben"><?= (int) $k['fragebogen'] ?> Fragebögen abgeschlossen</div></div>
  <div class="karte"><h3>Anfragen</h3><div class="wert"><?= (int) $k['anfragen'] ?></div><div class="neben"><?= (int) $k['angebote'] ?> Angebote</div></div>
  <div class="karte"><h3>Kunden</h3><div class="wert"><?= (int) $k['kunden'] ?></div><div class="neben"><?= (int) $k['auftraege'] ?> Aufträge</div></div>
  <div class="karte"><h3>Umsatz über Partner</h3><div class="wert"><?= Fmt::h($geld((int) $k['umsatz'])) ?></div><div class="neben"><?= (int) $k['zahlungen'] ?> Zahlungen eingegangen</div></div>
  <div class="karte"><h3>Conversion</h3><div class="wert"><?= Fmt::h($pz((float) $k['conversion'])) ?></div><div class="neben">Kunden je Besucher</div></div>
</div>

<?php if ($heute && $zk === 'heute' || ($heute && $detail === null && $journey === null)): ?>
<div class="block">
  <h2>Heute</h2>
  <ul class="st-saetze">
    <?php foreach ($heute as $h): ?>
      <li><b><?= Fmt::h($h['name']) ?></b> hat heute <b><?= (int) $h['sitzungen'] ?></b> Besucher gebracht
        (<?= (int) $h['neu'] ?> neu, <?= (int) $h['wieder'] ?> wiederkehrend): <?= (int) $h['rechner_gestartet'] ?> Preisrechner gestartet,
        <?= (int) $h['rechner_fertig'] ?> abgeschlossen, <?= (int) $h['fragebogen'] ?> Fragebögen, <?= (int) $h['anfragen'] ?> Anfragen,
        <?= (int) $h['kunden'] ?> neue Kunden, <?= Fmt::h($geld((int) $h['umsatz'])) ?> Umsatz.</li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="block" id="live" data-quelle="<?= Fmt::h(url('tracking/live')) ?>">
  <?php require __DIR__ . '/tracking_live.php'; ?>
</div>

<?php if ($detail !== null): $dp = $detail['partner']; $dk = $detail['k']; ?>
<div class="block" id="partner">
  <h2><?= Fmt::h($dp['name']) ?> <a class="mehr" href="<?= Fmt::h(url('partner/' . (int) $dp['id'])) ?>">Partnerakte →</a></h2>
  <div class="st-kopf">
    <?= $detail['qr'] ?>
    <div style="font-size:13.5px;line-height:1.8;min-width:0">
      <div><span class="leise" style="display:inline">Partner-ID</span> <?= (int) $dp['id'] ?> · <span class="leise" style="display:inline">Code</span> <b><?= Fmt::h($dp['code']) ?></b></div>
      <div style="overflow-wrap:anywhere"><span class="leise" style="display:inline">Empfehlungslink</span> <code><?= Fmt::h(Partner::link($dp)) ?></code></div>
      <div><span class="leise" style="display:inline">Klicks</span> heute <b><?= (int) $detail['klicks']['heute'] ?></b> · 7 Tage <b><?= (int) $detail['klicks']['7'] ?></b> · 30 Tage <b><?= (int) $detail['klicks']['30'] ?></b> · gesamt seit Start <b><?= (int) $detail['klicks']['gesamt'] ?></b></div>
    </div>
  </div>
  <div class="kacheln" style="margin-top:14px">
    <div class="kachel"><span>Eindeutige Besucher</span><b><?= (int) $dk['besucher'] ?></b></div>
    <div class="kachel"><span>Preisrechner gestartet</span><b><?= (int) $dk['rechner_gestartet'] ?></b></div>
    <div class="kachel"><span>Preisrechner abgeschlossen</span><b><?= (int) $dk['rechner_fertig'] ?></b></div>
    <div class="kachel"><span>Fragebogen abgeschlossen</span><b><?= (int) $dk['fragebogen'] ?></b></div>
    <div class="kachel"><span>Anfragen</span><b><?= (int) $dk['anfragen'] ?></b></div>
    <div class="kachel"><span>Kunden</span><b><?= (int) $dk['kunden'] ?></b></div>
    <div class="kachel"><span>Umsatz</span><b><?= Fmt::h($geld((int) $dk['umsatz'])) ?></b></div>
    <div class="kachel"><span>Conversion</span><b><?= Fmt::h($pz((float) $dk['conversion'])) ?></b></div>
    <div class="kachel"><span>Ø Auftragswert</span><b><?= $dk['auftraege'] > 0 ? Fmt::h($geld(intdiv((int) $dk['auftragswert'], (int) $dk['auftraege']))) : '—' ?></b></div>
    <div class="kachel"><span>Provision (bestehendes System)</span><b><?= Fmt::h($geld((int) $detail['provision'])) ?></b></div>
  </div>
</div>
<?php endif; ?>

<div class="zwei">
  <div class="block">
    <h2>Funnel<?= $detail !== null ? ' · ' . Fmt::h($detail['partner']['name']) : ' · alle Partner' ?></h2>
    <?php $trichter($funnel); ?>
    <p class="leise" style="margin-top:12px">Jede Stufe zählt Besuche bzw. Kunden, in denen das Ereignis wirklich eintrat. Ein Klick allein ist nie eine Conversion.</p>
  </div>
  <div class="block">
    <h2>Quellen</h2>
    <?php $balken($herkunft['quelle'], static fn($w) => Spur::quelleName($w), 'Noch keine Besuche im Zeitraum.'); ?>
  </div>
</div>

<div class="block">
  <h2>Partner <span class="mehr"><?= count($tabelle) ?> Partner</span></h2>
  <div class="tabellenrahmen">
    <table>
      <thead><tr><th>Partner</th><th class="num">Klicks</th><th class="num">Besucher</th><th class="num">Preisrechner</th><th class="num">Fragebogen</th><th class="num">Anfragen</th><th class="num">Kunden</th><th class="num">Conversion</th><th class="num">Umsatz</th></tr></thead>
      <tbody>
        <?php foreach ($tabelle as $r): ?>
          <tr><td><a href="<?= Fmt::h($hier(['partner' => $r['id']])) ?>#partner"><?= Fmt::h($r['name']) ?></a></td>
            <td class="num"><?= (int) $r['klicks'] ?></td><td class="num"><?= (int) $r['besucher'] ?></td><td class="num"><?= (int) $r['rechner_gestartet'] ?></td>
            <td class="num"><?= (int) $r['fragebogen'] ?></td><td class="num"><?= (int) $r['anfragen'] ?></td><td class="num"><?= (int) $r['kunden'] ?></td>
            <td class="num"><?= Fmt::h($pz((float) $r['conversion'])) ?></td><td class="num"><?= Fmt::h($geld((int) $r['umsatz'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$tabelle): ?><tr><td colspan="9" class="leer">Noch keine aktiven Partner.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="block">
  <h2>Herkunft</h2>
  <div class="st-drei">
    <div><p class="leise">Länder</p><?php $balken($herkunft['land'], static fn($w) => Geo::landName($w), '—'); ?></div>
    <div><p class="leise">Regionen (Italien, Deutschland)</p><?php $balken($herkunft['region'], static fn($w) => $w, '—'); ?></div>
    <div><p class="leise">Geräte</p><?php $balken($herkunft['geraet'], static fn($w) => Spur::geraetName($w), '—'); ?></div>
    <div><p class="leise">Browser</p><?php $balken($herkunft['browser'], static fn($w) => $w ?: '—', '—'); ?></div>
    <div><p class="leise">Einstiegsseiten</p><?php $balken($herkunft['einstieg'], static fn($w) => $w ?: '/', '—'); ?></div>
    <div><p class="leise">Kampagnen (utm_campaign)</p><?php $balken($herkunft['kampagne'], static fn($w) => $w, 'Keine Kampagnen-Links im Zeitraum.'); ?></div>
  </div>
  <p class="leise" style="margin-top:10px">Land und Region lokal aus der IP bestimmt, die IP wird nicht gespeichert. IP-Standort: <a href="https://db-ip.com" rel="noopener" target="_blank">DB-IP</a> (CC BY 4.0).</p>
</div>

<div class="block">
  <h2>Besuche <span class="mehr">die letzten <?= count($besuche) ?> im Zeitraum</span></h2>
  <div class="tabellenrahmen">
    <table>
      <thead><tr><th>Besucher</th><th>Beginn</th><th>Partner</th><th>Quelle</th><th>Land</th><th>Gerät</th><th>Einstieg → zuletzt</th><th class="num">Seiten</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($besuche as $b): ?>
          <tr<?= (int) $b['verdacht'] === 1 ? ' style="opacity:.55"' : '' ?>><td><a class="st-mono" href="<?= Fmt::h($hier(['besuch' => (int) $b['id']])) ?>#journey"><?= Fmt::h($b['visitor_id']) ?></a><?= !empty($b['customer_id']) ? ' <span class="marke2 gut">Kunde</span>' : '' ?></td>
            <td><?= Fmt::h(date('d.m. H:i', strtotime((string) $b['start_am']))) ?></td><td><?= Fmt::h($b['partner']) ?></td>
            <td><?= Fmt::h(Spur::quelleName((string) $b['quelle'])) ?></td><td><?= Fmt::h($b['land'] ?: '—') ?></td><td><?= Fmt::h(Spur::geraetName((string) $b['geraet'])) ?></td>
            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Fmt::h($b['einstieg']) ?> → <?= Fmt::h($b['aktuell']) ?></td>
            <td class="num"><?= (int) $b['seiten'] ?></td><td><?= $statusMarke((string) $b['status']) ?><?= (int) $b['verdacht'] === 1 ? ' <span class="marke2 schlecht">Verdacht</span>' : '' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$besuche): ?><tr><td colspan="9" class="leer">Noch keine Partner-Besuche in diesem Zeitraum.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<details class="block klapp" id="einstellungen">
  <summary style="cursor:pointer;font-weight:600">Einstellungen und Datenschutz</summary>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"><?= Csrf::feld() ?>
    <input type="hidden" name="tat" value="tracking_einstellungen">
    <label style="display:flex;gap:8px;align-items:center;font-size:13.5px"><input type="checkbox" name="spur_an" value="1" style="width:auto"<?= $einst['spur_an'] === '1' ? ' checked' : '' ?>> Partner-Tracking an</label>
    <label style="display:flex;gap:8px;align-items:center;font-size:13.5px;margin-top:6px"><input type="checkbox" name="spur_frage_an" value="1" style="width:auto"<?= $einst['spur_frage_an'] === '1' ? ' checked' : '' ?>> Partner-Besucher fragen, ob wir uns die Empfehlung merken dürfen (Einwilligung)</label>
    <label style="display:flex;gap:8px;align-items:center;font-size:13.5px;margin-top:6px"><input type="checkbox" name="spur_geo_an" value="1" style="width:auto"<?= $einst['spur_geo_an'] === '1' ? ' checked' : '' ?>> Land und Region bestimmen (lokal, ohne Speichern der IP)</label>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px">
      <div class="feld" style="flex:0 0 200px"><label>Partnerzuordnung mit Einwilligung (Tage)</label><input name="spur_zuordnung_tage" value="<?= Fmt::h($einst['spur_zuordnung_tage']) ?>"></div>
      <div class="feld" style="flex:0 0 200px"><label>Einzeldaten aufbewahren (Tage)</label><input name="spur_rohdaten_tage" value="<?= Fmt::h($einst['spur_rohdaten_tage']) ?>"></div>
      <div class="feld" style="flex:0 0 220px"><label>Mehrfachklick-Grenze (neue Sitzungen je Stunde)</label><input name="spur_klick_grenze" value="<?= Fmt::h($einst['spur_klick_grenze']) ?>"></div>
    </div>
    <p class="leise" style="margin-top:4px">Ohne Einwilligung gilt ein Besuch nur, bis der Browser zugeht. Danach bleibt die Zuordnung über die eingetragene E-Mail
      und dann <?= (int) Partner::zahl('partner_zuordnung_monate') ?> Monate am Kunden (Partnerprogramm). Nach der Aufbewahrungsfrist bleiben nur Tageszahlen.</p>
    <button class="knopf">Speichern</button>
  </form>
</details>

<script>
/* Live alle 30 Sekunden -- nur bei sichtbarer Seite, eine schlanke Abfrage. */
(function () {
  var box = document.getElementById('live'); if (!box) { return; }
  setInterval(function () {
    if (document.visibilityState !== 'visible') { return; }
    fetch(box.getAttribute('data-quelle'), { credentials: 'same-origin', headers: { 'X-Teil': '1' } })
      .then(function (r) { return r.ok ? r.text() : null; }).then(function (t) { if (t !== null) { box.innerHTML = t; } }).catch(function () {});
  }, 30000);
})();
</script>
