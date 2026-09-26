<?php
/** @var array $f @var ?array $audit @var array $befunde @var array $top @var array $teile @var array $messwerte @var array $ki
 *  @var array $vorlagen @var array $versand @var array $antworten @var array $protokoll @var array $gates @var ?string $sperre
 *  @var array $analysen @var array $audits */
$akqTeil = 'firma';
$b = Akquise::branchen()[(string) ($f['branche'] ?? '')] ?? [];
$gesperrt = (int) $f['gesperrt'] === 1;
$spracheText = AkquiseText::spracheFuer($f);

/* Die fuenf Stufen des Vorgangs -- dieselben Woerter wie in Uwes Vorgabe. */
$kontaktiert = (bool) array_filter($versand, static fn($v) => in_array($v['status'], ['gesendet', 'von_hand'], true));
$freigegeben = (bool) array_filter($vorlagen, static fn($v) => in_array($v['status'], ['freigegeben', 'gesendet'], true));
$stufen = [
    'Research' => true,
    'Audit' => $audit !== null && $audit['status'] === 'fertig',
    'Freigabe' => $freigegeben,
    'Kontakt' => $kontaktiert,
    'Antwort' => (bool) $antworten,
];
$jetzt = array_search(false, $stufen, true);

$schwere = static function (int $s): string {
    $o = '<span class="akq-schwere" title="Schwere ' . $s . ' von 5">';
    for ($i = 1; $i <= 5; $i++) { $o .= '<i class="' . ($i <= $s ? 'an' : '') . '"></i>'; }
    return $o . '</span>';
};
$gateKlasse = static fn(string $s): string => ['CONTACT_ALLOWED' => 'gut', 'REVIEW_REQUIRED' => 'warnung', 'DO_NOT_EMAIL' => 'schlecht'][$s] ?? '';
$mw = static function (array $m, string $k, string $einheit = '', int $teiler = 1, int $stellen = 0): string {
    if (!isset($m[$k]) || !is_numeric($m[$k])) { return '—'; }
    return number_format((float) $m[$k] / $teiler, $stellen, ',', '.') . $einheit;
};
$loesungen = [];
foreach ($top as $t) {
    $x = AkquiseText::saetze($t, $f, 'de');
    if ($x !== null) { $loesungen[] = $x[2]; }
}
?>
<div class="kopf"><div>
  <p class="akq-klein"><a href="<?= Fmt::h(url('akquise')) ?>">← Akquise</a> · <?= Fmt::h((string) $f['kennung']) ?></p>
  <h1 style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><?= Fmt::h((string) $f['name']) ?>
    <?php if ($f['score'] !== null): ?><span class="akq-score s-<?= Fmt::h((string) $f['score_stufe']) ?>"><?= (int) $f['score'] ?></span>
      <span class="akq-klein" style="font-weight:400"><?= Fmt::h(AkquiseScore::STUFEN[(string) $f['score_stufe']] ?? '') ?> · nur intern</span><?php endif; ?>
    <?php if ($gesperrt): ?><span class="marke2 schlecht">DO NOT CONTACT</span><?php endif; ?>
  </h1>
  <div class="akq-stufen" aria-label="Status">
    <?php foreach ($stufen as $wort => $fertig): ?>
      <span class="<?= $fertig ? 'fertig' : ($wort === $jetzt ? 'jetzt' : '') ?>"><?= $fertig ? '✓ ' : '' ?><?= Fmt::h($wort) ?></span>
    <?php endforeach; ?>
  </div>
</div></div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<div class="zwei">
  <div>
    <div class="block">
      <h2>Unternehmen</h2>
      <div class="tabellenrahmen"><table><tbody>
        <tr><td style="width:150px" class="akq-klein">Website</td><td><?php if ($f['url']): ?>
          <a href="<?= Fmt::h((string) $f['url']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--cyan)"><?= Fmt::h((string) $f['url']) ?></a>
          <?php else: ?>keine bekannt<?php endif; ?>
          <span class="marke2" style="margin-left:6px"><?= Fmt::h(Akquise::AUDIT_STATUS[(string) $f['audit_status']] ?? '') ?></span></td></tr>
        <tr><td class="akq-klein">Standort</td><td><?= Fmt::h(implode(', ', array_filter([(string) $f['adresse'], trim(($f['plz'] ?? '') . ' ' . ($f['stadt'] ?? '')), (string) $f['kreis'], (string) $f['region'], $f['land'] === 'DE' ? 'Deutschland' : 'Italien']))) ?></td></tr>
        <tr><td class="akq-klein">Branche</td><td><?= Fmt::h(Akquise::branchenName($f['branche'])) ?><?= (int) $f['tourismus'] ? ' · touristisch' : '' ?>
          <?= !empty($b['berufsrecht']) ? ' · <span class="marke2 warnung">Berufsrecht beachten</span>' : '' ?></td></tr>
        <tr><td class="akq-klein">Kontakt</td><td><?= Fmt::h(implode(' · ', array_filter([(string) $f['ansprechpartner'], (string) $f['telefon'], (string) $f['email']]))) ?: '—' ?></td></tr>
        <tr><td class="akq-klein">Sprache</td><td><?= Fmt::h(AkquiseText::SPRACHEN[$spracheText]) ?><?= $f['sprache'] ? '' : ' <span class="akq-klein">(aus dem Land abgeleitet)</span>' ?></td></tr>
        <tr><td class="akq-klein">Quelle</td><td class="akq-klein"><?= Fmt::h((string) ($f['quelle'] ?? 'von Hand')) ?> <?= $f['quelle_lizenz'] ? '· ' . Fmt::h((string) $f['quelle_lizenz']) : '' ?>
          · recherchiert <?= Fmt::h(Fmt::datum((string) $f['recherchiert_am'])) ?> · geprüft <?= $f['geprueft_am'] ? Fmt::h(Fmt::datum((string) $f['geprueft_am'])) : '—' ?></td></tr>
      </tbody></table></div>

      <details style="margin-top:12px"><summary class="akq-klein" style="cursor:pointer">Stammdaten, Einwilligung und Notiz bearbeiten</summary>
        <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:12px">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_firma_speichern"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
          <div class="reihe">
            <div class="feld"><label>Ansprechpartner (öffentlich, geschäftlich)</label><input name="ansprechpartner" value="<?= Fmt::h((string) $f['ansprechpartner']) ?>"></div>
            <div class="feld"><label>Branche</label><select name="branche"><option value="">—</option>
              <?php foreach (Akquise::branchen() as $k => $x): ?><option value="<?= Fmt::h($k) ?>"<?= $f['branche'] === $k ? ' selected' : '' ?>><?= Fmt::h((string) $x['de']) ?></option><?php endforeach; ?></select></div>
            <div class="feld"><label>Geschäftliche E-Mail</label><input name="email" type="email" value="<?= Fmt::h((string) $f['email']) ?>"></div>
            <div class="feld"><label>Telefon</label><input name="telefon" value="<?= Fmt::h((string) $f['telefon']) ?>"></div>
            <div class="feld"><label>Sprache der Ansprache</label><select name="sprache"><option value="">automatisch</option>
              <?php foreach (AkquiseText::SPRACHEN as $k => $w): ?><option value="<?= $k ?>"<?= $f['sprache'] === $k ? ' selected' : '' ?>><?= $w ?></option><?php endforeach; ?></select></div>
            <div class="feld"><label>Kontaktstatus</label><select name="kontakt_status"<?= $gesperrt ? ' disabled' : '' ?>>
              <?php foreach (Akquise::KONTAKT_STATUS as $k => $w): if ($k === 'gesperrt') { continue; } ?><option value="<?= $k ?>"<?= $f['kontakt_status'] === $k ? ' selected' : '' ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="feld"><label>Einwilligung (nur mit Beleg: wer, wann, wie, wofür)</label>
            <input name="einwilligung" value="<?= Fmt::h((string) $f['einwilligung']) ?>" placeholder="z. B. 24.09.2026, Inhaber am Telefon: „Schicken Sie mir das per Mail“ — Notiz im Kalender"></div>
          <label style="display:flex;gap:8px;align-items:center;font-size:13.5px;margin-bottom:12px">
            <input type="checkbox" name="bestandskunde" value="1" style="width:auto"<?= (int) $f['bestandskunde'] ? ' checked' : '' ?>> Bestehende Kundenbeziehung (eigener Kunde)</label>
          <div class="feld"><label>Notiz</label><textarea name="notiz" rows="3"><?= Fmt::h((string) $f['notiz']) ?></textarea></div>
          <button class="knopf">Speichern</button>
          <p class="akq-klein" style="margin-top:8px">Einwilligung und Kundenbeziehung ändern die Rechtslage im Gate und stehen deshalb in der Prüfspur.</p>
        </form>
      </details>
    </div>
  </div>

  <div>
    <div class="block">
      <h2>Compliance-Gate</h2>
      <div class="tabellenrahmen"><table><tbody>
        <?php foreach ($gates as $kanal => $g): ?>
          <tr><td style="width:120px"><?= Fmt::h(AkquiseGate::KANAELE[$kanal]) ?></td>
            <td><span class="marke2 <?= $gateKlasse($g['status']) ?>"><?= Fmt::h(AkquiseGate::STATUS[$g['status']]) ?></span>
              <div class="akq-klein" style="margin-top:5px"><?= Fmt::h(implode(' ', $g['gruende'])) ?></div>
              <?php if (!empty($g['regel']['quelle'])): ?><div class="akq-klein"><a href="<?= Fmt::h((string) $g['regel']['quelle']) ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:underline">Quelle</a>
                · geprüft <?= Fmt::h(Fmt::datum((string) $g['regel']['geprueft_am'])) ?></div><?php endif; ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <p class="akq-klein" style="margin-top:8px">Keine Rechtsberatung. Die Regeln stehen unter
        <a href="<?= Fmt::h(url('akquise/regeln')) ?>" style="text-decoration:underline">Compliance &amp; Versand</a> und sind dort änderbar.</p>
    </div>
  </div>
</div>

<?php if ($audit): ?>
<div class="block">
  <h2>Audit <span class="akq-klein" style="font-weight:400">· <?= Fmt::h(Fmt::zeit((string) $audit['beendet_am'])) ?> · <?= (int) $audit['seiten'] ?> Seite(n)
    · Worker <?= Fmt::h((string) ($audit['worker_version'] ?? '')) ?><?= $audit['ki_modell'] ? ' · gedeutet mit ' . Fmt::h((string) $audit['ki_modell']) : '' ?></span></h2>
  <?php if ($teile): ?>
    <div class="akq-teile" style="margin-bottom:14px">
      <?php foreach (AkquiseScore::GEWICHTE as $topf => $max): $w = (float) ($teile[$topf] ?? 0); ?>
        <div class="akq-teil"><div class="akq-klein"><?= Fmt::h(['technik' => 'Technik + Performance', 'mobile' => 'Mobile', 'ux' => 'UX + Conversion', 'design' => 'Design',
            'seo' => 'SEO', 'vertrauen' => 'Vertrauen', 'experience' => 'Experience'][$topf]) ?></div>
          <b><?= number_format($w, 0) ?></b><span class="akq-klein"> / <?= $max ?> Potenzial</span>
          <div class="balken"><span style="width:<?= $max > 0 ? round($w / $max * 100) : 0 ?>%"></span></div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (isset($messwerte['performance']) || isset($messwerte['lcp_ms'])): ?>
    <div class="tabellenrahmen" style="margin-bottom:14px"><table><thead><tr>
      <th>Quelle</th><th>Performance</th><th>LCP</th><th>CLS</th><th>TBT</th><th>INP (Feld)</th><th>FCP</th><th>TTFB</th><th>Gewicht</th><th>SEO</th><th>Barrierefreiheit</th></tr></thead>
      <tbody><tr>
        <td class="akq-klein"><?= Fmt::h((string) ($messwerte['quelle'] ?? 'Lighthouse')) ?> · mobil</td>
        <td><?= $mw($messwerte, 'performance') ?></td><td><?= $mw($messwerte, 'lcp_ms', ' s', 1000, 1) ?></td>
        <td><?= isset($messwerte['cls']) ? number_format((float) $messwerte['cls'], 2, ',', '.') : '—' ?></td>
        <td><?= $mw($messwerte, 'tbt_ms', ' ms') ?></td><td><?= $mw($messwerte, 'inp_ms', ' ms') ?></td>
        <td><?= $mw($messwerte, 'fcp_ms', ' s', 1000, 1) ?></td><td><?= $mw($messwerte, 'ttfb_ms', ' ms') ?></td>
        <td><?= $mw($messwerte, 'bytes', ' MB', 1048576, 1) ?></td><td><?= $mw($messwerte, 'seo') ?></td><td><?= $mw($messwerte, 'accessibility') ?></td>
      </tr></tbody></table></div>
  <?php endif; ?>

  <?php if ($audit['screenshot_mobil'] || $audit['screenshot_desktop']): ?>
    <div class="akq-foto">
      <?php if ($audit['screenshot_mobil']): ?><figure><img src="<?= Fmt::h(url('akquise/bild/' . (int) $audit['id'] . '/mobil')) ?>" width="195" height="422" alt="Startseite mobil" loading="lazy">
        <figcaption class="akq-klein">Mobil (390 px)</figcaption></figure><?php endif; ?>
      <?php if ($audit['screenshot_desktop']): ?><figure style="flex:1 1 360px"><img src="<?= Fmt::h(url('akquise/bild/' . (int) $audit['id'] . '/desktop')) ?>" width="640" height="400" alt="Startseite Desktop" loading="lazy">
        <figcaption class="akq-klein">Desktop (1366 px)</figcaption></figure><?php endif; ?>
    </div>
  <?php endif; ?>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:12px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_neu_pruefen"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
    <button class="knopf">Neu prüfen lassen</button>
    <?php if (count($audits) > 1): ?><span class="akq-klein" style="margin-left:8px">Frühere Audits: <?php foreach (array_slice($audits, 1) as $x): ?>
      <?= Fmt::h(Fmt::datum((string) $x['beendet_am'])) ?> (<?= $x['score'] ?? '—' ?>) <?php endforeach; ?></span><?php endif; ?>
  </form>
</div>

<div class="zwei">
  <div class="block">
    <h2>Top-Probleme</h2>
    <?php if (!$top): ?><div class="leer">Keine Probleme erkannt.</div><?php endif; ?>
    <?php foreach ($top as $i => $t): ?>
      <p style="margin:0 0 10px"><b><?= $i + 1 ?>.</b> <?= Fmt::h((string) $t['titel']) ?>
        <?= $t['status'] !== 'VERIFIED' ? '<span class="marke2 warnung">UNVERIFIED</span>' : '' ?></p>
    <?php endforeach; ?>
  </div>
  <div class="block">
    <h2>Vorgeschlagene Lösung</h2>
    <?php if (trim((string) $audit['loesung']) !== ''): ?>
      <p style="white-space:pre-wrap"><?= Fmt::h((string) $audit['loesung']) ?></p>
    <?php elseif ($loesungen): ?>
      <ul style="padding-left:18px"><?php foreach (array_unique($loesungen) as $l): ?><li><?= Fmt::h(mb_strtoupper(mb_substr($l, 0, 1)) . mb_substr($l, 1)) ?></li><?php endforeach; ?></ul>
    <?php else: ?><div class="leer">Noch keine.</div><?php endif; ?>
    <h2 style="margin-top:14px">Experience-Idee</h2>
    <?php if (trim((string) $audit['experience']) !== ''): ?>
      <p style="white-space:pre-wrap"><?= Fmt::h((string) $audit['experience']) ?></p>
    <?php elseif (!empty($b['experience_idee'])): ?>
      <p class="akq-klein">Nur als Ausgangspunkt aus der Branche, nicht geprüft: <?= Fmt::h((string) $b['experience_idee']) ?></p>
    <?php endif; ?>
    <p class="akq-klein">Technik nur, wenn sie für diesen Betrieb einen geschäftlichen Mehrwert erzeugt.</p>
  </div>
</div>

<div class="block">
  <h2>Alle Befunde <span class="akq-klein" style="font-weight:400">· <?= count($befunde) ?>, davon <?= count(array_filter($befunde, static fn($x) => $x['status'] === 'VERIFIED')) ?> belegt</span></h2>
  <?php foreach (Akquise::KATEGORIEN as $kat => $katName):
      $liste = array_values(array_filter($befunde, static fn($x) => $x['kategorie'] === $kat));
      if (!$liste) { continue; } ?>
    <h3 style="font-size:12px;text-transform:uppercase;letter-spacing:.07em;color:var(--leise);margin:14px 0 8px"><?= Fmt::h($katName) ?></h3>
    <?php foreach ($liste as $x): ?>
      <div class="akq-befund<?= $x['status'] !== 'VERIFIED' ? ' unbelegt' : '' ?>">
        <h4><?= $schwere((int) $x['schwere']) ?> <?= Fmt::h((string) $x['titel']) ?>
          <span class="marke2 <?= $x['status'] === 'VERIFIED' ? 'gut' : 'warnung' ?>"><?= Fmt::h((string) $x['status']) ?></span></h4>
        <?php if ($x['beschreibung']): ?><p><?= Fmt::h((string) $x['beschreibung']) ?></p><?php endif; ?>
        <?php if ($x['wirkung']): ?><p><i>Mögliche Wirkung:</i> <?= Fmt::h((string) $x['wirkung']) ?></p><?php endif; ?>
        <?php if ($x['beleg'] || $x['url']): ?><div class="beleg"><?= Fmt::h(trim(($x['url'] ? $x['url'] . "\n" : '') . (string) $x['beleg'])) ?></div><?php endif; ?>
        <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:6px" data-frage="Diesen Befund verwerfen? Er zählt danach nicht mehr im Score und erscheint in keinem Text." data-ja="Ja, verwerfen">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_befund_verwerfen"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
          <input type="hidden" name="befund" value="<?= (int) $x['id'] ?>"><button class="knopf" style="font-size:12px;padding:4px 10px">Stimmt nicht — verwerfen</button></form>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="block"><h2>Audit</h2><div class="leer">
  <?= $f['url'] ? 'Noch nicht geprüft. Der Worker nimmt die Seite beim nächsten Lauf mit.' : 'Keine eigene Website bekannt. Vor einer Ansprache prüfen, ob es wirklich keine gibt.' ?></div></div>
<?php endif; ?>

<div class="block" id="vorlagen">
  <h2>Kontaktvorlage</h2>
  <?php if ($gesperrt): ?>
    <div class="leer">Gesperrt — für diese Firma entsteht kein Text mehr.</div>
  <?php else: ?>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_vorlage_regel"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
      <select name="sprache" style="width:auto"><?php foreach (AkquiseText::SPRACHEN as $k => $w): ?><option value="<?= $k ?>"<?= $k === $spracheText ? ' selected' : '' ?>><?= $w ?></option><?php endforeach; ?></select>
      <select name="kanal" style="width:auto"><?php foreach (['email' => 'E-Mail', 'brief' => 'Brief'] as $k => $w): ?><option value="<?= $k ?>"><?= $w ?></option><?php endforeach; ?></select>
      <button class="knopf<?= $vorlagen ? '' : ' haupt' ?>">Vorlage aus den Befunden schreiben</button>
      <span class="akq-klein">Natürlichere Texte schreibt Claude im Worker (<code>npm run texte</code>).</span>
    </form>
  <?php endif; ?>

  <?php foreach ($vorlagen as $v): $hinweise = $v['pruefhinweise'] ? (json_decode((string) $v['pruefhinweise'], true) ?: []) : [];
      $offen = $v['status'] === 'entwurf'; ?>
    <div class="akq-befund" id="vorlage-<?= (int) $v['id'] ?>" style="background:var(--flaeche)">
      <h4><?= Fmt::h(AkquiseText::SPRACHEN[(string) $v['sprache']] ?? '') ?> · <?= Fmt::h(AkquiseGate::KANAELE[(string) $v['kanal']] ?? '') ?>
        <span class="marke2 <?= ['freigegeben' => 'gut', 'gesendet' => 'gut', 'verworfen' => 'schlecht'][$v['status']] ?? 'warnung' ?>"><?= Fmt::h((string) $v['status']) ?></span>
        <span class="akq-klein" style="font-weight:400">von <?= Fmt::h((string) $v['erzeugt_von']) ?> · <?= Fmt::h(Fmt::zeit((string) $v['updated_at'])) ?>
          <?= $v['freigegeben_am'] ? ' · freigegeben von ' . Fmt::h((string) $v['freigegeben_von']) : '' ?></span></h4>
      <?php if ($hinweise): ?><ul class="akq-hinweise"><?php foreach ($hinweise as $h): ?><li><?= Fmt::h((string) $h) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($offen && !$gesperrt): ?>
        <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:10px">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_vorlage_speichern"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
          <input type="hidden" name="vorlage" value="<?= (int) $v['id'] ?>"><input type="hidden" name="sprache" value="<?= Fmt::h((string) $v['sprache']) ?>">
          <input type="hidden" name="kanal" value="<?= Fmt::h((string) $v['kanal']) ?>">
          <div class="feld"><label>Betreff</label><input name="betreff" value="<?= Fmt::h((string) $v['betreff']) ?>"></div>
          <div class="feld"><label>Text</label><textarea class="akq-text" name="text" rows="18"><?= Fmt::h((string) $v['text']) ?></textarea></div>
          <button class="knopf">Speichern und prüfen</button>
        </form>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>" data-frage="Diesen Text als geprüft freigeben? Verschickt wird damit noch nichts." data-ja="Ja, freigeben">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_vorlage_freigeben"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
            <input type="hidden" name="vorlage" value="<?= (int) $v['id'] ?>"><button class="knopf haupt"<?= $hinweise ? ' disabled title="Erst die Beanstandungen beheben"' : '' ?>>Text freigeben</button></form>
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_vorlage_verwerfen"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
            <input type="hidden" name="vorlage" value="<?= (int) $v['id'] ?>"><button class="knopf">Verwerfen</button></form>
        </div>
      <?php else: ?>
        <?php if ($v['betreff']): ?><p><b><?= Fmt::h((string) $v['betreff']) ?></b></p><?php endif; ?>
        <div class="beleg" style="font-family:inherit;font-size:13.5px;color:var(--text)"><?= Fmt::h((string) $v['text']) ?></div>
      <?php endif; ?>

      <?php if ($v['status'] === 'freigegeben' && !$gesperrt): $g = $gates[$v['kanal']] ?? $gates['email']; ?>
        <div style="border-top:1px solid var(--linie);margin-top:12px;padding-top:12px">
          <?php if ($v['kanal'] === 'email' && in_array($g['status'], [AkquiseGate::ERLAUBT, AkquiseGate::PRUEFEN], true)): ?>
            <?php if ($sperre !== null): ?><p class="akq-klein" style="color:var(--gelb)">Versand gerade nicht möglich: <?= Fmt::h($sperre) ?></p><?php endif; ?>
            <form method="post" action="<?= Fmt::h(url('akquise')) ?>" data-frage="<?= Fmt::h('Diese E-Mail geht jetzt an ' . (string) $f['email'] . ' (' . (string) $f['name'] . '). Eine zweite Ansprache ist danach gesperrt.') ?>" data-ja="Ja, jetzt senden">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_senden"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
              <input type="hidden" name="vorlage" value="<?= (int) $v['id'] ?>">
              <?php if ($g['status'] === AkquiseGate::PRUEFEN): ?>
                <div class="feld"><label>Prüfvermerk (Pflicht): Was wurde wie geprüft?</label><input name="pruefvermerk" required minlength="15"></div>
              <?php endif; ?>
              <button class="knopf haupt"<?= $sperre !== null || empty($f['email']) ? ' disabled' : '' ?>>E-Mail senden an <?= Fmt::h((string) ($f['email'] ?: '—')) ?></button>
            </form>
          <?php else: ?>
            <p class="akq-klein">Das Gate lässt für <?= Fmt::h(AkquiseGate::KANAELE[(string) $v['kanal']] ?? '') ?> keinen automatischen Versand zu
              (<?= Fmt::h(AkquiseGate::STATUS[$g['status']]) ?>). Brief oder Anruf machst du selbst — und vermerkst es hier:</p>
          <?php endif; ?>
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:10px">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_von_hand"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
            <input type="hidden" name="vorlage" value="<?= (int) $v['id'] ?>">
            <div><label class="akq-klein">Kanal</label><select name="kanal" style="width:auto">
              <?php foreach (['brief', 'telefon'] as $k): ?><option value="<?= $k ?>"<?= $v['kanal'] === $k ? ' selected' : '' ?>><?= Fmt::h(AkquiseGate::KANAELE[$k]) ?> (<?= Fmt::h(AkquiseGate::STATUS[$gates[$k]['status']]) ?>)</option><?php endforeach; ?></select></div>
            <div style="flex:1 1 260px"><label class="akq-klein">Begründung / Prüfung</label><input name="begruendung" placeholder="z. B. Brief am 25.09. per Post, Widerspruchshinweis enthalten"></div>
            <button class="knopf">Von Hand kontaktiert</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="zwei">
  <div class="block">
    <h2>Antworten</h2>
    <?php foreach ($antworten as $x): ?>
      <div class="akq-befund"><h4><span class="marke2"><?= Fmt::h(AkquiseText::ANTWORT_KLASSEN[(string) $x['klasse']] ?? '') ?></span>
        <span class="akq-klein" style="font-weight:400"><?= Fmt::h(Fmt::zeit((string) $x['eingang_am'])) ?> · <?= Fmt::h((string) $x['klasse_quelle']) ?></span></h4>
        <?php if ($x['betreff']): ?><p><b><?= Fmt::h((string) $x['betreff']) ?></b></p><?php endif; ?>
        <?php if ($x['text']): ?><div class="beleg" style="font-family:inherit"><?= Fmt::h(mb_strimwidth((string) $x['text'], 0, 1200, '…')) ?></div><?php endif; ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_antwort"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
      <div class="reihe"><div class="feld"><label>Von</label><input name="von"></div><div class="feld"><label>Betreff</label><input name="betreff"></div></div>
      <div class="feld"><label>Text der Antwort</label><textarea name="text" rows="4"></textarea></div>
      <div class="feld"><label>Einordnung</label><select name="klasse"><option value="">automatisch erkennen</option>
        <?php foreach (AkquiseText::ANTWORT_KLASSEN as $k => $w): ?><option value="<?= $k ?>"><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
      <button class="knopf">Antwort eintragen</button>
      <p class="akq-klein" style="margin-top:6px">„Keine Kontaktaufnahme" und „Kein Interesse" sperren die Firma dauerhaft. Beantwortet wird nie automatisch.</p>
    </form>
  </div>

  <div>
    <div class="block" id="analyse">
      <h2>Analyse-Seite</h2>
      <?php if (!$analysen): ?>
        <p class="akq-klein">Eine persönliche, nicht indexierte Seite mit Screenshot, drei belegten Punkten und dem Weg zu Vecom. Aus, bis du sie einschaltest.</p>
        <?php if ($audit && $audit['status'] === 'fertig' && !$gesperrt): ?>
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_analyse_anlegen">
            <input type="hidden" name="firma" value="<?= (int) $f['id'] ?>"><button class="knopf">Analyse-Seite vorbereiten</button></form>
        <?php endif; ?>
      <?php endif; ?>
      <?php foreach ($analysen as $x): $adr = AkquiseAnalyse::adresse($x); ?>
        <p><span class="marke2 <?= (int) $x['aktiv'] ? 'gut' : '' ?>"><?= (int) $x['aktiv'] ? 'sichtbar' : 'aus' ?></span>
          <span class="akq-klein">bis <?= Fmt::h(Fmt::datum((string) $x['gueltig_bis'])) ?> · <?= (int) $x['aufrufe'] ?> Aufrufe</span></p>
        <input readonly value="<?= Fmt::h($adr) ?>" onclick="this.select()" style="margin:6px 0">
        <form method="post" action="<?= Fmt::h(url('akquise')) ?>" <?= (int) $x['aktiv'] ? '' : 'data-frage="Die Seite ist danach für jeden mit dem Link sichtbar (nicht in Suchmaschinen)." data-ja="Ja, einschalten"' ?>>
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_analyse_umschalten"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
          <input type="hidden" name="analyse" value="<?= (int) $x['id'] ?>"><?php if (!(int) $x['aktiv']): ?><input type="hidden" name="an" value="1"><?php endif; ?>
          <button class="knopf"><?= (int) $x['aktiv'] ? 'Ausschalten' : 'Einschalten' ?></button>
          <?php if ((int) $x['aktiv']): ?><a class="knopf" href="<?= Fmt::h($adr) ?>" target="_blank" rel="noopener">Ansehen</a><?php endif; ?>
        </form>
      <?php endforeach; ?>
    </div>

    <?php if (!$gesperrt): ?>
    <div class="block">
      <h2>Sperren (DO NOT CONTACT)</h2>
      <form method="post" action="<?= Fmt::h(url('akquise')) ?>" data-frage="<?= Fmt::h((string) $f['name']) ?> dauerhaft sperren? Domain, E-Mail und Telefon kommen auf die Sperrliste; offene Vorlagen werden verworfen." data-ja="Ja, dauerhaft sperren">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_sperren"><input type="hidden" name="firma" value="<?= (int) $f['id'] ?>">
        <div class="feld"><label>Grund</label><input name="grund" placeholder="z. B. Inhaber am Telefon: kein Interesse, bitte nicht mehr melden"></div>
        <button class="knopf">Dauerhaft sperren</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="block">
  <h2>Verlauf</h2>
  <?php if ($versand): ?>
    <div class="tabellenrahmen" style="margin-bottom:12px"><table><thead><tr><th>Zeit</th><th>Kanal</th><th>Status</th><th>Gate</th><th>Grund</th><th>Wer</th></tr></thead><tbody>
      <?php foreach ($versand as $x): ?><tr><td class="akq-klein"><?= Fmt::h(Fmt::zeit((string) $x['created_at'])) ?></td>
        <td><?= Fmt::h(AkquiseGate::KANAELE[(string) $x['kanal']] ?? '') ?></td><td><span class="marke2 <?= ['gesendet' => 'gut', 'von_hand' => 'gut', 'blockiert' => 'warnung', 'fehler' => 'schlecht', 'bounce' => 'schlecht'][$x['status']] ?? '' ?>"><?= Fmt::h((string) $x['status']) ?></span></td>
        <td class="akq-klein"><?= Fmt::h((string) $x['compliance']) ?></td><td class="akq-klein"><?= Fmt::h((string) $x['grund']) ?></td><td class="akq-klein"><?= Fmt::h((string) $x['actor']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <div class="tabellenrahmen"><table><tbody>
    <?php foreach ($protokoll as $p): ?>
      <tr><td class="akq-klein" style="width:150px"><?= Fmt::h(Fmt::zeit((string) $p['created_at'])) ?></td>
        <td style="width:130px"><span class="marke2"><?= Fmt::h((string) $p['schritt']) ?></span></td>
        <td><?= Fmt::h((string) $p['text']) ?></td><td class="akq-klein"><?= Fmt::h((string) $p['actor']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
