<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('muster')) ?>">Bausteine</a></div>
  <h1><?= $m ? Fmt::h((string) $m['name']) : 'Neuer Baustein' ?></h1></div>
  <?php if ($m): ?>
    <div class="rechts">
      <button class="knopf" data-kopieren="musterinhalt">Inhalt kopieren</button>
    </div>
  <?php endif; ?>
</div>

<div class="block">
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="muster_speichern">
    <?php if ($m): ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><?php endif; ?>

    <label>Name
      <input name="name" required maxlength="160" placeholder="Öffnungszeiten mit Feiertagen"
             value="<?= Fmt::h((string) ($m['name'] ?? '')) ?>"></label>

    <label>Wofür er da ist
      <input name="zweck" maxlength="400"
             placeholder="Zeigt die Zeiten und sagt „jetzt geöffnet“ oder „öffnet um 7:30“."
             value="<?= Fmt::h((string) ($m['zweck'] ?? '')) ?>"></label>

    <label>Läuft bei
      <input name="laeuft_bei" maxlength="400" placeholder="Boulevard, Charme Color"
             value="<?= Fmt::h((string) ($m['laeuft_bei'] ?? '')) ?>"></label>
    <p style="color:var(--leise);font-size:12.5px;margin:-6px 0 14px">
      Der Beleg, dass er funktioniert. Ein Baustein ohne diese Zeile ist noch keiner.</p>

    <label>Passt zu
      <input name="passt_zu" maxlength="200" placeholder="oeffnungszeiten, ristorante, bar"
             value="<?= Fmt::h((string) ($m['passt_zu'] ?? '')) ?>"></label>
    <p style="color:var(--leise);font-size:12.5px;margin:-6px 0 14px">
      Stichwörter mit Komma. Leer heißt: überall vorschlagen.</p>

    <label>Der Baustein selbst
      <textarea id="musterinhalt" name="inhalt" rows="16" spellcheck="false"
        style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;line-height:1.55"
        placeholder="Markup, Beschreibung, Hinweise — was auch immer beim nächsten Mal trägt."><?=
        Fmt::h((string) ($m['inhalt'] ?? '')) ?></textarea></label>

    <div class="leiste" style="gap:14px;margin-top:12px;flex-wrap:wrap">
      <button class="knopf haupt">Speichern</button>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--dim)">
        <input type="checkbox" name="aktiv" value="1" <?= (!$m || (int) $m['aktiv'] === 1) ? 'checked' : '' ?>>
        wird vorgeschlagen</label>
      <input type="number" name="sortierung" style="width:90px" title="Reihenfolge"
             value="<?= (int) ($m['sortierung'] ?? 0) ?>">
    </div>
  </form>
</div>

<?php if ($m): ?>
  <div class="block"><h2>Weg damit</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>"
          data-frage="Diesen Baustein löschen? Briefings von früher nennen ihn weiter beim Namen."
          data-ja="Ja, löschen" data-nein="Abbrechen">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="muster_loeschen">
      <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
      <button class="knopf">Löschen</button>
      <span style="color:var(--leise);font-size:12.5px;margin-left:8px">
        Willst du ihn nur aus den Vorschlägen nehmen, reicht der Haken oben —
        dann bleibt er nachlesbar.</span>
    </form>
  </div>
<?php endif; ?>
