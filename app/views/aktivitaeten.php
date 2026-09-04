<?php /* ==========================================================================
     Der Verlauf — und der Weg, ihn wieder loszuwerden.

     Diese Liste haelt fest, was passiert ist, und wird nirgends fuer eine
     Entscheidung gelesen: keine Stufe, keine Frist, keine Zahl haengt an ihr.
     Sie ist ein Gedaechtnis, kein Beleg — was fuers Finanzamt zaehlt, steht
     in Rechnungen und Zahlungen und bleibt hier unberuehrt.

     Deshalb darf eine Zeile weg, ohne dass etwas nachkippt. Was einmal
     aufgeraeumt ist, kommt aber nicht wieder: dafuer die Rueckfrage.
     ====================================================================== */ ?>
<div class="kopf"><div><h1>Aktivitäten</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Was passiert ist, in der Reihenfolge, in der es passiert ist. Ein reines
    Protokoll — hier hängt keine Frist und keine Zahl daran.</p></div>
</div>
<div class="block"><div class="tabellenrahmen"><table>
<thead><tr><th>Zeitpunkt</th><th>Ereignis</th><th>Kunde</th><th>Ausgelöst von</th><th></th></tr></thead><tbody>
<?php if (!$liste): ?><tr><td colspan="5"><div class="leer">Noch keine Einträge.</div></td></tr><?php endif; ?>
<?php foreach ($liste as $a): ?><tr>
<td style="white-space:nowrap;color:var(--dim)"><?= Fmt::h(Fmt::zeit($a['created_at'])) ?></td>
<td><?= Fmt::h($a['title']) ?></td>
<td><?= $a['customer_id'] ? '<a href="' . Fmt::h(url('kunden/' . $a['customer_id'])) . '">' . Fmt::h((string) $a['kunde']) . '</a>' : '—' ?></td>
<td style="color:var(--dim)"><?= Fmt::h($a['actor']) ?></td>
<td style="text-align:right">
  <form method="post" action="<?= Fmt::h(url('')) ?>"
        data-frage="Diesen Eintrag löschen? Er ist danach weg — am Vorgang selbst ändert das nichts."
        data-ja="Ja, löschen">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="aktivitaet_loeschen">
    <input type="hidden" name="zurueck" value="aktivitaeten">
    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
    <button class="knopf" title="Diesen Eintrag löschen">Löschen</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
