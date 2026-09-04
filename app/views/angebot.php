<?php
/* Ein Angebot bearbeiten.

   Solange es ein Entwurf ist, laesst sich alles aendern. Ab dem Verschicken
   ist es zu — was beim Kunden liegt, darf sich nicht hinter seinem Ruecken
   bewegen. Die Seite zeigt beide Zustaende, damit man nicht raetselt, warum
   ein Feld fehlt. */
$aenderbar = Angebot::aenderbar($a);
$stufen = [
    'entwurf'        => ['',        'Entwurf'],
    'gesendet'       => ['warnung', 'beim Kunden'],
    'angenommen'     => ['gut',     'angenommen'],
    'abgelehnt'      => ['',        'abgelehnt'],
    'abgelaufen'     => ['',        'abgelaufen'],
    'zurueckgezogen' => ['',        'zurückgezogen'],
];
/* Eine Neufassung lohnt sich nur, wo das Angebot beim Kunden lag oder liegt.
   Ein Entwurf ist ohnehin aenderbar, ein angenommenes darf nicht mehr wandern. */
$neufassbar = in_array((string) $a['status'], ['gesendet', 'abgelehnt', 'abgelaufen'], true)
           && ($a['ersetzt_durch'] ?? null) === null;
[$farbe, $wort] = $stufen[(string) $a['status']] ?? ['', (string) $a['status']];
$eur = static fn(int $c): string => number_format($c / 100, 2, ',', '');
$anzahlung = (int) round((int) $a['summe_cents'] * (int) $a['anzahlung_prozent'] / 100);
?>
<div class="kopf">
  <h1><?= Fmt::h((string) $a['nummer']) ?>
    <?php if ((int) ($a['fassung'] ?? 1) > 1): ?>
      <span class="marke2">Fassung <?= (int) $a['fassung'] ?></span>
    <?php endif; ?>
    <span class="marke2 <?= Fmt::h($farbe) ?>"><?= Fmt::h($wort) ?></span></h1>
  <div class="rechts">
    <?php if (($a['vorgaenger_id'] ?? null) !== null): ?>
      <a class="knopf" href="<?= Fmt::h(url('angebote/' . (int) $a['vorgaenger_id'])) ?>">Vorige Fassung</a>
    <?php endif; ?>
    <?php if (($a['ersetzt_durch'] ?? null) !== null): ?>
      <a class="knopf" href="<?= Fmt::h(url('angebote/' . (int) $a['ersetzt_durch'])) ?>">Neue Fassung</a>
    <?php endif; ?>
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
              data-frage="Angebot verschicken? Danach ist es festgeschrieben." data-ja="Ja, verschicken">
          <?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="angebot_senden">
          <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <input type="hidden" name="zurueck" value="angebote/<?= (int) $a['id'] ?>">
          <button class="knopf haupt"<?= ((int) $a['summe_cents'] <= 0 && (int) $a['monatlich_cents'] <= 0) ? ' disabled' : '' ?>>
            Angebot verschicken</button>
        </form>
      </div>
    <?php else: ?>
      <?php $wunsch = Angebot::wunsch($a); ?>
      <?php if ($wunsch !== null): ?>
        <?php /* Was der Kunde sich auf seiner Seite zusammengestellt hat.
                 Ein Vorschlag, kein Angebot -- deshalb steht er hier neben
                 dem Angebot und nicht darin. */ ?>
        <div class="block" data-tun="angebot_wunsch" style="border-left:3px solid var(--cyan)">
          <h2 style="font-size:15px;margin:0 0 6px">Sein Gegenvorschlag</h2>
          <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
            Eingegangen <?= Fmt::h(Fmt::seit((string) $a['wunsch_am'])) ?><?php
              if ((int) $a['wunsch_runden'] > 1): ?> · <?= (int) $a['wunsch_runden'] ?>. Runde<?php endif; ?>.
            Ein Klick macht daraus die neue Fassung — mit genau diesen Posten, du liest drüber und schickst.
          </p>
          <table style="width:100%;font-size:13.5px">
            <?php foreach ($wunsch['positionen'] as $wp): ?>
              <tr>
                <td><?= Fmt::h((string) $wp['bezeichnung']) ?>
                  <?php if ((int) $wp['menge'] > 1): ?><span style="color:var(--leise)">× <?= (int) $wp['menge'] ?></span><?php endif; ?>
                  <?php if (!empty($wp['neu'])): ?><span class="marke2 gut">neu</span><?php endif; ?></td>
                <td class="num"><?= Fmt::h(Fmt::geld((int) $wp['summe_cents'])) ?><?php
                  if ((int) $wp['monatlich']): ?>/Mon.<?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            <?php foreach ((array) ($wunsch['auf_anfrage'] ?? []) as $offenerPosten): ?>
              <tr><td><?= Fmt::h((string) $offenerPosten) ?></td>
                  <td class="num" style="color:var(--leise)">auf Anfrage</td></tr>
            <?php endforeach; ?>
            <tr><td><b>Seine Summe</b></td>
                <td class="num"><b><?= Fmt::h(Fmt::geld((int) $wunsch['summe_cents'])) ?></b>
                  <span style="color:var(--leise)">statt <?= Fmt::h($eur($a['summe_cents']) . ' €') ?></span></td></tr>
          </table>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"
                data-frage="Neue Fassung mit seinen Posten anlegen? Dieses Angebot wird zurückgezogen." data-ja="Ja, neue Fassung">
            <?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="angebot_neufassung">
            <input type="hidden" name="aus_wunsch" value="1">
            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <button class="knopf haupt">Fassung daraus machen</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ((string) $a['status'] === 'gesendet'): ?>
        <div class="block" data-tun="angebot_zusage">
          <h2 style="font-size:15px;margin:0 0 10px">Er hat zugesagt</h2>
          <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
            Für die Zusage am Telefon. Über seinen Link geht es von selbst — kommt sie
            mündlich, klick hier: Daraus entsteht die Bestellung mit
            <b><?= Fmt::h($eur($a['summe_cents']) . ' €') ?></b> und den Posten von oben,
            dazu die Anzahlung über <?= Fmt::h($eur($anzahlung)) ?> €. Nichts davon musst du abtippen.
          </p>
          <form method="post" action="<?= Fmt::h(url('')) ?>"
                data-frage="Zusage vermerken und Bestellung anlegen?" data-ja="Ja, Zusage vermerken">
            <?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="angebot_zusage">
            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <button class="knopf haupt">Zusage vermerken</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($neufassbar): ?>
        <div class="block" data-tun="angebot_neufassung">
          <h2 style="font-size:15px;margin:0 0 10px">Der Kunde will etwas anders</h2>
          <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
            Eine Seite mehr, die Speisekarte doch nicht: Dieses Blatt bleibt, wie es ist —
            der Kunde hat es gelesen. Stattdessen entsteht eine zweite Fassung als Entwurf,
            mit allen Posten von hier drin. Du änderst nur das eine und schickst sie.
            Dieses Angebot wird dabei zurückgezogen: Sein Link zeigt weiter das alte Blatt,
            nimmt aber keine Zusage mehr an — sonst wären zwei gültig und keiner wüsste welches.
          </p>
          <form method="post" action="<?= Fmt::h(url('')) ?>"
                data-frage="Neue Fassung anlegen? Dieses Angebot wird damit zurückgezogen." data-ja="Ja, neue Fassung">
            <?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="angebot_neufassung">
            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <button class="knopf haupt">Angebot ändern</button>
          </form>
        </div>
      <?php endif; ?>

      <div class="block" data-tun="angebot_link">
        <h2 style="font-size:15px;margin:0 0 10px">Link für den Kunden</h2>
        <input readonly value="<?= Fmt::h(Angebot::link($a)) ?>"
               style="width:100%;font-size:12.5px" onclick="this.select()">
        <p style="color:var(--leise);font-size:12.5px;margin:10px 0 0">
          Zum Kopieren anklicken. Wer den Link hat, sieht das Angebot — kein Konto nötig.
        </p>
        <a class="knopf" style="margin-top:12px"
           href="<?= Fmt::h(Angebot::link($a)) ?>&amp;pdf=1">PDF ansehen</a>

        <?php if ((string) $a['status'] === 'gesendet'): ?>
          <?php /* Verschicken heisst hier bisher nur: festschreiben. Der Kunde
                   erfaehrt davon nichts, solange ihm niemand den Link schickt --
                   deshalb steht das Schreibfeld gleich hier, mit der passenden
                   Vorlage vorgewaehlt. */ ?>
          <hr style="border:0;border-top:1px solid var(--linie);margin:16px 0">
          <h2 style="font-size:15px;margin:0 0 4px">Dem Kunden schicken</h2>
          <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 4px">
            Der Link kommt nicht von allein an. Die Vorlage steht schon drin —
            lies drüber und sende.
          </p>
          <?php
            $nfTat      = 'kunde_nachricht';
            $nfId       = (int) $a['customer_id'];
            $nfKennung  = $kennung ?? '';
            $nfVorlagen = $vorlagen ?? [];
            $nfVorname  = explode(' ', trim((string) ($a['kunde'] ?? '')))[0] ?? '';
            $nfZurueck  = 'angebote/' . (int) $a['id'];
            $nfVorwahl  = ($a['vorgaenger_id'] ?? null) !== null ? 'angebot_neufassung' : 'angebot_link';
            $nfBetreff  = '';
            $nfText     = '';
            include __DIR__ . '/nachrichtfeld.php';
          ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
