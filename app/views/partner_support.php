<?php
/* Partner › Support (Phase 9, 06.10.2026): alle Anliegen der Partner an einer Stelle — offene zuerst.
   Beantwortet wird in der Akte des Partners (dort steht der ganze Verlauf). Daten: $tickets, $stand. */
$tkThema = ['geld' => 'Provision & Auszahlung', 'kunde' => 'Kunde / Kontakt', 'werbemittel' => 'Werbemittel & Bestellung', 'technik' => 'Zugang & Technik', 'sonstiges' => 'Sonstiges'];
$tkStand = ['offen' => ['Offen', 'schlecht'], 'in_arbeit' => ['In Arbeit', 'warnung'], 'erledigt' => ['Erledigt', 'gut']];
$filter = ['' => 'Offen & in Arbeit', 'erledigt' => 'Erledigt (30 Tage)', 'alle' => 'Alle'];
?>
<div class="kopf"><div><h1>Support-Anliegen</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Was Partner an Vecom schreiben. Antworten und den Stand setzen in der Akte des Partners — dort steht der ganze Verlauf.</p></div></div>

<div class="block">
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin:0 0 12px">
    <?php foreach ($filter as $fk => $fw): ?>
      <a class="knopf<?= $stand === $fk ? ' haupt' : '' ?>" href="<?= Fmt::h(url('partner-support') . ($fk !== '' ? '?stand=' . $fk : '')) ?>" style="min-height:32px;padding:4px 12px;font-size:12.5px"><?= Fmt::h($fw) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$tickets): ?>
    <div class="leer"><?= $stand === '' ? 'Kein offenes Anliegen.' : 'Nichts in diesem Zeitraum.' ?></div>
  <?php else: ?>
    <div class="tabellenrahmen"><table id="support-liste">
      <thead><tr><th>Stand</th><th>Partner</th><th>Anliegen</th><th>Bezug</th><th>Zuletzt</th></tr></thead><tbody>
      <?php foreach ($tickets as $t): [$sw, $st] = $tkStand[$t['stand']] ?? [$t['stand'], '']; ?>
        <tr>
          <td style="white-space:nowrap"><span class="marke2 <?= $st ?>"><?= Fmt::h($sw) ?></span><?php if ((int) $t['neu'] > 0): ?> <span class="marke2 warnung"><?= (int) $t['neu'] ?> neu</span><?php endif; ?></td>
          <td><a href="<?= Fmt::h(url('partner/' . (int) $t['partner_id'])) ?>"><?= Fmt::h((string) $t['partner']) ?></a><div style="font-size:12px;color:var(--leise)"><?= Fmt::h((string) $t['code']) ?></div></td>
          <td><a href="<?= Fmt::h(url('partner/' . (int) $t['partner_id']) . '#ticket-' . (int) $t['id']) ?>"><b><?= Fmt::h((string) $t['betreff']) ?></b></a>
            <div style="font-size:12px;color:var(--leise)"><?= Fmt::h($tkThema[$t['thema']] ?? $t['thema']) ?></div></td>
          <td style="font-size:12.5px;color:var(--dim)"><?= $t['bezug_art'] ? Fmt::h(($t['bezug_art'] === 'lead' ? 'Kontakt ' : 'Bestellung ') . (string) ($t['bezug_name'] ?? '')) : '—' ?></td>
          <td style="font-size:12.5px;color:var(--dim);white-space:nowrap"><?= Fmt::h(Fmt::seit((string) $t['geaendert_am'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
