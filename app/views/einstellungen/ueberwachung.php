<?php
/* DER TAKTGEBER
   ==========================================================================
   Der Webspace hat keinen Dienst, der von allein läuft. Alles, was ohne
   Klick geschieht — Website-Prüfungen, Erinnerungen, Mahnungen, die
   Gespräche von STRATO —, hängt an dieser einen Adresse und daran, dass der
   KAS sie regelmäßig aufruft. Deshalb steht sie hier bei den Einstellungen
   und nicht nur beim Monitoring: Wer sie vergisst, verliert nicht eine
   Anzeige, sondern jede Automatik. */
?>
<div class="block"><h2>Cronjob im KAS</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin-bottom:12px">
    Der Webspace hat keinen eigenen Dienst, der von allein läuft. Der Anstoß kommt vom
    KAS: Er ruft alle zehn Minuten diese Adresse auf. Ohne den Schlüssel darin passiert nichts.
  </p>
  <div class="feld"><label>Diese Adresse im KAS eintragen</label>
    <input readonly onclick="this.select()" value="<?= Fmt::h((string) $adresse) ?>"></div>
  <ol style="color:var(--dim);font-size:13.5px;line-height:1.9;padding-left:20px;margin:0">
    <li>Im KAS links auf <b>Tools</b> → <b>Cronjobs</b></li>
    <li><b>Neuen Cronjob anlegen</b></li>
    <li>Bei <b>URL</b> die Adresse oben einfügen</li>
    <li>Intervall: <b>alle 10 Minuten</b></li>
    <li>Speichern — fertig</li>
  </ol>
  <p style="color:var(--leise);font-size:12.5px;margin-top:12px">
    Der Schlüssel gehört nicht in eine E-Mail und nicht in einen Chat. Wer ihn hat, kann den
    Lauf anstoßen — mehr nicht, aber das reicht als Grund, ihn für sich zu behalten.
  </p>
  <?php if ($bilanz): ?>
    <p style="color:var(--leise);font-size:12px;margin-top:14px;word-break:break-all">
      Letzte Bilanz: <?= Fmt::h(json_encode($bilanz, JSON_UNESCAPED_UNICODE)) ?></p>
  <?php endif; ?>
</div>
