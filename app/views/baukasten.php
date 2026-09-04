<?php
/* Der Baukasten: was eine Leistung kostet.

   Eine Seite, ein Speichern-Knopf. Preise pflegt man selten und dann meist
   mehrere auf einmal — je Zeile ein eigenes Formular waere zehnmal klicken
   fuer eine Preisrunde. */
$gruppen = [
    'basis'     => 'Grundlage',
    'seite'     => 'Umfang',
    'sprache'   => 'Sprachen',
    'funktion'  => 'Funktionen',
    'inhalt'    => 'Inhalte',
    'betreuung' => 'Betreuung',
];
$nachGruppe = [];
foreach ($liste as $b) { $nachGruppe[(string) $b['gruppe']][] = $b; }

/** Cent als Eurobetrag fuers Eingabefeld — ohne Waehrungszeichen. */
$eur = static fn(int $c): string => number_format($c / 100, 2, ',', '');

/* Steht ganz oben, weil auch der Knopf fuer das Ende der Einfuehrungsphase
   davon abhaengt -- und der kommt weiter unten vor dem Preisformular. */
$zu = Baukasten::gesperrt();
?>
<div class="kopf"><h1>Baukasten</h1></div>

<div class="block">
  <p style="color:var(--dim);font-size:14px;line-height:1.7;margin:0">
    Aus diesen Bausteinen entsteht der Preis. Der Kunde sieht sie nie einzeln —
    er beantwortet acht Fragen, und daraus wird eine Spanne.
    <strong>Von</strong> ist die untere Grenze, <strong>bis</strong> die obere.
    Steht bei „bis" nichts, gilt der Preis als sicher und die Position
    verbreitert die Spanne nicht.
  </p>
</div>

<?php if ($phase['laeuft']): ?>
  <div class="block">
    <h2 style="font-size:15px;margin:0 0 6px">Einführungspreise</h2>
    <?php if ($phase['erreicht']): ?>
      <div class="hinweis warnung" style="margin-bottom:14px">
        <strong><?= (int) $phase['zaehler'] ?> von <?= (int) $phase['ziel'] ?> Websites sind voll bezahlt.</strong>
        Die Einführungsphase ist damit vorbei. Unten steht, was die Erhöhung um
        <?= (int) $phase['erhoehung'] ?> % mit jedem Baustein macht — sie passiert erst,
        wenn du sie auslöst. Verschickte Angebote behalten ihren Preis.
      </div>
      <div class="tabellenrahmen"><table>
        <thead><tr><th>Baustein</th><th class="num">Von</th><th class="num">Bis</th></tr></thead>
        <tbody>
        <?php foreach ($phase['vorschau'] as $v): ?>
          <tr>
            <td><?= Fmt::h($v['name']) ?></td>
            <td class="num"><span style="color:var(--leise)"><?= Fmt::geld($v['alt_von']) ?></span>
              &nbsp;→&nbsp;<strong><?= Fmt::geld($v['neu_von']) ?></strong></td>
            <td class="num"><?php if ($v['alt_bis']): ?>
              <span style="color:var(--leise)"><?= Fmt::geld($v['alt_bis']) ?></span>
              &nbsp;→&nbsp;<strong><?= Fmt::geld($v['neu_bis']) ?></strong>
            <?php else: ?>—<?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($zu): ?>
        <p style="color:var(--leise);font-size:12.5px;margin:14px 0 0">
          Anheben geht erst, wenn der Baukasten entsperrt ist — der Knopf steht unten.</p>
      <?php else: ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:14px"
              onsubmit="return confirm('Preise wirklich um <?= (int) $phase['erhoehung'] ?> Prozent anheben? Das lässt sich nur von Hand rückgängig machen.')">
          <?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="preise_anheben">
          <input type="hidden" name="zurueck" value="baukasten">
          <button class="knopf haupt">Preise jetzt um <?= (int) $phase['erhoehung'] ?> % anheben</button>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <p style="color:var(--dim);font-size:14px;line-height:1.7;margin:0">
        <strong><?= (int) $phase['zaehler'] ?> von <?= (int) $phase['ziel'] ?></strong> Websites sind voll bezahlt —
        noch <?= (int) $phase['rest'] ?> Plätze zum Einführungspreis. Auf der Ergebnisseite des
        Konfigurators sieht der Kunde diese Zahl. Ist die Phase vorbei, melde ich mich hier
        mit einer Vorher-Nachher-Liste; angehoben wird erst auf deinen Knopfdruck.
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php /* ---------- Die Sperre ----------
         Hier stehen die Zahlen, aus denen jeder Preis entsteht. Eine Ziffer,
         die beim Durchscrollen verrutscht, aendert nicht eine Zeile, sondern
         jedes kuenftige Angebot -- und faellt nicht auf, weil der neue Preis
         so plausibel aussieht wie der alte. */ ?>
<div class="block" style="border-color:<?= $zu ? 'var(--linie)' : 'var(--cyan)' ?>">
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <div style="flex:1 1 300px;min-width:0">
      <h2 style="font-size:15px;margin:0 0 4px"><?= $zu ? 'Preise sind gesperrt' : 'Preise sind offen' ?></h2>
      <p style="color:var(--leise);font-size:12.5px;line-height:1.55;margin:0">
        <?php if ($zu): ?>
          Zum Ändern einmal entsperren. Nach dem Speichern sperrt es sich von selbst wieder —
          eine Sperre, an die man denken muss, ist nach dem dritten Mal offen und bleibt es.
        <?php else: ?>
          Jetzt lassen sich die Zahlen ändern. Sie gelten für neue Bedarfe;
          was ein Kunde schon gesehen hat, bleibt stehen.
        <?php endif; ?>
      </p>
    </div>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="flex:0 0 auto">
      <?= Csrf::feld() ?>
      <input type="hidden" name="tat" value="baukasten_sperre">
      <input type="hidden" name="zurueck" value="baukasten">
      <input type="hidden" name="zu" value="<?= $zu ? '0' : '1' ?>">
      <button class="knopf <?= $zu ? '' : 'haupt' ?>"><?= $zu ? 'Entsperren' : 'Wieder sperren' ?></button>
    </form>
  </div>
</div>

<form method="post" action="<?= Fmt::h(url('')) ?>">
  <?= Csrf::feld() ?>
  <input type="hidden" name="tat" value="bausteine_speichern">
  <input type="hidden" name="zurueck" value="baukasten">

  <?php foreach ($gruppen as $schluessel => $titel): ?>
    <?php $zeilen = $nachGruppe[$schluessel] ?? []; if (!$zeilen) { continue; } ?>
    <div class="block">
      <h2 style="font-size:15px;margin:0 0 12px"><?= Fmt::h($titel) ?></h2>
      <div class="tabellenrahmen"><table>
        <thead><tr>
          <th>Baustein</th>
          <th class="num" style="width:120px">Von</th>
          <th class="num" style="width:120px">Bis</th>
          <th style="width:150px">Zustand</th>
        </tr></thead>
        <tbody>
        <?php foreach ($zeilen as $b): ?>
          <?php
            $id = (int) $b['id'];
            $aufAnfrage = in_array((string) $b['slug'], Baukasten::NUR_AUF_ANFRAGE, true);
          ?>
          <tr>
            <td>
              <strong><?= Fmt::h(Baukasten::name($b, 'de')) ?></strong>
              <?php if ($b['je_einheit']): ?> <span class="marke2">je Stück</span><?php endif; ?>
              <?php if ($b['monatlich']): ?> <span class="marke2 warnung">monatlich</span><?php endif; ?>
              <?php if ($aufAnfrage): ?> <span class="marke2 warnung">nur auf Anfrage</span><?php endif; ?>
              <div style="color:var(--leise);font-size:12.5px;line-height:1.5;margin-top:3px">
                <?= Fmt::h(Baukasten::text($b, 'de')) ?>
              </div>
              <?php if ($aufAnfrage): ?>
                <div style="color:var(--leise);font-size:12px;margin-top:4px">
                  Wird nie automatisch gerechnet. Erscheint beim Bedarf als Vorschlag.
                </div>
              <?php endif; ?>
            </td>
            <td class="num">
              <input name="von[<?= $id ?>]" value="<?= Fmt::h($eur((int) $b['preis_cents'])) ?>"
                     inputmode="decimal" style="width:100%;text-align:right" aria-label="Von-Preis"
                     <?= $zu ? 'disabled' : '' ?>>
            </td>
            <td class="num">
              <input name="bis[<?= $id ?>]"
                     value="<?= (int) $b['preis_bis_cents'] ? Fmt::h($eur((int) $b['preis_bis_cents'])) : '' ?>"
                     inputmode="decimal" placeholder="—" style="width:100%;text-align:right" aria-label="Bis-Preis"
                     <?= $zu ? 'disabled' : '' ?>>
            </td>
            <td>
              <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                <input type="checkbox" name="aktiv[<?= $id ?>]" value="1"<?= $b['aktiv'] ? ' checked' : '' ?><?= $zu ? ' disabled' : '' ?>>
                <span>wird verwendet</span>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; ?>

  <?php if (!$zu): ?>
    <div class="block">
      <button class="knopf haupt">Preise speichern</button>
      <span style="color:var(--leise);font-size:12.5px;margin-left:12px">
        Änderungen gelten für neue Bedarfe. Was ein Kunde schon gesehen hat, bleibt stehen.
        Danach ist der Baukasten wieder zu.
      </span>
    </div>
  <?php endif; ?>
</form>
