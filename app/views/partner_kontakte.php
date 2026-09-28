<?php
/* Meine Kontakte (28.09.2026, Uwe: Ja). Reiter „Finden“. Die Liste liegt NUR
   im Browser des Partners (localStorage) -- Namen aus seinem Umfeld haben
   auf unserem Server nichts zu suchen. Ohne Skript: nur der Hinweis.
   Gesetzt: $p, $sprache, $h. */
$PP = static fn(string $k): string => Texte::h(Texte::PARTNER_PLUS[$k] ?? [], $sprache);
$mkSaetze = [];
foreach (PartnerMarketing::BRANCHEN as $bk) { $mkSaetze[$bk] = Texte::h(Texte::PARTNER_BRANCHEN[$bk]['args'][0], $sprache); }
$mkDaten = [
    'speicher' => 'vecom_kontakte_' . strtolower((string) $p['code']),
    'max' => 30,
    'link' => PartnerWerbung::link($p, 'kontakte'),
    'msg' => $PP('mk_msg'),
    'saetze' => $mkSaetze + ['andere' => ''],
    'branchen' => array_map(static fn(string $b) => Texte::h(Texte::PARTNER_MEDIEN['motive'][$b]['name'], $sprache), array_combine(PartnerMarketing::BRANCHEN, PartnerMarketing::BRANCHEN))
                  + ['andere' => $PP('mk_andere')],
    'status' => array_map(static fn(array $t) => Texte::h($t, $sprache), Texte::PARTNER_PLUS['mk_status']),
    't' => ['nachricht' => $PP('mk_nachricht'), 'weg' => $PP('mk_weg'), 'leer' => $PP('mk_leer'), 'voll' => $PP('mk_voll'), 'nachhaken' => $PP('mk_nachhaken'), 'kopieren' => $PP('ak_kopieren')],
];
?>
<div class="block pt" id="kontakte" data-reiter="finden">
  <h2><?= $h($PP('mk_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($PP('mk_text')) ?></p>
  <script type="application/json" id="kontakte_daten"><?= json_encode($mkDaten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <form class="pp-mk-neu" id="mk_neu" hidden>
    <div class="pp-mk-zeile">
      <div><label for="mk_name"><?= $h($PP('mk_name')) ?></label><input id="mk_name" type="text" maxlength="60" required autocomplete="off"></div>
      <div><label for="mk_branche"><?= $h($PP('mk_branche')) ?></label><select id="mk_branche">
        <?php foreach ($mkDaten['branchen'] as $bk => $bn): ?><option value="<?= $h($bk) ?>"><?= $h($bn) ?></option><?php endforeach; ?></select></div>
    </div>
    <label for="mk_notiz"><?= $h($PP('mk_notiz')) ?></label><input id="mk_notiz" type="text" maxlength="120" autocomplete="off">
    <button class="knopf haupt" style="margin-top:10px"><?= $h($PP('mk_neu')) ?></button>
  </form>
  <p class="klein" id="mk_meldung" role="status" aria-live="polite"></p>
  <ul class="pp-mk" id="mk_liste"></ul>
</div>
