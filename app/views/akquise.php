<?php
/** @var array $liste @var array $filter @var array $werte @var array $kz @var array $grenzen @var int $wartend
 *  @var array $suchen @var array $branchen @var array $signale @var array $checks @var array $plattform */
/* DIE LISTE (26.09.2026, einfacher gemacht)
   Fuenf Spalten statt sieben, vier Filter sichtbar statt vierzehn, und in
   jeder Zeile genau ein naechster Schritt. Alles andere ist noch da --
   unter „Mehr Filter“ und auf der Firmenseite --, aber es steht nicht mehr
   zwischen Uwe und der Frage „wen spreche ich heute an?“. */
$akqTeil = '';
require_once dirname(__DIR__) . '/src/AkquiseAnsprechen.php';
require_once dirname(__DIR__) . '/src/AkquiseMail.php';
require_once dirname(__DIR__) . '/src/AkquisePrio.php';
$wert = static fn(string $k): string => (string) ($filter[$k] ?? '');
$gewaehlt = static fn(string $k, string $v): string => (($filter[$k] ?? '') === $v) ? ' selected' : '';
$seitenUrl = static function (int $s) use ($filter): string {
    return url('akquise') . '?' . http_build_query($filter + ['seite' => $s]);
};
$mehrOffen = (bool) array_intersect_key(array_filter($filter, static fn($v) => $v !== ''),
    array_flip(['q', 'region', 'kreis', 'kontakt', 'compliance', 'audit', 'stufe', 'score_min', 'von', 'bis', 'sort', 'gesperrte', 'prio_min', 'prio', 'mit_email', 'mit_whatsapp']));
$kachel = static fn(string $k, string $v): string => url('akquise') . '?' . http_build_query([$k => $v]);
?>
<div class="kopf"><div><h1>Neue Kunden finden</h1>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">
    Betriebe ohne Website oder mit schwacher Website — dein Rechner findet und prüft sie nachts. Du sprichst sie an.</p></div>
</div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<?php /* So läuft es (29.09.2026, Uwe: Ja zu K1) */ ?>
<ol class="akq-weg3" aria-label="So läuft es">
  <li><b>1 · Betrieb aussuchen</b><span>Unten in der Liste — zuerst die ohne Website oder mit großer Chance.</span></li>
  <li><b>2 · Ansprechen</b><span>Knopf „Ansprechen“: fertige Texte für Anruf, Besuch, E-Mail und WhatsApp. Mail und WhatsApp gehen erst, wenn er zugestimmt hat.</span></li>
  <li><b>3 · Er sagt Ja</b><span>„Hat zugestimmt“ eintragen — dann bekommt er sein Dashboard, und die Folge-Mails laufen automatisch.</span></li>
</ol>

<?php /* Starten und Stoppen + Fortschritt (29.09.2026, Uwe) -- erneuert sich alle 30 Sekunden selbst */ ?>
<div class="block akq-steuer" id="steuerung" data-quelle="<?= Fmt::h(url('akquise/steuerung')) ?>" aria-live="polite">
  <?php require __DIR__ . '/akquise_steuerung.php'; ?>
</div>
<script>
(function () {
  var box = document.getElementById('steuerung'); if (!box || !window.fetch) return;
  function neu() {
    if (document.hidden || box.contains(document.activeElement) && document.activeElement.tagName === 'BUTTON') return;
    fetch(box.getAttribute('data-quelle'), { credentials: 'same-origin', headers: { 'X-Teil': '1' } })
      .then(function (r) { return r.ok ? r.text() : null; })
      .then(function (h) { if (h && h.indexOf('akq-st-kopf') !== -1) box.innerHTML = h; })
      .catch(function () {});
  }
  setInterval(neu, 30000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) neu(); });
})();
</script>

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
    <?php if ($suchen): ?>Vorgemerkt:
      <?php foreach ($suchen as $s): ?><span class="akq-chip"><?= Fmt::h((string) $s['gebiet']) ?><?= $s['status'] === 'laeuft' ? ' · läuft' : '' ?></span><?php endforeach; ?>
    <?php else: ?>Dein PC fängt binnen fünf Minuten an: erst die Betriebe, dann prüft er ihre Websites (nachts um 02:30).<?php endif; ?>
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

<?php if (!empty($checks)): /* Website-Check (27.09.2026): Betriebe, die selbst gefragt haben. Antworten darf Uwe auf die Anfrage -- Werbung erst mit bestätigter Einwilligung. */ ?>
<div class="block akq-fokus" id="checks">
  <h2 style="font-size:15px;margin:0 0 4px">Anfragen über den Website-Check <span class="akq-klein" style="font-weight:400">· <?= count($checks) ?> offen</span></h2>
  <p class="akq-klein" style="margin:0 0 10px">Diese Betriebe haben ihre Seite selbst auf vecom-design.it geprüft. „Analyse gewünscht“ heißt: Du darfst genau darauf antworten.
    Werbung erst, wenn die Einwilligung bestätigt ist.</p>
  <div class="tabellenrahmen"><table><tbody>
    <?php foreach ($checks as $c):
      $ew = $c['marketing'] ? ((string) $c['einwilligung'] === 'bestaetigt' ? ['gut', 'Einwilligung bestätigt'] : ['warnung', 'Einwilligung angefragt, noch nicht bestätigt']) : null; ?>
      <tr><td style="min-width:180px"><?php if ($c['firma_id']): ?><a href="<?= Fmt::h(url('akquise/' . (int) $c['firma_id'])) ?>" style="color:var(--cyan)"><?= Fmt::h((string) $c['firma']) ?></a><?php else: ?><?= Fmt::h((string) $c['firma']) ?><?php endif; ?>
            <div class="akq-klein"><?= Fmt::h((string) $c['host']) ?> · <?= Fmt::h(date('d.m.Y H:i', strtotime((string) $c['created_at']))) ?></div></td>
          <td><?= Fmt::h((string) $c['name']) ?> · <a href="mailto:<?= Fmt::h((string) $c['email']) ?>" style="text-decoration:underline"><?= Fmt::h((string) $c['email']) ?></a>
            <?= $c['telefon'] ? ' · ' . Fmt::h((string) $c['telefon']) : '' ?> · <?= Fmt::h(strtoupper((string) $c['sprache'])) ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:5px">
              <?php if ((int) $c['ausfuehrlich'] === 1): ?><span class="marke2 gut">Analyse gewünscht</span><?php else: ?><span class="marke2">nur Kurz-Check</span><?php endif; ?>
              <?php if ($ew): ?><span class="marke2 <?= $ew[0] ?>"><?= Fmt::h($ew[1]) ?></span><?php endif; ?>
              <span class="marke2"><?= (int) $c['schlecht'] ?> von 6 schlecht</span>
              <a class="marke2" href="<?= Fmt::h(AkquiseCheck::link((string) $c['token'])) ?>" target="_blank" rel="noopener">Ergebnis ansehen</a></div></td>
          <td style="width:100px;text-align:right"><form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="akq_check_erledigt"><input type="hidden" name="check" value="<?= (int) $c['id'] ?>">
            <button class="knopf" style="min-height:32px;padding:5px 10px;font-size:var(--fs-klein)">Erledigt</button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<?php if (!empty($plattform)): /* Plattform-Anfragen (30.09.2026, Kundenfinder Eingang 3): Der Betrieb hat selbst gefragt. */ require_once dirname(__DIR__) . '/src/AkquisePlattform.php'; ?>
<div class="block" id="plattform">
  <h2 style="font-size:15px;margin:0 0 8px">Plattform-Anfragen — Betriebe suchen selbst einen Webdesigner</h2>
  <p class="akq-klein" style="margin:0 0 8px">Aus dem Akquise-Postfach. Antworten darfst du, weil der Betrieb um Angebote gebeten hat — aber im Portal selbst (dort oft gegen Guthaben). Die Antwort unten ist fertig: kopieren, im Portal einfügen, senden, dann „Erledigt“.</p>
  <?php foreach ($plattform as $pa): ?>
    <details style="border-top:1px solid var(--linie,#333);padding:8px 0">
      <summary style="cursor:pointer"><b><?= Fmt::h((string) $pa['plattform']) ?></b> · <?= Fmt::h((string) $pa['betreff']) ?> <span class="akq-klein"><?= Fmt::h(date('d.m. H:i', strtotime((string) ($pa['eingang_am'] ?? 'now')))) ?></span></summary>
      <div class="akq-klein" style="white-space:pre-wrap;max-height:220px;overflow:auto;margin:8px 0"><?= Fmt::h(mb_substr((string) $pa['text'], 0, 1500)) ?></div>
      <label class="akq-klein" for="pa<?= (int) $pa['id'] ?>">Fertige Antwort (<?= $pa['sprache'] === 'de' ? 'Deutsch' : 'Italienisch' ?>)</label>
      <textarea id="pa<?= (int) $pa['id'] ?>" readonly rows="9" style="width:100%;font:13px/1.45 inherit"><?= Fmt::h(AkquisePlattform::antwort((string) $pa['sprache'])) ?></textarea>
      <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:6px"><?= Csrf::feld() ?>
        <input type="hidden" name="tat" value="akq_plattform_erledigt"><input type="hidden" name="anfrage" value="<?= (int) $pa['id'] ?>">
        <button class="knopf" style="min-height:32px;padding:5px 10px;font-size:var(--fs-klein)">Im Portal beantwortet — erledigt</button></form>
    </details>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($signale)): /* Signal-Wecker (26.09.2026): gute Anlässe, keine Aufträge -- das Gate gilt weiter. */ ?>
<div class="block" id="signale">
  <h2 style="font-size:15px;margin:0 0 8px">Signale — ein guter Anlass</h2>
  <p class="akq-klein" style="margin:0 0 8px">Websites, die gerade ausgefallen sind oder deren Zertifikat bald abläuft. Ob und wie du den Betrieb ansprechen darfst, zeigt die Firmenseite.</p>
  <table><tbody>
    <?php foreach ($signale as $sg): ?>
      <tr><td style="width:170px"><a href="<?= Fmt::h(url('akquise/' . (int) $sg['firma_id'])) ?>" style="color:var(--cyan)"><?= Fmt::h((string) $sg['name']) ?></a>
            <div class="akq-klein"><?= Fmt::h((string) ($sg['stadt'] ?? '')) ?></div></td>
          <td><?= Fmt::h((string) $sg['text']) ?><div class="akq-klein"><?= Fmt::h(date('d.m.Y', strtotime((string) $sg['created_at']))) ?><?= in_array((string) $sg['kontakt_status'], ['kontaktiert', 'geantwortet'], true) ? ' · schon kontaktiert' : '' ?></div></td>
          <td style="width:90px;text-align:right"><form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="akq_signal_erledigt"><input type="hidden" name="signal" value="<?= (int) $sg['id'] ?>">
            <button class="knopf" style="min-height:32px;padding:5px 10px;font-size:var(--fs-klein)">Erledigt</button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

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
        <?php /* Akquise-CRM (06.10.2026): Priorität und Kontaktwege */ ?>
        <div><label for="prio">Priorität</label><select id="prio" name="prio"><option value="">alle</option>
          <?php foreach (AkquisePrio::STUFEN as $k => [$z, $w]): ?><option value="<?= $k ?>"<?= $gewaehlt('prio', $k) ?>><?= $z . ' ' . Fmt::h($w) ?></option><?php endforeach; ?></select></div>
        <div><label for="prio_min">Priorität ab</label><input id="prio_min" name="prio_min" type="number" min="0" max="100" value="<?= Fmt::h($wert('prio_min')) ?>"></div>
        <div><label class="akq-haken" style="margin-top:22px"><input type="checkbox" name="mit_email" value="1"<?= !empty($filter['mit_email']) ? ' checked' : '' ?>> mit E-Mail</label></div>
        <div><label class="akq-haken" style="margin-top:22px"><input type="checkbox" name="mit_whatsapp" value="1"<?= !empty($filter['mit_whatsapp']) ? ' checked' : '' ?>> mit WhatsApp/Mobil</label></div>
        <div><label for="von">Gefunden ab</label><input id="von" name="von" type="date" value="<?= Fmt::h($wert('von')) ?>"></div>
        <div><label for="bis">bis</label><input id="bis" name="bis" type="date" value="<?= Fmt::h($wert('bis')) ?>"></div>
        <div><label for="sort">Reihenfolge</label><select id="sort" name="sort">
          <?php foreach (['score' => 'Beste Chance zuerst', 'prio' => 'Höchste Priorität zuerst', 'neu' => 'Neueste zuerst', 'geprueft' => 'Zuletzt geprüft', 'name' => 'Name'] as $k => $w): ?>
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

<?php require_once dirname(__DIR__) . '/src/PartnerAnrufliste.php'; $akqAl = PartnerAnrufliste::ueberblick(); if ($akqAl): ?>
<div class="block" id="anrufliste">
  <h2 style="font-size:15px;margin:0 0 8px">Beim Partner zum Anrufen</h2>
  <div class="tabellenrahmen"><table><thead><tr><th>Partner</th><th>offen</th><th>zugestimmt</th><th>kein Interesse</th><th>nicht erreichbar</th></tr></thead><tbody>
    <?php foreach ($akqAl as $al): ?><tr><td><a href="<?= Fmt::h(url('akquise') . '?partner=' . (int) $al['id']) ?>" title="Alle Betriebe dieses Partners zeigen"><?= Fmt::h((string) $al['name']) ?></a></td><td><?= (int) $al['offen'] ?></td><td><?= (int) $al['zugestimmt'] ?></td><td><?= (int) $al['kein_interesse'] ?></td><td><?= (int) $al['nicht_erreichbar'] ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <p class="akq-klein" style="margin-top:6px">„Nicht erreicht“ kommt nach 2–3 Tagen wieder auf die Liste, nach dem dritten Mal ist der Betrieb wieder frei.
    Kauft ein Betrieb, der beim Partner zugestimmt hat (auch erst Monate später), gehört er diesem Partner — mit mindestens <?= Fmt::h(Partner::satzWort(['art' => 'prozent', 'wert' => Partner::zahl('partner_anruf_bp')])) ?> Provision.</p>
</div>
<?php endif; ?>
<?php $akqSchnell = ['' => 'Alle', 'darf' => 'Haben zugestimmt', 'ohne_web' => 'Ohne Website', 'stark' => 'Starke Chancen', 'partner' => 'Beim Partner'];
  $akqSchnellAn = !empty($filter['darf']) ? 'darf' : (!empty($filter['ohne_web']) ? 'ohne_web' : (!empty($filter['stark']) ? 'stark' : (!empty($filter['partner']) ? 'partner' : ''))); ?>
<nav class="akq-schnell" aria-label="Schnellauswahl">
  <?php foreach ($akqSchnell as $k => $w): ?>
    <a href="<?= Fmt::h(url('akquise') . ($k !== '' ? '?' . $k . '=' . ($k === 'partner' ? 'alle' : '1') : '')) ?>" class="<?= $akqSchnellAn === $k && count(array_filter($filter, static fn($v) => $v !== '')) <= 1 ? 'an' : '' ?>"><?= Fmt::h($w) ?></a>
  <?php endforeach; ?>
  <a href="<?= Fmt::h(url('akquise') . '?kontakt=kontaktiert') ?>" class="<?= ($filter['kontakt'] ?? '') === 'kontaktiert' ? 'an' : '' ?>">Warten auf Antwort</a>
</nav>
<?php if (!empty($filter['partner']) && ctype_digit((string) $filter['partner'])):
  $akqPn = sicher(static fn() => (string) Db::wert('SELECT name FROM partner WHERE id = ?', [(int) $filter['partner']], ''), ''); ?>
  <p class="akq-klein" style="margin:0 0 8px">Gezeigt: nur Betriebe bei Partner <b><?= Fmt::h($akqPn) ?></b> · <a href="<?= Fmt::h(url('akquise') . '?partner=alle') ?>">alle Partner</a> · <a href="<?= Fmt::h(url('akquise')) ?>">alle Betriebe</a></p>
<?php endif; ?>
<?php /* Aussortieren (06.10.2026, Uwe): Betriebe ohne E-Mail und ohne WhatsApp raus. Neue kommen gar nicht erst hinein. */
  $akqWeg = sicher(static fn() => Akquise::aussortierbarZahl(), 0);
  if ($akqWeg > 0 && Rechte::darfTat('akq_aussortieren')): ?>
  <div class="hinweis" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <span><b><?= $akqWeg ?> Betrieb<?= $akqWeg === 1 ? '' : 'e' ?></b> ohne E-Mail und ohne WhatsApp — die lassen sich nicht anschreiben.</span>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin:0"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_aussortieren">
      <button class="knopf">Aussortieren</button></form>
  </div>
<?php endif; ?>
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
  <?php /* Anrufliste (29.09.2026, T1): anhaken und einem Partner zum Abtelefonieren geben */
    $akqPartner = sicher(static fn() => Db::all("SELECT id, name FROM partner WHERE status = 'aktiv' ORDER BY name"), []); ?>
  <form method="post" action="<?= Fmt::h(url('akquise')) ?>" id="uebergabe">
  <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_an_partner">
  <div class="tabellenrahmen"><table class="akq-tab">
    <thead><tr><th>Betrieb</th><th>Ort</th><th>Was fehlt</th><th>Ansprechen</th></tr></thead>
    <tbody>
    <?php foreach ($liste['zeilen'] as $z):
        $top = json_decode((string) ($z['top_probleme'] ?? '[]'), true) ?: [];
        $stand = AkquiseAnsprechen::stand($z);
        $stufe = (int) $z['gesperrt'] === 1 ? 'erledigt' : Akquise::stufe5((string) $z['kontakt_status']);
        $score = $z['score'] !== null ? (int) $z['score'] : null;
        $ohneWeb = trim((string) ($z['url'] ?? '')) === ''; ?>
      <tr>
        <td><label class="akq-wahl"><?php if ($akqPartner && in_array($stand['farbe'], ['grau', 'blau'], true) &&trim((string) ($z['telefon'] ?? '')) !== ''): ?><input type="checkbox" name="firmen[]" value="<?= (int) $z['id'] ?>" aria-label="<?= Fmt::h((string) $z['name']) ?> auswählen"><?php endif; ?>
          <a href="<?= Fmt::h(url('akquise/' . (int) $z['id'])) ?>"><b><?= Fmt::h((string) $z['name']) ?></b></a></label>
          <div class="akq-klein"><?= Fmt::h(Akquise::branchenName($z['branche'])) ?><?= !$ohneWeb ? ' · ' . Fmt::h((string) ($z['domain'] ?? '')) : '' ?></div>
          <?php /* E-Mail und WhatsApp in jeder Zeile (06.10.2026, Uwe). Die Adresse steht immer da; anklickbar (→ Versand von Hand
                   auf der Firmenseite) nur mit dokumentiertem Versandgrund. Daneben der Kommunikationsstatus. */
            $akqMail = trim((string) ($z['email'] ?? '')); $akqWa = Akquise::whatsappNummer($z);
            $akqMs = AkquiseMail::status($z); [$akqMsZ, , $akqMsW] = AkquiseMail::STATUS[$akqMs]; ?>
          <div class="akq-kontakt">
            <?php if ($akqMail !== ''): ?><span title="<?= Fmt::h($akqMsW) ?>">✉ <?php if ($akqMs !== AkquiseMail::NICHT): ?><a href="<?= Fmt::h(url('akquise/' . (int) $z['id']) . '#ansprechen') ?>"><?= Fmt::h($akqMail) ?></a><?php else: ?><?= Fmt::h($akqMail) ?><?php endif; ?> <span aria-label="<?= Fmt::h($akqMsW) ?>"><?= $akqMsZ ?></span></span><?php endif; ?>
            <?php if ($akqWa !== null): ?><span title="<?= trim((string) ($z['whatsapp'] ?? '')) !== '' ? 'WhatsApp-Nummer' : 'Handynummer — WhatsApp wahrscheinlich' ?>">WhatsApp <?= Fmt::h($akqWa) ?></span><?php endif; ?>
            <?php if ($akqMail === '' && $akqWa === null): ?><span class="akq-klein"><?= in_array((string) $z['audit_status'], ['offen', 'laeuft'], true) ? 'E-Mail wird auf der Website gesucht …' : 'Keine E-Mail, kein WhatsApp' ?></span><?php endif; ?>
          </div>
          <?php if ($score !== null): ?><span class="akq-chance s-<?= Fmt::h((string) $z['score_stufe']) ?>" style="margin-top:5px" title="Wie gut passt Vecom hier? 0–100"><b><?= $score ?></b> <?= Fmt::h(Akquise::chanceWort($score)) ?></span><?php endif; ?>
          <?php $zSt = AkquisePrio::STUFEN[(string) ($z['prio_stufe'] ?? '')] ?? null; if ($zSt): ?><span class="crm-prio p-<?= Fmt::h((string) $z['prio_stufe']) ?>" style="margin-top:5px" title="Akquise-Priorität: <?= Fmt::h($zSt[1] . (AkquisePrio::warum($z) !== '' ? ' — ' . AkquisePrio::warum($z) : '')) ?>"><?= $zSt[0] ?> <?= (int) $z['prio_score'] ?></span><?php endif; ?>
          <?php if ($stufe !== 'neu'): ?><span class="akq-stufe st-<?= $stufe ?>"><?= Fmt::h(Akquise::STUFEN5[$stufe][0]) ?></span><?php endif; ?>
          <?php if (!empty($z['beim_partner'])): $bp = $z['beim_partner']; [$bpWort, $bpArt] = Akquise::partnerKennung($bp); ?>
            <a class="akq-partner <?= $bpArt ?>" href="<?= Fmt::h(url('akquise') . '?partner=' . (int) $bp['partner_id']) ?>" title="Reserviert bis <?= Fmt::h(date('d.m.Y', strtotime((string) $bp['bis']))) ?>"><?= (string) ($bp['herkunft'] ?? '') === 'vecom' ? '☎' : '★' ?> <b><?= Fmt::h((string) $bp['partner_name']) ?></b> <?= Fmt::h($bpWort) ?></a>
          <?php endif; ?></td>
        <td><?= Fmt::h((string) ($z['stadt'] ?? '—')) ?><div class="akq-klein"><?= Fmt::h((string) $z['land']) ?></div></td>
        <td><?php if ($ohneWeb): ?><b>Keine Website</b>
            <?php elseif ($top): ?><?= Fmt::h((string) $top[0]) ?><?= count($top) > 1 ? ' <span class="akq-klein">+' . (count($top) - 1) . ' weitere</span>' : '' ?>
            <?php else: ?><span class="akq-klein"><?= Fmt::h((string) $z['audit_status'] === 'fertig' ? 'Nichts Schlimmes gefunden' : 'Website wird noch geprüft') ?></span><?php endif; ?></td>
        <td><div class="akq-los-zeile">
          <span class="akq-ampel klein <?= Fmt::h($stand['farbe']) ?>"><i></i><span><?= Fmt::h($stand['wort']) ?></span></span>
          <?php if ($stand['farbe'] !== 'rot'): ?>
            <a class="knopf akq-los" href="<?= Fmt::h(url('akquise/' . (int) $z['id']) . '#ansprechen') ?>">Ansprechen</a>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php if ($akqPartner): ?>
  <div class="akq-uebergabe" hidden>
    <b><span data-zahl>0</span> ausgewählt</b>
    <select name="partner" required aria-label="Partner"><option value="">Partner wählen …</option>
      <?php foreach ($akqPartner as $ap): ?><option value="<?= (int) $ap['id'] ?>"><?= Fmt::h((string) $ap['name']) ?></option><?php endforeach; ?></select>
    <label class="akq-haken" style="margin:0"><input type="checkbox" name="vermerk" value="Geprüft: italienische Nummern nicht im Registro Pubblico delle Opposizioni; bei deutschen Betrieben konkreter Anlass für Interesse (keine oder schwache Website)." required>
      Heute geprüft: IT-Nummern nicht im Registro delle Opposizioni, bei DE ein Anlass (keine/schwache Website)
      <small style="display:block;color:var(--leise)">Das Datum wird je Nummer gespeichert. Nach 15 Tagen verschwindet ein italienischer Betrieb aus der Anrufliste, bis du neu prüfst.</small></label>
    <button class="knopf haupt">Zum Abtelefonieren übergeben</button>
  </div>
  <?php endif; ?>
  </form>
  <p class="akq-klein" style="margin-top:8px">Häkchen vor dem Namen: nur bei Betrieben mit Telefonnummer, die noch nicht zugestimmt haben. Der Partner sieht sie in seiner Anrufliste.</p>
  <script>
  (function () {
    var f = document.getElementById('uebergabe'); if (!f) return;
    var leiste = f.querySelector('.akq-uebergabe'); if (!leiste) return;
    function neu() { var n = f.querySelectorAll('input[name="firmen[]"]:checked').length; leiste.hidden = n === 0; leiste.querySelector('[data-zahl]').textContent = n; }
    f.addEventListener('change', neu); neu();
  })();
  </script>
    <?php if ($liste['seiten'] > 1): ?>
      <div style="display:flex;gap:8px;justify-content:center;margin-top:14px;flex-wrap:wrap">
        <?php for ($s = 1; $s <= $liste['seiten']; $s++): ?>
          <a class="knopf<?= $s === $liste['seite'] ? ' an' : '' ?>" href="<?= Fmt::h($seitenUrl($s)) ?>"<?= $s === $liste['seite'] ? ' aria-current="page"' : '' ?>><?= $s ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
