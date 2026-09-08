<div class="kopf"><div><h1>Vecom-Standard</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Wie eine Vecom-Seite gebaut ist. Hängt an jedem Briefing — damit Seite 12
    besser wird als Seite 1 und nicht nur anders.</p></div>
  <div class="rechts">
    <button class="knopf" data-kopieren="standardtext">Hausregeln kopieren</button>
  </div>
</div>

<?php /* ======================================================================
     WO DIE KUNDENARBEIT LIEGT

     Ein Claude-Projekt ist ein eigener Raum mit eigener Wissensablage. Traegt
     Uwe seine Adresse hier ein, oeffnet jeder Briefing-Knopf genau diesen
     Raum: Kundenseiten liegen beisammen und nicht zwischen den Buechern.

     Und wer die Hausregeln einmal in die Wissensablage dieses Projekts legt,
     braucht sie nicht mehr an jedes Briefing zu haengen — dafuer der
     Schalter weiter unten.
     ================================================================== */ ?>
<div class="block"><h2>Wo die Kundenarbeit liegt</h2>
  <form method="post" action="<?= Fmt::h(url('')) ?>" class="leiste" style="gap:8px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="claude_projekt">
    <input name="url" placeholder="https://claude.ai/project/…" style="flex:1;min-width:260px"
           value="<?= Fmt::h((string) $projekt) ?>">
    <button class="knopf haupt">Eintragen</button>
  </form>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.65;margin:10px 0 0">
    <?php if (trim((string) $projekt) === ''): ?>
      Noch nichts eingetragen — die Briefing-Knöpfe öffnen einen freien Chat.
      Leg bei claude.ai einmal ein Projekt an, nenn es „Vecom — Kundenseiten“,
      und trag seine Adresse hier ein. Ab dann landet jedes Kundengespräch dort,
      getrennt von allem Privaten.
    <?php else: ?>
      Alle Briefing-Knöpfe öffnen dieses Projekt.
      <a href="<?= Fmt::h((string) $projekt) ?>" target="_blank" rel="noopener">Hinsehen</a>
    <?php endif; ?>
    <br>Erlaubt sind nur Adressen bei claude.ai — ein Knopf, der von hier aus
    mit einem Klick aufgeht, soll nicht irgendwo hinführen können.
  </p>
</div>

<?php /* ======================================================================
     MIT CLAUDE CODE BAUEN

     Der Knopf darueber oeffnet ein Chatfenster: kopieren, einfuegen, bauen,
     und am Ende die Vorschau-Adresse von Hand zurueck hierher tippen. Das
     traegt, solange ein Mensch dazwischensitzt.

     Claude Code sitzt nicht in einem Chatfenster. Es hat einen Ordner und
     eine Kommandozeile. Mit einem Schluessel holt es sich den Auftrag selbst
     und meldet Vorschau-Adresse und Stand selbst zurueck -- dieselbe Arbeit,
     nur ohne die zwei Handgriffe, bei denen sonst etwas liegen bleibt.

     Der Schluessel steht hier im Klartext, weil er genau einmal irgendwohin
     kopiert wird. Wer ihn verliert, erzeugt einen neuen; der alte ist damit
     im selben Moment wertlos.
     ================================================================== */ ?>
<div class="block"><h2>Mit Claude Code bauen
    <span class="mehr"><?= trim((string) $wSchluessel) !== '' ? 'offen' : 'zu' ?></span></h2>

  <?php if (trim((string) $wSchluessel) === ''): ?>
    <p style="color:var(--leise);font-size:12.5px;line-height:1.65;margin:0 0 12px">
      Ohne Schlüssel antwortet <code><?= Fmt::h((string) $wAdresse) ?></code> niemandem —
      auch nicht dir. Erzeug einen, wenn du Kundenseiten mit Claude Code bauen willst:
      Es holt sich Briefing, Fragebogen und Hausregeln dann selbst und trägt
      Vorschau-Adresse und Stand von allein hier ein.
    </p>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="werkstatt_schluessel_neu">
      <button class="knopf haupt">Schlüssel erzeugen</button>
    </form>
  <?php else: ?>
    <div class="feld"><label>Adresse</label>
      <input readonly id="wadresse" value="<?= Fmt::h((string) $wAdresse) ?>"></div>
    <div class="feld"><label>Schlüssel</label>
      <input readonly id="wschluessel" value="<?= Fmt::h((string) $wSchluessel) ?>"
             style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px"></div>

    <p style="color:var(--leise);font-size:12.5px;line-height:1.65;margin:6px 0 10px">
      Einmal auf dem Rechner hinterlegen, auf dem du baust — als
      <code>VECOM_WERKSTATT</code> in der Umgebung oder in der Datei, die dein
      Skill dafür liest. Danach reicht am Projekt der Knopf „Befehl für Claude Code".
    </p>

    <?php /* Ein Beispiel schlaegt jede Beschreibung: Wer das sieht, weiss
             in fuenf Sekunden, was am anderen Ende passiert. */ ?>
    <textarea id="wprobe" readonly rows="4" spellcheck="false"
      style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
             font-size:12px;line-height:1.55;white-space:pre;overflow-x:auto">curl -s <?= Fmt::h((string) $wAdresse) ?> \
  -H "X-Vecom-Werkstatt: $VECOM_WERKSTATT" \
  -H "Content-Type: application/json" \
  -d '{"aktion":"auftrag","kunde":"K-2026-0001"}'</textarea>

    <div class="leiste" style="margin-top:12px;gap:8px;flex-wrap:wrap">
      <button class="knopf" data-kopieren="wschluessel">Schlüssel kopieren</button>
      <button class="knopf stumm" data-kopieren="wprobe">Beispiel kopieren</button>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="werkstatt_schluessel_neu">
        <button class="knopf stumm">Neu erzeugen</button></form>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="werkstatt_schluessel_weg">
        <button class="knopf stumm">Tür schließen</button></form>
    </div>

    <p style="color:var(--leise);font-size:12.5px;line-height:1.65;margin:12px 0 0">
      Was von dort geht: Auftrag holen, Stand setzen, Vorschau- und
      Quelltext-Adresse eintragen, eine Notiz in die Akte legen.
      <strong>Freischalten</strong> geht auch — das ist der einzige Schritt, der beim
      Kunden ankommt, und er verlangt deshalb ein ausdrückliches „ja".
    </p>
  <?php endif; ?>
</div>

<div class="block"><h2>Die Hausregeln
    <span class="mehr"><?= $eigener ? 'eigene Fassung' : 'noch die Vorgabe' ?><?php
      if ($gesehenAm): ?> · durchgesehen <?= Fmt::h(Fmt::seit((string) $gesehenAm)) ?><?php
      elseif ($gesehen): ?> · durchgesehen<?php
      endif; ?></span></h2>

  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="standard_speichern">
    <textarea id="standardtext" name="text" rows="26" spellcheck="false"
      style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
             font-size:12.5px;line-height:1.6"><?= Fmt::h((string) $text) ?></textarea>

    <div class="leiste" style="margin-top:12px;gap:14px;flex-wrap:wrap">
      <button class="knopf haupt">Speichern</button>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--dim)">
        <input type="checkbox" name="anhaengen" value="1" <?= $anhaengen ? 'checked' : '' ?>>
        an jedes Briefing anhängen</label>
    </div>
  </form>

  <?php /* GELESEN IST AUCH ERLEDIGT
           Wer den Text liest und richtig findet, hat ihn durchgesehen. Ohne
           diesen Knopf muesste er eine Kleinigkeit aendern, nur damit ein
           Haken umspringt — und ein Werkzeug, das dazu zwingt, erzieht zum
           Pfusch. */ ?>
  <?php if (!$gesehen): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="standard_gesehen">
      <button class="knopf">Passt so</button>
      <span style="color:var(--leise);font-size:12.5px;margin-left:8px">
        Gelesen und nichts zu ändern? Dann hakt das den Punkt auf der Werkstatt ab.</span>
    </form>
  <?php endif; ?>

  <p style="color:var(--leise);font-size:12.5px;line-height:1.65;margin:12px 0 0">
    Ein leeres Feld heißt „zurück zur Vorgabe“, nicht „keine Hausregeln“ —
    ein leerer Standard fiele erst auf, wenn die Seite fertig ist.
    <br>Liegen die Regeln in der Wissensablage deines Claude-Projekts, kennt sie
    jedes Gespräch dort ohnehin. Dann nimm den Haken raus: Das Briefing wird
    um zwei Drittel kürzer und sagt dasselbe.
  </p>
</div>

<div class="block"><h2>Woher die Regeln kommen sollten</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.7;margin:0">
    Nicht aus dem Lehrbuch, sondern aus dem, was schiefging. Ruft ein Kunde an,
    weil niemand seine Öffnungszeiten findet, gehört ein Satz darüber hier hinein
    — und gilt ab dem nächsten Briefing für alle. Das ist der ganze Mechanismus:
    Jeder Ärger schreibt eine Zeile, und die Zeile kommt nicht wieder.
  </p>
</div>
