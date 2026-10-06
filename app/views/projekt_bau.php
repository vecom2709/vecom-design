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
  <?php if ($pbAlle): ?>
    <p style="margin:10px 0 0;font-size:13px;color:var(--rot, #c0392b)"><b>Alle automatischen Builds sind gestoppt.</b> <?= $pbAdmin ? '' : 'Wieder erlauben kann nur ein Admin.' ?></p>
    <?php if ($pbAdmin): ?><?= $pbForm('bau_weiter_alle', '<button class="knopf klein">Alle Builds wieder erlauben</button>') ?><?php endif; ?>
  <?php endif; ?>
</div>
