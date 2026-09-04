<div class="kopf"><div><h1>Bausteine</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Was zum dritten Mal gebaut wird, gehört benannt. Das Briefing schlägt
    passende von selbst vor.</p></div>
  <div class="rechts">
    <a class="knopf haupt" href="<?= Fmt::h(url('muster/neu')) ?>">Baustein anlegen</a>
  </div>
</div>

<?php if (!$liste): ?>
  <div class="block">
    <div class="leer" style="line-height:1.7">
      Noch keiner. Das ist der richtige Zustand am Anfang — hier wird nichts
      auf Vorrat angelegt.<br>
      Baust du beim nächsten Kunden etwas, das du schon einmal gebaut hast —
      Öffnungszeiten, Speisekarte, Galerie, Kontaktbereich, Karte —, dann leg
      es hier ab. Ab dem nächsten passenden Briefing schlägt es sich selbst vor.
    </div>
  </div>
<?php else: ?>
  <div class="block"><div class="tabellenrahmen"><table>
    <thead><tr><th>Baustein</th><th>Wofür</th><th>Läuft bei</th><th>Passt zu</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($liste as $m): ?>
      <tr<?= (int) $m['aktiv'] === 0 ? ' style="opacity:.5"' : '' ?>>
        <td><a href="<?= Fmt::h(url('muster/' . (int) $m['id'])) ?>"><strong><?= Fmt::h((string) $m['name']) ?></strong></a>
          <?php if ((int) $m['aktiv'] === 0): ?>
            <span class="marke2">stillgelegt</span>
          <?php endif; ?></td>
        <td style="color:var(--dim);font-size:13px"><?= Fmt::h((string) ($m['zweck'] ?? '')) ?></td>
        <td style="font-size:13px"><?= Fmt::h((string) ($m['laeuft_bei'] ?? '')) ?: '<span style="color:var(--leise)">— noch nirgends</span>' ?></td>
        <td style="color:var(--leise);font-size:12.5px"><?= Fmt::h((string) ($m['passt_zu'] ?? '')) ?: 'überall' ?></td>
        <td style="text-align:right"><a class="knopf stumm" href="<?= Fmt::h(url('muster/' . (int) $m['id'])) ?>">Ansehen</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
<?php endif; ?>

<div class="block"><h2>Wie das gemeint ist</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.7;margin:0">
    „Läuft bei“ ist das wichtigste Feld. Eine Sammlung von Schnipseln, die
    jemand einmal gut fand, ist eine Sammlung von Absichten — was zählt, ist
    der Beleg: Dieser Abschnitt steht bei zwei Kunden und tut dort seit Monaten
    seinen Dienst.
    <br><br>
    „Passt zu“ sind Stichwörter, an denen das Briefing erkennt, wann der
    Baustein in Frage kommt — etwa <code>speisekarte, ristorante, bar</code>.
    Bleibt das Feld leer, wird er überall vorgeschlagen; das ist für Dinge
    richtig, die auf jede Seite gehören.
  </p>
</div>
