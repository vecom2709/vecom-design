<?php
/* AutoBuild Phase 8 (06.10.2026): Kundenwünsche — einordnen gegen das Angebot, dann umsetzen lassen.
   Die Regeln stehen in Wunsch.php; Claude schlägt nur vor, entschieden wird hier. */
require_once dirname(__DIR__) . '/src/Wunsch.php';
require_once dirname(__DIR__) . '/src/BauAuftrag.php';
$wuListe = Wunsch::liste((int) $p['id']);
$wuAdmin = Auth::istAdmin();
$wuNeu = count(array_filter($wuListe, static fn($w) => $w['status'] === 'neu'));
$wuBaubar = count(Wunsch::baubar((int) $p['id']));
$wuBau = Bausperre::darfBauen($p);
$wuOffen = (bool) Db::wert("SELECT COUNT(*) FROM bau_auftraege WHERE project_id = ? AND art = 'wuensche' AND status IN ('wartet','laeuft')", [(int) $p['id']], 0);
$wuForm = static fn(string $tat, string $inhalt): string => '<form method="post" action="' . Fmt::h(url('')) . '" style="display:inline-flex;gap:6px;flex-wrap:wrap;align-items:center;margin:4px 6px 0 0">'
    . Csrf::feld() . '<input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="id" value="' . (int) $p['id'] . '">' . $inhalt . '</form>';
$wuFarbe = ['neu' => 'var(--gelb, #b7791f)', 'im_umfang' => 'var(--gruen, #2e7d32)', 'zusatz_angenommen' => 'var(--gruen, #2e7d32)', 'zusatz' => 'var(--cyan, #2b7bb9)',
            'in_arbeit' => 'var(--gelb, #b7791f)', 'umgesetzt' => 'var(--leise)', 'abgelehnt' => 'var(--rot, #c0392b)'];
?>
<div class="block" id="wuensche">
  <h2>Wünsche des Kunden <span style="font-size:12px;color:var(--leise);font-weight:500">Scope-Schutz: gebaut wird nur „im Umfang“ oder „Zusatz angenommen“</span></h2>
  <?php if (!$wuListe): ?>
    <p class="leer" style="margin:0">Noch keine Wünsche. Schreibt der Kunde auf seiner Seite „Ich möchte etwas ändern“, erscheint es hier.</p>
  <?php endif; ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 4px">
    <?php if ($wuNeu > 0 && !$wuOffen && !$wuBau['stopp']): ?>
      <?= $wuForm('bau_auftrag', '<input type="hidden" name="art" value="wuensche"><button class="knopf">Claude um Einordnung bitten (' . $wuNeu . ')</button>') ?>
    <?php elseif ($wuOffen): ?><span class="akq-klein">⏳ Claude ordnet gerade ein …</span><?php endif; ?>
    <?php if ($wuAdmin && $wuBaubar > 0): ?>
      <?php if ($wuBau['ok']): ?>
        <?= $wuForm('wunsch_umsetzen', '<button class="knopf haupt">' . ($wuBaubar === 1 ? '1 Wunsch' : $wuBaubar . ' Wünsche') . ' umsetzen lassen</button>') ?>
      <?php else: ?><span class="akq-klein">🔒 Umsetzen erst ohne Bausperre: <?= Fmt::h($wuBau['grund']) ?></span><?php endif; ?>
    <?php endif; ?>
  </div>
  <?php foreach (array_slice($wuListe, 0, 30) as $wu): $wuSt = Wunsch::anzeige($wu); ?>
    <div style="border-top:1px solid var(--linie, #eee);padding:10px 0">
      <div style="font-size:15px;white-space:pre-wrap;overflow-wrap:anywhere"><?= Fmt::h((string) $wu['text']) ?></div>
      <div style="font-size:13px;margin-top:4px">
        <b style="color:<?= $wuFarbe[$wuSt] ?? 'var(--leise)' ?>"><?= Fmt::h(Wunsch::STATUS[$wuSt] ?? $wuSt) ?></b>
        <span class="akq-klein" style="color:var(--leise)"> · <?= $wu['quelle'] === 'kunde' ? 'vom Kunden' : 'von Vecom eingetragen' ?> · <?= Fmt::h(Fmt::datum((string) $wu['created_at'])) ?>
          <?= (string) ($wu['eingeordnet_von'] ?? '') !== '' ? ' · eingeordnet von ' . Fmt::h((string) $wu['eingeordnet_von']) : '' ?>
          <?= (int) ($wu['version_id'] ?? 0) > 0 ? ' · in V' . (int) Db::wert('SELECT nummer FROM projekt_versionen WHERE id = ?', [(int) $wu['version_id']], 0) : '' ?></span>
        <?php if ((string) ($wu['grund'] ?? '') !== ''): ?><div class="akq-klein">Begründung (sieht der Kunde): <?= Fmt::h((string) $wu['grund']) ?></div><?php endif; ?>
        <?php if ($wu['status'] === 'neu' && (string) ($wu['vorschlag'] ?? '') !== ''): ?>
          <div style="margin-top:4px;padding:6px 10px;border-radius:8px;background:var(--flaeche2, rgba(127,127,127,.08))">
            🤖 Claude schlägt vor: <b><?= Fmt::h(['im_umfang' => 'im Umfang', 'zusatz' => 'Zusatz', 'unklar' => 'unklar — nachfragen'][$wu['vorschlag']] ?? $wu['vorschlag']) ?></b>
            <?= (string) $wu['vorschlag_aufwand'] !== '' ? ' · Aufwand: ' . Fmt::h((string) $wu['vorschlag_aufwand']) : '' ?>
            <div class="akq-klein"><?= Fmt::h((string) $wu['vorschlag_grund']) ?></div>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($wuAdmin && $wuSt !== 'umgesetzt' && $wuSt !== 'in_arbeit'): ?>
        <?php
          $wuWahl = $wu['status'] === 'zusatz' ? ['zusatz_angenommen' => 'Kunde hat das Zusatzangebot angenommen', 'abgelehnt' => 'abgelehnt', 'neu' => 'zurück auf neu']
                  : ['im_umfang' => 'im Umfang', 'zusatz' => 'Zusatz — erst Angebot', 'abgelehnt' => 'abgelehnt'] + ($wu['status'] !== 'neu' ? ['neu' => 'zurück auf neu'] : []);
          unset($wuWahl[$wu['status']]);
          $wuOpt = ''; foreach ($wuWahl as $k => $l) { $wuOpt .= '<option value="' . $k . '"' . ($k === ($wu['vorschlag'] ?? '') ? ' selected' : '') . '>' . Fmt::h($l) . '</option>'; }
        ?>
        <?php $wuFeld = $wuForm('wunsch_einordnen', '<input type="hidden" name="wunsch" value="' . (int) $wu['id'] . '"><select name="status">' . $wuOpt . '</select><input name="grund" maxlength="500" placeholder="Begründung für den Kunden (bei Zusatz/abgelehnt)" style="min-width:min(300px,100%)" value="' . Fmt::h($wu['status'] === 'neu' && ($wu['vorschlag'] ?? '') !== 'im_umfang' ? (string) ($wu['vorschlag_grund'] ?? '') : '') . '"><button class="knopf klein">Einordnen</button>'); ?>
        <?php if ($wu['status'] === 'neu' || $wu['status'] === 'zusatz'): ?><?= $wuFeld ?>
        <?php else: ?><details style="margin-top:2px"><summary style="cursor:pointer;font-size:13px">anders einordnen</summary><?= $wuFeld ?></details><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13px">Wunsch selbst eintragen (z. B. vom Telefon)</summary>
    <?= $wuForm('wunsch_neu', '<textarea name="text" required maxlength="2000" rows="2" style="min-width:min(460px,100%)" placeholder="Was soll anders sein?"></textarea><button class="knopf klein">Eintragen</button>') ?>
  </details>
</div>
