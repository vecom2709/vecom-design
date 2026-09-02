<div class="kopf"><div><h1>Ausgaben</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Was das Geschäft kostet. Im Regime forfettario wird das nicht einzeln abgezogen — aufbewahren
    und fortlaufend nummerieren muss man es trotzdem (comma 59 der L. 190/2014). Und für alles aus
    dem Ausland — Stripe, Google, Hosting — braucht der Commercialista eine eigene Liste.</p></div>
  <div><a class="knopf haupt" href="<?= Fmt::h(url('ausgaben/neu')) ?>">Ausgabe erfassen</a></div>
</div>

<?php if ($jahre): ?>
<div class="block" style="padding-bottom:14px">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span style="color:var(--leise);font-size:12.5px">Jahr:</span>
    <?php foreach ($jahre as $j): ?>
      <a class="knopf <?= (int) $j === (int) $jahr ? 'haupt' : '' ?>"
         href="<?= Fmt::h(url('ausgaben?jahr=' . (int) $j)) ?>"><?= (int) $j ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($summe['anzahl'] > 0): ?>
  <div class="tabellenrahmen" style="margin-top:12px"><table><tbody>
    <tr><td style="width:40%"><?= (int) $summe['anzahl'] ?> Belege <?= (int) $jahr ?></td>
        <td><b><?= Fmt::h(Fmt::geld((int) $summe['brutto'])) ?></b></td></tr>
    <?php if ((int) $summe['rc_netto'] > 0): ?>
    <tr><td>davon Reverse Charge (netto)</td><td><?= Fmt::h(Fmt::geld((int) $summe['rc_netto'])) ?>
      <div style="color:var(--leise);font-size:12.5px;margin-top:4px">Darauf fallen rechnerisch
        <?= Fmt::h(Fmt::geld((int) $summe['rc_iva'])) ?> italienische IVA an, die zu zahlen und
        nicht abziehbar ist. Den genauen Betrag rechnet der Commercialista — hier steht, worauf.</div></td></tr>
    <?php endif; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="block">
  <?php if (!$liste): ?>
    <div class="leer">Für <?= (int) $jahr ?> ist noch nichts erfasst. Fang mit dem an, was
      monatlich abgeht: Hosting, Stripe, Software.</div>
  <?php else: ?>
    <div class="tabellenrahmen"><table><thead><tr><th>Nummer</th><th>Datum</th><th>Lieferant</th><th>Kategorie</th>
      <th>Betrag</th><th>Beleg</th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $a): ?>
      <tr>
        <td style="white-space:nowrap"><?= Fmt::h((string) $a['beleg_nr']) ?>
          <?php if ((int) $a['reverse_charge'] === 1): ?>
            <br><small style="color:var(--gelb)">Reverse Charge</small><?php endif; ?></td>
        <td style="white-space:nowrap"><?= Fmt::h(Fmt::datum((string) $a['datum'])) ?></td>
        <td><?= Fmt::h((string) $a['lieferant']) ?>
          <?php if ((string) $a['land'] !== 'IT'): ?>
            <small style="color:var(--leise)"> · <?= Fmt::h((string) $a['land']) ?></small><?php endif; ?>
          <?php if ($a['titel']): ?><br><small style="color:var(--leise)"><?= Fmt::h((string) $a['titel']) ?></small><?php endif; ?></td>
        <td><?= Fmt::h(Ausgabe::KATEGORIEN[$a['kategorie']] ?? (string) $a['kategorie']) ?></td>
        <td style="white-space:nowrap;font-variant-numeric:tabular-nums">
          <?= Fmt::h(Fmt::geld((int) $a['brutto_cents'], (string) $a['waehrung'])) ?></td>
        <td><?php if (($a['stored_name'] ?? '') !== ''): ?>
              <a class="knopf" href="<?= Fmt::h(url('ausgaben/' . (int) $a['id'] . '/datei')) ?>">ansehen</a>
            <?php else: ?><span class="marke2 warnung">fehlt</span><?php endif; ?></td>
        <td style="text-align:right"><a class="knopf" href="<?= Fmt::h(url('ausgaben/' . (int) $a['id'])) ?>">Ändern</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
