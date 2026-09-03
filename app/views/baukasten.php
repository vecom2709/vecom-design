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
                     inputmode="decimal" style="width:100%;text-align:right" aria-label="Von-Preis">
            </td>
            <td class="num">
              <input name="bis[<?= $id ?>]"
                     value="<?= (int) $b['preis_bis_cents'] ? Fmt::h($eur((int) $b['preis_bis_cents'])) : '' ?>"
                     inputmode="decimal" placeholder="—" style="width:100%;text-align:right" aria-label="Bis-Preis">
            </td>
            <td>
              <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                <input type="checkbox" name="aktiv[<?= $id ?>]" value="1"<?= $b['aktiv'] ? ' checked' : '' ?>>
                <span>wird verwendet</span>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; ?>

  <div class="block">
    <button class="knopf haupt">Preise speichern</button>
    <span style="color:var(--leise);font-size:12.5px;margin-left:12px">
      Änderungen gelten für neue Bedarfe. Was ein Kunde schon gesehen hat, bleibt stehen.
    </span>
  </div>
</form>
