<?php
/* Was über den Konfigurator hereingekommen ist.

   Abgesendete zuerst — offene sind angefangene Wege, aus denen meist nichts
   wird. Sie stehen trotzdem da: Wer auf Schritt vier aufhört, ist ein Hinweis
   darauf, dass Schritt vier zu schwer ist.

   Nicht in der Tabelle stehen die Aufrufe ohne eine einzige Antwort. Eine
   Zeile entsteht schon beim Öffnen, und die waren nach kurzer Zeit in der
   Überzahl. Ihre Anzahl steht unter der Tabelle. */
?>
<div class="kopf">
  <h1>Bedarf</h1>
  <div class="rechts">
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
          data-frage="Angefangene ohne Antwort, seit über einem Tag stille und verwaiste Einträge löschen?" data-ja="Ja, aufräumen">
      <?= Csrf::feld() ?>
      <input type="hidden" name="tat" value="bedarf_aufraeumen">
      <button class="knopf">Aufräumen</button>
    </form>
  </div>
</div>

<?php /* Der zweite Knopf trifft auch abgesendete Anfragen. Deshalb steht er
         unten, nicht oben, und verlangt ein getipptes Wort -- ein Klick aus
         Gewohnheit soll nicht reichen. */ ?>

<div class="block"><div class="tabellenrahmen"><table>
<thead><tr>
  <th>Kunde</th><th>Was gebraucht wird</th>
  <th class="num">Spanne</th><th class="num">Monatlich</th>
  <th>Stand</th><th>Wann</th><th></th>
</tr></thead>
<tbody>
<?php if (!$liste): ?>
  <tr><td colspan="7"><div class="leer">Noch niemand hat den Konfigurator ausgefüllt.</div></td></tr>
<?php endif; ?>
<?php foreach ($liste as $b): ?>
  <?php
    $offen = (string) $b['status'] === 'offen';
    $antworten = [];
    if (($b['antworten'] ?? '') !== '') { $antworten = json_decode((string) $b['antworten'], true) ?: []; }
    $zwecke = [];
    foreach ((array) ($antworten['zweck'] ?? []) as $z) {
        $o = Baukasten::FRAGEN['zweck']['optionen'][$z] ?? null;
        if ($o) { $zwecke[] = Texte::h($o, 'de'); }
    }
  ?>
  <tr>
    <td>
      <?php if ($offen): ?>
        <span style="color:var(--leise)">— noch offen —</span>
      <?php else: ?>
        <a href="<?= Fmt::h(url('bedarf/' . $b['id'])) ?>"><strong><?= Fmt::h((string) $b['name']) ?></strong></a>
        <?php if (($b['firma'] ?? '') !== ''): ?>
          <div style="color:var(--leise);font-size:12.5px"><?= Fmt::h((string) $b['firma']) ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </td>
    <td style="font-size:13px;line-height:1.5">
      <?= $zwecke ? Fmt::h(implode(' · ', $zwecke)) : '<span style="color:var(--leise)">nichts gewählt</span>' ?>
    </td>
    <td class="num">
      <?= $offen ? '—' : Fmt::geld((int) $b['von_cents']) . ' – ' . Fmt::geld((int) $b['bis_cents']) ?>
    </td>
    <td class="num"><?= (int) $b['monatlich_cents'] ? Fmt::geld((int) $b['monatlich_cents']) . '/Mon.' : '—' ?></td>
    <td>
      <?php if ($offen): ?>
        <span class="marke2">Schritt <?= (int) $b['schritt'] ?> von 5</span>
      <?php elseif ((string) $b['status'] === 'angebot'): ?>
        <span class="marke2 gut">Angebot raus</span>
      <?php else: ?>
        <span class="marke2 warnung">wartet auf Angebot</span>
      <?php endif; ?>
    </td>
    <td style="font-size:12.5px;color:var(--leise)"><?= Fmt::h(Fmt::seit((string) $b['created_at'])) ?></td>
    <td style="text-align:right">
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
            data-frage="Diesen Eintrag löschen?" data-ja="Ja, löschen">
        <?= Csrf::feld() ?>
        <input type="hidden" name="tat" value="bedarf_loeschen">
        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <button class="knopf" style="padding:4px 9px;font-size:12px">Löschen</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="block" style="margin-top:14px">
  <h2 style="font-size:15px;margin:0 0 6px">Ganz leeren</h2>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 10px">
    Löscht auch abgesendete Anfragen — alles außer dem, woran ein Angebot hängt.
    Gedacht für nach einem Probelauf. Tippe <b>LÖSCHEN</b>, damit ein Klick aus
    Gewohnheit nicht reicht.
  </p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?= Csrf::feld() ?>
    <input type="hidden" name="tat" value="bedarf_aufraeumen">
    <input type="hidden" name="alles" value="1">
    <input name="bestaetigung" placeholder="LÖSCHEN" style="max-width:180px" autocomplete="off">
    <button class="knopf">Alles löschen</button>
  </form>
</div>

<?php if (($leer ?? 0) > 0): ?>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:12px 2px 0">
    Dazu <?= (int) $leer ?> Mal geöffnet und sofort wieder verlassen, ohne eine
    einzige Antwort. Solche Zeilen entstehen bei jedem Aufruf und stehen hier
    nur als Zahl — sie sagen nichts über einen Kunden, nur etwas über den
    ersten Bildschirm.
  </p>
<?php endif; ?>
</div>
