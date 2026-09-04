<div class="kopf"><div><h1><?= Fmt::h($a['name']) ?></h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Anfrage vom <?= Fmt::h(Fmt::datum($a['created_at'])) ?>
  <?php if ($a['customer_id']): ?> · <a href="<?= Fmt::h(url('kunden/' . (int) $a['customer_id'])) ?>">Kundenakte</a><?php endif; ?></p></div>
  <a class="knopf" href="<?= Fmt::h(url('anfragen')) ?>">Zurück</a>
</div>

<div class="block">
  <h2>Kontakt</h2>
  <div class="zeile"><span>E-Mail</span><b><a href="mailto:<?= Fmt::h($a['email']) ?>"><?= Fmt::h($a['email']) ?></a></b></div>
  <?php if ($a['telefon']): ?><div class="zeile"><span>Telefon</span><b><?= Fmt::h($a['telefon']) ?></b></div><?php endif; ?>
  <?php if ($a['website']): ?><div class="zeile"><span>Bestehende Seite</span><b><?= Fmt::h($a['website']) ?></b></div><?php endif; ?>
  <div class="zeile"><span>Sprache</span><b><?= Fmt::h(strtoupper((string) $a['sprache'])) ?></b></div>
  <?php if ($a['paket_name']): ?><div class="zeile"><span>Gewähltes Paket</span><b><?= Fmt::h($a['paket_name']) ?></b></div><?php endif; ?>
</div>

<div class="block">
  <h2>Seine private Seite</h2>
  <p style="color:var(--leise);font-size:13px;margin:0 0 10px">Dieselbe Adresse, die in der Eingangsbestätigung
  steht. Dort sieht er seine Anfrage, kann schreiben und Unterlagen hochladen. Kein Konto, kein Passwort —
  wer den Link hat, kommt hinein. Also nur an ihn weitergeben.</p>
  <?php $vorgang = sicher(static fn() => $a['token'] ? Anfrage::link((string) $a['token']) : '', ''); ?>
  <?php if ($vorgang): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="text" readonly value="<?= Fmt::h($vorgang) ?>" onclick="this.select()"
             style="flex:1;min-width:280px;font-size:13px">
      <a class="knopf" href="<?= Fmt::h($vorgang) ?>" target="_blank" rel="noopener">Ansehen</a>
    </div>
  <?php else: ?>
    <p style="color:var(--leise);font-size:13px;margin:0">Für diese Anfrage gibt es keinen Zugang.</p>
  <?php endif; ?>
</div>

<?php if ($bedarf): ?>
  <?php /* Aus dem Konfigurator. Die gespeicherte Nachricht steht in der
           Sprache des Kunden, weil er sie auf seiner Seite liest — hier wird
           sie deshalb frisch auf Deutsch aus den Antworten gebaut. */ ?>
  <div class="block">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap">
      <h2 style="margin:0">Was im Konfigurator angekreuzt wurde</h2>
      <a class="knopf" href="<?= Fmt::h(url('bedarf/' . (int) $bedarf['id'])) ?>">Zum Bedarf</a>
    </div>
    <table style="margin-top:12px"><tbody>
    <?php foreach (Baukasten::FRAGEN as $schluessel => $frage): ?>
      <?php
        $wert = $bAntworten[$schluessel] ?? null;
        if ($wert === null || $wert === '' || $wert === []) { continue; }
        $lesbar = [];
        foreach ((is_array($wert) ? $wert : [$wert]) as $w) {
            $o = $frage['optionen'][$w] ?? null;
            if ($o) { $lesbar[] = Texte::h($o, 'de'); }
        }
      ?>
      <tr>
        <td style="width:42%"><?= Fmt::h(Texte::h($frage['frage'], 'de')) ?></td>
        <td><?= Fmt::h(implode(', ', $lesbar)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>

  <?php if ($bVorschlag && (int) $bVorschlag['summe_cents'] > 0): ?>
    <div class="block">
      <h2>Was du nennen kannst</h2>
      <p style="font-size:26px;font-weight:600;margin:8px 0 4px;letter-spacing:-.01em">
        <?= Fmt::geld((int) $bVorschlag['summe_cents']) ?>
        <?php if ((int) $bVorschlag['monatlich_cents'] > 0): ?>
          <span style="font-size:15px;font-weight:400;color:var(--dim)">
            + <?= Fmt::geld((int) $bVorschlag['monatlich_cents']) ?> im Monat</span>
        <?php endif; ?>
      </p>
      <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0">
        Errechnet aus den Antworten, gerundet wie im Angebot. Die fertige Nachricht
        an den Kunden — in seiner Sprache, mit dieser Zahl und den Posten dazu —
        steht beim Bedarf und muss nur noch abgeschickt werden.
      </p>
    </div>
  <?php endif; ?>
<?php elseif (trim((string) $a['nachricht']) !== ''): ?>
  <div class="block">
    <h2>Was geschrieben wurde</h2>
    <pre style="white-space:pre-wrap;font:inherit;color:var(--dim);line-height:1.6;margin:0"><?= Fmt::h((string) $a['nachricht']) ?></pre>
  </div>
<?php endif; ?>

<?php if ($a['order_id']): ?>
  <div class="block">
    <div class="hinweis gut">Aus dieser Anfrage ist eine Bestellung geworden.
      <a href="<?= Fmt::h(url('bestellungen/' . (int) $a['order_id'])) ?>">Bestellung öffnen</a></div>
  </div>
<?php else: ?>
  <?php /* ====================================================================
       WAS HIER FRUEHER STAND, UND WARUM ES WEG IST

       Bis hierher war der einzige Weg von einer Anfrage weiter: "Paket
       waehlen und Bestellung anlegen". Das stammt aus der Zeit der drei
       Preiskarten. Heute entsteht der Preis im Konfigurator, und daraus wird
       das Angebot — feste Pakete sind die Ausnahme, nicht die Regel.

       Die Auswahlliste war damit eine Aufgabe ohne Loesung: Wer sie aufmachte,
       fand nichts, was zu dieser Anfrage passt. Deshalb steht der Weg, der
       wirklich weiterfuehrt, jetzt oben, und die Bestellung darunter — und
       nur dann, wenn es ueberhaupt ein Paket mit Preis gibt.
       ================================================================= */ ?>
  <div class="block">
    <h2>Wie es weitergeht</h2>
    <p style="color:var(--dim);font-size:13.5px;line-height:1.7;margin:0 0 12px">
      Der Kunde hat geschrieben, aber noch nicht gesagt, was er braucht.
      Der Konfigurator fragt das in acht Schritten ab und rechnet die Spanne —
      danach steht der Preis, und daraus wird das Angebot. Die Einladung dazu
      steht fertig da, in seiner Sprache.
    </p>
    <?php if ($a['customer_id']): ?>
      <a class="knopf haupt"
         href="<?= Fmt::h(url('kunden/' . (int) $a['customer_id'] . '?vorlage=bedarf_einladen&tun=kunde_nachricht')) ?>">
        Konfigurator schicken &rsaquo;</a>
      <a class="knopf" href="<?= Fmt::h(url('kunden/' . (int) $a['customer_id'])) ?>">Kundenakte</a>
    <?php else: ?>
      <div class="hinweis">Zu dieser Anfrage gibt es keine Kundenakte — sie kam nicht über das
        Formular. Leg den Kunden an, dann steht der Weg offen.</div>
    <?php endif; ?>
  </div>

  <?php if ($pakete): ?>
    <div class="block">
      <h2>Oder direkt ein Festpreis-Paket</h2>
      <p style="color:var(--leise);font-size:13px;line-height:1.65">
        Nur, wenn ihr euch schon auf ein Paket geeinigt habt — sonst führt der Weg oben weiter.
        Beim Anlegen entsteht die Anzahlung automatisch; den Zahlungslink erzeugst du danach
        in der Bestellung.</p>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="anfrage_bestellung">
        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
        <select name="paket_id" required style="min-width:220px">
          <option value="">Paket wählen …</option>
          <?php foreach ($pakete as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $a['package_id'] ? 'selected' : '' ?>>
              <?= Fmt::h($p['name']) ?> — <?= Fmt::h(Fmt::geld((int) $p['price_cents'], (string) $p['currency'])) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="knopf">Bestellung anlegen</button>
      </form>
    </div>
  <?php endif; ?>

  <div class="block">
    <h2>Stand</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;flex-wrap:wrap">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="anfrage_status">
      <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
      <?php foreach (['neu' => 'Neu', 'in_arbeit' => 'In Arbeit', 'erledigt' => 'Erledigt'] as $wert => $wort): ?>
        <button class="knopf <?= $a['status'] === $wert ? 'haupt' : '' ?>" name="status" value="<?= $wert ?>"><?= $wort ?></button>
      <?php endforeach; ?>
    </form>
  </div>
<?php endif; ?>
