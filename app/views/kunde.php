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
          <?php if (!empty($m['betreff'])): ?>
            <div style="font-size:12.5px;color:var(--cyan);margin-bottom:5px"><?= Fmt::h((string) $m['betreff']) ?></div>
          <?php endif; ?>
          <div style="white-space:pre-wrap;font-size:14px;line-height:1.55;color:var(--dim)"><?= Fmt::h((string) $m['body']) ?></div>
        </div>
      <?php endforeach; ?></div>
    <?php endif; ?>
    <?php
      $nfTat = 'kunde_nachricht'; $nfId = (int) $k['id'];
      $nfKennung = (string) ($kennung ?? ''); $nfVorlagen = (array) ($vorlagen ?? []);
      $nfVorname = explode(' ', (string) $k['name'])[0]; $nfZurueck = '';
      require __DIR__ . '/nachrichtfeld.php';
    ?>
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
      <a class="knopf" href="<?= Fmt::h($kundenlink) ?>" target="_blank" rel="noopener">Ansehen</a>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
            onsubmit="return confirm('Der alte Link gilt danach nicht mehr. Der Kunde braucht dann den neuen. Fortfahren?')">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="kundenlink_neu">
        <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
        <button class="knopf">Neuen Link erzeugen</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

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
          <button class="knopf" style="border-color:rgba(251,191,36,.45);color:var(--gelb)">
            Daten anonymisieren</button>
        </form>
      </details>
    <?php endif; ?>
  </div>
</div></div>
