<?php
/* Claude fragt um Lesezugang (AI Office Stufe 2, 07.10.2026). Daten: $anfrage (oder null), $an.
   Ein Ding auf dieser Seite: Erlauben ist der eine laute Knopf. Was erlaubt wird, steht
   davor in ganzen Sätzen — nicht „Sind Sie sicher?“, sondern wer, was, wie lange. */
$anfrage = $anfrage ?? null;
$wohin = $anfrage ? (string) parse_url((string) $anfrage['redirect_uri'], PHP_URL_HOST) : '';
?>
<div class="kopf"><div><h1>Claude-Zugang erlauben</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Claude möchte deine Verwaltung lesen.</p></div></div>

<?php if (!$an): ?>
  <div class="block"><p style="margin:0">Der Claude-Zugang ist ausgeschaltet. Einschalten kannst du ihn unter
    <a href="<?= Fmt::h(url('einstellungen?b=claude')) ?>">Einstellungen → Claude-Zugang</a>; danach in Claude noch einmal verbinden.</p></div>
<?php elseif ($anfrage === null): ?>
  <div class="block"><p style="margin:0">Diese Anfrage gilt nicht mehr — sie hält zehn Minuten und lässt sich nur einmal beantworten.
    In Claude einfach noch einmal „Verbinden“ wählen.</p></div>
<?php else: ?>
  <div class="block ce-karte">
    <table class="schlicht ce-tabelle"><tbody>
      <tr><td>Wer fragt</td><td><b><?= Fmt::h((string) $anfrage['client_name']) ?></b> — die Antwort geht an <b><?= Fmt::h($wohin) ?></b></td></tr>
      <tr><td>Was Claude darf</td><td><b>Nur lesen</b>: Kunden mit Namen und Kontakt, Projekte, Angebote, Zahlungen und Rechnungen,
        Meldungen, Akquise und Termine, Überwachung, AI Freigaben, die Prüfspur und das Wissen aus PROJEKT.md.</td></tr>
      <tr><td>Was Claude nicht darf</td><td>Nichts ändern, nichts senden, nichts freigeben. Passwörter, Schlüssel, Kundenlinks,
        Zahlmittel und Zugangsdaten gibt die Verwaltung nie heraus.</td></tr>
      <tr><td>Wie lange</td><td><?= (int) ClaudeZugang::TAGE ?> Tage, bis <?= Fmt::h(date('d.m.Y', time() + ClaudeZugang::TAGE * 86400)) ?>. Danach fragt Claude neu.
        Entziehen geht jederzeit sofort unter Einstellungen → Claude-Zugang.</td></tr>
      <tr><td>Was festgehalten wird</td><td>Jeder Griff von Claude steht mit Uhrzeit und Werkzeug in den Einstellungen.</td></tr>
    </tbody></table>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:16px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="claude_erlauben"><input type="hidden" name="a" value="<?= Fmt::h((string) $anfrage['id']) ?>">
      <button class="knopf haupt">Claude Lesezugang erlauben</button>
    </form>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="claude_ablehnen"><input type="hidden" name="a" value="<?= Fmt::h((string) $anfrage['id']) ?>">
      <button class="knopf">Ablehnen</button>
    </form>
  </div>
<?php endif; ?>

<style>
  .ce-karte{max-width:720px}
  .ce-tabelle td{vertical-align:top;line-height:1.55;padding-top:9px;padding-bottom:9px}
  .ce-tabelle td:first-child{width:30%;color:var(--dim)}
</style>
