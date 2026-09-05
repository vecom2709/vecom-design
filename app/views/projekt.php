<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('projekte')) ?>">Projekte</a></div>
<h1><?= Fmt::h($p['name']) ?></h1></div></div>
<div class="zwei"><div>
  <div class="block"><h2>Ablauf</h2>
    <div class="balken" style="height:8px;margin-bottom:14px"><i style="width:<?= (int) $p['progress'] ?>%"></i></div>
    <form method="post" action="<?= Fmt::h(url('')) ?>" class="leiste">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="projekt_status">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <select name="status"><?php foreach (Status::PROJEKT as $w => $t): ?>
        <option value="<?= $w ?>" <?= $p['status'] === $w ? 'selected' : '' ?>><?= Fmt::h($t) ?></option><?php endforeach; ?></select>
      <button class="knopf haupt">Status setzen</button></form>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">Der Projektstatus zieht die Bestellung sinngemäß mit.
    Der technische Website-Status bleibt davon unberührt — er wird nur vom Monitoring gesetzt.</p></div>

<?php /* ======================================================================
     MEHRBEDARF

     Der Preis steht seit der Zusage fest, und das ist richtig. Nur konnte
     der Fragebogen bisher mehr Umfang beschreiben, als das Angebot deckt,
     ohne dass es irgendwo auffiel: Gebaut wurde, was im Fragebogen stand,
     bezahlt war, was im Angebot stand.

     Dieser Block erscheint nur, wenn beides auseinanderlaeuft. Stimmt es
     ueberein, steht hier nichts -- und genau deshalb darf man ihm glauben,
     wenn er da ist.
     ================================================================== */ ?>
<?php if (!empty($mehrbedarf)): ?>
  <div class="block" data-tun="mehrbedarf" style="border-color:var(--cyan)">
    <h2>Mehrbedarf klären</h2>
    <p style="color:var(--dim);font-size:13.5px;margin:-4px 0 14px">
      Der Fragebogen sagt etwas anderes als Angebot
      <a href="<?= Fmt::h(url('angebote/' . (int) $mehrbedarf['angebot_id'])) ?>"><?= Fmt::h($mehrbedarf['nummer']) ?></a>.
      <?= $mehrbedarf['abgeschlossen'] ? 'Der Kunde hat abgeschickt.' : 'Der Kunde füllt noch aus.' ?></p>

    <?php if ($mehrbedarf['mehr']): ?>
      <h3 style="font-size:13px;color:var(--leise);margin:0 0 6px;text-transform:uppercase;letter-spacing:.06em">Zusätzlich gewünscht</h3>
      <table style="margin-bottom:14px"><tbody>
        <?php foreach ($mehrbedarf['mehr'] as $z): ?>
          <tr>
            <td><?= Fmt::h((string) $z['name']) ?>
              <?php if (isset($z['war'])): ?>
                <span style="color:var(--leise)">— <?= (int) $z['war'] ?> beauftragt, <?= (int) $z['wird'] ?> gewünscht</span>
              <?php endif; ?></td>
            <td style="width:22%" class="num"><?= (int) $z['menge'] > 1 ? (int) $z['menge'] . ' × ' : '' ?><?= Fmt::geld((int) $z['einzel_cents']) ?></td>
            <td style="width:22%" class="num"><b><?= Fmt::geld((int) $z['summe_cents']) ?><?= (int) $z['monatlich'] ? '/Mon.' : '' ?></b></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>

    <?php if ($mehrbedarf['auf_anfrage']): ?>
      <p style="font-size:13.5px;color:var(--dim);margin:0 0 14px">
        Ohne Preis, weil nur auf Anfrage: <b><?= Fmt::h(implode(', ', $mehrbedarf['auf_anfrage'])) ?></b>.
        Den Betrag nennst du.</p>
    <?php endif; ?>

    <?php if ($mehrbedarf['weniger']): ?>
      <p style="font-size:13.5px;color:var(--dim);margin:0 0 14px">
        Abgewählt, obwohl beauftragt: <b><?= Fmt::h(implode(', ', $mehrbedarf['weniger'])) ?></b>.
        Ohne Betrag — wer etwas abwählt, bekommt kein Geld zurück, weil eine Zahl in einer Liste steht.
        Das ist ein Gespräch.</p>
    <?php endif; ?>

    <?php if ((int) $mehrbedarf['summe_cents'] > 0 || (int) $mehrbedarf['monatlich_cents'] > 0): ?>
      <table style="margin-bottom:14px"><tbody>
        <tr><td style="width:38%"><b>Differenz</b></td>
            <td><b><?= Fmt::geld((int) $mehrbedarf['summe_cents']) ?></b><?php
              if ((int) $mehrbedarf['monatlich_cents'] > 0): ?> + <?= Fmt::geld((int) $mehrbedarf['monatlich_cents']) ?>/Mon.<?php endif; ?>
            </td></tr>
      </tbody></table>
    <?php endif; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ((int) $mehrbedarf['summe_cents'] > 0): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="mehrbedarf_nachtrag">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="signatur" value="<?= Fmt::h((string) $mehrbedarf['signatur']) ?>">
          <button class="knopf haupt">Nachtrag über <?= Fmt::geld((int) $mehrbedarf['summe_cents']) ?> anlegen</button></form>
      <?php endif; ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="mehrbedarf_erledigt">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <input type="hidden" name="signatur" value="<?= Fmt::h((string) $mehrbedarf['signatur']) ?>">
        <button class="knopf">Ist besprochen</button></form>
    </div>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">
      Der Nachtrag wird eine zweite Rate auf derselben Bestellung — Zahlungslink, Mail und Beleg
      laufen danach über dieselben Knöpfe wie Anzahlung und Restzahlung.
      „Ist besprochen“ hakt nur ab; kreuzt der Kunde später etwas Weiteres an, meldet sich die Führung von selbst wieder.</p>
  </div>
<?php endif; ?>

<?php /* ======================================================================
     WERKSTATT — der Auftrag an den Baumeister

     Alles, was hier zusammengetragen wird, stand vorher schon da: 35
     Fragebogenfelder, der bezahlte Umfang, Sprachen, Domain, Deadline. Nur
     nahm es den Umweg ueber Kopf und Finger in ein Chatfenster und wurde
     dabei jedes Mal kuerzer.

     Zwei Schritte, mit Absicht. Erst erzeugen — dabei wird das Briefing am
     Projekt festgehalten, und genau das ist der Wert: In Monat 14 steht hier
     noch, woraus die Seite gebaut ist. Dann kopieren und Claude oeffnen, mit
     einem Klick, weil die Zwischenablage eine Nutzerhandlung braucht.
     ================================================================== */ ?>
  <div class="block" data-tun="briefing"><h2>Werkstatt
    <?php if (!empty($p['briefing_am'])): ?>
      <span class="mehr">Briefing von <?= Fmt::h(Fmt::seit((string) $p['briefing_am'])) ?></span>
    <?php endif; ?></h2>

    <?php $ziel = sicher(static fn() => Standard::claudeZiel(), 'https://claude.ai/new'); ?>
    <?php $hatBriefing = trim((string) ($p['briefing'] ?? '')) !== ''; ?>

    <div class="leiste" style="gap:8px;flex-wrap:wrap">
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="briefing_bauen">
        <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>?tun=briefing">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <button class="knopf<?= $hatBriefing ? '' : ' haupt' ?>"><?=
          $hatBriefing ? 'Briefing neu erzeugen' : 'Briefing erzeugen' ?></button></form>

      <?php if ($hatBriefing): ?>
        <button class="knopf haupt" data-kopieren="briefingtext"
                data-oeffnen="<?= Fmt::h($ziel) ?>">Kopieren und Claude öffnen</button>
        <button class="knopf stumm" data-kopieren="briefingtext">Nur kopieren</button>
      <?php endif; ?>

      <?php if (!empty($p['chat_url'])): ?>
        <a class="knopf" href="<?= Fmt::h((string) $p['chat_url']) ?>" target="_blank" rel="noopener">Zum Gespräch</a>
      <?php endif; ?>
    </div>

    <?php if ($hatBriefing): ?>
      <textarea id="briefingtext" readonly rows="12" spellcheck="false"
        style="width:100%;margin-top:12px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
               font-size:12px;line-height:1.55;white-space:pre;overflow-wrap:normal;overflow-x:auto"><?=
        Fmt::h((string) $p['briefing']) ?></textarea>
      <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:8px 0 0">
        Die erste Zeile ist der Titel — danach benennt Claude das Gespräch, und
        du findest es später wieder.
        <?php if (trim((string) sicher(static fn() => Standard::claudeProjekt(), '')) === ''): ?>
          <br>Noch kein Claude-Projekt hinterlegt: Der Knopf öffnet einen freien Chat.
          Trag unter <a href="<?= Fmt::h(url('standard')) ?>">Vecom-Standard</a> die Adresse
          deines Projekts „Vecom — Kundenseiten“ ein, dann landet die Kundenarbeit dort
          und nicht zwischen den Büchern.
        <?php endif; ?>
      </p>

      <form method="post" action="<?= Fmt::h(url('')) ?>" class="leiste" style="margin-top:12px;gap:8px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="chat_merken">
        <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <input name="url" placeholder="Adresse des Gesprächs bei claude.ai" style="flex:1;min-width:240px"
               value="<?= Fmt::h((string) ($p['chat_url'] ?? '')) ?>">
        <button class="knopf">Gespräch merken</button>
      </form>
      <p style="color:var(--leise);font-size:12.5px;margin:6px 0 0">
        Einmal eingefügt, führt der Knopf oben direkt dorthin zurück — statt
        durch eine Liste von vierzig Gesprächen zu suchen.</p>
    <?php else: ?>
      <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:10px 0 0">
        Baut aus Fragebogen, Angebot und Eckdaten einen fertigen Auftrag —
        <?php if (!$fragebogen || trim((string) ($fragebogen['data'] ?? '')) === ''): ?>
          allerdings ist der Fragebogen noch leer. Das Briefing sagt das dann
          auch dazu, damit niemand auf Vermutungen baut.
        <?php else: ?>
          samt allem, was der Kunde geschrieben hat, und den Hausregeln.
        <?php endif; ?></p>
    <?php endif; ?>
  </div>

<?php /* ======================================================================
     ABNAHME — das Mechanische, bevor es der Kunde findet

     Was unter Termindruck durchrutscht, ist selten das Schwere: die
     Beschreibung, die auf drei Seiten dieselbe ist, das Impressum, das nur
     auf der Startseite steht, die englische Fassung, die nie verlinkt wurde.
     Geprueft wird nur, was sich eindeutig beantworten laesst — alles andere
     bleibt Arbeit fuer Augen.
     ================================================================== */ ?>
  <?php $ab = sicher(static fn() => Abnahme::gespeichert($p), null); ?>
  <div class="block" data-tun="abnahme"><h2>Abnahme
    <?php if ($ab): ?>
      <span class="mehr"><?= Fmt::h(Fmt::seit((string) ($p['abnahme_am'] ?? ''))) ?> geprüft</span>
    <?php endif; ?></h2>

    <?php $zuPruefen = trim((string) ($website['url'] ?? '')) ?: trim((string) ($p['preview_url'] ?? '')); ?>
    <?php if ($zuPruefen === ''): ?>
      <div class="leer">Weder eine Live-Adresse noch eine Vorschau eingetragen —
        es gibt nichts zu prüfen. Trag sie rechts unter „Website“ ein.</div>
    <?php else: ?>
      <div class="leiste" style="gap:8px;flex-wrap:wrap">
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="abnahme_pruefen">
          <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>?tun=abnahme">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="knopf<?= $ab ? '' : ' haupt' ?>"><?= $ab ? 'Neu prüfen' : 'Jetzt prüfen' ?></button></form>
        <span style="color:var(--leise);font-size:12.5px;align-self:center">
          <?= Fmt::h($zuPruefen) ?><?= trim((string) ($website['url'] ?? '')) === '' ? ' (Vorschau)' : '' ?></span>
      </div>

      <?php if ($ab): ?>
        <?php $z = (array) ($ab['zaehler'] ?? []); ?>
        <p style="margin:12px 0 8px;font-size:13.5px">
          <b style="color:var(--gruen,#2fbf71)"><?= (int) ($z['gut'] ?? 0) ?> in Ordnung</b>
          <?php if ((int) ($z['schlecht'] ?? 0) > 0): ?>
            · <b style="color:var(--rot,#e5484d)"><?= (int) $z['schlecht'] ?> zu beheben</b>
          <?php endif; ?>
          <?php if ((int) ($z['hinweis'] ?? 0) > 0): ?>
            · <span style="color:var(--leise)"><?= (int) $z['hinweis'] ?> Hinweise</span>
          <?php endif; ?>
        </p>
        <div class="tabellenrahmen"><table><tbody>
          <?php
            /* Erst das Kaputte, dann die Hinweise, dann das Gute. Wer
               hier hinsieht, will wissen, was noch zu tun ist — nicht,
               was schon stimmt. */
            $rang = ['schlecht' => 0, 'hinweis' => 1, 'gut' => 2];
            $punkte = (array) ($ab['punkte'] ?? []);
            usort($punkte, static fn(array $a, array $b): int
                => ($rang[$a['stand']] ?? 3) <=> ($rang[$b['stand']] ?? 3));
          ?>
          <?php foreach ($punkte as $pt): ?>
            <tr>
              <td style="width:26px;text-align:center;font-weight:700;color:<?=
                $pt['stand'] === 'gut' ? 'var(--gruen,#2fbf71)'
                  : ($pt['stand'] === 'schlecht' ? 'var(--rot,#e5484d)' : 'var(--leise)') ?>"><?=
                $pt['stand'] === 'gut' ? '✓' : ($pt['stand'] === 'schlecht' ? '✗' : '·') ?></td>
              <td style="width:30%"><?= Fmt::h((string) $pt['was']) ?></td>
              <td style="color:var(--dim);font-size:13px"><?= Fmt::h((string) $pt['befund']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table></div>
        <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:10px 0 0">
          Geprüft wird die Seite, die unter der Adresse steht — nicht jede
          Unterseite. Für die Pflichtangaben im Fuß reicht das, weil der Fuß
          überall derselbe ist; was nur auf einer Unterseite schiefliegt,
          findet weiterhin nur das Auge.</p>
      <?php else: ?>
        <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:10px 0 0">
          Holt die Seite ab und sieht nach: Titel, Beschreibung, Handy-Ansicht,
          Impressum und Datenschutz im Fuß, Sprachfassungen, Bildmaße und
          Alt-Texte, robots.txt und sitemap.xml, Verschlüsselung ohne gemischte
          Inhalte.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="block"><h2>Fragebogen</h2>
    <?php if (!$fragebogen): ?>
      <div class="leer">Zu diesem Projekt gibt es keinen Fragebogen.</div>
    <?php else: ?>
      <?php
        $fbDaten = $fragebogen['data'] ? (json_decode((string) $fragebogen['data'], true) ?: []) : [];
        $fbToken = (string) ($fragebogen['token'] ?? '');
        $fbFertig = $fragebogen['status'] === 'abgeschlossen';
      ?>
      <table style="margin-bottom:14px"><tbody>
        <tr><td style="width:38%">Stand</td><td><span class="marke2 <?= $fbFertig ? 'gut' : '' ?>">
          <?= $fbFertig ? 'Abgeschlossen' : 'Offen' ?></span></td></tr>
        <tr><td>Eingeladen</td><td><?= Fmt::h($fragebogen['eingeladen_am'] ? Fmt::datum($fragebogen['eingeladen_am']) : 'noch nicht') ?></td></tr>
        <tr><td>Erinnert</td><td><?= Fmt::h($fragebogen['erinnert_am'] ? Fmt::datum($fragebogen['erinnert_am']) : '—') ?></td></tr>
        <?php if ($fbFertig): ?>
          <tr><td>Abgeschickt</td><td><?= Fmt::h(Fmt::datum($fragebogen['submitted_at'])) ?></td></tr>
        <?php endif; ?>
      </tbody></table>

      <?php if ($fbToken !== ''): ?>
        <div class="feld"><label>Zugangslink für den Kunden</label>
          <input readonly onclick="this.select()" value="<?= Fmt::h(Onboarding::link($fbToken)) ?>"></div>
      <?php endif; ?>

      <?php if (!$fbFertig): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <form method="post" action="<?= Fmt::h(url('')) ?>">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="fragebogen_einladen">
            <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="knopf haupt"><?= $fragebogen['eingeladen_am'] ? 'Noch einmal verschicken' : 'Fragebogen verschicken' ?></button></form>
          <?php if ($fbToken === ''): ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="fragebogen_link">
              <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="knopf">Nur Link erzeugen</button></form>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($fbDaten): ?>
        <?php foreach (Texte::FRAGEBOGEN as $abschnitt => $inhalt): ?>
          <?php $hat = array_filter($inhalt['felder'], static fn($_, $n) => trim((string) ($fbDaten[$n] ?? '')) !== '', ARRAY_FILTER_USE_BOTH); ?>
          <?php if ($hat): ?>
            <h3 style="font-size:13px;color:var(--leise);margin:18px 0 6px;text-transform:uppercase;letter-spacing:.06em"><?= Fmt::h(Texte::h($inhalt, 'de')) ?></h3>
            <table><tbody>
            <?php foreach ($hat as $name => $feld): ?>
              <tr><td style="width:38%"><?= Fmt::h(Texte::h($feld, 'de')) ?></td>
                  <td style="white-space:pre-wrap"><?= Fmt::h(($feld['art'] ?? '') === 'wahl'
                        ? Umfang::worte((string) $fbDaten[$name], 'de')
                        : (string) $fbDaten[$name]) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php elseif ($fragebogen['eingeladen_am']): ?>
        <div class="leer">Der Kunde hat noch nichts eingetragen.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="block"><h2>Aufgaben</h2>
    <?php if (!$aufgaben): ?>
      <div class="leer">Noch keine Aufgaben.</div>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="text-align:center;margin-top:-10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="aufgaben_vorlage">
        <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <button class="knopf">Übliche zwölf Schritte einfügen</button></form>
    <?php else: ?>
      <?php $offen = 0; foreach ($aufgaben as $a) { if (!(int) $a['done']) { $offen++; } } ?>
      <p style="color:var(--leise);font-size:12.5px;margin-bottom:10px">
        <?= count($aufgaben) - $offen ?> von <?= count($aufgaben) ?> erledigt</p>
      <table><tbody>
      <?php foreach ($aufgaben as $a): ?>
        <?php $fertig = (int) $a['done'] === 1; ?>
        <tr>
          <td style="width:34px">
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="aufgabe_umschalten">
              <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="knopf" style="padding:2px 9px;min-width:0"
                title="<?= $fertig ? 'Wieder offen' : 'Erledigt' ?>"><?= $fertig ? '✓' : '&nbsp;&nbsp;' ?></button></form>
          </td>
          <td style="<?= $fertig ? 'color:var(--leise);text-decoration:line-through' : '' ?>"><?= Fmt::h($a['title']) ?></td>
          <td style="text-align:right;white-space:nowrap;color:var(--leise);font-size:12.5px">
            <?= Fmt::h($a['due_date'] ? Fmt::datum($a['due_date']) : '') ?></td>
          <td style="width:34px;text-align:right">
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="aufgabe_weg">
              <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="knopf" style="padding:2px 8px;min-width:0;color:var(--leise)" title="Löschen">×</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>

    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="aufgabe_anlegen">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <input name="titel" placeholder="Neue Aufgabe" style="flex:1;min-width:180px" required>
      <input type="date" name="due_date" style="width:auto;flex:0 0 150px" title="Bis wann">
      <button class="knopf">Hinzufügen</button>
    </form>
  </div>

  <div class="block"><h2>Nachrichten</h2>
    <?php if (!$nachrichten): ?><div class="leer">Noch keine Nachrichten.</div><?php else: ?>
      <?php $ungelesen = 0; foreach ($nachrichten as $n) { if ($n['sender'] === 'kunde' && $n['read_at'] === null) { $ungelesen++; } } ?>
      <?php foreach ($nachrichten as $n): ?>
        <?php $vomKunden = $n['sender'] === 'kunde'; ?>
        <div style="padding:11px 13px;border-radius:11px;margin-bottom:9px;border:1px solid var(--linie);
                    background:<?= $vomKunden ? 'var(--flaeche2)' : 'transparent' ?>">
          <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:5px">
            <b style="font-size:12.5px;color:<?= $vomKunden ? 'var(--cyan)' : 'var(--leise)' ?>">
              <?= $vomKunden ? Fmt::h((string) $p['kunde']) : 'Du' ?>
              <?php if ($vomKunden && $n['read_at'] === null): ?><span class="marke2 warnung" style="margin-left:6px">neu</span><?php endif; ?>
            </b>
            <small style="color:var(--leise)"><?= Fmt::h(Fmt::seit($n['created_at'])) ?></small>
          </div>
          <div style="white-space:pre-wrap;font-size:14px;line-height:1.55"><?= Fmt::h($n['body']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if ($ungelesen > 0): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="nachrichten_gelesen">
          <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="knopf">Als gelesen markieren</button></form>
      <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:14px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="nachricht_senden">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Antworten</label>
        <textarea name="text" rows="4" maxlength="5000" style="min-height:90px"
                  placeholder="Der Kunde bekommt den Text auch per E-Mail."></textarea></div>
      <button class="knopf haupt">Absenden</button></form>
    <?php if ($kundenlink): ?>
      <div class="feld" style="margin-top:14px"><label>Seine Projektseite</label>
        <input readonly onclick="this.select()" value="<?= Fmt::h($kundenlink) ?>"></div>
    <?php endif; ?>
  </div>

  <div class="block"><h2>Dateien</h2>
    <?php if (!$dateien): ?><div class="leer">Noch keine Dateien.</div><?php else: ?>
      <table><tbody>
      <?php foreach ($dateien as $d): ?>
        <tr>
          <td><a href="<?= Fmt::h(url('dateien/' . (int) $d['id'])) ?>"><?= Fmt::h($d['orig_name']) ?></a>
            <br><small style="color:var(--leise)"><?= Fmt::h(Fmt::bytes((int) $d['size_bytes'])) ?> ·
              <?= $d['uploaded_by'] === 'kunde' ? 'vom Kunden' : 'von dir' ?> ·
              <?= Fmt::h(Fmt::seit($d['created_at'])) ?></small></td>
          <td style="text-align:right;width:90px">
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="datei_weg">
              <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <button class="knopf">Löschen</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" enctype="multipart/form-data" style="margin-top:14px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="datei_hoch">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Datei hinterlegen</label><input type="file" name="datei" required></div>
      <button class="knopf">Hochladen</button></form>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">
      Der Kunde sieht diese Dateien auf seiner Projektseite und kann selbst welche schicken.</p>
  </div>

  <div class="block"><h2>Verlauf</h2>
    <?php if (!$aktivitaeten): ?><div class="leer">Noch nichts.</div><?php else: ?><ul class="verlauf">
    <?php foreach ($aktivitaeten as $a): ?><li><span class="punkt"></span><span><?= Fmt::h($a['title']) ?><br><small><?= Fmt::h($a['actor']) ?></small></span>
      <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
</div><div>
  <div class="block"><h2>Übersicht</h2><table><tbody>
    <tr><td>Kunde</td><td><a href="<?= Fmt::h(url('kunden/' . $p['customer_id'])) ?>"><?= Fmt::h($p['kunde']) ?></a></td></tr>
    <tr><td>Bestellung</td><td><?= $p['order_id'] ? '<a href="' . Fmt::h(url('bestellungen/' . $p['order_id'])) . '">' . Fmt::h((string) $p['order_no']) . '</a>' : '—' ?></td></tr>
    <tr><td>Projektstatus</td><td><span class="marke2 <?= Status::ton($p['status']) ?>"><?= Fmt::h(Status::label(Status::PROJEKT, $p['status'])) ?></span></td></tr>
    <tr><td>Website-Status</td><td><span class="marke2 <?= Status::ton((string) ($website['status'] ?? '')) ?>"><?= Fmt::h($website ? Status::label(Status::WEBSITE, $website['status']) : 'keine hinterlegt') ?></span></td></tr>
    <tr><td>Fragebogen</td><td><?= Fmt::h($fragebogen ? ucfirst((string) $fragebogen['status']) : '—') ?></td></tr>
    <tr><td>Start</td><td><?= Fmt::h(Fmt::datum($p['start_date'])) ?></td></tr>
  </tbody></table></div>
  <div class="block"><h2>Eckdaten</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="projekt_felder">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Deadline</label><input type="date" name="deadline" value="<?= Fmt::h($p['deadline'] ?? '') ?>"></div>
      <div class="feld"><label>Priorität</label><select name="priority">
        <?php foreach (['niedrig','normal','hoch'] as $pr): ?><option <?= $p['priority'] === $pr ? 'selected' : '' ?>><?= $pr ?></option><?php endforeach; ?></select></div>
      <button class="knopf">Speichern</button></form></div>

  <?php /* ---------- Vorschau und Abnahme ------------------------------
       WARUM DAS EIN EIGENER KASTEN IST UND KEIN FELD IN DEN ECKDATEN

       Es war eins: ein Textfeld "Vorschau-Link" zwischen Deadline und
       Prioritaet. Speichern hiess dort gar nichts -- der Kunde sah den
       Entwurf davon nicht, und freischalten liess er sich hier ueberhaupt
       nicht. Wer nur diese Seite offen hatte, trug die Adresse ein und
       wartete auf etwas, das nie passierte.

       Es sind drei Entscheidungen, und jede gehoert sichtbar hierher:
       eintragen, ansehen lassen, abnehmen lassen. */ ?>
  <?php
    $vsUrl   = trim((string) ($p['preview_url'] ?? ''));
    $vsFrei  = $p['vorschau_frei_am'] ?? null;
    $abFrei  = array_key_exists('abnahme_frei_am', $p) ? ($p['abnahme_frei_am'] ?? null) : null;
    $abSpalte = array_key_exists('abnahme_frei_am', $p);
    $zurueckHier = 'projekte/' . (int) $p['id'];
  ?>
  <div class="block" data-tun="vorschau"><h2>Vorschau und Abnahme
    <span class="mehr">
      <?php if ($abFrei): ?><span class="marke2 gut">Abnahme offen</span>
      <?php elseif ($vsFrei): ?><span class="marke2 gut">er darf ansehen</span>
      <?php elseif ($vsUrl !== ''): ?><span class="marke2 warnung">nur für dich</span>
      <?php else: ?><span class="marke2">keine Adresse</span><?php endif; ?>
    </span></h2>
    <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
      Drei Schritte, jeder eine eigene Entscheidung: <b>Adresse eintragen</b> (sieht nur du),
      <b>zum Ansehen freischalten</b> (er schaut und darf Änderungen wünschen, abnehmen kann er nicht),
      <b>Abnahme freischalten</b> (erst dann steht bei ihm „Passt so“ — und erst dann bekommt er die
      Nachricht, dass die Seite fertig ist).</p>

    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="vorschau_speichern">
      <input type="hidden" name="zurueck" value="<?= Fmt::h($zurueckHier) ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Adresse des Entwurfs</label>
        <input name="preview_url" placeholder="https://vorschau.vecom-design.it/…"
               value="<?= Fmt::h($vsUrl) ?>"></div>
      <button class="knopf">Adresse speichern</button>
      <?php if ($vsUrl !== ''): ?>
        <a class="knopf" href="<?= Fmt::h($vsUrl) ?>" target="_blank" rel="noopener"
           style="margin-left:8px">Selbst ansehen</a>
      <?php endif; ?>
    </form>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px;
                border-top:1px solid var(--linie);padding-top:12px">
      <?php if (!$vsFrei): ?>
        <?php if ($vsUrl === ''): ?>
          <button class="knopf" disabled title="Erst eine Adresse eintragen">Zum Ansehen freischalten</button>
          <span style="color:var(--leise);font-size:12.5px">Erst die Adresse — sonst bekäme er eine
            E-Mail und fände nichts.</span>
        <?php else: ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="vorschau_frei">
            <input type="hidden" name="zurueck" value="<?= Fmt::h($zurueckHier) ?>">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="knopf haupt">Zum Ansehen freischalten</button></form>
          <span style="color:var(--leise);font-size:12.5px">Setzt den Stand auf „Vorschau“ und
            schickt ihm die E-Mail. Abnehmen kann er damit noch nicht.</span>
        <?php endif; ?>
      <?php else: ?>
        <span style="color:var(--leise);font-size:12.5px">Zum Ansehen frei seit
          <?= Fmt::h(Fmt::zeit((string) $vsFrei)) ?></span>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
              data-frage="Der Kunde sieht den Entwurf danach nicht mehr. Fortfahren?" data-ja="Ja, sperren">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="vorschau_sperren">
          <input type="hidden" name="zurueck" value="<?= Fmt::h($zurueckHier) ?>">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="knopf">Wieder sperren</button></form>
      <?php endif; ?>
    </div>

    <?php if ($abSpalte): ?>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px;
                border-top:1px solid var(--linie);padding-top:12px">
      <?php if (!$abFrei): ?>
        <?php if (!$vsFrei): ?>
          <button class="knopf" disabled title="Erst zum Ansehen freischalten">Abnahme freischalten</button>
          <span style="color:var(--leise);font-size:12.5px">Erst ansehen lassen, dann abnehmen lassen.</span>
        <?php else: ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
                data-frage="Danach kann der Kunde die Seite abnehmen — daran hängt die Restzahlung. Fertig?"
                data-ja="Ja, Abnahme freischalten">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="abnahme_frei">
            <input type="hidden" name="zurueck" value="<?= Fmt::h($zurueckHier) ?>">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="knopf haupt">Abnahme freischalten</button></form>
          <span style="color:var(--leise);font-size:12.5px">Schickt ihm „die Seite ist fertig“ und
            zeigt ihm „Passt so“.</span>
        <?php endif; ?>
      <?php else: ?>
        <span style="color:var(--leise);font-size:12.5px">Abnahme frei seit
          <?= Fmt::h(Fmt::zeit((string) $abFrei)) ?></span>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
              data-frage="Der Kunde kann danach nicht mehr abnehmen. Fortfahren?" data-ja="Ja, zumachen">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="abnahme_sperren">
          <input type="hidden" name="zurueck" value="<?= Fmt::h($zurueckHier) ?>">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="knopf">Abnahme wieder zu</button></form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="block"><h2>Website</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="website_speichern">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="project_id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Domain</label>
        <input name="domain" placeholder="beispiel.it" value="<?= Fmt::h((string) ($website['domain'] ?? '')) ?>"></div>
      <div class="feld"><label>Adresse</label>
        <input name="url" placeholder="https://beispiel.it" value="<?= Fmt::h((string) ($website['url'] ?? '')) ?>"></div>
      <div class="feld" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="monitoring" id="mon" style="width:auto"
               <?= (int) ($website['monitoring'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label for="mon" style="margin:0">Regelmäßig überwachen</label></div>
      <button class="knopf">Speichern</button></form>

    <?php if ($website): ?>
      <table style="margin-top:14px"><tbody>
        <tr><td>Zustand</td><td><span class="marke2 <?= Status::ton((string) $website['status']) ?>">
          <?= Fmt::h(Status::label(Status::WEBSITE, (string) $website['status'])) ?></span></td></tr>
        <tr><td>Letzte Antwort</td><td><?= $website['last_status']
            ? (int) $website['last_status'] . ($website['last_ms'] !== null ? ' · ' . (int) $website['last_ms'] . ' ms' : '')
            : 'noch nicht geprüft' ?></td></tr>
        <tr><td>Zuletzt erreichbar</td><td><?= Fmt::h($website['last_ok_at'] ? Fmt::seit($website['last_ok_at']) : '—') ?></td></tr>
        <tr><td>Zertifikat bis</td><td><?= Fmt::h($website['ssl_expires_at'] ? Fmt::datum((string) $website['ssl_expires_at']) : '—') ?></td></tr>
      </tbody></table>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="website_pruefen">
        <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
        <input type="hidden" name="id" value="<?= (int) $website['id'] ?>">
        <button class="knopf">Jetzt prüfen</button></form>

      <?php if ($pruefungen): ?>
        <h3 style="font-size:12.5px;color:var(--leise);margin:16px 0 6px;text-transform:uppercase;letter-spacing:.06em">Letzte Prüfungen</h3>
        <table><tbody>
        <?php foreach ($pruefungen as $k): ?>
          <tr>
            <td style="width:22px"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= (int) $k['ok'] ? 'var(--gruen)' : 'var(--rot)' ?>"></span></td>
            <td style="color:var(--dim);font-size:13px"><?= Fmt::h(Fmt::seit($k['checked_at'])) ?>
              <?php if ($k['error']): ?><br><small style="color:var(--rot)"><?= Fmt::h($k['error']) ?></small><?php endif; ?></td>
            <td style="text-align:right;color:var(--leise);font-size:12.5px;white-space:nowrap">
              <?= $k['http_status'] ? (int) $k['http_status'] : '—' ?><?= $k['response_ms'] !== null ? ' · ' . (int) $k['response_ms'] . ' ms' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="block"><h2>E-Mails</h2>
    <?php if (!$mails): ?><div class="leer">Noch keine verschickt.</div><?php else: ?>
      <table><tbody>
      <?php foreach ($mails as $m): ?>
        <tr><td><?= Fmt::h($m['betreff']) ?><br><small style="color:var(--leise)"><?= Fmt::h(Fmt::seit($m['created_at'])) ?></small></td>
            <td style="text-align:right"><span class="marke2 <?= $m['status'] === 'gesendet' ? 'gut' : 'schlecht' ?>"><?= Fmt::h($m['status']) ?></span>
            <?php if ($m['fehler']): ?><br><small style="color:var(--rot)"><?= Fmt::h(mb_substr((string) $m['fehler'], 0, 120)) ?></small><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?></div>
</div></div>
