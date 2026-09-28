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
$gFehler = in_array($meldung, ['text_link', 'wa_nummer', 'bild_gross', 'bild_art'], true) ? $meldung : '';
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
  .gs-vorschau{margin-top:16px;border:1px solid var(--linie);border-radius:16px;overflow:hidden;background:var(--flaeche2);height:560px;position:relative}
  .gs-vorschau iframe{border:0;width:390px;height:1120px;transform:scale(.5);transform-origin:0 0;position:absolute;left:calc(50% - 97.5px);top:0}
  @media (min-width:700px){.gs-vorschau iframe{transform:scale(.6);left:calc(50% - 117px)}.gs-vorschau{height:660px}}
</style>
<div class="block pt" id="seite" data-reiter="profil">
  <h2><?= $h($W($PS['g_titel'])) ?></h2>
  <?php if (($_GET['m'] ?? '') === 'g_gut'): ?><div class="hinweis gut" role="status"><?= $h($W($PS['g_gut'])) ?></div><?php endif; ?>
  <?php if ($gFehler): ?><div class="hinweis schlecht" role="alert"><?= $h($W($PS[$gFehler])) ?></div><?php endif; ?>
  <p class="klein" style="margin-top:0"><?= $h($W($PS['g_text'])) ?></p>

  <form method="post" action="<?= $h($selbst()) ?>#seite" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="tat" value="seite">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= PartnerSeite::BILD_MAX_BYTE ?>">

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
        <label><input type="radio" name="bild" value="<?= $h($bk) ?>" <?= $gs['bild'] === $bk ? 'checked' : '' ?> data-vorlage="<?= $h($bfV) ?>" data-akzent="<?= $h($bfA) ?>"><img class="gs-bild" src="<?= $h($daumen($datei)) ?>" alt="" loading="lazy"><?= $h($W($PS['bilder'][$bk])) ?></label>
      <?php endforeach; ?>
    </div>
    <?php /* Farbvorschlag zum Bild (28.09.2026, Uwe: Ja zu L5) -- erscheint per Skript, ohne Skript bleibt alles wählbar. */ ?>
    <div class="gs-vorschlag" id="gs_vorschlag" hidden aria-live="polite"
         data-namen="<?= $h(json_encode(['v' => array_map($W, $PS['vorlagen']), 'a' => array_map($W, $PS['akzente'])], JSON_UNESCAPED_UNICODE)) ?>">
      <span class="gs-muster" aria-hidden="true"><i></i><i></i></span><span><?= $h($W($PS['g_vorschlag'])) ?> <b data-was></b></span>
      <button class="knopf" type="button" data-uebernehmen><?= $h($W($PS['g_uebernehmen'])) ?></button>
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

    <p class="gs-h"><?= $h($W($PS['g_texte'])) ?></p>
    <p class="klein" style="margin:0 0 8px"><?= $h($W($PS['g_sprache_hinweis'])) ?></p>
    <div class="gs-sprachen">
      <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): $eigenT = !empty($gs['texte'][$l]); ?>
        <details <?= $l === $sprache ? 'open' : '' ?>><summary><?= $h($wie) ?> <span class="gs-marke<?= $eigenT ? ' an' : '' ?>"><?= $h($W($PS[$eigenT ? 'g_eigen' : 'g_std'])) ?></span></summary>
          <?php foreach (PartnerSeite::TEXT_MAX as $tk => $max):
            $std = strtr(Texte::h(Texte::PARTNER_LANDE[$tk], $l), ['{name}' => Partner::anzeigeName($p)]);
            $wort = $tk === 'titel' ? $W($PS['g_t_titel']) : ($tk === 'lead' ? $W($PS['g_t_lead']) : strtr($W($PS['g_t_p']), ['{n}' => substr($tk, 1)])); ?>
            <label for="gs_<?= $l . $tk ?>"><?= $h($wort) ?> <span style="color:var(--leise)">(max. <?= $max ?>)</span></label>
            <?php if ($tk === 'lead'): ?>
              <textarea id="gs_<?= $l . $tk ?>" name="texte[<?= $l ?>][<?= $tk ?>]" rows="3" maxlength="<?= $max ?>" placeholder="<?= $h($std) ?>"><?= $h((string) ($gs['texte'][$l][$tk] ?? '')) ?></textarea>
            <?php else: ?>
              <input id="gs_<?= $l . $tk ?>" type="text" name="texte[<?= $l ?>][<?= $tk ?>]" maxlength="<?= $max ?>" placeholder="<?= $h($std) ?>" value="<?= $h((string) ($gs['texte'][$l][$tk] ?? '')) ?>">
            <?php endif; ?>
          <?php endforeach; ?>
        </details>
      <?php endforeach; ?>
    </div>

    <p class="gs-h"><?= $h($W($PS['g_knopf'])) ?></p>
    <div class="gs-knoepfe">
      <?php foreach (PartnerSeite::KNOEPFE as $kk): ?>
        <label class="gs-haken"><input type="radio" name="knopf" value="<?= $h($kk) ?>" <?= $gs['knopf'] === $kk ? 'checked' : '' ?>> <?= $h($W($PS['knoepfe'][$kk])) ?></label>
      <?php endforeach; ?>
    </div>

    <?php /* An/aus und Reihenfolge (27.09.2026, Uwe: Ja zu „Bausteine umsortieren“) -- ohne Ziehen, mit Positionsnummer. */ ?>
    <p class="gs-h"><?= $h($W($PS['g_reihenfolge'])) ?></p>
    <?php $nB = count(PartnerSeite::BAUSTEINE); $gsFilm = PartnerSeite::film() !== null; foreach ($gs['reihenfolge'] as $i => $bs): if ($bs === 'film' && !$gsFilm) { continue; } ?>
      <div class="gs-zeile">
        <label class="gs-haken"><input type="checkbox" name="bausteine[<?= $bs ?>]" value="1" <?= $gs['bausteine'][$bs] ? 'checked' : '' ?>> <?= $h($W($PS['g_b_' . $bs])) ?></label>
        <select name="pos[<?= $bs ?>]" aria-label="<?= $h($W($PS['g_reihenfolge']) . ' — ' . $W($PS['g_b_' . $bs])) ?>"><?php for ($n = 1; $n <= $nB; $n++): ?><option value="<?= $n ?>"<?= $n === $i + 1 ? ' selected' : '' ?>><?= $n ?></option><?php endfor; ?></select>
      </div>
    <?php endforeach; ?>

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
    <label for="gs_wa"><?= $h($W($PS['g_wa'])) ?></label>
    <input id="gs_wa" type="text" name="whatsapp" inputmode="tel" autocomplete="tel" maxlength="20" value="<?= $h($gs['whatsapp']) ?>" placeholder="+39 …">

    <button class="knopf haupt" type="submit" style="margin-top:14px"><?= $h($W($PS['g_speichern'])) ?></button>
  </form>

  <div class="knoepfe" style="margin-top:10px">
    <?php if ($eigenesBild): ?>
      <form method="post" action="<?= $h($selbst()) ?>#seite"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="seite_bild_weg">
        <button class="knopf" type="submit"><?= $h($W($PS['g_bild_weg'])) ?></button></form>
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
<script type="application/json" id="gs_farben"><?= json_encode(array_map(static fn($v) => ['grund' => $v['grund'], 'hell' => $v['hell']], PartnerSeite::VORLAGEN) + ['_akzente' => PartnerSeite::AKZENTE], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<script src="/assets/js/partner-gestalter.js?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/partner-gestalter.js') ?>" defer></script>
