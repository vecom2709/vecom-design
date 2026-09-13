<?php
/**
 * Damit alles läuft.
 *
 * Eine Seite, die man einmal im Monat aufmacht und nach zwanzig Sekunden
 * wieder zu. Deshalb steht oben ein Satz, der die Frage beantwortet, wegen
 * der man gekommen ist — und nicht eine Zahlenreihe, die man erst lesen muss.
 */
$bilanz = Bereit::bilanz($punkte);
$fehler  = (int) ($bilanz[Bereit::FEHLER] ?? 0);
$warnung = (int) ($bilanz[Bereit::WARNUNG] ?? 0);

$farbe = static fn(string $stand): string => match ($stand) {
    Bereit::GUT     => 'var(--gruen)',
    Bereit::WARNUNG => 'var(--gelb)',
    default         => 'var(--rot)',
};
$zeichen = static fn(string $stand): string => match ($stand) {
    Bereit::GUT     => '✓',
    Bereit::WARNUNG => '!',
    default         => '×',
};
?>

<div class="kopf">
  <div><h1>Damit alles läuft</h1>
    <p style="color:var(--leise);font-size:13px;margin-top:6px;max-width:62ch">
      Zehn Dinge, die eingerichtet sein müssen, damit die Verwaltung von allein arbeitet.
      Jede Zeile misst etwas, das wirklich in den Daten steht — kein Haken bedeutet hier
      nur „ist eingetragen".</p></div>
</div>

<?php /* ---------- Der eine Satz ----------
         Wer hierherkommt, will eine Antwort, keine Tabelle. Steht alles,
         steht das als Erstes da und man kann die Seite zumachen. */ ?>
<div class="bereit__satz <?= $fehler > 0 ? 'schlimm' : ($warnung > 0 ? 'lau' : 'gut') ?>">
  <?php if ($fehler > 0): ?>
    <b><?= $fehler ?> <?= $fehler === 1 ? 'Sache hält' : 'Sachen halten' ?> die Verwaltung auf.</b>
    <span>Sie stehen oben, mit dem Weg dorthin.</span>
  <?php elseif ($warnung > 0): ?>
    <b>Es läuft.</b>
    <span><?= $warnung ?> <?= $warnung === 1 ? 'Punkt ist' : 'Punkte sind' ?> einen Blick wert,
      aber nichts davon hält etwas auf.</span>
  <?php else: ?>
    <b>Alles steht.</b>
    <span>Nichts fehlt und nichts hängt. Du kannst die Seite wieder zumachen.</span>
  <?php endif; ?>
</div>

<div class="block" style="padding:0">
  <?php foreach ($punkte as $p): ?>
    <div class="bereit__zeile">
      <span class="bereit__marke" style="color:<?= $farbe($p['stand']) ?>;
            border-color:<?= $farbe($p['stand']) ?>"><?= $zeichen($p['stand']) ?></span>
      <div class="bereit__mitte">
        <div class="bereit__was"><?= Fmt::h((string) $p['was']) ?></div>
        <div class="bereit__text" style="color:<?= $p['stand'] === Bereit::GUT
            ? 'var(--leise)' : $farbe($p['stand']) ?>"><?= Fmt::h((string) $p['text']) ?></div>
        <?php if (trim((string) $p['warum']) !== ''): ?>
          <div class="bereit__warum"><?= Fmt::h((string) $p['warum']) ?></div>
        <?php endif; ?>
      </div>
      <div class="bereit__tun">
        <?php if (!empty($p['ziel'])): ?>
          <a class="knopf<?= $p['stand'] === Bereit::FEHLER ? ' haupt' : '' ?>"
             href="<?= Fmt::h(url((string) $p['ziel'])) ?>"><?= Fmt::h((string) ($p['wohin'] ?: 'ansehen')) ?></a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<p style="color:var(--leise);font-size:12.5px;max-width:70ch">
  Diese Seite ändert nichts von selbst. Sie sieht nur nach — jedes Mal neu, wenn du sie
  aufmachst.
</p>
