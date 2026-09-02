<div class="kopf"><div><h1>Belege fürs Finanzamt</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Ein Jahr, eine Datei: jeder Beleg als PDF, ein Verzeichnis als Tabelle und eine Übersicht.
    Das ist die Grundlage für deinen Commercialista — die elektronische Rechnung über das SdI
    bleibt seine Sache.</p></div>
</div>

<?php if (!$jahre): ?>
  <div class="block"><div class="leer">Noch keine Belege. Sobald der erste ausgestellt ist, steht hier ein Jahr.</div></div>
<?php else: ?>
  <?php foreach ($jahre as $j): ?>
    <?php $z = $uebersicht[$j] ?? null; if (!$z) { continue; } ?>
    <div class="block">
      <h2><?= (int) $j ?><span class="mehr"><?= (int) $z['anzahl'] ?> Belege ·
        <?= Fmt::h(Fmt::geld((int) $z['brutto'], (string) $z['waehrung'])) ?></span></h2>

      <div class="tabellenrahmen"><table><tbody>
        <tr><td style="width:34%">Netto</td><td><?= Fmt::h(Fmt::geld((int) $z['netto'], (string) $z['waehrung'])) ?></td></tr>
        <?php if ((int) $z['steuer'] > 0): ?>
          <tr><td>Steuer</td><td><?= Fmt::h(Fmt::geld((int) $z['steuer'], (string) $z['waehrung'])) ?></td></tr>
        <?php endif; ?>
        <tr><td>Brutto</td><td><b><?= Fmt::h(Fmt::geld((int) $z['brutto'], (string) $z['waehrung'])) ?></b></td></tr>
        <tr><td>Nummernreihe</td><td>
          <?php if ($z['luecken']): ?>
            <span class="marke2 schlecht">Lücken</span>
            <div style="color:var(--rot);font-size:12.5px;margin-top:5px">
              Es fehlen: <?= Fmt::h(implode(', ', $z['luecken'])) ?>.
              Eine Nummerierung muss im Jahr lückenlos sein — bitte klären, bevor das rausgeht.</div>
          <?php else: ?>
            <span class="marke2 gut">lückenlos</span>
          <?php endif; ?></td></tr>
        <?php if ((int) $z['offen'] > 0): ?>
          <tr><td>Nicht bezahlt</td><td><span class="marke2 warnung"><?= (int) $z['offen'] ?></span></td></tr>
        <?php endif; ?>
        <?php if ((int) $z['entwuerfe'] > 0): ?>
          <tr><td>Entwürfe</td><td><?= (int) $z['entwuerfe'] ?>
            <small style="color:var(--leise)">— ohne Nummer, nicht im Paket: das sind keine Belege</small></td></tr>
        <?php endif; ?>
      </tbody></table></div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;align-items:center">
        <a class="knopf haupt" href="<?= Fmt::h(url('steuerakte/' . (int) $j . '/paket')) ?>">Alles als ZIP</a>
        <a class="knopf" href="<?= Fmt::h(url('steuerakte/' . (int) $j . '/verzeichnis')) ?>">Nur die Tabelle</a>
        <span style="color:var(--leise);font-size:12.5px">Aufbewahrungsfrist zehn Jahre (Art. 2220 Codice civile).</span>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
