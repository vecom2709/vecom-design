<?php
/* Ein Bedarf im Einzelnen.

   Drei Dinge nebeneinander, und das ist Absicht: was der Kunde wollte, was
   das kostet, und was ich ihm zusaetzlich anbieten koennte. Wer nur die
   Summe sieht, verkauft zu billig; wer nur die Antworten sieht, rechnet von
   Hand.

   Die Spanne steht zweimal da, wenn sie sich unterscheidet: einmal so, wie
   der Kunde sie gesehen hat, und einmal so, wie sie sich heute rechnet. Das
   passiert, sobald zwischendurch Preise im Katalog geaendert wurden — und
   dann ist die Zahl, die der Kunde im Kopf hat, die aeltere. */

$sprache = (string) ($b['sprache'] ?? 'it');
$spracheLang = ['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'][$sprache] ?? $sprache;

$gesehenVon = (int) $b['von_cents'];
$gesehenBis = (int) $b['bis_cents'];
$jetzt      = Baukasten::spanne((int) $rechnung['von_cents'], (int) $rechnung['bis_cents']);
$abweichung = $gesehenVon !== $jetzt['von_cents'] || $gesehenBis !== $jetzt['bis_cents'];
?>
<div class="kopf">
  <h1><?= Fmt::h((string) $b['name']) ?></h1>
  <div class="rechts">
    <?php if ($b['anfrage_id']): ?>
      <a class="knopf" href="<?= Fmt::h(url('anfragen/' . $b['anfrage_id'])) ?>">Zur Anfrage</a>
    <?php endif; ?>
    <?php if ($b['customer_id']): ?>
      <a class="knopf" href="<?= Fmt::h(url('kunden/' . $b['customer_id'])) ?>">Zum Kunden</a>
    <?php endif; ?>
  </div>
</div>

<div class="zwei">
  <div>
    <div class="block">
      <h2 style="font-size:15px;margin:0 0 12px">Kontakt</h2>
      <table><tbody>
        <tr><td style="width:38%">Name</td><td><?= Fmt::h((string) $b['name']) ?></td></tr>
        <?php if (($b['firma'] ?? '') !== ''): ?>
          <tr><td>Betrieb</td><td><?= Fmt::h((string) $b['firma']) ?></td></tr>
        <?php endif; ?>
        <tr><td>E-Mail</td><td><a href="mailto:<?= Fmt::h((string) $b['email']) ?>"><?= Fmt::h((string) $b['email']) ?></a></td></tr>
        <?php if (($b['telefon'] ?? '') !== ''): ?>
          <tr><td>Telefon</td><td><?= Fmt::h((string) $b['telefon']) ?></td></tr>
        <?php endif; ?>
        <tr><td>Sprache</td><td><?= Fmt::h($spracheLang) ?></td></tr>
        <tr><td>Eingegangen</td><td><?= Fmt::h(Fmt::datum((string) $b['abgesendet_am'])) ?></td></tr>
      </tbody></table>
    </div>

    <div class="block">
      <h2 style="font-size:15px;margin:0 0 12px">Was angegeben wurde</h2>
      <table><tbody>
      <?php foreach (Baukasten::FRAGEN as $schluessel => $frage): ?>
        <?php
          $wert = $antworten[$schluessel] ?? null;
          if ($wert === null || $wert === '' || $wert === []) { continue; }
          $lesbar = [];
          foreach ((is_array($wert) ? $wert : [$wert]) as $w) {
              $o = $frage['optionen'][$w] ?? null;
              if ($o) { $lesbar[] = Texte::h($o, 'de'); }
          }
        ?>
        <tr>
          <td style="width:42%"><?= Fmt::h(Texte::h($frage['frage'], 'de')) ?></td>
          <td><?= Fmt::h(implode(', ', $lesbar)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>

  <div>
    <div class="block">
      <h2 style="font-size:15px;margin:0 0 12px">Was das rechnet</h2>
      <div class="tabellenrahmen"><table>
        <thead><tr><th>Position</th><th class="num">Menge</th><th class="num">Von</th><th class="num">Bis</th></tr></thead>
        <tbody>
        <?php foreach ($rechnung['positionen'] as $p): ?>
          <?php $bs = $katalog[$p['slug']] ?? null; if (!$bs) { continue; } ?>
          <tr>
            <td><?= Fmt::h(Baukasten::name($bs, 'de')) ?>
              <?php if ($p['monatlich']): ?><span class="marke2 warnung">monatlich</span><?php endif; ?>
            </td>
            <td class="num"><?= (int) $p['menge'] > 1 ? (int) $p['menge'] : '' ?></td>
            <td class="num"><?= Fmt::geld((int) $p['von_cents']) ?></td>
            <td class="num"><?= $p['monatlich'] ? '' : Fmt::geld((int) $p['bis_cents']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <th colspan="2">Summe einmalig</th>
          <th class="num"><?= Fmt::geld((int) $rechnung['von_cents']) ?></th>
          <th class="num"><?= Fmt::geld((int) $rechnung['bis_cents']) ?></th>
        </tr></tfoot>
      </table></div>
    </div>

    <div class="block">
      <h2 style="font-size:15px;margin:0 0 10px">Die Spanne</h2>
      <p style="font-size:22px;font-weight:600;margin:0 0 4px">
        <?= Fmt::geld($gesehenVon) ?> – <?= Fmt::geld($gesehenBis) ?>
      </p>
      <p style="color:var(--leise);font-size:12.5px;margin:0">
        So hat der Kunde sie gesehen — nach außen gerundet, damit sie wie eine
        Schätzung aussieht und nicht wie eine Rechnung.
      </p>
      <?php if ($abweichung): ?>
        <div class="hinweis warnung" style="margin-top:12px">
          Heute rechnet sich <?= Fmt::geld($jetzt['von_cents']) ?> – <?= Fmt::geld($jetzt['bis_cents']) ?>,
          weil sich seither Preise im Baukasten geändert haben. Der Kunde erinnert sich an die obere Zahl.
        </div>
      <?php endif; ?>
      <?php if ((int) $rechnung['monatlich_cents'] > 0): ?>
        <p style="color:var(--dim);font-size:14px;margin:12px 0 0">
          Dazu <?= Fmt::geld((int) $rechnung['monatlich_cents']) ?> im Monat für die Betreuung — dem wurde zugestimmt.
        </p>
      <?php endif; ?>
    </div>

    <?php if (!empty($rechnung['vorschlaege'])): ?>
      <div class="block">
        <h2 style="font-size:15px;margin:0 0 6px">Könntest du anbieten</h2>
        <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
          Steht bewusst nicht in der Spanne. Danach wurde nicht gefragt —
          aber die Antworten sprechen dafür.
        </p>
        <?php foreach ($rechnung['vorschlaege'] as $slug): ?>
          <?php $v = $katalog[$slug] ?? null; if (!$v) { continue; } ?>
          <div class="reihe" style="align-items:flex-start;gap:10px">
            <div style="flex:1 1 auto">
              <strong><?= Fmt::h(Baukasten::name($v, 'de')) ?></strong>
              <div style="color:var(--leise);font-size:12.5px;line-height:1.5">
                <?= Fmt::h(Baukasten::text($v, 'de')) ?>
              </div>
            </div>
            <div class="num" style="white-space:nowrap;font-size:13px">
              <?= Fmt::geld((int) $v['preis_cents']) ?>
              <?= (int) $v['preis_bis_cents'] ? ' – ' . Fmt::geld((int) $v['preis_bis_cents']) : '' ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
