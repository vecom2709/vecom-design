<?php
/**
 * DER TELEFONASSISTENT — ALLES, WAS SICHTBAR SEIN MUSS
 * ===========================================================================
 *
 * Diese Seite ist Auswertung, sonst nichts. Was eingestellt wird — Schlüssel,
 * Modus, der Zugang zu STRATO, die vierzehn Konfigurationen — steht unter
 * Einstellungen → Telefonassistentin. Der Grund ist banal und teuer: Wer eine
 * Zahl nachsehen wollte, kam vorher an einem Knopf vorbei, der den Schlüssel
 * neu erzeugt und STRATO ins Leere rufen lässt.
 *
 * WAS HIER ZUSAMMENKOMMT, KOMMT AUS ZWEI RICHTUNGEN
 *
 * Von STRATO: was gesprochen wurde — Anrufer, Dauer, Betreff, Zusammenfassung
 * und die maschinelle Auswertung je Anruf (Ausgang, Beteiligung,
 * Problem-Schlagworte, Verstöße gegen die eigenen Anweisungen).
 *
 * Von uns: was getan wurde — jeder Werkzeugaufruf mit Ergebnis, aus unserer
 * eigenen Spur. Sie gehört uns, überlebt jede Aufbewahrungsfrist bei STRATO
 * und ist das Einzige, was auch dann noch da ist, wenn der Anbieter wechselt.
 *
 * Nebeneinander beantworten sie die Frage, die eine Liste von Anrufen nie
 * beantwortet: Warum ist dieses Gespräch so ausgegangen?
 */
$tage   = max(1, min(365, (int) ($_GET['t'] ?? 30)));
$filter = (string) ($_GET['f'] ?? '');
?>

<div class="kopf"><div><h1>Telefonassistent</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    <?php if ($anzahl > 0): ?>
      <?= (int) $anzahl ?> <?= $anzahl === 1 ? 'Aufruf' : 'Aufrufe' ?> in den letzten 30 Tagen.
    <?php else: ?>
      Noch kein Aufruf. Die Konfigurationen stehen unter Einstellungen.
    <?php endif; ?>
    <?php if ($modus !== 'normal'): ?>
      <span class="marke2 warnung" style="margin-left:8px">Modus: <?= Fmt::h(Telefon::MODI[$modus] ?? $modus) ?></span>
    <?php endif; ?>
  </p></div>
  <a class="knopf" href="<?= Fmt::h(url('einstellungen?b=telefon')) ?>" style="text-decoration:none">Einstellungen</a>
</div>

<?php if ($strato['fehler'] !== ''): ?>
  <div class="hinweis schlecht">Die Gespräche von STRATO kommen nicht mehr an:
    <?= Fmt::h($strato['fehler']) ?>
    <a href="<?= Fmt::h(url('einstellungen?b=telefon')) ?>">Zugang neu hinterlegen</a></div>
<?php endif; ?>

<?php /* ---------- Heute anrufen ---------- */ ?>
<?php if (!empty($rueckrufe)):
  $dringend = array_filter($rueckrufe, static fn(array $r): bool => $r['dringend']);
  $alt      = array_filter($rueckrufe, static fn(array $r): bool => $r['ueberfaellig']);
?>
<div class="block" style="border-color:<?= $alt ? 'var(--rot)' : 'var(--cyan)' ?>">
  <h2>Heute anrufen <span class="marke2"><?= count($rueckrufe) ?></span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Das Einzige auf dieser Seite, was du persönlich tun musst. Oben steht, was
    dringend ist — das hat der Anrufer selbst gesagt und schlägt jede Rechnung.
    Darunter sortiert, wie weit das Gespräch schon war: wer sechs Fragen beantwortet
    und einen Termin genommen hat, steht über dem, der „rufen Sie mal an“ gesagt hat.
    Bei gleichem Stand zuerst das Älteste — wer lange wartet, hat am ehesten schon
    aufgegeben. Der Grund steht immer daneben; die Zahl entscheidet nichts, sie sortiert.
    <?php if ($alt): ?><br><b>Rot heißt: liegt seit mehr als einem Tag.</b><?php endif; ?>
  </p>
  <table class="tab"><tbody>
    <?php foreach ($rueckrufe as $r): ?>
      <tr<?= $r['ueberfaellig'] ? ' style="background:rgba(255,90,90,.06)"' : '' ?>>
        <td style="white-space:nowrap;vertical-align:top;width:1%">
          <?php if ($r['dringend']): ?><span class="marke2 schlecht">dringend</span><br><?php endif; ?>
          <span style="color:var(--leise);font-size:12px">
            <?= $r['stunden'] < 24
                  ? 'vor ' . (int) $r['stunden'] . ' h'
                  : 'seit ' . (int) round($r['stunden'] / 24) . ' Tag' . (round($r['stunden'] / 24) == 1 ? '' : 'en') ?>
          </span>
        </td>
        <td style="vertical-align:top">
          <b><?php if ($r['kunde_id'] > 0): ?>
            <a href="<?= Fmt::h(url('kunden/' . (int) $r['kunde_id'])) ?>"><?= Fmt::h($r['wer']) ?></a>
          <?php else: ?><?= Fmt::h($r['wer']) ?><?php endif; ?></b>
          <?php if ($r['anliegen'] !== ''): ?>
            <div style="color:var(--leise);font-size:12.5px;margin-top:3px"><?= Fmt::h($r['anliegen']) ?></div>
          <?php endif; ?>
          <?php /* Der Grund steht neben der Zahl. Eine Bewertung ohne
                    Begruendung ist eine Behauptung ueber einen Menschen,
                    und die stellt hier keine Software auf. */ ?>
          <?php if (!empty($r['gruende'])): ?>
            <div style="color:var(--leise);font-size:12px;margin-top:4px">
              <?= Fmt::h(implode(' · ', $r['gruende'])) ?>
            </div>
          <?php endif; ?>
        </td>
        <td style="vertical-align:top;white-space:nowrap">
          <?php if ($r['nummer'] !== ''): ?>
            <a href="tel:<?= Fmt::h(preg_replace('/[^0-9+]/', '', $r['nummer']) ?? '') ?>"
               style="font-weight:600"><?= Fmt::h($r['nummer']) ?></a>
          <?php else: ?>
            <span style="color:var(--leise)">keine Nummer</span>
          <?php endif; ?>
          <?php if ($r['erreichbar'] !== ''): ?>
            <div style="color:var(--leise);font-size:12.5px;margin-top:3px">
              erreichbar: <?= Fmt::h($r['erreichbar']) ?></div>
          <?php else: ?>
            <div style="color:var(--leise);font-size:12.5px;margin-top:3px">kein Zeitfenster genannt</div>
          <?php endif; ?>
        </td>
        <td style="text-align:right;vertical-align:top;white-space:nowrap">
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="telefon_rueckruf_weg">
            <input type="hidden" name="eintrag" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="zurueck" value="telefon">
            <button class="knopf">Erledigt</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<?php /* ---------- DIE GESPRÄCHE ----------
         Der eigentliche Grund dieser Seite. Bei STRATO stehen sie über fünf
         Seiten verteilt, ohne Suche, ohne Filter und mit einer
         Aufbewahrungsfrist, die uns nicht gehört. Hier stehen sie
         durchsuchbar, mit der Auswertung und mit unserer eigenen Spur
         daneben. */ ?>
<?php if ($strato['eingerichtet'] || $gespraeche): ?>
<?php $zz = $zahlen; ?>
<div class="block">
  <h2>Gespräche
    <span class="mehr" style="font-weight:400;color:var(--leise)">letzte <?= (int) $tage ?> Tage</span>
    <span class="marke2"><?= (int) $zz['anrufe'] ?></span></h2>

  <?php if ($zz['anrufe'] > 0): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:14px 0 18px">
    <div><div style="font-size:22px;font-weight:600"><?= (int) $zz['echte'] ?></div>
      <div style="color:var(--leise);font-size:12px">echte Gespräche<br>(ab 30 Sekunden)</div></div>
    <div><div style="font-size:22px;font-weight:600"><?= (int) $zz['minuten'] ?> min</div>
      <div style="color:var(--leise);font-size:12px">Gesprächszeit<br>im Schnitt <?= Fmt::h((string) $zz['schnitt']) ?></div></div>
    <div><div style="font-size:22px;font-weight:600"><?= (int) $zz['bekannt'] ?></div>
      <div style="color:var(--leise);font-size:12px">von einem<br>bekannten Kunden</div></div>
    <div><div style="font-size:22px;font-weight:600;color:<?= $zz['verstoesse'] > 0 ? 'var(--rot)' : 'inherit' ?>"><?= (int) $zz['verstoesse'] ?></div>
      <div style="color:var(--leise);font-size:12px">hat gegen ihre<br>Anweisungen gehandelt</div></div>
    <div><div style="font-size:22px;font-weight:600;color:<?= $zz['erfunden'] > 0 ? 'var(--rot)' : 'inherit' ?>"><?= (int) $zz['erfunden'] ?></div>
      <div style="color:var(--leise);font-size:12px">hat etwas<br>erfunden</div></div>
  </div>

  <?php if ($zz['tags']): ?>
    <p style="color:var(--leise);font-size:12.5px;margin:0 0 8px">
      Woran es lag — von STRATO je Anruf vermerkt, nicht von uns geraten:</p>
    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:18px">
      <?php foreach (array_slice($zz['tags'], 0, 10, true) as $t => $n): ?>
        <span class="marke2"><?= Fmt::h(Strato::tagWort($t)) ?> · <?= (int) $n ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($zz['ausgang']): ?>
    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:18px">
      <?php foreach ($zz['ausgang'] as $a => $n): $w = Strato::ausgangWort($a); ?>
        <span class="marke2 <?= Fmt::h($w['ton']) ?>"><?= Fmt::h($w['wort']) ?> · <?= (int) $n ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>

  <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px">
    <?php foreach (['' => 'alle', 'gespraech' => 'echte Gespräche', 'probleme' => 'mit Befund',
                    'verstoss' => 'Verstöße', 'kunden' => 'Bestandskunden'] as $f => $wort): ?>
      <a class="knopf<?= $filter === $f ? ' haupt' : '' ?>" style="text-decoration:none"
         href="<?= Fmt::h(url('telefon?t=' . $tage . ($f !== '' ? '&f=' . $f : ''))) ?>"><?= Fmt::h($wort) ?></a>
    <?php endforeach; ?>
    <span style="flex:1"></span>
    <?php foreach ([7, 30, 90, 365] as $d): ?>
      <a class="knopf<?= $tage === $d ? ' haupt' : '' ?>" style="text-decoration:none"
         href="<?= Fmt::h(url('telefon?t=' . $d . ($filter !== '' ? '&f=' . $filter : ''))) ?>"><?= $d ?> T</a>
    <?php endforeach; ?>
  </div>

  <?php if (!$gespraeche): ?>
    <p style="color:var(--leise);font-size:13px">
      <?= $strato['eingerichtet']
            ? 'In diesem Zeitraum steht nichts. Der Abgleich läuft stündlich mit dem Cronlauf.'
            : 'Noch kein Zugang zu STRATO hinterlegt — dann bleibt hier nur unsere eigene Spur weiter unten.' ?></p>
  <?php endif; ?>

  <?php foreach ($gespraeche as $g):
    $w   = Strato::ausgangWort((string) $g['ausgang']);
    $tg  = array_filter(explode(',', (string) $g['tags']));
    $min = intdiv((int) $g['sekunden'], 60) . ':' . str_pad((string) ((int) $g['sekunden'] % 60), 2, '0', STR_PAD_LEFT);
    $wer = trim((string) ($g['kunde_name'] ?: $g['name'])) ?: 'Unbekannt';
    $ueberWidget = (string) $g['kunde_nummer'] === 'widget-call';
  ?>
    <details style="border-top:1px solid var(--linie);padding:12px 0">
      <summary style="cursor:pointer;display:flex;gap:12px;align-items:baseline;flex-wrap:wrap">
        <span style="color:var(--leise);font-size:12.5px;min-width:112px"><?= Fmt::h(Fmt::zeit((string) $g['begonnen'])) ?></span>
        <b style="font-size:13.5px"><?= Fmt::h($wer) ?></b>
        <?php if ($g['kunde_id'] !== null): ?>
          <span class="marke2 gut">Kunde</span>
        <?php elseif ($ueberWidget): ?>
          <span class="marke2">über die Website</span>
        <?php endif; ?>
        <span style="flex:1;color:var(--dim);font-size:13px"><?= Fmt::h((string) $g['betreff'] ?: '—') ?></span>
        <?php if ((int) $g['verstoss'] || (int) $g['erfunden']): ?>
          <span class="marke2 schlecht">Verstoß</span>
        <?php endif; ?>
        <?php if ($g['ausgang']): ?>
          <span class="marke2 <?= Fmt::h($w['ton']) ?>"><?= Fmt::h($w['wort']) ?></span>
        <?php endif; ?>
        <span style="color:var(--leise);font-size:12.5px;min-width:44px;text-align:right"><?= Fmt::h($min) ?></span>
      </summary>

      <div style="padding:14px 0 4px 112px">
        <?php if ((string) $g['zusammenfassung'] !== ''): ?>
          <p style="font-size:13.5px;line-height:1.7;margin:0 0 14px"><?= Fmt::h((string) $g['zusammenfassung']) ?></p>
        <?php endif; ?>

        <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:12px">
          <?php if (!$ueberWidget && (string) $g['kunde_nummer'] !== ''): ?>
            <span class="marke2"><?= Fmt::h((string) $g['kunde_nummer']) ?></span>
          <?php endif; ?>
          <?php if ($g['engagement']): ?>
            <span class="marke2">Anrufer: <?= Fmt::h(Strato::ENGAGEMENT[(string) $g['engagement']] ?? (string) $g['engagement']) ?></span>
          <?php endif; ?>
          <?php foreach ($tg as $t): ?>
            <span class="marke2 warnung"><?= Fmt::h(Strato::tagWort($t)) ?></span>
          <?php endforeach; ?>
          <?php if ((int) $g['werkzeuge'] > 0): ?>
            <span class="marke2"><?= (int) $g['werkzeuge'] ?> Werkzeugaufrufe</span>
          <?php endif; ?>
        </div>

        <?php if ((string) $g['notizen'] !== ''): ?>
          <p style="color:var(--dim);font-size:12.5px;line-height:1.65;margin:0 0 12px;
                    border-left:2px solid var(--linie);padding-left:12px">
            <b style="color:var(--leise)">Auswertung von STRATO:</b><br>
            <?= nl2br(Fmt::h((string) $g['notizen'])) ?></p>
        <?php endif; ?>

        <?php if ((int) $g['verstoss'] && (string) $g['verstoss_text'] !== ''): ?>
          <p style="color:var(--rot);font-size:12.5px;line-height:1.65;margin:0 0 12px;
                    border-left:2px solid var(--rot);padding-left:12px">
            <b>Gegen ihre Anweisungen:</b><br><?= Fmt::h((string) $g['verstoss_text']) ?></p>
        <?php endif; ?>

        <?php /* UNSERE EIGENE SPUR. Zusammengeführt über die Zeit, weil uns
                 die Telefonplattform keine gemeinsame Gesprächsnummer gibt —
                 dieselbe Näherung wie überall hier, und deshalb steht sie
                 ausdrücklich dabei. */ ?>
        <?php $sp = $spuren[(string) $g['id']] ?? []; ?>
        <?php if ($sp): ?>
          <div style="margin-top:6px">
            <div style="color:var(--leise);font-size:12px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">
              Was sie dabei getan hat</div>
            <table style="font-size:12.5px"><tbody>
            <?php foreach ($sp as $a):
              $m = json_decode((string) $a['meta'], true) ?: []; ?>
              <tr>
                <td style="color:var(--leise);white-space:nowrap;width:60px"><?= Fmt::h(substr((string) $a['created_at'], 11, 5)) ?></td>
                <td><?= Fmt::h((string) $a['title']) ?>
                  <?php if (is_array($m) && $m): ?>
                    <div style="color:var(--leise);font-size:11.5px;word-break:break-word">
                      <?= Fmt::h(mb_substr((string) json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 400)) ?></div>
                  <?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody></table>
          </div>
        <?php elseif ((int) $g['werkzeuge'] > 0): ?>
          <?php /* STRATO zählt Werkzeugaufrufe, wir finden keine — dann
                   stimmt die Zuordnung über die Zeit nicht. Das gehört
                   hingeschrieben und nicht verschwiegen: Eine leere Spur, die
                   „nichts getan" bedeutet, und eine leere Spur, die „nicht
                   gefunden" bedeutet, sind zwei verschiedene Dinge. */ ?>
          <p style="color:var(--leise);font-size:12.5px;margin:0">
            STRATO zählt <?= (int) $g['werkzeuge'] ?> Werkzeugaufrufe, in unserer Spur steht
            zu dieser Zeit keiner. Die beiden werden über die Uhrzeit zusammengeführt —
            gehen die Uhren auseinander, passiert genau das.</p>
        <?php else: ?>
          <p style="color:var(--leise);font-size:12.5px;margin:0">
            Kein Werkzeugaufruf — sie hat frei gesprochen.</p>
        <?php endif; ?>
      </div>
    </details>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ---------- Der Trichter ----------
         Ohne Zahlen weisst du in drei Monaten nicht, ob der Tarif sich traegt.
         Bewusst ueber 90 Tage: Bei ein paar Anrufen im Monat sagt eine
         Wochenzahl nichts. */ ?>
<?php $t = (array) ($trichter ?? []); ?>
<?php if ($t): ?>
<div class="block">
  <h2>Trägt es sich? <span class="mehr" style="font-weight:400;color:var(--leise)">letzte 90 Tage</span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 14px">
    Jede Zeile zählt <b>Gespräche</b>, nicht Dinge: von so vielen Anrufen ging ein
    Link raus, aus so vielen wurde ein ausgefüllter Bedarf, daraus eine Anfrage,
    daraus eine Bestellung. Interessant ist, <b>wo</b> es abreißt. Bleiben Anrufe
    ohne verschickten Link, fehlt Manuela das Argument; kommen Links ohne
    ausgefüllten Bedarf, ist der Weg zu lang.
    <br>Was nicht am Telefon angefangen hat, steht hier bewusst nicht drin — sonst
    wären es große Zahlen, die niemandem gehören. Ein Gespräch ist dabei eine
    Minute: Nachfragen in derselben Minute gehören zum selben Anruf. Eine
    Näherung, und deshalb steht sie hier.</p>
  <div class="trichter">
    <?php
      $stufen = [
        ['Anrufe', (int) ($t['anrufe'] ?? 0)],
        ['davon Link verschickt', (int) ($t['links'] ?? 0)],
        ['davon Bedarf ausgefüllt', (int) ($t['bedarf'] ?? 0)],
        ['davon Anfrage', (int) ($t['anfragen'] ?? 0)],
        ['davon Bestellung', (int) ($t['bestellungen'] ?? 0)],
      ];
      $groesste = max(1, ...array_column($stufen, 1));
      $vorher = null;
    ?>
    <?php foreach ($stufen as [$wort, $zahl]): ?>
      <div class="trichter__stufe">
        <div class="trichter__zahl"><?= (int) $zahl ?></div>
        <div class="trichter__balken"><span style="width:<?= max(2, (int) round($zahl / $groesste * 100)) ?>%"></span></div>
        <div class="trichter__wort"><?= Fmt::h($wort) ?><?php
          if ($vorher !== null && $vorher > 0): ?><i> · <?= (int) round($zahl / $vorher * 100) ?> %</i><?php
          endif; ?></div>
      </div>
    <?php $vorher = $zahl; endforeach; ?>
  </div>
  <?php if ((int) ($t['anrufe'] ?? 0) === 0): ?>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">
      Noch kein Anruf. Die Zahlen füllen sich, sobald STRATO die Aktionen ruft.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* ---------- Kommt die Anrufernummer an? ---------- */ ?>
<?php if (!empty($cli)): ?>
<div class="block">
  <h2>Kommt die Anrufernummer an?</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Davon hängt ab, ob Manuela Bestandskunden am Telefon erkennt oder ob jeder erst
    Namen und Kundennummer buchstabieren muss. Zwischen dem Anrufer und der Verwaltung
    liegen zwei fremde Systeme — die Weiterleitung beim Telefonanbieter und STRATO.
    Ob die Nummer die Strecke überlebt, steht nirgends vollständig geschrieben.
    Also wird es hier abgelesen statt behauptet.</p>
  <?php if ($cli['kommt_an'] === true): ?>
    <p class="hinweis gut">Ja — bei <?= (int) $cli['mit'] ?> von
      <?= (int) ($cli['mit'] + $cli['ohne']) ?> Nachschlage-Aufrufen kam eine Rufnummer mit.
      Die Kundenerkennung funktioniert.</p>
  <?php elseif ($cli['kommt_an'] === false): ?>
    <p class="hinweis schlecht">Nein — bei <?= (int) $cli['ohne'] ?> Aufrufen kam keine
      Rufnummer mit. Prüfe beim Telefonanbieter die Einstellung <b>„Show"</b>: Sie muss auf
      <b>Caller’s number</b> stehen, nicht auf <em>Called number</em>. Bis dahin fragt
      Manuela nach Namen oder Kundennummer — das ist gebaut und funktioniert.</p>
  <?php else: ?>
    <p class="hinweis" style="border-color:var(--linie);color:var(--dim)">Noch nicht entschieden:
      <?= (int) $cli['mit'] ?> mit Nummer, <?= (int) $cli['ohne'] ?> ohne. Ein einzelner
      Anrufer kann seine Nummer auch selbst unterdrückt haben — nach ein paar Anrufen steht
      es fest.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* ---------- Gespräche ohne Ergebnis ----------
   Steht direkt unter „Heute anrufen", weil es dieselbe Art Arbeit ist: Hier
   hat jemand angerufen, sie hat gearbeitet — nachgesehen, beraten, geholfen —
   und am Ende ist nichts herausgegangen. Kein Link, kein Rückruf, nichts.
   Von außen sieht das nicht nach Technik aus, sondern nach jemandem, der
   seine Zusagen nicht hält. Deshalb wird es nicht gemeldet, sondern
   hingestellt. */ ?>
<?php $off = (array) ($offen ?? []); ?>
<?php if ($off): ?>
<div class="block" style="border-color:var(--rot)">
  <h2>Angefangen und nichts daraus geworden <span class="marke2"><?= count($off) ?></span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    In diesen Gesprächen hat Manuela gearbeitet, aber nichts verschickt und keinen
    Rückruf angelegt. Entweder hat der Anrufer aufgelegt — oder sie hat etwas zugesagt
    und nicht eingelöst. Beides ist einen Anruf wert, solange es frisch ist.</p>
  <table class="tab">
    <thead><tr><th>WANN</th><th>WER</th><th>WAS SCHON DA WAR</th></tr></thead>
    <tbody>
      <?php foreach ($off as $o): ?>
        <tr>
          <td style="white-space:nowrap;vertical-align:top">
            <?= Fmt::h(Fmt::zeit((string) $o['wann'])) ?>
            <div style="color:var(--leise);font-size:12px">
              <?= (int) $o['stunden'] < 24
                    ? 'vor ' . (int) $o['stunden'] . ' h'
                    : 'vor ' . (int) round(((int) $o['stunden']) / 24) . ' Tagen' ?></div>
          </td>
          <td style="vertical-align:top">
            <?php if ((int) $o['kunde_id'] > 0): ?>
              <a href="<?= Fmt::h(url('kunden/' . (int) $o['kunde_id'])) ?>"><?= Fmt::h((string) $o['wer']) ?></a>
            <?php else: ?><?= Fmt::h((string) $o['wer']) ?><?php endif; ?>
          </td>
          <td style="vertical-align:top;color:var(--leise);font-size:12.5px">
            <?php if ((string) $o['seite'] !== ''): ?>
              Seite angesehen: <b><?= Fmt::h((string) $o['seite']) ?></b><br>
            <?php endif; ?>
            <?php if (!empty($o['gefragt'])): ?>
              Beratung bis: <?= Fmt::h(implode(', ', array_map('strval', (array) $o['gefragt']))) ?><br>
            <?php endif; ?>
            <?= (int) $o['schritte'] ?> Schritt<?= (int) $o['schritte'] === 1 ? '' : 'e' ?> im Gespräch
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php /* ---------- Was sie sich angewöhnt hat ----------
   Warum das hier steht und nicht nur in einer Meldung: Eine Meldung klickt
   man weg. Ein Assistent driftet aber nicht an einem Tag, sondern über
   Wochen — und was man dagegen tun kann, muss dort stehen, wo man ohnehin
   nachsieht. Geändert wird nichts von allein: unten stehen Sätze zum
   Eintragen, eintragen muss sie ein Mensch. */ ?>
<?php $rb = $rueckblick ?? null; ?>
<?php if (is_array($rb) && !empty($rb['befunde'])): ?>
<div class="block" style="border-color:var(--gelb,#e0b400)">
  <h2>Was sie sich angewöhnt hat
    <span class="mehr" style="font-weight:400;color:var(--leise)">
      <?= (int) ($rb['gespraeche'] ?? 0) ?> Gespräche der letzten <?= (int) ($rb['tage'] ?? 7) ?> Tage</span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Jedes einzelne Gespräch sieht in Ordnung aus — sichtbar wird es erst im Muster.
    Rechts steht der Satz, der es abstellt: bei STRATO unter
    <b>Sprache, Stimme &amp; Verhalten → Verhalten im Telefonat</b> ergänzen.</p>
  <table class="tab">
    <thead><tr><th>WAS AUFFÄLLT</th><th>WAS DAGEGEN HILFT</th></tr></thead>
    <tbody>
      <?php foreach ($rb['befunde'] as $b): ?>
        <tr>
          <td style="vertical-align:top"><?= Fmt::h((string) ($b['satz'] ?? '')) ?></td>
          <td style="vertical-align:top;color:var(--leise)"><?= Fmt::h((string) ($b['vorschlag'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!empty($rb['stand'])): ?>
    <p style="color:var(--leise);font-size:12px;margin:10px 0 0">
      Stand: <?= Fmt::h(Fmt::zeit((string) $rb['stand'])) ?></p>
  <?php endif; ?>
</div>
<?php elseif (is_array($rb)): ?>
<div class="block">
  <h2>Was sie sich angewöhnt hat</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 0">
    Nichts aufgefallen in <?= (int) ($rb['gespraeche'] ?? 0) ?> Gesprächen der letzten
    <?= (int) ($rb['tage'] ?? 7) ?> Tage. Der Rückblick läuft einmal die Woche von selbst.</p>
</div>
<?php endif; ?>

<?php /* ---------- Woran es hakt ---------- */ ?>
<?php if (!empty($haken)): ?>
<div class="block">
  <h2>Woran es hakt <span class="mehr" style="font-weight:400;color:var(--leise)">letzte 90 Tage</span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Jede Zeile ist jemand, der angerufen hat, weil er nicht weiterkam. Zwanzig Anrufe
    zum Fragebogen sind kein Support-Fall, sondern ein Produktfehler — dann ist nicht
    der Assistent zu verbessern, sondern der Fragebogen.
    <br><b>Geholfen</b> heißt: Der Assistent hat wirklich etwas verschickt. Ob es dem
    Anrufer danach reichte, wissen wir nicht — das steht hier bewusst nicht.</p>
  <table class="tab">
    <thead><tr><th>Woran</th><th style="text-align:right">Anrufe</th>
               <th style="text-align:right">davon geholfen</th></tr></thead>
    <tbody>
    <?php
      $worte = ['fragebogen' => 'Fragebogen', 'bezahlung' => 'Bezahlung',
                'link_weg' => 'Link weg', 'vorschau' => 'Entwurf',
                'zugang' => 'Zugang zur eigenen Seite', 'sonstiges' => 'Sonstiges'];
    ?>
    <?php foreach ($haken as $h): ?>
      <tr>
        <td><?= Fmt::h($worte[$h['problem']] ?? $h['problem']) ?></td>
        <td style="text-align:right"><?= (int) $h['anzahl'] ?></td>
        <td style="text-align:right;color:var(--leise)"><?= (int) $h['geloest'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php /* ---------- Wissenslücken ---------- */ ?>
<?php if (!empty($luecken)): ?>
<div class="block" style="border-color:var(--cyan)">
  <h2>Was Manuela nicht wusste <span class="marke2"><?= count($luecken) ?></span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Die wertvollste Liste dieser Seite. Jede Zeile ist eine Frage, die ein echter
    Anrufer gestellt hat und auf die es keine Antwort gab. Beantworte sie in der
    Wissensbasis bei STRATO — dann verschwindet sie hier.</p>
  <table class="tab"><tbody>
    <?php foreach ($luecken as $l): ?>
      <tr>
        <td style="white-space:nowrap;color:var(--leise);font-size:12.5px"><?=
          Fmt::h(Fmt::zeit((string) ($l['wann'] ?? ''))) ?></td>
        <td><?= Fmt::h((string) ($l['frage'] ?? '')) ?></td>
        <td style="text-align:right">
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="telefon_luecke_weg">
            <input type="hidden" name="zurueck" value="telefon">
            <input type="hidden" name="schluessel" value="<?= Fmt::h((string) ($l['schluessel'] ?? '')) ?>">
            <button class="knopf">Erledigt</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<div class="block">
  <h2>Was der Assistent getan hat</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Jeder Aufruf steht hier. Ein Assistent, der unbeobachtet in die Verwaltung schreibt,
    ist so viel wert wie das Vertrauen, das man ihm entgegenbringt — und das hält nur,
    solange man nachsehen kann.</p>
  <?php if (!$verlauf): ?>
    <p style="color:var(--leise);font-size:13px">Noch nichts.</p>
  <?php else: ?>
    <table class="tab"><thead><tr><th>Wann</th><th>Was</th><th>Kunde</th></tr></thead><tbody>
      <?php foreach ($verlauf as $z): ?>
        <tr>
          <td style="white-space:nowrap;color:var(--leise)"><?= Fmt::h(Fmt::zeit((string) $z['created_at'])) ?></td>
          <td><?= Fmt::h((string) $z['title']) ?></td>
          <td><?php if ($z['customer_id']): ?>
            <a href="<?= Fmt::h(url('kunden/' . (int) $z['customer_id'])) ?>"><?=
              Fmt::h((string) sicher(static fn() => Db::wert(
                'SELECT name FROM customers WHERE id = ?', [(int) $z['customer_id']], '—'), '—')) ?></a>
          <?php else: ?><span style="color:var(--leise)">—</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
