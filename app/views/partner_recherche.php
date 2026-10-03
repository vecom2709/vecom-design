<?php
/* ==========================================================================
   Kunden finden (26.09.2026): Firmen-Finder, Website-Schnellcheck,
   Gesprächsleitfaden. Eingebunden aus partner.php ($p, $sprache, $T, $h,
   $selbst, $meldung, $checkNeu).
   ========================================================================== */
$fiOrt = trim((string) ($_GET['fi_ort'] ?? ''));
$fiBranche = (string) ($_GET['fi_branche'] ?? '');
$fiErg = $fiOrt !== '' ? PartnerRecherche::suchen((int) $p['id'], $fiOrt, $fiBranche, $sprache, !isset($_GET['fi_nz']), true) : null;
$fiMeine = PartnerRecherche::meine((int) $p['id'], $sprache);
$branchenListe = Akquise::branchen();
$fiMeldung = in_array($meldung, ['fi_weg', 'fi_vecom', 'fi_voll'], true) ? $meldung : '';
$feFehler = Texte::PARTNER['fe_fehler'][$meldung] ?? null;
$feWahl = $feFehler ? $_POST : [];
$ckMeldung = in_array($meldung, ['ck_adresse', 'ck_genug'], true) ? $meldung : '';
$ckLetzte = PartnerCheck::letzte((int) $p['id']);
$datum = static fn(string $d): string => date('d.m.Y', strtotime($d));
$mpKnopf = Texte::h(Texte::PARTNER_MARKETING['mp_knopf'], $sprache);
/* Kontaktieren (27.09.2026): Vorlagen in der Sprache des Betriebs, Telefon/E-Mail
   nur bei eigenen Reservierungen, „Vecom soll anschreiben“. */
$AK = static fn(string $k): string => Texte::h(Texte::PARTNER_ANSCHREIBEN['ui'][$k] ?? [], $sprache);
$akMeldung = (string) ($_GET['m'] ?? '');
$kontaktFeld = static function (array $f) use ($h, $p, $sprache, $selbst, $AK, $akMeldung): string {
    $id = (int) $f['id'];
    $spB = PartnerAnschreiben::sprache($f, $sprache);
    $check = PartnerAnschreiben::check((int) $p['id'], (string) $f['domain']);
    $links = PartnerAnschreiben::links($f);
    $wa = PartnerAnschreiben::waNummer((string) $f['telefon'], (string) $f['land']);
    $offen = (int) ($_GET['ak'] ?? 0) === $id;
    $o = '<details class="ak" id="ak_' . $id . '"' . ($offen ? ' open' : '') . '><summary>' . $h($AK('titel')) . '</summary>';
    if ($offen && $akMeldung === 'ak_gut') { $o .= '<div class="hinweis gut" role="status">' . $h($AK('vecom_gut')) . '</div>'; }
    if ($offen && $akMeldung === 'ak_vecom') { $o .= '<div class="hinweis">' . $h($AK('vecom_nein')) . '</div>'; }
    $o .= '<p class="klein ak-regel">' . $h($AK('regel')) . '</p><div class="ak-daten">';
    if ($f['telefon'] === '' && $f['email'] === '') { $o .= '<span class="klein">' . $h($AK('keine')) . '</span>'; }
    if ($f['telefon'] !== '') { $o .= '<span>' . $h($AK('tel')) . ': <a href="tel:' . $h(preg_replace('/[^\d+]/', '', $f['telefon'])) . '">' . $h($f['telefon']) . '</a></span>'; }
    if ($f['email'] !== '') { $o .= '<span>' . $h($AK('mail')) . ': <a href="mailto:' . $h($f['email']) . '">' . $h($f['email']) . '</a></span>'; }
    $o .= '</div><div class="ck-knoepfe ak-links">';
    if (isset($links['web'])) { $o .= '<a class="knopf klein-knopf" target="_blank" rel="noopener" href="' . $h($links['web']) . '">' . $h($AK('web')) . '</a>'; }
    $o .= '<a class="knopf klein-knopf" target="_blank" rel="noopener" href="' . $h($links['route']) . '">' . $h($AK('route')) . '</a>';
    if ($f['telefon'] === '') { $o .= '<a class="knopf klein-knopf" target="_blank" rel="noopener" href="' . $h($links['suche']) . '">' . $h($AK('suche')) . '</a>'; }
    $o .= '</div><p class="md-l" style="margin:12px 0 6px">' . $h($AK('sprache')) . '</p><div class="chips ak-sp">';
    foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie) {
        $o .= '<button type="button" data-ak-sp="' . $l . '" data-ak="' . $id . '" aria-pressed="' . ($l === $spB ? 'true' : 'false') . '">' . $wie . '</button>';
    }
    $o .= '</div>';
    foreach (['it', 'de', 'en'] as $l) {
        $t = PartnerAnschreiben::texte($p, $f, $l, $check);
        $waLink = 'https://wa.me/' . $wa . '?text=' . rawurlencode($t['wa']);
        $mailLink = 'mailto:' . rawurlencode($f['email']) . '?subject=' . rawurlencode($t['betreff']) . '&body=' . rawurlencode($t['mail']);
        $o .= '<div class="ak-text" data-ak-text="' . $id . '-' . $l . '"' . ($l === $spB ? '' : ' hidden') . '>'
            . '<textarea id="ak_' . $id . '_' . $l . '_wa" readonly rows="6">' . $h($t['wa']) . '</textarea>'
            . '<div class="ck-knoepfe"><a class="knopf klein-knopf haupt" target="_blank" rel="noopener" href="' . $h($waLink) . '" data-angeschrieben="' . $id . '">' . $h($AK('wa')) . '</a>'
            . '<button class="knopf klein-knopf" type="button" data-kopie="ak_' . $id . '_' . $l . '_wa" data-angeschrieben="' . $id . '">' . $h($AK('kopieren')) . '</button></div>'
            . '<textarea id="ak_' . $id . '_' . $l . '_mail" readonly rows="7" style="margin-top:10px">' . $h($t['betreff'] . "\n\n" . $t['mail']) . '</textarea>'
            . '<div class="ck-knoepfe"><a class="knopf klein-knopf" href="' . $h($mailLink) . '" data-angeschrieben="' . $id . '">' . $h($AK('mail_neu')) . '</a>'
            . '<button class="knopf klein-knopf" type="button" data-kopie="ak_' . $id . '_' . $l . '_mail" data-angeschrieben="' . $id . '">' . $h($AK('kopieren')) . '</button></div></div>';
    }
    $w = PartnerAnschreiben::wunsch($id);
    /* Briefversand ausgeschaltet (27.09.2026): „Vecom soll anschreiben“ gibt es dann nicht -- außer ein früherer Wunsch hat einen Stand zu zeigen. */
    require_once dirname(__DIR__) . '/src/AkquiseGate.php';
    if (!AkquiseGate::briefAn() && ($w === null || $w['partner_id'] !== (int) $p['id'])) { return $o . '</details>'; }
    $o .= '<div class="ak-vecom"><b>' . $h($AK('vecom')) . '</b><p class="klein" style="margin:4px 0 8px">' . $h($AK('vecom_text')) . '</p>';
    if ($w !== null && $w['partner_id'] === (int) $p['id']) {
        $o .= '<p class="klein ak-stand">' . $h(strtr($AK($w['status'] === 'verschickt' ? 'vecom_raus' : 'vecom_offen'), ['{datum}' => date('d.m.Y', strtotime((string) ($w['erledigt_am'] ?: $w['created_at'])))])) . '</p>';
    } else {
        $o .= '<form method="post" action="' . $h($selbst()) . '#ak_' . $id . '"><input type="hidden" name="_csrf" value="' . $h($_SESSION['csrf']) . '">'
            . '<input type="hidden" name="tat" value="ak_vecom"><input type="hidden" name="firma" value="' . $id . '">'
            . '<button class="knopf klein-knopf" type="submit">' . $h($AK('vecom')) . '</button></form>';
    }
    return $o . '</div></details>';
};
$firmaZeile = static function (array $f, bool $meine) use ($h, $T, $selbst, $datum, $fiOrt, $fiBranche, $p, $mpKnopf, $kontaktFeld, $sprache): string {
    $o = '<li class="firma"><div class="firma__kopf"><b>' . $h($f['name']) . '</b><span class="chance ' . $h($f['chance']) . '">' . $h($T('fi_chance_' . $f['chance'])) . '</span></div>'
       . '<small>' . $h($f['branche']) . ' · ' . $h(trim($f['adresse'] !== '' ? $f['adresse'] . ', ' . $f['ort'] : $f['ort'], ', ')) . ($f['domain'] !== '' ? ' · ' . $h($f['domain']) : '') . '</small>';
    $o .= '<div class="firma__tat">';
    if ($meine || ($f['stand'] ?? '') === 'meine') {
        $o .= '<span class="klein" style="margin:0">' . $h(strtr($T('fi_bis'), ['{datum}' => $datum((string) $f['bis'])])) . '</span>'
            . '<span class="ck-knoepfe"><a class="knopf klein-knopf" target="_blank" rel="noopener" href="' . $h(PartnerMappe::link($p, ['firma' => (int) $f['id']])) . '">' . $h($mpKnopf) . '</a>'
            . '<form method="post" action="' . $h($selbst(['fi_ort' => $fiOrt, 'fi_branche' => $fiBranche, 'fi_nz' => 1])) . '#recherche">'
            . '<input type="hidden" name="_csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tat" value="fi_frei">'
            . '<input type="hidden" name="firma" value="' . (int) $f['id'] . '"><button class="knopf klein-knopf" type="submit">' . $h($T('fi_frei')) . '</button></form></span>';
        /* W2 (28.09.2026): der Flyer zur Branche dieses Betriebs, mit eigenem QR-Code -- zum Mitnehmen */
        if ($meine) {
            require_once dirname(__DIR__) . '/src/PartnerFlyer.php';
            $fs = PartnerFlyer::fuerBranche((string) ($f['branche_key'] ?? ''));
            $o .= '<span class="ck-knoepfe" style="width:100%"><span class="klein" style="margin:0">' . $h(strtr($T('fi_flyer'), ['{name}' => PartnerFlyer::name($fs, $sprache)])) . '</span>'
                . '<a class="knopf klein-knopf" download href="' . $h($selbst(['fl' => $fs, 'f' => 'pdf'])) . '">PDF</a>'
                . '<a class="knopf klein-knopf" download href="' . $h($selbst(['fl' => $fs, 'f' => 'jpg'])) . '">' . $h($T('fl_jpg')) . '</a></span>';
        }
    } elseif (($f['stand'] ?? '') === 'vecom') {
        $o .= '<span class="klein" style="margin:0">' . $h($T('fi_vecom')) . '</span>';
    } else {
        $o .= '<form method="post" action="' . $h($selbst(['fi_ort' => $fiOrt, 'fi_branche' => $fiBranche, 'fi_nz' => 1])) . '#recherche">'
            . '<input type="hidden" name="_csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tat" value="fi_reserv">'
            . '<input type="hidden" name="firma" value="' . (int) $f['id'] . '"><button class="knopf klein-knopf" type="submit">' . $h($T('fi_reserv')) . '</button></form>';
    }
    $o .= '</div>';
    if ($meine) { $o .= $kontaktFeld($f); }
    return $o . '</li>';
};
?>
<style>
  .firmen{list-style:none;padding:0;margin:10px 0 0;display:grid;gap:8px}
  .firma{border:1px solid var(--linie);border-radius:12px;padding:10px 12px;display:grid;gap:4px}
  .firma__kopf{display:flex;gap:8px;align-items:baseline;justify-content:space-between;flex-wrap:wrap}
  .firma small{color:var(--leise);font-size:12.5px;line-height:1.45}
  .firma__tat{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-top:4px}
  .firma__tat form{display:block}
  .chance{font-size:11.5px;padding:2px 9px;border-radius:999px;border:1px solid var(--linie);white-space:nowrap;color:var(--dim)}
  .chance.hoch{border-color:rgba(241,211,139,.6);color:var(--cyan)}
  .ak{margin-top:8px;border-top:1px solid var(--linie);padding-top:6px}
  .ak>summary{cursor:pointer;color:var(--cyan);font-size:14px;padding:4px 0}
  .ak-regel{margin:6px 0 8px;color:var(--leise)}
  .ak-daten{display:flex;flex-wrap:wrap;gap:6px 16px;font-size:14px;margin-bottom:8px}
  .ak-daten a{color:var(--text)}
  .ak textarea{width:100%;box-sizing:border-box;font-size:13.5px;line-height:1.5;padding:9px 11px;margin-top:8px}
  .ak .ck-knoepfe{margin-top:6px}
  .ak-vecom{margin-top:14px;border:1px dashed var(--linie2);border-radius:12px;padding:10px 12px}
  .ak-stand{margin:0;color:var(--cyan)}
  .ck-knoepfe{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
  .ck-knoepfe form{display:inline}
  .ck-weg{color:var(--dim) !important}
  .klein-knopf{min-height:34px !important;padding:6px 12px !important;font-size:13px !important}
  .reihe{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
  .reihe > div{flex:1 1 160px;display:flex;flex-direction:column;gap:4px}
  .reihe select{font-size:16px;padding:11px 12px;border-radius:10px}
  .ck-ergebnis{border:1px solid rgba(241,211,139,.45);border-radius:12px;padding:12px;margin-top:10px}
  .ck-ergebnis ul{list-style:none;padding:0;margin:6px 0 10px;display:grid;gap:4px;font-size:14px}
  .ampel{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px;vertical-align:middle}
  .ampel.gut{background:#34d39b}.ampel.hinweis{background:#e8b64c}.ampel.schlecht{background:#ef6b5b}
  .al{border:1px solid rgba(241,211,139,.55);border-radius:14px;padding:14px 14px 12px;margin:0 0 22px;background:rgba(241,211,139,.06);scroll-margin-top:90px}
  .al-liste{margin-top:10px}
  .al-knoepfe{margin-top:8px}
  .al-sb{display:grid;grid-template-columns:max-content 1fr;align-items:baseline;gap:6px 14px;margin:12px 0 0;font-size:14.5px;line-height:1.5}
  .al-sb dt{color:var(--dim);font-size:12.5px;text-transform:uppercase;letter-spacing:.04em;padding-top:2px}
  .al-sb dd{margin:0;color:var(--text);min-width:0;overflow-wrap:anywhere}
  .al-sb a{color:var(--gold, #f1d38b);display:inline-flex;align-items:center;min-height:32px}
  .al-sb-leise{color:var(--dim)}
  .al-sb-marke{display:inline-block;margin-left:6px;padding:1px 9px;border-radius:999px;font-size:12.5px;font-weight:600;border:1px solid}
  .al-sb-marke.gruen{color:#34d39b;border-color:rgba(52,211,155,.5);background:rgba(52,211,155,.08)}
  .al-sb-marke.grau{color:var(--dim);border-color:var(--linie)}
  .al-sb-marke.gold{color:#f1d38b;border-color:rgba(241,211,139,.45);background:rgba(241,211,139,.07)}
  .al-an{margin-top:12px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,.035);border:1px solid var(--linie)}
  .al-an p{margin:0}
  .al-an-kopf{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;font-size:14.5px}
  .al-an-kopf .al-sb-marke{margin-left:0}
  .al-an-ohne{margin-top:6px!important;font-size:14.5px;color:#f1d38b}
  .al-an ul{margin:8px 0 0;padding-left:20px;display:grid;gap:3px;font-size:14.5px;line-height:1.45;color:var(--text)}
  @media (max-width:520px){.al-sb{grid-template-columns:1fr;gap:2px}.al-sb dd{margin-bottom:8px}}
  .al-mehr{margin-top:8px;border-top:1px solid var(--linie);padding-top:4px}
  .al-mehr > summary{cursor:pointer;color:var(--gold, #f1d38b);font-size:14px;min-height:40px;display:flex;align-items:center;justify-content:space-between;gap:10px;list-style:none}
  .al-mehr > summary::-webkit-details-marker{display:none}
  .al-mehr > summary::after{content:"";flex:0 0 auto;width:7px;height:7px;margin:0 7px 3px 0;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);transition:transform .18s cubic-bezier(.16,1,.3,1)}
  .al-mehr[open] > summary::after{transform:rotate(-135deg);margin-bottom:-3px}
  .al-mehr[open] > summary{margin-bottom:2px}
  .al-sagen{margin-top:8px}
  .al-sagen summary{cursor:pointer;font-size:14px;color:var(--text)}
  .al-skript{list-style:none;margin:10px 0 0;padding:0;display:grid;gap:10px}
  .al-skript li{border-left:3px solid var(--linie);padding:2px 0 2px 12px}
  .al-skript li.satz{border-left-color:rgba(241,211,139,.8)}
  .al-skript small{display:block;font-size:11.5px;color:var(--dim);text-transform:uppercase;letter-spacing:.05em}
  .al-skript p{margin:2px 0 0;font-size:15px;line-height:1.55;color:var(--text)}
  .al-skript li.al-einw p{margin-top:6px}
  .al-skript li.al-einw b{color:var(--gold, #f1d38b);font-weight:600}
  #anrufliste .al-erg{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;margin-top:10px}
  #anrufliste .al-erg form{display:inline-flex;flex-direction:row;gap:0;margin:0}
  #anrufliste .al-ja{flex:0 0 auto}
  #anrufliste .al-ja[open]{flex:1 1 100%}
  /* Ergebnis in einer Zeile: drei kleine Knöpfe statt drei Stockwerke (03.10.2026) */
  #anrufliste .al-erg .knopf{min-height:40px;padding:8px 14px;font-size:14px}
  #anrufliste .al-ja > summary{list-style:none;display:inline-flex;border-color:rgba(52,211,155,.6);color:#34d39b}
  #anrufliste .al-ja > summary::-webkit-details-marker{display:none}
  #anrufliste .al-ja[open] > summary{margin-bottom:10px}
  #anrufliste .al-ja form{display:flex;flex-direction:column;gap:10px;max-width:520px}
  .al-feld{display:flex;flex-direction:column;gap:4px;font-size:13px;color:var(--dim)}
  .al-haken{display:flex;gap:9px;align-items:flex-start;font-size:14px;color:var(--text)}
  .al-haken input{width:auto;margin-top:3px}
  #anrufliste blockquote{margin:0;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,.04);font-size:14px;line-height:1.5}
  .ap{border:1px solid rgba(241,211,139,.45);border-radius:14px;padding:14px 14px 12px;margin:0 0 22px;background:rgba(241,211,139,.04);scroll-margin-top:90px}
  #heute form.ap-ort{display:flex;flex-direction:row;align-items:stretch;gap:8px;flex-wrap:wrap;max-width:560px;margin-top:10px}
  #heute form.ap-ort input{flex:1 1 220px;min-width:0}
  #heute form.ap-ort button{flex:0 0 auto}
  .ap-liste{margin-top:10px}
  #leitfaden{scroll-margin-top:90px}
  .leitfaden details{border-top:1px solid var(--linie);padding:10px 0}
  .leitfaden summary{cursor:pointer;font-size:14.5px;color:var(--text)}
  .leitfaden pre{white-space:pre-wrap;font-family:inherit;font-size:14px;line-height:1.65;color:var(--dim);margin:8px 0 0}
</style>

<div class="block pt" id="recherche" data-reiter="finden">
  <h2><?= $h($T('re_titel')) ?></h2>

  <?php /* Anrufliste (29.09.2026, Uwe: Ja zu T1–T4): Betriebe, die Vecom zum Abtelefonieren gegeben hat */
        require_once dirname(__DIR__) . '/src/PartnerAnrufliste.php'; require_once dirname(__DIR__) . '/src/PartnerSteckbrief.php';
        $alListe = PartnerAnrufliste::liste((int) $p['id']); $alErl = PartnerAnrufliste::erledigt((int) $p['id']);
        $alMeld = preg_match('~^al_[a-z_]+$~', (string) ($_GET['al'] ?? '')) ? (string) $_GET['al'] : '';
        if ($alListe || array_sum($alErl) > 0 || $alMeld !== '' || PartnerAnrufliste::wiedervorlage((int) $p['id'])['n'] > 0): $alSatz = Partner::satzWort(PartnerAnrufliste::satz($p), true); ?>
  <section class="al" id="anrufliste" aria-labelledby="al_titel">
   <details class="klapp" data-klapp="anrufliste" open>
    <summary><h3 class="md-h" id="al_titel"><?= $h(strtr($T('al_titel'), ['{n}' => (string) count($alListe)])) ?></h3></summary>
    <p class="klein" style="margin-top:0"><?= $h(strtr($T('al_text'), ['{satz}' => $alSatz])) ?></p>
    <p class="klein" style="margin-top:0"><?= $h($T('al_regel')) ?> <?= $h($T('al_wv_hinweis')) ?></p>
    <?php if ($alMeld !== ''): ?><div class="hinweis <?= in_array($alMeld, ['al_danke', 'al_danke_wa', 'al_ok', 'al_raus'], true) ? 'gut' : 'schlecht' ?>" role="status"><?= $h($T($alMeld)) ?></div><?php endif; ?>
    <?php $alWv = PartnerAnrufliste::wiedervorlage((int) $p['id']); if ($alWv['n'] > 0): ?>
      <p class="klein"><?= $h(strtr($T('al_wv'), ['{n}' => (string) $alWv['n'], '{datum}' => date('d.m.', strtotime((string) $alWv['naechster']))])) ?></p>
    <?php endif; ?>
    <?php if (array_sum($alErl) > 0): ?><p class="klein"><?= $h(strtr($T('al_erledigt'), ['{z}' => (string) $alErl['zugestimmt'], '{k}' => (string) $alErl['kein_interesse']])) ?></p><?php endif; ?>
    <?php if (!$alListe): ?>
      <p class="klein"><?= $h($T('al_leer')) ?></p>
    <?php else: ?>
      <ol class="firmen al-liste">
        <?php $alNr = 0; foreach ($alListe as $af): $alNr++;
          $alAudit = Akquise::letzterAudit((int) $af['id']);   /* Problem und Lösung aus der Fehler-Analyse dieses Betriebs */
          $alBef = $alAudit ? Akquise::befunde((int) $alAudit['id']) : [];
          $alP = AkquiseAnsprechen::paket($af, $alBef, null, '', (string) $p['name']);
          $alWege = PartnerAnrufliste::wege($af);
          $alTel = (string) preg_replace('~[^\d+]~', '', (string) $af['telefon']);
          $alWa = preg_match('~^(\+39|0039)?3\d{8,9}$~', (string) preg_replace('~[\s./-]~', '', (string) $af['telefon'])) ? (string) $af['telefon'] : '';
          $alForm = static fn(string $erg, string $inhalt) => '<form method="post" action="' . $h($selbst()) . '#anrufliste"><input type="hidden" name="_csrf" value="' . $h($_SESSION['csrf']) . '">'
              . '<input type="hidden" name="tat" value="al_ergebnis"><input type="hidden" name="firma" value="' . (int) $af['id'] . '"><input type="hidden" name="ergebnis" value="' . $erg . '">' . $inhalt . '</form>'; ?>
          <li class="firma al-firma">
            <div class="firma__kopf"><b><?= $h((string) $af['name']) ?></b>
              <?php if ((int) $af['versuche'] > 0): ?><span class="chance mittel"><?= $h(strtr($T('al_versuche'), ['{n}' => (string) (int) $af['versuche']])) ?></span><?php endif; ?></div>
            <small><?= $h(implode(' · ', array_filter([(string) $af['branche'] !== '' ? Akquise::branchenName((string) $af['branche'], $sprache) : '', trim(((string) ($af['plz'] ?? '')) . ' ' . ((string) ($af['stadt'] ?? '')))]))) ?></small>
            <div class="al-knoepfe">
              <a class="knopf haupt" href="tel:<?= $h($alTel) ?>"><?= $h($T('al_anrufen')) ?>: <?= $h((string) $af['telefon']) ?></a>
            </div>
            <?php /* Nur der erste Betrieb steht offen — die übrigen zeigen Name, Anrufknopf und Ergebnis, der Rest klappt (03.10.2026). */ ?>
            <details class="al-mehr"<?= $alNr === 1 ? ' open' : '' ?>><summary><?= $h($T('kl_details')) ?></summary>
            <?php /* D1–D4 (29.09.2026): Steckbrief, Öffnungszeiten laut Website, beste Anrufzeit, Ergebnis der Prüfung */
              $sbW = static fn(string $k): string => PartnerSteckbrief::wort($k, $sprache);
              $sb = PartnerSteckbrief::steckbrief($af);
              $sbJetzt = PartnerSteckbrief::ortszeit($af);
              $sbZ = PartnerSteckbrief::zeiten($af);
              $sbA = PartnerSteckbrief::anrufzeit($af, $sprache, $sbJetzt);
              $sbAn = PartnerSteckbrief::analyse($af, $alAudit, $alBef, $sprache); ?>
            <dl class="al-sb">
              <?php if ($sb['adresse'] !== ''): ?><dt><?= $h($sbW('adresse')) ?></dt>
                <dd><?= $h($sb['adresse']) ?> <a href="<?= $h($sb['karte']) ?>" target="_blank" rel="noopener"><?= $h($sbW('karte')) ?> ↗</a></dd><?php endif; ?>
              <dt><?= $h($sbW('web')) ?></dt>
              <dd><?php if ($sb['url'] !== ''): ?><a href="<?= $h($sb['url']) ?>" target="_blank" rel="noopener nofollow"><?= $h($sb['url_zeigen']) ?> ↗</a><?php else: ?><span class="al-sb-leise"><?= $h($sbW('ohne_web')) ?></span><?php endif; ?></dd>
              <?php if ($sb['person'] !== ''): ?><dt><?= $h($sbW('person')) ?></dt><dd><?= $h($sb['person']) ?></dd><?php endif; ?>
              <dt><?= $h($sbW('zeiten')) ?></dt>
              <dd><?php if ($sbZ): $sbOffen = PartnerSteckbrief::offen($sbZ['z'], $sbJetzt); ?><?= $h(PartnerSteckbrief::zeitenText($sbZ['z'], $sprache)) ?>
                  <span class="al-sb-marke <?= $sbOffen ? 'gruen' : 'grau' ?>"><?= $h($sbW($sbOffen ? 'offen' : 'zu')) ?></span>
                <?php else: ?><span class="al-sb-leise"><?= $h($sbW('zeiten_leer')) ?></span><?php endif; ?></dd>
              <dt><?= $h($sbW('beste')) ?></dt>
              <dd><b><?= $h($sbA['gut']) ?></b><?php if ($sbA['jetzt']): ?> <span class="al-sb-marke gruen"><?= $h($sbW('gut_jetzt')) ?></span><?php endif; ?>
                <br><span class="al-sb-leise"><?= $h($sbA['nicht']) ?></span></dd>
            </dl>
            <div class="al-an">
              <?php if (!$sbAn['geprueft']): ?><p class="al-sb-leise"><?= $h($sbW('nie')) ?></p>
              <?php else: ?>
                <p class="al-an-kopf"><b><?= $h($sbW('analyse')) ?></b>
                  <?php if ($sbAn['score'] !== null): ?><span class="al-sb-marke gold"><?= $h(strtr($sbW('chance'), ['{n}' => (string) $sbAn['score']])) ?></span><?php endif; ?>
                  <?php if ($sbAn['am'] !== ''): ?><span class="al-sb-leise"><?= $h(strtr($sbW('am'), ['{d}' => $sbAn['am']])) ?></span><?php endif; ?></p>
                <?php if ($sbAn['ohne_web']): ?><p class="al-an-ohne"><?= $h($sbW('kein_web_analyse')) ?></p><?php endif; ?>
                <?php if ($sbAn['punkte']): ?><ul><?php foreach ($sbAn['punkte'] as $pt): ?><li><?= $h($pt) ?></li><?php endforeach; ?></ul><?php elseif (!$sbAn['ohne_web']): ?><p class="al-sb-leise" style="margin-top:6px"><?= $h($sbW('keine')) ?></p><?php endif; ?>
              <?php endif; ?>
            </div>
            <details class="al-sagen"><summary><?= $h($T('al_sagen')) ?></summary>
              <ol class="al-skript" lang="<?= $h($alP['sprache']) ?>">
                <?php foreach (['hallo' => 'al_s_hallo', 'lob' => 'al_s_lob', 'problem' => 'al_s_problem', 'zahl' => 'al_s_zahl', 'loesung' => 'al_s_loesung', 'frage' => 'al_s_frage', 'ja' => 'al_s_ja', 'email' => 'al_s_email'] as $k => $w):
                  if ((string) ($alP['saetze'][$k] ?? '') === '') { continue; } ?>
                  <li class="<?= in_array($k, ['ja', 'email'], true) ? 'satz' : '' ?>"><small lang="<?= $h($sprache) ?>"><?= $h($T($w)) ?></small><p><?= $h($alP['saetze'][$k]) ?></p></li>
                <?php endforeach; ?>
                <li class="al-einw"><small lang="<?= $h($sprache) ?>"><?= $h($T('al_s_einwaende')) ?></small>
                  <?php foreach ($alP['saetze']['einwaende'] as [$eq, $ea]): ?><p><b><?= $h($eq) ?></b> <?= $h($ea) ?></p><?php endforeach; ?></li>
                <li><small lang="<?= $h($sprache) ?>"><?= $h($T('al_s_nein')) ?></small><p><?= $h($alP['saetze']['nein']) ?></p></li>
              </ol>
            </details>
            </details>
            <div class="al-erg">
              <details class="al-ja"><summary class="knopf"><?= $h($T('al_zugestimmt')) ?></summary>
                <?= $alForm('zugestimmt', '
                  <label class="al-feld"><span>' . $h($T('al_person')) . '</span><input name="person" required minlength="2" maxlength="80" placeholder="' . $h($T('al_person_ph')) . '"></label>
                  <label class="al-feld"><span>' . $h($T('al_email')) . '</span><input name="email" type="email" maxlength="190" required value="' . $h((string) ($af['email'] ?? '')) . '"></label>
                  <p class="klein" style="margin:0">' . $h($T('al_eins_hinweis')) . '</p>'
                  . (in_array('whatsapp', $alWege, true) ? '<label class="al-feld"><span>' . $h($T('al_wa_zusatz')) . '</span><input name="whatsapp" inputmode="tel" maxlength="40" value="' . $h($alWa) . '" placeholder="+39 3…"></label>' : '') . '
                  <blockquote lang="' . $h($alP['sprache']) . '">' . $h($alP['wortlaut']) . '</blockquote>
                  <label class="al-haken"><input type="checkbox" name="vorgelesen" value="1" required> ' . $h($T('al_haken')) . '</label>
                  <button class="knopf haupt" type="submit">' . $h($T('al_speichern')) . '</button>') ?>
              </details>
              <?= $alForm('kein_interesse', '<button class="knopf" type="submit">' . $h($T('al_kein')) . '</button>') ?>
              <?= $alForm('nicht_erreicht', '<button class="knopf" type="submit">' . $h($T('al_nicht')) . '</button>') ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
   </details>
  </section>
  <?php endif; ?>

  <?php /* Partner-Autopilot (29.09.2026): jeden Morgen fünf Betriebe zum Vorbeigehen */
        require_once dirname(__DIR__) . '/src/PartnerAutopilot.php'; require_once dirname(__DIR__) . '/src/PartnerFlyer.php';
        $apOrt = PartnerAutopilot::ort($p); $apListe = $apOrt !== '' ? PartnerAutopilot::heute($p, $sprache) : []; ?>
  <section class="ap" id="heute" aria-labelledby="ap_titel">
   <details class="klapp" data-klapp="heute" open>
    <summary><h3 class="md-h" id="ap_titel"><?= $h($apOrt !== '' ? strtr($T('ap_titel'), ['{n}' => (string) count($apListe), '{ort}' => $apOrt]) : $T('ap_titel_leer')) ?></h3></summary>
    <p class="klein" style="margin-top:0"><?= $h($T('ap_text')) ?></p>
    <?php if ($apOrt === ''): ?>
      <form method="post" action="<?= $h($selbst()) ?>#heute" class="ap-ort">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="ap_ort">
        <input type="text" name="ort" maxlength="80" required placeholder="<?= $h($T('ap_ort_ph')) ?>" aria-label="<?= $h($T('ap_ort_frage')) ?>">
        <button class="knopf haupt" type="submit"><?= $h($T('ap_ort_knopf')) ?></button>
      </form>
    <?php elseif (!$apListe): ?>
      <p class="klein"><?= $h($T('ap_leer')) ?></p>
    <?php else: ?>
      <ol class="firmen ap-liste">
        <?php foreach ($apListe as $af): $afFl = PartnerFlyer::fuerBranche($af['branche_key']); $afL = PartnerAnschreiben::links($af); ?>
          <li class="firma">
            <div class="firma__kopf"><b><?= $h($af['name']) ?></b><span class="chance <?= $h($af['chance']) ?>"><?= $h($T('fi_chance_' . $af['chance'])) ?></span></div>
            <small><?= $h($af['branche']) ?> · <?= $h(trim($af['adresse'] !== '' ? $af['adresse'] . ', ' . $af['ort'] : $af['ort'], ', ')) ?><?= $af['domain'] !== '' ? ' · ' . $h($af['domain']) : ' · ' . $h($T('ap_ohne_web')) ?></small>
            <div class="ck-knoepfe" style="margin-top:6px">
              <?php if (!$af['meine']): ?>
                <form method="post" action="<?= $h($selbst()) ?>#heute" style="display:inline"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
                  <input type="hidden" name="tat" value="fi_reserv"><input type="hidden" name="firma" value="<?= (int) $af['id'] ?>">
                  <button class="knopf klein-knopf" type="submit"><?= $h($T('fi_reserv')) ?></button></form>
              <?php else: ?><span class="klein" style="margin:0"><?= $h($T('ap_reserviert')) ?></span><?php endif; ?>
              <a class="knopf klein-knopf" download href="<?= $h($selbst(['fl' => $afFl, 'f' => 'pdf'])) ?>"><?= $h(strtr($T('ap_flyer'), ['{name}' => PartnerFlyer::name($afFl, $sprache)])) ?></a>
              <a class="knopf klein-knopf" target="_blank" rel="noopener" href="<?= $h($afL['route']) ?>"><?= $h($AK('route')) ?></a>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="knoepfe" style="margin-top:8px">
        <a class="knopf haupt" target="_blank" rel="noopener" href="<?= $h(PartnerAutopilot::route($apListe)) ?>"><?= $h($T('ap_route')) ?></a>
        <a class="knopf" href="#leitfaden"><?= $h($T('ap_leitfaden')) ?></a>
      </div>
      <details style="margin-top:8px"><summary class="klein" style="cursor:pointer"><?= $h(strtr($T('ap_ort_aendern'), ['{ort}' => $apOrt])) ?></summary>
        <form method="post" action="<?= $h($selbst()) ?>#heute" class="ap-ort" style="margin-top:6px">
          <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="ap_ort">
          <input type="text" name="ort" maxlength="80" required value="<?= $h($apOrt) ?>" aria-label="<?= $h($T('ap_ort_frage')) ?>">
          <button class="knopf" type="submit"><?= $h($T('ap_ort_knopf')) ?></button>
        </form></details>
    <?php endif; ?>
   </details>
  </section>

  <h3 class="md-h" style="margin-top:4px"><?= $h($T('ck_titel')) ?></h3>
  <p class="klein" style="margin-top:0"><?= $h($T('ck_text')) ?></p>
  <?php if (($_GET['m'] ?? '') === 'ck_weg_gut'): ?><div class="hinweis gut" role="status"><?= $h($T('ck_weg_gut')) ?></div><?php endif; ?>
  <?php if ($ckMeldung): ?><div class="hinweis schlecht" role="alert"><?= $h($T($ckMeldung)) ?></div><?php endif; ?>
  <form method="post" action="<?= $h($selbst()) ?>#recherche" data-warten="<?= $h($T('ck_laeuft')) ?>">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="tat" value="check">
    <label for="ck_url"><?= $h($T('ck_feld')) ?></label>
    <div class="kopie"><input id="ck_url" type="text" name="url" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="300" required
        value="<?= $h((string) ($_POST['url'] ?? $_GET['ck_url'] ?? '')) ?>"><button class="knopf haupt" type="submit"><?= $h($T('ck_pruefen')) ?></button></div>
  </form>
  <?php if ($checkNeu): $ckE = $checkNeu['ergebnis']; $ckL = PartnerCheck::link($checkNeu['token']); ?>
    <div class="ck-ergebnis" role="status">
      <b><?= $h($T('ck_fertig')) ?> <?= $h($ckE['host']) ?></b>
      <ul>
        <?php foreach ($ckE['punkte'] as $pk): $K = Texte::PARTNER_CHECK['punkte'][$pk['was']]; ?>
          <li><span class="ampel <?= $h($pk['stand']) ?>" aria-hidden="true"></span><?= $h(Texte::h($K['titel'], $sprache)) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="knoepfe" style="margin-top:0">
        <a class="knopf haupt" href="https://wa.me/?text=<?= rawurlencode($T('ck_wa_text') . $ckL) ?>" target="_blank" rel="noopener"><?= $h($T('ck_wa')) ?></a>
        <a class="knopf" href="<?= $h($ckL) ?>" target="_blank" rel="noopener"><?= $h($T('ck_oeffnen')) ?></a>
        <button class="knopf" type="button" data-kopie-text="<?= $h($ckL) ?>"><?= $h($T('kopieren')) ?></button>
        <a class="knopf" target="_blank" rel="noopener" href="<?= $h(PartnerMappe::link($p, ['ck' => $checkNeu['token']])) ?>"><?= $h($mpKnopf) ?></a>
      </div>
      <p class="klein" style="margin:8px 0 0"><?= $h(Texte::h(Texte::PARTNER_MARKETING['mp_erklaer'], $sprache)) ?></p>
    </div>
  <?php endif; ?>
  <?php if ($ckLetzte && !$checkNeu): ?>
    <details class="klapp" data-klapp="ck_letzte"<?= count($ckLetzte) <= 3 ? ' open' : '' ?>>
    <summary><span class="md-l"><?= $h($T('ck_letzte')) ?></span><span class="klapp__zahl"><?= count($ckLetzte) ?></span></summary>
    <ul class="firmen">
      <?php foreach ($ckLetzte as $c): ?>
        <li class="firma"><div class="firma__kopf"><b><?= $h($c['host']) ?></b><small><?= $h($datum($c['created_at'])) ?></small></div>
          <small><?= $h(strtr($T('ck_punkte'), ['{n}' => (string) $c['schlecht']])) ?> · <?= $h(strtr($T('ck_aufrufe'), ['{n}' => (string) $c['aufrufe']])) ?></small>
          <?php $ckL2 = PartnerCheck::link($c['token']); ?>
          <div class="firma__tat"><a href="<?= $h($ckL2) ?>" target="_blank" rel="noopener" style="color:var(--cyan);font-size:13.5px"><?= $h($T('ck_oeffnen')) ?> →</a>
            <span class="ck-knoepfe">
              <a class="knopf klein-knopf" href="https://wa.me/?text=<?= rawurlencode($T('ck_wa_text') . $ckL2) ?>" target="_blank" rel="noopener">WhatsApp</a>
              <button class="knopf klein-knopf" type="button" data-kopie-text="<?= $h($ckL2) ?>"><?= $h($T('kopieren')) ?></button>
              <button class="knopf klein-knopf" type="button" data-teilen-text="<?= $h($T('ck_wa_text') . $ckL2) ?>" hidden><?= $h($T('teilen_mehr')) ?></button>
              <a class="knopf klein-knopf" target="_blank" rel="noopener" href="<?= $h(PartnerMappe::link($p, ['ck' => $c['token']])) ?>"><?= $h($mpKnopf) ?></a>
              <form method="post" action="<?= $h($selbst()) ?>#recherche" onsubmit="return confirm(this.dataset.frage)" data-frage="<?= $h($T('ck_loeschen_frage')) ?>">
                <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="ck_weg"><input type="hidden" name="token" value="<?= $h($c['token']) ?>">
                <button class="knopf klein-knopf ck-weg" type="submit"><?= $h($T('ck_loeschen')) ?></button></form>
            </span></div></li>
      <?php endforeach; ?>
    </ul>
    </details>
  <?php endif; ?>

  <h3 class="md-h" style="margin-top:24px"><?= $h($T('fi_titel')) ?></h3>
  <p class="klein" style="margin-top:0"><?= $h($T('fi_text')) ?></p>
  <?php if ($fiMeldung): ?><div class="hinweis schlecht" role="alert"><?= $h($T($fiMeldung)) ?></div><?php endif; ?>
  <form method="get" action="/partner.php#recherche" data-warten="<?= $h($T('fi_laeuft')) ?>">
    <input type="hidden" name="t" value="<?= $h((string) $p['token']) ?>">
    <div class="reihe">
      <div><label for="fi_ort"><?= $h($T('fi_ort')) ?></label><input id="fi_ort" type="text" name="fi_ort" maxlength="80" required value="<?= $h($fiOrt) ?>" autocomplete="address-level2"></div>
      <div><label for="fi_branche"><?= $h($T('fi_branche')) ?></label>
        <select id="fi_branche" name="fi_branche"><option value=""><?= $h($T('fi_alle')) ?></option>
          <?php foreach ($branchenListe as $bk => $bv): ?><option value="<?= $h($bk) ?>"<?= $bk === $fiBranche ? ' selected' : '' ?>><?= $h(Akquise::branchenName($bk, $sprache)) ?></option><?php endforeach; ?>
        </select></div>
      <button class="knopf" type="submit"><?= $h($T('fi_suchen')) ?></button>
    </div>
  </form>
  <?php if ($fiErg !== null): ?>
    <?php $fiWeb = $fiErg['web'] ?? null; ?>
    <?php if ($fiWeb && $fiWeb['fehler'] !== null && $fiWeb['fehler'] !== 'läuft'): ?><div class="hinweis" style="margin-top:10px"><?= $h($T('fi_web_fehler')) ?></div>
    <?php elseif ($fiWeb && $fiWeb['gefragt'] && $fiWeb['neu'] > 0): ?><p class="klein" role="status"><?= $h(strtr($T('fi_web_neu'), ['{n}' => (string) $fiWeb['neu']])) ?></p><?php endif; ?>
    <?php if (!$fiErg['ok']): ?><div class="hinweis" style="margin-top:10px"><?= $h($T($fiErg['grund'] === 'fi_ort' ? 'fi_keine' : $fiErg['grund'])) ?></div>
    <?php elseif (!$fiErg['treffer']): ?><p class="klein"><?= $h($T('fi_keine')) ?></p>
    <?php else: ?>
      <?php $fiZeigen = array_slice($fiErg['treffer'], 0, 5); $fiRest = array_slice($fiErg['treffer'], 5); ?>
      <ul class="firmen"><?php foreach ($fiZeigen as $f) { echo $firmaZeile($f, false); } ?></ul>
      <?php if ($fiRest): ?><details class="weitere"><summary><?= $h(strtr($T('kl_weitere'), ['{n}' => (string) count($fiRest)])) ?></summary>
        <ul class="firmen"><?php foreach ($fiRest as $f) { echo $firmaZeile($f, false); } ?></ul></details><?php endif; ?>
      <p class="klein"><?= $h($T('fi_hinweis')) ?></p>
      <p class="klein" style="margin-top:4px;font-size:11.5px"><?= $h($T('fi_osm')) ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($fiOrt !== ''): $fiLinks = PartnerRecherche::suchlinks($fiOrt, $fiBranche, $sprache); if ($fiLinks): ?>
    <p class="md-l" style="margin-top:16px"><?= $h($T('fi_quellen')) ?></p>
    <p class="klein" style="margin-top:0"><?= $h($T('fi_quellen_text')) ?></p>
    <div class="ck-knoepfe" style="margin-top:6px">
      <?php foreach ($fiLinks as $fl): ?><a class="knopf klein-knopf" href="<?= $h($fl['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $h(Texte::h(Texte::PARTNER['fi_q'][$fl['art']], $sprache)) ?> ↗</a><?php endforeach; ?>
    </div>
  <?php endif; endif; ?>

  <details id="eintragen" style="margin-top:16px"<?= $feFehler || ($_GET['m'] ?? '') === 'fe_gut' ? ' open' : '' ?>>
    <summary style="cursor:pointer;color:var(--cyan);font-size:14.5px"><?= $h($T('fe_titel')) ?></summary>
    <?php if (($_GET['m'] ?? '') === 'fe_gut'): ?><div class="hinweis gut" role="status" style="margin-top:8px"><?= $h($T('fe_gut')) ?></div><?php endif; ?>
    <?php if ($feFehler): ?><div class="hinweis schlecht" role="alert" style="margin-top:8px"><?= $h(Texte::h($feFehler, $sprache)) ?></div><?php endif; ?>
    <form method="post" action="<?= $h($selbst()) ?>#eintragen" style="margin-top:10px">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="fe_eintragen">
      <label for="fe_name"><?= $h($T('fe_name')) ?></label><input id="fe_name" type="text" name="name" required maxlength="160" value="<?= $h((string) ($feWahl['name'] ?? '')) ?>">
      <div class="reihe">
        <div><label for="fe_ort"><?= $h($T('fe_ort')) ?></label><input id="fe_ort" type="text" name="ort" required maxlength="80" value="<?= $h((string) ($feWahl['ort'] ?? $fiOrt)) ?>"></div>
        <div><label for="fe_branche"><?= $h($T('fe_branche')) ?></label>
          <select id="fe_branche" name="branche" required><option value=""></option>
            <?php foreach ($branchenListe as $bk => $bv): ?><option value="<?= $h($bk) ?>"<?= $bk === (string) ($feWahl['branche'] ?? $fiBranche) ? ' selected' : '' ?>><?= $h(Akquise::branchenName($bk, $sprache)) ?></option><?php endforeach; ?>
          </select></div>
        <div style="flex:0 1 90px"><label for="fe_land">Land</label><select id="fe_land" name="land"><option value="IT">IT</option><option value="DE"<?= ($feWahl['land'] ?? '') === 'DE' ? ' selected' : '' ?>>DE</option></select></div>
      </div>
      <label for="fe_adresse"><?= $h($T('fe_adresse')) ?></label><input id="fe_adresse" type="text" name="adresse" maxlength="200" value="<?= $h((string) ($feWahl['adresse'] ?? '')) ?>" autocomplete="off">
      <label for="fe_website"><?= $h($T('fe_website')) ?></label><input id="fe_website" type="text" name="website" maxlength="300" inputmode="url" autocapitalize="off" spellcheck="false" value="<?= $h((string) ($feWahl['website'] ?? '')) ?>" placeholder="www…">
      <button class="knopf" type="submit"><?= $h($T('fe_knopf')) ?></button>
    </form>
  </details>

  <?php if ($fiMeine): ?>
    <details class="klapp" data-klapp="fi_meine" style="margin-top:16px"<?= count($fiMeine) <= 3 ? ' open' : '' ?>>
    <summary><span class="md-l"><?= $h($T('fi_meine')) ?></span><span class="klapp__zahl"><?= count($fiMeine) ?></span></summary>
    <ul class="firmen"><?php foreach ($fiMeine as $f) { echo $firmaZeile($f, true); } ?></ul>
    </details>
  <?php endif; ?>

  <div class="leitfaden" id="leitfaden" style="margin-top:22px">
    <h3 class="md-h"><?= $h($T('lf_titel')) ?></h3>
    <?php foreach (Texte::PARTNER_LEITFADEN as $li => $abschnitt): ?>
      <details><summary><?= $h(PartnerVorlagen::text("leitfaden.$li.titel", $sprache, Texte::h($abschnitt['titel'], $sprache))) ?></summary><pre><?= $h(PartnerVorlagen::text("leitfaden.$li.text", $sprache, Texte::h($abschnitt['text'], $sprache))) ?></pre></details>
    <?php endforeach; ?>
  </div>
</div>
<script>
/* Sprache der Nachricht umschalten (Kontaktieren). */
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-ak-sp]'); if (!b) { return; }
  var id = b.dataset.ak;
  [].forEach.call(document.querySelectorAll('[data-ak="' + id + '"]'), function (a) { a.setAttribute('aria-pressed', a === b ? 'true' : 'false'); });
  [].forEach.call(document.querySelectorAll('[data-ak-text^="' + id + '-"]'), function (t) { t.hidden = t.dataset.akText !== id + '-' + b.dataset.akSp; });
});
/* Schnellcheck und Websuche dauern ein paar Sekunden: Knopf sperren und sagen, was passiert. */
(function () {
  [].forEach.call(document.querySelectorAll('#recherche form[data-warten]'), function (f) {
    f.addEventListener('submit', function () { var b = f.querySelector('button[type=submit]'); b.disabled = true; b.textContent = f.dataset.warten; });
  });
})();
</script>
