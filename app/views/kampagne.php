<?php
/**
 * Marketing · eine Kampagne (Growth Engine Phase 3 — 30.09.2026).
 *
 * Link und QR zum Teilen, Zahlen im Zeitraum, Werbemittel mit eigenem Link,
 * Kosten (auch mit Beleg aus „Ausgaben“), wer darüber kam, Einstellungen.
 *
 * Erwartet: $z, $k, $zahl, $jeWerbemittel, $werbemittel, $kosten, $kostenZeitraum, $belege, $kontakte.
 * Seit 01.10.2026 (Telegram Growth Engine T1) auch $tgKampagne: Bot-Link, Kanal, Kanal-Einladungslink, Zahlen.
 */
[$von, $bis, $zk] = $z;
$leer = MkKampagne::LEER;
$branchen = MkKampagne::branchen();
$budget = MkKampagne::budget($k);
$zielWort = MkKampagne::ZIEL_ARTEN[$k['ziel_art']][0] ?? 'Leads';
$zielWert = MkKampagne::zielWert($k, $zahl + $leer);
$lz = MkKampagne::laufzeit($k);
$zahl += $leer;
$geld = static fn(int $c): string => Fmt::geld($c);
$n = static fn(int $x): string => number_format($x, 0, ',', '.');
$datum = static fn(string $t): string => date('d.m.Y', strtotime($t));
$id = (int) $k['id'];
$hier = static fn(array $mehr = []): string => url('kampagnen/' . $id) . '?' . http_build_query(array_filter(array_merge(
    ['z' => $zk, 'von' => $zk === 'frei' ? $von : null, 'bis' => $zk === 'frei' ? $bis : null], $mehr), static fn($v) => $v !== null && $v !== ''));
$link = MkKampagne::link($k);
$lang = MkKampagne::basis() . MkKampagne::zielAdresse($k);
$qr = MkKampagne::qr($link);
$statusKlasse = $k['status'] === 'aktiv' ? 'gut' : ($k['status'] === 'pausiert' ? 'warnung' : '');
$q = static fn(int $a, int $b): string => $b > 0 ? number_format($a / $b * 100, 1, ',', '.') . ' %' : '—';
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1><?= Fmt::h($k['name']) ?> <span class="marke2 <?= $statusKlasse ?>" style="vertical-align:4px"><?= Fmt::h(MkKampagne::STATUS[$k['status']] ?? $k['status']) ?></span></h1>
    <div class="weg"><?= Fmt::h(implode(' · ', array_filter([
        MkKampagne::PLATTFORMEN[$k['plattform']] ?? $k['plattform'],
        $branchen[$k['branche']] ?? '',
        $k['cta'] === 'eigen' ? 'CTA „' . $k['cta_text'] . '“' : (isset(MkKampagne::CTA[$k['cta']]) ? 'CTA „' . MkKampagne::CTA[$k['cta']] . '“' : ''),
        'Zielseite ' . $k['ziel'],
        $lz === 'offen' ? 'angelegt am ' . $datum((string) $k['created_at']) : ($lz === 'vor' ? 'startet am ' . $datum((string) $k['start_am']) : ($lz === 'vorbei' ? 'abgelaufen am ' . $datum((string) $k['ende_am']) : 'läuft' . ($k['ende_am'] ? ' bis ' . $datum((string) $k['ende_am']) : ''))),
      ]))) ?></div>
  </div>
  <a class="knopf" href="<?= Fmt::h(url('kampagnen')) ?>">‹ Alle Kampagnen</a>
</div>

<?php if ($k['status'] !== 'aktiv'): ?>
  <div class="hinweis" style="margin-bottom:16px">Diese Kampagne ist <?= $k['status'] === 'pausiert' ? 'pausiert' : 'beendet' ?>: Der Link führt still auf die Startseite und zählt nichts mehr — gedruckte Flyer landen so nie auf einer Fehlerseite.</div>
<?php endif; ?>

<div class="block">
  <h2>Zum Teilen</h2>
  <div class="mk-teilen">
    <div>
      <label class="leise" for="kl_kurz" style="margin:0">Kurzlink — für Beiträge, Bio, Stories, Newsletter, Flyer</label>
      <div class="mk-link"><input id="kl_kurz" readonly value="<?= Fmt::h($link) ?>"><button class="knopf" type="button" data-kopieren="kl_kurz">Kopieren</button></div>
      <label class="leise" for="kl_lang" style="margin:6px 0 0">Langer Link mit UTM — nur, wenn ein Anzeigen-Werkzeug die Zieladresse selbst will</label>
      <div class="mk-link"><input id="kl_lang" readonly value="<?= Fmt::h($lang) ?>"><button class="knopf" type="button" data-kopieren="kl_lang">Kopieren</button></div>
      <p class="mk-fein" style="margin:0">Der Kurzlink zählt jeden Klick und merkt sich die Kampagne für diesen Besuch; der lange Link wird nur über die UTM-Angaben in der anonymen Zählung sichtbar. Für eigene Beiträge immer den Kurzlink nehmen. Deine eigenen Klicks (angemeldet in der Verwaltung) zählen nicht.</p>
    </div>
    <figure style="margin:0;text-align:center">
      <div class="mk-qr"><?= $qr ?></div>
      <figcaption class="mk-fein" style="margin-top:6px"><a download="qr-<?= Fmt::h($k['code']) ?>.svg" href="data:image/svg+xml;base64,<?= base64_encode($qr) ?>">QR als SVG laden</a></figcaption>
    </figure>
  </div>
</div>

<?php $tgK = $tgKampagne ?? null; if ($tgK !== null): $tgZ = $tgK['zahl']; $tgE = $tgK['einladung']; ?>
<div class="block" id="telegram">
  <h2>Telegram <span class="mehr">dieselbe Kampagne im Bot und im Kanal</span></h2>
  <?php if ($tgK['bot'] === ''): ?>
    <p class="leise">Der Bot ist noch nicht eingerichtet (Einstellungen → Telegram) — dann erscheint hier sein Link für diese Kampagne.</p>
  <?php else: ?>
  <div class="mk-teilen">
    <div>
      <label class="leise" for="kl_tgbot" style="margin:0">Bot-Link — startet den Vecom-Bot; Preisrechner, Anfrage und Kunde zählen für diese Kampagne</label>
      <div class="mk-link"><input id="kl_tgbot" readonly value="<?= Fmt::h($tgK['bot']) ?>"><button class="knopf" type="button" data-kopieren="kl_tgbot">Kopieren</button></div>
      <?php if ($tgK['kanal']['id'] === ''): ?>
        <p class="mk-fein" style="margin:6px 0 0">Kanal-Link: erst den Kanal hinterlegen (Einstellungen → Telegram).</p>
      <?php elseif ($tgE): ?>
        <label class="leise" for="kl_tgkanal" style="margin:6px 0 0">Kanal-Link — tritt dem Kanal bei; Beitritte darüber zählen für diese Kampagne</label>
        <div class="mk-link"><input id="kl_tgkanal" readonly value="<?= Fmt::h((string) $tgE['link']) ?>"><button class="knopf" type="button" data-kopieren="kl_tgkanal">Kopieren</button></div>
      <?php else: ?>
        <form method="post" action="<?= Fmt::h(url('kampagnen/' . $id)) ?>" style="margin:8px 0 0">
          <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="kampagne_telegram_link"><input type="hidden" name="id" value="<?= $id ?>">
          <button class="knopf">Kanal-Link für diese Kampagne anlegen</button>
          <span class="mk-fein">Ein eigener Einladungslink des Kanals „<?= Fmt::h($tgK['kanal']['titel'] ?: 'Vecom Design') ?>“ — so sieht man, wie viele über diese Kampagne beitreten.</span>
        </form>
      <?php endif; ?>
      <p class="mk-fein" style="margin:6px 0 0">Gezählt wird, wie viele über diese Links kamen — nicht, wer. Aufrufe einzelner Kanalbeiträge gibt Telegram an Bots nicht heraus; die stehen in Telegrams eigener Kanalstatistik.</p>
    </div>
    <figure style="margin:0;text-align:center">
      <div class="mk-qr"><?= MkKampagne::qr($tgK['bot']) ?></div>
      <figcaption class="mk-fein" style="margin-top:6px">QR zum Bot</figcaption>
    </figure>
  </div>
  <div class="karten mk-karten" style="margin-top:12px">
    <div class="karte"><h3>Bot-Starts</h3><div class="wert"><?= $n($tgZ['bot_start']) ?></div><div class="neben"><?= $n($tgZ['bot_neu']) ?> davon neue Nutzer · im Zeitraum</div></div>
    <div class="karte"><h3>Kanal-Beitritte</h3><div class="wert"><?= $tgE ? $n($tgZ['kanal_bei']) : '–' ?></div><div class="neben"><?= $tgE ? 'über den Kanal-Link, im Zeitraum' : 'erst mit Kanal-Link messbar' ?></div></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<form class="mk-filter" method="get" action="<?= Fmt::h(url('kampagnen/' . $id)) ?>">
  <nav class="mk-chips" aria-label="Zeitraum">
    <?php foreach (MkKennzahlen::ZEITRAEUME as $zs => $zw): if ((string) $zs === 'frei') { continue; } ?>
      <a href="<?= Fmt::h($hier(['z' => (string) $zs, 'von' => null, 'bis' => null])) ?>"<?= $zk === (string) $zs ? ' aria-current="page"' : '' ?>><?= Fmt::h($zw) ?></a>
    <?php endforeach; ?>
  </nav>
  <input type="hidden" name="z" value="frei">
  <div class="feld"><label for="kd_von" class="leise" style="margin:0">von</label><input id="kd_von" type="date" name="von" value="<?= Fmt::h($von) ?>"></div>
  <div class="feld"><label for="kd_bis" class="leise" style="margin:0">bis</label><input id="kd_bis" type="date" name="bis" value="<?= Fmt::h($bis) ?>"></div>
  <button class="knopf">Zeitraum anzeigen</button>
</form>

<div class="karten mk-karten">
  <div class="karte" style="border-color:var(--linie2)"><h3>Ziel · <?= Fmt::h($zielWort) ?></h3><div class="wert"><?= $n($zielWert) ?></div><div class="neben"><?= $k['ziel_art'] === 'besuche' ? $n($zahl['klicks']) . ' Klicks' : Fmt::h($q($zielWert, $zahl['besuche'])) . ' der Besuche' ?></div></div>
  <div class="karte"><h3>Klicks</h3><div class="wert"><?= $n($zahl['klicks']) ?></div><div class="neben"><?= $n($zahl['besuche']) ?> Besuche · <?= $n($zahl['checks']) ?> Website-Checks · <?= $n($zahl['termine']) ?> Termine</div></div>
  <div class="karte"><h3>Leads</h3><div class="wert"><?= $n($zahl['leads']) ?></div><div class="neben"><?= Fmt::h($q($zahl['leads'], $zahl['besuche'])) ?> der Besuche · <?= $n($zahl['kunden']) ?> Kunden · <?= $n($zahl['angebote']) ?> Angebote</div></div>
  <div class="karte"><h3>Umsatz</h3><div class="wert"><?= Fmt::h($geld($zahl['umsatz'])) ?></div>
    <div class="neben">Kosten <?= Fmt::h($geld($kostenZeitraum)) ?><?php if ($kostenZeitraum > 0): ?> · ROAS <?= number_format($zahl['umsatz'] / $kostenZeitraum, 1, ',', '.') ?> · pro Lead <?= $zahl['leads'] > 0 ? Fmt::h($geld(intdiv($kostenZeitraum, $zahl['leads']))) : '—' ?> · pro Kunde <?= $zahl['kunden'] > 0 ? Fmt::h($geld(intdiv($kostenZeitraum, $zahl['kunden']))) : '—' ?><?php endif; ?></div></div>
</div>

<?php
  $stufen = [['Klicks', $zahl['klicks']], ['Besuche', $zahl['besuche']], ['Preisrechner abgeschlossen', $zahl['rechner']], ['Leads', $zahl['leads']],
             ['Angebote', $zahl['angebote']], ['Kunden', $zahl['kunden']], ['Zahlungen', $zahl['zahlungen']]];
  $max = max(1, (int) $stufen[0][1]);
?>
<div class="block">
  <h2>Weg zum Kunden <span class="mehr">im Zeitraum</span></h2>
  <div class="trichter mk-trichter">
    <?php foreach ($stufen as $i => [$wort, $wert]): $vor = $i > 0 ? (int) $stufen[$i - 1][1] : 0; ?>
      <div class="trichter__stufe">
        <span class="trichter__zahl"><?= $n((int) $wert) ?></span>
        <span class="trichter__balken" aria-hidden="true"><span style="width:<?= max(1, round($wert / $max * 100)) ?>%"></span></span>
        <span class="trichter__wort"><?= Fmt::h($wort) ?><?php if ($i > 0 && $vor > 0 && $wert <= $vor): ?> <i>· <?= Fmt::h($q((int) $wert, $vor)) ?> der Stufe davor</i><?php endif; ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="block" id="werbemittel">
  <h2>Werbemittel <span class="mehr">je Beitrag, Anzeige oder Flyer ein eigener Link</span></h2>
  <?php $ohne = $jeWerbemittel[0] ?? null; ?>
  <?php if ($werbemittel || $ohne): ?>
  <div class="tabellenrahmen">
    <table class="mk-tab">
      <thead><tr><th>Werbemittel</th><th>Link</th><th class="num">Klicks</th><th class="num">Besuche</th><th class="num">Leads</th><th class="num">Kunden</th><th class="num">Umsatz</th></tr></thead>
      <tbody>
        <?php foreach ($werbemittel as $w): $wz = ($jeWerbemittel[(int) $w['id']] ?? []) + $leer; $wl = MkKampagne::link($k, $w); ?>
          <tr>
            <td class="mk-name"><b><?= Fmt::h($w['name']) ?></b><br><span class="mk-code"><?= Fmt::h(MkKampagne::ARTEN[$w['art']] ?? $w['art']) ?></span></td>
            <td><span class="mk-link" style="flex-wrap:nowrap"><input id="wm_<?= (int) $w['id'] ?>" readonly value="<?= Fmt::h($wl) ?>" style="min-width:220px"><button class="knopf" type="button" data-kopieren="wm_<?= (int) $w['id'] ?>">Kopieren</button></span></td>
            <td class="num"><?= $n($wz['klicks']) ?></td><td class="num"><?= $n($wz['besuche']) ?></td><td class="num"><?= $n($wz['leads']) ?></td>
            <td class="num"><?= $n($wz['kunden']) ?></td><td class="num"><?= Fmt::h($geld($wz['umsatz'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($ohne): $ohne += $leer; ?>
          <tr><td class="mk-name">über den Kurzlink ohne Werbemittel</td><td class="mk-code">/k/<?= Fmt::h($k['code']) ?></td>
            <td class="num"><?= $n($ohne['klicks']) ?></td><td class="num"><?= $n($ohne['besuche']) ?></td><td class="num"><?= $n($ohne['leads']) ?></td>
            <td class="num"><?= $n($ohne['kunden']) ?></td><td class="num"><?= Fmt::h($geld($ohne['umsatz'])) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="leise">Noch kein Werbemittel. Wer mehrere Beiträge zur selben Kampagne teilt, gibt jedem seinen Link — dann zeigt diese Tabelle, welcher am besten wirkt.</p>
  <?php endif; ?>
  <form method="post" action="<?= Fmt::h(url('kampagnen/' . $id)) ?>" class="mk-formular" style="margin-top:14px">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>">
    <input type="hidden" name="tat" value="werbemittel_anlegen"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="feld"><label for="wm_name">Name</label><input id="wm_name" name="name" required maxlength="120" placeholder="z. B. Reel 3 · Vorher/Nachher"></div>
    <div class="feld"><label for="wm_art">Art</label><select id="wm_art" name="art"><?php foreach (MkKampagne::ARTEN as $ak => $aw): ?><option value="<?= $ak ?>"><?= Fmt::h($aw) ?></option><?php endforeach; ?></select></div>
    <div class="feld"><label for="wm_code">Code <span class="mk-fein">(frei = aus dem Namen)</span></label><input id="wm_code" name="code" maxlength="12" pattern="[a-z0-9][a-z0-9\-]{0,11}"></div>
    <div><button class="knopf">Werbemittel mit eigenem Link anlegen</button></div>
  </form>
</div>

<div class="mk-zwei">
  <div class="block" id="kosten">
    <h2>Kosten <span class="mehr">gesamt <?= Fmt::h($geld((int) array_sum(array_column($kosten, 'betrag_cents')))) ?></span></h2>
    <?php if ($budget !== null): $bf = ['ok' => 'var(--gruen)', 'knapp' => 'var(--gelb)', 'erreicht' => 'var(--rot)'][$budget['stufe']]; ?>
      <div style="margin:0 0 12px">
        <span class="mk-budget gross"><i style="width:<?= min(100, (int) round($budget['anteil'])) ?>%;background:<?= $bf ?>"></i></span>
        <span class="mk-fein">Budget <?= Fmt::h($geld($budget['ausgegeben'])) ?> von <?= Fmt::h($geld($budget['grenze'])) ?> <?= $budget['art'] === 'monat' ? 'in diesem Monat' : 'insgesamt' ?> · <?= number_format($budget['anteil'], 0, ',', '.') ?> %<?= $budget['stufe'] === 'erreicht' ? ' — erreicht: die Anzeige bei der Plattform pausieren oder das Budget erhöhen' : ($budget['stufe'] === 'knapp' ? ' — bald erreicht' : '') ?></span>
      </div>
    <?php endif; ?>
    <?php if ($kosten): ?>
      <div class="tabellenrahmen"><table class="mk-tab">
        <thead><tr><th>Datum</th><th>Notiz / Beleg</th><th class="num">Betrag</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($kosten as $ko): ?>
            <tr><td><?= Fmt::h($datum((string) $ko['datum'])) ?></td>
              <td class="mk-name"><?= Fmt::h($ko['notiz']) ?><?php if ($ko['ausgabe_id'] !== null): ?><br><span class="mk-code">Beleg <?= Fmt::h((string) $ko['beleg_nr']) ?> · <?= Fmt::h((string) $ko['lieferant']) ?></span><?php endif; ?></td>
              <td class="num"><?= Fmt::h($geld((int) $ko['betrag_cents'])) ?></td>
              <td><form method="post" action="<?= Fmt::h(url('kampagnen/' . $id)) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="kampagne_kosten_loeschen"><input type="hidden" name="kosten_id" value="<?= (int) $ko['id'] ?>"><button class="knopf klein" title="Diesen Kostenposten entfernen">Entfernen</button></form></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php else: ?>
      <p class="leise">Noch keine Kosten. Ohne Kosten gibt es keinen ROAS und keine Kosten pro Lead — organische Beiträge kosten nichts, dann bleibt es so.</p>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('kampagnen/' . $id)) ?>" class="mk-formular" style="margin-top:14px">
      <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>">
      <input type="hidden" name="tat" value="kampagne_kosten"><input type="hidden" name="id" value="<?= $id ?>">
      <div class="feld"><label for="ko_datum">Datum</label><input id="ko_datum" type="date" name="datum" value="<?= date('Y-m-d') ?>" required></div>
      <div class="feld"><label for="ko_betrag">Betrag netto (€)</label><input id="ko_betrag" name="betrag" inputmode="decimal" placeholder="z. B. 50,00"></div>
      <?php if ($belege): ?>
      <div class="feld breit"><label for="ko_beleg">oder Beleg aus „Ausgaben“ verbinden <span class="mk-fein">(zählt dann im Überblick nicht doppelt)</span></label>
        <select id="ko_beleg" name="ausgabe_id"><option value="">— kein Beleg —</option>
          <?php foreach ($belege as $be): ?><option value="<?= (int) $be['id'] ?>"><?= Fmt::h($datum((string) $be['datum']) . ' · ' . $be['lieferant'] . ($be['titel'] ? ' · ' . $be['titel'] : '') . ' · ' . Fmt::geld((int) ($be['netto_cents'] ?: $be['brutto_cents']))) ?></option><?php endforeach; ?>
        </select></div>
      <?php endif; ?>
      <div class="feld breit"><label for="ko_notiz">Notiz</label><input id="ko_notiz" name="notiz" maxlength="200" placeholder="z. B. Meta-Anzeige 7 Tage"></div>
      <div class="breit"><button class="knopf">Kosten eintragen</button></div>
    </form>
  </div>

  <div class="block">
    <h2>Wer darüber kam <span class="mehr">nur wer selbst seine Daten eintrug</span></h2>
    <?php if ($kontakte): ?>
      <div class="tabellenrahmen"><table class="mk-tab">
        <thead><tr><th>Kontakt</th><th>Erster Besuch</th><th>Stand</th></tr></thead>
        <tbody>
          <?php foreach ($kontakte as $c): ?>
            <tr><td class="mk-name"><a href="<?= Fmt::h(url('kunden/' . (int) $c['id'])) ?>"><?= Fmt::h(Fmt::name($c['name'], $c['company'])) ?></a><?= $c['company'] ? '<br><span class="mk-code">' . Fmt::h($c['company']) . '</span>' : '' ?><?= $c['werbemittel'] ? '<br><span class="mk-code">über ' . Fmt::h($c['werbemittel']) . '</span>' : '' ?></td>
              <td><?= Fmt::h($datum((string) $c['erster_besuch'])) ?></td>
              <td><?= Fmt::h(Spur::STATUS[$c['status']] ?? (string) $c['status']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php else: ?>
      <p class="leise" style="margin:0">Noch niemand. Besucher bleiben anonym, bis sie selbst ihre E-Mail eintragen oder anfragen — dann stehen sie hier mit Link zur Akte.</p>
    <?php endif; ?>
  </div>
</div>

<details class="block" id="einstellungen">
  <summary style="cursor:pointer;font-weight:600">Einstellungen der Kampagne</summary>
  <form method="post" action="<?= Fmt::h(url('kampagnen/' . $id)) ?>" class="mk-formular" style="margin-top:14px">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>">
    <input type="hidden" name="tat" value="kampagne_aendern"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="feld"><label for="ke_name">Name</label><input id="ke_name" name="name" value="<?= Fmt::h($k['name']) ?>" maxlength="120" required></div>
    <div class="feld"><label for="ke_ziel">Zielseite</label><input id="ke_ziel" name="ziel" list="ke_ziele" value="<?= Fmt::h($k['ziel']) ?>" maxlength="180" required pattern="/[A-Za-z0-9/_.\-]*">
      <datalist id="ke_ziele"><?php foreach (MkKampagne::ZIELE as $zp => $zw): ?><option value="<?= Fmt::h($zp) ?>"><?= Fmt::h($zw) ?></option><?php endforeach; ?></datalist></div>
    <div class="feld"><label for="ke_status">Status</label><select id="ke_status" name="status"><?php foreach (MkKampagne::STATUS as $sk => $sw): ?><option value="<?= $sk ?>"<?= $k['status'] === $sk ? ' selected' : '' ?>><?= Fmt::h($sw) ?></option><?php endforeach; ?></select></div>
    <?php $kf = $k; $kfId = 'ke'; require __DIR__ . '/mk_kampagne_felder.php'; ?>
    <div class="feld breit"><label for="ke_notiz">Notiz</label><input id="ke_notiz" name="notiz" value="<?= Fmt::h($k['notiz']) ?>" maxlength="500"></div>
    <p class="mk-fein breit" style="margin:0">Der Kurz-Code <b>/k/<?= Fmt::h($k['code']) ?></b> bleibt fest — er steht vielleicht schon in Beiträgen oder auf Flyern. Plattform ebenso; für eine andere Plattform eine neue Kampagne anlegen.</p>
    <div class="breit"><button class="knopf">Einstellungen speichern</button></div>
  </form>
</details>
