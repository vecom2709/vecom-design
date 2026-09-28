<?php
/** @var array $beitraege @var array $meta @var array $leads */
/* BEITRÄGE FÜR FACEBOOK UND INSTAGRAM (28.09.2026, Uwe: Ja zu Z4).
   Montag und Donnerstag legt das System einen Entwurf an. Uwe liest,
   ändert auf Wunsch den Text und drückt „Freigeben & posten“ -- erst dann
   geht etwas raus. */
$akqTeil = 'beitraege';
$btFarbe = ['entwurf' => 'var(--cyan)', 'gepostet' => 'var(--gruen)', 'fehler' => 'var(--rot)', 'verworfen' => 'var(--leise)'];
$btWort = ['entwurf' => 'wartet auf dich', 'gepostet' => 'gepostet', 'fehler' => 'nicht geklappt', 'verworfen' => 'verworfen'];
?>
<div class="kopf"><div><h1>Beiträge</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Zweimal die Woche liegt hier ein fertiger Beitrag für deine Facebook-Seite und Instagram: ein Satz als Bild, ein kurzer Text,
    der Link auf die kostenlose Analyse. Nichts geht von selbst raus — erst dein Klick auf „Freigeben &amp; posten“.</p></div></div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<style>
  .bt-liste{display:grid;gap:14px}
  .bt{display:grid;grid-template-columns:220px 1fr;gap:16px;align-items:start}
  .bt img{width:220px;height:220px;border-radius:12px;border:1px solid var(--linie);display:block;background:#0b0a09}
  .bt textarea{width:100%;min-height:190px;font:inherit;line-height:1.5}
  .bt .zeile{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px}
  .bt-neu{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.bt-neu select{width:auto;min-width:0}
  @media (max-width:700px){.bt{grid-template-columns:1fr}.bt img{width:100%;height:auto;aspect-ratio:1}}
</style>

<?php if (!$meta['bereit']): ?>
  <div class="block" style="border-color:rgba(241,211,139,.45)"><p style="margin:0">Die Facebook-Seite ist noch nicht verbunden. Die Entwürfe entstehen trotzdem; posten geht, sobald Seiten-ID und Schlüssel unter
    <a href="<?= Fmt::h(url('akquise/regeln#wege')) ?>">Regeln &amp; Versand → Wege zum Ja</a> eingetragen sind. Bis dahin kannst du Bild und Text auch von Hand posten (Bild antippen → speichern).</p></div>
<?php endif; ?>

<div class="block">
  <div class="akq-los-zeile">
    <h2 style="margin:0">Entwürfe</h2>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" class="bt-neu">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_beitrag_neu">
      <select name="code" aria-label="Thema"><option value="">Nächstes Thema</option>
        <?php foreach (MetaSeite::THEMEN as $c => $t): ?><option value="<?= Fmt::h($c) ?>"><?= Fmt::h($t['de'][0]) ?></option><?php endforeach; ?></select>
      <select name="sprache" aria-label="Sprache"><option value="it">Italienisch</option><option value="de">Deutsch</option></select>
      <button class="knopf">Neuen Entwurf anlegen</button>
    </form>
  </div>
  <?php if (!$beitraege): ?><p class="akq-klein" style="margin-top:12px">Noch keine Beiträge. Der erste entsteht am nächsten Montag oder Donnerstag — oder jetzt mit dem Knopf.</p><?php endif; ?>
  <div class="bt-liste" style="margin-top:14px">
    <?php foreach ($beitraege as $b): $offen = in_array($b['status'], ['entwurf', 'fehler'], true); ?>
      <div class="bt">
        <a href="<?= Fmt::h(MetaSeite::bildAdresse($b)) ?>" target="_blank" rel="noopener"><img src="<?= Fmt::h('/beitrag.php?t=' . $b['token']) ?>" alt="<?= Fmt::h((string) $b['titel']) ?>" width="220" height="220" loading="lazy"></a>
        <div>
          <p class="akq-klein" style="margin:0 0 6px"><span style="color:<?= $btFarbe[$b['status']] ?? 'inherit' ?>;font-weight:650"><?= Fmt::h($btWort[$b['status']] ?? $b['status']) ?></span>
            · <?= strtoupper(Fmt::h((string) $b['sprache'])) ?> · <?= Fmt::h(Fmt::datum((string) $b['created_at'])) ?>
            <?= $b['gepostet_am'] ? ' · gepostet ' . Fmt::h(Fmt::datum((string) $b['gepostet_am'])) : '' ?>
            <?= $b['fb_id'] ? ' · Facebook ✓' : '' ?><?= $b['ig_id'] ? ' · Instagram ✓' : '' ?></p>
          <?php if ($b['grund']): ?><p class="akq-hinweise" style="list-style:none;padding:0;margin:0 0 6px"><?= Fmt::h((string) $b['grund']) ?></p><?php endif; ?>
          <?php if ($offen): ?>
            <form method="post" action="<?= Fmt::h(url('akquise')) ?>">
              <?= Csrf::feld() ?><input type="hidden" name="beitrag" value="<?= (int) $b['id'] ?>">
              <textarea name="text" aria-label="Text des Beitrags"><?= Fmt::h((string) $b['text']) ?></textarea>
              <div class="zeile">
                <button class="knopf haupt" name="tat" value="akq_beitrag_posten"<?= $meta['bereit'] ? '' : ' disabled title="Erst die Facebook-Seite verbinden"' ?>>Freigeben &amp; posten</button>
                <button class="knopf" name="tat" value="akq_beitrag_verwerfen">Verwerfen</button>
              </div>
            </form>
          <?php else: ?>
            <p style="white-space:pre-wrap;margin:0;font-size:14px;color:var(--dim)"><?= Fmt::h((string) $b['text']) ?></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="block" id="formular">
  <h2>Werbeformular — letzte Meldungen</h2>
  <?php if (!$leads): ?><p class="akq-klein">Noch keine. Sobald jemand das Formular deiner Werbeanzeige ausfüllt, steht es hier — und die Bestätigungsmail ist schon unterwegs.</p>
  <?php else: ?>
    <table><thead><tr><th>Wann</th><th>Ergebnis</th></tr></thead><tbody>
      <?php foreach ($leads as $l): ?><tr><td class="akq-klein"><?= Fmt::h(Fmt::datum((string) $l['created_at'])) ?></td>
        <td class="akq-klein" style="color:<?= $l['status'] === 'ok' ? 'var(--gruen)' : ($l['status'] === 'neu' ? 'inherit' : 'var(--rot)') ?>"><?= Fmt::h($l['status'] === 'ok' ? 'Bestätigungsmail verschickt' : ((string) ($l['grund'] ?: $l['status']))) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
