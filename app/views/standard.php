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

<div class="block"><h2>Die Hausregeln
    <span class="mehr"><?= $eigener ? 'eigene Fassung' : 'noch die Vorgabe' ?></span></h2>

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
