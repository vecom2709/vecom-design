<?php /* AutoBuild Phase 10 (07.10.2026): Bauregeln — was Claude beim Bauen zusätzlich beachten muss. Regeln in BauRegeln.php. */
$brListe = BauRegeln::liste(true);
$brVorschlaege = BauRegeln::vorschlaege();
$brForm = static fn(string $tat, string $inhalt, string $stil = 'display:inline-flex;gap:6px;flex-wrap:wrap;align-items:center;margin:4px 6px 0 0'): string =>
    '<form method="post" action="' . Fmt::h(url('')) . '" style="' . $stil . '">' . Csrf::feld() . '<input type="hidden" name="tat" value="' . $tat . '">' . $inhalt . '</form>';
?>
<div class="kopf"><div><h1>Bauregeln <span style="font-size:15px;color:var(--leise);font-weight:500">Version <?= BauRegeln::version() ?></span></h1>
  <p style="color:var(--leise);font-size:14px;margin-top:6px">Jeder Bauauftrag bekommt diese Regeln mit — zusätzlich zum <a href="<?= Fmt::h(url('standard')) ?>">Vecom-Standard</a> und vor ihm.
    Fest eingebaut und hier nicht abschaltbar: Bausperre vor der Angebotsannahme, nichts erfinden, keine Platzhalter, keine fremden Skripte, Dateigrenzen, nichts veröffentlichen ohne Menschen.</p></div></div>

<?php if ($brVorschlaege): ?>
<div class="block" style="border-color:var(--gelb, #b7791f)">
  <h2>Vorschläge aus wiederkehrenden Mängeln</h2>
  <?php foreach ($brVorschlaege as $v): $bel = json_decode((string) $v['belege'], true) ?: []; ?>
    <div style="border-top:1px solid var(--linie, #eee);padding:10px 0">
      <div style="font-size:15.5px"><b><?= Fmt::h((string) $v['text']) ?></b></div>
      <div class="akq-klein" style="margin-top:4px">Kam vor bei: <?= Fmt::h(implode(' · ', array_map(static fn($b) => $b['projekt'] . ' V' . $b['fassung'] . ' („' . mb_substr($b['mangel'], 0, 80) . '“)', array_slice($bel, 0, 5)))) ?></div>
      <?php if ($admin): ?>
        <?= $brForm('bauregel_vorschlag', '<input type="hidden" name="vorschlag" value="' . (int) $v['id'] . '"><input type="hidden" name="entscheidung" value="ja"><button class="knopf klein haupt">Ja, als Regel aufnehmen</button>') ?>
        <?= $brForm('bauregel_vorschlag', '<input type="hidden" name="vorschlag" value="' . (int) $v['id'] . '"><input type="hidden" name="entscheidung" value="nein"><button class="knopf klein">Nein</button>') ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="block">
  <h2>Eigene Regeln</h2>
  <?php if (!$brListe): ?><div class="leer">Noch keine eigene Regel. Es gelten der Vecom-Standard und die festen Regeln.</div><?php endif; ?>
  <?php foreach ($brListe as $r): ?>
    <div style="border-top:1px solid var(--linie, #eee);padding:10px 0;<?= (int) $r['aktiv'] ? '' : 'opacity:.55' ?>">
      <div style="font-size:15.5px"><?= Fmt::h((string) $r['text']) ?></div>
      <div class="akq-klein" style="color:var(--leise)"><?= (int) $r['aktiv'] ? 'gilt' : 'ausgeschaltet' ?> · <?= $r['quelle'] === 'vorschlag' ? 'aus einem Vorschlag' : 'von Hand' ?> · <?= Fmt::h((string) ($r['von'] ?? '')) ?> · <?= Fmt::h(Fmt::datum((string) $r['created_at'])) ?></div>
      <?php if ($admin): ?>
        <details style="margin-top:4px"><summary style="cursor:pointer;font-size:var(--fs-klein)">ändern</summary>
          <?= $brForm('bauregel_aendern', '<input type="hidden" name="regel" value="' . (int) $r['id'] . '"><textarea name="text" rows="2" maxlength="500" style="min-width:min(520px,100%)">' . Fmt::h((string) $r['text']) . '</textarea><button class="knopf klein">Speichern</button>') ?>
        </details>
        <?= $brForm('bauregel_schalten', '<input type="hidden" name="regel" value="' . (int) $r['id'] . '"><input type="hidden" name="an" value="' . ((int) $r['aktiv'] ? '0' : '1') . '"><button class="knopf klein">' . ((int) $r['aktiv'] ? 'Ausschalten' : 'Wieder einschalten') . '</button>') ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($admin): ?>
    <?= $brForm('bauregel_neu', '<textarea name="text" required minlength="8" maxlength="500" rows="2" style="min-width:min(520px,100%)" placeholder="z. B. Öffnungszeiten immer als Tabelle, nie als Fließtext."></textarea><button class="knopf">Regel aufnehmen</button>', 'display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:12px') ?>
  <?php endif; ?>
</div>

<?php $brVerlauf = BauRegeln::verlauf(); if ($brVerlauf): ?>
<div class="block">
  <h2>Verlauf</h2>
  <ul style="font-size:14px;margin:0;padding-left:18px">
    <?php foreach ($brVerlauf as $l): ?>
      <li>Version <?= (int) $l['id'] ?> · <?= Fmt::h(Fmt::zeit((string) $l['created_at'])) ?> · <?= Fmt::h(['neu' => 'neu', 'geaendert' => 'geändert', 'aus' => 'ausgeschaltet', 'an' => 'eingeschaltet'][$l['aktion']] ?? $l['aktion']) ?> · <?= Fmt::h((string) $l['von']) ?>: <?= Fmt::h(mb_substr((string) $l['text'], 0, 140)) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
