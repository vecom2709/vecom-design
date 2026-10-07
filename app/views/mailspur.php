<?php
/* MAIL-SPUR (07.10.2026, Uwe: „wenn eine email versendet wurde soll dies auch ganz klar makiert sein
   inklusive des verlaufs was geschrieben wurde“ — „Band + Text aufklappbar“).
   Eingebunden mit require; erwartet $mailSpur (Liste aus MailSpur, neueste zuerst) und optional
   $mailSpurLeer (Satz, wenn nichts verschickt wurde; leer = nichts zeigen).
   Jede Mail ein Band in ihrer Farbe: gesendet grün, zurückgehalten gelb, nicht gesendet rot —
   Datum, Empfänger, Betreff und wodurch sie rausging. Ein Klick zeigt den Text, wie er rausging. */
require_once dirname(__DIR__) . '/src/MailSpur.php';
$mailSpur = $mailSpur ?? [];
if (empty($GLOBALS['mailspur_stil'])): $GLOBALS['mailspur_stil'] = true; ?>
<style>
  .ms-liste{display:flex;flex-direction:column;gap:6px;margin:8px 0 0}
  .ms-band{border:1px solid var(--linie,rgba(255,255,255,.1));border-left:4px solid var(--leise);border-radius:10px;padding:8px 12px;font-size:13.5px;background:rgba(255,255,255,.02)}
  .ms-band.gut{border-left-color:var(--gruen,#5bbf7a)} .ms-band.schlecht{border-left-color:var(--rot,#e46a6a)} .ms-band.warnung{border-left-color:#e8a34a}
  .ms-kopf{display:flex;flex-wrap:wrap;gap:4px 10px;align-items:baseline}
  .ms-kopf b{white-space:nowrap}
  .ms-band.gut .ms-kopf b{color:var(--gruen,#5bbf7a)} .ms-band.schlecht .ms-kopf b{color:var(--rot,#e46a6a)} .ms-band.warnung .ms-kopf b{color:#e8a34a}
  .ms-leise{color:var(--leise);font-size:12.5px}
  .ms-band details{margin-top:4px} .ms-band summary{cursor:pointer;color:var(--dim);font-size:12.5px}
  .ms-text{white-space:pre-wrap;word-break:break-word;font-size:13px;line-height:1.55;margin:8px 0 4px;padding:10px 12px;border-radius:8px;background:rgba(0,0,0,.18);max-height:420px;overflow:auto}
</style>
<?php endif; ?>
<?php if (!$mailSpur): ?>
  <?php if (!empty($mailSpurLeer)): ?><p class="ms-leise" style="margin:6px 0 0"><?= Fmt::h((string) $mailSpurLeer) ?></p><?php endif; ?>
<?php else: ?>
  <div class="ms-liste">
    <?php foreach ($mailSpur as $ms): [$msWort, $msTon] = MailSpur::STATUS[$ms['status']] ?? ['✉ ' . $ms['status'], '']; ?>
      <div class="ms-band <?= Fmt::h($msTon) ?>" data-mail="<?= (int) $ms['id'] ?>">
        <div class="ms-kopf"><b><?= Fmt::h($msWort) ?></b>
          <span><?= Fmt::h(Fmt::zeit($ms['zeit'])) ?> an <?= Fmt::h($ms['an']) ?></span>
          <span>„<?= Fmt::h($ms['betreff'] !== '' ? $ms['betreff'] : 'ohne Betreff') ?>“</span></div>
        <?php if ($ms['wer'] !== '' || ($ms['status'] !== 'gesendet' && $ms['fehler'] !== '')): ?>
          <div class="ms-leise"><?= $ms['wer'] !== '' ? Fmt::h(ucfirst($ms['wer'])) : '' ?><?= $ms['status'] !== 'gesendet' && $ms['fehler'] !== '' ? ($ms['wer'] !== '' ? ' · ' : '') . '<span style="color:var(--rot)">' . Fmt::h($ms['fehler']) . '</span>' : '' ?></div>
        <?php endif; ?>
        <?php if ($ms['text'] !== '' || $ms['anhaenge']): ?>
          <details><summary>Text ansehen, wie er rausging</summary>
            <?php if ($ms['text'] !== ''): ?><div class="ms-text"><?= Fmt::h($ms['text']) ?></div><?php endif; ?>
            <?php if ($ms['anhaenge']): ?><div class="ms-leise">Anhänge: <?= Fmt::h(implode(', ', array_map(static fn($a) => (string) ($a['name'] ?? ''), $ms['anhaenge']))) ?></div><?php endif; ?>
            <?php if ($ms['kunde']): ?><a class="ms-leise" href="<?= Fmt::h(url('kunden/' . (int) $ms['kunde'] . '/mail/' . (int) $ms['id'])) ?>">Mit Briefbogen in der Kundenakte ansehen →</a><?php endif; ?>
          </details>
        <?php else: ?>
          <div class="ms-leise">Text nicht gespeichert (vor dem 07.10.2026 verschickt).</div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
