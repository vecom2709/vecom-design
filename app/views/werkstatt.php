<?php /* ==========================================================================
     DIE WERKSTATT — alle Kundenseiten auf einem Blatt

     Bisher lag jede Kundenseite in ihrem eigenen Projekt, und ob sie noch
     lebt, stand im Monitoring, und ob sie die Pflichtangaben hat, stand
     nirgends. Wer wissen wollte, wie es um die zwoelf Seiten steht, die er
     gebaut hat, oeffnete zwoelf Seiten.

     Hier steht es nebeneinander. Eine Kachel je Seite: was sie ist, ob sie
     antwortet, was die letzte Abnahme ergab, und ein Fenster, in dem sie zu
     sehen ist.

     WARUM ECHTE FENSTER UND KEINE BILDCHEN

     Fuer Bildchen braeuchte der Server einen Browser — den hat ein
     geteilter Webspace nicht, und ein fremder Dienst dafuer waere eine
     Abhaengigkeit, die nichts traegt. Ein Fenster zeigt dafuer immer den
     jetzigen Stand, nicht den von letzter Woche. Geladen wird es erst, wenn
     es in den Blick kommt; und wo eine Seite das Einbetten verbietet,
     bleibt es leer — dann sagt die Kachel es und verlinkt sie stattdessen.
     ========================================================================== */ ?>
<div class="kopf"><div><h1>Werkstatt</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    <?= count($liste) ?> <?= count($liste) === 1 ? 'Kundenseite' : 'Kundenseiten' ?>.
    Klick auf eine Kachel führt ins Projekt.</p></div>
  <div class="rechts">
    <a class="knopf" href="<?= Fmt::h(url('standard')) ?>">Vecom-Standard</a>
    <a class="knopf" href="<?= Fmt::h(url('muster')) ?>">Bausteine</a>
  </div>
</div>

<?php /* ==========================================================================
     WAS NOCH FEHLT

     Eine Anleitung, die man einmal liest und dann sucht, ist keine. Hier
     steht der Einrichtungsstand da, wo gearbeitet wird — und verschwindet,
     sobald alles steht. Danach nimmt der Streifen keinen Platz mehr weg.

     Der Cronjob steht mit dabei, weil ohne ihn nichts von selbst laeuft:
     keine Betreuungsmonate, keine erste Zahlungserinnerung, kein
     Monitoring, keine naechtliche Abnahme. Das ist der eine Handgriff, der
     mehr bringt als alles, was man hier klicken kann.
     ========================================================================== */ ?>
<?php if ($einrichtung['offen']): ?>
  <div class="block" style="border-color:var(--cyan)">
    <h2>Noch einzurichten
      <span class="mehr"><?= count($einrichtung['offen']) ?> von <?= (int) $einrichtung['gesamt'] ?> offen</span></h2>
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:12px">
      <?php foreach ($einrichtung['punkte'] as $pt): ?>
        <li style="display:flex;gap:10px;align-items:flex-start">
          <span style="flex:0 0 auto;width:18px;text-align:center;font-weight:700;color:<?=
            $pt['fertig'] ? 'var(--gruen,#2fbf71)' : 'var(--leise)' ?>"><?=
            $pt['fertig'] ? '✓' : '·' ?></span>
          <div style="min-width:0">
            <div style="font-size:13.5px;<?= $pt['fertig'] ? 'color:var(--leise)' : 'font-weight:600' ?>"><?=
              Fmt::h((string) $pt['was']) ?></div>
            <?php if (!$pt['fertig']): ?>
              <div style="color:var(--dim);font-size:12.5px;line-height:1.6;margin-top:2px"><?=
                Fmt::h((string) $pt['warum']) ?>
                <?php if (!empty($pt['ziel'])): ?>
                  <a href="<?= Fmt::h(url((string) $pt['ziel'])) ?>"><?= Fmt::h((string) $pt['wohin']) ?></a>
                <?php endif; ?></div>
            <?php else: ?>
              <div style="color:var(--leise);font-size:12.5px;margin-top:2px"><?= Fmt::h((string) $pt['stand']) ?></div>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!$liste): ?>
  <div class="block"><div class="leer">Noch kein Projekt. Sobald eine Bestellung bezahlt ist,
    steht die Seite hier.</div></div>
<?php else: ?>

<div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(310px,1fr))">
<?php foreach ($liste as $w): ?>
  <?php
    $wer   = trim((string) ($w['company'] ?: $w['kunde']));
    $adr   = trim((string) ($w['live'] ?? '')) ?: trim((string) ($w['preview_url'] ?? ''));
    $istVorschau = trim((string) ($w['live'] ?? '')) === '' && $adr !== '';
    $ab    = null;
    if (trim((string) ($w['abnahme'] ?? '')) !== '') {
      $d = json_decode((string) $w['abnahme'], true);
      if (is_array($d)) { $ab = $d; }
    }
    // Antwortet die Seite? Nur wo das Monitoring wirklich gelaufen ist —
    // sonst behauptete die Kachel etwas, das niemand geprueft hat.
    $lebt = null;
    if ($w['last_status'] !== null) { $lebt = (int) $w['last_status'] >= 200 && (int) $w['last_status'] < 400; }
  ?>
  <div class="block" style="margin:0;display:flex;flex-direction:column;gap:10px">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
      <div style="min-width:0">
        <div style="font-weight:600;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=
          Fmt::h($wer) ?></div>
        <div style="color:var(--leise);font-size:12px;font-variant-numeric:tabular-nums">
          <?= Fmt::h((string) ($w['kundennr'] ?? '')) ?><?php
          if (trim((string) ($w['kundennr'] ?? '')) !== '' && trim((string) $w['name']) !== ''): ?> · <?php endif; ?>
          <?= Fmt::h((string) $w['name']) ?></div>
      </div>
      <span class="marke2 <?= Fmt::h(Status::ton((string) $w['status'])) ?>" style="flex:0 0 auto"><?=
        Fmt::h(Status::label(Status::PROJEKT, (string) $w['status'])) ?></span>
    </div>

    <?php /* ---------- Das Fenster ---------- */ ?>
    <?php if ($adr !== ''): ?>
      <?php /* EIN RUECKFALL, DER IMMER DASTEHT
               Viele Seiten verbieten das Einbetten (X-Frame-Options oder
               frame-ancestors) — zu Recht, es ist die Abwehr gegen
               Clickjacking. Der Rahmen bleibt dann leer, und zwar ohne
               jede Meldung. Deshalb liegt der Rueckfall DARUNTER: Laedt das
               Fenster, deckt es ihn zu; laedt es nicht, steht da trotzdem
               etwas Lesbares statt eines schwarzen Kastens. */ ?>
      <div style="position:relative;height:170px;border-radius:10px;overflow:hidden;
                  border:1px solid var(--linie);background:var(--flaeche2,#0e1420)">
        <div style="position:absolute;inset:0;display:flex;flex-direction:column;
                    align-items:center;justify-content:center;gap:6px;padding:12px;text-align:center">
          <span style="font-size:12.5px;color:var(--dim);word-break:break-all"><?=
            Fmt::h(preg_replace('~^https?://~', '', $adr) ?? $adr) ?></span>
          <span style="font-size:11.5px;color:var(--leise)">Zum Ansehen klicken</span>
        </div>
        <?php /* GANZ ZUGESPERRT, MIT ABSICHT
                 "allow-scripts allow-same-origin" waere die treuere Vorschau —
                 aber liegt die Kundenseite auf derselben Domain wie die
                 Verwaltung, duerfte sie damit in diese Seite hineingreifen.
                 Fuer ein Bildchen ist das ein schlechter Tausch. Ohne
                 Skripte rendert eine gebaute Seite ohnehin: Sie ist HTML
                 und CSS. Was ohne JavaScript leer bleibt, zeigt den
                 Rueckfall darunter — auch das eine Auskunft. */ ?>
        <iframe src="<?= Fmt::h($adr) ?>" loading="lazy" tabindex="-1" aria-hidden="true"
                referrerpolicy="no-referrer" sandbox=""
                style="position:absolute;left:0;top:0;width:1280px;height:850px;border:0;
                       transform:scale(.2655);transform-origin:0 0;pointer-events:none"></iframe>
        <a href="<?= Fmt::h($adr) ?>" target="_blank" rel="noopener"
           title="<?= Fmt::h($adr) ?>"
           style="position:absolute;inset:0"></a>
        <?php if ($istVorschau): ?>
          <span class="marke2" style="position:absolute;left:8px;top:8px">Vorschau</span>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="leer" style="font-size:12.5px;margin:0">Keine Adresse eingetragen —
        weder live noch als Vorschau.</div>
    <?php endif; ?>

    <?php /* ---------- Der Zustand ---------- */ ?>
    <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:12px">
      <?php if ($lebt === true): ?>
        <span class="marke2 gut">antwortet</span>
      <?php elseif ($lebt === false): ?>
        <span class="marke2 schlecht">gestört (<?= (int) $w['last_status'] ?>)</span>
      <?php elseif (trim((string) ($w['live'] ?? '')) !== ''): ?>
        <span class="marke2">noch nicht geprüft</span>
      <?php endif; ?>

      <?php if ($ab): ?>
        <?php $zs = (int) ($ab['zaehler']['schlecht'] ?? 0); ?>
        <span class="marke2 <?= $zs > 0 ? 'schlecht' : 'gut' ?>"
              title="Abnahme <?= Fmt::h(Fmt::seit((string) ($w['abnahme_am'] ?? ''))) ?>">
          Abnahme: <?= $zs > 0 ? $zs . ' offen' : 'sauber' ?></span>
      <?php endif; ?>

      <?php if (!empty($w['ssl_expires_at'])): ?>
        <?php $tage = (int) floor((strtotime((string) $w['ssl_expires_at']) - strtotime('today')) / 86400); ?>
        <?php if ($tage <= 30): ?>
          <span class="marke2 <?= $tage <= 7 ? 'schlecht' : 'warnung' ?>">Zertifikat: <?= $tage ?> Tage</span>
        <?php endif; ?>
      <?php endif; ?>

      <?php if (!empty($w['deadline'])): ?>
        <?php $dt = (int) floor((strtotime((string) $w['deadline']) - strtotime('today')) / 86400); ?>
        <span class="marke2 <?= $dt < 0 ? 'schlecht' : ($dt <= 7 ? 'warnung' : '') ?>">
          <?= $dt < 0 ? abs($dt) . ' Tage über' : ($dt === 0 ? 'heute fällig' : 'noch ' . $dt . ' Tage') ?></span>
      <?php endif; ?>
    </div>

    <?php /* ---------- Die Wege von hier ---------- */ ?>
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:auto;padding-top:4px">
      <a class="knopf" href="<?= Fmt::h(url('projekte/' . (int) $w['id'])) ?>">Projekt</a>
      <?php /* Das Briefing liegt schon da — es dafuer erst ins Projekt zu
               gehen, waeren zwei Klicks fuer einen Text, der sich nicht
               mehr aendert. Das Feld steht versteckt daneben. */ ?>
      <?php if (trim((string) ($w['briefing'] ?? '')) !== ''): ?>
        <textarea id="brief<?= (int) $w['id'] ?>" readonly aria-hidden="true" tabindex="-1"
                  style="position:absolute;left:-9999px;width:1px;height:1px"><?=
          Fmt::h((string) $w['briefing']) ?></textarea>
        <button class="knopf" data-kopieren="brief<?= (int) $w['id'] ?>"
                data-oeffnen="<?= Fmt::h($claudeZiel) ?>">Briefing → Claude</button>
      <?php endif; ?>
      <?php if (!empty($w['chat_url'])): ?>
        <a class="knopf" href="<?= Fmt::h((string) $w['chat_url']) ?>" target="_blank" rel="noopener">Gespräch</a>
      <?php elseif (empty($w['briefing_am'])): ?>
        <a class="knopf stumm" href="<?= Fmt::h(url('projekte/' . (int) $w['id'] . '?tun=briefing_bauen')) ?>">Briefing fehlt</a>
      <?php endif; ?>
      <a class="knopf stumm" href="<?= Fmt::h(url('kunden/' . (int) $w['kunde_id'])) ?>">Kunde</a>
    </div>
  </div>
<?php endforeach; ?>
</div>

<p style="color:var(--leise);font-size:12.5px;line-height:1.65;margin:16px 2px 0">
  Die Fenster zeigen die Seite so, wie sie gerade steht — geladen wird erst, was
  in den Blick kommt. Steht statt der Seite nur ihre Adresse da, verbietet sie
  das Einbetten; das ist kein Fehler, sondern richtig so, und der Klick öffnet
  sie trotzdem.
  „Antwortet“ kommt aus dem <a href="<?= Fmt::h(url('monitoring')) ?>">Monitoring</a>,
  „Abnahme“ aus der letzten Prüfung im Projekt.
</p>
<?php endif; ?>
