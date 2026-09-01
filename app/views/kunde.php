<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('kunden')) ?>">Kunden</a></div>
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
  <div class="block"><h2>Nachricht an den Kunden</h2>
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
          <div style="white-space:pre-wrap;font-size:14px;line-height:1.55;color:var(--dim)"><?= Fmt::h((string) $m['body']) ?></div>
        </div>
      <?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_nachricht">
      <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
      <div class="feld"><textarea name="text" rows="5" required
        placeholder="Hallo <?= Fmt::h(explode(' ', (string) $k['name'])[0]) ?>, …"></textarea></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <button class="knopf haupt">Senden</button>
        <?php foreach ($vorlagen as $titel => $inhalt): ?>
          <button type="button" class="knopf" onclick="var t=this.closest('form').querySelector('textarea');t.value=<?= Fmt::h(json_encode($inhalt, JSON_UNESCAPED_UNICODE)) ?>;t.focus()"><?= Fmt::h($titel) ?></button>
        <?php endforeach; ?>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <div class="block"><h2>Dateien</h2>
    <?php if (!$dateien): ?><div class="leer">Noch nichts.</div><?php else: ?>
      <?php foreach ($dateien as $d): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 0;border-top:1px solid var(--linie)">
          <span><?= Fmt::h($d['orig_name']) ?><br><small style="color:var(--leise)">
            <?= Fmt::h(Fmt::bytes((int) $d['size_bytes'])) ?> · <?= $d['uploaded_by'] === 'kunde' ? 'vom Kunden' : 'von dir' ?>
            · <?= Fmt::h(Fmt::datum($d['created_at'])) ?></small></span>
          <a class="knopf" href="<?= Fmt::h(url('dateien/' . (int) $d['id'])) ?>">Herunterladen</a>
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
  <div class="block"><h2>Kontakt</h2><table><tbody>
    <tr><td>E-Mail</td><td><?= Fmt::h($k['email']) ?></td></tr>
    <tr><td>Telefon</td><td><?= Fmt::h($k['phone'] ?: '—') ?></td></tr>
    <tr><td>Firma</td><td><?= Fmt::h($k['company'] ?: '—') ?></td></tr>
    <tr><td>Branche</td><td><?= Fmt::h($k['industry'] ?: '—') ?></td></tr>
    <tr><td>Adresse</td><td><?= Fmt::h(trim(($k['street'] ?? '') . ' ' . ($k['zip'] ?? '') . ' ' . ($k['city'] ?? '') . ' ' . ($k['country'] ?? ''))) ?: '—' ?></td></tr>
    <tr><td>Sprache</td><td><?= Fmt::h(['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'][strtolower((string) ($k['sprache'] ?? 'it'))] ?? 'Italiano') ?>
      <small style="color:var(--leise)">— so gehen die automatischen E-Mails raus</small></td></tr>
    <tr><td>Kunde seit</td><td><?= Fmt::h(Fmt::datum($k['created_at'])) ?></td></tr>
  </tbody></table></div>
  <?php if ($k['notes']): ?><div class="block"><h2>Interne Notizen</h2><p style="color:var(--dim);white-space:pre-wrap"><?= Fmt::h($k['notes']) ?></p></div><?php endif; ?>
  <div class="block"><h2>Verlauf</h2>
    <?php if (!$aktivitaeten): ?><div class="leer">Noch nichts.</div><?php else: ?><ul class="verlauf">
    <?php foreach ($aktivitaeten as $a): ?><li><span class="punkt"></span><span><?= Fmt::h($a['title']) ?></span>
      <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>

  <?php /* -------------------------------------------------------------------
       Kunde entfernen. Zwei Wege, weil es zwei verschiedene Faelle sind:
       ein Testkunde, den es nie gab, und ein echter Kunde, der sein
       Loeschrecht ausuebt. Der zweite darf die Buchhaltung nicht mitnehmen.
       Beides steckt in einem zugeklappten Bereich — nichts davon soll
       aus Versehen angeklickt werden.
  ------------------------------------------------------------------- */ ?>
  <div class="block" style="border-color:rgba(255,138,138,.28)">
    <h2 style="color:var(--rot)">Kunde entfernen</h2>

    <?php if ($anonym ?? false): ?>
      <div class="hinweis schlecht" style="margin:0">Dieser Datensatz ist am
        <?= Fmt::h(Fmt::datum((string) $k['anonym_am'])) ?> anonymisiert worden.
        Die personenbezogenen Daten sind weg; Bestellungen, Zahlungen und Belege
        stehen weiter in den Büchern und tragen ihren Empfänger auf dem Dokument.</div>

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
          <button class="knopf" style="border-color:rgba(251,191,36,.45);color:var(--gelb)">
            Daten anonymisieren</button>
        </form>
      </details>
    <?php endif; ?>
  </div>
</div></div>
