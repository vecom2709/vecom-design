<?php
/* AutoBuild Phase 4 (06.10.2026): Projektkarte „Bauen“ — Bausperre und Not-Aus auf einen Blick.
   Die Regel selbst steht in Bausperre::darfBauen(); hier wird sie nur gezeigt und bedient. */
require_once dirname(__DIR__) . '/src/Bausperre.php';
$pbB = Bausperre::darfBauen($p);
$pbP = Db::one('SELECT ki_stopp, ki_stopp_grund, ki_stopp_am, ki_stopp_von, bau_frei_am, bau_frei_von FROM projects WHERE id = ?', [(int) $p['id']]) ?: [];
$pbAlle = Bausperre::alleGestoppt();
$pbAdmin = Auth::istAdmin();
$pbForm = static fn(string $tat, string $inhalt, string $attr = ''): string => '<form method="post" action="' . Fmt::h(url('')) . '" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0"' . $attr . '>'
    . Csrf::feld() . '<input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="id" value="' . (int) $p['id'] . '">' . $inhalt . '</form>';
?>
<div class="block" id="bauen" style="border-color:<?= $pbB['stopp'] ? 'var(--rot, #c0392b)' : ($pbB['ok'] ? 'var(--gruen, #2e7d32)' : 'var(--gelb, #b7791f)') ?>">
  <h2>Bauen <span style="font-size:12px;color:var(--leise);font-weight:500">AutoBuild · Bausperre und Not-Aus</span></h2>
  <?php if ($pbB['stopp']): ?>
    <p style="font-size:15px;margin:0"><b>⛔ <?= Fmt::h($pbB['grund']) ?></b></p>
    <?php if ((int) ($pbP['ki_stopp'] ?? 0) === 1): ?><p class="akq-klein" style="margin:4px 0 0;color:var(--leise)">gestoppt von <?= Fmt::h((string) $pbP['ki_stopp_von']) ?> am <?= Fmt::h(Fmt::datum((string) $pbP['ki_stopp_am'])) ?> — keine Änderungen, keine Vorschau, kein Paket, kein Livegang.</p><?php endif; ?>
  <?php elseif ($pbB['ok']): ?>
    <p style="font-size:15px;margin:0"><b>🔓 Bauen erlaubt</b> <span style="color:var(--leise);font-size:13px">seit <?= Fmt::h(Fmt::datum((string) $pbB['seit'])) ?> · <?= Fmt::h((string) $pbB['von']) ?></span></p>
  <?php else: ?>
    <p style="font-size:15px;margin:0"><b>🔒 <?= Fmt::h($pbB['grund']) ?></b></p>
    <p style="font-size:13px;margin:6px 0 0;color:var(--dim)">Claude darf analysieren und planen, aber nichts bauen, nichts ändern und nichts veröffentlichen.
      <?= $pbB['angenommen'] ? '✓' : '✗' ?> Angebot angenommen · <?= $pbB['bezahlt'] ? '✓' : '✗' ?> Anzahlung bezahlt</p>
  <?php endif; ?>

  <?php if ((int) ($pbP['ki_stopp'] ?? 0) === 1): ?>
    <?php if ($pbAdmin): ?><?= $pbForm('bau_weiter', '<button class="knopf">KI wieder freigeben</button>') ?><?php else: ?><p class="akq-klein" style="margin:6px 0 0">Wieder freigeben kann nur ein Admin.</p><?php endif; ?>
  <?php else: ?>
    <?= $pbForm('bau_stopp', '<input name="grund" maxlength="255" placeholder="Grund (z. B. Kunde wartet auf Rückmeldung, falsche Richtung)" style="min-width:min(360px,100%)"><button class="knopf">⛔ KI für dieses Projekt stoppen</button>') ?>
  <?php endif; ?>
  <?php if (!$pbB['ok'] && !$pbB['stopp'] && $pbAdmin): ?>
    <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13px">Bausperre von Hand aufheben (nur Admin)</summary>
      <?= $pbForm('bau_von_hand', '<input name="grund" required minlength="10" maxlength="200" placeholder="Warum ohne Annahme und Zahlung im System? (z. B. schriftlicher Auftrag per Mail)" style="min-width:min(420px,100%)"><button class="knopf">Von Hand freigeben</button>') ?>
    </details>
  <?php endif; ?>
  <?php
  /* AutoBuild Phase 5: Bau-Warteschlange — Analyse und Pflichtenheft über den PC (Claude-Abo). */
  require_once dirname(__DIR__) . '/src/BauAuftrag.php';
  $pbListe = BauAuftrag::fuerProjekt((int) $p['id'], 8);
  $pbStand = Db::one('SELECT analyse_am, pflichtenheft_am, MD5(analyse) AS analyse_md5, MD5(pflichtenheft) AS pflichtenheft_md5 FROM projects WHERE id = ?', [(int) $p['id']]) ?: [];
  $pbOffen = [];
  foreach ($pbListe as $pbA) { if (in_array($pbA['status'], ['wartet', 'laeuft'], true)) { $pbOffen[$pbA['art']] = true; } }
  ?>
  <h3 style="margin:16px 0 4px;font-size:16px">Claude-Aufträge <span style="font-size:12px;color:var(--leise);font-weight:500">laufen auf deinem PC · live geht nichts ohne deinen Klick</span></h3>
  <?php if (!$pbB['stopp']): ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php foreach (BauAuftrag::STARTBAR as $pbArt): [$pbName, $pbWas] = BauAuftrag::ARTEN[$pbArt]; ?>
      <div style="flex:1 1 280px;border:1px solid var(--linie, #ddd);border-radius:10px;padding:10px 12px">
        <b style="font-size:15px"><?= Fmt::h($pbName) ?></b>
        <?php if ($pbArt !== 'bauen'): $pbAm = $pbStand[$pbArt . '_am'] ?? null; ?>
        <span class="akq-klein" style="color:var(--leise)"> · <?= $pbAm ? 'übernommen am ' . Fmt::h(Fmt::datum((string) $pbAm)) : 'noch keine' ?></span>
        <?php endif; ?>
        <p style="font-size:13px;margin:4px 0 0;color:var(--dim)"><?= Fmt::h($pbWas) ?></p>
        <?php $pbSperre = $pbArt !== 'bauen' ? null : (!$pbB['ok'] ? 'Erst wenn die Bausperre gefallen ist (Angebot angenommen + Anzahlung).' : (empty($pbStand['pflichtenheft_am']) ? 'Erst ein Pflichtenheft übernehmen — gebaut wird nur dagegen.' : null)); ?>
        <?php if (!empty($pbOffen[$pbArt]) || ($pbArt === 'bauen' && !empty($pbOffen['review']))): ?>
          <p style="font-size:13px;margin:6px 0 0"><b>⏳ läuft oder wartet schon</b></p>
        <?php elseif ($pbSperre !== null): ?>
          <p style="font-size:13px;margin:6px 0 0">🔒 <?= Fmt::h($pbSperre) ?></p>
        <?php else: ?>
          <?= $pbForm('bau_auftrag', '<input type="hidden" name="art" value="' . $pbArt . '"><input name="hinweis" maxlength="500" placeholder="Zusatzwunsch (optional)" style="flex:1 1 180px"><button class="knopf">' . Fmt::h($pbArt === 'bauen' ? 'Website bauen lassen' : $pbName . ' erstellen') . '</button>') ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="akq-klein" style="margin:4px 0 0">Gestoppt — es werden keine Claude-Aufträge angelegt oder abgeholt.</p>
  <?php endif; ?>
  <?php if ($pbListe): ?>
    <div style="margin-top:10px">
    <?php $pbFertigOffen = []; foreach ($pbListe as $pbA): ?>
      <?php $pbFarbe = ['fertig' => 'var(--gruen, #2e7d32)', 'fehler' => 'var(--rot, #c0392b)', 'laeuft' => 'var(--gelb, #b7791f)'][$pbA['status']] ?? 'var(--leise)'; ?>
      <details style="border-top:1px solid var(--linie, #eee);padding:8px 0"<?php $pbAuf = in_array($pbA['status'], ['wartet', 'laeuft'], true) || ($pbA['status'] === 'fertig' && empty($pbFertigOffen[$pbA['art']])); if ($pbA['status'] === 'fertig') { $pbFertigOffen[$pbA['art']] = true; } ?><?= $pbAuf ? ' open' : '' ?>>
        <summary style="cursor:pointer;font-size:14px">
          <b><?= Fmt::h(BauAuftrag::name((string) $pbA['art'])) ?></b>
          <span style="color:<?= $pbFarbe ?>"> · <?= Fmt::h(BauAuftrag::STATUS[$pbA['status']] ?? $pbA['status']) ?></span>
          <span class="akq-klein" style="color:var(--leise)"> · #<?= (int) $pbA['id'] ?> · <?= Fmt::h(Fmt::datum((string) $pbA['created_at'])) ?> · <?= Fmt::h((string) $pbA['von']) ?></span>
        </summary>
        <?php if ((string) $pbA['hinweis'] !== ''): ?><p class="akq-klein" style="margin:6px 0">Wunsch: <?= Fmt::h((string) $pbA['hinweis']) ?></p><?php endif; ?>
        <?php if ($pbA['status'] === 'wartet'): ?>
          <?= $pbForm('bau_auftrag_abbrechen', '<input type="hidden" name="auftrag" value="' . (int) $pbA['id'] . '"><button class="knopf klein">Abbrechen</button>') ?>
        <?php elseif (in_array($pbA['status'], ['fehler', 'abgebrochen'], true) && (string) $pbA['fehler'] !== ''): ?>
          <p style="font-size:13px;margin:6px 0;color:var(--rot, #c0392b)"><?= Fmt::h((string) $pbA['fehler']) ?></p>
        <?php elseif ($pbA['status'] === 'fertig'): ?>
          <?php $pbIstPlan = in_array($pbA['art'], ['analyse', 'pflichtenheft'], true); ?>
          <p class="akq-klein" style="margin:6px 0;color:var(--leise)"><?= $pbIstPlan ? 'Entwurf von Claude — erst lesen, dann übernehmen. Nichts davon ist geprüft oder zugesagt.' : 'Runde ' . (int) ($pbA['versuch'] ?? 1) . ' von ' . BauAuftrag::MAX_VERSUCHE . ' · die Fassung steht unten unter „Fassungen“ — live erst nach Testfassung und deinem „geprüft“.' ?></p>
          <div style="font-size:15px;line-height:1.55;max-height:560px;overflow:auto;border:1px solid var(--linie, #eee);border-radius:8px;padding:8px 14px;background:var(--flaeche, transparent)"><?= BauAuftrag::alsHtml((string) $pbA['ergebnis']) ?></div>
          <?php if (!$pbIstPlan): ?><p style="margin:8px 0 0"><a href="#versionen">→ zu den Fassungen</a></p>
          <?php elseif (($pbStand[$pbA['art'] . '_md5'] ?? null) === md5((string) $pbA['ergebnis'])): ?><p style="font-size:14px;margin:8px 0 0;color:var(--gruen, #2e7d32)"><b>✓ Diese Fassung gilt für das Projekt.</b></p>
          <?php elseif ($pbAdmin): ?><?= $pbForm('bau_uebernehmen', '<input type="hidden" name="auftrag" value="' . (int) $pbA['id'] . '"><button class="knopf">Gelesen — als ' . Fmt::h(BauAuftrag::name((string) $pbA['art'])) . ' übernehmen</button>') ?>
          <?php else: ?><p class="akq-klein" style="margin:6px 0 0">Übernehmen kann nur ein Admin.</p><?php endif; ?>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($pbAlle): ?>
    <p style="margin:10px 0 0;font-size:13px;color:var(--rot, #c0392b)"><b>Alle automatischen Builds sind gestoppt.</b> <?= $pbAdmin ? '' : 'Wieder erlauben kann nur ein Admin.' ?></p>
    <?php if ($pbAdmin): ?><?= $pbForm('bau_weiter_alle', '<button class="knopf klein">Alle Builds wieder erlauben</button>') ?><?php endif; ?>
  <?php endif; ?>
</div>
