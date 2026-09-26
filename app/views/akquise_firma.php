<?php
/** @var string $ansicht @var array $ampel @var ?array $schritt
 *  @var array $f @var ?array $audit @var array $befunde @var array $top @var array $teile @var array $messwerte @var array $ki
 *  @var array $vorlagen @var array $versand @var array $antworten @var array $protokoll @var array $gates @var ?string $sperre
 *  @var array $analysen @var array $audits */
/* DIE FIRMA IN DREI REITERN (26.09.2026)
   Vorher stand alles untereinander: Gate-Tabelle, sieben Teilwerte, elf
   Messwerte, jeder Befund, jede Vorlage, das Protokoll. Richtig war davon
   alles -- aber wer nur wissen wollte „was mache ich jetzt?", musste es
   suchen. Jetzt: Ueberblick (das Wichtigste und der naechste Schritt),
   Alle Befunde (fuer den, der nachpruefen will), Verlauf (was geschah). */
$akqTeil = 'firma';
$b = Akquise::branchen()[(string) ($f['branche'] ?? '')] ?? [];
$gesperrt = (int) $f['gesperrt'] === 1;
$spracheText = AkquiseText::spracheFuer($f);
$fid = (int) $f['id'];
$seite = url('akquise/' . $fid);
$stufeJetzt = $gesperrt ? 'erledigt' : Akquise::stufe5((string) $f['kontakt_status']);
$stufenReihe = array_keys(Akquise::STUFEN5);
$stufeNr = (int) array_search($stufeJetzt, $stufenReihe, true);
$score = $f['score'] !== null ? (int) $f['score'] : null;

/* Die aktuelle Vorlage: die juengste, die noch zaehlt. Aeltere stehen im Verlauf. */
$aktiv = null;
foreach ($vorlagen as $v) { if (in_array($v['status'], ['entwurf', 'freigegeben'], true)) { $aktiv = $v; break; } }
$kontaktiert = in_array($stufeJetzt, ['kontaktiert', 'antwort'], true) || (bool) array_filter($versand, static fn($v) => in_array($v['status'], ['gesendet', 'von_hand'], true));
$telefonGeht = Akquise::deutschsprachig($f) && in_array($gates['telefon']['status'] ?? '', [AkquiseGate::ERLAUBT, AkquiseGate::PRUEFEN], true);

$schwere = static function (int $s): string {
    $o = '<span class="akq-schwere" title="Gewicht ' . $s . ' von 5">';
    for ($i = 1; $i <= 5; $i++) { $o .= '<i class="' . ($i <= $s ? 'an' : '') . '"></i>'; }
    return $o . '</span>';
};
$beleg = static fn(string $st): string => '<span class="akq-beleg ' . ($st === 'VERIFIED' ? 'ja' : 'nein') . '">' . Fmt::h(Akquise::belegWort($st)) . '</span>';
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
$reiterUrl = static fn(string $a): string => $seite . ($a === 'ueberblick' ? '' : '?ansicht=' . $a);
$post = static function (string $tat, string $inhalt = '', string $attr = '') use ($fid): string {
    return '<form method="post" action="' . Fmt::h(url('akquise')) . '"' . $attr . '>' . Csrf::feld()
        . '<input type="hidden" name="tat" value="' . Fmt::h($tat) . '"><input type="hidden" name="firma" value="' . $fid . '">' . $inhalt . '</form>';
};
?>
<div class="kopf akq-kopf"><div>
  <p class="akq-klein"><a href="<?= Fmt::h(url('akquise')) ?>">← Alle Betriebe</a></p>
  <h1><?= Fmt::h((string) $f['name']) ?></h1>
  <div class="akq-zeile">
    <?php if ($score !== null): ?>
      <span class="akq-chance s-<?= Fmt::h((string) $f['score_stufe']) ?>" title="Wie gut passt Vecom hier? 0–100, nur intern">
        Chance <b><?= $score ?></b> · <?= Fmt::h(Akquise::chanceWort($score)) ?></span>
    <?php endif; ?>
    <span class="akq-ampel <?= Fmt::h($ampel['farbe']) ?>"><i></i><?= Fmt::h($ampel['wort']) ?></span>
    <span class="akq-klein"><?= Fmt::h(implode(' · ', array_filter([(string) $f['stadt'], Akquise::branchenName($f['branche'])]))) ?></span>
  </div>
  <ol class="akq-stufen5" aria-label="Wo steht dieser Betrieb?">
    <?php foreach (Akquise::STUFEN5 as $k => [$wort]): $nr = (int) array_search($k, $stufenReihe, true); ?>
      <li class="<?= $nr < $stufeNr ? 'st-fertig' : ($nr === $stufeNr ? 'st-jetzt' : '') ?>"<?= $nr === $stufeNr ? ' aria-current="step"' : '' ?>><?= Fmt::h($wort) ?></li>
    <?php endforeach; ?>
  </ol>
</div>
<?php if ($schritt !== null): ?>
  <div class="akq-schritt">
    <span class="akq-klein">Nächster Schritt</span>
    <?php if ($schritt['art'] === 'post'): ?>
      <?= $post($schritt['ziel'], '<input type="hidden" name="zurueck" value="akquise/' . $fid . '"><button class="knopf haupt">' . Fmt::h($schritt['wort']) . '</button>') ?>
    <?php elseif ($schritt['art'] === 'still'): ?>
      <span class="knopf" aria-disabled="true"><?= Fmt::h($schritt['wort']) ?> …</span>
    <?php else: ?>
      <a class="knopf haupt" href="<?= Fmt::h(url($schritt['ziel'])) ?>"<?= str_ends_with($schritt['ziel'], '/brief') || str_ends_with($schritt['ziel'], '/anruf') ? ' target="_blank" rel="noopener"' : '' ?>><?= Fmt::h($schritt['wort']) ?></a>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<nav class="akq-unterreiter" aria-label="Ansicht">
  <?php foreach (['ueberblick' => 'Überblick', 'befunde' => 'Alle Befunde (' . count($befunde) . ')', 'verlauf' => 'Verlauf'] as $k => $w): ?>
    <a href="<?= Fmt::h($reiterUrl($k)) ?>" class="<?= $ansicht === $k ? 'an' : '' ?>"<?= $ansicht === $k ? ' aria-current="page"' : '' ?>><?= Fmt::h($w) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($ansicht === 'ueberblick'): ?>
<!-- ============================ ÜBERBLICK ============================ -->
<div class="zwei">
  <div>
    <div class="block">
      <h2>Das Wichtigste</h2>
      <?php if (!$audit): ?>
        <div class="leer"><?= $f['url'] ? 'Noch nicht geprüft. Dein Rechner nimmt die Seite beim nächsten Nachtlauf mit.'
            : 'Keine eigene Website bekannt.' . (Akquise::deutschsprachig($f) ? ' Ein Anruf ist hier der beste Weg.' : '') ?></div>
      <?php elseif (!$top): ?>
        <div class="leer">Keine Probleme gefunden — die Website ist in gutem Zustand.</div>
      <?php else: ?>
        <ol class="akq-drei">
          <?php foreach ($top as $t): ?>
            <li><b><?= Fmt::h((string) $t['titel']) ?></b> <?= $beleg((string) $t['status']) ?>
              <?php if (!empty($t['wirkung'])): ?><div class="akq-klein"><?= Fmt::h((string) $t['wirkung']) ?></div><?php endif; ?></li>
          <?php endforeach; ?>
        </ol>
        <p class="akq-klein">„geprüft“ = gemessen oder im Quelltext belegt. „unsicher“ = nur vermutet — kommt nicht in Texte, bevor du es bestätigst.
          <a href="<?= Fmt::h($reiterUrl('befunde')) ?>" style="text-decoration:underline">Alle Befunde</a></p>
      <?php endif; ?>
      <?php if ($audit && ($audit['screenshot_mobil'] || $audit['screenshot_desktop'])): ?>
        <div class="akq-foto" style="margin-top:14px">
          <?php if ($audit['screenshot_mobil']): ?><figure><img src="<?= Fmt::h(url('akquise/bild/' . (int) $audit['id'] . '/mobil')) ?>" width="150" height="325" alt="Startseite auf dem Handy" loading="lazy">
            <figcaption class="akq-klein">Handy</figcaption></figure><?php endif; ?>
          <?php if ($audit['screenshot_desktop']): ?><figure style="flex:1 1 300px"><img src="<?= Fmt::h(url('akquise/bild/' . (int) $audit['id'] . '/desktop')) ?>" width="520" height="325" alt="Startseite am Computer" loading="lazy">
            <figcaption class="akq-klein">Computer</figcaption></figure><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($audit && ($loesungen || trim((string) $audit['loesung']) !== '' || trim((string) $audit['experience']) !== '')): ?>
    <div class="block">
      <h2>So könnten wir helfen</h2>
      <?php if (trim((string) $audit['loesung']) !== ''): ?>
        <p style="white-space:pre-wrap"><?= Fmt::h((string) $audit['loesung']) ?></p>
      <?php else: ?>
        <ul style="padding-left:18px;margin:0"><?php foreach (array_unique($loesungen) as $l): ?><li><?= Fmt::h(mb_strtoupper(mb_substr($l, 0, 1)) . mb_substr($l, 1)) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if (trim((string) $audit['experience']) !== ''): ?>
        <p style="margin-top:10px"><b>Besonderes Erlebnis:</b> <?= Fmt::h((string) $audit['experience']) ?></p>
      <?php elseif (!empty($b['experience_idee'])): ?>
        <p class="akq-klein" style="margin-top:10px">Idee aus der Branche (nicht geprüft): <?= Fmt::h((string) $b['experience_idee']) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <!-- ====== Kontakt: genau ein Weg, je nach Stand ====== -->
    <?php if ($kontaktiert && !$gesperrt): ?>
    <div class="block akq-fokus" id="antwort">
      <h2>Hat sich jemand gemeldet?</h2>
      <p class="akq-klein">Trag die Antwort ein — egal ob Mail, Anruf oder Brief. Beantwortet wird nie automatisch.</p>
      <?= $post('akq_antwort', '
        <div class="feld"><label>Was wurde gesagt oder geschrieben?</label><textarea name="text" rows="4"></textarea></div>
        <div class="reihe"><div class="feld"><label>Von</label><input name="von" placeholder="Name oder Adresse"></div>
          <div class="feld"><label>Einordnung</label><select name="klasse"><option value="">automatisch erkennen</option>'
          . implode('', array_map(static fn($k, $w) => '<option value="' . $k . '">' . Fmt::h($w) . '</option>', array_keys(AkquiseText::ANTWORT_KLASSEN), AkquiseText::ANTWORT_KLASSEN))
          . '</select></div></div>
        <button class="knopf">Antwort eintragen</button>
        <p class="akq-klein" style="margin-top:6px">„Kein Interesse“ und „Bitte nicht mehr melden“ sperren den Betrieb dauerhaft.</p>') ?>
    </div>
    <?php endif; ?>

    <div class="block" id="kontakt">
      <h2>Ansprechen</h2>
      <?php if ($gesperrt): ?>
        <div class="leer">Nicht ansprechen — der Betrieb steht auf der Sperrliste.</div>
      <?php elseif ($kontaktiert && !$aktiv): ?>
        <p class="akq-klein">Schon kontaktiert. Was verschickt wurde, steht im <a href="<?= Fmt::h($reiterUrl('verlauf')) ?>" style="text-decoration:underline">Verlauf</a>.</p>
      <?php elseif (!$aktiv): ?>
        <?php if ($audit && $audit['status'] === 'fertig'): ?>
          <p class="akq-klein" style="margin-bottom:10px">
            <?= $ampel['farbe'] === 'gruen' ? 'E-Mail ist hier erlaubt.' : 'E-Mail ohne Einwilligung ist nicht erlaubt — der Brief ist der Weg.' ?>
            Der Text entsteht aus den geprüften Befunden; du liest ihn, bevor irgendetwas passiert.</p>
          <?= $post('akq_vorlage_regel', '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <button class="knopf akq-los">' . ($ampel['farbe'] === 'gruen' ? 'E-Mail schreiben' : 'Brief schreiben') . '</button>
            <details><summary class="akq-klein" style="cursor:pointer">anders …</summary><div style="display:flex;gap:8px;margin-top:8px">
              <select name="sprache" style="width:auto">' . implode('', array_map(static fn($k, $w) => '<option value="' . $k . '"' . ($k === $spracheText ? ' selected' : '') . '>' . $w . '</option>', array_keys(AkquiseText::SPRACHEN), AkquiseText::SPRACHEN)) . '</select>
              <select name="kanal" style="width:auto"><option value="">passend</option><option value="brief">Brief</option><option value="email">E-Mail</option></select>
            </div></details></div>') ?>
        <?php else: ?>
          <p class="akq-klein">Erst nach der Prüfung der Website entsteht ein Text.</p>
        <?php endif; ?>
      <?php else: $v = $aktiv; $hinweise = $v['pruefhinweise'] ? (json_decode((string) $v['pruefhinweise'], true) ?: []) : []; ?>
        <p class="akq-klein" style="margin-bottom:8px"><?= Fmt::h(AkquiseGate::KANAELE[(string) $v['kanal']] ?? '') ?> auf <?= Fmt::h(AkquiseText::SPRACHEN[(string) $v['sprache']] ?? '') ?>
          · <?= $v['status'] === 'entwurf' ? 'Entwurf — bitte lesen' : 'freigegeben von ' . Fmt::h((string) $v['freigegeben_von']) ?>
          · geschrieben von <?= Fmt::h((string) $v['erzeugt_von']) ?></p>
        <?php if ($hinweise): ?><ul class="akq-hinweise"><?php foreach ($hinweise as $h): ?><li><?= Fmt::h((string) $h) ?></li><?php endforeach; ?></ul><?php endif; ?>

        <?php if ($v['status'] === 'entwurf'): ?>
          <?= $post('akq_vorlage_speichern', '<input type="hidden" name="vorlage" value="' . (int) $v['id'] . '">
            <input type="hidden" name="sprache" value="' . Fmt::h((string) $v['sprache']) . '"><input type="hidden" name="kanal" value="' . Fmt::h((string) $v['kanal']) . '">
            <div class="feld"><label>Betreff</label><input name="betreff" value="' . Fmt::h((string) $v['betreff']) . '"></div>
            <div class="feld"><label>Text</label><textarea class="akq-text" name="text" rows="16">' . Fmt::h((string) $v['text']) . '</textarea></div>
            <button class="knopf">Änderung speichern</button>', ' style="margin-top:10px"') ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <?= $post('akq_vorlage_freigeben', '<input type="hidden" name="vorlage" value="' . (int) $v['id'] . '"><button class="knopf akq-los"'
                . ($hinweise ? ' disabled title="Erst die Beanstandungen oben beheben"' : '') . '>Text ist gut — freigeben</button>') ?>
            <?= $post('akq_vorlage_verwerfen', '<input type="hidden" name="vorlage" value="' . (int) $v['id'] . '"><button class="knopf">Verwerfen</button>') ?>
          </div>
        <?php else: ?>
          <?php if ($v['betreff']): ?><p><b><?= Fmt::h((string) $v['betreff']) ?></b></p><?php endif; ?>
          <details><summary class="akq-klein" style="cursor:pointer">Text ansehen</summary>
            <div class="beleg" style="font-family:inherit;font-size:13.5px;color:var(--text);white-space:pre-wrap;margin-top:8px"><?= Fmt::h((string) $v['text']) ?></div></details>

          <?php if ($v['kanal'] === 'brief'): ?>
            <div class="akq-weg">
              <a class="knopf akq-los" href="<?= Fmt::h(url('akquise/' . $fid . '/brief')) ?>" target="_blank" rel="noopener">Brief drucken</a>
              <span class="akq-klein">Öffnet den Brief auf Vecom-Briefbogen mit Bildschirmfoto und QR-Code — drucken oder als PDF speichern.</span>
            </div>
            <?= $post('akq_brief_verschickt', '<input type="hidden" name="vorlage" value="' . (int) $v['id'] . '">
              <label class="akq-haken"><input type="checkbox" name="bestaetigt" value="1" required>
                Brief ist eingeworfen. Mir ist kein Werbewiderspruch dieses Betriebs bekannt, und der Hinweis „keine Nachricht mehr gewünscht“ steht im Brief.</label>
              <button class="knopf">Als verschickt vermerken</button>', ' style="margin-top:14px;border-top:1px solid var(--linie);padding-top:12px"') ?>
          <?php else: $g = $gates['email']; ?>
            <?php if (in_array($g['status'], [AkquiseGate::ERLAUBT, AkquiseGate::PRUEFEN], true)): ?>
              <?php if ($sperre !== null): ?><p class="akq-klein" style="color:var(--gelb)">Gerade nicht möglich: <?= Fmt::h($sperre) ?></p><?php endif; ?>
              <?= $post('akq_senden', '<input type="hidden" name="vorlage" value="' . (int) $v['id'] . '">'
                  . ($g['status'] === AkquiseGate::PRUEFEN ? '<div class="feld"><label>Was hast du geprüft? (Pflicht)</label><input name="pruefvermerk" required minlength="15"></div>' : '')
                  . '<button class="knopf akq-los"' . ($sperre !== null || empty($f['email']) ? ' disabled' : '') . '>E-Mail senden an ' . Fmt::h((string) ($f['email'] ?: '—')) . '</button>',
                  ' style="margin-top:12px" data-frage="' . Fmt::h('Diese E-Mail geht jetzt an ' . (string) $f['email'] . ' (' . (string) $f['name'] . '). Eine zweite Ansprache ist danach gesperrt.') . '" data-ja="Ja, jetzt senden"') ?>
            <?php else: ?>
              <p class="akq-klein" style="margin-top:10px">E-Mail ist hier nicht erlaubt (<?= Fmt::h(AkquiseGate::STATUS[$g['status']]) ?>). Verwirf den Text und schreib einen Brief.</p>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>

      <?php if (!$gesperrt && !$kontaktiert && $telefonGeht): ?>
        <p class="akq-klein" style="margin-top:14px;border-top:1px solid var(--linie);padding-top:12px">
          Lieber anrufen? <a href="<?= Fmt::h(url('akquise/' . $fid . '/anruf')) ?>" target="_blank" rel="noopener" style="text-decoration:underline">Anrufzettel öffnen</a>
          <?= $f['telefon'] ? '· ' . Fmt::h((string) $f['telefon']) : '' ?></p>
      <?php endif; ?>
    </div>

    <div class="block">
      <h2>Darf ich den Betrieb ansprechen?</h2>
      <p><span class="akq-ampel <?= Fmt::h($ampel['farbe']) ?>"><i></i><?= Fmt::h($ampel['wort']) ?></span></p>
      <details style="margin-top:8px"><summary class="akq-klein" style="cursor:pointer">Warum? Alle Wege einzeln</summary>
        <table style="margin-top:8px"><tbody>
          <?php foreach ($gates as $kanal => $g): ?>
            <tr><td style="width:120px"><?= Fmt::h(AkquiseGate::KANAELE[$kanal]) ?></td>
              <td><span class="marke2 <?= $gateKlasse($g['status']) ?>"><?= Fmt::h(AkquiseGate::STATUS[$g['status']]) ?></span>
                <div class="akq-klein" style="margin-top:4px"><?= Fmt::h(implode(' ', $g['gruende'])) ?></div></td></tr>
          <?php endforeach; ?>
        </tbody></table>
        <p class="akq-klein" style="margin-top:8px">Keine Rechtsberatung. Die Regeln stehen unter
          <a href="<?= Fmt::h(url('akquise/regeln')) ?>" style="text-decoration:underline">Regeln &amp; Versand</a>.</p>
      </details>
    </div>

    <div class="block">
      <h2>Betrieb</h2>
      <table><tbody>
        <tr><td style="width:110px" class="akq-klein">Website</td><td><?php if ($f['url']): ?>
          <a href="<?= Fmt::h((string) $f['url']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--cyan)"><?= Fmt::h((string) ($f['domain'] ?: $f['url'])) ?></a>
          <?php else: ?>keine bekannt<?php endif; ?></td></tr>
        <tr><td class="akq-klein">Adresse</td><td><?= Fmt::h(implode(', ', array_filter([(string) $f['adresse'], trim(($f['plz'] ?? '') . ' ' . ($f['stadt'] ?? '')), $f['land'] === 'DE' ? 'Deutschland' : 'Italien']))) ?></td></tr>
        <tr><td class="akq-klein">Kontakt</td><td><?= Fmt::h(implode(' · ', array_filter([(string) $f['ansprechpartner'], (string) $f['telefon'], (string) $f['email']]))) ?: '—' ?></td></tr>
        <tr><td class="akq-klein">Sprache</td><td><?= Fmt::h(AkquiseText::SPRACHEN[$spracheText]) ?><?= !empty($b['berufsrecht']) ? ' · <span class="marke2 warnung">Berufsrecht beachten</span>' : '' ?></td></tr>
      </tbody></table>
      <?php if (trim((string) $f['notiz']) !== ''): ?><p class="akq-klein" style="margin-top:8px">✎ <?= Fmt::h((string) $f['notiz']) ?></p><?php endif; ?>

      <details style="margin-top:12px"><summary class="akq-klein" style="cursor:pointer">Angaben ändern</summary>
        <?= $post('akq_firma_speichern', '
          <div class="reihe">
            <div class="feld"><label>Ansprechpartner</label><input name="ansprechpartner" value="' . Fmt::h((string) $f['ansprechpartner']) . '"></div>
            <div class="feld"><label>Branche</label><select name="branche"><option value="">—</option>'
            . implode('', array_map(static fn($k, $x) => '<option value="' . Fmt::h((string) $k) . '"' . ($f['branche'] === $k ? ' selected' : '') . '>' . Fmt::h((string) $x['de']) . '</option>', array_keys(Akquise::branchen()), Akquise::branchen()))
            . '</select></div>
            <div class="feld"><label>E-Mail (geschäftlich)</label><input name="email" type="email" value="' . Fmt::h((string) $f['email']) . '"></div>
            <div class="feld"><label>Telefon</label><input name="telefon" value="' . Fmt::h((string) $f['telefon']) . '"></div>
            <div class="feld"><label>Sprache</label><select name="sprache"><option value="">automatisch</option>'
            . implode('', array_map(static fn($k, $w) => '<option value="' . $k . '"' . ($f['sprache'] === $k ? ' selected' : '') . '>' . $w . '</option>', array_keys(AkquiseText::SPRACHEN), AkquiseText::SPRACHEN))
            . '</select></div>
          </div>
          <input type="hidden" name="kontakt_status" value="' . Fmt::h((string) $f['kontakt_status']) . '">
          <div class="feld"><label>Einwilligung — nur mit Beleg: wer, wann, wie</label>
            <input name="einwilligung" value="' . Fmt::h((string) $f['einwilligung']) . '" placeholder="z. B. 24.09.2026, Inhaber am Telefon: „Schicken Sie mir das per Mail“"></div>
          <label class="akq-haken"><input type="checkbox" name="bestandskunde" value="1"' . ((int) $f['bestandskunde'] ? ' checked' : '') . '> Ist schon unser Kunde</label>
          <div class="feld"><label>Notiz</label><textarea name="notiz" rows="3">' . Fmt::h((string) $f['notiz']) . '</textarea></div>
          <button class="knopf">Speichern</button>', ' style="margin-top:12px"') ?>
      </details>
      <?php if (!$gesperrt): ?>
        <details style="margin-top:8px"><summary class="akq-klein" style="cursor:pointer">Nie mehr ansprechen</summary>
          <?= $post('akq_sperren', '<div class="feld"><label>Grund</label><input name="grund" placeholder="z. B. Inhaber am Telefon: bitte nicht mehr melden"></div>
            <button class="knopf">Dauerhaft sperren</button>', ' style="margin-top:10px"') ?>
        </details>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php elseif ($ansicht === 'befunde'): ?>
<!-- ============================ ALLE BEFUNDE ============================ -->
<?php if (!$audit): ?>
  <div class="block"><div class="leer"><?= $f['url'] ? 'Noch nicht geprüft.' : 'Keine eigene Website bekannt.' ?></div></div>
<?php else: ?>
<div class="block">
  <h2>Prüfung vom <?= Fmt::h(Fmt::datum((string) $audit['beendet_am'])) ?>
    <span class="akq-klein" style="font-weight:400">· <?= (int) $audit['seiten'] ?> Seite(n) angesehen<?= $audit['ki_modell'] ? ' · mit Claude gedeutet' : '' ?></span></h2>
  <?php if ($teile): ?>
    <p class="akq-klein" style="margin-bottom:8px">Woraus sich die Chance von <?= $score ?? '—' ?> zusammensetzt — je voller der Balken, desto mehr gibt es zu verbessern:</p>
    <div class="akq-teile" style="margin-bottom:14px">
      <?php foreach (AkquiseScore::GEWICHTE as $topf => $max): $w = (float) ($teile[$topf] ?? 0); ?>
        <div class="akq-teil"><div class="akq-klein"><?= Fmt::h(['technik' => 'Technik & Tempo', 'mobile' => 'Handy', 'ux' => 'Anfragen & Buchen', 'design' => 'Gestaltung',
            'seo' => 'Gefunden werden', 'vertrauen' => 'Vertrauen', 'experience' => 'Besonderes Erlebnis'][$topf]) ?></div>
          <b><?= number_format($w, 0) ?></b><span class="akq-klein"> von <?= $max ?></span>
          <div class="balken"><span style="width:<?= $max > 0 ? round($w / $max * 100) : 0 ?>%"></span></div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (isset($messwerte['performance']) || isset($messwerte['lcp_ms'])): ?>
    <details><summary class="akq-klein" style="cursor:pointer">Messwerte (<?= Fmt::h((string) ($messwerte['quelle'] ?? 'Lighthouse')) ?>, Handy)</summary>
      <div class="tabellenrahmen" style="margin-top:8px"><table><thead><tr>
        <th>Tempo-Note</th><th>Hauptinhalt sichtbar</th><th>Springen</th><th>Blockiert</th><th>Erster Inhalt</th><th>Server</th><th>Gewicht</th><th>SEO</th><th>Barrierefrei</th></tr></thead>
        <tbody><tr>
          <td><?= $mw($messwerte, 'performance') ?></td><td><?= $mw($messwerte, 'lcp_ms', ' s', 1000, 1) ?></td>
          <td><?= isset($messwerte['cls']) ? number_format((float) $messwerte['cls'], 2, ',', '.') : '—' ?></td>
          <td><?= $mw($messwerte, 'tbt_ms', ' ms') ?></td><td><?= $mw($messwerte, 'fcp_ms', ' s', 1000, 1) ?></td>
          <td><?= $mw($messwerte, 'ttfb_ms', ' ms') ?></td><td><?= $mw($messwerte, 'bytes', ' MB', 1048576, 1) ?></td>
          <td><?= $mw($messwerte, 'seo') ?></td><td><?= $mw($messwerte, 'accessibility') ?></td>
        </tr></tbody></table></div></details>
  <?php endif; ?>
  <?= $post('akq_neu_pruefen', '<button class="knopf">Neu prüfen lassen</button>'
      . (count($audits) > 1 ? '<span class="akq-klein" style="margin-left:8px">Frühere Prüfungen: ' . implode(' ', array_map(static fn($x) => Fmt::h(Fmt::datum((string) $x['beendet_am'])) . ' (' . ($x['score'] ?? '—') . ')', array_slice($audits, 1))) . '</span>' : ''),
      ' style="margin-top:12px"') ?>
</div>

<div class="block">
  <h2>Alle Befunde <span class="akq-klein" style="font-weight:400">· <?= count($befunde) ?>, davon <?= count(array_filter($befunde, static fn($x) => $x['status'] === 'VERIFIED')) ?> geprüft</span></h2>
  <?php if (!$befunde): ?><div class="leer">Nichts gefunden.</div><?php endif; ?>
  <?php foreach (Akquise::KATEGORIEN as $kat => $katName):
      $liste = array_values(array_filter($befunde, static fn($x) => $x['kategorie'] === $kat));
      if (!$liste) { continue; } ?>
    <h3 class="akq-kat"><?= Fmt::h($katName) ?></h3>
    <?php foreach ($liste as $x): ?>
      <div class="akq-befund<?= $x['status'] !== 'VERIFIED' ? ' unbelegt' : '' ?>">
        <h4><?= $schwere((int) $x['schwere']) ?> <?= Fmt::h((string) $x['titel']) ?> <?= $beleg((string) $x['status']) ?></h4>
        <?php if ($x['beschreibung']): ?><p><?= Fmt::h((string) $x['beschreibung']) ?></p><?php endif; ?>
        <?php if ($x['wirkung']): ?><p><i>Was das bedeuten kann:</i> <?= Fmt::h((string) $x['wirkung']) ?></p><?php endif; ?>
        <?php if ($x['beleg'] || $x['url']): ?><details><summary class="akq-klein" style="cursor:pointer;margin-top:6px">Beleg</summary>
          <div class="beleg"><?= Fmt::h(trim(($x['url'] ? $x['url'] . "\n" : '') . (string) $x['beleg'])) ?></div></details><?php endif; ?>
        <?= $post('akq_befund_verwerfen', '<input type="hidden" name="befund" value="' . (int) $x['id'] . '"><input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=befunde">
          <button class="knopf klein">Stimmt nicht</button>', ' style="margin-top:6px"') ?>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ============================ VERLAUF ============================ -->
<div class="zwei">
  <div>
    <div class="block">
      <h2>Antworten</h2>
      <?php if (!$antworten): ?><div class="leer">Noch keine.</div><?php endif; ?>
      <?php foreach ($antworten as $x): ?>
        <div class="akq-befund"><h4><span class="marke2"><?= Fmt::h(AkquiseText::ANTWORT_KLASSEN[(string) $x['klasse']] ?? '') ?></span>
          <span class="akq-klein" style="font-weight:400"><?= Fmt::h(Fmt::zeit((string) $x['eingang_am'])) ?></span></h4>
          <?php if ($x['betreff']): ?><p><b><?= Fmt::h((string) $x['betreff']) ?></b></p><?php endif; ?>
          <?php if ($x['text']): ?><div class="beleg" style="font-family:inherit"><?= Fmt::h(mb_strimwidth((string) $x['text'], 0, 1200, '…')) ?></div><?php endif; ?></div>
      <?php endforeach; ?>
    </div>

    <div class="block">
      <h2>Verschickte und verworfene Texte</h2>
      <?php $alt = array_filter($vorlagen, static fn($v) => !in_array($v['status'], ['entwurf', 'freigegeben'], true)); ?>
      <?php if (!$alt): ?><div class="leer">Noch keine.</div><?php endif; ?>
      <?php foreach ($alt as $v): ?>
        <details class="akq-befund" style="background:var(--flaeche)"><summary style="cursor:pointer">
          <?= Fmt::h(AkquiseGate::KANAELE[(string) $v['kanal']] ?? '') ?> · <?= Fmt::h(AkquiseText::SPRACHEN[(string) $v['sprache']] ?? '') ?>
          · <?= Fmt::h(['gesendet' => 'verschickt', 'verworfen' => 'verworfen'][$v['status']] ?? (string) $v['status']) ?>
          <span class="akq-klein">· <?= Fmt::h(Fmt::zeit((string) $v['updated_at'])) ?></span></summary>
          <?php if ($v['betreff']): ?><p style="margin-top:8px"><b><?= Fmt::h((string) $v['betreff']) ?></b></p><?php endif; ?>
          <div class="beleg" style="font-family:inherit;font-size:13.5px;color:var(--text);white-space:pre-wrap"><?= Fmt::h((string) $v['text']) ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="block" id="analyse">
      <h2>Persönliche Analyse-Seite</h2>
      <?php if (!$analysen): ?>
        <p class="akq-klein">Entsteht mit der Freigabe eines Briefs — der QR-Code führt dorthin. Nicht in Suchmaschinen, nach 60 Tagen aus.</p>
        <?php if ($audit && $audit['status'] === 'fertig' && !$gesperrt): ?>
          <?= $post('akq_analyse_anlegen', '<button class="knopf">Jetzt schon vorbereiten</button>') ?>
        <?php endif; ?>
      <?php endif; ?>
      <?php foreach ($analysen as $x): $adr = AkquiseAnalyse::adresse($x); ?>
        <p><span class="marke2 <?= (int) $x['aktiv'] ? 'gut' : '' ?>"><?= (int) $x['aktiv'] ? 'sichtbar' : 'aus' ?></span>
          <span class="akq-klein">bis <?= Fmt::h(Fmt::datum((string) $x['gueltig_bis'])) ?> · <?= (int) $x['aufrufe'] ?>× geöffnet</span></p>
        <input readonly value="<?= Fmt::h($adr) ?>" onclick="this.select()" style="margin:6px 0">
        <?= $post('akq_analyse_umschalten', '<input type="hidden" name="analyse" value="' . (int) $x['id'] . '">' . ((int) $x['aktiv'] ? '' : '<input type="hidden" name="an" value="1">')
            . '<input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=verlauf">'
            . '<button class="knopf">' . ((int) $x['aktiv'] ? 'Ausschalten' : 'Einschalten') . '</button>'
            . ((int) $x['aktiv'] ? ' <a class="knopf" href="' . Fmt::h($adr) . '" target="_blank" rel="noopener">Ansehen</a>' : '')) ?>
      <?php endforeach; ?>
    </div>

    <?php if (!$gesperrt && $aktiv === null && !$kontaktiert): ?>
    <div class="block">
      <h2>Schon selbst Kontakt gehabt?</h2>
      <?= $post('akq_von_hand', '<div class="reihe"><div class="feld"><label>Wie</label><select name="kanal"><option value="telefon">Anruf</option><option value="brief">Brief</option></select></div>
          <div class="feld"><label>Wann und was</label><input name="begruendung" placeholder="z. B. 25.09. angerufen, Inhaber war da"></div></div>
          <button class="knopf">Vermerken</button>') ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="block">
  <h2>Was geschah</h2>
  <?php if ($versand): ?>
    <div class="tabellenrahmen" style="margin-bottom:12px"><table><thead><tr><th>Wann</th><th>Weg</th><th>Ergebnis</th><th>Grund</th><th>Wer</th></tr></thead><tbody>
      <?php foreach ($versand as $x): ?><tr><td class="akq-klein"><?= Fmt::h(Fmt::zeit((string) $x['created_at'])) ?></td>
        <td><?= Fmt::h(AkquiseGate::KANAELE[(string) $x['kanal']] ?? '') ?></td>
        <td><span class="marke2 <?= ['gesendet' => 'gut', 'von_hand' => 'gut', 'blockiert' => 'warnung', 'fehler' => 'schlecht', 'bounce' => 'schlecht'][$x['status']] ?? '' ?>"><?= Fmt::h(['gesendet' => 'verschickt', 'von_hand' => 'selbst gemacht', 'blockiert' => 'gestoppt', 'fehler' => 'Fehler', 'bounce' => 'kam zurück'][$x['status']] ?? (string) $x['status']) ?></span></td>
        <td class="akq-klein"><?= Fmt::h((string) $x['grund']) ?></td><td class="akq-klein"><?= Fmt::h((string) $x['actor']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <div class="tabellenrahmen"><table><tbody>
    <?php foreach ($protokoll as $p): ?>
      <tr><td class="akq-klein" style="width:150px"><?= Fmt::h(Fmt::zeit((string) $p['created_at'])) ?></td>
        <td><?= Fmt::h((string) $p['text']) ?></td><td class="akq-klein"><?= Fmt::h((string) $p['actor']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <p class="akq-klein" style="margin-top:8px"><?= Fmt::h((string) $f['kennung']) ?> · gefunden über <?= Fmt::h((string) ($f['quelle'] ?? 'von Hand')) ?>
    am <?= Fmt::h(Fmt::datum((string) $f['recherchiert_am'])) ?><?= $f['quelle_lizenz'] ? ' · ' . Fmt::h((string) $f['quelle_lizenz']) : '' ?></p>
</div>
<?php endif; ?>
