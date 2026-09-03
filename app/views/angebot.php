<?php
/* Ein Angebot bearbeiten.

   Solange es ein Entwurf ist, laesst sich alles aendern. Ab dem Verschicken
   ist es zu — was beim Kunden liegt, darf sich nicht hinter seinem Ruecken
   bewegen. Die Seite zeigt beide Zustaende, damit man nicht raetselt, warum
   ein Feld fehlt. */
$aenderbar = Angebot::aenderbar($a);
$stufen = [
    'entwurf'    => ['',        'Entwurf'],
    'gesendet'   => ['warnung', 'beim Kunden'],
    'angenommen' => ['gut',     'angenommen'],
    'abgelehnt'  => ['',        'abgelehnt'],
    'abgelaufen' => ['',        'abgelaufen'],
];
[$farbe, $wort] = $stufen[(string) $a['status']] ?? ['', (string) $a['status']];
$eur = static fn(int $c): string => number_format($c / 100, 2, ',', '');
$anzahlung = (int) round((int) $a['summe_cents'] * (int) $a['anzahlung_prozent'] / 100);
?>
<div class="kopf">
  <h1><?= Fmt::h((string) $a['nummer']) ?> <span class="marke2 <?= Fmt::h($farbe) ?>"><?= Fmt::h($wort) ?></span></h1>
  <div class="rechts">
    <a class="knopf" href="<?= Fmt::h(url('kunden/' . $a['customer_id'])) ?>">Zum Kunden</a>
    <?php if ($a['bedarf_id']): ?>
      <a class="knopf" href="<?= Fmt::h(url('bedarf/' . $a['bedarf_id'])) ?>">Zum Bedarf</a>
    <?php endif; ?>
    <?php if ($a['order_id']): ?>
      <a class="knopf" href="<?= Fmt::h(url('bestellungen/' . $a['order_id'])) ?>">Zur Bestellung</a>
    <?php endif; ?>
  </div>
</div>

<div class="zwei">
  <div>
    <div class="block">
      <h2 style="font-size:15px;margin:0 0 12px">Positionen</h2>

      <?php if (!$positionen): ?>
        <div class="leer">Noch keine Position. Unten kannst du welche hinzufügen.</div>
      <?php elseif ($aenderbar): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>">
          <?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="angebot_zeilen">
          <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <input type="hidden" name="zurueck" value="angebote/<?= (int) $a['id'] ?>">
          <div class="tabellenrahmen"><table>
            <thead><tr>
              <th>Leistung</th>
              <th class="num" style="width:80px">Menge</th>
              <th class="num" style="width:120px">Einzeln</th>
              <th class="num" style="width:110px">Summe</th>
              <th style="width:60px"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($positionen as $p): ?>
              <tr>
                <td><strong><?= Fmt::h((string) $p['bezeichnung']) ?></strong>
                  <?php if ((int) $p['monatlich']): ?> <span class="marke2 warnung">monatlich</span><?php endif; ?>
                  <?php if (trim((string) $p['beschreibung']) !== ''): ?>
                    <div style="color:var(--leise);font-size:12.5px;line-height:1.5;margin-top:3px">
                      <?= Fmt::h((string) $p['beschreibung']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="num"><input name="menge[<?= (int) $p['id'] ?>]" value="<?= (int) $p['menge'] ?>"
                       inputmode="numeric" style="width:100%;text-align:right" aria-label="Menge"></td>
                <td class="num"><input name="preis[<?= (int) $p['id'] ?>]" value="<?= Fmt::h($eur((int) $p['einzel_cents'])) ?>"
                       inputmode="decimal" style="width:100%;text-align:right" aria-label="Einzelpreis"></td>
                <td class="num"><?= Fmt::geld((int) $p['summe_cents'], (string) $a['currency']) ?></td>
                <td style="text-align:right">
                  <button class="knopf stumm" name="weg" value="<?= (int) $p['id'] ?>"
                          formnovalidate title="Zeile entfernen">×</button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <div style="margin-top:12px"><button class="knopf haupt">Zeilen speichern</button></div>
        </form>
      <?php else: ?>
        <div class="tabellenrahmen"><table>
          <thead><tr><th>Leistung</th><th class="num">Menge</th><th class="num">Summe</th></tr></thead>
          <tbody>
          <?php foreach ($positionen as $p): ?>
            <tr>
              <td><?= Fmt::h((string) $p['bezeichnung']) ?>
                <?php if ((int) $p['monatlich']): ?> <span class="marke2 warnung">monatlich</span><?php endif; ?></td>
              <td class="num"><?= (int) $p['menge'] ?></td>
              <td class="num"><?= Fmt::geld((int) $p['summe_cents'], (string) $a['currency']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>

    <?php if ($aenderbar): ?>
      <div class="block">
        <h2 style="font-size:15px;margin:0 0 12px">Etwas hinzufügen</h2>
        <form method="post" action="<?= Fmt::h(url('')) ?>" class="reihe" style="gap:8px;margin:0 0 14px">
          <?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="angebot_baustein">
          <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <input type="hidden" name="zurueck" value="angebote/<?= (int) $a['id'] ?>">
          <select name="slug" style="flex:1 1 auto">
            <?php foreach ($katalog as $slug => $b): ?>
              <option value="<?= Fmt::h($slug) ?>"><?= Fmt::h(Baukasten::name($b, 'de')) ?>
                — <?= Fmt::geld((int) $b['preis_cents']) ?><?php
                  if ((int) $b['preis_bis_cents']) { echo ' bis ' . Fmt::geld((int) $b['preis_bis_cents']); } ?></option>
            <?php endforeach; ?>
          </select>
          <input name="menge" value="1" inputmode="numeric" style="width:70px;text-align:right" aria-label="Menge">
          <button class="knopf">Aus dem Baukasten</button>
        </form>

        <form method="post" action="<?= Fmt::h(url('')) ?>" class="reihe" style="gap:8px;margin:0">
          <?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="angebot_freie_zeile">
          <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <input type="hidden" name="zurueck" value="angebote/<?= (int) $a['id'] ?>">
          <input name="bezeichnung" placeholder="Eigene Leistung" style="flex:1 1 auto" required>
          <input name="preis" placeholder="0,00" inputmode="decimal" style="width:110px;text-align:right" aria-label="Preis">
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;white-space:nowrap">
            <input type="checkbox" name="monatlich" value="1"> monatlich</label>
          <button class="knopf">Freie Zeile</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="block">
      <h2 style="font-size:15px;margin:0 0 10px">Summe</h2>
      <p style="font-size:24px;font-weight:600;margin:0 0 2px">
        <?= Fmt::geld((int) $a['summe_cents'], (string) $a['currency']) ?>
      </p>
      <p style="color:var(--leise);font-size:12.5px;margin:0">
        Anzahlung <?= (int) $a['anzahlung_prozent'] ?> % = <?= Fmt::geld($anzahlung, (string) $a['currency']) ?>
      </p>
      <?php if ((int) $a['monatlich_cents'] > 0): ?>
        <p style="color:var(--dim);font-size:14px;margin:10px 0 0">
          Dazu <?= Fmt::geld((int) $a['monatlich_cents'], (string) $a['currency']) ?> im Monat.
        </p>
      <?php endif; ?>
    </div>

    <div class="block">
      <h2 style="font-size:15px;margin:0 0 12px">Eckdaten</h2>
      <table><tbody>
        <tr><td style="width:44%">Kunde</td><td><?= Fmt::h((string) $a['kunde']) ?></td></tr>
        <tr><td>Sprache</td><td><?= Fmt::h(['it'=>'Italienisch','de'=>'Deutsch','en'=>'Englisch'][(string) $a['sprache']] ?? (string) $a['sprache']) ?></td></tr>
        <tr><td>Gültig bis</td><td><?= Fmt::h(Fmt::datum((string) $a['gueltig_bis'])) ?></td></tr>
        <?php if ($a['gesendet_am']): ?>
          <tr><td>Verschickt</td><td><?= Fmt::h(Fmt::datum((string) $a['gesendet_am'])) ?></td></tr>
        <?php endif; ?>
        <?php if ($a['angenommen_am']): ?>
          <tr><td>Angenommen</td><td><?= Fmt::h(Fmt::datum((string) $a['angenommen_am'])) ?></td></tr>
        <?php endif; ?>
        <?php if ($a['abgelehnt_am']): ?>
          <tr><td>Abgelehnt</td><td><?= Fmt::h(Fmt::datum((string) $a['abgelehnt_am'])) ?></td></tr>
        <?php endif; ?>
      </tbody></table>
      <?php if (trim((string) $a['abgelehnt_grund']) !== ''): ?>
        <div class="hinweis" style="margin-top:12px">
          <strong>Begründung:</strong> <?= Fmt::h((string) $a['abgelehnt_grund']) ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($aenderbar): ?>
      <div class="block">
        <h2 style="font-size:15px;margin:0 0 10px">Verschicken</h2>
        <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
          Danach lässt sich nichts mehr ändern — was beim Kunden liegt, darf sich
          nicht hinter seinem Rücken bewegen. Die Frist von 14 Tagen läuft ab jetzt.
        </p>
        <form method="post" action="<?= Fmt::h(url('')) ?>"
              onsubmit="return confirm('Angebot verschicken? Danach ist es festgeschrieben.')">
          <?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="angebot_senden">
          <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <input type="hidden" name="zurueck" value="angebote/<?= (int) $a['id'] ?>">
          <button class="knopf haupt"<?= ((int) $a['summe_cents'] <= 0 && (int) $a['monatlich_cents'] <= 0) ? ' disabled' : '' ?>>
            Angebot verschicken</button>
        </form>
      </div>
    <?php else: ?>
      <div class="block">
        <h2 style="font-size:15px;margin:0 0 10px">Link für den Kunden</h2>
        <input readonly value="<?= Fmt::h(Angebot::link($a)) ?>"
               style="width:100%;font-size:12.5px" onclick="this.select()">
        <p style="color:var(--leise);font-size:12.5px;margin:10px 0 0">
          Zum Kopieren anklicken. Wer den Link hat, sieht das Angebot — kein Konto nötig.
        </p>
        <a class="knopf" style="margin-top:12px"
           href="<?= Fmt::h(Angebot::link($a)) ?>&amp;pdf=1">PDF ansehen</a>
      </div>
    <?php endif; ?>
  </div>
</div>
