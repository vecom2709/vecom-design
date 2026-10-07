<?php
/* AI Freigaben (AI Office Stufe 1, 06.10.2026). Daten: $offen, $ruhend, $entschieden, $gehalten, $notaus, $fokus.
   Uwe: Umfang „Was Claude vorschlägt“, Ausführen „Dieselbe Tat wie Ihr Knopf“, Zurückstellen „morgen, 3 Tage,
   1 Woche“. Ein Ding je Karte: Genehmigen ist der eine laute Knopf. */
$offen = $offen ?? []; $ruhend = $ruhend ?? []; $entschieden = $entschieden ?? []; $mailsJeFreigabe = $mailsJeFreigabe ?? []; $gehalten = $gehalten ?? [];
$statusWort = ['ausgefuehrt' => ['erledigt', 'gut'], 'fehlgeschlagen' => ['fehlgeschlagen', 'schlecht'], 'abgelehnt' => ['abgelehnt', ''],
               'von_hand' => ['per Knopf erledigt', 'gut'], 'laeuft' => ['läuft', 'warnung']];
$link = static function (array $f): string {
    if (!empty($f['projekt_id'])) { return url('projekte/' . (int) $f['projekt_id']); }
    if (!empty($f['kunde_id'])) { return url('kunden/' . (int) $f['kunde_id']); }
    if (!empty($f['partner_id'])) { return url('partner/' . (int) $f['partner_id']); }
    return '';
};
?>
<div class="kopf">
  <div><h1>AI Freigaben</h1>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">
    Was Claude vorbereitet hat und auf dein Ja wartet. Genehmigen löst genau die Tat aus, die auch dein Knopf
    in der Verwaltung auslöst — mit derselben Rückfrage. Ohne dein Ja passiert nichts.</p></div>
</div>

<?php if (!$offen && !$gehalten): ?>
  <div class="block"><p style="margin:0;color:var(--dim)">Nichts wartet auf dich.</p></div>
<?php endif; ?>

<?php foreach ($offen as $f):
  $r = Freigabe::rueckfrage($f); $d = Freigabe::daten($f); $art = Freigabe::ARTEN[$f['art']] ?? null;
  $schwer = $r['gewicht'] === Ablauf::SCHWER; $ziel = $link($f); ?>
  <div class="block fr-karte<?= (int) $fokus === (int) $f['id'] ? ' fr-fokus' : '' ?>" id="f<?= (int) $f['id'] ?>">
    <h2 style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><?= Fmt::h((string) $f['titel']) ?>
      <span class="marke2 <?= $schwer ? 'schlecht' : 'warnung' ?>" title="<?= Fmt::h($r['frage']) ?>"><?= $schwer ? 'wiegt schwer' : 'erreicht den Empfänger' ?></span>
      <?php if ($f['status'] === 'zurueckgestellt'): ?><span class="marke2">war zurückgestellt</span><?php endif; ?></h2>
    <p style="color:var(--leise);font-size:var(--fs-klein);margin:0 0 10px">
      <?= Fmt::h((string) $f['vorgeschlagen_von']) ?> · <?= Fmt::h((string) $f['system_name']) ?> · <?= Fmt::h(Fmt::zeit((string) $f['created_at'])) ?>
      <?php if ($ziel !== ''): ?> · <a href="<?= Fmt::h($ziel) ?>">Akte öffnen</a><?php endif; ?></p>
    <table class="schlicht fr-tabelle"><tbody>
      <?php /* „Claude empfiehlt“ nur, wenn Claude vorgeschlagen hat — die Bewertungs-Bitte (V6) und der Spürhund sind Regeln, keine Meinung. */
        $vonClaude = str_starts_with((string) $f['vorgeschlagen_von'], 'Claude');
        foreach (['grund' => 'Grund', 'ist' => 'Jetzt', 'soll' => 'Danach', 'auswirkung' => 'Mögliche Auswirkung', 'kosten' => 'Kosten', 'rollback' => 'Rückweg', 'empfehlung' => $vonClaude ? 'Claude empfiehlt' : 'Empfehlung'] as $k => $w):
        if (empty($f[$k])) { continue; } ?>
        <tr><td style="width:24%;color:var(--dim)"><?= Fmt::h($w) ?></td><td><?= nl2br(Fmt::h((string) $f[$k])) ?></td></tr>
      <?php endforeach; ?>
      <tr><td style="color:var(--dim)">Risiko</td><td><?= Fmt::h($r['frage']) ?></td></tr>
    </tbody></table>

    <form method="post" action="<?= Fmt::h(url('')) ?>" data-frage="<?= Fmt::h($r['frage']) ?>" data-ja="<?= Fmt::h($r['ja']) ?>" style="margin-top:14px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="freigabe_genehmigen"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
      <input type="hidden" name="zurueck" value="ai-freigaben">
      <?php if ($art && in_array('betreff', $art['aenderbar'], true)): ?>
        <div class="feld"><label for="fr-b-<?= (int) $f['id'] ?>">Betreff — du kannst ihn vor dem Genehmigen ändern</label>
          <input id="fr-b-<?= (int) $f['id'] ?>" name="betreff" value="<?= Fmt::h((string) ($d['betreff'] ?? '')) ?>" maxlength="200" required></div>
      <?php endif; ?>
      <?php if ($art && in_array('text', $art['aenderbar'], true)): ?>
        <div class="feld"><label for="fr-t-<?= (int) $f['id'] ?>">Text — du kannst ihn vor dem Genehmigen ändern</label>
          <textarea id="fr-t-<?= (int) $f['id'] ?>" name="text" rows="7" required><?= Fmt::h((string) ($d['text'] ?? '')) ?></textarea></div>
      <?php endif; ?>
      <button class="knopf haupt"><?= Fmt::h($r['ja']) ?></button>
    </form>
    <div class="fr-weiter">
      <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="freigabe_ablehnen">
        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><input type="hidden" name="zurueck" value="ai-freigaben">
        <input name="grund" placeholder="Warum nicht? (optional)" maxlength="300" aria-label="Grund für die Ablehnung" style="width:auto;min-width:220px">
        <button class="knopf">Ablehnen</button></form>
      <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="freigabe_zurueckstellen">
        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><input type="hidden" name="zurueck" value="ai-freigaben">
        <span style="color:var(--leise);font-size:var(--fs-klein)">Zurückstellen:</span>
        <?php foreach (Freigabe::ZURUECK_TAGE as $tage => $wort): ?>
          <button class="knopf" name="tage" value="<?= (int) $tage ?>">bis <?= Fmt::h($wort) ?></button>
        <?php endforeach; ?></form>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($gehalten): ?>
  <div class="block" id="gehalten">
    <h2>Vom Not-Aus zurückgehalten: <?= count($gehalten) ?> Mail<?= count($gehalten) === 1 ? '' : 's' ?></h2>
    <p style="font-size:var(--fs-klein);margin:0 0 12px;color:var(--leise)">Automationen wollten sie während des Not-Aus verschicken. Nichts davon ist draußen.
      <?= $notaus ? 'Senden geht erst, wenn der Not-Aus gelöst ist (Einstellungen → Automationen).' : '' ?></p>
    <?php foreach ($gehalten as $g): ?>
      <div class="fr-mail">
        <div><b><?= Fmt::h((string) $g['betreff']) ?></b>
          <p>An <?= Fmt::h((string) $g['empfaenger']) ?> · <?= Fmt::h(Fmt::zeit((string) $g['created_at'])) ?><?= !empty($g['herkunft']) ? ' · aus ' . Fmt::h((string) $g['herkunft']) : '' ?></p></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if (!$notaus): ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="ausgang_senden"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="zurueck" value="ai-freigaben"><button class="knopf">Mail jetzt senden</button></form>
          <?php endif; ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="ausgang_verwerfen"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="zurueck" value="ai-freigaben"><button class="knopf">Mail verwerfen</button></form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($ruhend): ?>
  <div class="block"><h2>Zurückgestellt</h2>
    <table class="schlicht"><tbody>
      <?php foreach ($ruhend as $f): ?>
        <tr><td><?= Fmt::h((string) $f['titel']) ?></td><td style="color:var(--dim);width:30%">wieder oben ab <?= Fmt::h(Fmt::zeit((string) $f['zurueck_bis'])) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>

<?php if ($entschieden): ?>
  <div class="block"><h2>Zuletzt entschieden</h2>
    <table class="schlicht"><tbody>
      <?php foreach ($entschieden as $f): [$sw, $sk] = $statusWort[$f['status']] ?? [$f['status'], '']; ?>
        <tr id="f<?= (int) $f['id'] ?>"><td><?= Fmt::h((string) $f['titel']) ?><?php if (!empty($f['ergebnis'])): ?><br><small style="color:var(--leise)"><?= Fmt::h(mb_substr((string) $f['ergebnis'], 0, 160)) ?></small><?php endif; ?>
          <?php /* Mail-Spur (07.10.2026): was diese Freigabe verschickt hat — Band mit Stand, Text aufklappbar. */
            $mailSpur = ($mailsJeFreigabe ?? [])[(int) $f['id']] ?? []; $mailSpurLeer = ''; require __DIR__ . '/mailspur.php'; ?></td>
          <td style="width:1%;white-space:nowrap"><span class="marke2 <?= $sk ?>"><?= Fmt::h($sw) ?></span><?= !empty($f['geaendert']) ? ' <span class="marke2">geändert</span>' : '' ?></td>
          <td style="color:var(--dim);width:28%"><?= Fmt::h(trim((string) ($f['entschieden_von'] ?? '') . ' · ' . ($f['kanal'] === 'telegram' ? 'Telegram · ' : '') . Fmt::zeit((string) ($f['entschieden_am'] ?? $f['created_at'])), ' ·')) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>

<style>
  .fr-fokus{border-color:var(--gold,#f1d38b)}
  .fr-tabelle td{vertical-align:top;line-height:1.55}
  .fr-weiter{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:12px}
  .fr-weiter form{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:0}
  .fr-mail{display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap;padding:12px 0;border-top:1px solid var(--linie,rgba(255,255,255,.08))}
  .fr-mail p{margin:4px 0 0;color:var(--leise);font-size:var(--fs-klein)}
  .fr-mail form{margin:0}
</style>
