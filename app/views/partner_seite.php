<?php
/* ==========================================================================
   Gestalter der Empfehlungsseite im Partner-Dashboard (26.09.2026).
   Eingebunden aus partner.php ($p, $sprache, $T, $h, $selbst, $meldung).
   Alle Wahlen sind Schlüssel aus PartnerSeite -- das Formular kann nichts
   anderes schicken, als dort steht (und der Server prüft es trotzdem).
   ========================================================================== */
$gs = PartnerSeite::gestaltung($p);
$PS = Texte::PARTNER_SEITE;
$W = static fn(array $t): string => Texte::h($t, $sprache);
$gFehler = in_array($meldung, ['text_link', 'wa_nummer', 'bild_gross', 'bild_art', 'gruss_gross', 'gruss_art'], true) ? $meldung : '';
$grussJetzt = PartnerSeite::grussAdresse($p);
$vorschau = '/p.php?' . http_build_query(['c' => $p['code'], 'lang' => $sprache, 'n' => 1, 'v' => substr(md5((string) ($p['seite_am'] ?? '') . (string) ($p['foto_am'] ?? '')), 0, 6)]);
$eigenesBild = !empty($p['seite_bild_am']) ? '/p.php?' . http_build_query(['titel' => $p['code'], 'v' => substr(md5((string) $p['seite_bild_am']), 0, 8)]) : null;
$daumen = static fn(string $datei): string => '/assets/img/' . (str_contains($datei, 'haar/') ? $datei : preg_replace('~\.webp$~', '-800.webp', $datei));
?>
<style>
  .gs-wahl{display:flex;gap:8px;flex-wrap:wrap}
  .gs-wahl label{display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;font-size:12.5px;color:var(--dim);text-align:center}
  .gs-wahl input{position:absolute;opacity:0;width:1px;height:1px}
  .gs-muster{width:74px;height:48px;border-radius:10px;border:2px solid var(--linie2);display:grid;grid-template-rows:1fr 10px;overflow:hidden}
  .gs-muster i{display:block}
  .gs-farbe{width:34px;height:34px;border-radius:50%;border:2px solid var(--linie2)}
  .gs-bild{width:110px;aspect-ratio:16/9;border-radius:10px;border:2px solid var(--linie2);object-fit:cover;background:var(--flaeche2);display:grid;place-items:center;font-size:12px}
  .gs-wahl input:checked + .gs-muster,.gs-wahl input:checked + .gs-farbe,.gs-wahl input:checked + .gs-bild{border-color:var(--cyan);box-shadow:0 0 0 2px rgba(241,211,139,.35)}
  .gs-wahl input:focus-visible + *{outline:2px solid var(--cyan);outline-offset:2px}
  .gs-h{font-size:13px;color:var(--leise);text-transform:uppercase;letter-spacing:.05em;margin:16px 0 8px}
  .gs-sprachen details{border:1px solid var(--linie);border-radius:12px;padding:10px 12px;margin-bottom:8px}
  .gs-sprachen summary{cursor:pointer;font-size:14px}
  .gs-sprachen label{font-size:12.5px;color:var(--dim);display:block;margin:8px 0 4px}
  .gs-sprachen input,.gs-sprachen textarea{width:100%;font-size:15px;padding:10px 12px;box-sizing:border-box}
  .gs-haken{display:flex;gap:10px;align-items:center;font-size:14.5px;color:var(--text);margin:6px 0}
  .gs-haken input{width:auto;margin:0}
  .gs-zeile{display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid var(--linie);padding:2px 0}
  .gs-zeile select{width:auto;font-size:14px;padding:6px 10px}
  .gs-marke{font-size:11.5px;padding:2px 8px;border-radius:999px;border:1px solid var(--linie2);color:var(--leise);margin-left:6px}
  .gs-marke.an{border-color:var(--cyan);color:var(--cyan)}
  .gs-schrift{font-size:22px;line-height:1.1;color:var(--text)}
  .gs-wahl.gs-reihe label{flex-direction:row;gap:10px;border:1px solid var(--linie2);border-radius:12px;padding:10px 14px;color:var(--text);font-size:14px}
  .gs-wahl.gs-reihe label:has(input:checked){border-color:var(--cyan);box-shadow:0 0 0 2px rgba(241,211,139,.35)}
  .gs-wahl.gs-reihe label:has(input:focus-visible){outline:2px solid var(--cyan);outline-offset:2px}
  .gs-vorschlag{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:10px 0 0;padding:10px 12px;border:1px solid var(--cyan);border-radius:12px;font-size:14px}
  .gs-vorschlag .gs-muster{width:44px;height:30px}
  .gs-vorschlag button{min-height:40px}
  .gs-gruss{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
  .gs-gruss audio{width:100%;max-width:420px}
  .gs-gruss-zeit{font-variant-numeric:tabular-nums;color:var(--dim);font-size:14px}
  .gs-gruss [data-aufnahme].laeuft{border-color:#e5534b;color:#e5534b}
  /* Assistent (03.10.2026, E1–E4) */
  .ga-fertig{display:grid;gap:8px;border:1px solid var(--linie2);border-radius:12px;padding:10px 12px;margin:0 0 14px;font-size:14px}
  .ga-fertig b{display:block}
  .ga-fertig span{color:var(--dim)}
  .ga-fertig a{color:var(--cyan)}
  .ga-balken{display:block;height:6px;border-radius:3px;background:rgba(255,255,255,.08);overflow:hidden}
  .ga-balken i{display:block;height:100%;background:linear-gradient(90deg,#b98a31,#f1d38b)}
  .ga-schritte{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:0 0 12px}
  .ga-schritte button{min-height:44px;border-radius:10px;border:1px solid var(--linie2);background:none;color:var(--dim);font:inherit;font-size:13px;cursor:pointer;padding:6px}
  .ga-schritte button[aria-current="step"]{border-color:#f1d38b;color:var(--text);background:rgba(241,211,139,.08)}
  .ga-schritte button:focus-visible{outline:2px solid #f1d38b;outline-offset:2px}
  .ga-h{font-size:16px;margin:4px 0 6px}
  .js-ga .ga-h{display:none}
  .ga-looks{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px}
  .ga-look{display:flex;flex-direction:column;gap:6px;padding:0 0 8px;border:2px solid var(--linie2);border-radius:12px;background:none;color:var(--text);font:inherit;font-size:13.5px;cursor:pointer;overflow:hidden;text-align:center}
  .ga-look img{width:100%;aspect-ratio:16/9;object-fit:cover;display:block}
  .ga-look[aria-pressed="true"]{border-color:#f1d38b;box-shadow:0 0 0 2px rgba(241,211,139,.35)}
  .ga-look:focus-visible{outline:2px solid #f1d38b;outline-offset:2px}
  .ga-selbst{margin-top:12px;border-top:1px solid var(--linie);padding-top:8px}
  .ga-selbst > summary,.ga-andere > summary{cursor:pointer;color:var(--cyan);font-size:14px;min-height:40px;display:flex;align-items:center}
  .ga-eigen label{font-size:12.5px;color:var(--dim);display:block;margin:8px 0 4px}
  .ga-eigen input,.ga-eigen textarea{width:100%;font-size:15px;padding:10px 12px;box-sizing:border-box}
  .ga-andere{margin-top:10px}
  .ga-reihe .gs-zeile{gap:6px}
  .ga-reihe .gs-zeile label{flex:1 1 auto}
  .ga-reihe .ga-pfeil{min-width:40px;min-height:40px;border-radius:10px;border:1px solid var(--linie2);background:none;color:var(--text);cursor:pointer;font-size:16px;line-height:1}
  .ga-reihe .ga-pfeil:disabled{opacity:.35;cursor:default}
  .ga-unten{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
  .ga-vorschau-dlg{border:0;padding:0;background:transparent;max-width:none;max-height:none}
  .ga-vorschau-dlg::backdrop{background:rgba(5,8,24, .8)}
  .ga-vorschau-dlg .hv-rahmen{width:min(390px,92vw);height:min(760px,84vh);padding:10px}
  .ga-vorschau-dlg iframe{border:0;width:100%;height:100%;border-radius:30px;background:#080f23}
  .gs-vorschau{margin-top:16px;border:1px solid var(--linie);border-radius:16px;overflow:hidden;background:var(--flaeche2);height:560px;position:relative}
  .gs-vorschau iframe{border:0;width:390px;height:1120px;transform:scale(.5);transform-origin:0 0;position:absolute;left:calc(50% - 97.5px);top:0}
  @media (min-width:700px){.gs-vorschau iframe{transform:scale(.6);left:calc(50% - 117px)}.gs-vorschau{height:660px}}
</style>
<div class="block pt" id="seite" data-reiter="profil">
  <h2><?= $h($W($PS['g_titel'])) ?></h2>
  <?php if (($_GET['m'] ?? '') === 'g_gut'): ?><div class="hinweis gut" role="status"><?= $h($W($PS['g_gut'])) ?></div><?php endif; ?>
  <?php if ($gFehler): ?><div class="hinweis schlecht" role="alert"><?= $h($W($PS[$gFehler])) ?></div><?php endif; ?>
  <p class="klein" style="margin-top:0"><?= $h($W($PS['g_text'])) ?></p>

  <?php /* Fertig-Anzeige (03.10.2026, E3): was der Seite noch fehlt, mit Sprung dorthin. */
    $gaFehlt = array_filter([
        'f_foto' => empty($p['foto_am']) ? '#profil' : null, 'f_satz' => trim((string) ($p['profil_satz'] ?? '')) === '' ? '#profil' : null,
        'f_bild' => $gs['bild'] === '' ? '#gs_schritt1' : null, 'f_text' => empty($gs['texte'][$sprache]['titel']) && empty($gs['texte'][$sprache]['lead']) ? '#gs_schritt2' : null,
        'f_wa' => $gs['whatsapp'] === '' ? '#gs_wa' : null, 'f_gruss' => $grussJetzt === null ? '#gs_schritt2' : null]);
    $gaProzent = (int) round(100 * (6 - count($gaFehlt)) / 6); ?>
  <div class="ga-fertig" role="status">
    <div><b><?= $h($gaFehlt ? strtr($W($PS['ga_fertig']), ['{p}' => (string) $gaProzent]) : $W($PS['ga_fertig_alles'])) ?></b>
      <?php if ($gaFehlt): ?><span><?= $h($W($PS['ga_fehlt'])) ?><?php $gaI = 0; foreach ($gaFehlt as $gaK => $gaZ): ?><?= $gaI++ ? ', ' : '' ?><a href="<?= $h($gaZ) ?>"><?= $h($W($PS['ga_' . $gaK])) ?></a><?php endforeach; ?></span><?php endif; ?></div>
    <span class="ga-balken" aria-hidden="true"><i style="width:<?= $gaProzent ?>%"></i></span>
  </div>

  <form method="post" action="<?= $h($selbst()) ?>#seite" enctype="multipart/form-data" id="gs_form" data-vorschau="<?= $h($vorschau) ?>" data-live="<?= $h($W($PS['ga_vorschau_live'])) ?>" data-zu="<?= $h($W($PS['ga_schliessen'])) ?>">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="tat" value="seite">
    <input type="hidden" name="sprache_quelle" value="<?= $h($sprache) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= PartnerSeite::BILD_MAX_BYTE ?>">

    <?php /* Drei Schritte (E1). Ohne Skript stehen sie untereinander; alles bleibt ein Formular. */ ?>
    <nav class="ga-schritte" aria-label="<?= $h($W($PS['g_titel'])) ?>" hidden>
      <?php foreach ([1, 2, 3] as $gaN): ?><button type="button" data-ga="<?= $gaN ?>" aria-current="<?= $gaN === 1 ? 'step' : 'false' ?>"><?= $h($W($PS['ga_s' . $gaN])) ?></button><?php endforeach; ?>
    </nav>

    <section class="ga-schritt" id="gs_schritt1" data-schritt="1">
      <h3 class="ga-h"><?= $h($W($PS['ga_s1'])) ?></h3>
      <?php /* Ein-Klick-Looks je Branche (E2): Bild, Vorlage, Farbe, Schrift und Texte in einem Tipp. */
        $gaLooks = [];
        foreach (Texte::SEITE_BRANCHEN as $gaB => $gaBd) {
            $gaBild = array_search($gaB, PartnerSeite::BILD_BRANCHE, true);
            if ($gaBild === false || !isset(PartnerSeite::BILDER[$gaBild])) { continue; }
            [$gaV, $gaA] = PartnerSeite::BILD_FARBE[$gaBild] ?? [$gs['vorlage'], $gs['akzent']];
            $gaLooks[$gaB] = ['bild' => $gaBild, 'vorlage' => $gaV, 'akzent' => $gaA, 'schrift' => PartnerSeite::LOOK_SCHRIFT[$gaB] ?? 'modern', 'name' => Texte::h($gaBd['name'], $sprache)];
        } ?>
      <p class="gs-h" style="margin-top:4px"><?= $h($W($PS['ga_look_titel'])) ?></p>
      <p class="klein" style="margin:0 0 8px"><?= $h($W($PS['ga_look_text'])) ?></p>
      <div class="ga-looks" role="group" aria-label="<?= $h($W($PS['ga_look_titel'])) ?>">
        <?php foreach ($gaLooks as $gaB => $gaL): ?>
          <button type="button" class="ga-look" data-look="<?= $h($gaB) ?>" data-bild="<?= $h($gaL['bild']) ?>" data-vorlage="<?= $h($gaL['vorlage']) ?>" data-akzent="<?= $h($gaL['akzent']) ?>" data-schrift="<?= $h($gaL['schrift']) ?>"
                  aria-pressed="<?= $gs['bild'] === $gaL['bild'] ? 'true' : 'false' ?>"><img src="<?= $h($daumen(PartnerSeite::BILDER[$gaL['bild']])) ?>" alt="" loading="lazy"><span><?= $h($gaL['name']) ?></span></button>
        <?php endforeach; ?>
      </div>
      <div class="gs-vorschlag" id="ga_ersetzen" hidden><span><?= $h($W($PS['ga_look_ersetzen'])) ?></span><button class="knopf" type="button" data-ja><?= $h($W($PS['ga_look_ja'])) ?></button></div>

      <?php /* Bewegtes Titelbild (03.10.2026, Uwe: Ja zu B1/B3/B4) — nur, was Uwe freigegeben hat. */
        $gaKino = PartnerKopf::fuerSeite($gs); $gaKinoSzene = isset(PartnerKopf::SZENE[$gs['bild']]); ?>
      <p class="gs-h"><?= $h($W($PS['ga_kino'])) ?></p>
      <div class="gs-wahl gs-reihe" role="radiogroup" aria-label="<?= $h($W($PS['ga_kino'])) ?>">
        <?php foreach (PartnerKopf::WAHL as $gaKw): ?>
          <label><input type="radio" name="kino" value="<?= $h($gaKw) ?>" <?= $gs['kino'] === $gaKw ? 'checked' : '' ?>><?= $h($W($PS['ga_kino_' . $gaKw])) ?></label>
        <?php endforeach; ?>
      </div>
      <p class="klein" style="margin:4px 0 0"><?= $h($W($PS['ga_kino_hilfe'])) ?></p>
      <?php if ($gs['bild'] !== 'eigen' && $gaKino === null && ($gs['kino'] === 'piazza' || ($gs['kino'] === 'auto' && $gaKinoSzene))): ?><p class="klein" style="margin:4px 0 0;color:var(--leise)"><?= $h($W($PS['ga_kino_bald'])) ?></p><?php endif; ?>

      <details class="ga-selbst"<?= $gs['bild'] === 'eigen' ? ' open' : '' ?>><summary><?= $h($W($PS['ga_selbst'])) ?></summary>
    <p class="gs-h"><?= $h($W($PS['g_vorlage'])) ?></p>
    <div class="gs-wahl" role="radiogroup">
      <?php foreach (PartnerSeite::VORLAGEN as $vk => $v): $ak = PartnerSeite::AKZENTE[$v['akzent']][$v['hell'] ? 'hell' : 'dunkel']; ?>
        <label><input type="radio" name="vorlage" value="<?= $h($vk) ?>" <?= $gs['vorlage'] === $vk ? 'checked' : '' ?>>
          <span class="gs-muster" style="background:<?= $h($v['grund']) ?>"><i></i><i style="background:<?= $h($ak) ?>"></i></span><?= $h($W($PS['vorlagen'][$vk])) ?></label>
      <?php endforeach; ?>
    </div>

    <p class="gs-h"><?= $h($W($PS['g_akzent'])) ?></p>
    <div class="gs-wahl" role="radiogroup">
      <?php foreach (PartnerSeite::AKZENTE as $ak => $a): ?>
        <label><input type="radio" name="akzent" value="<?= $h($ak) ?>" <?= $gs['akzent'] === $ak ? 'checked' : '' ?>>
          <span class="gs-farbe" style="background:linear-gradient(135deg,<?= $h($a['dunkel']) ?> 50%,<?= $h($a['hell']) ?> 50%)"></span><?= $h($W($PS['akzente'][$ak])) ?></label>
      <?php endforeach; ?>
    </div>

    <p class="gs-h"><?= $h($W($PS['g_bild'])) ?></p>
    <div class="gs-wahl" role="radiogroup">
      <label><input type="radio" name="bild" value="" <?= $gs['bild'] === '' ? 'checked' : '' ?>><span class="gs-bild">—</span><?= $h($W($PS['bilder'][''])) ?></label>
      <?php if ($eigenesBild): ?>
        <label><input type="radio" name="bild" value="eigen" <?= $gs['bild'] === 'eigen' ? 'checked' : '' ?>><img class="gs-bild" src="<?= $h($eigenesBild) ?>" alt=""><?= $h($W($PS['bilder']['eigen'])) ?></label>
      <?php endif; ?>
      <?php foreach (PartnerSeite::BILDER as $bk => $datei): [$bfV, $bfA] = PartnerSeite::BILD_FARBE[$bk] ?? ['', '']; ?>
        <label><input type="radio" name="bild" value="<?= $h($bk) ?>" <?= $gs['bild'] === $bk ? 'checked' : '' ?> data-vorlage="<?= $h($bfV) ?>" data-akzent="<?= $h($bfA) ?>" data-branche="<?= $h(PartnerSeite::BILD_BRANCHE[$bk] ?? '') ?>"><img class="gs-bild" src="<?= $h($daumen($datei)) ?>" alt="" loading="lazy"><?= $h($W($PS['bilder'][$bk])) ?></label>
      <?php endforeach; ?>
    </div>
    <?php /* Farbvorschlag zum Bild (28.09.2026, Uwe: Ja zu L5) -- erscheint per Skript, ohne Skript bleibt alles wählbar. */ ?>
    <div class="gs-vorschlag" id="gs_vorschlag" hidden aria-live="polite"
         data-namen="<?= $h(json_encode(['v' => array_map($W, $PS['vorlagen']), 'a' => array_map($W, $PS['akzente'])], JSON_UNESCAPED_UNICODE)) ?>">
      <span class="gs-muster" aria-hidden="true"><i></i><i></i></span><span><?= $h($W($PS['g_vorschlag'])) ?> <b data-was></b></span>
      <button class="knopf" type="button" data-uebernehmen><?= $h($W($PS['g_uebernehmen'])) ?></button>
    </div>
    <?php /* Branchentexte (28.09.2026, Uwe: Ja zu R1) -- setzt die Texte in alle drei Sprachen, gespeichert wird erst mit „Speichern“. */ ?>
    <div class="gs-vorschlag" id="gs_branche" hidden aria-live="polite"
         data-ersetzen="<?= $h($W($PS['g_branche_ersetzen'])) ?>" data-fertig="<?= $h($W($PS['g_branche_fertig'])) ?>" data-knopf="<?= $h($W($PS['g_branche_knopf'])) ?>">
      <span><?= $h($W($PS['g_branche'])) ?> <b data-was></b></span>
      <button class="knopf" type="button" data-einsetzen><?= $h($W($PS['g_branche_knopf'])) ?></button>
    </div>
    <label for="gs_bild" style="margin-top:10px"><?= $h($W($PS['g_bild_hoch'])) ?></label>
    <input id="gs_bild" type="file" name="titelbild" accept="image/jpeg,image/png,image/webp">

    <p class="gs-h"><?= $h($W($PS['g_kopf'])) ?></p>
    <div class="gs-wahl gs-reihe" role="radiogroup">
      <?php foreach (PartnerSeite::KOEPFE as $kk): ?>
        <label><input type="radio" name="kopf" value="<?= $h($kk) ?>" <?= $gs['kopf'] === $kk ? 'checked' : '' ?>><?= $h($W($PS['koepfe'][$kk])) ?></label>
      <?php endforeach; ?>
    </div>

    <p class="gs-h"><?= $h($W($PS['g_schrift'])) ?></p>
    <div class="gs-wahl gs-reihe" role="radiogroup">
      <?php foreach (PartnerSeite::SCHRIFTEN as $sk => $sd): ?>
        <label><input type="radio" name="schrift" value="<?= $h($sk) ?>" <?= $gs['schrift'] === $sk ? 'checked' : '' ?>>
          <span class="gs-schrift" style="font-family:<?= $h($sd['familie']) ?>;font-weight:<?= (int) $sd['gewicht'] ?>">Aa</span><?= $h($W($PS['schriften'][$sk])) ?></label>
      <?php endforeach; ?>
    </div>
      </details>
    </section>

    <section class="ga-schritt" id="gs_schritt2" data-schritt="2">
      <h3 class="ga-h"><?= $h($W($PS['ga_s2'])) ?></h3>
      <?php /* Ein Text statt drei (E4): die eigene Sprache offen, die anderen übersetzt Vecom. */
        $gaSpNamen = ['it' => ['it' => 'italiano', 'de' => 'Italienisch', 'en' => 'Italian'], 'de' => ['it' => 'tedesco', 'de' => 'Deutsch', 'en' => 'German'], 'en' => ['it' => 'inglese', 'de' => 'Englisch', 'en' => 'English']];
        $gaFeld = static function (string $l) use ($gs, $p, $h, $W, $PS): string {
            $o = '';
            foreach (PartnerSeite::TEXT_MAX as $tk => $max) {
                $std = (string) ($gs['auto'][$l][$tk] ?? strtr(Texte::h(Texte::PARTNER_LANDE[$tk], $l), ['{name}' => Partner::anzeigeName($p)]));
                $wort = $tk === 'titel' ? $W($PS['g_t_titel']) : ($tk === 'lead' ? $W($PS['g_t_lead']) : strtr($W($PS['g_t_p']), ['{n}' => substr($tk, 1)]));
                $o .= '<label for="gs_' . $l . $tk . '">' . $h($wort) . ' <span style="color:var(--leise)">(max. ' . $max . ')</span></label>';
                $wert = $h((string) ($gs['texte'][$l][$tk] ?? ''));
                $o .= $tk === 'lead'
                    ? '<textarea id="gs_' . $l . $tk . '" name="texte[' . $l . '][' . $tk . ']" rows="3" maxlength="' . $max . '" placeholder="' . $h($std) . '">' . $wert . '</textarea>'
                    : '<input id="gs_' . $l . $tk . '" type="text" name="texte[' . $l . '][' . $tk . ']" maxlength="' . $max . '" placeholder="' . $h($std) . '" value="' . $wert . '">';
            }
            return $o;
        }; ?>
      <div class="gs-sprachen">
        <p class="gs-h" style="margin-top:4px"><?= $h(strtr($W($PS['ga_eigene']), ['{sprache}' => $gaSpNamen[$sprache][$sprache]])) ?></p>
        <div class="ga-eigen"><?= $gaFeld($sprache) ?></div>
        <details class="ga-andere"><summary><?= $h($W($PS['ga_andere'])) ?></summary>
          <p class="klein" style="margin:6px 0 4px"><?= $h($W($PS['ga_auto_hinweis'])) ?></p>
          <?php foreach (array_diff(['it', 'de', 'en'], [$sprache]) as $l): $gaAuto = !empty($gs['auto'][$l]); $eigenT = !empty($gs['texte'][$l]); ?>
            <details><summary><?= $h(['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'][$l]) ?>
              <span class="gs-marke<?= $eigenT || $gaAuto ? ' an' : '' ?>"><?= $h($eigenT ? $W($PS['g_eigen']) : ($gaAuto ? $W($PS['ga_auto_fertig']) : ($gs['auto_offen'] ? $W($PS['ga_auto_offen']) : $W($PS['g_std'])))) ?></span></summary>
              <?= $gaFeld($l) ?>
            </details>
          <?php endforeach; ?>
        </details>
      </div>
      <?php /* Zwei Überschriften testen (03.10.2026, N3) */ $gaAb = $gs['ab']; $gaAbSt = $gaAb !== null && $gaAb['gewinner'] === null ? PartnerSeite::abStand($p, $gs) : null; ?>
      <label for="gs_titel_b" style="margin-top:12px"><?= $h($W($PS['ga_ab'])) ?> <span style="color:var(--leise)">(max. <?= PartnerSeite::TEXT_MAX['titel'] ?>)</span></label>
      <input id="gs_titel_b" type="text" name="titel_b" maxlength="<?= PartnerSeite::TEXT_MAX['titel'] ?>" value="<?= $h($gaAb !== null && $gaAb['gewinner'] === null ? $gaAb['b'] : '') ?>">
      <p class="klein" style="margin:4px 0 0"><?= $h($W($PS['ga_ab_hilfe'])) ?></p>
      <?php if ($gaAbSt !== null): ?><p class="klein ga-ab"><?= $h(strtr($W($PS['ga_ab_stand']), ['{ab}' => (string) $gaAbSt['a']['besuche'], '{ae}' => (string) $gaAbSt['a']['aktiv'], '{bb}' => (string) $gaAbSt['b']['besuche'], '{be}' => (string) $gaAbSt['b']['aktiv']])) ?></p>
      <?php elseif ($gaAb !== null && $gaAb['gewinner'] !== null): ?><p class="klein ga-ab"><?= $h(strtr($W($PS['ga_ab_gewonnen']), ['{v}' => strtoupper($gaAb['gewinner']), '{t}' => $gaAb['gewinner'] === 'b' ? $gaAb['b'] : $gaAb['a']])) ?></p><?php endif; ?>
      <label for="gs_wa" id="gs_wa_l"><?= $h($W($PS['g_wa'])) ?></label>
      <input id="gs_wa" type="text" name="whatsapp" inputmode="tel" autocomplete="tel" maxlength="20" value="<?= $h($gs['whatsapp']) ?>" placeholder="+39 …">

    <?php /* Sprachnachricht (28.09.2026, Uwe: Ja zu R6) */ ?>
    <p class="gs-h"><?= $h($W($PS['g_gruss'])) ?></p>
    <p class="klein" style="margin:0 0 8px"><?= $h($W($PS['g_gruss_hilfe'])) ?></p>
    <?php if ($grussJetzt): ?><p class="klein" style="margin:0 0 4px"><?= $h($W($PS['g_gruss_jetzt'])) ?></p><audio controls preload="none" src="<?= $h($grussJetzt) ?>" style="width:100%;max-width:420px;margin-bottom:8px"></audio><?php endif; ?>
    <div class="gs-gruss" id="gs_gruss" data-auf="<?= $h($W($PS['g_gruss_auf'])) ?>" data-stop="<?= $h($W($PS['g_gruss_stop'])) ?>" data-neu="<?= $h($W($PS['g_gruss_neu'])) ?>">
      <button class="knopf" type="button" data-aufnahme hidden><?= $h($W($PS['g_gruss_auf'])) ?></button>
      <span class="gs-gruss-zeit" data-zeit hidden>0:00 / 0:30</span>
      <audio data-probe controls hidden></audio>
      <p class="klein" data-hinweis hidden></p>
    </div>
    <label for="gs_gruss_datei" style="margin-top:8px"><?= $h($W($PS['g_gruss_datei'])) ?></label>
    <input id="gs_gruss_datei" type="file" name="gruss" accept="audio/*">
    </section>

    <section class="ga-schritt" id="gs_schritt3" data-schritt="3">
      <h3 class="ga-h"><?= $h($W($PS['ga_s3'])) ?></h3>
    <p class="gs-h"><?= $h($W($PS['g_knopf'])) ?></p>
    <div class="gs-knoepfe">
      <?php foreach (PartnerSeite::KNOEPFE as $kk): ?>
        <label class="gs-haken"><input type="radio" name="knopf" value="<?= $h($kk) ?>" <?= $gs['knopf'] === $kk ? 'checked' : '' ?>> <?= $h($W($PS['knoepfe'][$kk])) ?></label>
      <?php endforeach; ?>
    </div>

    <?php /* An/aus und Reihenfolge (27.09.2026, Uwe: Ja zu „Bausteine umsortieren“) -- ohne Ziehen, mit Positionsnummer; Pfeile per Skript (E4, 03.10.2026). */ ?>
    <p class="gs-h"><?= $h($W($PS['g_reihenfolge'])) ?></p>
    <div class="ga-reihe" data-hoch="<?= $h($W($PS['ga_hoch'])) ?>" data-runter="<?= $h($W($PS['ga_runter'])) ?>">
    <?php $nB = count(PartnerSeite::BAUSTEINE); $gsFilm = PartnerSeite::film() !== null; foreach ($gs['reihenfolge'] as $i => $bs): if ($bs === 'film' && !$gsFilm) { continue; } ?>
      <div class="gs-zeile">
        <label class="gs-haken"><input type="checkbox" name="bausteine[<?= $bs ?>]" value="1" <?= $gs['bausteine'][$bs] ? 'checked' : '' ?>> <?= $h($W($PS['g_b_' . $bs])) ?></label>
        <select name="pos[<?= $bs ?>]" aria-label="<?= $h($W($PS['g_reihenfolge']) . ' — ' . $W($PS['g_b_' . $bs])) ?>"><?php for ($n = 1; $n <= $nB; $n++): ?><option value="<?= $n ?>"<?= $n === $i + 1 ? ' selected' : '' ?>><?= $n ?></option><?php endfor; ?></select>
      </div>
    <?php endforeach; ?>
    </div>

    <?php $gsFilme = PartnerSeite::filme(); if (count($gsFilme) > 1): ?>
      <p class="gs-h"><?= $h($W($PS['g_film_wahl'])) ?></p>
      <?php foreach ($gsFilme as $fid): ?>
        <label class="gs-haken"><input type="radio" name="film" value="<?= $h($fid) ?>" <?= $gs['film'] === $fid ? 'checked' : '' ?>> <?= $h($W($PS['g_film_' . str_replace('-', '_', $fid)])) ?></label>
      <?php endforeach; ?>
    <?php endif; ?>
    <p class="gs-h"><?= $h($W($PS['g_arbeiten'])) ?></p>
    <?php foreach (PartnerSeite::ARBEITEN as $ak): $ar = $PS['arbeiten'][$ak]; ?>
      <label class="gs-haken"><input type="checkbox" name="arbeiten[<?= $h($ak) ?>]" value="1" <?= in_array($ak, $gs['arbeiten'], true) ? 'checked' : '' ?>> <span><b><?= $h($ar['name']) ?></b> <span style="color:var(--leise)">· <?= $h(Texte::h($ar, $sprache)) ?></span>
        <?php $arUrl = PartnerSeite::arbeitUrl($ak); if ($arUrl): ?><a href="<?= $h($arUrl) ?>" target="_blank" rel="noopener" style="color:var(--leise);text-decoration:underline"><?= $h(preg_replace('~^www\.~', '', (string) parse_url($arUrl, PHP_URL_HOST))) ?></a><?php endif; ?></span></label>
    <?php endforeach; ?>
    </section>

    <div class="ga-unten">
      <button class="knopf" type="button" data-ga-zurueck hidden><?= $h($W($PS['ga_zurueck'])) ?></button>
      <button class="knopf" type="button" data-ga-weiter hidden><?= $h($W($PS['ga_weiter'])) ?></button>
      <button class="knopf haupt" type="submit"><?= $h($W($PS['g_speichern'])) ?></button>
      <button class="knopf" type="button" data-ga-vorschau hidden><?= $h($W($PS['ga_vorschau'])) ?></button>
    </div>
  </form>

  <div class="knoepfe" style="margin-top:10px">
    <?php if ($eigenesBild): ?>
      <form method="post" action="<?= $h($selbst()) ?>#seite"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="seite_bild_weg">
        <button class="knopf" type="submit"><?= $h($W($PS['g_bild_weg'])) ?></button></form>
    <?php endif; ?>
    <?php if ($grussJetzt): ?>
      <form method="post" action="<?= $h($selbst()) ?>#seite"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="seite_gruss_weg">
        <button class="knopf" type="submit"><?= $h($W($PS['g_gruss_weg'])) ?></button></form>
    <?php endif; ?>
    <?php if (PartnerSeite::eigen($p)): ?>
      <form method="post" action="<?= $h($selbst()) ?>#seite"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="seite_standard">
        <button class="knopf" type="submit"><?= $h($W($PS['g_standard'])) ?></button></form>
    <?php endif; ?>
  </div>

  <?php $gsLink = Partner::link($p); $gsText = $T('teilen_text') . $gsLink; ?>
  <p class="gs-h"><?= $h($T('teilen_seite')) ?></p>
  <div class="knoepfe" style="margin-top:0">
    <button class="knopf" type="button" data-kopie-text="<?= $h($gsLink) ?>"><?= $h($T('kopieren')) ?></button>
    <a class="knopf" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($gsText) ?>"><?= $h($T('teilen_wa')) ?></a>
    <a class="knopf" href="mailto:?subject=<?= rawurlencode($T('teilen_betreff')) ?>&amp;body=<?= rawurlencode($gsText) ?>"><?= $h($T('teilen_mail')) ?></a>
    <button class="knopf" type="button" data-teilen-text="<?= $h($gsText) ?>" hidden><?= $h($T('teilen_mehr')) ?></button>
  </div>
  <p class="gs-h"><?= $h($W($PS['g_vorschau'])) ?></p>
  <div class="gs-vorschau"><iframe src="<?= $h($vorschau) ?>" title="<?= $h($W($PS['g_vorschau'])) ?>" loading="lazy"></iframe></div>
</div>
<script type="application/json" id="gs_branchen"><?= json_encode(array_map(static fn($b) => ['name' => Texte::h($b['name'], $sprache)] + array_intersect_key($b, ['it' => 1, 'de' => 1, 'en' => 1]), Texte::SEITE_BRANCHEN), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script type="application/json" id="gs_farben"><?= json_encode(array_map(static fn($v) => ['grund' => $v['grund'], 'hell' => $v['hell']], PartnerSeite::VORLAGEN) + ['_akzente' => PartnerSeite::AKZENTE], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<script src="/assets/js/partner-gestalter.js?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/partner-gestalter.js') ?>" defer></script>
