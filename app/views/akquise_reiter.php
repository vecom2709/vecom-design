<?php
/* Reiter der Akquise und die Notbremse -- auf jeder Akquise-Seite gleich.
   Die Notbremse steht hier und nicht versteckt in den Einstellungen: Wer
   merkt, dass etwas schieflaeuft, soll nicht erst suchen muessen. */
$akqTeil = $akqTeil ?? '';
$akqG = AkquiseGate::grenzen();
$reiter = ['' => 'Betriebe', 'recherche' => 'Suchaufträge', 'regeln' => 'Regeln & Versand', 'protokoll' => 'Protokoll'];
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
  /* --- Einfache Sprache (26.09.2026): Chance, Ampel, fuenf Stufen, ein naechster Schritt --- */
  .akq-chance{display:inline-flex;gap:6px;align-items:baseline;padding:3px 10px;border-radius:999px;font-size:13px;white-space:nowrap;
    background:var(--flaeche2);border:1px solid var(--linie);color:var(--dim)}
  .akq-chance b{font-size:14px;color:var(--text)}
  .akq-chance.s-top,.akq-chance.s-sehr_interessant{border-color:rgba(241,211,139,.5);color:var(--cyan)}
  .akq-chance.s-top b,.akq-chance.s-sehr_interessant b{color:var(--cyan)}
  .akq-chance.s-interessant{border-color:var(--linie2)}
  .akq-ampel{display:inline-flex;gap:7px;align-items:center;font-size:13px;color:var(--dim);white-space:nowrap}
  .akq-ampel i{width:11px;height:11px;border-radius:50%;background:var(--leise);flex:none;box-shadow:0 0 0 3px rgba(255,255,255,.04)}
  .akq-ampel.gruen i{background:var(--gruen)} .akq-ampel.gelb i{background:#f5c542} .akq-ampel.rot i{background:var(--rot)}
  .akq-ampel.klein span{font-size:12px;color:var(--leise)}
  .akq-stufen5{display:flex;gap:0;list-style:none;margin:12px 0 0;padding:0;flex-wrap:wrap;align-items:center}
  .akq-stufen5 li{display:block;font-size:12.5px;line-height:1.4;padding:5px 12px;color:var(--leise);border:1px solid var(--linie);margin-left:-1px}
  .akq-stufen5 li:first-child{border-radius:999px 0 0 999px;margin-left:0} .akq-stufen5 li:last-child{border-radius:0 999px 999px 0}
  .akq-stufen5 li.st-fertig{color:var(--dim);background:rgba(241,211,139,.05)}
  .akq-stufen5 li.st-jetzt{color:var(--grund);background:var(--cyan);border-color:var(--cyan);font-weight:650}
  .akq-kopf{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}
  .akq-zeile{display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-top:8px}
  .akq-schritt{display:flex;flex-direction:column;gap:6px;align-items:flex-end}
  .akq-schritt .knopf{min-height:44px;padding:10px 20px;font-size:15px}
  .akq-unterreiter{display:flex;gap:4px;border-bottom:1px solid var(--linie);margin:0 0 16px;overflow-x:auto}
  .akq-unterreiter a{padding:9px 14px;color:var(--dim);font-size:14px;border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap}
  .akq-unterreiter a.an{color:var(--text);border-bottom-color:var(--cyan)}
  .akq-beleg{display:inline-block;font-size:11.5px;padding:1px 8px;border-radius:999px;border:1px solid var(--linie);font-weight:500;vertical-align:1px}
  .akq-beleg.ja{color:var(--gruen);border-color:rgba(74,222,128,.35)} .akq-beleg.nein{color:#f5c542;border-color:rgba(245,197,66,.35);border-style:dashed}
  .akq-drei{margin:0 0 10px;padding-left:20px} .akq-drei li{margin:0 0 10px}
  .akq-fokus{border-color:rgba(241,211,139,.45)}
  .akq-haken{display:flex;gap:9px;align-items:flex-start;font-size:13.5px;margin:0 0 12px;color:var(--dim);cursor:pointer}
  .akq-haken input{width:auto;margin-top:3px;flex:none}
  .akq-weg{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:12px}
  .akq-kat{font-size:12px;text-transform:uppercase;letter-spacing:.07em;color:var(--leise);margin:14px 0 8px}
  .knopf.klein{font-size:12px;padding:4px 10px;min-height:0}
  .knopf.akq-los{border-color:rgba(241,211,139,.55);color:var(--cyan);white-space:nowrap}
  .knopf.akq-los:hover{background:rgba(241,211,139,.1)}
  .akq-los-zeile{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
  .akq-los-zeile form{margin:0}
  .akq-stufe{display:inline-block;margin-top:5px;font-size:11.5px;padding:1px 8px;border-radius:999px;border:1px solid var(--linie);color:var(--leise)}
  .akq-stufe.st-bereit{color:var(--cyan);border-color:rgba(241,211,139,.4)} .akq-stufe.st-antwort{color:var(--gruen);border-color:rgba(74,222,128,.4)}
  .akq-chip{display:inline-block;margin:2px 4px 2px 0;padding:2px 10px;border-radius:999px;background:var(--flaeche2);border:1px solid var(--linie);color:var(--text)}
  .akq-suchzeile{display:grid;grid-template-columns:150px 1fr auto;gap:10px;align-items:end}
  .akq-suchzeile label,.akq-filter label{font-size:11.5px;color:var(--leise);display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.05em}
  .akq-suchzeile .knopf{min-height:42px}
  .akq-branchen{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:4px 12px;margin-top:8px;font-size:13.5px}
  .akq-branchen label{display:flex;gap:7px;align-items:center;color:var(--dim)} .akq-branchen input{width:auto}
  .akq-filter .akq-stark{display:flex;align-items:flex-end} .akq-filter .akq-stark .akq-haken{margin:0 0 10px;text-transform:none;letter-spacing:0;font-size:13.5px;color:var(--dim)}
  .akq-kacheln a.karte{color:inherit;transition:border-color .18s var(--e)} .akq-kacheln a.karte:hover{border-color:var(--linie2)}
  .akq-tab td:last-child{min-width:220px}
  @media (max-width:700px){ .akq-filter .breit{grid-column:span 1} .akq-reiter .rechts{margin-left:0;width:100%}
    .akq-suchzeile{grid-template-columns:1fr} .akq-schritt{align-items:flex-start} }
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
