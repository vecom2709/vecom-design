<?php
/* Reiter der Akquise und die Notbremse -- auf jeder Akquise-Seite gleich.
   Die Notbremse steht hier und nicht versteckt in den Einstellungen: Wer
   merkt, dass etwas schieflaeuft, soll nicht erst suchen muessen. */
$akqTeil = $akqTeil ?? '';
$akqG = AkquiseGate::grenzen();
$reiter = ['' => 'Leads', 'recherche' => 'Recherche', 'regeln' => 'Compliance & Versand', 'protokoll' => 'Protokoll'];
?>
<style>
  .akq-reiter{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:0 0 16px}
  .akq-reiter a{padding:7px 13px;border-radius:999px;border:1px solid var(--linie);color:var(--dim);font-size:13.5px}
  .akq-reiter a.an{background:var(--flaeche2);color:var(--text);border-color:var(--linie2)}
  .akq-reiter .rechts{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .akq-score{display:inline-grid;place-items:center;min-width:40px;height:26px;padding:0 8px;border-radius:8px;font-weight:650;font-size:13px;
    background:var(--flaeche2);border:1px solid var(--linie)}
  .akq-score.s-top{background:rgba(31,232,255,.14);border-color:rgba(31,232,255,.45);color:var(--cyan)}
  .akq-score.s-sehr_interessant{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.4);color:var(--gruen)}
  .akq-score.s-interessant{background:rgba(251,191,36,.1);border-color:rgba(251,191,36,.35);color:var(--gelb)}
  .akq-score.s-beobachten,.akq-score.s-gering{color:var(--dim)}
  .akq-klein{font-size:12.5px;color:var(--leise)}
  .akq-probleme{margin:0;padding-left:16px;font-size:12.5px;color:var(--dim);max-width:340px}
  .akq-probleme li{margin:1px 0}
  .akq-filter{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;margin-bottom:12px}
  .akq-filter .breit{grid-column:span 2}
  .akq-filter label{font-size:11.5px;color:var(--leise);display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.05em}
  .akq-tab td{vertical-align:top}
  .akq-tab tr:hover td{background:rgba(150,180,230,.035)}
  .akq-befund{border:1px solid var(--linie);border-radius:12px;padding:12px 14px;margin-bottom:10px;background:var(--flaeche2)}
  .akq-befund.unbelegt{border-style:dashed;opacity:.85}
  .akq-befund h4{font-size:14.5px;margin:0 0 4px;display:flex;gap:8px;align-items:baseline;flex-wrap:wrap}
  .akq-befund p{font-size:13.5px;color:var(--dim);margin:4px 0 0}
  .akq-befund .beleg{font-family:ui-monospace,Menlo,monospace;font-size:12px;color:var(--leise);white-space:pre-wrap;word-break:break-word;margin-top:6px}
  .akq-schwere{display:inline-flex;gap:2px}
  .akq-schwere i{width:6px;height:10px;border-radius:2px;background:var(--linie2);display:inline-block}
  .akq-schwere i.an{background:var(--gelb)}
  .akq-teile{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}
  .akq-teil{background:var(--flaeche2);border:1px solid var(--linie);border-radius:10px;padding:9px 11px}
  .akq-teil b{font-size:18px}
  .akq-teil .balken{height:5px;background:var(--linie);border-radius:3px;margin-top:6px;overflow:hidden}
  .akq-teil .balken span{display:block;height:100%;background:linear-gradient(90deg,var(--blau),var(--cyan))}
  .akq-stufen{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0 0}
  .akq-stufen span{font-size:12.5px;padding:5px 10px;border-radius:999px;border:1px solid var(--linie);color:var(--leise)}
  .akq-stufen span.fertig{color:var(--gruen);border-color:rgba(74,222,128,.35)}
  .akq-stufen span.jetzt{color:var(--text);border-color:var(--cyan);box-shadow:0 0 0 1px rgba(31,232,255,.25) inset}
  .akq-foto{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start}
  .akq-foto img{border-radius:12px;border:1px solid var(--linie);display:block;max-width:100%;height:auto}
  .akq-text{font-family:inherit;min-height:340px;line-height:1.55}
  .akq-hinweise{margin:8px 0 0;padding-left:18px;color:var(--rot);font-size:13px}
  @media (max-width:700px){ .akq-filter .breit{grid-column:span 1} .akq-reiter .rechts{margin-left:0;width:100%} }
</style>
<nav class="akq-reiter" aria-label="Akquise">
  <?php foreach ($reiter as $ziel => $wort): ?>
    <a href="<?= Fmt::h(url('akquise' . ($ziel !== '' ? '/' . $ziel : ''))) ?>" class="<?= $akqTeil === $ziel ? 'an' : '' ?>"><?= Fmt::h($wort) ?></a>
  <?php endforeach; ?>
  <div class="rechts">
    <?php if ($akqG['stop']): ?>
      <span class="marke2 schlecht">Notbremse gezogen — nichts geht raus</span>
    <?php elseif (!$akqG['versand_an']): ?>
      <span class="marke2">E-Mail-Versand aus</span>
    <?php else: ?>
      <span class="marke2 warnung">E-Mail-Versand an · <?= (int) $akqG['tag'] ?>/Tag</span>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>"
          <?= $akqG['stop'] ? '' : 'data-frage="Alle Aussendungen sofort stoppen? Nichts geht mehr raus, bis du die Bremse wieder löst." data-ja="Ja, alles stoppen"' ?>>
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_notbremse">
      <input type="hidden" name="zurueck" value="<?= Fmt::h('akquise' . ($akqTeil !== '' ? '/' . $akqTeil : '')) ?>">
      <?php if ($akqG['stop']): ?>
        <button class="knopf">Notbremse lösen</button>
      <?php else: ?>
        <input type="hidden" name="ziehen" value="1"><button class="knopf">Notbremse</button>
      <?php endif; ?>
    </form>
  </div>
</nav>
