<?php
/* ==========================================================================
   Kampagnen im Command Center (Etappe 2, 05.10.2026): der Assistent (vier
   Fragen) und die Seite einer Kampagne (Strategie, Paket, Ergebnis, Ziel des
   Links, Status). Eingebunden aus partner_cc.php; dort stehen Kopf, Leiste
   und Fuß. Erwartet zusätzlich: $ccSeite ('neu' | 'kampagne'), $ccK (Kampagne
   oder null), $ccKatalog (Werbemittel::katalog des Partners), $ccMeldung.
   ========================================================================== */
$K = Texte::PARTNER_KAMPAGNE;
$k = static fn(array $t, array $r = []): string => strtr(Texte::h($t, $sprache), $r);
?>
<main id="cc-start" tabindex="-1">
<p class="cc-zurueck"><a href="<?= $h($selbst(['cc' => 1])) ?>">← <?= $h($k($K['zurueck'])) ?></a></p>

<?php if ($ccSeite === 'neu'):
  $ccPf = PartnerCommand::profil($p);
  $ccWahlZiel = (string) ($ccPost['ziel'] ?? ($ccPf['ziel'] !== '' ? $ccPf['ziel'] : ''));
  $ccWahlBr = (string) ($ccPost['branche'] ?? ($ccPf['branchen'][0] ?? ''));
  $ccWahlOrt = (string) ($ccPost['region'] ?? $ccPf['ort']);
  $ccWahlSp = (string) ($ccPost['sprache'] ?? (string) $p['sprache']);
  $ccWahlWeg = (string) ($ccPost['weg'] ?? 'auto');
  $ccWahlBudget = (string) ($ccPost['budget'] ?? ''); ?>
  <div class="cc-hallo cc-auf"><h1><?= $h($k($K['neu'])) ?><span class="cc-frage"><?= $h($k($K['neu_satz'])) ?></span></h1></div>
  <?php if ($ccMeldung === 'k_fehler' || $ccMeldung === 'k_zu_viele'): ?>
    <div class="hinweis schlecht" role="alert" style="margin:0 0 16px"><?= $h($k($K[$ccMeldung === 'k_fehler' ? 'fehler' : 'zu_viele'])) ?></div>
  <?php endif; ?>
  <section class="cc-karte cc-profil cc-auf z2">
    <form method="post" action="<?= $h($selbst(['cc' => 1, 'kampagne' => 'neu'])) ?>" class="cc-assistent" data-stand="<?= $h($c($C['pf_schritt'])) ?>">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="kampagne_neu">
      <fieldset class="cc-schritt">
        <legend><?= $h($k($K['f_ziel'])) ?></legend>
        <div class="cc-chips">
          <?php foreach (PartnerKampagne::ZIELE as $z): ?>
            <label class="cc-chip"><input type="radio" name="ziel" value="<?= $h($z) ?>"<?= $ccWahlZiel === $z ? ' checked' : '' ?>><span><?= $h($k(Texte::KAMPAGNE_ZIELE[$z])) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <fieldset class="cc-schritt">
        <legend><?= $h($k($K['f_branche'])) ?></legend>
        <div class="cc-chips">
          <?php foreach (PartnerKampagne::BRANCHEN as $b): ?>
            <label class="cc-chip"><input type="radio" name="branche" value="<?= $h($b) ?>"<?= $ccWahlBr === $b ? ' checked' : '' ?>><span><?= $h($k(Texte::KAMPAGNE_BRANCHEN[$b])) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <fieldset class="cc-schritt">
        <legend><label for="cc-k-ort"><?= $h($k($K['f_region'])) ?></label></legend>
        <input type="text" id="cc-k-ort" name="region" maxlength="60" value="<?= $h($ccWahlOrt) ?>" placeholder="<?= $h($k($K['f_region_ph'])) ?>" autocomplete="address-level2">
        <label for="cc-k-sp" class="cc-feldname"><?= $h($k($K['f_sprache'])) ?></label>
        <select id="cc-k-sp" name="sprache">
          <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
            <option value="<?= $l ?>"<?= $ccWahlSp === $l ? ' selected' : '' ?>><?= $h($wie) ?></option>
          <?php endforeach; ?>
        </select>
      </fieldset>
      <fieldset class="cc-schritt">
        <legend><?= $h($k($K['f_weg'])) ?></legend>
        <div class="cc-chips">
          <label class="cc-chip"><input type="radio" name="weg" value="auto"<?= $ccWahlWeg === 'auto' ? ' checked' : '' ?>><span><?= $h(['it' => 'Adatto all’obiettivo', 'de' => 'Passend zum Ziel', 'en' => 'To suit the goal'][$sprache] ?? 'Passend zum Ziel') ?></span></label>
          <?php foreach (PartnerKampagne::WEGE as $w): ?>
            <label class="cc-chip"><input type="radio" name="weg" value="<?= $h($w === '' ? 'seite' : $w) ?>"<?= $ccWahlWeg === ($w === '' ? 'seite' : $w) ? ' checked' : '' ?>><span><?= $h($k($K['wege'][$w])) ?></span></label>
          <?php endforeach; ?>
        </div>
        <label for="cc-k-budget" class="cc-feldname"><?= $h($k($K['f_budget'])) ?></label>
        <input type="text" id="cc-k-budget" name="budget" inputmode="decimal" maxlength="9" value="<?= $h($ccWahlBudget) ?>" placeholder="150" style="max-width:160px">
        <p class="hilfe"><?= $h($k($K['f_budget_hilfe'])) ?></p>
      </fieldset>
      <div class="cc-fuss">
        <button class="knopf" type="button" data-cc-zurueck hidden><?= $h($c($C['pf_zurueck'])) ?></button>
        <button class="knopf haupt" type="button" data-cc-weiter hidden><?= $h($c($C['pf_weiter'])) ?></button>
        <button class="knopf haupt" type="submit" data-cc-speichern><?= $h($k($K['erstellen'])) ?></button>
        <span class="stand" data-cc-stand aria-live="polite"></span>
      </div>
    </form>
  </section>

<?php elseif ($ccK):
  $ccKid = (int) $ccK['id'];
  $ccPaket = PartnerKampagne::paket($p, $ccK, $sprache, $ccKatalog);
  $ccBudget = PartnerKampagne::budget($ccPaket, $ccK['budget_cents'] !== null ? (int) $ccK['budget_cents'] : null);
  $ccKZ = PartnerKampagne::zahlen((int) $p['id'], $ccKid) ?? [];
  $ccMat = Db::all("SELECT e.id, e.status, e.scans, e.version, COALESCE(NULLIF(w.name_$sprache, ''), w.name_it) AS produkt FROM wm_entwuerfe e JOIN wm_produkte w ON w.id = e.produkt_id
                     WHERE e.partner_id = ? AND e.kampagne_id = ? AND e.status IN ('entwurf','freigegeben','ersetzt') ORDER BY e.id DESC", [(int) $p['id'], $ccKid]);
  $ccSt = (string) $ccK['status']; ?>
  <div class="cc-hallo cc-auf">
    <p class="cc-auge"><?= $h(PartnerKampagne::nummer($ccK)) ?> · <span class="cc-status s-<?= $h($ccSt) ?>"><?= $h($k($K['status'][$ccSt])) ?></span></p>
    <h1 style="font-size:clamp(26px,5.4vw,40px)"><?= $h((string) $ccK['name']) ?></h1>
  </div>
  <?php if ($ccMeldung === 'k_erstellt'): ?><div class="hinweis gut cc-auf" role="status" style="margin:0 0 16px"><?= $h($k($K['erstellt'])) ?></div><?php endif; ?>
  <?php if ($ccMeldung === 'k_gut'): ?><div class="hinweis gut cc-auf" role="status" style="margin:0 0 16px"><?= $h($k($K['gespeichert'])) ?></div><?php endif; ?>
  <?php if ($ccSt === 'pausiert'): ?><div class="hinweis" style="margin:0 0 16px"><?= $h($k($K['pausiert_satz'])) ?></div><?php endif; ?>

  <div class="cc-raster">
    <section class="cc-heute cc-auf z2" aria-labelledby="cc-strat-t">
      <p class="cc-auge" id="cc-strat-t"><?= $h($k($K['strategie'])) ?></p>
      <?php foreach (PartnerKampagne::strategie($ccK, $sprache) as $i => $satz): ?>
        <p class="<?= $i === 0 ? 'cc-strat1' : 'cc-warum' ?>"><?= $h($satz) ?></p>
      <?php endforeach; ?>
      <p class="cc-budget"><?= $h($k($K['budget_ab'], ['{summe}' => Fmt::geld($ccBudget['summe']), '{n}' => (string) $ccBudget['n']])) ?>
        <?php if ($ccBudget['budget'] !== null): ?><br><?= $h($ccBudget['reicht'] ? $k($K['budget_ok'], ['{budget}' => Fmt::geld($ccBudget['budget'])])
            : ($ccBudget['deckt'] ? $k($K['budget_teil'], ['{budget}' => Fmt::geld($ccBudget['budget']), '{liste}' => implode(', ', $ccBudget['deckt'])])
            : $k($K['budget_kein'], ['{budget}' => Fmt::geld($ccBudget['budget'])]))) ?><?php endif; ?></p>
    </section>

    <section class="cc-auf z3" aria-labelledby="cc-zahl-t">
      <h2 class="cc-titel" id="cc-zahl-t"><?= $h($k($K['zahlen'])) ?></h2>
      <div class="cc-zahlen cc-zahlen-klein">
        <?php foreach ($K['z'] as $zk => $zn): $zw = (int) ($ccKZ[$zk] ?? 0); ?>
          <div class="cc-zahl<?= $zw === 0 ? ' null' : '' ?><?= $zk === 'provision_cents' && $zw > 0 ? ' gold' : '' ?>"><span class="l"><?= $h($k($zn)) ?></span>
            <b><?= $h($zk === 'provision_cents' ? Fmt::geld($zw) : (string) $zw) ?></b></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="cc-breit cc-auf z3" aria-labelledby="cc-paket-t">
      <h2 class="cc-titel" id="cc-paket-t"><?= $h($k($K['paket'])) ?></h2>
      <ul class="cc-paket">
        <?php foreach ($ccPaket as $i => $x): $xid = 'cc-x' . $i; ?>
          <li class="cc-teil a-<?= $h($x['art']) ?>">
            <div class="cc-teil-kopf"><b><?= $h($x['titel']) ?></b><small><?= $h($x['satz']) ?></small></div>
            <?php if ($x['art'] === 'link'): ?>
              <div class="cc-kopie"><input id="<?= $xid ?>" type="text" readonly value="<?= $h($x['link']) ?>" aria-label="<?= $h($x['titel']) ?>">
                <button class="knopf" type="button" data-cc-kopie="<?= $xid ?>" data-fertig="<?= $h($k($K['kopiert'])) ?>"><?= $h($k($K['kopieren'])) ?></button></div>
            <?php elseif (isset($x['text'])): ?>
              <?php if (!empty($x['betreff'])): ?><input type="text" readonly value="<?= $h($x['betreff']) ?>" aria-label="<?= $h($x['titel']) ?>" class="cc-betreff"><?php endif; ?>
              <textarea id="<?= $xid ?>" readonly rows="5"><?= $h($x['text']) ?></textarea>
              <div class="knoepfe">
                <button class="knopf" type="button" data-cc-kopie="<?= $xid ?>" data-fertig="<?= $h($k($K['kopiert'])) ?>"><?= $h($k($K['kopieren'])) ?></button>
                <?php if (!empty($x['teilen'])): ?><a class="knopf" href="<?= $h($x['teilen']) ?>" target="_blank" rel="noopener"><?= $h($k($K['wa_oeffnen'])) ?></a><?php endif; ?>
              </div>
            <?php elseif ($x['art'] === 'story'): ?>
              <a class="knopf" href="<?= $h($selbst() . '#medien') ?>"><?= $h($k($K['oeffnen'])) ?> →</a>
            <?php elseif (isset($x['produkt_id'])): ?>
              <a class="knopf haupt" href="<?= $h($selbst(['kampagne' => $ccKid]) . '#wm-p' . (int) $x['produkt_id']) ?>"><?= $h($k($K['gestalten'])) ?> →</a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($ccMat): ?>
        <h3 class="cc-titel" style="margin-top:18px"><?= $h($k($K['material'])) ?></h3>
        <ul class="cc-mat">
          <?php foreach ($ccMat as $m): ?>
            <li><span><?= $h((string) $m['produkt']) ?> · V<?= (int) $m['version'] ?></span><span><?= (int) $m['scans'] ?> <?= $h($k($K['z']['scans'])) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="cc-breit cc-karte cc-auf z4" aria-labelledby="cc-weg-t">
      <h2 class="cc-titel" id="cc-weg-t" style="margin-bottom:4px"><?= $h($k($K['weg_aendern'])) ?></h2>
      <p class="hilfe" style="margin:0 0 12px"><?= $h($k($K['weg_satz'])) ?></p>
      <form method="post" action="<?= $h($selbst(['cc' => 1, 'kampagne' => $ccKid])) ?>">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="kampagne_weg"><input type="hidden" name="id" value="<?= $ccKid ?>">
        <div class="cc-chips" role="radiogroup" aria-labelledby="cc-weg-t">
          <?php foreach (PartnerKampagne::WEGE as $w): ?>
            <label class="cc-chip"><input type="radio" name="weg" value="<?= $h($w === '' ? 'seite' : $w) ?>"<?= (string) $ccK['ziel_weg'] === $w ? ' checked' : '' ?>><span><?= $h($k($K['wege'][$w])) ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="cc-fuss" style="margin-top:12px"><button class="knopf" type="submit"><?= $h($k($K['speichern'])) ?></button></div>
      </form>
      <form method="post" action="<?= $h($selbst(['cc' => 1, 'kampagne' => $ccKid])) ?>" class="cc-fuss" style="margin-top:18px;border-top:1px solid var(--linie);padding-top:14px">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="kampagne_status"><input type="hidden" name="id" value="<?= $ccKid ?>">
        <?php if ($ccSt === 'aktiv'): ?><button class="knopf" name="status" value="pausiert"><?= $h($k($K['pausieren'])) ?></button><?php endif; ?>
        <?php if ($ccSt === 'pausiert'): ?><button class="knopf haupt" name="status" value="aktiv"><?= $h($k($K['fortsetzen'])) ?></button><?php endif; ?>
      </form>
      <?php if ($ccSt !== 'beendet'): /* Rückfrage ohne Dialogfenster: erst aufklappen, dann bestätigen */ ?>
        <details class="cc-ende">
          <summary class="knopf stumm"><?= $h($k($K['beenden'])) ?></summary>
          <form method="post" action="<?= $h($selbst(['cc' => 1, 'kampagne' => $ccKid])) ?>">
            <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="kampagne_status"><input type="hidden" name="id" value="<?= $ccKid ?>">
            <p class="hilfe" style="margin:8px 0 10px"><?= $h($k($K['beenden_frage'])) ?></p>
            <button class="knopf" name="status" value="beendet"><?= $h($k($K['beenden'])) ?></button>
          </form>
        </details>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>
</main>
