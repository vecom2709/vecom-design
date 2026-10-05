<?php
/* Partner Academy verwalten (Etappe 2, 05.10.2026, Uwe: „MACH“).
   Nur Zahlen, keine Namen: wie viele Partner lernen, welche Module fertig
   werden, welche Einwände und Unterlagen gebraucht werden. Dazu die Schalter
   je Modul, eigene PDFs und die Meldung „Neue Schulung verfügbar“.
   Die Inhalte selbst stehen in app/data/academy/*.json (im Repository). */
$st = $stat + ['partner_aktiv' => 0, 'module_fertig' => 0, 'partner_mit' => 0, 'je_modul' => [], 'einwaende' => [], 'pdfs' => [], 'oft' => []];
$jeModul = array_column($st['je_modul'], null, 'modul');
$deE = []; foreach (Academy::inhalte('de')['einwaende'] as $e) { $deE[$e['slug']] = $e['satz']; }
$docTitel = array_column($docs, 'titel', 'slug');
$kats = ['eigene' => 'Von Vecom', 'grundlagen' => 'Grundlagen', 'kunden' => 'Kunden finden', 'gespraech' => 'Gespräch', 'vecom' => 'Vecom-Leistungen', 'ablauf' => 'Ablauf', 'regeln' => 'Regeln'];
$sprachen = ['alle' => 'Alle Sprachen', 'it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'];
$kb = static fn(int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
?>
<div class="kopf"><h1>Partner Academy</h1>
  <p style="color:var(--leise);margin:4px 0 0;font-size:13px">Verkaufstraining der Partner · letzte 30 Tage · nur Zahlen, keine Namen</p></div>

<div class="karten">
  <div class="karte"><h3>Partner aktiv in der Academy</h3><div class="wert"><?= (int) $st['partner_aktiv'] ?></div></div>
  <div class="karte"><h3>Partner haben je begonnen</h3><div class="wert"><?= (int) $st['partner_mit'] ?></div></div>
  <div class="karte"><h3>Module abgeschlossen</h3><div class="wert"><?= (int) $st['module_fertig'] ?></div></div>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 8px">Module — Fortschritt und Schalter</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">Ein abgeschaltetes Modul verschwindet für Partner sofort (auch seine Unterlage). „Reihe“ leer = Nummer aus der Datei.</p>
  <div class="tabellenrahmen"><table>
    <thead><tr><th>Nr.</th><th>Modul</th><th>Begonnen</th><th>Fertig</th><th>Aktiv</th><th>Pflicht</th><th>Reihe</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($module as $m): $sw = $schalter[$m['slug']] ?? null; $jm = $jeModul[$m['slug']] ?? ['begonnen' => 0, 'fertig' => 0]; $fid = 'am-' . $m['slug']; ?>
      <tr<?= $m['aktiv'] ? '' : ' style="opacity:.55"' ?>>
        <td><?= (int) $m['nr'] ?></td>
        <td><b><?= Fmt::h($m['titel']) ?></b><br><a href="<?= Fmt::h(url('academy?vorschau=' . rawurlencode(array_search($m['slug'], array_map(static fn($d) => $d[0], Academy::DOKUMENTE), true) ?: ''))) ?>" target="_blank" rel="noopener" style="font-size:12px">PDF ansehen</a></td>
        <td><?= (int) $jm['begonnen'] ?></td>
        <td><?= (int) $jm['fertig'] ?></td>
        <td><input form="<?= $fid ?>" type="checkbox" name="aktiv" value="1" <?= $m['aktiv'] ? 'checked' : '' ?> aria-label="Aktiv"></td>
        <td><select form="<?= $fid ?>" name="pflicht" aria-label="Pflicht">
          <option value="" <?= ($sw['pflicht'] ?? null) === null ? 'selected' : '' ?>>wie Datei<?= ($sw['pflicht'] ?? null) === null ? (!empty($m['pflicht']) ? ' (Pflicht)' : ' (freiwillig)') : '' ?></option>
          <option value="1" <?= ($sw['pflicht'] ?? null) !== null && (int) $sw['pflicht'] === 1 ? 'selected' : '' ?>>Pflicht</option>
          <option value="0" <?= ($sw['pflicht'] ?? null) !== null && (int) $sw['pflicht'] === 0 ? 'selected' : '' ?>>freiwillig</option></select></td>
        <td><input form="<?= $fid ?>" type="number" name="reihe" min="-99" max="999" style="width:70px" value="<?= ($sw['reihe'] ?? null) === null ? '' : (int) $sw['reihe'] ?>" aria-label="Reihe"></td>
        <td><form id="<?= $fid ?>" method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="academy_modul"><input type="hidden" name="slug" value="<?= Fmt::h($m['slug']) ?>"><input type="hidden" name="zurueck" value="academy">
          <button class="knopf klein">Speichern</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="raster2" style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
  <div class="block">
    <h2 style="font-size:15px;margin:0 0 8px">Häufigste Einwände</h2>
    <?php if (!$st['einwaende']): ?><p style="color:var(--leise);font-size:13px;margin:0">Noch keine Zahlen.</p><?php endif; ?>
    <ol style="margin:0;padding-left:20px;font-size:13.5px;line-height:1.7">
      <?php foreach ($st['einwaende'] as $z): ?><li>„<?= Fmt::h($deE[$z['ziel']] ?? $z['ziel']) ?>“ — <b><?= (int) $z['n'] ?></b></li><?php endforeach; ?>
    </ol>
  </div>
  <div class="block">
    <h2 style="font-size:15px;margin:0 0 8px">Meistgenutzte Unterlagen</h2>
    <?php if (!$st['pdfs']): ?><p style="color:var(--leise);font-size:13px;margin:0">Noch keine Zahlen.</p><?php endif; ?>
    <ol style="margin:0;padding-left:20px;font-size:13.5px;line-height:1.7">
      <?php foreach ($st['pdfs'] as $z): ?><li><?= Fmt::h($docTitel[$z['ziel']] ?? $z['ziel']) ?> — <b><?= (int) $z['n'] ?></b></li><?php endforeach; ?>
    </ol>
  </div>
  <div class="block">
    <h2 style="font-size:15px;margin:0 0 8px">Am meisten geöffnet</h2>
    <?php if (!$st['oft']): ?><p style="color:var(--leise);font-size:13px;margin:0">Noch keine Zahlen.</p><?php endif; ?>
    <ol style="margin:0;padding-left:20px;font-size:13.5px;line-height:1.7">
      <?php foreach (array_slice($st['oft'], 0, 8) as $z): ?><li><?= Fmt::h($z['art']) ?>: <?= Fmt::h($z['ziel']) ?> — <b><?= (int) $z['n'] ?></b></li><?php endforeach; ?>
    </ol>
  </div>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">PDF-Bibliothek</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">Die eingebauten Unterlagen entstehen aus den Modulen — immer aktuell, in der Sprache des Partners, mit seinem Provisionssatz.
    Eigene PDFs (höchstens <?= Academy::PDF_MAX >> 20 ?> MB) kommen dazu; „Ersetzen“ macht eine neue Version, „Archivieren“ blendet aus.</p>
  <div class="tabellenrahmen"><table>
    <thead><tr><th>Unterlage</th><th>Art</th><th>Sprache</th><th>Version</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($docs as $d): if ($d['eigen']) { continue; } ?>
      <tr><td><?= Fmt::h($d['titel']) ?></td><td>eingebaut · <?= Fmt::h($kats[$d['kategorie']] ?? $d['kategorie']) ?></td><td>it · de · en</td><td><?= Fmt::h(Fmt::datum($d['stand'])) ?></td>
        <td><a href="<?= Fmt::h(url('academy?vorschau=' . rawurlencode($d['slug']))) ?>" target="_blank" rel="noopener">Ansehen</a>
          · <a href="<?= Fmt::h(url('academy?vorschau=' . rawurlencode($d['slug']) . '&sprache=it')) ?>" target="_blank" rel="noopener">it</a></td></tr>
    <?php endforeach; ?>
    <?php foreach ($eigene as $d): ?>
      <tr<?= (int) $d['archiviert'] ? ' style="opacity:.55"' : '' ?>><td><b><?= Fmt::h($d['titel']) ?></b><br><small style="color:var(--leise)"><?= Fmt::h($d['dateiname']) ?> · <?= $kb((int) $d['groesse']) ?></small></td>
        <td>eigenes · <?= Fmt::h($kats[$d['kategorie']] ?? $d['kategorie']) ?><?= (int) $d['archiviert'] ? ' · archiviert' : '' ?></td>
        <td><?= Fmt::h($sprachen[$d['sprache']] ?? $d['sprache']) ?></td><td>v<?= (int) $d['version'] ?> · <?= Fmt::h(Fmt::datum($d['updated_at'])) ?></td>
        <td style="white-space:nowrap"><a href="<?= Fmt::h(url('academy?pdf=' . (int) $d['id'])) ?>" target="_blank" rel="noopener">Ansehen</a>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"><?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="academy_pdf_archiv"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><input type="hidden" name="zurueck" value="academy">
            <input type="hidden" name="archiv" value="<?= (int) $d['archiviert'] ? '' : '1' ?>"><button class="knopf klein"><?= (int) $d['archiviert'] ? 'Wieder zeigen' : 'Archivieren' ?></button></form></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <form method="post" action="<?= Fmt::h(url('')) ?>" enctype="multipart/form-data" style="margin-top:14px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="academy_pdf"><input type="hidden" name="zurueck" value="academy">
    <div class="reihe">
      <div class="feld"><label>Titel (so sehen ihn die Partner) *</label><input name="titel" required maxlength="160"></div>
      <div class="feld"><label>Kategorie</label><select name="kategorie"><?php foreach ($kats as $k => $n): ?><option value="<?= $k ?>"><?= Fmt::h($n) ?></option><?php endforeach; ?></select></div>
      <div class="feld"><label>Für</label><select name="sprache"><?php foreach ($sprachen as $k => $n): ?><option value="<?= $k ?>"><?= Fmt::h($n) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="reihe">
      <div class="feld"><label>PDF-Datei *</label><input type="file" name="datei" accept="application/pdf,.pdf" required></div>
      <div class="feld"><label>Ersetzt (neue Version von)</label><select name="ersetze"><option value="0">— neues Dokument</option>
        <?php foreach ($eigene as $d): ?><option value="<?= (int) $d['id'] ?>"><?= Fmt::h($d['titel']) ?> (v<?= (int) $d['version'] ?>)</option><?php endforeach; ?></select></div>
    </div>
    <button class="knopf haupt">PDF hochladen</button>
  </form>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">„Neue Schulung verfügbar“ melden</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">Alle freigeschalteten Partner bekommen einen Hinweis aufs Handy (wer die App hat), jeder in seiner Sprache.
    Auf der Academy-Startseite steht der Hinweis 14 Tage. Vorher fragt die Seite nach.</p>
  <?php if ($neu): $nz = Academy::meldeZiel($neu['ziel'], 'de'); ?>
    <p style="font-size:13px;margin:0 0 10px">Zuletzt gemeldet: <b><?= Fmt::h($nz['titel'] ?? $neu['ziel']) ?></b> am <?= Fmt::h(Fmt::datum($neu['am'])) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="academy_melden"><input type="hidden" name="zurueck" value="academy">
    <div class="feld" style="flex:1 1 280px"><label>Was ist neu?</label><select name="ziel" required>
      <optgroup label="Module"><?php foreach ($module as $m): if (!$m['aktiv']) { continue; } ?><option value="modul:<?= Fmt::h($m['slug']) ?>"><?= (int) $m['nr'] ?> · <?= Fmt::h($m['titel']) ?></option><?php endforeach; ?></optgroup>
      <optgroup label="Unterlagen"><?php foreach ($docs as $d): ?><option value="pdf:<?= Fmt::h($d['slug']) ?>"><?= Fmt::h($d['titel']) ?></option><?php endforeach; ?></optgroup>
    </select></div>
    <button class="knopf haupt">An alle Partner melden</button>
  </form>
</div>
