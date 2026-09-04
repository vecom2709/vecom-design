<?php
/**
 * Ein Vorgang — alles zu einem Kunden auf einer Seite.
 *
 * Ersetzt den Weg über Anfrage → Bestellung → Projekt → Fragebogen. Die
 * alten Seiten bleiben bestehen und funktionieren weiter; diese hier ist
 * die Zusammenfassung, mit der man tatsächlich arbeitet.
 *
 * Jeder Knopf schickt an dieselbe Stelle wie vorher — es gibt hier keine
 * eigene Logik, nur eine andere Anordnung.
 */
$hier   = 'vorgaenge/' . $v['schluessel'];
$pid    = $v['projekt_id'];
$fb     = $v['fragebogen'];
$s      = $v['schritt'];
$tage   = Vorgang::ruhtSeitTagen($v);
?>

<div class="kopf">
  <div>
    <div class="weg"><a href="<?= Fmt::h(url('vorgaenge')) ?>">Vorgänge</a></div>
    <h1><?= Fmt::h($v['kunde']) ?><?= $v['firma'] !== '' ? ' <span style="color:var(--leise);font-weight:400">· ' . Fmt::h($v['firma']) . '</span>' : '' ?>
      <?php /* Die Sprache gehoert sichtbar hierher: In ihr kommen alle Mails
               an, in ihr sieht er seine Seite, in ihr steht sein Fragebogen.
               Sie erst zu bemerken, wenn etwas Falsches rausging, ist zu
               spaet -- geaendert wird sie in der Kundenakte. */ ?>
      <?php /* "Vermutet" ist hier keine Kleinigkeit: Steht es da, hat der
               Kunde die Sprache nie selbst gewaehlt — sie kam aus der
               Seitenfassung, auf der er zufaellig stand. Wer das vor dem
               ersten Schreiben sieht, fragt einmal nach; wer es nicht sieht,
               schickt einem Deutschen drei italienische Mails. */ ?>
      <?php $spOk = (bool) sicher(static fn() => $v['kunde_id'] === null || Db::wert(
          'SELECT sprache_bestaetigt FROM customers WHERE id = ?', [(int) $v['kunde_id']], null) !== null, true); ?>
      <span class="marke2 <?= $spOk ? '' : 'warnung' ?>"
            title="<?= $spOk ? 'Der Kunde hat diese Sprache selbst gewählt.'
                             : 'Geraten aus der Sprachfassung der Website — der Kunde hat sie nie bestätigt. Ändern in der Kundenakte.' ?>"><?=
        Fmt::h(['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'][(string) $v['sprache']] ?? (string) $v['sprache'])
        ?><?= $spOk ? '' : ' · vermutet' ?></span>
    </h1>
  </div>
  <div class="rechts">
    <?php if ($v['kunde_id']): ?>
      <a class="knopf" href="<?= Fmt::h(url('kunden/' . (int) $v['kunde_id'])) ?>">Kundenakte</a><?php endif; ?>
    <?php if ($v['bestell_id']): ?>
      <a class="knopf" href="<?= Fmt::h(url('bestellungen/' . (int) $v['bestell_id'])) ?>">Bestellung</a><?php endif; ?>
    <?php if ($pid): ?>
      <a class="knopf" href="<?= Fmt::h(url('projekte/' . (int) $pid)) ?>">Projekt</a><?php endif; ?>
  </div>
</div>

<?php /* ---------- Wo steht der Vorgang ---------- */ ?>
<div class="stufen">
  <?php foreach (Vorgang::STUFEN as $schl => $wort): ?>
    <?php $nr = (int) array_search($schl, array_keys(Vorgang::STUFEN), true); ?>
    <span class="<?= $nr === $v['stufe_nr'] ? 'jetzt' : ($nr < $v['stufe_nr'] ? 'da' : '') ?>"><?= Fmt::h($wort) ?></span>
  <?php endforeach; ?>
</div>

<?php /* ---------- Der nächste Handgriff ---------- */ ?>
<div class="dran <?= $v['dran'] === Vorgang::DU ? '' : 'wartet' ?>">
  <h2>
    <?php if ($v['dran'] === Vorgang::DU): ?>Du bist dran
    <?php elseif ($v['dran'] === Vorgang::KUNDE): ?>Der Kunde ist dran
    <?php else: ?>Nichts offen<?php endif; ?>
    <span class="mehr"><?= $tage === 0 ? 'heute bewegt' : ($tage === 1 ? 'seit gestern still' : "seit $tage Tagen still") ?></span>
  </h2>
  <p><?= Fmt::h($v['warum']) ?></p>

  <?php if ($s !== null && $s['tat'] === 'anfrage_bestellung' && $v['anfrage_id'] && $pakete): ?>
    <?php /* Aus der Anfrage wird eine Bestellung. Der einzige Schritt,
             der eine Entscheidung von dir braucht: welches Paket.

             Das "&& $pakete" ist kein Schoenheitsfehler-Schutz: Ohne Pakete
             stand hier eine leere Auswahlliste mit einem Pflichtfeld davor —
             ein Knopf, den zu druecken unmoeglich war. Gibt es keins, faellt
             der Schritt in den gewoehnlichen Zweig darunter und fuehrt
             dorthin, wo es wirklich weitergeht. */ ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" class="tun">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="anfrage_bestellung">
      <input type="hidden" name="id" value="<?= (int) $v['anfrage_id'] ?>">
      <select name="paket_id" required style="min-width:230px">
        <option value="">Paket wählen …</option>
        <?php foreach ($pakete as $p): ?>
          <option value="<?= (int) $p['id'] ?>"><?= Fmt::h($p['name']) ?>
            — <?= Fmt::h(Fmt::geld((int) $p['price_cents'], (string) $p['currency'])) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="knopf haupt">Bestellung anlegen</button>
      <span style="color:var(--leise);font-size:12.5px">Die Anzahlung entsteht dabei automatisch.</span>
    </form>

  <?php elseif ($s !== null && $s['tat'] !== null): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" class="tun">
      <?= Csrf::feld() ?>
      <input type="hidden" name="tat" value="<?= Fmt::h((string) $s['tat']) ?>">
      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
      <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
      <?php foreach ($s['felder'] as $feld => $wert): ?>
        <input type="hidden" name="<?= Fmt::h($feld) ?>" value="<?= Fmt::h((string) $wert) ?>">
      <?php endforeach; ?>
      <button class="knopf haupt"><?= Fmt::h($s['knopf']) ?></button>
      <?php if (($s['felder']['status'] ?? '') === 'vorschau'): ?>
        <span style="color:var(--leise);font-size:12.5px">Trag die Vorschau-Adresse vorher rechts unter „Website“ ein —
          sie steht dann in der E-Mail an den Kunden.</span>
      <?php endif; ?>
    </form>

  <?php elseif ($s !== null && ($s['ziel'] ?? null) !== null): ?>
    <?php /* Der Handgriff liegt woanders — auf der Bedarfsseite steht der
             fertige Preis, auf der Angebotsseite das Angebot. Hierher zu
             verlinken ist ehrlicher, als einen Knopf zu zeigen, der etwas
             anderes tut als das, was als Nächstes ansteht. */ ?>
    <div class="tun">
      <a class="knopf haupt" href="<?= Fmt::h(url((string) $s['ziel'])) ?>"><?= Fmt::h($s['knopf']) ?> &rsaquo;</a>
    </div>

  <?php elseif ($s !== null): ?>
    <div class="tun"><span style="color:var(--leise);font-size:13px"><?= Fmt::h($s['knopf']) ?>
      — dafür ist unten das Feld „Gespräch“.</span></div>
  <?php endif; ?>
</div>

<div class="zwei">
<div>

  <?php /* ---------- Mehrbedarf ----------
           Steht ganz oben, weil er als Einziger hier kostet, wenn man ihn
           uebersieht. Passt der Fragebogen zum Angebot, steht hier nichts. */ ?>
  <?php if (!empty($v['mehrbedarf'])): $mb = $v['mehrbedarf']; ?>
    <div class="block" data-tun="mehrbedarf" style="border-color:var(--cyan)">
      <h2>Mehrbedarf klären</h2>
      <p style="color:var(--dim);font-size:13.5px;margin:-4px 0 12px">
        Der Fragebogen sagt etwas anderes als Angebot
        <a href="<?= Fmt::h(url('angebote/' . (int) $mb['angebot_id'])) ?>"><?= Fmt::h($mb['nummer']) ?></a>.</p>
      <?php if ($mb['mehr']): ?>
        <table style="margin-bottom:12px"><tbody>
          <?php foreach ($mb['mehr'] as $z): ?>
            <tr><td><?= Fmt::h((string) $z['name']) ?><?php if (isset($z['war'])): ?>
                  <span style="color:var(--leise)">— <?= (int) $z['war'] ?> beauftragt, <?= (int) $z['wird'] ?> gewünscht</span>
                <?php endif; ?></td>
                <td class="num" style="width:28%"><b><?= Fmt::geld((int) $z['summe_cents']) ?><?= (int) $z['monatlich'] ? '/Mon.' : '' ?></b></td></tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
      <?php if ($mb['weniger']): ?>
        <p style="font-size:13.5px;color:var(--dim);margin:0 0 12px">
          Abgewählt, obwohl beauftragt: <b><?= Fmt::h(implode(', ', $mb['weniger'])) ?></b>.</p>
      <?php endif; ?>
      <?php if ($mb['auf_anfrage']): ?>
        <p style="font-size:13.5px;color:var(--dim);margin:0 0 12px">
          Ohne Preis, weil nur auf Anfrage: <b><?= Fmt::h(implode(', ', $mb['auf_anfrage'])) ?></b>.</p>
      <?php endif; ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ((int) $mb['summe_cents'] > 0): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="mehrbedarf_nachtrag">
            <input type="hidden" name="id" value="<?= (int) $mb['projekt_id'] ?>">
            <input type="hidden" name="signatur" value="<?= Fmt::h((string) $mb['signatur']) ?>">
            <button class="knopf haupt">Nachtrag über <?= Fmt::geld((int) $mb['summe_cents']) ?> anlegen</button></form>
        <?php endif; ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="mehrbedarf_erledigt">
          <input type="hidden" name="id" value="<?= (int) $mb['projekt_id'] ?>">
          <input type="hidden" name="signatur" value="<?= Fmt::h((string) $mb['signatur']) ?>">
          <button class="knopf">Ist besprochen</button></form>
      </div>
    </div>
  <?php endif; ?>

  <?php /* ---------- Angebot ----------
           Fehlte hier, ausgerechnet. Wer wissen wollte, was vereinbart ist,
           musste die Seite verlassen. Die Zeilen stehen knapp da; geaendert
           wird weiter auf der Angebotsseite, wo auch die Knoepfe dafuer
           sind. */ ?>
  <?php if (!empty($v['angebot'])): $a = $v['angebot']; ?>
    <div class="block">
      <h2>Angebot<span class="mehr">
        <?= Fmt::h((string) $a['nummer']) ?><?= (int) $a['fassung'] > 1 ? ' · Fassung ' . (int) $a['fassung'] : '' ?>
        · <span class="marke2 <?= $a['status'] === 'angenommen' ? 'gut' : ($a['status'] === 'abgelehnt' ? 'schlecht' : '') ?>"><?= Fmt::h(ucfirst((string) $a['status'])) ?></span></span></h2>
      <table style="margin-bottom:12px"><tbody>
        <?php foreach ($v['angebot_zeilen'] as $z): ?>
          <tr><td><?= Fmt::h((string) $z['bezeichnung']) ?><?= (int) $z['menge'] > 1 ? ' × ' . (int) $z['menge'] : '' ?></td>
              <td class="num" style="width:28%"><?= Fmt::geld((int) $z['summe_cents']) ?><?= (int) $z['monatlich'] ? '/Mon.' : '' ?></td></tr>
        <?php endforeach; ?>
        <tr><td><b>Summe</b></td><td class="num"><b><?= Fmt::geld((int) $a['summe_cents'], (string) $a['currency']) ?></b>
          <?php if ((int) $a['monatlich_cents'] > 0): ?> + <?= Fmt::geld((int) $a['monatlich_cents']) ?>/Mon.<?php endif; ?></td></tr>
      </tbody></table>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <a class="knopf" href="<?= Fmt::h(url('angebote/' . (int) $a['id'])) ?>">Angebot öffnen</a>
        <?php if (count($v['angebote']) > 1): ?>
          <span style="color:var(--leise);font-size:12.5px"><?= count($v['angebote']) ?> Fassungen insgesamt</span>
        <?php endif; ?>
        <?php if ($a['gueltig_bis'] !== null && $a['status'] === 'gesendet'): ?>
          <span style="color:var(--leise);font-size:12.5px">gültig bis <?= Fmt::h(Fmt::datum($a['gueltig_bis'])) ?></span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php /* ---------- Bedarf ----------
           Was der Kunde im Rechner angekreuzt hat, auf Deutsch -- egal, in
           welcher Sprache er geklickt hat. Zugeklappt: Man braucht es einmal
           am Anfang und danach selten. */ ?>
  <?php if (!empty($v['bedarf']) && $v['bedarf_antworten']): ?>
    <div class="block">
      <details>
        <summary style="cursor:pointer;font-weight:650;font-size:15px">Was er im Rechner angekreuzt hat</summary>
        <table style="margin-top:12px"><tbody>
          <?php foreach (Baukasten::FRAGEN as $schluessel => $frage): ?>
            <?php
              $wert = $v['bedarf_antworten'][$schluessel] ?? null;
              if ($wert === null || $wert === '' || $wert === []) { continue; }
              $worte = [];
              foreach ((array) $wert as $w) {
                $o = $frage['optionen'][(string) $w] ?? null;
                $worte[] = $o ? Texte::h($o, 'de') : (string) $w;
              }
            ?>
            <tr><td style="width:42%"><?= Fmt::h(Texte::h($frage['frage'] ?? [], 'de')) ?></td>
                <td><?= Fmt::h(implode(' · ', $worte)) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table>
        <a class="knopf" style="margin-top:12px" href="<?= Fmt::h(url('bedarf/' . (int) $v['bedarf']['id'])) ?>">Bedarf öffnen</a>
      </details>
    </div>
  <?php endif; ?>

  <?php /* ---------- Gespräch ---------- */ ?>
  <div class="block">
    <h2>Gespräch<?php if ($v['ungelesen'] > 0): ?><span class="mehr"><?= (int) $v['ungelesen'] ?> ungelesen</span><?php endif; ?></h2>
    <?php if ($v['anfrage_text'] !== ''): ?>
      <div style="border:1px solid var(--linie);border-left:3px solid var(--cyan);border-radius:10px;
                  padding:11px 13px;margin-bottom:12px">
        <div style="font-size:12px;color:var(--leise);margin-bottom:5px">Aus dem Anfrageformular</div>
        <div style="white-space:pre-wrap;font-size:14px;line-height:1.55;color:var(--dim)"><?= Fmt::h($v['anfrage_text']) ?></div>
      </div>
    <?php endif; ?>

    <?php foreach ($v['nachrichten'] as $m): ?>
      <div style="padding:10px 12px;border:1px solid var(--linie);border-radius:10px;margin-bottom:8px;
                  <?= $m['sender'] === 'kunde' ? '' : 'background:var(--flaeche2)' ?>">
        <div style="font-size:12.5px;font-weight:650;display:flex;justify-content:space-between;gap:10px;margin-bottom:5px">
          <span><?= $m['sender'] === 'kunde' ? Fmt::h($v['kunde']) : 'du' ?></span>
          <span style="color:var(--leise);font-weight:400"><?= Fmt::h(Fmt::seit($m['created_at'])) ?></span></div>
        <?php if (!empty($m['betreff'])): ?>
          <div style="font-size:12.5px;color:var(--cyan);margin-bottom:5px"><?= Fmt::h((string) $m['betreff']) ?></div>
        <?php endif; ?>
        <div style="white-space:pre-wrap;font-size:14px;line-height:1.55;color:var(--dim)"><?= Fmt::h((string) $m['body']) ?></div>
      </div>
    <?php endforeach; ?>

    <?php if ($v['anonym']): ?>
      <div class="leer">Der Datensatz ist anonymisiert — es gibt keine Adresse mehr.</div>
    <?php else: ?>
      <?php
        $nfTat = $pid ? 'nachricht_senden' : 'kunde_nachricht';
        $nfId = (int) ($pid ?: $v['kunde_id']);
        $nfKennung = (string) ($kennung ?? ''); $nfVorlagen = (array) ($vorlagen ?? []);
        $nfVorname = explode(' ', (string) $v['kunde'])[0]; $nfZurueck = $hier;
        require __DIR__ . '/nachrichtfeld.php';
      ?>
    <?php endif; ?>
  </div>

  <?php /* ---------- Zahlungen ---------- */ ?>
  <?php if ($v['zahlungen']): ?>
  <div class="block">
    <h2>Zahlungen<span class="mehr"><?= $v['offen_cent'] > 0 ? Fmt::geld($v['offen_cent'], $v['waehrung']) . ' offen' : 'alles bezahlt' ?></span></h2>
    <?php foreach ($v['zahlungen'] as $z): ?>
      <div style="border-top:1px solid var(--linie);padding:12px 0">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
          <div style="min-width:150px"><strong><?= Fmt::h($z['bezeichnung'] ?: ucfirst((string) $z['art'])) ?></strong>
            <div style="color:var(--leise);font-size:12px"><?= Fmt::h($z['provider'] === 'offen' ? 'noch kein Anbieter' : (string) $z['provider']) ?></div></div>
          <div style="font-variant-numeric:tabular-nums;font-size:16px;font-weight:600"><?= Fmt::geld((int) $z['amount_cents'], $z['currency']) ?></div>
          <span class="marke2 <?= Status::ton($z['status']) ?>"><?= Fmt::h(Status::label(Status::ZAHLUNG, $z['status'])) ?></span>
          <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap">
            <?php if ($z['status'] === 'bezahlt'): ?>
              <?php $beleg = sicher(static fn() => Db::one('SELECT id, invoice_no FROM invoices WHERE payment_id = ?', [(int) $z['id']]), null); ?>
              <?php if ($beleg): ?>
                <a class="knopf" href="<?= Fmt::h(url('rechnungen/' . (int) $beleg['id'])) ?>"><?= Fmt::h((string) $beleg['invoice_no']) ?></a>
              <?php endif; ?>
            <?php else: ?>
              <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
                <?= Csrf::feld() ?><input type="hidden" name="tat" value="zahlungslink">
                <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
                <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
                <button class="knopf"><?= $z['link_url'] ? 'Neuer Link' : 'Zahlungslink' ?></button></form>
              <?php if ($z['link_url']): ?>
                <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
                  <?= Csrf::feld() ?><input type="hidden" name="tat" value="zahlungslink_senden">
                  <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
                  <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
                  <button class="knopf">Link senden</button></form>
              <?php endif; ?>
              <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
                <?= Csrf::feld() ?><input type="hidden" name="tat" value="zahlung_bestaetigen">
                <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
                <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
                <button class="knopf">Von Hand buchen</button></form>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($z['link_url'] && $z['status'] !== 'bezahlt'): ?>
          <input readonly value="<?= Fmt::h((string) $z['link_url']) ?>" onclick="this.select()"
                 style="margin-top:9px;width:100%;font-size:12px;font-family:ui-monospace,monospace">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php /* ---------- Fragebogen ---------- */ ?>
  <?php if ($fb): ?>
  <?php
    $fbDaten  = $fb['data'] ? (json_decode((string) $fb['data'], true) ?: []) : [];
    $fbFertig = $fb['status'] === 'abgeschlossen';
  ?>
  <div class="block">
    <h2>Fragebogen<span class="mehr"><span class="marke2 <?= $fbFertig ? 'gut' : '' ?>">
      <?= $fbFertig ? 'Abgeschlossen' : 'Offen' ?></span></span></h2>
    <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 10px">
      Eingeladen: <?= Fmt::h($fb['eingeladen_am'] ? Fmt::datum($fb['eingeladen_am']) : 'noch nicht') ?>
      <?php if ($fb['erinnert_am']): ?> · erinnert: <?= Fmt::h(Fmt::datum($fb['erinnert_am'])) ?><?php endif; ?>
      <?php if ($fbFertig): ?> · zurück: <?= Fmt::h(Fmt::datum($fb['submitted_at'])) ?><?php endif; ?></p>

    <?php if ($fbDaten): ?>
      <?php foreach (Texte::FRAGEBOGEN as $inhalt): ?>
        <?php $hat = array_filter($inhalt['felder'],
              static fn($_, $n) => trim((string) ($fbDaten[$n] ?? '')) !== '', ARRAY_FILTER_USE_BOTH); ?>
        <?php if ($hat): ?>
          <h3 style="font-size:12px;color:var(--leise);margin:16px 0 4px;text-transform:uppercase;letter-spacing:.06em"><?= Fmt::h(Texte::h($inhalt, 'de')) ?></h3>
          <table><tbody>
          <?php foreach ($hat as $name => $feld): ?>
            <tr><td style="width:38%"><?= Fmt::h(Texte::h($feld, 'de')) ?></td>
                <td style="white-space:pre-wrap"><?= Fmt::h(($feld['art'] ?? '') === 'wahl'
                      ? Umfang::worte((string) $fbDaten[$name], 'de')
                      : (string) $fbDaten[$name]) ?></td></tr>
          <?php endforeach; ?>
          </tbody></table>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php elseif (!$fbFertig): ?>
      <div class="leer">Der Kunde hat noch nichts eingetragen.</div>
    <?php endif; ?>

    <?php if (!$fbFertig && $pid): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="fragebogen_einladen">
        <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
        <input type="hidden" name="id" value="<?= (int) $pid ?>">
        <button class="knopf"><?= $fb['eingeladen_am'] ? 'Noch einmal verschicken' : 'Fragebogen verschicken' ?></button></form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php /* ---------- Dateien ---------- */ ?>
  <div class="block">
    <h2>Dateien<span class="mehr"><?= count($v['dateien']) ?></span></h2>
    <?php if (!$v['dateien']): ?><div class="leer">Noch nichts.</div><?php else: ?>
      <?php foreach ($v['dateien'] as $d): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 0;border-top:1px solid var(--linie)">
          <span><?= Fmt::h((string) $d['orig_name']) ?><br><small style="color:var(--leise)">
            <?= Fmt::h(Fmt::bytes((int) $d['size_bytes'])) ?> ·
            <?= $d['uploaded_by'] === 'kunde' ? 'vom Kunden' : 'von dir' ?> ·
            <?= Fmt::h(Fmt::datum($d['created_at'])) ?></small></span>
          <a class="knopf" href="<?= Fmt::h(url('dateien/' . (int) $d['id'])) ?>">Herunterladen</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($v['kunde_id']): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" enctype="multipart/form-data"
            style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_datei">
        <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
        <input type="hidden" name="id" value="<?= (int) $v['kunde_id'] ?>">
        <input type="file" name="datei" required style="max-width:240px">
        <button class="knopf">Hochladen</button>
      </form>
    <?php endif; ?>
  </div>

</div><div>

  <?php /* ---------- Auf einen Blick ---------- */ ?>
  <div class="block"><h2>Auf einen Blick</h2><table><tbody>
    <tr><td>E-Mail</td><td><?= Fmt::h($v['email']) ?></td></tr>
    <tr><td>Sprache</td><td><?= Fmt::h(['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'][$v['sprache']] ?? 'Italiano') ?></td></tr>
    <?php if ($v['bestellnr'] !== ''): ?>
      <tr><td>Bestellung</td><td><?= Fmt::h($v['bestellnr']) ?></td></tr>
      <tr><td>Paket</td><td><?= Fmt::h($v['paket']) ?></td></tr>
      <tr><td>Preis</td><td><?= Fmt::geld($v['preis'], $v['waehrung']) ?></td></tr>
    <?php elseif ($v['paket'] !== ''): ?>
      <tr><td>Gewünscht</td><td><?= Fmt::h($v['paket']) ?></td></tr>
    <?php endif; ?>
    <?php if ($v['projekt'] && $v['projekt']['deadline']): ?>
      <tr><td>Deadline</td><td><?= Fmt::h(Fmt::datum($v['projekt']['deadline'])) ?></td></tr>
    <?php endif; ?>
    <tr><td>Begonnen</td><td><?= Fmt::h(Fmt::datum($v['begonnen'])) ?></td></tr>
  </tbody></table></div>

  <?php /* ---------- Seine Seite ---------- */ ?>
  <?php if ($v['link_kunde']): ?>
  <div class="block"><h2>Seine Seite</h2>
    <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 10px">Eine Adresse, vom ersten Kontakt
      bis lange nach dem Onlinegang. Kein Konto, kein Passwort — wer den Link hat, kommt hinein.
      Also nur an ihn.</p>
    <div class="feld">
      <input readonly onclick="this.select()" value="<?= Fmt::h((string) $v['link_kunde']) ?>" style="font-size:12px"></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <a class="knopf" href="<?= Fmt::h((string) $v['link_kunde']) ?>" target="_blank" rel="noopener">Ansehen</a>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
            data-frage="Der alte Link gilt danach nicht mehr. Der Kunde braucht dann den neuen. Fortfahren?" data-ja="Ja, neuen Link">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="kundenlink_neu">
        <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
        <input type="hidden" name="id" value="<?= (int) ($v['kunde_id'] ?? 0) ?>">
        <button class="knopf">Neuen Link erzeugen</button>
      </form>
    </div>
    <?php if ($v['link_anfrage'] || $v['link_projekt']): ?>
      <details style="margin-top:12px">
        <summary style="cursor:pointer;color:var(--leise);font-size:12.5px">Ältere Links (leiten weiter)</summary>
        <?php foreach (array_filter(['Anfrage' => $v['link_anfrage'], 'Projekt' => $v['link_projekt']]) as $was => $adr): ?>
          <div class="feld" style="margin-top:8px"><label><?= Fmt::h($was) ?></label>
            <input readonly onclick="this.select()" value="<?= Fmt::h((string) $adr) ?>" style="font-size:12px"></div>
        <?php endforeach; ?>
      </details>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php /* ---------- Vorschau ---------- */ ?>
  <?php if ($pid && !empty($v['vorschau']['spalte'])): ?>
  <?php $vs = $v['vorschau']; ?>
  <div class="block"><h2>Vorschau
    <span class="mehr">
      <?php if ($vs['frei_am']): ?>
        <span class="marke2 gut">freigeschaltet</span>
      <?php elseif ($vs['url'] !== ''): ?>
        <span class="marke2 warnung">nur für dich</span>
      <?php else: ?>
        <span class="marke2">keine Adresse</span>
      <?php endif; ?>
    </span></h2>
    <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
      Eintragen und Freischalten sind zweierlei. Der Kunde sieht den Entwurf erst nach dem
      Freischalten — vorher steht bei ihm ein grauer Kasten mit dem Hinweis, dass er hier erscheint.</p>

    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="vorschau_speichern">
      <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
      <input type="hidden" name="id" value="<?= (int) $pid ?>">
      <div class="feld"><label>Adresse des Entwurfs</label>
        <input name="preview_url" placeholder="https://vorschau.vecom-design.it/…"
               value="<?= Fmt::h($vs['url']) ?>"></div>
      <button class="knopf">Adresse speichern</button>
      <?php if ($vs['url'] !== ''): ?>
        <a class="knopf" href="<?= Fmt::h($vs['url']) ?>" target="_blank" rel="noopener"
           style="margin-left:8px">Selbst ansehen</a>
      <?php endif; ?>
    </form>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px;
                border-top:1px solid var(--linie);padding-top:12px">
      <?php if (!$vs['frei_am']): ?>
        <?php if ($vs['url'] === ''): ?>
          <button class="knopf" disabled title="Erst eine Adresse eintragen">Für den Kunden freischalten</button>
          <span style="color:var(--leise);font-size:12.5px">Erst die Adresse eintragen — sonst bekäme er
            eine E-Mail und fände nichts.</span>
        <?php else: ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="vorschau_frei">
            <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
            <input type="hidden" name="id" value="<?= (int) $pid ?>">
            <button class="knopf haupt">Für den Kunden freischalten</button></form>
          <span style="color:var(--leise);font-size:12.5px">Setzt den Stand auf „Vorschau“
            und schickt ihm die E-Mail.</span>
        <?php endif; ?>
      <?php else: ?>
        <span style="color:var(--leise);font-size:12.5px">Freigegeben am
          <?= Fmt::h(Fmt::zeit((string) $vs['frei_am'])) ?></span>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
              data-frage="Der Kunde sieht den Entwurf danach nicht mehr. Fortfahren?" data-ja="Ja, sperren">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="vorschau_sperren">
          <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
          <input type="hidden" name="id" value="<?= (int) $pid ?>">
          <button class="knopf">Wieder sperren</button></form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php /* ---------- Website ---------- */ ?>
  <?php if ($pid): ?>
  <div class="block"><h2>Website</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="website_speichern">
      <input type="hidden" name="zurueck" value="<?= Fmt::h($hier) ?>">
      <input type="hidden" name="id" value="<?= (int) $pid ?>">
      <div class="feld"><label>Adresse</label>
        <input name="url" placeholder="https://…" value="<?= Fmt::h((string) ($v['website']['url'] ?? '')) ?>"></div>
      <button class="knopf">Speichern</button>
      <?php if ($v['website']): ?>
        <span class="marke2 <?= Status::ton((string) $v['website']['status']) ?>" style="margin-left:8px">
          <?= Fmt::h(Status::label(Status::WEBSITE, (string) $v['website']['status'])) ?></span>
      <?php endif; ?>
    </form>
  </div>
  <?php endif; ?>

  <?php /* ---------- Belege ---------- */ ?>
  <?php if ($v['belege']): ?>
  <div class="block"><h2>Belege</h2>
    <?php foreach ($v['belege'] as $r): ?>
      <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid var(--linie)">
        <a href="<?= Fmt::h(url('rechnungen/' . (int) $r['id'])) ?>"><?= Fmt::h((string) $r['invoice_no']) ?></a>
        <span style="color:var(--leise);font-size:13px"><?= Fmt::geld((int) $r['total_cents'], (string) $r['currency']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php /* ---------- Verlauf ---------- */ ?>
  <div class="block"><h2>Verlauf</h2>
    <?php if (!$v['aktivitaeten']): ?><div class="leer">Noch nichts.</div><?php else: ?><ul class="verlauf">
      <?php foreach ($v['aktivitaeten'] as $a): ?>
        <li><span class="punkt"></span><span><?= Fmt::h((string) $a['title']) ?></span>
          <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li>
      <?php endforeach; ?></ul><?php endif; ?>
  </div>

</div></div>
