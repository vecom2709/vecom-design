<?php
/* ======================================================================
   DIESE AKTE IST AUCH EINE SCHUBLADE

   Seit dem Umbau gibt es einen Bildschirm je Kunde: die Vorgangsseite. Die
   Kundenakte ist dort eine Schublade — und bleibt gleichzeitig ihre eigene
   Seite, damit jeder alte Verweis weiter trifft.

   Eingebettet faellt weg, was auf der Vorgangsseite ohnehin schon steht:
   Bestellungen, Projekte, Zahlungen, die Nachricht, die Dateien, seine
   Seite und der Verlauf. Sonst stuende dasselbe zweimal auf einem Schirm,
   und man wuesste bei zwei Listen nie, welche die richtige ist.

   Uebrig bleibt, was es NUR hier gibt: Kontakt, Betreuung, Domain und
   Hosting, interne Notizen und das Entfernen.

   Als eigene Seite aufgerufen, aendert sich nichts — $eingebettet ist dann
   nicht gesetzt, und alle Bloecke stehen wie vorher.
   ====================================================================== */
$eing = !empty($eingebettet);
?>
<?php if (!$eing): ?>
<?php $knr = trim((string) ($k['kundennr'] ?? '')); ?>
<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('kunden')) ?>">Kunden</a><?php
  if ($knr !== ''): ?> · <span style="font-variant-numeric:tabular-nums"><?= Fmt::h($knr) ?></span><?php
  endif; ?></div>
<h1><?= Fmt::h($k['name']) ?></h1></div>
<div class="rechts"><?php if (!($anonym ?? false)): ?>
<a class="knopf" href="<?= Fmt::h(url('kunden/' . $k['id'] . '/bearbeiten')) ?>">Bearbeiten</a>
<a class="knopf haupt" href="<?= Fmt::h(url('bestellungen/neu')) ?>">Bestellung erfassen</a>
<?php else: ?><span class="marke2 schlecht">Anonymisiert</span><?php endif; ?></div></div>
<div class="zwei"><div>
  <div class="block"><h2>Bestellungen</h2><div class="tabellenrahmen"><table>
    <thead><tr><th>Nummer</th><th>Paket</th><th class="num">Preis</th><th>Status</th><th>Datum</th></tr></thead><tbody>
    <?php if (!$bestellungen): ?><tr><td colspan="5"><div class="leer">Noch keine Bestellung.</div></td></tr><?php endif; ?>
    <?php foreach ($bestellungen as $b): ?><tr>
      <td><a href="<?= Fmt::h(url('bestellungen/' . $b['id'])) ?>"><?= Fmt::h($b['order_no']) ?></a></td>
      <td><?= Fmt::h($b['package_name']) ?></td><td class="num"><?= Fmt::geld((int) $b['price_cents'], $b['currency']) ?></td>
      <td><span class="marke2 <?= Status::ton($b['status']) ?>"><?= Fmt::h(Status::label(Status::BESTELLUNG, $b['status'])) ?></span></td>
      <td><?= Fmt::h(Fmt::datum($b['ordered_at'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <div class="block"><h2>Projekte</h2><div class="tabellenrahmen"><table>
    <thead><tr><th>Projekt</th><th>Status</th><th class="num">Fortschritt</th><th>Deadline</th></tr></thead><tbody>
    <?php if (!$projekte): ?><tr><td colspan="4"><div class="leer">Noch kein Projekt.</div></td></tr><?php endif; ?>
    <?php foreach ($projekte as $p): ?><tr>
      <td><a href="<?= Fmt::h(url('projekte/' . $p['id'])) ?>"><?= Fmt::h($p['name']) ?></a></td>
      <td><span class="marke2 <?= Status::ton($p['status']) ?>"><?= Fmt::h(Status::label(Status::PROJEKT, $p['status'])) ?></span></td>
      <td class="num"><?= (int) $p['progress'] ?>%</td><td><?= Fmt::h(Fmt::datum($p['deadline'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <div class="block"><h2>Zahlungen</h2><div class="tabellenrahmen"><table>
    <thead><tr><th>Bestellung</th><th>Anbieter</th><th class="num">Betrag</th><th>Status</th><th>Bezahlt am</th></tr></thead><tbody>
    <?php if (!$zahlungen): ?><tr><td colspan="5"><div class="leer">Noch keine Zahlung.</div></td></tr><?php endif; ?>
    <?php foreach ($zahlungen as $z): ?><tr><td><?= Fmt::h($z['order_no']) ?></td><td><?= Fmt::h($z['provider']) ?></td>
      <td class="num"><?= Fmt::geld((int) $z['amount_cents'], $z['currency']) ?></td>
      <td><span class="marke2 <?= Status::ton($z['status']) ?>"><?= Fmt::h(Status::label(Status::ZAHLUNG, $z['status'])) ?></span></td>
      <td><?= Fmt::h(Fmt::zeit($z['paid_at'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <div class="block" id="schreiben"><h2>Nachricht an den Kunden</h2>
    <?php if ($anonym ?? false): ?>
      <div class="leer">Der Datensatz ist anonymisiert — es gibt keine Adresse mehr,
        an die etwas gehen könnte.</div>
    <?php else: ?>
    <p style="color:var(--leise);font-size:13px;margin:0 0 12px">Geht als E-Mail raus und steht danach hier
      — und auf der Seite des Kunden. Antwortet er dort, landet es ebenfalls hier.</p>
    <?php if ($nachrichten): ?>
      <div style="margin-bottom:14px">
      <?php foreach ($nachrichten as $m): ?>
        <div style="padding:10px 12px;border:1px solid var(--linie);border-radius:10px;margin-bottom:8px;
                    <?= $m['sender'] === 'kunde' ? '' : 'background:var(--flaeche2)' ?>">
          <div style="font-size:12.5px;font-weight:650;display:flex;justify-content:space-between;gap:10px;margin-bottom:5px">
            <span><?= $m['sender'] === 'kunde' ? Fmt::h($k['name']) : 'du' ?></span>
            <span style="color:var(--leise);font-weight:400"><?= Fmt::h(Fmt::seit($m['created_at'])) ?></span></div>
          <?php if (!empty($m['betreff'])): ?>
            <div style="font-size:12.5px;color:var(--cyan);margin-bottom:5px"><?= Fmt::h((string) $m['betreff']) ?></div>
          <?php endif; ?>
          <div style="white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px;line-height:1.55;color:var(--dim)"><?= Fmt::h((string) $m['body']) ?></div>
        </div>
      <?php endforeach; ?></div>
    <?php endif; ?>
    <?php
      $nfTat = 'kunde_nachricht'; $nfId = (int) $k['id'];
      $nfKennung = (string) ($kennung ?? ''); $nfVorlagen = (array) ($vorlagen ?? []);
      $nfVorname = explode(' ', (string) $k['name'])[0]; $nfZurueck = '';
      $nfVorwahl = (string) ($vorwahl ?? '');
      require __DIR__ . '/nachrichtfeld.php';
    ?>
    <?php endif; ?>
  </div>

  <div class="block"><h2>Dateien</h2>
    <?php /* Phase 6a: die alte Website als Vorlage sichern -- Texte, Bilder und
             PDFs als eine ZIP-Datei hier in der Ablage. Nur lesen, nichts aendern. */ ?>
    <?php
      require_once __DIR__ . '/../src/Altseite.php';
      $altS = sicher(static fn() => Altseite::fuerKunde((int) $k['id']), null);
      $altVorschlag = (string) sicher(static fn() => Db::wert(
          "SELECT COALESCE((SELECT seite_adresse FROM questionnaires WHERE customer_id = ? AND seite_adresse IS NOT NULL ORDER BY id DESC LIMIT 1),
                           (SELECT website FROM anfragen WHERE customer_id = ? AND website IS NOT NULL AND website <> '' ORDER BY id DESC LIMIT 1), '')",
          [(int) $k['id'], (int) $k['id']], ''), '');
    ?>
    <?php if ($altS && in_array((string) $altS['stand'], ['offen', 'laeuft'], true)): ?>
      <p style="color:var(--dim);font-size:13px;margin:4px 0 10px">Die alte Seite <b><?= Fmt::h((string) $altS['host']) ?></b> wird gerade gesichert
        — <?= count(json_decode((string) $altS['seiten'], true) ?: []) ?> Seiten gelesen. Die ZIP-Datei erscheint hier, sobald alles da ist.</p>
    <?php else: ?>
      <?php if ($altS && (string) $altS['stand'] === 'fehler'): ?>
        <div class="hinweis schlecht" style="margin:4px 0 8px">Die letzte Sicherung von <?= Fmt::h((string) $altS['host']) ?> ging nicht: <?= Fmt::h((string) $altS['fehler']) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:4px 0 10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="altseite_sichern">
        <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
        <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
        <input name="adresse" required value="<?= Fmt::h($altVorschlag) ?>" placeholder="alte-seite.it" style="max-width:240px" aria-label="Adresse der alten Seite">
        <button class="knopf">Alte Seite als Vorlage sichern</button>
        <span style="color:var(--leise);font-size:12.5px">Texte, Bilder, PDFs → eine ZIP-Datei. Die alte Seite bleibt, wie sie ist.</span>
      </form>
    <?php endif; ?>
    <?php /* Phase 6c: 1:1-Umzug der alten Seite -- angefragt von dir, zugestimmt
             vom Kunden, kopiert von dir. Hier: Test, Zugang auf Klick, Checkliste. */ ?>
    <?php require_once __DIR__ . '/../src/Seitenumzug.php';
      $sU = sicher(static fn() => Seitenumzug::fuerKunde((int) $k['id']), null); ?>
    <?php if (!$sU || in_array((string) $sU['stand'], ['fertig', 'abgebrochen'], true)): ?>
      <details style="margin:0 0 10px"><summary style="font-size:13px;color:var(--dim);cursor:pointer">Alte Website 1:1 zu uns umziehen …
        <?php if ($sU): ?><span style="color:var(--leise)">(zuletzt <?= Fmt::h((string) $sU['adresse']) ?>: <?= Fmt::h((string) $sU['stand']) ?>)</span><?php endif; ?></summary>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="seitenumzug_anfragen">
          <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
          <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
          <input name="adresse" required value="<?= Fmt::h($altVorschlag) ?>" placeholder="alte-seite.it" style="max-width:240px" aria-label="Adresse der alten Seite">
          <button class="knopf">Umzug anfragen</button>
          <span style="color:var(--leise);font-size:12.5px">Der Kunde stimmt auf seiner Seite zu und gibt dort den Zugang ein — nie per Mail.</span>
        </form></details>
    <?php else: ?>
      <?php $sT = json_decode((string) ($sU['test_json'] ?? ''), true); $sS = json_decode((string) ($sU['schritte'] ?? ''), true) ?: []; ?>
      <div style="margin:0 0 12px;padding:12px 14px;border:1px solid var(--linie);border-radius:10px">
        <div style="font-weight:650">Website-Umzug <?= Fmt::h((string) $sU['adresse']) ?>
          <span class="marke2 <?= (string) $sU['stand'] === 'zugang_da' ? 'warnung' : '' ?>" style="margin-left:6px"><?=
            Fmt::h((string) $sU['stand'] === 'angefragt' ? 'wartet auf Zustimmung und Zugang' : 'Zugang ist da') ?></span></div>
        <?php if ((string) $sU['stand'] === 'zugang_da'): ?>
          <p style="font-size:12.5px;margin:6px 0 0;color:var(--leise)">Zugang wird gelöscht am <?= Fmt::h(Fmt::datum((string) $sU['loeschen_am'])) ?>.
            Verbindung: <?= is_array($sT) ? '<b>' . Fmt::h((string) ($sT['text'] ?? '')) . '</b>' . (!empty($sT['wordpress']) ? ' · WordPress erkannt' : '') : 'wird im nächsten Cron geprüft' ?></p>
          <?php if (!empty($_SESSION['seitenumzug_zugang'][(int) $sU['id']])): $sZ = $_SESSION['seitenumzug_zugang'][(int) $sU['id']]; unset($_SESSION['seitenumzug_zugang'][(int) $sU['id']]); ?>
            <div class="hinweis gut" style="margin-top:8px;font-size:13px">
              <?php foreach (['ftp_host' => 'FTP-Server', 'ftp_user' => 'FTP-Benutzer', 'ftp_pass' => 'FTP-Passwort', 'db_host' => 'DB-Host',
                              'db_name' => 'DB-Name', 'db_user' => 'DB-Benutzer', 'db_pass' => 'DB-Passwort'] as $sF => $sN): if ((string) ($sZ[$sF] ?? '') === '') { continue; } ?>
                <?= Fmt::h($sN) ?>: <code style="user-select:all"><?= Fmt::h((string) $sZ[$sF]) ?></code><br>
              <?php endforeach; ?>
              <span style="color:var(--leise)">Nur jetzt sichtbar.</span></div>
          <?php endif; ?>
          <table class="schlicht" style="margin-top:8px"><tbody>
            <?php foreach (Seitenumzug::SCHRITTE as $sK => $sText): $sDa = !empty($sS[$sK]); ?>
              <tr><td style="width:1%"><?= $sDa ? '✓' : '·' ?></td><td><?= Fmt::h($sText) ?>
                <?php if ($sDa): ?><small style="color:var(--leise)"> · <?= Fmt::h((string) $sS[$sK]) ?></small><?php endif; ?></td>
                <td style="width:1%"><form method="post" action="<?= Fmt::h(url('')) ?>">
                  <?= Csrf::feld() ?><input type="hidden" name="tat" value="seitenumzug_schritt">
                  <input type="hidden" name="id" value="<?= (int) $sU['id'] ?>"><input type="hidden" name="schritt" value="<?= Fmt::h($sK) ?>">
                  <input type="hidden" name="wert" value="<?= $sDa ? '0' : '1' ?>"><input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
                  <button class="knopf" style="padding:4px 10px"><?= $sDa ? 'zurück' : 'erledigt' ?></button></form></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        <?php endif; ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
          <?php foreach ([['seitenumzug_zeigen', 'Zugang anzeigen', (string) $sU['stand'] === 'zugang_da'],
                          ['seitenumzug_testen', 'Verbindung neu prüfen', (string) $sU['stand'] === 'zugang_da'],
                          ['seitenumzug_fertig', 'Umzug abschließen', (string) $sU['stand'] === 'zugang_da' && count($sS) === count(Seitenumzug::SCHRITTE)],
                          ['seitenumzug_abbrechen', 'Abbrechen', true]] as [$sTat, $sWort, $sZeig]): if (!$sZeig) { continue; } ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="<?= Fmt::h($sTat) ?>"><input type="hidden" name="id" value="<?= (int) $sU['id'] ?>">
              <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>"><button class="knopf"><?= Fmt::h($sWort) ?></button></form>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php /* Phase 6b: E-Mail-Umzug -- laeuft von selbst, hier nur Stand und Knoepfe. */ ?>
    <?php require_once __DIR__ . '/../src/Mailumzug.php'; require_once __DIR__ . '/../src/Hosting.php';
      $mUs = sicher(static fn() => Mailumzug::fuerKunde((int) $k['id']), []);
      $mZiel = (string) sicher(static fn() => Db::wert("SELECT CONCAT('" . Hosting::POSTFACH . "@', domain) FROM hosting_auftraege WHERE customer_id = ? AND mail = 'vecom' ORDER BY id DESC LIMIT 1", [(int) $k['id']], ''), ''); ?>
    <?php foreach ($mUs as $mU): if ((string) $mU['stand'] === 'abgebrochen') { continue; } ?>
      <div style="margin:0 0 10px;padding:10px 14px;border:1px solid var(--linie);border-radius:10px;font-size:13px">
        <b>E-Mail-Umzug</b> <?= Fmt::h((string) $mU['adresse']) ?> → <?= Fmt::h((string) $mU['ziel_adresse']) ?>
        <span class="marke2 <?= ['fertig' => 'gut', 'fehler' => 'schlecht', 'laeuft' => 'warnung'][(string) $mU['stand']] ?? '' ?>" style="margin-left:6px"><?=
          Fmt::h(['angefragt' => 'wartet auf Zustimmung', 'zugang_da' => 'startet im nächsten Cron', 'laeuft' => 'läuft',
                  'fertig' => 'kopiert — Nachlauf bis ' . Fmt::datum((string) $mU['loeschen_am']), 'fehler' => 'hängt'][(string) $mU['stand']] ?? (string) $mU['stand']) ?></span>
        <div style="color:var(--leise);margin-top:4px"><?= (int) $mU['kopiert'] ?> von <?= (int) $mU['gesamt'] ?> Mails kopiert<?=
          (int) $mU['zu_gross'] > 0 ? ' · ' . (int) $mU['zu_gross'] . ' über 50 MB übersprungen' : '' ?><?=
          $mU['letzter_lauf'] ? ' · zuletzt ' . Fmt::h(Fmt::seit((string) $mU['letzter_lauf'])) : '' ?></div>
        <?php if ((string) $mU['stand'] === 'fehler'): ?><div style="color:var(--rot);margin-top:4px"><?= Fmt::h((string) $mU['fehler']) ?></div><?php endif; ?>
        <?php if ($mU['zugang_blob'] !== null): ?>
          <div style="display:flex;gap:8px;margin-top:8px">
            <?php foreach ([['mailumzug_jetzt', 'Jetzt abgleichen'], ['mailumzug_abbrechen', 'Anhalten']] as [$mT, $mW]): ?>
              <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="<?= $mT ?>">
                <input type="hidden" name="id" value="<?= (int) $mU['id'] ?>"><input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
                <button class="knopf" style="padding:4px 10px"><?= Fmt::h($mW) ?></button></form>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php /* Nur mit zugestimmtem Hosting samt Postfach bei uns (26.09.2026):
             vorher sah der Kunde eine Umzugs-Aufforderung fuer einen Vertrag,
             den er nie geschlossen hatte. */
          $mDarf = (bool) sicher(static fn() => Db::wert("SELECT COUNT(*) FROM hosting_auftraege WHERE customer_id = ?
                       AND status IN ('zugestimmt','in_arbeit','angelegt','aktiv') AND mail = 'vecom'", [(int) $k['id']], 0), false); ?>
    <details style="margin:0 0 10px"><summary style="font-size:13px;color:var(--dim);cursor:pointer">E-Mails aus dem alten Postfach umziehen …</summary>
      <?php if (!$mDarf): ?>
        <p style="color:var(--leise);font-size:12.5px;margin:8px 0 0">Geht erst, wenn der Kunde Domain &amp; Hosting mit Postfach bei uns zugestimmt hat — vorher gibt es kein Ziel.</p>
      <?php else: ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="mailumzug_anfragen">
        <input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
        <input name="adresse" type="email" required value="<?= Fmt::h((string) $k['email']) ?>" placeholder="alte Adresse" style="max-width:230px" aria-label="Altes Postfach">
        <span>→</span>
        <input name="ziel" type="email" value="<?= Fmt::h($mZiel) ?>" placeholder="neue Adresse" style="max-width:230px" aria-label="Neues Postfach">
        <button class="knopf">Umzug anfragen</button>
        <span style="color:var(--leise);font-size:12.5px">Der Kunde stimmt zu und gibt sein altes Passwort ein — sobald das neue Postfach eingerichtet ist. Danach läuft alles von selbst.</span>
      </form>
      <?php endif; ?></details>
    <?php if (!$dateien): ?><div class="leer">Noch nichts.</div><?php else: ?>
      <?php foreach ($dateien as $d): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 0;border-top:1px solid var(--linie)">
          <span><?= Fmt::h($d['orig_name']) ?><br><small style="color:var(--leise)">
            <?= Fmt::h(Fmt::bytes((int) $d['size_bytes'])) ?> · <?= $d['uploaded_by'] === 'kunde' ? 'vom Kunden' : 'von dir' ?>
            · <?= Fmt::h(Fmt::datum($d['created_at'])) ?></small></span>
          <span style="display:flex;gap:6px;flex-shrink:0">
            <a class="knopf" href="<?= Fmt::h(url('dateien/' . (int) $d['id'])) ?>">Herunterladen</a>
            <?php /* Loeschen — mit Rueckfrage, weil es nicht rueckgaengig zu
                     machen ist. Der Weg zurueck fuehrt in die Kundenakte, nicht
                     ins Projekt: Diese Datei haengt hier am Kunden. */ ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
                  data-frage="„<?= Fmt::h($d['orig_name']) ?>" wirklich löschen? Das lässt sich nicht rückgängig machen."
                  data-ja="Ja, löschen">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="datei_weg">
              <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
              <button class="knopf" style="color:var(--rot)" title="Löschen">Löschen</button>
            </form>
          </span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" enctype="multipart/form-data" style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_datei">
      <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
      <input type="file" name="datei" required style="max-width:260px">
      <button class="knopf">Hochladen</button>
    </form>
  </div>
</div><div>
<?php endif; ?>

  <div class="block"><h2>Kontakt</h2><table><tbody>
    <tr><td>E-Mail</td><td><?= Fmt::h($k['email']) ?></td></tr>
    <tr><td>Telefon</td><td><?= Fmt::h($k['phone'] ?: '—') ?></td></tr>
    <tr><td>Firma</td><td><?= Fmt::h($k['company'] ?: '—') ?></td></tr>
    <tr><td>Branche</td><td><?= Fmt::h($k['industry'] ?: '—') ?></td></tr>
    <tr><td>Adresse</td><td><?= Fmt::h(trim(($k['street'] ?? '') . ' ' . ($k['zip'] ?? '') . ' ' . ($k['city'] ?? '') . ' ' . ($k['country'] ?? ''))) ?: '—' ?></td></tr>
    <?php /* ------------------------------------------------------------
         SPRACHE: GEFRAGT ODER GERATEN

         An dieser einen Zeile haengt alles, was der Kunde je zu lesen
         bekommt — jede Mail, jeder Beleg, seine ganze Seite. Sie stand
         hier bisher als Tatsache da, war aber meistens geraten: aus der
         Sprachfassung der Website, auf der er zufaellig stand.

         Jetzt steht daneben, woher sie kommt. "Vermutet" heisst: Frag
         einmal nach oder schreib ihm zweisprachig. Und geaendert wird sie
         hier mit einem Klick, nicht in einem Formular mit zwoelf Feldern.
         ------------------------------------------------------------ */ ?>
    <?php
      $kSp = strtolower((string) ($k['sprache'] ?? 'it'));
      $kSpOk = (bool) sicher(static fn() => Db::wert(
          'SELECT sprache_bestaetigt FROM customers WHERE id = ?', [(int) $k['id']], null) !== null, true);
    ?>
    <tr><td>Sprache</td><td>
      <div class="leiste" style="gap:8px;flex-wrap:wrap;align-items:center">
        <form method="post" action="<?= Fmt::h(url('')) ?>" class="leiste" style="gap:4px;margin:0">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="sprache_setzen">
          <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
          <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $sl => $wort): ?>
            <button class="knopf <?= $kSp === $sl ? 'haupt' : '' ?>" name="sprache" value="<?= $sl ?>"
                    <?= $kSp === $sl ? 'disabled' : '' ?>><?= Fmt::h($wort) ?></button>
          <?php endforeach; ?>
        </form>
        <span class="marke2 <?= $kSpOk ? 'gut' : 'warnung' ?>"><?= $kSpOk ? 'vom Kunden bestätigt' : 'vermutet' ?></span>
      </div>
      <small style="color:var(--leise);line-height:1.6;display:block;margin-top:6px">
        So gehen alle automatischen E-Mails raus, und so sieht er seine Seite.
        <?php if (!$kSpOk): ?><br>Er hat sie nie selbst gewählt — sie stammt aus der Sprachfassung
        der Website, auf der er stand. Sobald er oben auf seiner Seite umschaltet, steht hier
        „bestätigt".<?php endif; ?>
      </small></td></tr>
    <tr><td>Kunde seit</td><td><?= Fmt::h(Fmt::datum($k['created_at'])) ?></td></tr>
  </tbody></table></div>
  <?php /* ---------- Betreuung: der zweite Vertrag ---------- */ ?>
  <?php
    require_once __DIR__ . '/../src/Abo.php';
    $abo = sicher(static fn() => Abo::fuerKunde((int) $k['id']), null);
    $betreuungspakete = sicher(static fn() => Db::all(
        "SELECT * FROM packages WHERE active = 1 AND art = 'betreuung' ORDER BY sort, monthly_cents"), []);
  ?>
  <?php if (empty($k['anonym_am'])): ?>
  <div class="block"><h2>Betreuung
    <?php if ($abo): ?>
      <span class="mehr"><span class="marke2 <?= ['aktiv'=>'gut','gekuendigt'=>'warnung'][$abo['status']] ?? '' ?>">
        <?= Fmt::h(['aktiv'=>'läuft','gekuendigt'=>'gekündigt','beendet'=>'beendet','angelegt'=>'angelegt'][$abo['status']] ?? $abo['status']) ?></span></span>
    <?php endif; ?></h2>
    <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
      Ein eigener Vertrag, getrennt von der Website: monatlich, zwölf Monate Mindestlaufzeit,
      danach zum Monatsende kündbar.</p>

    <?php if ($abo): ?>
      <div class="tabellenrahmen"><table><tbody>
        <tr><td style="width:38%">Paket</td><td><?= Fmt::h((string) $abo['paket_name']) ?></td></tr>
        <tr><td>Monatlich</td><td><b><?= Fmt::h(Fmt::geld((int) $abo['betrag_cents'], (string) $abo['currency'])) ?></b></td></tr>
        <tr><td>Zahlart</td><td><?= Fmt::h(Abo::ZAHLARTEN[$abo['zahlart']] ?? (string) $abo['zahlart']) ?></td></tr>
        <tr><td>Läuft seit</td><td><?= Fmt::h(Fmt::datum((string) $abo['beginn'])) ?></td></tr>
        <tr><td>Mindestens bis</td><td><?= Fmt::h(Fmt::datum((string) $abo['mindestlaufzeit_bis'])) ?></td></tr>
        <?php if ($abo['laeuft_bis']): ?>
          <tr><td>Gekündigt zum</td><td><b><?= Fmt::h(Fmt::datum((string) $abo['laeuft_bis'])) ?></b>
            <small style="color:var(--leise)">— danach wird nicht mehr abgebucht</small></td></tr>
        <?php endif; ?>
      </tbody></table></div>

      <?php /* ---------- Die abgerechneten Monate ---------- */ ?>
      <?php $raten = sicher(static fn() => Abo::raten((int) $abo['id']), []); ?>
      <div style="margin-top:14px">
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin-bottom:6px">
          <strong style="font-size:13.5px">Abgerechnete Monate</strong>
          <?php if (in_array((string) $abo['status'], ['aktiv','gekuendigt'], true)): ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:6px;align-items:center;margin:0">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="abo_abrechnen">
              <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $abo['id'] ?>">
              <input type="month" name="monat" style="width:150px"
                     value="<?= Fmt::h(date('Y-m', strtotime((string) ($abo['naechste_abrechnung'] ?: 'today')))) ?>">
              <button class="knopf">Monat abrechnen</button></form>
          <?php endif; ?>
        </div>
        <?php if (!$raten): ?>
          <div class="leer" style="font-size:12.5px">Noch kein Monat abgerechnet. Der nächtliche Lauf legt an,
            was fällig wird — anfordern musst du selbst, damit der Kunde davon erfährt.</div>
        <?php else: ?>
          <div class="tabellenrahmen"><table>
            <thead><tr><th>Monat</th><th class="num">Betrag</th><th>Stand</th><th></th></tr></thead><tbody>
            <?php foreach ($raten as $r): ?>
              <tr>
                <td><?= Fmt::h(Abo::monatswort((string) $r['abrechnungsmonat'])) ?></td>
                <td class="num"><?= Fmt::h(Fmt::geld((int) $r['amount_cents'], (string) $r['currency'])) ?></td>
                <td><span class="marke2 <?= Fmt::h(Status::ton((string) $r['status'])) ?>"><?=
                    Fmt::h(Status::label(Status::ZAHLUNG, (string) $r['status'])) ?></span>
                  <?php if ((string) $r['status'] !== 'bezahlt'): ?>
                    <small style="color:var(--leise)"><?= $r['faellig_am']
                      ? ' · fällig ' . Fmt::h(Fmt::datum((string) $r['faellig_am']))
                      : ' · noch nicht angefordert' ?></small>
                  <?php endif; ?></td>
                <td style="text-align:right">
                  <?php if ((string) $r['status'] !== 'bezahlt'): ?>
                    <?php /* Derselbe Mahnstand wie bei einer Bestellung: Was
                             der Kunde schon bekommen hat, steht am Geld —
                             sonst schickt man ihm die zweite Mahnung, ohne
                             zu wissen, dass die erste raus ist. */ ?>
                    <?php
                      $mStand = sicher(static fn() => Mahnung::stand((int) $r['id']), 0);
                      $mUeber = $r['faellig_am']
                          ? (int) floor((strtotime('today') - strtotime((string) $r['faellig_am'])) / 86400) : null;
                      $mNaechste = $mStand + 1;
                      $mDran = $mUeber !== null && $mNaechste >= 2 && $mNaechste <= 3
                               && $mUeber >= (Mahnung::STUFEN[$mNaechste] ?? 999);
                    ?>
                    <?php if ($mStand > 0): ?>
                      <span class="marke2" title="<?= Fmt::h(Mahnung::name($mStand)) ?> ist raus"><?=
                        (int) $mStand ?>. Mahnstufe</span>
                    <?php endif; ?>
                    <?php if ($mDran): ?>
                      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
                            data-frage="<?= Fmt::h(Mahnung::name($mNaechste)) ?> an <?= Fmt::h((string) $k['name']) ?> schicken?"
                            data-ja="Ja, schicken" data-nein="Abbrechen">
                        <?= Csrf::feld() ?><input type="hidden" name="tat" value="mahnung_schicken">
                        <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="stufe" value="<?= (int) $mNaechste ?>">
                        <button class="knopf" title="Seit <?= (int) $mUeber ?> Tagen überfällig"><?=
                          Fmt::h(Mahnung::name($mNaechste)) ?></button></form>
                    <?php endif; ?>
                    <?php if (!$r['faellig_am']): ?>
                      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
                            data-frage="Betreuung <?= Fmt::h(Abo::monatswort((string) $r['abrechnungsmonat'])) ?> anfordern?"
                            data-ja="Ja, anfordern" data-nein="Abbrechen">
                        <?= Csrf::feld() ?><input type="hidden" name="tat" value="abo_anfordern">
                        <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="knopf">Anfordern</button></form>
                    <?php endif; ?>
                    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
                          data-frage="Als bezahlt buchen? Daraus entsteht ein Beleg."
                          data-ja="Ja, buchen" data-nein="Abbrechen">
                      <?= Csrf::feld() ?><input type="hidden" name="tat" value="zahlung_bestaetigen">
                      <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <button class="knopf">Von Hand buchen</button></form>
                  <?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
      </div>

      <?php if (in_array((string) $abo['status'], ['aktiv', 'angelegt'], true)): ?>
        <?php $vor = sicher(static fn() => Abo::kuendigungsvorschau($abo), ['ende' => '', 'mindestlaufzeit' => false]); ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"
              data-frage="Kündigen zum <?= Fmt::h(Fmt::datum((string) $vor['ende'])) ?>? Der Kunde bekommt sofort die Bestätigung."
              data-ja="Ja, kündigen">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="abo_kuendigen">
          <input type="hidden" name="id" value="<?= (int) $abo['id'] ?>">
          <button class="knopf">Für den Kunden kündigen</button>
          <span style="color:var(--leise);font-size:12.5px;margin-left:8px">
            Ende wäre der <?= Fmt::h(Fmt::datum((string) $vor['ende'])) ?><?= $vor['mindestlaufzeit'] ? ' (Mindestlaufzeit)' : ' (Monatsende)' ?>.
            Kündigen kann er auch selbst auf seiner Seite.</span>
        </form>
      <?php endif; ?>

    <?php elseif ($betreuungspakete): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="abo_anlegen">
        <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
        <div class="feld"><label>Paket</label>
          <select name="paket_slug">
            <?php foreach ($betreuungspakete as $bp): ?>
              <option value="<?= Fmt::h((string) $bp['slug']) ?>"><?= Fmt::h((string) $bp['name']) ?>
                — <?= Fmt::h(Fmt::geld((int) $bp['monthly_cents'], (string) $bp['currency'])) ?> im Monat</option>
            <?php endforeach; ?>
          </select></div>
        <div class="feld"><label>Monatsbetrag, wenn abweichend vereinbart</label>
          <input name="betrag" inputmode="decimal" placeholder="leer = Paketpreis">
          <small style="color:var(--leise);display:block;margin-top:5px">Nur ausfüllen, wenn ihr
            etwas anderes besprochen habt. Was hier steht, steht danach im Vertrag.</small></div>
        <div class="feld"><label>Zahlart</label>
          <select name="zahlart">
            <option value="manuell">Von Hand abrechnen — bis Stripe bereit ist</option>
            <option value="karte">Karte</option>
            <option value="sepa">SEPA-Lastschrift</option>
          </select></div>
        <button class="knopf">Betreuung anlegen</button>
        <span style="color:var(--leise);font-size:12.5px;margin-left:8px">
          Die Mindestlaufzeit beginnt heute.</span>
      </form>
    <?php else: ?>
      <div class="leer">Es gibt keine Betreuungspakete. Leg sie unter „Pakete" an.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php /* ---------- Domain & Hosting: der dritte Vertrag ----------
           Entsteht sonst von allein aus dem Fragebogen. Hier ist der
           Handgriff fuer alle anderen Faelle: Eine Anfrage nur nach Domain
           und Hosting (Solo), oder ein Kunde, der es sich spaeter ueberlegt.
           Uwe prueft die Wunschdomain, schlaegt sie vor — und der Kunde
           bekommt die Angebots-Mail mit dem Link auf seine Seite, wo Preis
           und Ja-Knopf stehen. Die ZUSTIMMUNG bleibt beim Kunden. */ ?>
  <?php $hostingA = null; ?>
  <?php if (empty($k['anonym_am'])): ?>
  <?php
    require_once __DIR__ . '/../src/Hosting.php';
    $hostingA = sicher(static fn() => Hosting::fuerKunde((int) $k['id']), null);
    /* Die Wunschdomains aus dem Fragebogen — falls es einen gibt. Sie stehen
       hier, damit Uwe nicht in den Fragebogen springen muss: Ein Klick
       prueft den Wunsch und bietet ihn an (nur wenn er wirklich frei ist —
       das prueft der Handler, nicht diese Ansicht). */
    $hostingWuensche = sicher(static function () use ($k): array {
        $q = Db::one("SELECT data FROM questionnaires WHERE customer_id = ?
                       ORDER BY id DESC LIMIT 1", [(int) $k['id']]);
        if (!$q || empty($q['data'])) { return []; }
        $d = json_decode((string) $q['data'], true) ?: [];
        $aus = [];
        foreach (['wunsch1', 'wunsch2', 'wunsch3'] as $f) {
            $w = trim((string) ($d[$f] ?? ''));
            if ($w !== '' && !in_array($w, $aus, true)) { $aus[] = $w; }
        }
        return $aus;
    }, []);
  ?>
  <div class="block"><h2>Domain &amp; Hosting
    <?php if ($hostingA): ?>
      <span class="mehr"><span class="marke2 <?= ['zugestimmt'=>'gut','angelegt'=>'gut','aktiv'=>'gut','in_arbeit'=>'warn'][$hostingA['status']] ?? '' ?>">
        <?= Fmt::h(['vorgeschlagen'=>'vorgeschlagen','zugestimmt'=>'zugestimmt','in_arbeit'=>'wird eingerichtet','angelegt'=>'angelegt','aktiv'=>'läuft'][$hostingA['status']] ?? (string) $hostingA['status']) ?></span></span>
    <?php endif; ?></h2>

    <?php if ($hostingA): ?>
      <div class="tabellenrahmen"><table><tbody>
        <tr><td style="width:38%">Domain</td><td><b><?= Fmt::h((string) $hostingA['domain']) ?></b>
          <?php /* Was mit der Domain geschehen soll (Migration 053). "Umzug" nur,
                   wenn der Kunde es ausdruecklich gewaehlt hat. */ ?>
          <small style="color:var(--leise)"> — <?= Fmt::h([
              'neu' => 'neu registrieren', 'transfer' => 'Umzug zu Vecom (Kunde hat ihn gewählt)',
              'behalten' => 'bleibt beim bisherigen Anbieter', 'offen' => 'noch mit dem Kunden klären',
          ][(string) ($hostingA['domain_aktion'] ?? 'neu')] ?? (string) $hostingA['domain_aktion']) ?></small></td></tr>
        <tr><td>E-Mail</td><td><?= Fmt::h([
              'vecom' => 'Postfach ' . Hosting::POSTFACH . '@' . $hostingA['domain'] . ' über Vecom',
              'bisher' => 'bleibt beim bisherigen Anbieter — kein Postfach, MX nicht anfassen',
              'keine' => 'keine', 'offen' => 'noch offen — kein Postfach, bis der Kunde es wählt',
          ][(string) ($hostingA['mail'] ?? 'vecom')] ?? (string) $hostingA['mail']) ?></td></tr>
        <?php $hZust = sicher(static fn() => Db::one("SELECT * FROM zustimmungen WHERE art = 'hosting' AND bezug_id = ?
                                                      ORDER BY id DESC LIMIT 1", [(int) $hostingA['id']]), null); ?>
        <?php if ($hZust): ?>
          <tr><td>Zustimmung</td><td><details><summary><?= Fmt::h(Fmt::zeit((string) $hZust['created_at'])) ?>
            · Fassung <?= Fmt::h((string) $hZust['fassung']) ?> · <?= Fmt::h(strtoupper((string) $hZust['sprache'])) ?></summary>
            <p style="white-space:pre-line;color:var(--dim);font-size:12.5px;margin:6px 0 0"><?= Fmt::h((string) $hZust['text']) ?></p>
          </details></td></tr>
        <?php endif; ?>
        <tr><td>Monatlich</td><td><?= Fmt::h(Fmt::geld((int) $hostingA['preis_cents'])) ?><?=
          $hostingA['inklusive'] ? ' <small style="color:var(--leise)">— in der Betreuung enthalten</small>' : '' ?></td></tr>
        <?php if ($hostingA['kas_login']): ?>
          <tr><td>KAS-Account</td><td><?= Fmt::h((string) $hostingA['kas_login']) ?></td></tr>
        <?php endif; ?>
        <?php /* 26.09.2026: Vecom ist die Quelle fuer den Speicher, der KAS folgt.
                 Drei Zahlen, damit eine Abweichung sofort ins Auge faellt. */
              $hSoll = Hosting::speicherVon($hostingA);
              $hIst  = $hostingA['kas_speicher_mb'] !== null ? (int) $hostingA['kas_speicher_mb'] : null;
              $hBelegt = $hostingA['kas_login'] ? (sicher(static fn() => Hosting::speicher(), [])[(string) $hostingA['kas_login']] ?? null) : null; ?>
        <tr><td>Speicher</td><td>
          <b><?= Fmt::h(Hosting::gb($hSoll)) ?></b> vereinbart
          <?php if ($hBelegt !== null): ?> · <?= Fmt::h(Hosting::gb((int) $hBelegt)) ?> belegt<?php endif; ?>
          <?php if ($hIst !== null): ?>
            · im KAS <?= Fmt::h(Hosting::gb($hIst)) ?>
            <?php if ($hIst !== $hSoll): ?> <span class="marke2 warn">weicht ab</span><?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($hostingA['kas_gelesen_am'])): ?><br><small style="color:var(--leise)">zuletzt abgeglichen <?= Fmt::h(Fmt::zeit((string) $hostingA['kas_gelesen_am'])) ?></small><?php endif; ?>
        </td></tr>
        <?php if (in_array((string) $hostingA['status'], ['angelegt', 'aktiv'], true)): ?>
          <tr><td>HTTPS</td><td>
            <?= Fmt::h(['ok' => '✓ in Ordnung', 'warnung' => '⚠ unvollständig', 'fehler' => '✗ fehlt'][(string) ($hostingA['ssl_status'] ?? '')] ?? 'noch nicht geprüft') ?>
            <?php if (!empty($hostingA['ssl_text'])): ?><br><small style="color:var(--dim)"><?= Fmt::h((string) $hostingA['ssl_text']) ?></small><?php endif; ?>
            <?php if (!empty($hostingA['ssl_geprueft_am'])): ?><br><small style="color:var(--leise)">geprüft <?= Fmt::h(Fmt::zeit((string) $hostingA['ssl_geprueft_am'])) ?></small><?php endif; ?>
          </td></tr>
        <?php endif; ?>
        <tr><td>Datenbank / FTP</td><td><?= $hostingA['mit_datenbank'] ? 'Datenbank' : 'keine Datenbank' ?> · <?= $hostingA['mit_ftp'] ? 'FTP für Vecom' : 'kein eigener FTP' ?></td></tr>
        <?php if ($hostingA['notiz']): ?>
          <tr><td>Notiz</td><td style="color:var(--dim)"><?= Fmt::h((string) $hostingA['notiz']) ?></td></tr>
        <?php endif; ?>
      </tbody></table></div>
      <p style="color:var(--leise);font-size:12.5px;margin:10px 0 0">
        <?php if ((string) $hostingA['status'] === 'vorgeschlagen'): ?>
          Der Kunde hat die Angebots-Mail und entscheidet auf seiner Seite.
        <?php elseif ((string) $hostingA['status'] === 'zugestimmt'): ?>
          <?= $hostingA['project_id'] === null
              ? 'Zugestimmt. Angelegt wird von selbst, sobald die erste Monatsrate bezahlt ist.'
              : 'Zugestimmt. Bei der finalen Freigabe geht die erste Monatsrate raus; angelegt wird von selbst, sobald sie bezahlt ist (steckt das Hosting in Betreuung Plus/Premium, gleich bei der Freigabe).' ?>
        <?php elseif ((string) $hostingA['status'] === 'in_arbeit'): ?>
          Wird eingerichtet. Gescheiterte Schritte versucht der Cron nach <?= (int) Hosting::PAUSE_MINUTEN ?> Minuten noch einmal, höchstens <?= (int) Hosting::VERSUCHE ?>-mal.
          Die Zugangsdaten gibt es für den Kunden erst, wenn alles Automatische durch ist.
        <?php else: ?>
          Angelegt<?= $hostingA['angelegt_am'] ? ' am ' . Fmt::h(Fmt::datum((string) $hostingA['angelegt_am'])) : '' ?>.
          <?= $hostingA['zugang_blob'] !== null ? 'Die Zugangsdaten warten auf den einmaligen Abruf durch den Kunden.'
              : 'Die Zugangsdaten sind abgerufen oder abgelaufen.' ?>
        <?php endif; ?></p>
      <?php /* Phase 3: jeder Schritt einzeln -- was geklappt hat, was wartet, was Handarbeit ist. */ ?>
      <?php $hSchritte = sicher(static fn() => Hosting::schritte((int) $hostingA['id']), []); ?>
      <?php if ($hSchritte): ?>
        <?php $hZeichen = ['fertig' => '✓', 'entfaellt' => '–', 'offen' => '·', 'laeuft' => '…', 'fehler' => '↻', 'hand' => '✋']; ?>
        <div class="tabelle" style="margin-top:10px"><table class="schlicht"><tbody>
          <?php foreach (Hosting::SCHRITTE as $hS => $hName): if (!isset($hSchritte[$hS])) { continue; } $hZ = $hSchritte[$hS]; ?>
            <tr><td style="width:38%"><?= Fmt::h($hZeichen[(string) $hZ['status']] ?? '?') ?> <?= Fmt::h($hName) ?></td>
              <td><?= Fmt::h(['fertig' => 'erledigt', 'entfaellt' => 'entfällt', 'offen' => 'offen', 'laeuft' => 'läuft',
                              'fehler' => 'wird wiederholt', 'hand' => 'von Hand'][(string) $hZ['status']] ?? (string) $hZ['status']) ?>
                <?php if ((int) $hZ['versuche'] > 1): ?><small style="color:var(--leise)"> · <?= (int) $hZ['versuche'] ?> Versuche</small><?php endif; ?>
                <?php if ((string) ($hZ['text'] ?? '') !== ''): ?><br><small style="color:var(--dim)"><?= Fmt::h((string) $hZ['text']) ?></small><?php endif; ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
        <?php $hOffen = array_filter($hSchritte, static fn($z) => in_array((string) $z['status'], ['fehler', 'hand'], true)); ?>
        <?php if ($hOffen): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_weiter">
            <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
            <input type="hidden" name="id" value="<?= (int) $hostingA['id'] ?>">
            <button class="knopf">Offene Schritte wiederholen</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <?php /* DOMAIN BESTELLEN (26.09.2026, Weg B): Uwe bestellt im Domainbestellsystem
               von All-Inkl -- dafuer gibt es keine Schnittstelle. Hier steht alles zum
               Kopieren; dass die Domain da ist, merkt der Cron an den Nameservern
               (Hosting::registrierungNachsehen) und macht von allein weiter. */ ?>
      <?php if (Hosting::wartetAufBestellung($hostingA)): ?>
        <?php $dbFelder = array_filter([
            'Domain'        => (string) $hostingA['domain'],
            'Inhaber'       => trim((string) ($k['company'] ?: $k['name'])),
            'Ansprechpartner' => $k['company'] ? trim((string) $k['name']) : '',
            'E-Mail'        => trim((string) $k['email']),
            'Telefon'       => trim((string) ($k['phone'] ?? '')),
            'Adresse'       => trim(implode(', ', array_filter([(string) ($k['street'] ?? ''), trim(($k['zip'] ?? '') . ' ' . ($k['city'] ?? '')), (string) ($k['country'] ?? '')]))),
            'Steuernummer / Codice fiscale' => trim((string) ($k['tax_code'] ?? '')),
            'USt-IdNr / P. IVA' => trim((string) ($k['vat_id'] ?? '')),
            'Nameserver'    => implode(' · ', Hosting::NAMESERVER),
          ], static fn($x) => $x !== ''); ?>
        <div style="margin-top:12px;padding:12px 14px;border:1px solid var(--linie);border-radius:10px">
          <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
            <b>Domain bestellen</b>
            <?php /* Das vorhandene Kopieren aus dem Layout: erst in die Zwischenablage,
                     dann das Bestellsystem in einem neuen Tab (data-oeffnen). */ ?>
            <button type="button" class="knopf klein" data-kopieren="db-daten-<?= (int) $hostingA['id'] ?>"
                    data-oeffnen="https://www.domain-bestellsystem.de/">Kopieren und Bestellsystem öffnen ↗</button>
          </div>
          <textarea id="db-daten-<?= (int) $hostingA['id'] ?>" readonly aria-hidden="true" tabindex="-1"
                    style="position:absolute;left:-9999px;width:1px;height:1px"><?= Fmt::h(implode("\n", array_map(static fn($n, $w) => $n . ': ' . $w, array_keys($dbFelder), $dbFelder))) ?></textarea>
          <table class="schlicht" style="margin-top:8px"><tbody>
            <?php foreach ($dbFelder as $dbN => $dbW): ?>
              <tr><td style="width:38%;color:var(--leise)"><?= Fmt::h($dbN) ?></td>
                <td><code style="user-select:all;overflow-wrap:anywhere"><?= Fmt::h($dbW) ?></code></td></tr>
            <?php endforeach; ?>
          </tbody></table>
          <?php foreach (['Adresse' => 'Die Adresse fehlt', 'Telefon' => 'Die Telefonnummer fehlt'] as $dbP => $dbS): if (!isset($dbFelder[$dbP])): ?>
            <p style="color:var(--rot);font-size:12.5px;margin:6px 0 0"><?= Fmt::h($dbS) ?> — das Bestellsystem verlangt sie für den Inhaber. Unter „Bearbeiten“ ergänzen.</p>
          <?php endif; endforeach; ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_registrierung">
            <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>"><input type="hidden" name="id" value="<?= (int) $hostingA['id'] ?>">
            <button class="knopf klein">Jetzt nachsehen</button>
            <span style="color:var(--leise);font-size:12.5px">Das System sieht auch von selbst alle zehn Minuten nach: Sobald die Nameserver auf All-Inkl zeigen,
              prüft es HTTPS, meldet sich bei dir und schreibt dem Kunden.</span>
          </form>
        </div>
      <?php elseif ((string) ($hostingA['domain_aktion'] ?? '') === 'neu' && !empty($hostingA['domain_registriert_am'])): ?>
        <p style="color:var(--leise);font-size:12.5px;margin:10px 0 0">Domain registriert erkannt am <?= Fmt::h(Fmt::zeit((string) $hostingA['domain_registriert_am'])) ?>.</p>
      <?php endif; ?>
      <?php /* Die Handgriffe zu Speicher, HTTPS und Technik -- klein, unter der Tabelle.
               Keiner davon ist die Hauptsache der Seite, deshalb kein Blau. */ ?>
      <?php $hZ = 'kunden/' . (int) $k['id']; $hId = (int) $hostingA['id']; ?>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;align-items:center">
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:6px;align-items:center">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_speicher_vereinbaren">
          <input type="hidden" name="zurueck" value="<?= Fmt::h($hZ) ?>"><input type="hidden" name="id" value="<?= $hId ?>">
          <input name="gb" inputmode="decimal" value="<?= Fmt::h(rtrim(rtrim(number_format($hSoll / 1024, 2, ',', ''), '0'), ',')) ?>" style="width:70px" aria-label="Vereinbarter Speicher in GB"> GB
          <button class="knopf klein">Vereinbaren</button>
        </form>
        <?php if ($hostingA['kas_login'] && $hIst !== null && $hIst !== $hSoll): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_speicher_kas">
            <input type="hidden" name="zurueck" value="<?= Fmt::h($hZ) ?>"><input type="hidden" name="id" value="<?= $hId ?>">
            <button class="knopf">KAS auf Vecom-Wert setzen</button>
          </form>
        <?php endif; ?>
        <?php if ($hostingA['kas_login']): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_abgleich">
            <input type="hidden" name="zurueck" value="<?= Fmt::h($hZ) ?>"><button class="knopf klein">Jetzt abgleichen</button>
          </form>
        <?php endif; ?>
        <?php if (in_array((string) $hostingA['status'], ['angelegt', 'aktiv'], true)): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_https">
            <input type="hidden" name="zurueck" value="<?= Fmt::h($hZ) ?>"><input type="hidden" name="id" value="<?= $hId ?>">
            <button class="knopf klein">HTTPS prüfen</button>
          </form>
        <?php endif; ?>
      </div>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:8px;font-size:13px;color:var(--dim)">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_technik">
        <input type="hidden" name="zurueck" value="<?= Fmt::h($hZ) ?>"><input type="hidden" name="id" value="<?= $hId ?>">
        <div style="display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center">
          <label style="display:inline-flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="mit_datenbank" value="1" style="width:auto;margin:0" <?= $hostingA['mit_datenbank'] ? 'checked' : '' ?>> Datenbank anlegen</label>
          <label style="display:inline-flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="mit_ftp" value="1" style="width:auto;margin:0" <?= $hostingA['mit_ftp'] ? 'checked' : '' ?>> FTP-Zugang für Vecom</label>
          <button class="knopf klein">Merken</button>
        </div>
      </form>
      <?php if (!empty($hostingA['technik_blob'])): ?>
        <?php $hTech = (($_SESSION['hosting_technik']['id'] ?? 0) === $hId) ? $_SESSION['hosting_technik']['daten'] : null;
              unset($_SESSION['hosting_technik']); ?>
        <?php if ($hTech): ?>
          <div class="tabellenrahmen" style="margin-top:8px"><table><tbody>
            <?php foreach (['datenbank' => 'Datenbank', 'ftp' => 'FTP'] as $hTs => $hTn): if (empty($hTech[$hTs])) { continue; } ?>
              <tr><td style="width:38%"><?= $hTn ?></td><td><code><?= Fmt::h((string) ($hTech[$hTs]['name'] ?? $hTech[$hTs]['login'] ?? $hTech[$hTs]['kommentar'] ?? '')) ?></code>
                · Passwort <input type="password" readonly value="<?= Fmt::h((string) ($hTech[$hTs]['passwort'] ?? '')) ?>" style="width:170px"
                     onfocus="this.type='text';this.select()" onblur="this.type='password'"></td></tr>
            <?php endforeach; ?>
          </tbody></table></div>
        <?php else: ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:8px">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_technik_zeigen">
            <input type="hidden" name="zurueck" value="<?= Fmt::h($hZ) ?>"><input type="hidden" name="id" value="<?= $hId ?>">
            <button class="knopf klein">Zugang Datenbank/FTP zeigen</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <?php /* Nach Vertragsende: sperren, nicht loeschen -- und wieder oeffnen koennen. */ ?>
      <?php if ((string) ($hostingA['kas_login'] ?? '') !== '' && in_array((string) $hostingA['status'], ['angelegt', 'aktiv'], true)): ?>
        <?php $hGesperrt = !empty($hostingA['gesperrt_am']);
              $hSperrbar = !$hGesperrt && (bool) array_filter(sicher(static fn() => Hosting::zumSperren(), []), static fn($x) => (int) $x['id'] === (int) $hostingA['id']); ?>
        <?php if ($hGesperrt || $hSperrbar): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="<?= $hGesperrt ? 'hosting_entsperren' : 'hosting_sperren' ?>">
            <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>"><input type="hidden" name="id" value="<?= (int) $hostingA['id'] ?>">
            <?php if ($hGesperrt): ?>
              <span style="color:var(--leise);font-size:12.5px">KAS-Zugang gesperrt seit <?= Fmt::h(Fmt::datum((string) $hostingA['gesperrt_am'])) ?>.</span>
              <button class="knopf" style="margin-left:8px">Wieder öffnen</button>
            <?php else: ?>
              <span style="color:var(--rot);font-size:12.5px">Vertrag beendet, KAS-Zugang noch offen.</span>
              <button class="knopf" style="margin-left:8px">Zugang sperren</button>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <?php /* Phase 5: der Domain-Umzug -- Stand, Sperre, was im KAS-DNS stehen
               muss, und der Code nur auf Klick. Den Antrag stellst du im
               Domainbestellsystem; hier klickst du nur, DASS er gestellt ist. */ ?>
      <?php $umzugA = (string) ($hostingA['domain_aktion'] ?? '') === 'transfer'
          ? sicher(static function () use ($hostingA) { require_once __DIR__ . '/../src/Domainumzug.php';
                return Domainumzug::fuerAuftrag((int) $hostingA['id']); }, null) : null; ?>
      <?php if ($umzugA): ?>
        <?php $uStand = ['code_fehlt' => 'wartet auf den Auth-Code', 'code_da' => 'Code ist da — Antrag stellen',
                         'beantragt' => 'beantragt — der Cron sieht nach, wann die Nameserver umstehen', 'fertig' => 'umgezogen'][(string) $umzugA['stand']] ?? (string) $umzugA['stand']; ?>
        <div style="margin-top:14px;padding:12px 14px;border:1px solid var(--linie);border-radius:10px">
          <div style="font-weight:650">Umzug <?= Fmt::h((string) $umzugA['domain']) ?>
            <span class="marke2 <?= (string) $umzugA['stand'] === 'fertig' ? 'gut' : ((string) $umzugA['stand'] === 'code_da' ? 'warnung' : '') ?>" style="margin-left:6px"><?= Fmt::h($uStand) ?></span></div>
          <p style="color:var(--leise);font-size:12.5px;margin:6px 0 0">
            Transfersperre: <b><?= Fmt::h(['gesperrt' => 'gesetzt — der Kunde muss sie beim alten Anbieter lösen', 'frei' => 'keine',
                                           'unklar' => 'nicht lesbar (bei .it nur per WHOIS)'][(string) ($umzugA['sperre'] ?? 'unklar')] ?? '—') ?></b>
            <?= $umzugA['sperre_am'] ? ' · geprüft ' . Fmt::h(Fmt::datum((string) $umzugA['sperre_am'])) : '' ?></p>
          <?php $uDns = json_decode((string) ($umzugA['dns_json'] ?? ''), true) ?: []; ?>
          <?php $uDnsSchritt = (array) (($hSchritte ?? [])['dns'] ?? []); ?>
          <?php if ((string) ($uDnsSchritt['status'] ?? '') === 'fertig'): ?>
            <p style="font-size:12.5px;margin:10px 0 0">✓ <b>DNS automatisch in den KAS übernommen</b>
              <span style="color:var(--leise)">— <?= Fmt::h((string) $uDnsSchritt['text']) ?></span></p>
          <?php elseif ($uDns && !in_array((string) $umzugA['stand'], ['beantragt', 'fertig'], true)): ?>
            <p style="font-size:12.5px;margin:10px 0 4px"><b>Vor dem Antrag im KAS-DNS eintragen</b>
              <span style="color:var(--leise)">— sonst kommen beim Kunden nach dem Umzug keine Mails mehr an. NS nicht übernehmen.</span></p>
            <div class="tabellenrahmen"><table class="schlicht"><tbody>
              <?php foreach ($uDns as $e): ?>
                <tr<?= $e['typ'] === 'NS' ? ' style="opacity:.5"' : '' ?>><td style="width:22%"><code><?= Fmt::h((string) $e['name']) ?></code></td>
                  <td style="width:10%"><?= Fmt::h((string) $e['typ']) ?></td>
                  <td><code style="word-break:break-all;user-select:all"><?= Fmt::h((string) $e['wert']) ?></code></td></tr>
              <?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
          <?php if (!empty($_SESSION['umzug_code'][(int) $umzugA['id']])): ?>
            <div class="hinweis gut" style="margin-top:10px">Auth-Code: <code style="user-select:all;font-size:15px"><?=
              Fmt::h((string) $_SESSION['umzug_code'][(int) $umzugA['id']]) ?></code>
              <span style="color:var(--leise)"> — nur jetzt sichtbar; nach „KK-Antrag gestellt“ ist er gelöscht.</span></div>
            <?php unset($_SESSION['umzug_code'][(int) $umzugA['id']]); ?>
          <?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <?php foreach ([['umzug_code_zeigen', 'Auth-Code anzeigen', (string) $umzugA['stand'] === 'code_da'],
                            ['umzug_beantragt', 'KK-Antrag gestellt', (string) $umzugA['stand'] === 'code_da'],
                            ['umzug_pruefen', 'DNS und Sperre neu prüfen', in_array((string) $umzugA['stand'], ['code_fehlt', 'code_da'], true)]] as [$uTat, $uWort, $uZeigen]):
                if (!$uZeigen) { continue; } ?>
              <form method="post" action="<?= Fmt::h(url('')) ?>">
                <?= Csrf::feld() ?><input type="hidden" name="tat" value="<?= Fmt::h($uTat) ?>">
                <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
                <input type="hidden" name="id" value="<?= (int) $umzugA['id'] ?>">
                <button class="knopf"><?= Fmt::h($uWort) ?></button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      <?php $uebrig = array_values(array_filter($hostingWuensche,
          static fn(string $w): bool => strtolower($w) !== strtolower((string) $hostingA['domain']))); ?>
      <?php if ($uebrig): ?>
        <p style="color:var(--leise);font-size:12.5px;margin:8px 0 0">
          Weitere Wünsche aus dem Fragebogen: <?= Fmt::h(implode(' · ', $uebrig)) ?></p>
      <?php endif; ?>
      <?php if ((string) $hostingA['status'] === 'zugestimmt'): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px"
              data-frage="Jetzt anlegen, ohne auf die Zahlung zu warten? Der KAS-Account entsteht sofort<?= ($hostingA['mail'] ?? 'vecom') === 'vecom' ? ', dazu das Postfach' : '' ?><?= ($hostingA['domain_aktion'] ?? 'neu') === 'neu' ? ' — und die Domain-Bestellung danach kostet dich Registrierungsgebühr' : '' ?>."
              data-ja="Ja, anlegen">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_anlegen">
          <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
          <input type="hidden" name="id" value="<?= (int) $hostingA['id'] ?>">
          <button class="knopf">Jetzt von Hand anlegen</button>
          <span style="color:var(--leise);font-size:12.5px;margin-left:8px">
            Nur wenn du nicht auf die Automatik warten willst.</span>
        </form>
      <?php endif; ?>
      <?php if ((string) $hostingA['status'] === 'vorgeschlagen'): ?>
        <?php /* Noch nicht beantwortet: Das Angebot darf sich aendern (26.09.2026). */ ?>
        <details style="margin-top:10px"><summary style="font-size:13px;color:var(--dim);cursor:pointer">Andere Domain anbieten …</summary>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:8px">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_vorschlag">
            <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
            <div class="feld"><label>Neue Wunschdomain</label><input name="domain" placeholder="z. B. trattoria-rossi.it" required></div>
            <label style="display:inline-flex;gap:6px;align-items:center;font-size:13px;color:var(--dim);margin:0 0 8px">
              <input type="checkbox" name="selbst_geprueft" value="1" style="width:auto;margin:0">
              Selbst geprüft, sie ist frei (<a href="https://web-whois.nic.it/" target="_blank" rel="noopener">.it bei NIC.it</a>)</label><br>
            <button class="knopf">Prüfen und neu anbieten</button>
          </form></details>
      <?php endif; ?>

    <?php else: ?>
      <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
        Wunschdomain prüfen und dem Kunden anbieten. Er bekommt sofort die Angebots-Mail
        mit dem Link auf seine Seite — zustimmen muss er dort selbst, erst dann entsteht etwas.</p>
      <?php if ($hostingWuensche): ?>
        <div style="margin:0 0 12px">
          <div style="font-size:12.5px;color:var(--leise);margin-bottom:6px">
            Seine Wünsche aus dem Fragebogen — ein Klick prüft und bietet an:</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php foreach ($hostingWuensche as $wunsch): ?>
              <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
                <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_vorschlag">
                <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
                <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                <input type="hidden" name="domain" value="<?= Fmt::h($wunsch) ?>">
                <button class="knopf"><?= Fmt::h($wunsch) ?></button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="hosting_vorschlag">
        <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
        <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
        <div class="feld"><label>Wunschdomain</label>
          <input name="domain" placeholder="z. B. trattoria-rossi.it" required></div>
        <label style="display:inline-flex;gap:6px;align-items:center;font-size:13px;color:var(--dim);margin:0 0 8px">
          <input type="checkbox" name="selbst_geprueft" value="1" style="width:auto;margin:0">
          Selbst geprüft, sie ist frei (<a href="https://web-whois.nic.it/" target="_blank" rel="noopener">.it bei NIC.it</a> oder im Domainbestellsystem)</label><br>
        <button class="knopf">Prüfen und dem Kunden anbieten</button>
        <span style="color:var(--leise);font-size:12.5px;margin-left:8px">
          Vergebene Domains werden nie angeboten. Lässt sich „frei“ nicht automatisch bestätigen (bei .it häufig), zählt dein Haken.</span>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

<?php if (!$eing): ?>
  <?php /* Die eine Adresse des Kunden — dieselbe, die in allen E-Mails steht. */ ?>
  <?php
    require_once __DIR__ . '/../src/Kundenzugang.php';
    $kundenlink = '';
    if (empty($k['anonym_am'])) {
        try { $kundenlink = Kundenzugang::linkFuer((int) $k['id']); } catch (Throwable $e) { $kundenlink = ''; }
    }
  ?>
  <?php if ($kundenlink !== ''): ?>
  <div class="block"><h2>Seine Seite</h2>
    <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 10px">Eine Adresse, vom ersten Kontakt
      bis lange nach dem Onlinegang. Wer den Link hat, kommt hinein — also nur an ihn.</p>
    <div class="feld">
      <input readonly onclick="this.select()" value="<?= Fmt::h($kundenlink) ?>" style="font-size:12px"></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <?php /* Der Weg fuer den Kunden, den es hier vorher nicht gab: einen, den
               du selbst angelegt hast, weil ihr euch getroffen oder telefoniert
               habt. Er hat nie ein Formular ausgefuellt und kennt seine Adresse
               deshalb nicht. Ein Klick, und der fertige Brief steht unten im
               Schreibfeld — mit dem Link darin. */ ?>
      <a class="knopf haupt" href="<?= Fmt::h(url('kunden/' . (int) $k['id'] . '?vorlage=zugang#schreiben')) ?>">Link schicken</a>
      <a class="knopf" href="<?= Fmt::h($kundenlink) ?>" target="_blank" rel="noopener">Ansehen</a>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
            data-frage="Der alte Link gilt danach nicht mehr. Der Kunde braucht dann den neuen. Fortfahren?" data-ja="Ja, neuen Link">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="kundenlink_neu">
        <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
        <button class="knopf">Neuen Link erzeugen</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
<?php endif; ?>

  <?php if ($k['notes']): ?><div class="block"><h2>Interne Notizen</h2><p style="color:var(--dim);white-space:pre-wrap"><?= Fmt::h($k['notes']) ?></p></div><?php endif; ?>
<?php if (!$eing): ?>
  <div class="block"><h2>Verlauf</h2>
    <?php if (!$aktivitaeten): ?><div class="leer">Noch nichts.</div><?php else: ?><ul class="verlauf">
    <?php foreach ($aktivitaeten as $a): ?><li><span class="punkt"></span><span><?= Fmt::h($a['title']) ?></span>
      <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
<?php endif; ?>

  <?php /* -------------------------------------------------------------------
       Kunde entfernen. Zwei Wege, weil es zwei verschiedene Faelle sind:
       ein Testkunde, den es nie gab, und ein echter Kunde, der sein
       Loeschrecht ausuebt. Der zweite darf die Buchhaltung nicht mitnehmen.
       Beides steckt in einem zugeklappten Bereich — nichts davon soll
       aus Versehen angeklickt werden.
  ------------------------------------------------------------------- */ ?>
  <?php
  /**
   * Der Ausweg fuer den Probelauf — gebraucht an zwei Stellen: wenn das
   * normale Loeschen an einem Beleg scheitert, und bei einem bereits
   * anonymisierten Datensatz, wo er der einzige Weg ist, der noch bleibt.
   * Deshalb einmal geschrieben und zweimal gerufen.
   */
  $testweg = static function () use ($k, $belege) {
    /* Ein Testbeleg ist kein Dokument, das man aufbewahrt, sondern ein
       Fehleintrag — und er blockiert den Nummernkreis: Bleibt BE-2026-0001
       stehen, faengt der erste echte Beleg bei 0002 an, und eine
       italienische Nummerierung muss im Jahr lueckenlos sein. */ ?>
        <details style="border:1px solid rgba(255,138,138,.3);border-radius:10px;padding:10px 12px">
          <summary style="cursor:pointer;font-weight:650;font-size:13.5px;color:var(--rot)">
            Es war nur ein Testlauf — alles weg, auch die Belege</summary>
          <p style="color:var(--leise);font-size:13px;line-height:1.6;margin:10px 0 10px">
            Nur für Vorgänge, die es nie gegeben hat: dein eigener Probelauf. Dann sind die
            Belege unten keine Dokumente, die du aufbewahren musst, sondern Fehleinträge —
            und sie blockieren deinen Nummernkreis. Nach dem Löschen fängt der nächste Beleg
            wieder bei 0001 an.<br>
            <strong style="color:var(--rot)">Hat der Kunde wirklich gezahlt, darfst du das nicht.</strong>
            Dann ist „Anonymisieren" der richtige Weg.</p>
          <?php if ($belege ?? []): ?>
            <div style="font-size:12.5px;color:var(--dim);border:1px solid var(--linie);
                        border-radius:10px;padding:9px 11px;margin-bottom:11px">
              <div style="font-weight:650;margin-bottom:5px">Diese Belege würden vernichtet</div>
              <?php foreach ($belege as $b): ?>
                <div><?= Fmt::h($b['nummer']) ?> · <?= Fmt::geld($b['betrag'], $b['waehrung']) ?>
                  <?= $b['datum'] ? ' · ' . Fmt::h(Fmt::datum($b['datum'])) : '' ?></div>
              <?php endforeach; ?>
              <div style="margin-top:6px;color:var(--leise)">Nummer, Betrag und Datum bleiben
                danach in der Prüfspur stehen — das ist das Einzige, was noch bezeugt, dass es
                sie gab.</div>
            </div>
          <?php endif; ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>"
                style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_loeschen">
            <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
            <input type="hidden" name="auch_belege" value="1">
            <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
            <input name="bestaetigung" required autocomplete="off" placeholder="ALLES LÖSCHEN"
                   aria-label="Zur Bestätigung ALLES LÖSCHEN eingeben"
                   style="max-width:190px;text-transform:uppercase;letter-spacing:.06em">
            <button class="knopf" style="border-color:rgba(255,138,138,.55);color:var(--rot)">
              Testdaten endgültig löschen</button>
          </form>
        </details>
  <?php };   /* Ende des Bausteins — PHP wurde oben fuer das HTML verlassen */ ?>

  <div class="block" style="border-color:rgba(255,138,138,.28)">
    <h2 style="color:var(--rot)">Kunde entfernen</h2>

    <?php if ($anonym ?? false): ?>
      <div class="hinweis schlecht" style="margin:0 0 12px">Dieser Datensatz ist am
        <?= Fmt::h(Fmt::datum((string) $k['anonym_am'])) ?> anonymisiert worden.
        Die personenbezogenen Daten sind weg; Bestellungen, Zahlungen und Belege
        stehen weiter in den Büchern und tragen ihren Empfänger auf dem Dokument.</div>
      <p style="color:var(--leise);font-size:13px;line-height:1.6;margin:0 0 12px">
        Damit bleibt er in der Liste stehen — und das ist bei einem echten Kunden auch
        richtig so. War es dein eigener Probelauf, geht er unten ganz weg.</p>
      <?php $testweg(); ?>

    <?php else: ?>
      <p style="color:var(--leise);font-size:13px;margin:0 0 12px;line-height:1.6">
        Zwei Wege, und sie sind nicht dasselbe. <strong style="color:var(--dim)">Löschen</strong>
        ist für Testkunden und Vertipper — alles verschwindet.
        <strong style="color:var(--dim)">Anonymisieren</strong> ist für den echten Kunden,
        der die Löschung seiner Daten verlangt: Der Mensch verschwindet, die Buchhaltung bleibt.
        Beides lässt sich nicht rückgängig machen.</p>

      <?php if ($umfang ?? []): ?>
        <div style="font-size:12.5px;color:var(--leise);border:1px solid var(--linie);
                    border-radius:10px;padding:10px 12px;margin-bottom:12px">
          <div style="font-weight:650;color:var(--dim);margin-bottom:6px">An diesem Kunden hängen</div>
          <?= Fmt::h(Kunde::umfangText($umfang)) ?>
        </div>
      <?php endif; ?>

      <?php /* --- Weg 1 --- */ ?>
      <?php if ($riegel ?? []): ?>
        <div class="hinweis schlecht" style="margin-bottom:12px">
          <strong>Löschen ist hier gesperrt.</strong>
          <ul style="margin:7px 0 0;padding-left:18px">
            <?php foreach ($riegel as $grund): ?><li style="margin-bottom:4px"><?= Fmt::h($grund) ?></li><?php endforeach; ?>
          </ul>
          <div style="margin-top:8px">Nimm den Weg darunter — er entfernt die
            personenbezogenen Daten und lässt die Belege stehen.</div>
        </div>
        <?php $testweg(); ?>

      <?php else: ?>
        <details style="border:1px solid var(--linie);border-radius:10px;padding:10px 12px;margin-bottom:10px">
          <summary style="cursor:pointer;font-weight:650;font-size:13.5px">Vollständig löschen</summary>
          <p style="color:var(--leise);font-size:13px;line-height:1.6;margin:10px 0 12px">
            Der Kunde verschwindet mit allem, was oben aufgezählt ist — samt hochgeladener
            Dateien. Es gibt keine Rechnung und keine eingegangene Zahlung, die dem im Weg
            stünde. Danach ist nichts davon wiederherstellbar.</p>
          <form method="post" action="<?= Fmt::h(url('')) ?>"
                style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_loeschen">
            <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
            <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
            <input name="bestaetigung" required autocomplete="off" placeholder="LÖSCHEN"
                   aria-label="Zur Bestätigung LÖSCHEN eingeben"
                   style="max-width:150px;text-transform:uppercase;letter-spacing:.06em">
            <button class="knopf" style="border-color:rgba(255,138,138,.45);color:var(--rot)">
              Endgültig löschen</button>
          </form>
        </details>
      <?php endif; ?>

      <?php /* --- Weg 2 --- */ ?>
      <details style="border:1px solid var(--linie);border-radius:10px;padding:10px 12px">
        <summary style="cursor:pointer;font-weight:650;font-size:13.5px">Anonymisieren (DSGVO-Auskunft)</summary>
        <p style="color:var(--leise);font-size:13px;line-height:1.6;margin:10px 0 8px">
          <strong style="color:var(--dim)">Weg:</strong> Name, Adresse, Telefon, Steuernummern,
          Nachrichten, Dateien, Fragebogen, Anfragen, der Zugang des Kunden und sein Verlauf.<br>
          <strong style="color:var(--dim)">Bleibt:</strong> Bestellungen, Zahlungen und Belege.
          Jeder Beleg bekommt vorher seinen Empfänger eingefroren, damit er auch danach zeigt,
          an wen er ging — das verlangt die zehnjährige Aufbewahrung (Art. 2220 c.c.), und die
          DSGVO nimmt diesen Fall in Art. 17 Abs. 3 b ausdrücklich vom Löschrecht aus.<br>
          <strong style="color:var(--dim)">Bleibt ebenfalls:</strong> eine eingetragene Website.
          Solange sie online ist, läuft der Vertrag — dann ist Anonymisieren verfrüht.</p>
        <form method="post" action="<?= Fmt::h(url('')) ?>"
              style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_anonymisieren">
          <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
          <input type="hidden" name="zurueck" value="kunden/<?= (int) $k['id'] ?>">
          <input name="bestaetigung" required autocomplete="off" placeholder="ANONYM"
                 aria-label="Zur Bestätigung ANONYM eingeben"
                 style="max-width:150px;text-transform:uppercase;letter-spacing:.06em">
          <button class="knopf" style="border-color:rgba(255,159,90,.45);color:var(--gelb)">
            Daten anonymisieren</button>
        </form>
      </details>
    <?php endif; ?>
  </div>
<?php if (!$eing): ?>
</div></div>
<?php endif; ?>
