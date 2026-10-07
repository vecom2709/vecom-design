<?php
/* Nachrichten-Werkstatt (Akquise-CRM Modul D, 06.10.2026): Prüfliste ✅/⚠/⛔ und Vorschau unter einem Text.
   Eingebunden aus akquise_ansprechen.php mit $ws = ['kanal' => email|whatsapp, 'senden' => bool, 'betreff' => …, 'text' => …,
   'betreffFeld' => id, 'textFeld' => id]. Die Liste rechnet der Server (AkquiseWerkstatt) — beim Laden hier, beim Tippen
   über tat=akq_werkstatt. Dieselbe Prüfung läuft noch einmal beim Senden; die Anzeige ist Hilfe, das Schloss sitzt dort. */
require_once dirname(__DIR__) . '/src/AkquiseWerkstatt.php';
$wsListe = AkquiseWerkstatt::pruefliste($f, $ws['kanal'], (string) $ws['betreff'], (string) $ws['text'], (bool) $ws['senden']);
$wsStopp = AkquiseWerkstatt::zahl($wsListe, AkquiseWerkstatt::STOPP);
$wsHin = AkquiseWerkstatt::zahl($wsListe, AkquiseWerkstatt::HINWEIS);
$wsZeichen = [AkquiseWerkstatt::OK => '✅', AkquiseWerkstatt::HINWEIS => '⚠', AkquiseWerkstatt::STOPP => '⛔'];
$wsV = $ws['kanal'] === 'email' ? AkquiseWerkstatt::vorschau($f, (string) $ws['betreff'], (string) $ws['text']) : null;
?>
<?php if (empty($GLOBALS['wsStil'])): $GLOBALS['wsStil'] = true; ?>
<style>
  .ws{border:1px solid var(--linie);border-radius:12px;padding:10px 12px;margin:8px 0;background:var(--flaeche2)}
  .ws-kopf{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;font-size:13.5px}
  .ws-kopf b{font-size:14px}
  .ws-zahl{font-size:var(--fs-klein);color:var(--dim);font-variant-numeric:tabular-nums}
  .ws-liste{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-direction:column;gap:3px}
  .ws-liste li{display:flex;gap:8px;font-size:13.5px;line-height:1.45;color:var(--dim)}
  .ws-liste li span{flex:none;width:1.3em;text-align:center}
  .ws-liste li.ws-stopp{color:#ff9b9b} .ws-liste li.ws-hinweis{color:var(--gelb)} .ws-liste li.ws-leer{color:#7fe0a8}
  .ws-liste li.ws-ok{display:none} .ws.alle .ws-liste li.ws-ok{display:flex}
  .ws-mehr{font:inherit;font-size:var(--fs-klein);color:var(--cyan);background:none;border:0;padding:0;cursor:pointer;margin-top:6px}
  .ws-gelesen{display:flex;gap:8px;align-items:flex-start;font-size:13.5px;margin:10px 0 0;color:var(--text)}
  .ws-gelesen[hidden]{display:none}
  .ws-toene{display:flex;gap:6px;align-items:center;flex-wrap:wrap;font-size:var(--fs-klein);color:var(--dim);margin:0 0 10px;padding-bottom:10px;border-bottom:1px solid var(--linie)}
  .ws-toene small{flex-basis:100%;font-size:var(--fs-klein);color:var(--leise)}
  .ws-vs{border:1px solid rgba(241,211,139,.35);border-radius:10px;padding:10px 12px;margin:0 0 10px;background:var(--flaeche)}
  .ws-vs[hidden]{display:none}
  .ws-vs-kopf{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;font-size:13.5px}
  .ws-vs-kopf span{color:var(--dim);font-size:var(--fs-klein)}
  .ws-vs-grund{color:#ff9b9b;font-size:var(--fs-klein);margin:6px 0 0}
  .ws-vs-betreff{font-size:13.5px;margin:8px 0 4px} .ws-vs-betreff span{color:var(--leise)}
  .ws-vs pre{white-space:pre-wrap;font:inherit;font-size:13.5px;line-height:1.55;margin:6px 0;max-height:300px;overflow:auto}
  .ws-vs-knoepfe{display:flex;gap:6px;align-items:center;flex-wrap:wrap} .ws-vs-knoepfe small{font-size:var(--fs-klein);color:var(--leise)}
  [data-ws-vs-inhalt][hidden],[data-ws-vs-zu][hidden],.ws-vs-grund[hidden]{display:none}
  form:has(.ws) button.haupt:disabled{opacity:.4;filter:grayscale(1);cursor:not-allowed}
  .ws-gelesen input{width:auto;flex:none;margin-top:3px}
  .ws-vorschau{margin-top:10px;border-top:1px solid var(--linie);padding-top:8px}
  .ws-vorschau summary{cursor:pointer;font-size:13.5px;font-weight:650}
  .ws-vorschau dl{display:grid;grid-template-columns:auto 1fr;gap:2px 10px;font-size:var(--fs-klein);margin:8px 0}
  .ws-vorschau dt{color:var(--leise)} .ws-vorschau dd{margin:0;overflow-wrap:anywhere}
  .ws-vorschau pre{white-space:pre-wrap;font:inherit;font-size:13.5px;line-height:1.55;background:var(--flaeche);border:1px solid var(--linie);border-radius:10px;padding:10px 12px;margin:0;max-height:340px;overflow:auto}
</style>
<?php endif; ?>
<div class="ws" data-werkstatt data-kanal="<?= Fmt::h($ws['kanal']) ?>" data-firma="<?= (int) $f['id'] ?>" data-senden="<?= $ws['senden'] ? '1' : '0' ?>"
     data-betreff="<?= Fmt::h((string) ($ws['betreffFeld'] ?? '')) ?>" data-text="<?= Fmt::h((string) $ws['textFeld']) ?>" data-url="<?= Fmt::h(url('akquise')) ?>" aria-live="polite">
  <?php $wsVs = AkquiseWerkstatt::vorschlag((int) $f['id'], $ws['kanal']); /* D-2: Töne über den PC */ ?>
  <div class="ws-toene"><span>Umformulieren lassen:</span>
    <?php foreach (AkquiseWerkstatt::TOENE as $wsTk => [$wsTw]): ?><button type="button" class="knopf klein" data-ws-ton="<?= $wsTk ?>"><?= Fmt::h($wsTw) ?></button><?php endforeach; ?>
    <small>Claude auf deinem PC · kommt in ein paar Minuten · nichts wird gesendet</small></div>
  <div class="ws-vs" data-ws-vs<?= $wsVs ? '' : ' hidden' ?> data-id="<?= (int) ($wsVs['id'] ?? 0) ?>" data-stand="<?= Fmt::h((string) ($wsVs['stand'] ?? '')) ?>">
    <div class="ws-vs-kopf"><b data-ws-vs-titel><?= $wsVs ? Fmt::h('Vorschlag „' . $wsVs['wort'] . '“') : '' ?></b>
      <span data-ws-vs-stand><?= $wsVs ? Fmt::h(['wartet' => 'wartet auf deinen PC …', 'laeuft' => 'Claude schreibt …', 'fertig' => 'bereit', 'fehler' => 'nicht geklappt'][$wsVs['stand']]) : '' ?></span></div>
    <p class="ws-vs-grund" data-ws-vs-grund<?= ($wsVs['grund'] ?? '') !== '' ? '' : ' hidden' ?>><?= Fmt::h((string) ($wsVs['grund'] ?? '')) ?></p>
    <div data-ws-vs-inhalt<?= ($wsVs['stand'] ?? '') === 'fertig' ? '' : ' hidden' ?>>
      <?php if ($ws['kanal'] === 'email'): ?><p class="ws-vs-betreff"><span>Betreff:</span> <b data-ws-vs-betreff><?= Fmt::h((string) ($wsVs['betreff'] ?? '')) ?></b></p><?php endif; ?>
      <pre data-ws-vs-text><?= Fmt::h((string) ($wsVs['text'] ?? '')) ?></pre>
      <div class="ws-vs-knoepfe"><button type="button" class="knopf klein" data-ws-vs-nehmen>Übernehmen</button>
        <button type="button" class="knopf klein" data-ws-vs-weg>Verwerfen</button>
        <small>Übernehmen ersetzt den Text oben — danach läuft die Prüfung neu.</small></div>
    </div>
    <div class="ws-vs-knoepfe" data-ws-vs-zu<?= in_array($wsVs['stand'] ?? '', ['fehler'], true) ? '' : ' hidden' ?>><button type="button" class="knopf klein" data-ws-vs-weg>Schließen</button></div>
  </div>
  <div class="ws-kopf"><b>Prüfung vor dem <?= $ws['senden'] ? 'Senden' : 'Kopieren' ?></b>
    <span class="ws-zahl" data-ws-zahl><?= $wsStopp ? '⛔ ' . $wsStopp . ' · ' : '' ?>⚠ <?= $wsHin ?> · ✅ <?= count($wsListe) - $wsStopp - $wsHin ?></span></div>
  <ul class="ws-liste" data-ws-liste>
    <?php if ($wsStopp + $wsHin === 0): ?><li class="ws-leer"><span aria-hidden="true">✅</span>Nichts offen — alle Punkte erfüllt.</li><?php endif; ?>
    <?php foreach ($wsListe as $wsX): ?><li class="ws-<?= $wsX['stufe'] ?>"><span aria-hidden="true"><?= $wsZeichen[$wsX['stufe']] ?></span><?= Fmt::h($wsX['text']) ?></li><?php endforeach; ?>
  </ul>
  <button type="button" class="ws-mehr" data-ws-alle>Alles Erfüllte zeigen</button>
  <?php if ($ws['senden']): ?>
    <label class="ws-gelesen" data-ws-gelesen<?= $wsHin ? '' : ' hidden' ?>><input type="checkbox" name="hinweise_gelesen" value="1"<?= $wsHin ? ' required' : '' ?>>
      Hinweise gelesen — so soll die Nachricht raus.</label>
  <?php endif; ?>
  <?php if ($wsV): ?>
    <details class="ws-vorschau"><summary>Vorschau: so kommt sie an</summary>
      <dl><dt>Von</dt><dd data-ws-v="von"><?= Fmt::h($wsV['von']) ?></dd>
        <dt>An</dt><dd data-ws-v="an"><?= Fmt::h($wsV['an']) ?></dd>
        <?php if ($wsV['antwort']): ?><dt>Antwort an</dt><dd><?= Fmt::h($wsV['antwort']) ?></dd><?php endif; ?>
        <dt>Betreff</dt><dd data-ws-v="betreff"><?= Fmt::h($wsV['betreff'] !== '' ? $wsV['betreff'] : '— fehlt —') ?></dd></dl>
      <pre data-ws-v="text"><?= Fmt::h($wsV['text']) ?></pre>
      <p class="akq-klein" style="margin:6px 0 0">Der Abmeldelink bekommt beim Senden ein eigenes Zeichen je Empfänger.</p>
    </details>
  <?php endif; ?>
</div>
