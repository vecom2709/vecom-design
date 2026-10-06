<?php
/* Modul H: Kampagne als Arbeitsfilter (Heute, Pipeline, Nächster bester Kontakt). Bleibt in der Sitzung, bis „alle“ gewählt wird.
   Daten: $kampagne (?int), $kampagnen (aktive), $kwZiel (Seite), $kwInForm (true = nur das Feld, steht in einem vorhandenen GET-Formular). */
$kwInForm = $kwInForm ?? false;
if (!$kampagnen && !$kampagne) { return; }
$kwFeld = '<div class="feld"><label for="kw-k">Kampagne</label><select id="kw-k" name="kampagne"' . ($kwInForm ? '' : ' onchange="this.form.submit()"') . '><option value="0">alle Betriebe</option>';
foreach ($kampagnen as $kw) { $kwFeld .= '<option value="' . (int) $kw['id'] . '"' . ((int) $kampagne === (int) $kw['id'] ? ' selected' : '') . '>' . Fmt::h((string) $kw['name']) . ' (' . (int) $kw['n'] . ')</option>'; }
$kwFeld .= '</select></div>';
if ($kwInForm) { echo $kwFeld; return; }
?>
<form method="get" action="<?= Fmt::h(url($kwZiel)) ?>" class="kw-leiste" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:0 0 14px">
  <?= $kwFeld ?><button class="knopf klein">Anwenden</button>
  <?php if ($kampagne): ?><a class="knopf klein" href="<?= Fmt::h(url($kwZiel . '?kampagne=0')) ?>">Alle Betriebe</a><span class="akq-klein" style="padding-bottom:8px">Nur Betriebe dieser Kampagne — gilt auch für „Nächster bester Kontakt“ und die Pipeline.</span><?php endif; ?>
</form>
