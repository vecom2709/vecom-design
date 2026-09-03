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
    <?php if ($angebotId): ?>
      <a class="knopf haupt" href="<?= Fmt::h(url('angebote/' . $angebotId)) ?>">Zum Angebot</a>
    <?php elseif ($b['customer_id'] && $b['status'] !== 'offen'): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
        <?= Csrf::feld() ?>
        <input type="hidden" name="tat" value="angebot_aus_bedarf">
        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <button class="knopf haupt">Angebot erstellen</button>
      </form>
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

    <?php /* Die Spanne ist ehrlich, aber sie ist keine Antwort: Wer sie
             bekommt, fragt zurueck, was es denn nun kostet. Hier steht die
             Zahl, die man nennen kann — aus demselben Rechenweg wie das
             Angebot, damit nicht zwei verschiedene im Umlauf sind. */ ?>
    <?php if ((int) ($vorschlag['summe_cents'] ?? 0) > 0): ?>
      <div class="block">
        <h2 style="font-size:15px;margin:0 0 10px">Was du nennen kannst</h2>
        <p style="font-size:28px;font-weight:600;margin:0 0 4px;letter-spacing:-.01em">
          <?= Fmt::geld((int) $vorschlag['summe_cents']) ?>
        </p>
        <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
          Die Mitte jeder Spanne, gerundet wie im Angebot. Nennst du diese Zahl,
          steht später dieselbe im Angebot — nachrechnen musst du nichts.
        </p>
        <div class="tabellenrahmen"><table>
          <tbody>
          <?php foreach ($vorschlag['positionen'] as $p): ?>
            <?php if ((int) $p['monatlich']) { continue; } ?>
            <?php $bs = $katalog[$p['slug']] ?? null; if (!$bs) { continue; } ?>
            <tr>
              <td><?= Fmt::h(Baukasten::name($bs, 'de')) ?><?= (int) $p['menge'] > 1 ? ' (' . (int) $p['menge'] . ')' : '' ?></td>
              <td class="num"><?= Fmt::geld((int) $p['summe_cents']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php if ((int) ($vorschlag['monatlich_cents'] ?? 0) > 0): ?>
          <p style="color:var(--dim);font-size:14px;margin:12px 0 0">
            Dazu <?= Fmt::geld((int) $vorschlag['monatlich_cents']) ?> im Monat.
          </p>
        <?php endif; ?>
      </div>

      <?php /* Das Briefing zum Kopieren.

               Im Konfigurator steht bereits alles, was ein Briefing braucht.
               Bisher las Uwe das ab und tippte es neu — und beim Abtippen
               geht zuverlaessig das verloren, was der Kunde NICHT angekreuzt
               hat. Genau dieser Abschnitt steht hier deshalb ausdruecklich
               drin: Ein Sprachmodell, dem man ein Restaurant beschreibt, baut
               ungefragt eine Tischreservierung dazu. */ ?>
      <?php if (($bauprompt ?? '') !== ''): ?>
        <div class="block">
          <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap">
            <h2 style="font-size:15px;margin:0">Briefing zum Bauen</h2>
            <button type="button" class="knopf" data-kopieren="#bauprompt">Kopieren</button>
          </div>
          <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:8px 0 10px">
            Alles aus dem Konfigurator, fertig als Auftrag an Claude — samt der Liste,
            was <em>nicht</em> gebaut werden soll. Kopieren, einfügen, loslegen.
          </p>
          <textarea id="bauprompt" readonly rows="14" onclick="this.select()"
            style="width:100%;font:12.5px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace"><?= Fmt::h($bauprompt) ?></textarea>
        </div>
      <?php endif; ?>

      <?php if (($nachricht['text'] ?? '') !== '' && $b['customer_id']): ?>
        <div class="block">
          <h2 style="font-size:15px;margin:0 0 6px">Als Nachricht an den Kunden</h2>
          <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 4px">
            Fertig formuliert auf <?= Fmt::h($spracheLang) ?> — mit derselben Zahl wie oben und den
            Posten, aus denen sie besteht. Lies drüber, ändere was du willst, sende.
            Die Nachricht geht als E-Mail raus und steht auf seiner Seite.
          </p>
          <?php
            $nfTat      = 'kunde_nachricht';
            $nfId       = (int) $b['customer_id'];
            $nfKennung  = $kennung;
            $nfVorlagen = $vorlagen;
            $nfVorname  = explode(' ', trim((string) $b['name']))[0] ?? '';
            $nfZurueck  = 'bedarf/' . (int) $b['id'];
            $nfVorwahl  = '';
            $nfBetreff  = (string) ($nachricht['betreff'] ?? '');
            $nfText     = (string) ($nachricht['text'] ?? '');
            include __DIR__ . '/nachrichtfeld.php';
          ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

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

<script>
/* Kopieren mit Rueckmeldung. Ohne die Rueckmeldung drueckt man zweimal, weil
   nichts passiert zu sein scheint — und beim zweiten Mal ist man sich nicht
   mehr sicher, ob nun einmal oder zweimal kopiert wurde. */
document.querySelectorAll('[data-kopieren]').forEach(function (knopf) {
  knopf.addEventListener('click', function () {
    var feld = document.querySelector(knopf.getAttribute('data-kopieren'));
    if (!feld) { return; }
    feld.select();
    var gut = false;
    try { gut = document.execCommand('copy'); } catch (e) { gut = false; }
    if (!gut && navigator.clipboard) { navigator.clipboard.writeText(feld.value).then(function () {}); gut = true; }
    var alt = knopf.textContent;
    knopf.textContent = gut ? 'Kopiert' : 'Bitte von Hand kopieren';
    setTimeout(function () { knopf.textContent = alt; }, 1800);
  });
});
</script>
