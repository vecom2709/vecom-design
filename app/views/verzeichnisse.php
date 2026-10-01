<?php
/**
 * Marketing · Verzeichnisse (Telegram Growth Engine T5 — 01.10.2026, Uwe: „ja“).
 *
 * Wo Vecom Design eingetragen werden kann, was dort erlaubt ist, wie weit
 * jeder Eintrag ist und was er bringt (Verzeichnisse). Eingereicht wird von
 * Hand; jede Stelle bekommt beim Vorbereiten eigene Links, damit man sieht,
 * welche wirkt.
 *
 * Erwartet: $liste (Verzeichnisse::liste), $offen (ID des aufgeklappten Eintrags oder 0).
 */
$datum = static fn(?string $t): string => $t ? date('d.m.Y', strtotime($t)) : '';
$zahl = static fn(int $x): string => number_format($x, 0, ',', '.');
$mal = static fn(int $x, string $eins, string $mehr): string => number_format($x, 0, ',', '.') . ' ' . ($x === 1 ? $eins : $mehr);
$jeArt = array_fill_keys(array_keys(Verzeichnisse::ARTEN), []);
foreach ($liste as $e) { $jeArt[(string) $e['art']][] = $e; }
$anz = static fn(string $st): int => count(array_filter($liste, static fn($e) => $e['status'] === $st));
$faelligAnz = static fn(string $st): int => count(array_filter($liste, static fn($e) => $e['status'] === $st && $e['faellig']));
$summe = ['besuche' => 0, 'leads' => 0, 'kunden' => 0, 'beitritte' => 0, 'fenster' => 0];
foreach ($liste as $e) { foreach ($summe as $k => $_) { $summe[$k] += (int) ($e['zahl'][$k] ?? 0); } }
$firma = Verzeichnisse::firmendaten();
$kanal = Telegram::kanal();
$kanalName = preg_match('~t\.me/([A-Za-z0-9_]{4,64})~', (string) ($kanal['link'] ?? ''), $kanalM) ? $kanalM[1] : '';
$stMarke = static function (array $e): string {
    $klasse = match ((string) $e['status']) { 'online' => ' gut', 'abgelehnt' => ' schlecht', default => $e['faellig'] ? ' warnung' : '' };
    $wort = Verzeichnisse::STATUS[(string) $e['status']] ?? (string) $e['status'];
    if ($e['faellig']) { $wort .= $e['status'] === 'eingereicht' ? ' · nachsehen' : ' · seit über einer Woche'; }
    return '<span class="marke2' . $klasse . '">' . Fmt::h($wort) . '</span>';
};
$feldNr = 0;
$kopierFeld = static function (string $titel, string $text, int $zeilen = 3) use (&$feldNr): void { $feldNr++; ?>
    <div class="vz-text">
      <label class="leise" for="vzt<?= $feldNr ?>"><?= Fmt::h($titel) ?></label>
      <textarea id="vzt<?= $feldNr ?>" readonly rows="<?= $zeilen ?>"><?= Fmt::h($text) ?></textarea>
      <button class="knopf" type="button" data-kopieren="vzt<?= $feldNr ?>">Kopieren</button>
    </div>
<?php };
$linkFeld = static function (string $titel, string $link) use (&$feldNr): void { $feldNr++; ?>
    <label class="leise" for="vzl<?= $feldNr ?>" style="margin:6px 0 0"><?= Fmt::h($titel) ?></label>
    <div class="mk-link"><input id="vzl<?= $feldNr ?>" readonly value="<?= Fmt::h($link) ?>"><button class="knopf" type="button" data-kopieren="vzl<?= $feldNr ?>">Kopieren</button></div>
<?php };
require __DIR__ . '/mk_stil.php';
?>
<style>
  .vz-eintrag{border-top:1px solid var(--linie);padding:10px 0}
  .vz-eintrag:first-of-type{border-top:0}
  .vz-eintrag > summary{display:flex;flex-wrap:wrap;gap:6px 12px;align-items:center;cursor:pointer;list-style:none}
  .vz-eintrag > summary::-webkit-details-marker{display:none}
  .vz-eintrag > summary::before{content:"▸";color:var(--leise);font-size:12px;width:10px}
  .vz-eintrag[open] > summary::before{content:"▾"}
  .vz-name{font-weight:600}
  .vz-auf{font-size:12.5px;color:var(--gold,#d9b46a);text-decoration:none;white-space:nowrap}
  .vz-auf:hover{text-decoration:underline}
  .vz-tun{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
  .vz-klein{font-size:12.5px;color:var(--leise)}
  .vz-wirkung{font-size:12.5px;color:var(--dim);margin-left:auto}
  .vz-eintrag.ruhig > summary .vz-name{color:var(--dim);font-weight:500}
  .vz-innen{display:grid;gap:10px;padding:10px 0 4px 22px;max-width:900px}
  .vz-innen p{margin:0;line-height:1.55}
  .vz-text{display:grid;gap:6px}
  .vz-text textarea{width:100%;font-size:13.5px;line-height:1.5;resize:vertical}
  .vz-text .knopf{justify-self:start}
  .vz-stand{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
  .vz-stand input[type=url]{flex:1 1 260px;min-width:0}
  .vz-vorher{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}
  .vz-knopf{display:flex;flex-wrap:wrap;gap:12px 22px;align-items:flex-start}
  .vz-knopf .knopf{cursor:grab;font-size:15px;padding:10px 18px}
  .vz-knopf ol{margin:0;padding-left:20px;flex:1 1 380px;line-height:1.55;font-size:13.5px}
  .vz-knopf li{margin:0 0 4px}
  .vz-daten{display:grid;grid-template-columns:auto 1fr;gap:4px 14px;font-size:13.5px;margin:0}
  .vz-daten dt{color:var(--leise)} .vz-daten dd{margin:0}
  @media(max-width:700px){.vz-innen{padding-left:0}.vz-wirkung{margin-left:0;width:100%}}
</style>

<div class="mk-kopf">
  <div>
    <h1>Verzeichnisse <span class="leise" style="font-weight:400;font-size:15px">&amp; Kooperationen</span></h1>
    <div class="weg">Wo Vecom Design stehen kann · was dort erlaubt ist · wie weit jeder Eintrag ist · was er bringt</div>
  </div>
  <nav class="mk-chips" aria-label="Weiter">
    <a href="<?= Fmt::h(url('telegram')) ?>">Telegram</a>
    <a href="<?= Fmt::h(url('kampagnen')) ?>">Kampagnen</a>
  </nav>
</div>

<?php
/* EINFACH ZUERST (01.10.2026, Uwe: Ja zu V1, V2, V3, S2, S3): oben das Land,
   der nächste Eintrag, die Stellen, die Kunden bringen, und „Partner
   gewinnen“. Alles Bisherige steht unverändert darunter, eingeklappt. */
require_once dirname(__DIR__) . '/src/MkLand.php';
require_once dirname(__DIR__) . '/src/MkKooperation.php';
require_once dirname(__DIR__) . '/src/Firma.php';
$vzLand = MkLand::wahl();
$vzT = Verzeichnisse::fuerLand($liste, $vzLand);
$vzN = Verzeichnisse::naechster($liste, $vzLand);
$vzStandKnopf = static function (array $e, string $status, string $wort, string $klasse = 'knopf klein'): string {
    return '<form method="post" action="' . Fmt::h(url('verzeichnisse')) . '" style="margin:0"><input type="hidden" name="_csrf" value="' . Fmt::h(Csrf::token()) . '">'
        . '<input type="hidden" name="tat" value="verzeichnis_stand"><input type="hidden" name="id" value="' . (int) $e['id'] . '"><input type="hidden" name="zurueck" value="verzeichnisse">'
        . '<button class="' . $klasse . '" name="status" value="' . Fmt::h($status) . '">' . Fmt::h($wort) . '</button></form>';
};
$vzBew = MkKooperation::bewerbungen();
$vzGLink = (string) Firma::get('firma_google_bewertung');
$vzGoogle = null; foreach ($liste as $e) { if ($e['schluessel'] === 'google') { $vzGoogle = $e; } } ?>
<style>
  .vz-einfach{display:grid;gap:14px;margin-bottom:16px}
  .vz-naechst{border-color:rgba(241,211,139,.55)}
  .vz-zeile{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;padding:9px 0;border-top:1px solid var(--linie)}
  .vz-zeile:first-of-type{border-top:0}
  .vz-zeile .vz-name{flex:1 1 220px;min-width:0}
  .vz-zeile .vz-tun{display:flex;gap:6px;flex-wrap:wrap}
  .vz-gruppen{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px}
  .vz-gruppe{border:1px solid var(--linie2);border-radius:12px;padding:12px 14px;display:grid;gap:8px;align-content:start}
  .vz-gruppe h3{margin:0;font-size:15.5px}
  .vz-gruppe p{margin:0;line-height:1.55;font-size:13.5px}
  .vz-gruppe textarea{width:100%;font-size:13px;line-height:1.5;resize:vertical;min-height:120px}
  .vz-alles > summary{cursor:pointer;font-weight:600;padding:4px 0}
</style>
<div class="vz-einfach">
  <?php if ($vzLand === 'DE'): ?>
    <div class="hinweis" role="note" style="margin:0">Für Deutschland bringen Karten und Branchenbücher wenig: Sie verlangen eine Adresse in Deutschland, und Google zeigt ein Profil nur im Umkreis von etwa zwei Stunden Fahrt. Wirksam sind dort die Agentur-Verzeichnisse unten, <b>Partner</b> (Steuerberater, Fotografen, Druckereien …) und Beiträge mit Anzeigen.</div>
  <?php endif; ?>

  <section class="block vz-naechst" aria-labelledby="vz-n-titel">
    <h2 id="vz-n-titel"><?= MkLand::marke($vzLand) ?> Nächster Eintrag</h2>
    <?php if ($vzN === null): ?>
      <p class="mk-fein" style="margin:0">Für <?= Fmt::h(MkLand::name($vzLand)) ?> ist alles eingetragen oder entschieden. Was eingereicht ist, steht unten — sobald es online ist, auf „Online“ setzen.</p>
    <?php else: ?>
      <p style="margin:0 0 4px;font-size:17px"><b><?= Fmt::h((string) $vzN['name']) ?></b> <span class="mk-fein">· <?= Fmt::h(Verzeichnisse::ARTEN[(string) $vzN['art']][0] ?? '') ?> · <?= Fmt::h((string) $vzN['kosten']) ?><?= (string) $vzN['konto'] !== '' ? ' · ' . Fmt::h((string) $vzN['konto']) : '' ?></span></p>
      <?php if ((string) $vzN['regeln'] !== ''): ?><p style="margin:0 0 10px;max-width:80ch;line-height:1.55"><?= Fmt::h((string) $vzN['regeln']) ?></p><?php endif; ?>
      <ol class="mk-fein" style="margin:0 0 10px;padding-left:20px;line-height:1.6">
        <li>„Zur Eintragsseite“ öffnen.</li>
        <li>Dort den Ausfüll-Knopf aus der Lesezeichenleiste drücken — oder die Texte unten unter „Alle Stellen“ kopieren.</li>
        <li>Konto, Captcha und Absenden bleiben bei dir. Danach hier „Eingereicht“.</li>
      </ol>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <a class="knopf haupt" href="<?= Fmt::h((string) $vzN['url']) ?>" target="_blank" rel="noopener noreferrer">Zur Eintragsseite ↗</a>
        <?= $vzStandKnopf($vzN, 'eingereicht', 'Eingereicht', 'knopf') ?>
        <?= $vzStandKnopf($vzN, 'spaeter', 'Später') ?>
        <?= $vzStandKnopf($vzN, 'nein', 'Nicht eintragen') ?>
        <a class="knopf klein" href="<?= Fmt::h(url('verzeichnisse') . '?e=' . (int) $vzN['id']) ?>#v-<?= (int) $vzN['id'] ?>">Texte zum Kopieren</a>
      </div>
    <?php endif; ?>
  </section>

  <section class="block" aria-labelledby="vz-w-titel">
    <h2 id="vz-w-titel">Wo eintragen — <?= Fmt::h(MkLand::name($vzLand)) ?> <span class="mehr">zuerst die Stellen, die Kunden bringen</span></h2>
    <?php foreach ($vzT['wirkt'] as $e): ?>
      <div class="vz-zeile">
        <span class="vz-name"><b><?= Fmt::h((string) $e['name']) ?></b> <span class="mk-fein">· <?= Fmt::h((string) $e['kosten']) ?></span></span>
        <?= $stMarke($e) ?>
        <span class="vz-tun">
          <?php if ($e['status'] === 'offen'): ?><a class="knopf klein" href="<?= Fmt::h((string) $e['url']) ?>" target="_blank" rel="noopener noreferrer">Eintragen ↗</a><?= $vzStandKnopf($e, 'eingereicht', 'Eingereicht') ?>
          <?php elseif ($e['status'] === 'eingereicht'): ?><?= $vzStandKnopf($e, 'online', 'Ist online') ?>
          <?php elseif ($e['status'] === 'online' && !empty($e['eintrag_url'])): ?><a class="knopf klein" href="<?= Fmt::h((string) $e['eintrag_url']) ?>" target="_blank" rel="noopener noreferrer">Ansehen ↗</a><?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
    <?php if (!$vzT['wirkt']): ?><p class="mk-fein" style="margin:0">Hier gibt es für <?= Fmt::h(MkLand::name($vzLand)) ?> keine Stelle, die sich lohnt.</p><?php endif; ?>
    <p class="mk-fein" style="margin:10px 0 0"><?= count($vzT['weitere']) ?> weitere Stellen (Telegram-Kataloge, kleinere Verzeichnisse) stehen unten unter „Alle Stellen und Werkzeuge“.</p>
  </section>

  <section class="block" id="partner-gewinnen" aria-labelledby="vz-p-titel">
    <h2 id="vz-p-titel">Partner gewinnen — <?= Fmt::h(MkLand::name($vzLand)) ?> <span class="mehr">Menschen, die täglich mit Kleinbetrieben zu tun haben · Provision über das Partnerprogramm</span></h2>
    <p class="mk-fein" style="margin:0 0 10px;max-width:90ch;line-height:1.55"><?= Fmt::h(MkKooperation::ANSPRACHE) ?></p>
    <div class="vz-gruppen">
      <?php foreach (MkKooperation::GRUPPEN as $gk => [$gDe, $gIt, $gWarum]): $gId = 'vzg-' . $gk; ?>
        <div class="vz-gruppe">
          <h3><?= Fmt::h($vzLand === 'IT' ? $gIt . ' (' . $gDe . ')' : $gDe) ?><?= ($vzBew[$gk] ?? 0) > 0 ? ' <span class="marke2 gut">' . (int) $vzBew[$gk] . ' angemeldet</span>' : '' ?></h3>
          <p><?= Fmt::h($gWarum) ?></p>
          <label class="leise" for="<?= $gId ?>-l" style="margin:0">Partnerseite für diese Gruppe</label>
          <div class="mk-link"><input id="<?= $gId ?>-l" readonly value="<?= Fmt::h(MkKooperation::link($gk, $vzLand)) ?>"><button class="knopf" type="button" data-kopieren="<?= $gId ?>-l">Kopieren</button></div>
          <div class="vz-tun"><a class="knopf klein" href="<?= Fmt::h(MkKooperation::suche($gk, $vzLand)) ?>" target="_blank" rel="noopener noreferrer">In der Gegend finden ↗</a><a class="knopf klein" href="<?= Fmt::h(MkKooperation::link($gk, $vzLand)) ?>" target="_blank" rel="noopener">Partnerseite ansehen ↗</a></div>
          <details><summary class="mk-fein" style="cursor:pointer">Gesprächsleitfaden<?= $vzLand === 'IT' ? ' (italienisch)' : '' ?></summary>
            <textarea id="<?= $gId ?>-t" readonly rows="7"><?= Fmt::h(MkKooperation::leitfaden($gk, $vzLand)) ?></textarea>
            <button class="knopf klein" type="button" data-kopieren="<?= $gId ?>-t">Kopieren</button></details>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="block" id="google-profil" aria-labelledby="vz-g-titel">
    <h2 id="vz-g-titel">Eigenes Google-Profil und Bewertungen <span class="mehr">zeigt Kunden zugleich, was das Google-Profil-Paket (89 €) bringt</span></h2>
    <ol style="margin:0;padding-left:20px;line-height:1.7">
      <li>Profil: <?= $vzGoogle ? $stMarke($vzGoogle) : '—' ?> <?= $vzGoogle && $vzGoogle['status'] === 'offen' ? '<a href="' . Fmt::h((string) $vzGoogle['url']) . '" target="_blank" rel="noopener noreferrer">einrichten ↗</a> (als Firma mit Servicegebiet, Adresse ausblenden)' : '' ?></li>
      <li>Bewertungslink: <?= $vzGLink !== '' ? '<span class="marke2 gut">hinterlegt</span>' : '<span class="marke2 warnung">fehlt</span> — nach dem Einrichten unter <a href="' . Fmt::h(url('einstellungen') . '?b=firma') . '">Einstellungen › Firma</a> eintragen' ?></li>
      <li>Um Bewertungen bitten: persönlich bei der Übergabe oder mit dem QR-Code zum Ausdrucken (Karte für den Termin, Aufsteller). In Deutschland nicht per E-Mail ohne Einwilligung — das gilt dort als Werbung.</li>
      <li>Beiträge fürs Profil schreibt das Content-Studio mit (Format „Google-Unternehmensprofil“) — veröffentlicht werden sie von Hand im Profil.</li>
    </ol>
    <?php if ($vzGLink !== ''): ?><p style="margin:10px 0 0"><a class="knopf" href="<?= Fmt::h(url('bewertung-karte')) ?>" target="_blank" rel="noopener">QR-Karte „Bewerten Sie uns“ drucken ↗</a></p><?php endif; ?>
  </section>
</div>

<details class="block vz-alles"<?= $offen > 0 ? ' open' : '' ?>>
<summary>Alle Stellen und Werkzeuge — Zahlen, Ausfüll-Knopf, Firmendaten, Texte, eigene Stellen</summary>
<div class="karten mk-karten">
  <div class="karte"><h3>Offen</h3><div class="wert"><?= $zahl($anz('offen')) ?></div>
    <div class="neben"><?= $faelligAnz('offen') > 0 ? $zahl($faelligAnz('offen')) . ' davon seit über einer Woche' : 'noch nicht eingetragen' ?></div></div>
  <div class="karte"><h3>Eingereicht</h3><div class="wert"><?= $zahl($anz('eingereicht')) ?></div>
    <div class="neben"><?= $faelligAnz('eingereicht') > 0 ? $zahl($faelligAnz('eingereicht')) . ' seit über einer Woche — nachsehen, ob sie online sind' : 'wartet auf die Prüfung dort' ?></div></div>
  <div class="karte"><h3>Online</h3><div class="wert"><?= $zahl($anz('online')) ?></div>
    <div class="neben">eingetragen und sichtbar</div></div>
  <div class="karte"><h3>Gebracht</h3><div class="wert"><?= $zahl($summe['leads']) ?> <span class="leise" style="font-size:14px;font-weight:400"><?= $summe['leads'] === 1 ? 'Lead' : 'Leads' ?></span></div>
    <div class="neben"><?= Fmt::h($mal($summe['besuche'], 'Besuch', 'Besuche') . ' · ' . $mal($summe['fenster'], 'Fenster', 'Fenster') . ' · ' . $mal($summe['beitritte'], 'Beitritt', 'Beitritte') . ' · ' . $mal($summe['kunden'], 'Kunde', 'Kunden')) ?> — über die eigenen Links</div></div>
</div>

<div class="block" id="knopf">
  <h2>Ausfüll-Knopf <span class="mehr">einmal einrichten, dann auf jeder Eintragsseite ein Klick statt Kopieren</span></h2>
  <div class="vz-knopf">
    <a class="knopf haupt" id="vz-lesezeichen" href="<?= Fmt::h(Verzeichnisse::lesezeichen()) ?>" title="In die Lesezeichenleiste ziehen">★ Vecom eintragen</a>
    <ol>
      <li>Diesen Knopf mit der Maus in die Lesezeichenleiste von Chrome ziehen — einmalig. Leiste nicht zu sehen: Strg + Umschalt + B.</li>
      <li>Bei einer Stelle unten „Zur Eintragsseite ↗“ öffnen und dort auf das Lesezeichen klicken. Ein kleines Fenster der Verwaltung legt die eigenen Links an und füllt das Formular aus — die Felder sind danach gold umrandet.</li>
      <li>Prüfen, ein Captcha lösen, auf der Seite absenden. Im kleinen Fenster dann „Eingereicht“.</li>
    </ol>
  </div>
  <p class="mk-fein" id="vz-lesezeichen-hinweis" hidden>Nicht hier klicken — in die Lesezeichenleiste ziehen und auf der Eintragsseite drücken.</p>
  <p class="mk-fein" style="margin:6px 0 0">Der Knopf schreibt nur, was hier steht — Firmendaten, Texte in der Sprache der Stelle, die eigenen Links — und schickt nichts ab. Konto, Captcha und Bedingungen bleiben bei dir. Bei ungewöhnlichen Formularen kann ein Feld leer bleiben; die Texte unten bleiben zum Kopieren da.</p>
  <script>
  document.getElementById('vz-lesezeichen').addEventListener('click', function (e) { e.preventDefault(); document.getElementById('vz-lesezeichen-hinweis').hidden = false; });
  </script>
</div>

<div class="block">
  <h2>Bevor du einträgst</h2>
  <div class="vz-vorher">
    <div class="vz-innen" style="padding:0">
      <p><b>Kanalbeschreibung auch auf Italienisch.</b> Telegram-Kataloge übernehmen die Beschreibung des Kanals — steht sie nur auf Deutsch, liest sie in Italien niemand. In Telegram: Kanal → Bearbeiten → Beschreibung.</p>
      <?php $kopierFeld('Vorschlag für die Kanalbeschreibung (IT und DE, ' . mb_strlen(Texte::VERZEICHNIS['kanal']) . ' von 255 Zeichen)', Texte::VERZEICHNIS['kanal'], 5); ?>
    </div>
    <div class="vz-innen" style="padding:0">
      <p><b>Überall dieselben Firmendaten.</b> Karten und Verzeichnisse vergleichen Name, Adresse und Telefon untereinander — gleiche Angaben überall sind für Google ein Zeichen von Verlässlichkeit.</p>
      <?php if ($firma): ?>
        <dl class="vz-daten"><?php foreach ($firma as $k => $w): ?><dt><?= Fmt::h($k) ?></dt><dd><?= Fmt::h((string) $w) ?></dd><?php endforeach; ?></dl>
      <?php endif; ?>
      <p class="mk-fein">Aus <a href="<?= Fmt::h(url('einstellungen') . '?b=firma') ?>">Einstellungen → Firma</a><?= isset($firma['Straße'], $firma['Telefon']) ? '' : ' — dort fehlt noch etwas, das Verzeichnisse verlangen' ?>.</p>
    </div>
  </div>
</div>

<?php foreach (Verzeichnisse::ARTEN as $art => [$titel, $satz]): $l = $jeArt[$art]; ?>
<section class="block" id="art-<?= Fmt::h($art) ?>">
  <h2><?= Fmt::h($titel) ?> <span class="mehr"><?= Fmt::h($satz) ?></span></h2>
  <?php if ($art === 'kanal'): ?>
    <p class="vz-tun" style="margin:0 0 8px">
      <a class="knopf klein" href="https://www.google.com/search?q=<?= rawurlencode('site:t.me (Agrigento OR Sicilia OR Sciacca OR Licata OR Favara)') ?>" target="_blank" rel="noopener noreferrer">Kanäle und Gruppen um Agrigent suchen ↗</a>
      <?php if ($kanalName !== ''): ?><a class="knopf klein" href="https://web.telegram.org/k/#@<?= Fmt::h($kanalName) ?>" target="_blank" rel="noopener noreferrer">Eigenen Kanal in Telegram Web ↗</a><?php endif; ?>
    </p>
    <p class="mk-fein" style="margin:0 0 10px">Gefunden? Beschreibung und angeheftete Regeln lesen — nur wenn Kooperationen oder Geschäftsbeiträge ausdrücklich erlaubt sind, unten aufnehmen und den Admin persönlich anschreiben.</p>
  <?php endif; ?>
  <?php if (!$l): ?>
    <p class="leise" style="margin:0"><?= $art === 'kanal'
      ? 'Noch keine. Am 01.10.2026 nachgesehen: Keiner der lokalen Kanäle und Gruppen um Agrigent erlaubt in Beschreibung oder Regeln ausdrücklich Geschäftsbeiträge oder Kooperationen. Findest du einen, der es tut, nimm ihn unten auf.'
      : 'Noch keine.' ?></p>
  <?php endif; ?>
  <?php foreach ($l as $e): $id = (int) $e['id']; $z = $e['zahl']; $ruhig = in_array($e['status'], ['spaeter', 'nein', 'abgelehnt'], true); $links = Verzeichnisse::links($e); ?>
  <details class="vz-eintrag<?= $ruhig ? ' ruhig' : '' ?>" id="v-<?= $id ?>"<?= $offen === $id ? ' open' : '' ?>>
    <summary>
      <span class="vz-name"><?= Fmt::h((string) $e['name']) ?></span>
      <?= $stMarke($e) ?>
      <span class="vz-klein"><?= Fmt::h(implode(' · ', array_filter([(string) $e['kosten'], (string) $e['konto'],
        $e['captcha'] === null ? '' : ((int) $e['captcha'] === 1 ? 'mit Captcha' : 'ohne Captcha'), Verzeichnisse::SPRACHEN[(string) $e['sprache']] ?? '']))) ?></span>
      <?php if (!(int) $e['kostenlos']): ?><span class="marke2 warnung">kostet</span><?php endif; ?>
      <?php $vzTg = $art === 'kanal' && preg_match('~^https://t\.me/([A-Za-z0-9_]{4,64})/?$~', (string) $e['url'], $vzM) ? $vzM[1] : ''; ?>
      <a class="vz-auf" href="<?= Fmt::h($e['status'] === 'online' && !empty($e['eintrag_url']) ? (string) $e['eintrag_url'] : (string) $e['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $art === 'kanal' ? 'Kanal öffnen' : ($e['status'] === 'online' && !empty($e['eintrag_url']) ? 'Eintrag ansehen' : 'Öffnen') ?> ↗</a>
      <?php if ($vzTg !== ''): ?><a class="vz-auf" href="https://web.telegram.org/k/#@<?= Fmt::h($vzTg) ?>" target="_blank" rel="noopener noreferrer">Telegram Web ↗</a><?php endif; ?>
      <?php if (Verzeichnisse::ohneKonto($e) && in_array($e['status'], ['offen'], true)): ?><span class="marke2">ohne Konto und Captcha</span><?php endif; ?>
      <?php if ($z !== null): ?><span class="vz-wirkung"><?= Fmt::h($mal($z['besuche'], 'Besuch', 'Besuche') . ' · ' . $mal($z['fenster'], 'Fenster', 'Fenster') . ' · ' . $mal($z['beitritte'], 'Beitritt', 'Beitritte')) ?> · <b><?= Fmt::h($mal($z['leads'], 'Lead', 'Leads')) ?></b><?= $z['kunden'] > 0 ? ' · ' . Fmt::h($mal($z['kunden'], 'Kunde', 'Kunden')) : '' ?></span><?php endif; ?>
    </summary>
    <div class="vz-innen">
      <?php if ((string) $e['regeln'] !== ''): ?><p><?= Fmt::h((string) $e['regeln']) ?></p><?php endif; ?>
      <p class="mk-fein"><?= $e['geprueft_am'] ? 'Nachgesehen am ' . Fmt::h($datum((string) $e['geprueft_am'])) : '' ?>
        <?php if (!empty($e['regeln_url'])): ?> · <a href="<?= Fmt::h((string) $e['regeln_url']) ?>" target="_blank" rel="noopener noreferrer">Regeln dort ↗</a><?php endif; ?>
        · <a href="<?= Fmt::h((string) $e['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $art === 'kanal' ? 'Kanal öffnen' : 'Zur Eintragsseite' ?> ↗</a>
        <?php if (!empty($e['eintrag_url'])): ?> · <a href="<?= Fmt::h((string) $e['eintrag_url']) ?>" target="_blank" rel="noopener noreferrer">Eintrag ansehen ↗</a><?php endif; ?></p>
      <?php if ((string) $e['notiz'] !== ''): ?><p class="mk-fein"><?= Fmt::h((string) $e['notiz']) ?></p><?php endif; ?>
      <?php if (Verzeichnisse::ohneKonto($e) && $e['status'] === 'offen'): ?>
        <p class="mk-fein">Kein Konto und kein Captcha nötig — hier kann Claude für dich einreichen, wenn du im Chat Ja sagst.</p>
      <?php endif; ?>

      <?php if (!$ruhig && $e['status'] !== 'online' && empty($e['kampagne_id'])): ?>
        <form method="post" action="<?= Fmt::h(url('verzeichnisse')) ?>" class="vz-stand">
          <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="verzeichnis_vorbereiten"><input type="hidden" name="id" value="<?= $id ?>">
          <button class="knopf haupt">Eintrag vorbereiten</button>
          <span class="mk-fein">legt eigene Links an — was darüber kommt, zählt für diese Stelle — und zeigt die Texte zum Kopieren</span>
        </form>
      <?php elseif (!empty($e['kampagne_id'])): ?>
        <div>
          <?php if ($art === 'telegram'): ?>
            <?php if ($links['oeffentlich'] !== ''): ?><?php $linkFeld('Kanal-Adresse fürs Formular (Kataloge nehmen den öffentlichen Kanal)', $links['oeffentlich']); ?><?php endif; ?>
            <?php if ($links['fenster'] !== ''): ?><?php $linkFeld('Fenster-Link für die Beschreibung — zählt für diese Stelle, wenn der Katalog Links anklickbar macht', $links['fenster']); ?><?php endif; ?>
          <?php elseif ($art === 'kanal'): ?>
            <?php if ($links['einladung'] !== ''): ?>
              <?php $linkFeld('Kanal-Link dieser Kooperation — Beitritte darüber zählen hier', $links['einladung']); ?>
            <?php else: ?>
              <form method="post" action="<?= Fmt::h(url('verzeichnisse')) ?>" class="vz-stand" style="margin-top:4px">
                <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="verzeichnis_kanal_link"><input type="hidden" name="id" value="<?= $id ?>">
                <button class="knopf">Kanal-Link anlegen</button><span class="mk-fein">ein eigener Einladungslink des Kanals für diese Kooperation</span>
              </form>
            <?php endif; ?>
            <?php if ($links['fenster'] !== ''): ?><?php $linkFeld('Fenster-Link (Preis, Website-Check, Anfrage) — zählt ebenfalls hier', $links['fenster']); ?><?php endif; ?>
          <?php else: ?>
            <?php $linkFeld('Website-Adresse für den Eintrag — zählt Besuche, Preisrechner und Anfragen für diese Stelle', $links['website']); ?>
          <?php endif; ?>
        </div>
        <?php foreach (Verzeichnisse::texte($e, $links) as $t): ?>
          <?php $kopierFeld($t['titel'], $t['text'], $art === 'kanal' ? 7 : ($art === 'telegram' ? 3 : 4)); ?>
          <?php if ($t['hinweis'] !== ''): ?><p class="mk-fein"><?= Fmt::h($t['hinweis']) ?></p><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($art === 'kanal'): ?><p class="mk-fein">Die Anfrage schickst du selbst, an diesen einen Admin. Mehrere Admins auf einmal anzuschreiben gilt bei Telegram als Spam und kann den eigenen Kanal kosten.</p><?php endif; ?>
      <?php endif; ?>

      <form method="post" action="<?= Fmt::h(url('verzeichnisse')) ?>" class="vz-stand">
        <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="verzeichnis_stand"><input type="hidden" name="id" value="<?= $id ?>">
        <?php if (in_array($e['status'], ['eingereicht', 'online'], true)): ?>
          <input type="url" name="eintrag_url" placeholder="Adresse des Eintrags, wenn er online ist (freiwillig)" value="<?= Fmt::h((string) ($e['eintrag_url'] ?? '')) ?>" aria-label="Adresse des Eintrags">
        <?php endif; ?>
        <?php if ($e['status'] === 'offen'): ?>
          <button class="knopf" name="status" value="eingereicht">Eingereicht</button>
          <button class="knopf" name="status" value="spaeter">Später</button>
          <button class="knopf" name="status" value="nein">Nicht eintragen</button>
        <?php elseif ($e['status'] === 'eingereicht'): ?>
          <button class="knopf haupt" name="status" value="online">Ist online</button>
          <button class="knopf" name="status" value="abgelehnt">Abgelehnt</button>
          <button class="knopf" name="status" value="eingereicht" title="Datum bleibt, nur die Adresse wird gespeichert">Adresse speichern</button>
        <?php elseif ($e['status'] === 'online'): ?>
          <button class="knopf" name="status" value="online">Adresse speichern</button>
          <button class="knopf" name="status" value="offen">Wieder offen</button>
        <?php else: ?>
          <button class="knopf" name="status" value="offen">Wieder öffnen</button>
        <?php endif; ?>
        <span class="mk-fein">Stand seit <?= Fmt::h($datum((string) ($e['status_am'] ?? $e['angelegt_am']))) ?></span>
      </form>
      <?php if ((string) ($e['schluessel'] ?? '') === '' && !in_array($e['status'], ['eingereicht', 'online'], true)): /* 01.10.2026: eigene Einträge wieder entfernen */ ?>
        <form method="post" action="<?= Fmt::h(url('verzeichnisse')) ?>" class="vz-stand" data-frage="„<?= Fmt::h((string) $e['name']) ?>“ aus der Liste nehmen? Nur selbst aufgenommene Einträge, über die noch nichts kam." data-ja="Ja, entfernen">
          <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="verzeichnis_weg"><input type="hidden" name="id" value="<?= $id ?>">
          <button class="knopf">Eintrag entfernen</button>
        </form>
      <?php endif; ?>
    </div>
  </details>
  <?php endforeach; ?>
</section>
<?php endforeach; ?>

<div class="block" id="neu">
  <h2>Eigene Stelle aufnehmen <span class="mehr">ein Verzeichnis, eine Karte, ein Kanal oder eine Gruppe</span></h2>
  <form class="mk-formular" method="post" action="<?= Fmt::h(url('verzeichnisse')) ?>">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="verzeichnis_anlegen">
    <div class="feld"><label for="vz_art">Art</label>
      <select id="vz_art" name="art"><?php foreach (Verzeichnisse::ARTEN as $a => [$t]): ?><option value="<?= Fmt::h($a) ?>"><?= Fmt::h($t) ?></option><?php endforeach; ?></select></div>
    <div class="feld"><label for="vz_name">Name</label><input id="vz_name" name="name" maxlength="120" required></div>
    <div class="feld"><label for="vz_url">Adresse (Eintragsseite oder t.me/…)</label><input id="vz_url" name="url" type="url" maxlength="255" required placeholder="https://"></div>
    <div class="feld"><label for="vz_sprache">Sprache dort</label>
      <select id="vz_sprache" name="sprache"><?php foreach (Verzeichnisse::SPRACHEN as $s => $w): ?><option value="<?= Fmt::h($s) ?>"><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
    <div class="feld"><label for="vz_konto">Konto nötig?</label><input id="vz_konto" name="konto" maxlength="80" placeholder="z. B. kein Konto, E-Mail, Google-Konto"></div>
    <div class="feld"><label for="vz_captcha">Captcha</label>
      <select id="vz_captcha" name="captcha"><option value="">weiß nicht</option><option value="1">ja</option><option value="0">nein</option></select></div>
    <div class="feld"><label class="mk-haken"><input type="checkbox" name="kostenlos" value="1" checked> kostenloser Eintrag</label></div>
    <div class="feld"><label for="vz_kosten">Falls nicht kostenlos: was kostet es?</label><input id="vz_kosten" name="kosten" maxlength="160"></div>
    <div class="feld breit"><label for="vz_regeln">Was dort erlaubt ist — bei Kanälen der Wortlaut aus Beschreibung oder Regeln</label><textarea id="vz_regeln" name="regeln" rows="3" maxlength="700"></textarea></div>
    <div class="feld breit"><label class="mk-haken"><input type="checkbox" name="erlaubt" value="1"> Bei Kanälen und Gruppen: Beschreibung oder Regeln erlauben Kooperationen oder Geschäftsbeiträge ausdrücklich — ich habe es selbst gelesen.</label></div>
    <div class="breit"><button class="knopf haupt">Aufnehmen</button></div>
  </form>
</div>

<details class="block mk-quelle">
  <summary>Wie hier gearbeitet wird — und was sich nicht messen lässt</summary>
  <p style="margin:10px 0 0">Vorgeschlagen werden nur Stellen mit kostenlosem Grundeintrag; bezahlte Zusätze stehen als „nicht buchen“ dabei. Eingereicht wird von Hand: Konten, Passwörter, Captchas und das Annehmen von Bedingungen bleiben bei dir. Wo kein Konto und kein Captcha nötig ist, kann Claude einreichen — nur nach deinem Ja je Eintrag. Die Regeln jeder Stelle wurden am angegebenen Tag auf deren eigenen Seiten nachgesehen; sie können sich ändern.</p>
  <p style="margin:8px 0 0">Kanäle und Gruppen: nur solche, deren Beschreibung oder Regeln Kooperationen ausdrücklich erlauben, und die Anfrage geht an einen Admin nach dem anderen. Unaufgeforderte Werbung und Einladungslinks an Fremde wertet Telegram als Spam (telegram.org/faq_spam) — mit Sperren bis zur Dauer. Eine abgesprochene gegenseitige Erwähnung, die jeder Admin selbst postet, ist erlaubt.</p>
  <p style="margin:8px 0 0">Gemessen wird über die eigenen Links jedes Eintrags (eine gewöhnliche Kampagne, Code vz-…): Besuche, Preisrechner und Anfragen über den Website-Link, geöffnete Fenster über den Fenster-Link, Beitritte über den Kanal-Link. Wer den Kanal über seinen öffentlichen Namen findet — so verlinken die meisten Telegram-Kataloge —, kommt ohne Link an und lässt sich keinem Katalog zuordnen. Ab einer Woche ohne Bewegung erinnert die Verwaltung einmal je Woche per Zuruf.</p>
</details>
</details>
