<div class="kopf"><h1>Aktivitäten</h1></div>
<div class="block"><div class="tabellenrahmen"><table>
<thead><tr><th>Zeitpunkt</th><th>Ereignis</th><th>Kunde</th><th>Ausgelöst von</th></tr></thead><tbody>
<?php if (!$liste): ?><tr><td colspan="4"><div class="leer">Noch keine Einträge.</div></td></tr><?php endif; ?>
<?php foreach ($liste as $a): ?><tr>
<td style="white-space:nowrap;color:var(--dim)"><?= Fmt::h(Fmt::zeit($a['created_at'])) ?></td>
<td><?= Fmt::h($a['title']) ?></td>
<td><?= $a['customer_id'] ? '<a href="' . Fmt::h(url('kunden/' . $a['customer_id'])) . '">' . Fmt::h((string) $a['kunde']) . '</a>' : '—' ?></td>
<td style="color:var(--dim)"><?= Fmt::h($a['actor']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
