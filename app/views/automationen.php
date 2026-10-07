<?php
/* Automation Center (Phase 8, 06.10.2026). Daten: $liste (Bereich => Regeln), $notaus, $probeRegel, $probe, $lauf, $admin, $darfNotaus.
   Ein Ding je Bildschirm: Die Seite beantwortet „Was läuft von allein, und kann ich es anhalten?“ —
   der Not-Aus ist der einzige laute Knopf, und nur solange er nicht steht. */
$zahl = ['alle' => 0, 'raus' => 0, 'aus' => 0, 'fehler' => 0];
foreach ($liste as $regeln) { foreach ($regeln as $r) {
    $zahl['alle']++; if ($r['raus']) { $zahl['raus']++; } if ($r['aus']) { $zahl['aus']++; } if ($r['fehler_folge'] > 0) { $zahl['fehler']++; }
} }
?>
<style>
  .am-kopfzahlen{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
  .am-regel{display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-top:1px solid var(--linie);flex-wrap:wrap}
  .am-regel:first-of-type{border-top:0}
  .am-regel:target{background:rgba(212,175,55,.07);margin:0 -12px;padding:12px}
  .am-was{flex:1;min-width:240px}
  .am-was b{font-size:14px}
  .am-was p{margin:3px 0 0;color:var(--leise);font-size:var(--fs-klein);line-height:1.45}
  .am-lauf{flex:0 0 230px;font-size:var(--fs-klein);color:var(--leise);line-height:1.5}
  .am-lauf .am-fehler{color:var(--rot)}
  .am-tun{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
  .am-tun form{margin:0}
  .am-aus .am-was b{color:var(--leise);text-decoration:line-through}
  .am-probe{margin:10px 0 0;padding:12px 14px;border:1px solid var(--linie);border-radius:10px;background:var(--flaeche2)}
  .am-probe li{margin:3px 0;font-size:var(--fs-klein)}
  .am-hand li{font-size:var(--fs-klein);color:var(--dim);margin:3px 0}
  @media (max-width:640px){ .am-lauf{flex:1 1 100%} }
</style>

<div class="kopf"><div><h1>Automationen</h1>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">
    Alles, was ohne Klick läuft — <?= $zahl['alle'] ?> Regeln, <?= $zahl['raus'] ?> davon erreichen Kunden, Partner oder bewegen Geld.
    <?php if ($lauf): ?>Letzter Lauf <?= Fmt::h(Fmt::seit($lauf)) ?>.<?php else: ?>Noch kein Lauf.<?php endif; ?>
  </p>
  <div class="am-kopfzahlen">
    <?php if ($zahl['aus'] > 0): ?><span class="marke2 warnung"><?= $zahl['aus'] ?> ausgeschaltet</span><?php endif; ?>
    <?php if ($zahl['fehler'] > 0): ?><span class="marke2 schlecht"><?= $zahl['fehler'] ?> mit Fehler im letzten Lauf</span><?php endif; ?>
  </div></div>
  <?php if (!$notaus['an'] && $darfNotaus): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="automation_notaus"><input type="hidden" name="zurueck" value="automationen">
      <button class="knopf" id="notaus-ziehen" style="border-color:var(--rot);color:var(--rot)">Not-Aus</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($notaus['an']): ?>
  <div class="block" id="notaus" style="border-color:rgba(255,138,138,.45)">
    <h2 style="color:var(--rot)">Not-Aus steht</h2>
    <p style="font-size:var(--fs-klein);margin:0 0 12px">Seit <?= Fmt::h($notaus['am'] !== '' ? Fmt::zeit($notaus['am']) : '—') ?><?= $notaus['von'] !== '' ? ', gezogen von ' . Fmt::h($notaus['von']) : '' ?>.
      Alle Regeln mit „geht raus“ ruhen — und auch Webhooks, Bots und das Telefon schicken nichts. Mails, die dabei entstehen, warten unten auf dich. Prüfungen, Zahlungsabgleich, Sicherung und Entwürfe laufen weiter.</p>
    <?php if ($admin): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="automation_weiter"><input type="hidden" name="zurueck" value="automationen">
        <button class="knopf haupt">Not-Aus lösen</button>
        <span style="color:var(--leise);font-size:var(--fs-klein);margin-left:8px">Danach wird beim nächsten Lauf abgearbeitet, was inzwischen fällig wurde.</span>
      </form>
    <?php else: ?>
      <p style="color:var(--leise);font-size:var(--fs-klein);margin:0">Lösen kann nur der Admin.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php $gehalten = $gehalten ?? []; if ($gehalten): /* AI Office Stufe 0 (06.10.2026): was der Not-Aus zurückhielt */ ?>
  <div class="block" id="gehalten">
    <h2>Zurückgehalten: <?= count($gehalten) ?> Mail<?= count($gehalten) === 1 ? '' : 's' ?></h2>
    <p style="font-size:var(--fs-klein);margin:0 0 12px;color:var(--leise)">Automationen wollten sie während des Not-Aus verschicken. Nichts davon ist draußen.
      <?= $notaus['an'] ? 'Senden geht erst, wenn der Not-Aus gelöst ist.' : 'Du entscheidest je Mail — oder alle auf einmal.' ?></p>
    <?php if ($admin && !$notaus['an']): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0 0 12px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="ausgang_alle_senden"><input type="hidden" name="zurueck" value="automationen">
        <button class="knopf haupt">Alle <?= count($gehalten) ?> Mails jetzt senden</button>
      </form>
    <?php endif; ?>
    <?php foreach ($gehalten as $g): ?>
      <div class="am-regel" id="gehalten-<?= (int) $g['id'] ?>">
        <div class="am-was">
          <b><?= Fmt::h($g['betreff']) ?></b>
          <span class="marke2"><?= Fmt::h($g['anlass']) ?></span>
          <?php if (!empty($g['herkunft'])): ?><span class="marke2" title="Woher die Mail kam">aus <?= Fmt::h($g['herkunft']) ?></span><?php endif; ?>
          <p>An <?= Fmt::h($g['empfaenger']) ?> · <?= Fmt::h(Fmt::zeit((string) $g['created_at'])) ?></p>
        </div>
        <?php if ($admin): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php if (!$notaus['an']): ?>
              <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="ausgang_senden"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="zurueck" value="automationen"><button class="knopf">Mail jetzt senden</button></form>
            <?php endif; ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="ausgang_verwerfen"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="zurueck" value="automationen"><button class="knopf">Mail verwerfen</button></form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php foreach (Automation::BEREICHE as $bk => $bName): if (empty($liste[$bk])) { continue; } ?>
  <div class="block" id="bereich-<?= Fmt::h($bk) ?>">
    <h2><?= Fmt::h($bName) ?></h2>
    <?php foreach ($liste[$bk] as $r): ?>
      <div class="am-regel<?= $r['aus'] ? ' am-aus' : '' ?>" id="<?= Fmt::h($r['regel']) ?>">
        <div class="am-was">
          <b><?= Fmt::h($r['titel']) ?></b>
          <span class="marke2" title="<?= $r['stufe'] === 'B' ? 'Bereitet vor, ein Mensch gibt frei' : 'Läuft allein' ?>">Stufe <?= Fmt::h($r['stufe']) ?></span>
          <?php if ($r['raus']): ?><span class="marke2 warnung" title="Erreicht Kunden, Partner oder die Öffentlichkeit, oder bewegt Geld — der Not-Aus hält sie an">geht raus</span><?php endif; ?>
          <?php if ($r['aus']): ?><span class="marke2 schlecht">aus</span>
          <?php elseif ($r['grund'] !== null): ?><span class="marke2 schlecht"><?= Fmt::h(rtrim($r['grund'], '.')) ?></span><?php endif; ?>
          <p><?= Fmt::h($r['was']) ?></p>
        </div>
        <div class="am-lauf">
          <?php if ($r['zuletzt_am']): ?>
            Zuletzt <?= Fmt::h(Fmt::seit((string) $r['zuletzt_am'])) ?><?= $r['dauer_ms'] !== null && $r['dauer_ms'] >= 1000 ? ' · ' . number_format($r['dauer_ms'] / 1000, 1, ',', '') . ' s' : '' ?><br>
            <?php if ($r['fehler_folge'] > 0): ?><span class="am-fehler">Fehler<?= $r['fehler_folge'] > 1 ? ' (' . $r['fehler_folge'] . '× hintereinander)' : '' ?>: <?= Fmt::h(mb_substr((string) $r['fehler'], 0, 140)) ?></span>
            <?php elseif ((string) $r['ergebnis'] !== ''): ?><?= Fmt::h(mb_substr((string) $r['ergebnis'], 0, 90)) ?><?php endif; ?>
          <?php else: ?>Noch nicht gelaufen<?php endif; ?>
        </div>
        <div class="am-tun">
          <?php if ($r['probe']): ?><a class="knopf" href="<?= Fmt::h(url('automationen') . '?probe=' . rawurlencode($r['regel']) . '#' . rawurlencode($r['regel'])) ?>">Probelauf</a><?php endif; ?>
          <?php if ($admin): ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="automation_schalten">
              <input type="hidden" name="regel" value="<?= Fmt::h($r['regel']) ?>"><input type="hidden" name="an" value="<?= $r['aus'] ? '1' : '0' ?>">
              <button class="knopf"><?= $r['aus'] ? 'Einschalten' : 'Ausschalten' ?></button>
            </form>
          <?php endif; ?>
        </div>
        <?php if ($probeRegel === $r['regel']): ?>
          <div class="am-probe" style="flex:1 1 100%" id="probe">
            <?php if ($probe === null): ?>
              Der Probelauf ließ sich nicht rechnen.
            <?php else: ?>
              <b>Probelauf — so sähe der nächste Lauf jetzt aus.</b> Es wurde nichts gesendet und nichts geändert.
              <?php if (!empty($probe['hinweis'])): ?><p style="margin:6px 0 0;color:var(--leise);font-size:var(--fs-klein)"><?= Fmt::h((string) $probe['hinweis']) ?></p><?php endif; ?>
              <?php if (!$probe['zeilen']): ?>
                <p style="margin:6px 0 0">Niemand — im Moment ist nichts fällig.</p>
              <?php else: ?>
                <ul style="margin:8px 0 0;padding-left:18px">
                  <?php foreach ($probe['zeilen'] as $z): ?><li><b><?= Fmt::h($z['wer']) ?></b> — <?= Fmt::h($z['was']) ?></li><?php endforeach; ?>
                </ul>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<div class="block klapp">
  <h2>Nur von Hand (Stufe C)</h2>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin:-4px 0 8px">Das läuft nie von allein — es gibt dafür keinen Schalter.</p>
  <ul class="am-hand" style="margin:0;padding-left:18px">
    <?php foreach (Automation::VON_HAND as $was => $bk): ?><li><?= Fmt::h($was) ?> <span style="color:var(--leise)">· <?= Fmt::h(Automation::BEREICHE[$bk] ?? $bk) ?></span></li><?php endforeach; ?>
  </ul>
</div>
