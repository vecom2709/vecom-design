<?php
/**
 * Marketing · Inhalte (Content-Studio, Marketing-Studio Schritt 2 — 01.10.2026).
 * Erwartet: $f (Filter), $liste (MkInhalt::liste), $zahl (je Status), $zielgruppen (freigegebene),
 *           $alleZg, $kampagnen, $auftraege (MkAuftrag::liste(…, 'inhalte')), $pc (AkquiseSteuerung::stand).
 * Marketing-Studio 5: $land, $offen (MkLand::offen), $ohneDeutsch — je Land getrennt, Italienisches mit
 * deutscher Fassung zum Lesen.
 */
require_once dirname(__DIR__) . '/src/MkAuftrag.php';
require_once dirname(__DIR__) . '/src/MkLand.php';
$land = $land ?? 'IT';
$offen = $offen ?? MkLand::offen();
$ohneDeutsch = (int) ($ohneDeutsch ?? 0);
$branchen = MkKampagne::branchen();
$auftraege = $auftraege ?? [];
$pc = $pc ?? ['pc_wach' => false, 'pc_alter' => null];
$kurz = static function (array $x): string {
    $f = $x['f'];
    $t = match ($x['format']) {
        'meta_anzeige' => (string) ($f['primaertexte'][0] ?? ''),
        'google_anzeige' => implode(' · ', array_slice((array) ($f['ueberschriften'] ?? []), 0, 3)),
        'karussell', 'story' => implode(' · ', array_map(static fn($fo) => (string) $fo['titel'], array_slice((array) ($f['folien'] ?? []), 0, 3))),
        default => (string) (($f['hook'] ?? '') !== '' ? $f['hook'] : ($f['text'] ?? '')),
    };
    return mb_strlen($t) > 170 ? mb_substr($t, 0, 168) . '…' : $t;
};
$filterLink = static fn(array $mehr) => url('inhalte') . '?' . http_build_query(array_filter($mehr + $f));
$deKurz = static function (array $x): string {
    $t = trim(preg_replace('/\s+/u', ' ', (string) ($x['uebersetzung'] ?? '')) ?? '');
    return mb_strlen($t) > 140 ? mb_substr($t, 0, 138) . '…' : $t;
};
require __DIR__ . '/mk_stil.php';
?>
<div class="mk-kopf">
  <div>
    <h1>Inhalte</h1>
    <div class="weg">organisch und bezahlt · von Claude aus einer freigegebenen Zielgruppe geschrieben · du prüfst, änderst und gibst frei — jedes Stück bekommt seinen eigenen Link</div>
  </div>
</div>

<?php $mkLand = $land; $mkLandSeite = 'inhalte'; $mkLandOffen = $offen; /* Länderschalter steht seit M1 (01.10.2026) oben im Gerüst für alle Marketing-Seiten. */ ?>

<?php /* Verpasst — nachposten (03.10.2026, Uwe: „verpasste Beiträge und Entwürfe … nachträglich zu einem anderen Zeitpunkt nachposten“) */
  require_once dirname(__DIR__) . '/src/MkNachposten.php';
  try { $npListe = MkNachposten::verpasst($land); } catch (Throwable $npE) { $npListe = []; }   /* die Ansicht wird auch ohne index.php gerendert (Kette) */
  $npPl = static fn(string $k): string => trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$k] ?? $k)) ?? '');
  $npFrei = count(array_filter($npListe, static fn($v) => $v['grund']['k'] !== 'entwurf'));
  if ($npListe): ?>
<details class="block np" id="nachposten" open>
  <summary style="cursor:pointer"><b>Verpasst — nachposten (<?= count($npListe) ?>)</b> <span class="mk-fein">— hatte seinen Sendeplatz und ist nicht draußen. Auf den nächsten freien Platz legen oder einen Zeitpunkt wählen; zur Zeit geht es automatisch raus bzw. aufs Handy.</span></summary>
  <?php if ($npFrei > 1): ?>
    <form method="post" action="<?= Fmt::h(url('inhalte')) ?>" style="margin:10px 0 4px">
      <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="nachposten_alle"><input type="hidden" name="land" value="<?= Fmt::h($land) ?>">
      <button class="knopf haupt">Alle <?= $npFrei ?> freigegebenen auf die nächsten freien Plätze verteilen</button> <span class="mk-fein">Entwürfe nicht — die brauchen jeder dein eigenes Ja.</span>
    </form>
  <?php endif; ?>
  <ul class="np-liste">
    <?php foreach ($npListe as $npV): $npX = $npV['x']; $npE = $npV['grund']['k'] === 'entwurf'; ?>
      <li>
        <div class="np-was"><a href="<?= Fmt::h(url('inhalte/' . (int) $npX['id'])) ?>"><b><?= Fmt::h((string) $npX['titel']) ?></b></a>
          <span class="marke2"><?= Fmt::h($npPl((string) $npX['plattform'])) ?></span><?php if ($npE): ?> <span class="marke2 warnung">Entwurf</span><?php endif; ?>
          <span class="mk-fein"><?= Fmt::h($npV['grund']['satz']) ?></span></div>
        <form method="post" action="<?= Fmt::h(url('inhalte')) ?>" class="np-tat">
          <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="nachposten"><input type="hidden" name="id" value="<?= (int) $npX['id'] ?>">
          <button class="knopf" name="wann" value=""><?= $npE ? 'Freigeben und auf den nächsten Platz' : 'Nächster freier Platz' ?></button>
          <label class="mk-sr" for="np_w<?= (int) $npX['id'] ?>">Zeitpunkt</label>
          <input id="np_w<?= (int) $npX['id'] ?>" type="datetime-local" name="wann_frei" min="<?= date('Y-m-d\TH:i', time() + 300) ?>" max="<?= date('Y-m-d\TH:i', time() + 60 * 86400) ?>" style="width:auto">
          <button class="knopf" name="mit_zeit" value="1"><?= $npE ? 'Freigeben und zu dieser Zeit' : 'Zu dieser Zeit' ?></button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</details>
<style>
  .np{border-color:rgba(232,182,76,.5)}
  .np-liste{list-style:none;margin:10px 0 0;padding:0;display:grid;gap:10px}
  .np-liste li{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between;border-top:1px solid var(--linie);padding-top:10px}
  .np-was{display:flex;flex-direction:column;gap:3px;min-width:0;flex:1 1 320px}
  .np-was .marke2{align-self:flex-start}
  .np-tat{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:0}
</style>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/src/MkMedium.php'; ?>
<details class="block mk-auftrag mk-mehr" id="perprompt"<?= !empty($_GET['prompt']) ? ' open' : '' ?>><summary style="cursor:pointer"><b>Bild oder Video per Prompt</b> <span class="mk-fein">— einfach beschreiben, was zu sehen sein soll. Es entsteht über Kie.ai und landet als Entwurf „Per Prompt“ hier in der Liste.</span></summary>
  <form method="post" action="<?= Fmt::h(url('inhalte')) ?>" class="mk-formular" style="margin-top:10px">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="medium_frei">
    <div class="feld breit"><label for="pp_text">Was soll zu sehen sein? <span class="mk-fein">(Deutsch oder Englisch, je genauer desto besser: Ort, Licht, Stimmung, Bildausschnitt)</span></label>
      <textarea id="pp_text" name="eigen" rows="4" maxlength="<?= MkMedium::EIGEN_MAX ?>" required placeholder="z. B. Eine Friseurin in Agrigent zeigt einer Kundin auf dem Handy ihre neue Website, helles Tageslicht, Salon im Hintergrund unscharf"></textarea></div>
    <div class="feld"><label for="pp_art">Was</label><select id="pp_art" name="medium" onchange="var v=this.value==='video';document.getElementById('pp_mb').hidden=v;document.getElementById('pp_mv').hidden=!v;document.getElementById('pp_fb').hidden=v;document.getElementById('pp_fv').hidden=!v;document.getElementById('pp_mb').disabled=v;document.getElementById('pp_mv').disabled=!v;document.getElementById('pp_fb').disabled=v;document.getElementById('pp_fv').disabled=!v;"><option value="bild">Bild</option><option value="video">Video</option></select></div>
    <div class="feld"><label for="pp_mb">Modell</label>
      <select id="pp_mb" name="modell"><?php foreach (MkMedium::MODELLE['bild'] as $ppK => [$ppW, $ppC]): if (MkMedium::istDreiD($ppK)) { continue; } ?><option value="<?= Fmt::h($ppK) ?>"><?= Fmt::h($ppW) ?> · ca. <?= (int) $ppC ?> Credits</option><?php endforeach; ?></select>
      <select id="pp_mv" name="modell" hidden disabled aria-label="Videomodell"><?php foreach (MkMedium::MODELLE['video'] as $ppK => [$ppW, $ppC]): if (MkMedium::istDreiD($ppK)) { continue; } ?><option value="<?= Fmt::h($ppK) ?>"><?= Fmt::h($ppW) ?> · ca. <?= (int) $ppC ?> Credits</option><?php endforeach; ?></select></div>
    <div class="feld"><label for="pp_fb">Format</label>
      <select id="pp_fb" name="format"><?php foreach (MkMedium::FORMATE['bild'] as $ppF): ?><option value="<?= $ppF ?>"><?= $ppF ?></option><?php endforeach; ?></select>
      <select id="pp_fv" name="format" hidden disabled aria-label="Videoformat"><?php foreach (MkMedium::FORMATE['video'] as $ppF): ?><option value="<?= $ppF ?>"><?= $ppF ?></option><?php endforeach; ?></select></div>
    <input type="hidden" name="land" value="<?= Fmt::h((string) ($land ?? 'IT')) ?>">
    <div class="breit"><button class="knopf haupt">Erzeugen</button> <span class="mk-fein">Vor jedem Lauf prüft dein PC das Kie-Guthaben. Höchstens <?= (int) MkMedium::PRO_TAG ?> Bilder/Videos am Tag.</span></div>
  </form>
</details>
<details class="block mk-auftrag mk-mehr" id="auftraege"><summary style="cursor:pointer"><b>Mehr: Beiträge frei zusammenstellen</b> <span class="mk-fein">— Plattformen, Anzahl und Thema selbst wählen. Für den Alltag reicht „Diese Woche werben“ unter „Jetzt dran“.</span></summary>
<section aria-labelledby="mk-schreib-titel" style="margin-top:12px">
  <div class="mk-auftrag__kopf">
    <h2 id="mk-schreib-titel">Claude Inhalte schreiben lassen</h2>
    <span class="mk-ampel <?= $pc['pc_wach'] ? 'gruen' : '' ?>"><i></i><?= $pc['pc_wach'] ? 'Dein PC ist an — holt Aufträge alle 5 Minuten ab' : ($pc['pc_alter'] === null ? 'Dein PC hat sich noch nie gemeldet' : 'Dein PC ist aus oder schläft — der Auftrag wartet, bis er wieder an ist') ?></span>
  </div>
  <?php if (!$zielgruppen): ?>
    <p style="margin:0;max-width:75ch;line-height:1.6">Noch keine freigegebene Zielgruppe in <?= Fmt::h(MkLand::name($land)) ?>. Claude schreibt nur für Zielgruppen, die du geprüft hast: unter <a href="<?= Fmt::h(url('zielgruppen') . '?land=' . $land) ?>">Zielgruppen &amp; Recherche</a> eine recherchieren lassen, öffnen und „Zielgruppe freigeben“ drücken.</p>
  <?php else: ?>
  <form class="mk-formular mk-schreiben" method="post" action="<?= Fmt::h(url('inhalte')) ?>">
    <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="inhalte_erstellen">
    <div class="feld"><label for="mi_zg">Für wen</label>
      <select id="mi_zg" name="zielgruppe"><?php foreach ($zielgruppen as $z): ?><option value="<?= (int) $z['id'] ?>"<?= (int) $f['zielgruppe'] === (int) $z['id'] ? ' selected' : '' ?>><?= Fmt::h(($branchen[$z['branche']] ?? $z['branche']) . ' · ' . $z['land'] . ' — ' . $z['titel']) ?></option><?php endforeach; ?></select></div>
    <div class="feld"><label for="mi_umfang">Was</label>
      <select id="mi_umfang" name="umfang"><option value="beides">Organisch und Anzeigen</option><option value="organisch">Nur organische Beiträge</option><option value="bezahlt">Nur Anzeigen</option></select></div>
    <div class="feld"><label for="mi_anzahl">Wie viele</label>
      <select id="mi_anzahl" name="anzahl"><?php foreach ([4, 6, 8, 12] as $n): ?><option value="<?= $n ?>"<?= $n === 6 ? ' selected' : '' ?>><?= $n ?> Stück</option><?php endforeach; ?></select></div>
    <div class="feld"><label for="mi_kampagne">In welche Kampagne</label>
      <select id="mi_kampagne" name="kampagne"><option value="0">je Plattform eine eigene (automatisch)</option><?php foreach ($kampagnen as $k): ?><option value="<?= (int) $k['id'] ?>"><?= Fmt::h($k['name']) ?></option><?php endforeach; ?></select></div>
    <fieldset class="feld breit mk-plattformen"><legend>Wo</legend>
      <?php foreach (MkInhalt::PLATTFORMEN as $pk): ?><label class="mk-haken"><input type="checkbox" name="plattformen[]" value="<?= $pk ?>"<?= in_array($pk, ['instagram', 'facebook', 'google'], true) ? ' checked' : '' ?>> <?= Fmt::h(MkKampagne::PLATTFORMEN[$pk] ?? $pk) ?></label><?php endforeach; ?>
    </fieldset>
    <div class="feld breit"><label for="mi_thema">Thema <span class="mk-fein">(freiwillig)</span></label>
      <input id="mi_thema" name="thema" maxlength="200" placeholder="z. B. Airbnb-Gebühr ab Mitte Oktober · Nebensaison · kostenloser Website-Check"></div>
    <div class="breit"><button class="knopf haupt">Inhalte schreiben lassen</button></div>
  </form>
  <p class="mk-fein" style="margin:8px 0 0;max-width:90ch;line-height:1.55">Claude nutzt das freigegebene Profil, deine Recherche-Funde (gemerkte zuerst) und die Grenzen jeder Plattform. Dauer etwa 5–15 Minuten, über dein Claude-Abo. Alles kommt als Entwurf; erst bei der Freigabe entsteht der eigene Link. Höchstens <?= MkAuftrag::PRO_TAG ?> Schreibaufträge am Tag.</p>
  <?php endif; ?>
  <?php if ($ohneDeutsch > 0): ?>
    <div class="mk-hinweis-zeile" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--linie)">
      <span style="font-size:14px"><?= $ohneDeutsch ?> italienische <?= $ohneDeutsch === 1 ? 'Inhalt hat' : 'Inhalte haben' ?> noch keine deutsche Fassung zum Lesen (aus der Zeit vor dem Umbau). Neue bringen sie gleich mit.</span>
      <form method="post" action="<?= Fmt::h(url('inhalte')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="uebersetzen_starten"><input type="hidden" name="zurueck" value="inhalte"><button class="knopf klein">Übersetzen lassen</button></form>
    </div>
  <?php endif; ?>
  <?php $mkSeite = 'inhalte'; require __DIR__ . '/mk_auftraege.php'; ?>
</section>
</details>

<?php if (!empty($vorherNachher)): /* Marketing-Studio 9 */ ?>
<section class="block mk-start" id="vorher-nachher" aria-labelledby="mk-vn-titel">
  <h2 id="mk-vn-titel">Vorher/Nachher aus fertigen Projekten <span class="mehr">der Kunde hat zugestimmt · ein Klick: drei Entwürfe mit Bild</span></h2>
  <ul class="mk-zeilen">
    <?php foreach ($vorherNachher as $vn): ?>
      <li><span><b><?= Fmt::h((string) ($vn['company'] ?: $vn['name'])) ?></b> <span class="mk-fein">· <?= Fmt::h((string) $vn['domain']) ?> · zugestimmt am <?= Fmt::h(date('d.m.Y', strtotime((string) $vn['referenz_am']))) ?></span></span>
        <form method="post" action="<?= Fmt::h(url('inhalte')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="vorher_nachher_erstellen"><input type="hidden" name="kunde" value="<?= (int) $vn['id'] ?>"><button class="knopf klein haupt">Beitrag erstellen</button></form></li>
    <?php endforeach; ?>
  </ul>
  <p class="mk-fein" style="margin:8px 0 0">Instagram, Facebook und Telegram in der Sprache des Kunden (mit deutscher Fassung). Dein PC fotografiert die neue Website und nimmt als „Vorher“ das Bild aus dem Website-Check der Akquise, falls es eins gibt. Freigeben wie immer.</p>
</section>
<?php endif; ?>

<nav class="mk-chips" aria-label="Nach Stand" style="margin:0 0 12px">
  <a href="<?= Fmt::h($filterLink(['status' => ''])) ?>"<?= $f['status'] === '' ? ' aria-current="page"' : '' ?>>Offen (<?= (int) $zahl['entwurf'] + (int) $zahl['freigegeben'] ?>)</a>
  <?php foreach (MkInhalt::STATUS as $sk => $sw): ?><a href="<?= Fmt::h($filterLink(['status' => $sk])) ?>"<?= $f['status'] === $sk ? ' aria-current="page"' : '' ?>><?= Fmt::h($sw) ?> (<?= (int) ($zahl[$sk] ?? 0) ?>)</a><?php endforeach; ?>
</nav>
<form class="mk-filter" method="get" action="<?= Fmt::h(url('inhalte')) ?>" aria-label="Inhalte filtern">
  <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= Fmt::h($f['status']) ?>"><?php endif; ?>
  <select name="plattform" aria-label="Plattform" style="width:auto"><option value="">Alle Plattformen</option><?php foreach (MkInhalt::PLATTFORMEN as $pk): ?><option value="<?= $pk ?>"<?= $f['plattform'] === $pk ? ' selected' : '' ?>><?= Fmt::h(MkKampagne::PLATTFORMEN[$pk] ?? $pk) ?></option><?php endforeach; ?></select>
  <select name="art" aria-label="Organisch oder bezahlt" style="width:auto"><option value="">Organisch und bezahlt</option><?php foreach (MkInhalt::ARTEN as $ak => $aw): ?><option value="<?= $ak ?>"<?= $f['art'] === $ak ? ' selected' : '' ?>><?= Fmt::h($aw) ?></option><?php endforeach; ?></select>
  <select name="zielgruppe" aria-label="Zielgruppe" style="width:auto;max-width:100%"><option value="0">Alle Zielgruppen</option><?php foreach ($alleZg as $z): ?><option value="<?= (int) $z['id'] ?>"<?= (int) $f['zielgruppe'] === (int) $z['id'] ? ' selected' : '' ?>><?= Fmt::h($z['titel']) ?></option><?php endforeach; ?></select>
  <button class="knopf">Filtern</button>
</form>

<?php if (!$liste): ?>
  <div class="block"><p style="margin:0;max-width:64ch;line-height:1.6">Noch keine Inhalte in <?= Fmt::h(MkLand::name($land)) ?><?= array_filter(array_diff_key($f, ['land' => 1])) ? ' für diesen Filter' : '' ?>. Oben eine freigegebene Zielgruppe wählen und „Inhalte schreiben lassen“ drücken.</p></div>
<?php else: ?>
  <div class="mk-inhalte">
    <?php foreach ($liste as $x): ?>
      <a class="mk-inhalt-karte" href="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>">
        <span class="mk-inhalt-karte__marken">
          <span class="marke2"><?= Fmt::h(MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform']) ?></span>
          <span class="marke2 <?= $x['art'] === 'bezahlt' ? 'warnung' : '' ?>"><?= Fmt::h(MkInhalt::ARTEN[$x['art']] ?? $x['art']) ?></span>
          <?= MkInhalt::spracheMarke((string) $x['sprache']) ?>
          <span class="mk-fein"><?= Fmt::h(MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) ?></span>
        </span>
        <b class="mk-inhalt-karte__titel"><?= Fmt::h($x['titel']) ?></b>
        <span class="mk-inhalt-karte__text"><?= Fmt::h($kurz($x)) ?></span>
        <?php if ($x['sprache'] !== 'de' && $deKurz($x) !== ''): ?><span class="mk-de">🇩🇪 Auf Deutsch: <?= Fmt::h($deKurz($x)) ?></span><?php endif; ?>
        <span class="mk-inhalt-karte__fuss">
          <span class="marke2 <?= ['entwurf' => 'warnung', 'freigegeben' => 'gut', 'veroeffentlicht' => 'gut', 'verworfen' => ''][$x['status']] ?? '' ?>"><?= Fmt::h(MkInhalt::STATUS[$x['status']] ?? $x['status']) ?></span>
          <span class="mk-fein"><?= Fmt::h(date('d.m.Y', strtotime((string) $x['created_at']))) ?><?= $x['creative_id'] ? ' · eigener Link' : '' ?><?= !empty($x['geplant_am']) && $x['status'] === 'freigegeben' ? ' · geplant ' . Fmt::h(date('d.m. H:i', strtotime((string) $x['geplant_am']))) : '' ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
