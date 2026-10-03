<?php
/**
 * Marketing · Freigeben (Marketing-Studio 6 — 01.10.2026, Uwe: „ja“ zu U3:
 * „da, wo es mehrere Schritte braucht, sollen es weniger sein“).
 *
 * Ein Stück nach dem anderen, je Land: Vorschau, deutsche Fassung, ein Satz,
 * was „Ja“ tut — dann Ja (freigeben, Bild wählen, auf den nächsten freien
 * Sendeplatz legen), Nein (verwerfen) oder Später. Tasten J, N, S.
 *
 * Erwartet: $land, $x (nächster Entwurf oder null), $rest, $offen, $zg, $medien, $bildLaeuft, $geplant,
 *           $autopilot (Marketing-Studio 7: e, naechster, zg, telegram), $demos (Marketing-Studio 10).
 */
require_once dirname(__DIR__) . '/src/MkLand.php';
require_once dirname(__DIR__) . '/src/MkVeroeffentlichen.php';
require_once dirname(__DIR__) . '/src/MkMedium.php';
$land = $land ?? 'IT';
$offen = $offen ?? MkLand::offen();
$medien = $medien ?? [];
$geplant = $geplant ?? [];
$name = MkLand::name($land);
$andere = MkLand::andere($land);
$plName = static fn(string $p): string => trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$p] ?? $p)) ?? '');
$tag = static fn(string $t): string => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int) date('w', strtotime($t))] . ' ' . date('d.m. H:i', strtotime($t));
$knopf = static fn(string $tat, string $wort, string $klasse, string $taste, int $id): string => '<form method="post" action="' . Fmt::h(url('freigabe')) . '" style="margin:0">'
    . '<input type="hidden" name="_csrf" value="' . Fmt::h(Csrf::token()) . '"><input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="id" value="' . $id . '">'
    . '<button class="' . $klasse . '" data-taste="' . $taste . '">' . Fmt::h($wort) . ' <kbd>' . strtoupper($taste) . '</kbd></button></form>';
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1>Freigeben</h1>
    <div class="weg">ein Stück nach dem anderen · Ja gibt frei und plant es auf den nächsten freien Abend (<?= MkVeroeffentlichen::SENDEZEIT ?>) · Nein verwirft · Später legt es nach hinten</div>
  </div>
</div>

<?php $mkLand = $land; $mkLandSeite = 'freigabe'; $mkLandOffen = $offen; /* Länderschalter steht seit M1 (01.10.2026) oben im Gerüst für alle Marketing-Seiten. */ ?>
<?php $wwZurueck = 'freigabe'; require __DIR__ . '/mk_woche.php'; ?>

<?php $demos = $demos ?? []; if ($demos): require_once dirname(__DIR__) . '/src/MkDemo.php';   /* Marketing-Studio 10: Demo-Vorschauen */
  $dmForm = static fn(string $tat, int $id, string $wort, string $klasse, string $mehr = ''): string => '<form method="post" action="' . Fmt::h(url('freigabe')) . '" style="margin:0;display:flex;gap:6px;flex-wrap:wrap">'
      . '<input type="hidden" name="_csrf" value="' . Fmt::h(Csrf::token()) . '"><input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="id" value="' . $id . '">' . $mehr
      . '<button class="' . $klasse . '">' . Fmt::h($wort) . '</button></form>'; ?>
<section class="block" id="demos" aria-labelledby="mk-demo-titel" style="margin-bottom:16px">
  <h2 id="mk-demo-titel">Demo-Vorschauen <span class="mehr">vom Interessenten selbst angefragt · erst dein Ja schickt ihm den Link (<?= MkDemo::GUELTIG_TAGE ?> Tage gültig)</span></h2>
  <ul class="mk-zeilen">
    <?php foreach ($demos as $dm):
      $dmHaengt = $dm['status'] === 'wartet' && !in_array((string) ($dm['auftrag_status'] ?? ''), ['wartet', 'laeuft'], true);
      $dmHost = (string) (parse_url((string) $dm['url'], PHP_URL_HOST) ?: $dm['url']); ?>
      <li style="display:block">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
          <span><b><?= Fmt::h((string) ($dm['firma'] ?: 'Interessent')) ?></b> <span class="mk-fein">· <?= Fmt::h(trim((string) $dm['stadt'] . ' · ' . $dmHost, ' ·')) ?> · <?= Fmt::h($dmHaengt ? 'hängt' : (MkDemo::STATUS[$dm['status']] ?? $dm['status'])) ?></span></span>
          <?php if (in_array($dm['status'], ['fertig', 'freigegeben'], true)): ?><a class="knopf klein<?= $dm['status'] === 'fertig' ? ' haupt' : '' ?>" href="<?= Fmt::h(url('demo/' . (int) $dm['id'])) ?>" target="_blank" rel="noopener">Ansehen</a><?php endif; ?>
        </div>
        <?php if ($dm['status'] === 'fertig'): ?>
          <?php if (trim((string) $dm['zusammenfassung']) !== ''): ?><p class="mk-fein" style="margin:6px 0 8px;max-width:80ch;line-height:1.55">Claude: <?= Fmt::h((string) $dm['zusammenfassung']) ?></p><?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <?= $dmForm('demo_freigeben', (int) $dm['id'], 'Freigeben und Link schicken', 'knopf klein haupt') ?>
            <?= $dmForm('demo_nochmal', (int) $dm['id'], 'Nochmal bauen', 'knopf klein', '<input type="text" name="hinweis" maxlength="600" placeholder="Hinweis für Claude, z. B. „Fotos größer, weniger Text“" style="width:min(360px,100%)" aria-label="Hinweis für Claude">') ?>
            <?= $dmForm('demo_verwerfen', (int) $dm['id'], 'Verwerfen', 'knopf klein') ?>
          </div>
        <?php elseif ($dm['status'] === 'fehler' || $dmHaengt): ?>
          <p class="mk-fein" style="margin:6px 0 8px">Nicht geklappt: <?= Fmt::h((string) ($dm['fehler'] ?: ($dm['auftrag_ergebnis'] ?? '') ?: 'keine Rückmeldung vom PC')) ?></p>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><?= $dmForm('demo_nochmal', (int) $dm['id'], 'Nochmal bauen', 'knopf klein haupt') ?><?= $dmForm('demo_verwerfen', (int) $dm['id'], 'Verwerfen', 'knopf klein') ?></div>
        <?php elseif ($dm['status'] === 'wartet'): ?>
          <p class="mk-fein" style="margin:6px 0 0">Dein PC baut sie gerade (Claude über dein Abo, meist 5–15 Minuten)<?= trim((string) $dm['hinweis']) !== '' ? ' — mit deinem Hinweis' : '' ?>.</p>
        <?php else: ?>
          <p class="mk-fein" style="margin:6px 0 0">Verschickt · gültig bis <?= Fmt::h(date('d.m.Y', strtotime((string) $dm['gueltig_bis']))) ?> · <?= (int) $dm['aufrufe'] ?>× angesehen</p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($x === null): ?>
  <div class="block">
    <h2>Alles durchgesehen in <?= Fmt::h($name) ?></h2>
    <p style="margin:0 0 12px;max-width:70ch;line-height:1.6">Kein Entwurf wartet hier. Neue entstehen mit „Diese Woche werben“ unter „Jetzt dran“ — oder bei einer Zielgruppe mit „Beiträge schreiben lassen“.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ((int) ($offen[$andere] ?? 0) > 0): ?><a class="knopf haupt" href="<?= Fmt::h(url('freigabe') . '?land=' . $andere) ?>">In <?= Fmt::h(MkLand::name($andere)) ?> warten Entwürfe</a><?php endif; ?>
      <a class="knopf" href="<?= Fmt::h(url('zielgruppen') . '?land=' . $land) ?>">Zu den Zielgruppen</a>
    </div>
  </div>
<?php else:
  $f = $x['f'];
  $bild = null;
  foreach ($medien as $m) { if ($m['status'] === 'gewaehlt') { $bild = $m; break; } }
  if ($bild === null) { foreach ($medien as $m) { if ($m['status'] === 'neu') { $bild = $m; break; } } }
?>
  <section class="mk-stapel" aria-labelledby="mk-stapel-titel">
    <div class="mk-stapel__kopf">
      <span class="mk-fein">Noch <b><?= (int) $rest ?></b> <?= (int) $rest === 1 ? 'Entwurf' : 'Entwürfe' ?> in <?= Fmt::h($name) ?></span>
      <span class="marke2"><?= Fmt::h($plName((string) $x['plattform'])) ?></span>
      <?= MkInhalt::spracheMarke((string) $x['sprache']) ?>
      <span class="marke2 <?= $x['art'] === 'bezahlt' ? 'warnung' : '' ?>"><?= Fmt::h(MkInhalt::ARTEN[$x['art']] ?? $x['art']) ?></span>
      <span class="mk-fein"><?= Fmt::h(MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) ?><?= $zg ? ' · für ' . Fmt::h((string) $zg['titel']) : '' ?></span>
    </div>
    <h2 id="mk-stapel-titel" class="mk-stapel__titel"><?= Fmt::h((string) $x['titel']) ?></h2>
    <div class="mk-zwei">
      <div>
        <div class="mk-vorschau">
          <div class="mk-vorschau__kopf"><span class="mk-vorschau__logo"></span><div><div class="mk-vorschau__name">Vecom Design</div><div class="mk-vorschau__zweit"><?= Fmt::h($plName((string) $x['plattform'])) ?><?= $x['art'] === 'bezahlt' ? ' · Gesponsert' : '' ?></div></div></div>
          <?php if ($bild): ?>
            <?php if ($bild['art'] === 'video'): ?><video class="mk-medium" src="<?= Fmt::h(url('medien/' . (int) $bild['id'])) ?>" controls preload="metadata" playsinline></video>
            <?php else: ?><img class="mk-medium" src="<?= Fmt::h(url('medien/' . (int) $bild['id'])) ?>" alt="<?= Fmt::h('Bild zu „' . $x['titel'] . '“') ?>"><?php endif; ?>
          <?php elseif ($x['format'] !== 'google_anzeige'): ?>
            <div class="mk-vorschau__bild"><?= ($dreiDWartet ?? 0) > 0 && !$bildLaeuft ? 'Blender rechnet das Bild in der Nachtschicht …' : ($bildLaeuft ? 'Das Bild entsteht gerade …' : Fmt::h($x['bildidee'] ? 'Bildidee: ' . $x['bildidee'] : 'noch kein Bild')) ?></div>
          <?php endif; ?>
          <?php /* Marketing-Studio 11: Mehrere Bilder (z. B. Kie.ai und Blender) — hier das bessere wählen. */
            $msWahl = array_values(array_filter($medien, static fn($m) => $m['status'] !== 'verworfen' && $bild && $m['art'] === $bild['art']));
            if (count($msWahl) > 1): ?>
            <div class="mk-wahl" role="group" aria-label="Welches Bild?">
              <?php foreach ($msWahl as $mw): ?>
                <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" style="margin:0">
                  <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="stapel_medium"><input type="hidden" name="medium_id" value="<?= (int) $mw['id'] ?>"><input type="hidden" name="land" value="<?= Fmt::h($land) ?>">
                  <button class="mk-wahl__knopf<?= (int) $mw['id'] === (int) $bild['id'] ? ' ist' : '' ?>" aria-pressed="<?= (int) $mw['id'] === (int) $bild['id'] ? 'true' : 'false' ?>">
                    <?php if ($mw['art'] === 'video'): ?><span class="mk-wahl__video">Video</span><?php else: ?><img src="<?= Fmt::h(url('medien/' . (int) $mw['id'])) ?>" alt="" loading="lazy"><?php endif; ?>
                    <span><?= Fmt::h(['blender' => 'Blender', 'unreal' => 'Unreal'][(string) $mw['modell']] ?? 'Kie.ai') ?></span>
                  </button>
                </form>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="mk-vorschau__text"><?= Fmt::h(MkInhalt::kopiertext($x)) ?></div>
          <?php if (!empty($f['folien'])): ?>
            <div class="mk-folien"><?php foreach ($f['folien'] as $i => $fo): ?><div class="mk-folie"><i><?= $i + 1 ?>/<?= count($f['folien']) ?></i><b><?= Fmt::h((string) $fo['titel']) ?></b><span><?= Fmt::h((string) $fo['text']) ?></span></div><?php endforeach; ?></div>
          <?php endif; ?>
          <?php if (!empty($f['szenen'])): ?>
            <ol class="mk-fein" style="margin:10px 0 0;padding-left:18px;line-height:1.5"><?php foreach ($f['szenen'] as $sz): ?><li><?= (int) $sz['sekunden'] ?> s · <?= Fmt::h((string) $sz['bild']) ?><?= $sz['einblendung'] !== '' ? ' · „' . Fmt::h((string) $sz['einblendung']) . '“' : '' ?></li><?php endforeach; ?></ol>
          <?php endif; ?>
        </div>
      </div>
      <div class="mk-stapel__rechts">
        <?php if ($x['sprache'] !== 'de' && !empty($x['uebersetzung'])): ?>
          <div class="mk-uebersetzung" style="margin-top:0"><h3>🇩🇪 Auf Deutsch — nur zum Lesen, gepostet wird der Text links</h3><?= Fmt::h((string) $x['uebersetzung']) ?></div>
        <?php endif; ?>
        <?php if ($x['begruendung']): ?><p class="mk-fein" style="margin:0;line-height:1.55"><b>Warum das trägt:</b> <?= Fmt::h((string) $x['begruendung']) ?></p><?php endif; ?>
        <div class="mk-stapel__entscheid">
          <p style="margin:0;line-height:1.5"><?= Fmt::h(MkVeroeffentlichen::wasPassiert($x)) ?></p>
          <div class="mk-stapel__knoepfe">
            <?= $knopf('stapel_ja', 'Ja — freigeben', 'knopf haupt', 'j', (int) $x['id']) ?>
            <?= $knopf('stapel_nein', 'Nein — verwerfen', 'knopf', 'n', (int) $x['id']) ?>
            <?= $knopf('stapel_spaeter', 'Später', 'knopf', 's', (int) $x['id']) ?>
          </div>
          <a class="mk-fein" href="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>#bearbeiten">Erst etwas ändern? Stück öffnen →</a>
        </div>
      </div>
    </div>
  </section>
  <script>
  /* J = Ja, N = Nein, S = Später — nicht, während jemand tippt. */
  document.addEventListener('keydown', function (e) {
    var a = document.activeElement; if (e.ctrlKey || e.metaKey || e.altKey || (a && /^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName))) { return; }
    var k = document.querySelector('[data-taste="' + e.key.toLowerCase() + '"]'); if (k) { e.preventDefault(); k.click(); }
  });
  </script>
<?php endif; ?>

<?php if ($geplant): ?>
<div class="block" style="margin-top:16px">
  <h2>Eingeplant <span class="mehr">geht von selbst raus · ändern oder aufheben am Stück</span></h2>
  <ul class="mk-zeilen">
    <?php foreach ($geplant as $g): ?><li><span><?= MkLand::marke((string) $g['land'], false) ?> <a href="<?= Fmt::h(url('inhalte/' . (int) $g['id'])) ?>#posten"><?= Fmt::h((string) $g['titel']) ?></a></span><span class="mk-fein" style="white-space:nowrap"><?= Fmt::h($plName((string) $g['plattform'])) ?> · <?= Fmt::h($tag((string) $g['geplant_am'])) ?></span></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if (isset($autopilot)): $ap = $autopilot['e']; require_once dirname(__DIR__) . '/src/MkAutopilot.php'; ?>
<section class="block mk-start" id="autopilot" aria-labelledby="mk-ap-titel" style="margin-top:16px">
  <h2 id="mk-ap-titel">Autopilot für <?= Fmt::h($name) ?> <span class="mehr"><?= $ap['an'] ? 'an' : 'aus' ?> · einmal je Woche eine Kampagne, Freigabe per Telegram</span></h2>
  <form method="post" action="<?= Fmt::h(url('freigabe')) ?>">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="autopilot_speichern"><input type="hidden" name="land" value="<?= Fmt::h($land) ?>">
    <div class="mk-start__wahl">
      <label class="mk-haken"><input type="checkbox" name="an" value="1"<?= $ap['an'] ? ' checked' : '' ?>> <b>Autopilot an</b></label>
      <label class="mk-haken">jeden <select name="tag" style="width:auto"><?php foreach (MkAutopilot::TAGE as $tn => $tw): ?><option value="<?= $tn ?>"<?= $ap['tag'] === $tn ? ' selected' : '' ?>><?= Fmt::h($tw) ?></option><?php endforeach; ?></select></label>
      <label class="mk-haken">um <select name="stunde" style="width:auto"><?php for ($st = 5; $st <= 22; $st++): ?><option value="<?= $st ?>"<?= $ap['stunde'] === $st ? ' selected' : '' ?>><?= sprintf('%02d:00', $st) ?></option><?php endfor; ?></select></label>
      <label class="mk-haken"><input type="checkbox" name="anzeigen" value="1"<?= $ap['anzeigen'] ? ' checked' : '' ?>> auch Anzeigen</label>
      <label class="mk-haken"><input type="checkbox" name="bilder" value="1"<?= $ap['bilder'] ? ' checked' : '' ?>> mit Bildern (Kie.ai)</label>
    </div>
    <div><button class="knopf haupt">Speichern</button></div>
  </form>
  <ol class="mk-start__schritte">
    <li>Am gewählten Tag startet die Verwaltung eine Kampagne für die freigegebene Zielgruppe, die am längsten nichts bekommen hat<?= $autopilot['zg'] ? ' — als Nächstes „' . Fmt::h((string) $autopilot['zg']['titel']) . '“' : ' — noch keine freigegeben' ?>.</li>
    <li>Claude schreibt <?= $ap['anzeigen'] ? '8 Stücke (Beiträge und Anzeigen)' : '6 Beiträge' ?> über dein Claude-Abo<?= $ap['bilder'] ? ', Kie.ai macht die Bilder (etwa ' . ($ap['anzeigen'] ? 7 : 6) * (int) MkMedium::MODELLE['bild'][array_key_first(MkMedium::MODELLE['bild'])][1] . ' Credits je Woche, Guthaben wird vorher geprüft)' : '' ?>.</li>
    <li>Sind Texte und Bilder fertig, kommt eine Telegram-Nachricht: Stück für Stück Ja, Nein oder Später — wie hier.<?= !$autopilot['telegram'] ? ' <b>Dein Telegram ist noch nicht verbunden</b> (Einstellungen → Telegram) — dann bleibt der Stapel hier.' : '' ?></li>
    <li>Ohne dein Ja geht nichts raus.</li>
  </ol>
  <?php if ($autopilot['naechster'] !== null): ?><p class="mk-fein" style="margin:8px 0 0">Nächster Lauf: <?= $autopilot['naechster'] <= time() + 600 ? 'beim nächsten Cronlauf' : Fmt::h($tag(date('Y-m-d H:i', (int) $autopilot['naechster']))) ?>.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php $mo = MkMedium::motor();   /* Marketing-Studio 11: womit Bilder und Videos entstehen, und wann der PC 3D rechnet */ ?>
<section class="block mk-start" id="motor" aria-labelledby="mk-mo-titel" style="margin-top:16px">
  <h2 id="mk-mo-titel">Bilder und Videos <span class="mehr">Kie.ai, Blender oder Unreal · gilt für Kampagnen, Autopilot und „Automatisch“ am Stück</span></h2>
  <form method="post" action="<?= Fmt::h(url('freigabe')) ?>">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="motor_speichern"><input type="hidden" name="land" value="<?= Fmt::h($land) ?>">
    <div class="mk-start__wahl">
      <label class="mk-haken">Bilder <select name="bild" style="width:auto"><?php foreach (MkMedium::MOTOREN['bild'] as $mk => $mw): ?><option value="<?= $mk ?>"<?= $mo['bild'] === $mk ? ' selected' : '' ?>><?= Fmt::h($mw) ?></option><?php endforeach; ?></select></label>
      <label class="mk-haken">Videos <select name="video" style="width:auto"><?php foreach (MkMedium::MOTOREN['video'] as $mk => $mw): ?><option value="<?= $mk ?>"<?= $mo['video'] === $mk ? ' selected' : '' ?>><?= Fmt::h($mw) ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="mk-start__wahl">
      <label class="mk-haken"><input type="checkbox" name="nacht_an" value="1"<?= $mo['nacht_an'] ? ' checked' : '' ?>> <b>Nachtschicht</b> — 3D nur</label>
      <label class="mk-haken">von <select name="nacht_von" style="width:auto"><?php for ($st = 0; $st <= 23; $st++): ?><option value="<?= $st ?>"<?= $mo['nacht_von'] === $st ? ' selected' : '' ?>><?= sprintf('%02d:00', $st) ?></option><?php endfor; ?></select></label>
      <label class="mk-haken">bis <select name="nacht_bis" style="width:auto"><?php for ($st = 0; $st <= 23; $st++): ?><option value="<?= $st ?>"<?= $mo['nacht_bis'] === $st ? ' selected' : '' ?>><?= sprintf('%02d:00', $st) ?></option><?php endfor; ?></select></label>
    </div>
    <div class="mk-start__wahl">
      <input type="hidden" name="unreal_bereit" value="0">
      <label class="mk-haken"><input type="checkbox" name="unreal_bereit" value="1"<?= $mo['unreal_bereit'] ? ' checked' : '' ?>> <b>Unreal freigeschaltet</b> — 3D-Videos mit dem Path Tracer (Probelauf 01.10.: so hell wie Blender, etwa viermal schneller)</label>
    </div>
    <div><button class="knopf haupt">Speichern</button></div>
  </form>
  <ul class="mk-start__schritte" style="list-style:disc">
    <li>3D-Szenen gibt es für: <?= Fmt::h(implode(', ', array_unique(array_map(static fn($b) => MkKampagne::branchen()[$b] ?? $b, array_keys(MkMedium::STUDIOS))))) ?>. Für andere Branchen baut Claude das Bild als Blender-Szene aus der Bildidee; Videos laufen dort über Kie.ai.</li>
    <li>Blender rechnet fotoreal mit Cycles auf deiner RTX 5070 — ohne Credits. Vor jedem Bild misst der PC die Belichtung an einer kleinen Probe und gleicht höchstens eine Blende aus.</li>
    <li>Ein <b>Werbespot</b> sind fünf Einstellungen wie beim Dreh: weit heran, nah mit Schärfeverlagerung, tief seitlich, Bogen ums Motiv, Kran ins Schlussbild — mit echter Bewegungsunschärfe, dezenter Farbe, Musik und Abspann (Name in Gold, Satz, Adresse). Vorher prüft der PC jede Kamerafahrt, ob etwas im Weg steht, und weicht aus.</li>
    <li><?= $mo['unreal_bereit'] ? 'Unreal ist freigeschaltet: Wer „Unreal“ wählt, bekommt die eine Fahrt mit dem Path Tracer; vor jedem Film misst ein Probebild die Belichtung gegen Blender, scheitert Unreal, rechnet Blender.' : 'Unreal (Path Tracer) ist ausgeschaltet — 3D-Videos entstehen mit Blender.' ?></li>
    <li>Gerade <?= MkMedium::imFenster() ? 'darf der PC 3D rechnen' : 'ist keine Nachtschicht — 3D wartet bis ' . sprintf('%02d:00', $mo['nacht_von']) ?>. „3D jetzt rechnen“ am Stück geht immer.</li>
  </ul>
</section>

<?php $g3Offen = MkMedium::galerieOffen();   /* Marketing-Studio 11: 3D-Galerie für Partner — erst nach deinem Ja sichtbar */ ?>
<section class="block mk-start" id="partner3d" aria-labelledby="mk-g3-titel" style="margin-top:16px">
  <h2 id="mk-g3-titel">Studio für Partner <span class="mehr">du produzierst, du gibst frei — erst dann steht es im Partner-Dashboard (Reiter Werben) · Partner dürfen höchstens <?= MkMedium::PARTNER_JE_WOCHE ?> Wünsche je Woche schicken · ihr Link kommt beim Teilen drauf</span></h2>
  <?php $g3Wuensche = MkMedium::wuenscheOffen(); if ($g3Wuensche): ?>
    <h3 class="mk-fein" style="margin:4px 0 8px;font-size:14px;color:var(--text)">Wünsche von Partnern — erst nach deinem Ja wird gerechnet</h3>
    <?php foreach ($g3Wuensche as $g3w): ?>
      <div class="mk-wunsch" style="border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:10px 12px;margin:0 0 8px;line-height:1.55">
        <b><?= Fmt::h($g3w['partner']) ?></b> · <?= $g3w['art'] === 'video' ? 'Video' : 'Bild' ?> <?= Fmt::h($g3w['format']) ?> ·
        <?= $g3w['studio'] !== '' ? Fmt::h(MkMedium::STUDIO_NAMEN[$g3w['studio']] ?? $g3w['studio']) : 'eigene Idee' ?>
        <span class="mk-fein" style="display:inline"> · <?= Fmt::h(date('d.m. H:i', strtotime($g3w['am']))) ?></span>
        <?php if ($g3w['text'] !== ''): ?><blockquote style="margin:6px 0;padding:6px 10px;border-left:3px solid rgba(241,211,139,.6)"><?= Fmt::h($g3w['text']) ?></blockquote><?php endif; ?>
        <?php if ($g3w['titel'] !== ''): ?><div>Titel im Video: „<?= Fmt::h($g3w['titel']) ?>“</div><?php endif; ?>
        <?php if ($g3w['studio'] !== ''): ?><div class="mk-fein">Blickwinkel <?= Fmt::h((string) ($g3w['wunsch']['blick'] ?? '')) ?> · Nähe <?= Fmt::h((string) ($g3w['wunsch']['naehe'] ?? '')) ?> · Stimmung <?= Fmt::h((string) ($g3w['wunsch']['stimmung'] ?? '')) ?></div><?php endif; ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
          <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="g3_wunsch_ja"><input type="hidden" name="auftrag_id" value="<?= (int) $g3w['id'] ?>"><button class="knopf klein haupt">Ja, rechnen</button></form>
          <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="g3_wunsch_nein"><input type="hidden" name="auftrag_id" value="<?= (int) $g3w['id'] ?>"><button class="knopf klein">Nein</button></form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if ($g3Offen): ?>
    <div class="mk-galerie">
      <?php foreach ($g3Offen as $g3): ?>
        <figure class="mk-galerie__stueck">
          <?php if ($g3['art'] === 'video'): ?><video class="mk-medium" src="<?= Fmt::h(url('medien/' . (int) $g3['id'])) ?>" controls preload="metadata" playsinline></video>
          <?php else: ?><img class="mk-medium" src="<?= Fmt::h(url('medien/' . (int) $g3['id'])) ?>" alt="3D <?= Fmt::h((string) $g3['studio']) ?>" loading="lazy"><?php endif; ?>
          <figcaption><span><?= !empty($g3['partner_id']) ? 'Wunsch eines Partners · ' : '' ?><?= in_array((string) $g3['modell'], ['veo3', 'veo3_fast', 'nano-banana-pro'], true) ? 'Kie.ai · ' : '' ?><?= Fmt::h((string) $g3['studio'] === 'vecom' ? 'Vecom-Werbespot' : (MkMedium::STUDIO_NAMEN[(string) $g3['studio']] ?? ((string) $g3['studio'] !== '' ? (string) $g3['studio'] : 'eigene Idee'))) ?> · <?= Fmt::h((string) $g3['format']) ?></span>
            <span class="mk-galerie__knoepfe">
              <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="galerie_freigeben"><input type="hidden" name="medium_id" value="<?= (int) $g3['id'] ?>"><button class="knopf klein haupt">Für Partner freigeben</button></form>
              <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="galerie_verwerfen"><input type="hidden" name="medium_id" value="<?= (int) $g3['id'] ?>"><button class="knopf klein">Verwerfen</button></form>
            </span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="mk-fein" style="margin:0 0 10px">Nichts wartet. Partner sehen nur, was du hier im Studio produzierst und freigibst.</p>
  <?php endif; ?>
  <?php require_once dirname(__DIR__) . '/src/MkStudio.php'; $stLauf = MkStudio::laeuft(); $stLp = $stLauf ? (json_decode((string) $stLauf['parameter'], true) ?: []) : []; /* Studio, 03.10.2026 */ ?>
  <div class="mk-studio" id="studio" style="margin-top:14px;padding:14px 16px;border:1px solid rgba(241,211,139,.35);border-radius:12px">
    <h3 style="margin:0 0 6px;font-size:17px">Studio — ein Video nach dem anderen</h3>
    <p class="mk-fein" style="margin:0 0 10px;max-width:90ch;line-height:1.55">Kinoreif wie ein Trailer: mit Stimme über Kie.ai (Veo 3.1 Quality, etwa 400 Credits). Fehlt das Kie-Guthaben, kommt derselbe Eintrag automatisch als Blender-Werbespot mit Musik. Partner sehen es erst nach deinem „Für Partner freigeben“, und beim Teilen setzt ihr Dashboard ihren Link ans Ende — so führt jedes Video auf die Seite dessen, der es teilt. <b>3D-Arbeiten</b> starten vom Blender-Standbild der Fallstudie (Laptop und Handy mit der echten Website) und sind immer quer 16:9, nur mit Kie.ai.</p>
    <?php if ($stLauf): ?>
      <p class="hinweis" style="margin:0 0 10px">Läuft gerade: <b><?= Fmt::h((string) ($stLp['titel'] ?? '')) ?></b> · <?= $stLauf['status'] === 'laeuft' ? 'dein PC arbeitet daran' : 'wartet auf deinen PC' ?>. Das nächste geht, sobald es fertig ist.</p>
    <?php else: ?>
    <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" class="mk-formular" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
      <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="studio_produzieren">
      <div class="feld" style="flex:2 1 280px"><label for="st_k">Was</label>
        <select id="st_k" name="katalog"><?php foreach (MkStudio::GRUPPEN as $stG => $stGn): ?><optgroup label="<?= Fmt::h($stGn) ?>"><?php foreach (MkStudio::KATALOG as $stK => $stE): if ($stE[0] !== $stG) { continue; } ?><option value="<?= Fmt::h($stK) ?>"><?= Fmt::h($stE[1]) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
      <div class="feld"><label for="st_a">Art</label><select id="st_a" name="art"><option value="video">Video</option><option value="bild">Bild</option></select></div>
      <div class="feld" style="flex:1 1 220px"><label for="st_m">Motor</label><select id="st_m" name="motor"><?php foreach (MkStudio::MOTOREN as $stM => $stMn): ?><option value="<?= Fmt::h($stM) ?>"><?= Fmt::h($stMn) ?></option><?php endforeach; ?></select></div>
      <div class="feld"><label for="st_s">Sprache</label><select id="st_s" name="sprache"><option value="de">Deutsch</option><option value="it">Italienisch</option><option value="en">Englisch</option></select></div>
      <div class="feld"><label for="st_f">Format</label><select id="st_f" name="format"><option value="9:16">hoch 9:16</option><option value="16:9">quer 16:9</option><option value="4:5">Bild 4:5</option><option value="1:1">Bild 1:1</option></select></div>
      <button class="knopf haupt">Produzieren</button>
    </form>
    <?php endif; ?>
  </div>
  <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" style="margin-top:12px" onsubmit="return confirm('Alle bisherigen 3D-Bilder und -Videos aus den Partner-Dashboards und der Verwaltung nehmen und offene Galerie-Aufträge abbrechen? Die Dateien bleiben bis zum endgültigen Löschen auf dem Server.')">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="galerie_leeren">
    <button class="knopf klein">Alle bisherigen Galerie-Bilder und -Videos entfernen</button>
  </form>
  <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" class="mk-zeile" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="vecom_spot">
    <span>Vecom-Werbespot: je Branche eine Einstellung (<?= count(MkMedium::VECOM_SPOT) ?>), am Ende das goldene V —</span>
    <select name="sprache" aria-label="Sprache" style="width:auto"><option value="it">Italienisch</option><option value="de">Deutsch</option><option value="en">Englisch</option></select>
    <select name="format" aria-label="Format" style="width:auto"><option value="9:16">hoch 9:16</option><option value="16:9">quer 16:9</option></select>
    <button class="knopf">Rechnen (nächste Nachtschicht)</button>
  </form>
</section>

<?php /* Titelbilder der Partnerseiten (03.10.2026, Uwe: Ja zu B1 Kino-Kopf, B3 Piazza-Intro, B4 Jahreszeiten). */
  require_once dirname(__DIR__) . '/src/PartnerKopf.php';
  $pkStand = PartnerKopf::stand(); $pkWartet = PartnerKopf::wartend(); $pkJetzt = PartnerKopf::saison();
  $pkFehlt = 0; foreach ($pkStand as $pkS) { foreach ($pkS as $pkZ) { foreach ($pkZ as $pkW) { $pkFehlt += $pkW === 'fehlt' ? 1 : 0; } } }
  $pkZeichen = ['frei' => ['●', 'frei', '#34d39b'], 'wartet' => ['◆', 'wartet auf dich', '#f1d38b'], 'rechnet' => ['○', 'beim PC', '#8fb4ff'], 'fehlt' => ['–', 'fehlt', 'rgba(255,255,255,.4)']]; ?>
<section class="block mk-start" id="koepfe" aria-labelledby="mk-pk-titel" style="margin-top:16px">
  <h2 id="mk-pk-titel">Titelbilder der Partnerseiten <span class="mehr">Kino-Kopf je Branche · Licht der Jahreszeit · Piazza-Intro aus Unreal · erst dein Ja bringt sie auf die Seiten</span></h2>
  <p class="mk-fein" style="margin:0 0 10px;max-width:92ch;line-height:1.55">Je Szene ein Standbild (1920×1080) und eine stumme Schleife von <?= PartnerKopf::SCHLEIFE_SEKUNDEN ?> Sekunden (1280×720), gerechnet mit Cycles auf deinem PC in der Nachtschicht — keine Credits. Die Seite zeigt zuerst das Standbild; die Schleife beginnt im selben Blickwinkel und blendet darüber. Jetzt ist <b><?= Fmt::h(PartnerKopf::SAISONEN[$pkJetzt]) ?></b>; die Seiten nehmen von selbst die passende Jahreszeit. Es wechseln Licht und Farbe, keine Gegenstände. Das Piazza-Intro (Quattro Canti, Palermo, goldene Stunde, <?= PartnerKopf::INTRO_SEKUNDEN ?> s) rechnet Unreal, wenn freigeschaltet, sonst Blender.</p>
  <div class="tabellenrahmen" style="overflow-x:auto"><table style="min-width:560px;font-variant-numeric:tabular-nums">
    <thead><tr><th scope="col">Szene</th><?php foreach (PartnerKopf::SAISONEN as $pkSk => $pkSn): ?><th scope="col"><?= Fmt::h($pkSn) ?><?= $pkSk === $pkJetzt ? ' (jetzt)' : '' ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($pkStand as $pkSt => $pkS): ?>
      <tr><th scope="row"><?= Fmt::h($pkSt === PartnerKopf::PIAZZA ? 'Piazza-Intro Palermo (ganzjährig)' : (MkMedium::STUDIO_NAMEN[$pkSt] ?? $pkSt)) ?></th>
        <?php foreach ($pkSt === PartnerKopf::PIAZZA ? ['' => ''] : PartnerKopf::SAISONEN as $pkSk => $pkSn): $pkZ = $pkS[$pkSk] ?? ['bild' => 'fehlt', 'film' => 'fehlt']; ?>
          <td<?= $pkSt === PartnerKopf::PIAZZA ? ' colspan="4"' : '' ?>><?php foreach (['bild' => 'Bild', 'film' => 'Film'] as $pkA => $pkAn): $pkZe = $pkZeichen[$pkZ[$pkA]]; ?><span style="color:<?= $pkZe[2] ?>;margin-right:10px" title="<?= Fmt::h($pkAn . ': ' . $pkZe[1]) ?>"><?= $pkZe[0] ?> <?= $pkAn ?></span><?php endforeach; ?></td>
        <?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <p class="mk-fein" style="margin:6px 0 10px"><?php foreach ($pkZeichen as $pkZe): ?><span style="color:<?= $pkZe[2] ?>;margin-right:14px"><?= $pkZe[0] ?> <?= Fmt::h($pkZe[1]) ?></span><?php endforeach; ?></p>
  <?php if ($pkWartet): ?>
    <div class="mk-galerie">
      <?php foreach ($pkWartet as $pk): ?>
        <figure class="mk-galerie__stueck">
          <?php if ($pk['art'] === 'film'): ?><video class="mk-medium" src="<?= Fmt::h(url('medien/' . (int) $pk['medium_id'])) ?>" controls muted loop preload="metadata" playsinline></video>
          <?php else: ?><img class="mk-medium" src="<?= Fmt::h(url('medien/' . (int) $pk['medium_id'])) ?>" alt="Titelbild <?= Fmt::h((string) $pk['studio']) ?>" loading="lazy"><?php endif; ?>
          <figcaption><span><?= Fmt::h($pk['studio'] === PartnerKopf::PIAZZA ? 'Piazza-Intro' : (MkMedium::STUDIO_NAMEN[$pk['studio']] ?? $pk['studio'])) ?> · <?= Fmt::h(PartnerKopf::SAISONEN[$pk['saison']] ?? 'ganzjährig') ?> · <?= $pk['art'] === 'film' ? 'Film' : 'Standbild' ?></span>
            <span class="mk-galerie__knoepfe">
              <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="kopf_freigeben"><input type="hidden" name="kopf_id" value="<?= (int) $pk['id'] ?>"><button class="knopf klein">Freigeben</button></form>
              <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="kopf_verwerfen"><input type="hidden" name="kopf_id" value="<?= (int) $pk['id'] ?>"><button class="knopf klein">Verwerfen</button></form>
            </span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($pkFehlt > 0): ?>
    <form method="post" action="<?= Fmt::h(url('freigabe')) ?>" class="mk-zeile" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
      <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="koepfe_bestellen">
      <span><?= $pkFehlt ?> fehlen noch — je Klick die nächsten <?= PartnerKopf::JE_KLICK ?> (Piazza und die laufende Jahreszeit zuerst; Verworfenes wird mit neuem Blickwinkel neu gerechnet).</span>
      <button class="knopf">Rechnen lassen (nächste Nachtschicht)</button>
    </form>
  <?php endif; ?>
</section>
