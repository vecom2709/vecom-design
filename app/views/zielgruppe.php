<?php
/**
 * Marketing · eine Zielgruppe (Marketing-Studio 1 und 5 — 01.10.2026).
 * Erwartet: $z (MkZielgruppe::laden), $daten (Datengrundlage), $funde (Recherche dieser Branche im Land),
 *           $kampagnen (dieser Zielgruppe), $inhalteZahl, $pc.
 * Italienische Kundensprache (Einwände, Fragen, Suchbegriffe, Botschaften,
 * Keywords) steht mit deutscher Fassung darunter — Uwe: „für die Verwaltung
 * auf Deutsch anzeigen, dass wir es lesen können“.
 */
require_once dirname(__DIR__) . '/src/MkLand.php';
require_once dirname(__DIR__) . '/src/MkMedium.php';
$p = $z['p'];
$branchen = MkKampagne::branchen();
$kampagnen = $kampagnen ?? [];
$inhalteZahl = (int) ($inhalteZahl ?? 0);
$n = static fn(int $x): string => number_format($x, 0, ',', '.');
$datum = static fn(?string $t): string => $t ? date('d.m.Y', strtotime($t)) : '—';
$istIt = $z['land'] === 'IT';
$de = $istIt ? (array) ($p['de'] ?? []) : [];
$liste = static function (array $eintraege, array $deutsch = []): void {
    if (!$eintraege) { echo '<p class="leise" style="margin:0">—</p>'; return; }
    echo '<ul class="mk-liste">';
    foreach (array_values($eintraege) as $i => $e) {
        $d = trim((string) ($deutsch[$i] ?? ''));
        echo '<li>' . Fmt::h((string) $e) . ($d !== '' && $d !== (string) $e ? '<span class="mk-de">' . Fmt::h($d) . '</span>' : '') . '</li>';
    }
    echo '</ul>';
};
$nutzbar = $z['status'] === 'freigegeben' || $z['v'] !== null;
$gegenstueck = $gegenstueck ?? null;
$kampagneLaeuft = (bool) ($kampagneLaeuft ?? false);
$zahlen = ($zahlen ?? []) + MkKampagne::LEER;
$ohneDe = $istIt && MkZielgruppe::ohneDeutsch($p + ['land' => 'IT']);
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1><?= Fmt::h($p['titel'] ?? $z['titel']) ?>
      <?php if ($z['status'] === 'freigegeben'): ?><span class="marke2 gut" style="vertical-align:4px">freigegeben</span><?php else: ?><span class="marke2 warnung" style="vertical-align:4px"><?= $z['v'] ? 'Überarbeitung' : 'Entwurf' ?></span><?php endif; ?></h1>
    <div class="weg"><?= MkLand::marke((string) $z['land']) ?> · <?= Fmt::h($branchen[$z['branche']] ?? $z['branche']) ?> · Stand <?= Fmt::h($datum($z['updated_at'])) ?><?= $z['freigegeben_am'] ? ' · freigegeben am ' . Fmt::h($datum($z['freigegeben_am'])) : '' ?> · von Claude recherchiert</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($nutzbar): ?><a class="knopf haupt" href="#kampagne">Beiträge schreiben lassen</a><?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('zielgruppen')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="recherche_starten"><input type="hidden" name="branche" value="<?= Fmt::h((string) $z['branche']) ?>"><input type="hidden" name="land" value="<?= Fmt::h((string) $z['land']) ?>"><button class="knopf" title="Claude überarbeitet dieses Profil mit frischer Recherche — als Entwurf, die freigegebene Fassung gilt bis dahin weiter">Neu recherchieren</button></form>
    <a class="knopf" href="<?= Fmt::h(url('zielgruppen') . '?land=' . $z['land']) ?>">‹ Alle in <?= Fmt::h(MkLand::name((string) $z['land'])) ?></a>
  </div>
</div>

<?php if ($z['status'] !== 'freigegeben'): ?>
<div class="block" style="border-color:var(--linie2)">
  <h2>Prüfen und freigeben</h2>
  <p style="margin:0 0 12px;max-width:70ch;line-height:1.6"><?= $z['v'] ? 'Claude hat die freigegebene Fassung überarbeitet. Bis du freigibst, gilt die alte weiter.' : 'Ein Entwurf. Inhalte und Kampagnen stützen sich erst darauf, wenn du ihn freigibst.' ?> Prüf vor allem, ob die Probleme zu dem passen, was du von Kunden hörst, und ob die Quellen tragen.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <form method="post" action="<?= Fmt::h(url('zielgruppen/' . (int) $z['id'])) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="zielgruppe_freigeben"><input type="hidden" name="id" value="<?= (int) $z['id'] ?>"><button class="knopf haupt">Zielgruppe freigeben</button></form>
    <form method="post" action="<?= Fmt::h(url('zielgruppen/' . (int) $z['id'])) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="zielgruppe_verwerfen"><input type="hidden" name="id" value="<?= (int) $z['id'] ?>"><button class="knopf"><?= $z['v'] ? 'Überarbeitung verwerfen, alte Fassung behalten' : 'Entwurf verwerfen' ?></button></form>
  </div>
</div>
<?php endif; ?>

<?php if ($ohneDe): ?>
<div class="block mk-hinweis-zeile">
  <span style="font-size:14px;max-width:70ch;line-height:1.5">Einwände, Fragen, Suchbegriffe und Botschaften stehen auf Italienisch, weil Kunden sie so lesen und tippen. Die deutsche Übersetzung darunter fehlt bei diesem Profil noch.</span>
  <form method="post" action="<?= Fmt::h(url('zielgruppen')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="uebersetzen_starten"><button class="knopf klein">Übersetzen lassen</button></form>
</div>
<?php endif; ?>

<?php if ($nutzbar): ?>
<section class="block mk-start" id="kampagne" aria-labelledby="mk-start-titel">
  <h2 id="mk-start-titel">Beiträge schreiben lassen <span class="mehr">ein Klick · Claude schreibt, du gibst frei, Vecom postet</span></h2>
  <?php if ($kampagneLaeuft): ?>
    <p style="margin:0;line-height:1.6">Claude schreibt gerade für diese Zielgruppe — oder der Auftrag wartet auf deinen PC. Die Entwürfe landen unter <a href="<?= Fmt::h(url('freigabe') . '?land=' . $z['land']) ?>">Freigeben</a>.</p>
  <?php else: ?>
  <form method="post" action="<?= Fmt::h(url('zielgruppen/' . (int) $z['id'])) ?>">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="kampagne_starten"><input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
    <details class="mk-mehr"><summary>Mehr: was genau, Thema, auch im anderen Land</summary>
    <div class="mk-start__wahl">
      <label class="mk-haken"><input type="checkbox" name="organisch" value="1" checked> Beiträge (Instagram, Facebook, Telegram)</label>
      <label class="mk-haken"><input type="checkbox" name="anzeigen" value="1" checked> Anzeigen (Meta und Google)</label>
      <label class="mk-haken"><input type="checkbox" name="bilder" value="1" checked> mit Bildern (Kie.ai, etwa <?= (int) MkMedium::MODELLE['bild'][array_key_first(MkMedium::MODELLE['bild'])][1] ?> Credits je Bild)</label>
      <?php if ($gegenstueck): ?><label class="mk-haken"><input type="checkbox" name="beide" value="1"> auch für <?= MkLand::marke(MkLand::andere((string) $z['land'])) ?></label><?php endif; ?>
    </div>
    <div class="feld" style="margin:0"><label for="ks_thema">Thema <span class="mk-fein">(freiwillig — sonst wählt Claude aus Profil und Funden)</span></label>
      <input id="ks_thema" name="thema" maxlength="200" placeholder="<?= $z['land'] === 'DE' ? 'z. B. Online-Termine ohne Telefon · Google-Profil' : 'z. B. Airbnb-Gebühr ab Mitte Oktober · Nebensaison' ?>"></div>
    </details>
    <div><button class="knopf haupt">Beiträge schreiben lassen</button></div>
  </form>
  <ol class="mk-start__schritte">
    <li>Claude schreibt die Mischung: Beiträge, ein Karussell, einen Telegram-Beitrag, eine Meta- und eine Google-Anzeige mit Suchbegriffen und Ausschlüssen (etwa 5–15 Minuten über dein Claude-Abo).</li>
    <li>Kie.ai macht die Bilder — dein PC prüft vorher das Guthaben.</li>
    <li>Unter <a href="<?= Fmt::h(url('freigabe') . '?land=' . $z['land']) ?>">Freigeben</a> gehst du Stück für Stück durch: Ja plant es auf den nächsten freien Abend, Nein verwirft.</li>
    <li>Jeder Klick, Website-Check und Lead landet bei den Kampagnen dieser Zielgruppe — unten.</li>
  </ol>
  <?php endif; ?>
</section>
<?php endif; ?>

<div class="block">
  <h2>Kurz gesagt</h2>
  <p style="margin:0;max-width:75ch;line-height:1.65"><?= Fmt::h((string) ($p['kurz'] ?? '')) ?></p>
  <?php if (!empty($p['ansprache'])): ?><p style="margin:12px 0 0;max-width:75ch;line-height:1.65"><b>Ansprache:</b> <?= Fmt::h((string) $p['ansprache']) ?></p><?php endif; ?>
</div>
<?php $kw = (array) ($p['kundenweg'] ?? []); if (isset(MkZielgruppe::KUNDENWEGE[(string) ($kw['weg'] ?? '')])): [$kwName, $kwText] = MkZielgruppe::KUNDENWEGE[$kw['weg']]; ?>
<div class="block" id="kundenweg" style="border-color:rgba(241,211,139,.45)">
  <h2>Weg zum Kunden <span class="mehr">aus der Recherche · danach richtet sich die Kampagne</span></h2>
  <p style="margin:0 0 6px;font-size:17px"><b><?= Fmt::h($kwName) ?></b><?= ($kw['stichwort'] ?? '') !== '' && $kw['weg'] === 'kommentar' ? ' · Stichwort <b>' . Fmt::h((string) $kw['stichwort']) . '</b>' : '' ?></p>
  <p class="mk-fein" style="margin:0 0 8px"><?= Fmt::h($kwText) ?></p>
  <?php if (($kw['angebot'] ?? '') !== ''): ?><p style="margin:0 0 6px;max-width:75ch;line-height:1.6"><b>Was der Betrieb bekommt:</b> <?= Fmt::h((string) $kw['angebot']) ?></p><?php endif; ?>
  <?php if (($kw['warum'] ?? '') !== ''): ?><p style="margin:0 0 6px;max-width:75ch;line-height:1.6"><b>Warum:</b> <?= Fmt::h((string) $kw['warum']) ?></p><?php endif; ?>
  <?php if (isset(MkZielgruppe::KUNDENWEGE[(string) ($kw['zweiter'] ?? '')])): ?><p class="mk-fein" style="margin:0">Zweitbester Weg: <?= Fmt::h(MkZielgruppe::KUNDENWEGE[$kw['zweiter']][0]) ?></p><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($nutzbar): require_once dirname(__DIR__) . '/src/MkSeite.php';
  $se = isset($mkSeite) && is_array($mkSeite) ? $mkSeite : null; $sa = isset($mkSeiteAuftrag) && is_array($mkSeiteAuftrag) ? $mkSeiteAuftrag : null;
  $seC = $se ? json_decode((string) ($se['entwurf'] ?: $se['inhalt']), true) : null;
  $seLive = MkSeite::pfad($se);
  $seBasis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
  $seForm = static function (string $tat, string $text, bool $haupt = false) use ($z, $se): void { ?>
    <form method="post" action="<?= Fmt::h(url('zielgruppen/' . (int) $z['id'])) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="<?= Fmt::h($tat) ?>"><input type="hidden" name="zielgruppe" value="<?= (int) $z['id'] ?>"><input type="hidden" name="id" value="<?= (int) ($se['id'] ?? 0) ?>"><button class="knopf<?= $haupt ? ' haupt' : '' ?>"><?= Fmt::h($text) ?></button></form>
  <?php }; ?>
<div class="block" id="seite">
  <h2>Eigene Landingpage <span class="mehr">eine Seite nur für diese Zielgruppe, in ihrer Sprache · Beiträge führen dorthin</span></h2>
  <?php if ($se === null): ?>
    <p style="margin:0 0 12px;max-width:72ch;line-height:1.6">Claude schreibt aus diesem Profil eine Seite, die genau diese Betriebe anspricht — ihre Probleme, ihre Einwände, ihre Fragen — <?= $istIt ? 'auf Italienisch, mit deutscher Fassung zum Lesen' : 'auf Deutsch' ?>. Online geht sie erst mit deinem Ja.</p>
  <?php else: ?>
    <p style="margin:0 0 10px">
      <?php if ($seLive): ?><span class="marke2 gut">online</span> <a href="<?= Fmt::h($seBasis . $seLive) ?>" target="_blank" rel="noopener"><?= Fmt::h($seBasis . $seLive) ?></a> · <?= number_format((int) $se['aufrufe'], 0, ',', '.') ?> Aufrufe<?php else: ?><span class="marke2 warnung"><?= $se['status'] === 'aus' ? 'offline' : 'noch nicht online' ?></span><?php endif; ?>
      <?php if ($se['entwurf']): ?> · <b>neuer Entwurf wartet auf dein Ja</b><?php endif; ?>
    </p>
    <?php if (is_array($seC)): ?>
      <div style="border:1px solid var(--linie);border-radius:10px;padding:12px 14px;margin:0 0 12px;max-width:80ch">
        <div class="mk-fein"><?= Fmt::h((string) ($seC['kicker'] ?? '')) ?></div>
        <div style="font-size:19px;font-weight:700;margin:2px 0 6px"><?= Fmt::h((string) ($seC['h1'] ?? '')) ?></div>
        <div style="line-height:1.55"><?= Fmt::h((string) ($seC['lead'] ?? '')) ?></div>
        <div class="mk-fein" style="margin-top:6px">Abschnitte: <?= Fmt::h(implode(' · ', array_map(static fn($a) => (string) ($a['h2'] ?? ''), (array) ($seC['abschnitte'] ?? [])))) ?> · <?= count((array) ($seC['faq'] ?? [])) ?> Fragen</div>
      </div>
      <?php if (!empty($se['lesen_de'])): ?><details style="margin:0 0 12px;max-width:80ch"><summary>Deutsche Fassung zum Lesen</summary><div style="white-space:pre-line;line-height:1.6;margin-top:8px"><?= Fmt::h((string) $se['lesen_de']) ?></div></details><?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($sa && in_array($sa['status'], ['wartet', 'laeuft'], true)): ?>
    <p class="mk-fein" style="margin:0 0 10px"><?= $sa['status'] === 'laeuft' ? 'Claude schreibt gerade …' : 'Wartet auf deinen PC (fragt alle 5 Minuten nach).' ?></p>
  <?php elseif ($sa && $sa['status'] === 'fehler'): ?>
    <p class="mk-fein" style="margin:0 0 10px;color:var(--warnung,#c96)">Letzter Versuch nicht geklappt: <?= Fmt::h((string) $sa['ergebnis']) ?></p>
  <?php endif; ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php if ($se && ($se['entwurf'] || $se['status'] !== 'freigegeben')): ?>
      <a class="knopf" href="<?= Fmt::h(url('seite-vorschau/' . (int) $se['id'])) ?>" target="_blank" rel="noopener">Vorschau ansehen ↗</a>
      <?php $seForm('seite_freigeben', 'Online stellen', true); ?>
      <?php if ($se['entwurf']) { $seForm('seite_verwerfen', $se['inhalt'] ? 'Entwurf verwerfen, alte Fassung behalten' : 'Entwurf verwerfen'); } ?>
    <?php endif; ?>
    <?php if (!($sa && in_array($sa['status'], ['wartet', 'laeuft'], true))) { $seForm('seite_schreiben', $se ? 'Neu schreiben lassen' : 'Seite schreiben lassen', $se === null); } ?>
    <?php if ($seLive) { $seForm('seite_offline', 'Offline nehmen'); } ?>
  </div>
</div>
<?php endif; ?>

<div class="block">
  <h2>Datengrundlage <span class="mehr">selbst gemessen, nicht geschätzt</span></h2>
  <div class="kacheln" style="margin:0 0 12px">
    <div class="kachel"><span>Betriebe in der Akquise</span><b><?= $n($daten['firmen']) ?></b></div>
    <div class="kachel"><span>Websites geprüft</span><b><?= $n($daten['geprueft']) ?></b></div>
    <div class="kachel"><span>ohne Website</span><b><?= $n($daten['ohne_website']) ?></b></div>
    <div class="kachel"><span>Ø Handlungsbedarf (Score)</span><b><?= $daten['score'] !== null ? (int) $daten['score'] : '—' ?></b><span class="leise">0 gut · 100 dringend</span></div>
    <div class="kachel"><span>Kampagnen (12 Monate)</span><b><?= $n((int) $daten['kampagnen']['anzahl']) ?></b><span class="leise"><?= $n((int) $daten['kampagnen']['leads']) ?> Leads · <?= $n((int) $daten['kampagnen']['kunden']) ?> Kunden</span></div>
  </div>
  <?php if ($daten['befunde']): ?>
    <h3 class="leise" style="margin:6px 0 8px;font-size:12px">Häufigste belegte Befunde auf ihren Websites</h3>
    <div class="balkenliste">
      <?php foreach ($daten['befunde'] as $b): ?>
        <div class="bl__zeile" title="<?= Fmt::h($b['titel'] . ': ' . $b['n'] . ' Betriebe') ?>"><span class="bl__wort"><?= Fmt::h($b['titel']) ?></span><span class="bl__spur"><i style="width:<?= min(100, round($b['anteil'])) ?>%"></i></span><b class="bl__zahl"><?= number_format($b['anteil'], 0, ',', '.') ?> %</b></div>
      <?php endforeach; ?>
    </div>
  <?php else: ?><p class="leise" style="margin:0">Noch keine geprüften Websites dieser Branche.</p><?php endif; ?>
</div>

<div class="mk-zwei">
  <?php foreach (MkZielgruppe::LISTEN as $k => [$ueberschrift]): ?>
    <div class="block"><h2><?= Fmt::h($ueberschrift) ?><?= $istIt && in_array($k, MkZielgruppe::UEBERSETZT, true) ? ' <span class="mehr">italienisch · deutsch darunter</span>' : '' ?></h2><?php $liste((array) ($p[$k] ?? []), (array) ($de[$k] ?? [])); ?></div>
  <?php endforeach; ?>
</div>

<div class="block">
  <h2>Bezahlte Werbung</h2>
  <?php $bz = (array) ($p['bezahlt'] ?? []); ?>
  <?php foreach (MkZielgruppe::BEZAHLT as $bk => $bw): if (empty($bz[$bk])) { continue; } ?>
    <p style="margin:0 0 10px;max-width:80ch;line-height:1.6"><b><?= Fmt::h($bw) ?>:</b> <?= Fmt::h(is_array($bz[$bk]) ? implode(' · ', $bz[$bk]) : (string) $bz[$bk]) ?>
      <?php if ($bk === 'keywords' && !empty($de['keywords'])): ?><span class="mk-de"><?= Fmt::h(implode(' · ', array_filter(array_map('strval', (array) $de['keywords'])))) ?></span><?php endif; ?></p>
  <?php endforeach; ?>
</div>

<div class="block">
  <h2>Quellen <span class="mehr"><?= count((array) ($p['quellen'] ?? [])) ?></span></h2>
  <ol style="margin:0;padding-left:20px;line-height:1.7;font-size:14px">
    <?php foreach ((array) ($p['quellen'] ?? []) as $q): ?>
      <li><a href="<?= Fmt::h((string) $q['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= Fmt::h((string) $q['titel']) ?></a><?= !empty($q['datum']) ? ' <span class="leise" style="display:inline">· ' . Fmt::h((string) $q['datum']) . '</span>' : '' ?></li>
    <?php endforeach; ?>
  </ol>
</div>

<div class="block">
  <h2>Kampagnen dieser Zielgruppe <span class="mehr"><?= count($kampagnen) ?> · <?= $inhalteZahl ?> <?= $inhalteZahl === 1 ? 'Inhalt' : 'Inhalte' ?></span></h2>
  <?php if ($kampagnen): ?>
    <p class="mk-zahlen" aria-label="Letzte 30 Tage"><span><b><?= (int) $zahlen['klicks'] ?></b>Klicks</span><span><b><?= (int) $zahlen['checks'] ?></b>Website-Checks</span><span><b><?= (int) $zahlen['leads'] ?></b>Leads</span><span><b><?= (int) $zahlen['kunden'] ?></b>Kunden</span><span class="mk-fein">letzte 30 Tage</span></p>
    <ul class="mk-liste">
      <?php foreach ($kampagnen as $k): ?><li><a href="<?= Fmt::h(url('kampagnen/' . (int) $k['id'])) ?>"><?= Fmt::h((string) $k['name']) ?></a> <span class="mk-fein">· <?= Fmt::h(MkKampagne::STATUS[$k['status']] ?? $k['status']) ?> · /k/<?= Fmt::h((string) $k['code']) ?></span></li><?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="leise" style="margin:0;max-width:70ch;line-height:1.6">Noch keine. Sobald du einen Inhalt dieser Zielgruppe freigibst, entsteht die Kampagne dazu von selbst — mit eigenem Link, damit jeder Klick und jeder Lead hier ankommt.</p>
  <?php endif; ?>
</div>

<section id="funde" aria-labelledby="mk-zg-funde">
  <h2 id="mk-zg-funde" style="font-size:17px;margin:6px 0 10px">Recherche zu dieser Zielgruppe <span class="mehr" style="font-weight:400;color:var(--leise);font-size:13px"><?= count($funde) ?> · Claude nutzt gemerkte Funde zuerst</span></h2>
  <?php if ($funde): ?>
    <?php $fundeZurueck = 'zielgruppen/' . (int) $z['id']; require __DIR__ . '/mk_funde.php'; ?>
  <?php else: ?>
    <div class="block"><p class="leise" style="margin:0">Noch keine Funde zu dieser Branche in <?= Fmt::h(MkLand::name((string) $z['land'])) ?>. „Neu recherchieren“ bringt welche mit.</p></div>
  <?php endif; ?>
</section>
