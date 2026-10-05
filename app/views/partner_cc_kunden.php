<?php
/* ==========================================================================
   KUNDEN im Command Center (Phase 2, 05.10.2026, PartnerLeads; Spezifikation
   Punkt 6–10, 27). Zwei Seiten:
     kunden — die Pipeline: + Kontakt (mit Dublettenhinweis), Stufen, Liste
              nach Priorität, dann „Über deinen Link gekommen“ (ohne Namen)
              und die Werkzeuge zum Finden neuer Kunden.
     lead   — die Akte: Schnellfunktionen, Stufe, Priorität, nächster
              Schritt, Notizen, Aufgaben, Verlauf, Daten, Übergabe.
   Erwartet aus partner_cc.php: $p, $sprache, $h, $selbst, $c, $ccSeite, $ccLead,
   $ccLeadMeldung, $ccLeadPost, $ccLeadDup.
   ========================================================================== */
require_once dirname(__DIR__) . '/src/PartnerLeads.php';
require_once dirname(__DIR__) . '/src/PartnerAnschreiben.php';
require_once dirname(__DIR__) . '/src/PartnerPost.php';
require_once dirname(__DIR__) . '/src/Akquise.php';
$L = Texte::PARTNER_LEADS;
$l = static fn(array $t, array $r = []): string => strtr(Texte::h($t, $sprache), $r);
$lDatum = static fn(?string $d): string => $d ? date($sprache === 'en' ? 'd/m/Y' : ($sprache === 'de' ? 'd.m.Y' : 'd/m/Y'), (int) strtotime($d)) : '';
$lBranche = static fn(string $k): string => $k === '' ? '' : Akquise::branchenName($k, $sprache);
$lMeld = static function (string $m) use ($L, $l): array {
    if ($m === 'angelegt' || $m === 'gespeichert' || $m === 'ue_gut') { return ['gut', $l($L[$m])]; }
    if (str_starts_with($m, 'import:')) { [, $n, $s] = array_pad(explode(':', $m), 3, '0'); return ['gut', $l($L['import_gut'], ['{neu}' => $n, '{schon}' => $s])]; }
    return isset($L['fehler'][$m]) ? ['schlecht', $l($L['fehler'][$m])] : ['', ''];
};
[$lmArt, $lmText] = $lMeld((string) ($ccLeadMeldung ?? ''));
$lAkte = static fn(int $id): string => $selbst(['cc' => 1, 'lead' => $id]);
$lListe = static fn(array $x = []): string => $selbst(['cc' => 1, 'kunden' => 1] + $x);
$lFormKopf = static fn(string $tat, int $id = 0): string => '<input type="hidden" name="_csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tat" value="' . $h($tat) . '">'
    . ($id > 0 ? '<input type="hidden" name="id" value="' . $id . '">' : '');
$lBranchen = Akquise::branchen();
uasort($lBranchen, static fn($a, $b) => strcoll((string) ($a[$sprache] ?? $a['de']), (string) ($b[$sprache] ?? $b['de'])));
?>
<?php if ($ccSeite === 'kunden'):
  $lStufe = in_array((string) ($_GET['stufe'] ?? ''), PartnerLeads::STUFEN, true) ? (string) $_GET['stufe'] : null;
  $lZahl = PartnerLeads::zaehlen((int) $p['id']);
  $lAlle = PartnerLeads::liste((int) $p['id'], $lStufe);
  $lArchiv = Db::all('SELECT id, name, stufe FROM partner_leads WHERE partner_id = ? AND archiviert_am IS NOT NULL ORDER BY archiviert_am DESC LIMIT 50', [(int) $p['id']]);
  $lPost = is_array($ccLeadPost ?? null) ? $ccLeadPost : [];
  $lDup = is_array($ccLeadDup ?? null) ? $ccLeadDup : [];
  $lNeuOffen = $lDup || in_array((string) ($ccLeadMeldung ?? ''), ['name', 'genug'], true) || isset($_GET['neu']);
  $lAktiv = array_sum($lZahl);   // „Alle“ = alles, was die Liste ohne Filter zeigt (Archiv nicht)
?>
<main id="cc-start" tabindex="-1" class="cc-kunden">
  <div class="cc-seitenkopf cc-auf">
    <h1><?= $h($l($L['titel'])) ?></h1>
    <p class="cc-lead"><?= $h($l($L['satz'])) ?></p>
  </div>
  <?php if ($lmText !== ''): ?><div class="hinweis <?= $h($lmArt) ?> cc-auf" role="<?= $lmArt === 'gut' ? 'status' : 'alert' ?>" style="margin:0 0 16px"><?= $h($lmText) ?></div><?php endif; ?>

  <details class="cc-neu cc-auf z2" id="neu"<?= $lNeuOffen ? ' open' : '' ?>>
    <summary class="knopf haupt">+ <?= $h($l($L['neu'])) ?></summary>
    <form method="post" action="<?= $h($lListe()) ?>#neu" class="cc-karte cc-form">
      <?= $lFormKopf('lead_neu') ?>
      <?php if ($lDup): ?>
        <div class="cc-dup" role="alert">
          <p><b><?= $h($l($L['dup_titel'])) ?></b></p>
          <ul>
            <?php foreach ($lDup as $d): ?>
              <li><?php if ($d['art'] === 'eigen'): ?><a href="<?= $h($lAkte((int) $d['id'])) ?>"><?= $h((string) $d['name']) ?></a> · <?= $h($l($L['dup_grund'][$d['grund']])) ?>
                <?php else: ?><?= $h($l($L['dup_betreut'])) ?><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
          <input type="hidden" name="trotzdem" value="1">
        </div>
      <?php endif; ?>
      <div class="cc-felder">
        <label class="cc-feld breit"><span><?= $h($l($L['f']['name'])) ?> *</span><input name="name" required minlength="2" maxlength="120" value="<?= $h((string) ($lPost['name'] ?? '')) ?>" autocomplete="organization"></label>
        <label class="cc-feld"><span><?= $h($l($L['f']['ansprechpartner'])) ?></span><input name="ansprechpartner" maxlength="80" value="<?= $h((string) ($lPost['ansprechpartner'] ?? '')) ?>" autocomplete="name"></label>
        <label class="cc-feld"><span><?= $h($l($L['f']['branche'])) ?></span><select name="branche"><option value=""><?= $h($l($L['f']['keine'])) ?></option>
          <?php foreach ($lBranchen as $bk => $bv): ?><option value="<?= $h($bk) ?>"<?= ($lPost['branche'] ?? '') === $bk ? ' selected' : '' ?>><?= $h((string) ($bv[$sprache] ?? $bv['de'])) ?></option><?php endforeach; ?></select></label>
        <label class="cc-feld"><span><?= $h($l($L['f']['ort'])) ?></span><input name="ort" maxlength="80" value="<?= $h((string) ($lPost['ort'] ?? $p['heimatort'] ?? '')) ?>" autocomplete="address-level2"></label>
        <label class="cc-feld"><span><?= $h($l($L['f']['telefon'])) ?></span><input name="telefon" type="tel" maxlength="40" value="<?= $h((string) ($lPost['telefon'] ?? '')) ?>" autocomplete="tel"></label>
        <label class="cc-feld"><span><?= $h($l($L['f']['email'])) ?></span><input name="email" type="email" maxlength="190" value="<?= $h((string) ($lPost['email'] ?? '')) ?>" autocomplete="email"></label>
        <label class="cc-feld"><span><?= $h($l($L['f']['website'])) ?></span><input name="website" maxlength="255" inputmode="url" value="<?= $h((string) ($lPost['website'] ?? '')) ?>" placeholder="www.…"></label>
        <label class="cc-feld"><span><?= $h($l($L['f']['quelle'])) ?></span><select name="quelle">
          <?php foreach (PartnerLeads::QUELLEN_HAND as $q): ?><option value="<?= $h($q) ?>"<?= ($lPost['quelle'] ?? 'eigen') === $q ? ' selected' : '' ?>><?= $h($l($L['quellen'][$q])) ?></option><?php endforeach; ?></select></label>
        <label class="cc-feld breit"><span><?= $h($l($L['f']['notiz'])) ?></span><textarea name="notiz" rows="2" maxlength="1000"><?= $h((string) ($lPost['notiz'] ?? '')) ?></textarea></label>
      </div>
      <div class="cc-fuss"><button class="knopf haupt" type="submit"><?= $h($l($lDup ? $L['dup_trotzdem'] : $L['speichern'])) ?></button></div>
    </form>
  </details>

  <?php /* „Meine Kontakte“ lagen nur im Browser (localStorage). Übernommen wird nur auf Klick — partner-cc.js zeigt den
     Kasten, wenn dort etwas liegt und noch nicht übernommen wurde. */ ?>
  <div class="cc-import cc-karte" data-cc-import="<?= $h('vecom_kontakte_' . strtolower((string) $p['code'])) ?>" hidden>
    <p><b><?= $h($l($L['import_titel'])) ?></b><br><span data-cc-import-text data-vorlage="<?= $h($l($L['import_text'])) ?>"></span></p>
    <form method="post" action="<?= $h($lListe()) ?>"><?= $lFormKopf('lead_import') ?><input type="hidden" name="kontakte" value="">
      <button class="knopf" type="submit"><?= $h($l($L['import_knopf'])) ?></button></form>
  </div>

  <nav class="cc-pipeline cc-auf z2" aria-label="<?= $h($l($L['stufe'])) ?>">
    <a href="<?= $h($lListe()) ?>"<?= $lStufe === null ? ' aria-current="page"' : '' ?>><b><?= $lAktiv ?></b><span><?= $h($l($L['alle'])) ?></span></a>
    <?php foreach (PartnerLeads::STUFEN as $st): ?>
      <a href="<?= $h($lListe(['stufe' => $st])) ?>" class="s-<?= $h($st) ?><?= $lZahl[$st] === 0 ? ' null' : '' ?>"<?= $lStufe === $st ? ' aria-current="page"' : '' ?>><b><?= (int) $lZahl[$st] ?></b><span><?= $h($l($L['stufen'][$st])) ?></span></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$lAlle): ?>
    <p class="cc-leer cc-auf z3"><?= $h($l($lStufe === null ? $L['leer'] : $L['leer_stufe'])) ?></p>
  <?php else: ?>
    <ul class="cc-leads cc-auf z3">
      <?php foreach ($lAlle as $ld): $heute = date('Y-m-d'); $fae = (string) ($ld['aufgabe_am'] ?? $ld['naechster_am'] ?? '');
        $tel = trim((string) $ld['telefon']); $wa = $tel !== '' ? PartnerAnschreiben::waNummer($tel, (string) ($p['land'] ?? 'IT') ?: 'IT') : ''; ?>
        <li class="p-<?= $h($ld['prio']) ?>">
          <a class="cc-lead-zeile" href="<?= $h($lAkte((int) $ld['id'])) ?>">
            <i class="cc-punkt <?= $h(['heiss' => 'rot', 'warm' => 'gelb', 'normal' => 'blau', 'spaeter' => 'grau'][$ld['prio']]) ?>" aria-hidden="true"></i>
            <span class="cc-lead-name"><b><?= $h((string) $ld['name']) ?></b>
              <small><?= $h(implode(' · ', array_filter([$lBranche((string) $ld['branche']), (string) $ld['ort'],
                  $fae !== '' ? $l($L[$fae < $heute ? 'ueberfaellig' : 'faellig_kurz'], ['{datum}' => $lDatum($fae)]) : '']))) ?></small></span>
            <span class="cc-stufe s-<?= $h((string) $ld['stufe']) ?>"><?= $h($l($L['stufen'][(string) $ld['stufe']])) ?></span>
            <span class="sr-nur"> — <?= $h($l($L['prio'][$ld['prio']])) ?></span>
          </a>
          <?php if ($tel === ''): ?><span class="cc-mini leer" aria-hidden="true"></span><?php else: ?>
            <a class="cc-mini" href="tel:<?= $h(preg_replace('~[^\d+]~', '', $tel)) ?>" data-cc-kontakt="anruf" data-id="<?= (int) $ld['id'] ?>" aria-label="<?= $h($l($L['schnell']['anrufen']) . ': ' . $ld['name']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($lArchiv): ?>
    <details class="cc-mehrblick cc-archiv">
      <summary><?= $h($l($L['archiviert'], ['{n}' => (string) count($lArchiv)])) ?></summary>
      <ul class="cc-archivliste">
        <?php foreach ($lArchiv as $a): ?>
          <li><span><?= $h((string) $a['name']) ?> <small>· <?= $h($l($L['stufen'][(string) $a['stufe']] ?? $L['stufen']['neu'])) ?></small></span>
            <form method="post" action="<?= $h($lListe()) ?>"><?= $lFormKopf('lead_wieder', (int) $a['id']) ?><button class="knopf" type="submit"><?= $h($l($L['wieder'])) ?></button></form></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>

  <?php $lEmp = PartnerPost::empfehlungen((int) $p['id']); $TP = static fn(string $k): string => Texte::h(Texte::PARTNER[$k] ?? [], $sprache); ?>
  <?php if ($lEmp): ?>
    <section class="cc-linkkunden cc-auf z4" aria-labelledby="cc-lk-t">
      <h2 class="cc-titel" id="cc-lk-t"><?= $h($l($L['link_titel'])) ?></h2>
      <p class="hilfe" style="margin:0 0 10px"><?= $h($l($L['link_satz'])) ?></p>
      <ul class="cc-kliste">
        <?php foreach ($lEmp as $e): ?>
          <li><span class="cc-lk"><span><b><?= $h(strtr($TP('emp_nr'), ['{n}' => (string) $e['nr']])) ?></b>
              <small><?= $h(($e['ort'] !== '' ? $e['ort'] . ' · ' : '') . strtr($TP('emp_seit'), ['{datum}' => $lDatum($e['seit'])])) ?></small></span>
            <span class="cc-kz"><b><?= $e['provision'] > 0 ? $h(Fmt::geld((int) $e['provision'])) : '—' ?></b><small><?= $h($TP('emp_s_' . $e['stufe'])) ?></small></span></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="cc-werkzeuge cc-auf z4" aria-labelledby="cc-wz-t">
    <h2 class="cc-titel" id="cc-wz-t"><?= $h($l($L['werkzeuge'])) ?></h2>
    <nav class="cc-schnell">
      <a href="<?= $h($selbst() . '#recherche') ?>"><?= $h($l($L['wz']['finder'])) ?></a>
      <a href="<?= $h($selbst() . '#anrufliste') ?>"><?= $h($l($L['wz']['anrufe'])) ?></a>
      <a href="<?= $h($selbst() . '#schnellcheck') ?>"><?= $h($l($L['wz']['check'])) ?></a>
      <a href="<?= $h($selbst() . '#vorab') ?>"><?= $h($l($L['wz']['vorab'])) ?></a>
    </nav>
  </section>
</main>

<?php elseif ($ccSeite === 'lead' && is_array($ccLead ?? null)):
  $ld = $ccLead; $id = (int) $ld['id'];
  $ldPrio = PartnerLeads::prioritaet($ld + ['aufgabe_am' => null]);
  $ldVerlauf = PartnerLeads::verlauf((int) $p['id'], $id);
  $ldAufgaben = array_values(array_filter($ldVerlauf, static fn($v) => $v['art'] === 'aufgabe' && $v['erledigt_am'] === null));
  usort($ldAufgaben, static fn($a, $b) => strcmp((string) ($a['faellig_am'] ?? '9999'), (string) ($b['faellig_am'] ?? '9999')));
  $tel = trim((string) $ld['telefon']); $mail = trim((string) $ld['email']);
  // Sprache der Nachricht an den Betrieb: aus der Adresse seiner Website oder E-Mail (.it → Italienisch), sonst wie der Partner.
  $ldHost = (string) $ld['domain'] !== '' ? (string) $ld['domain'] : (string) substr((string) strrchr($mail, '@'), 1);
  $ldSp = $ldHost !== '' ? PartnerAnschreiben::spracheZurAdresse($ldHost, $sprache) : $sprache;
  $ldAn = trim((string) $ld['ansprechpartner']) !== '' ? ' ' . trim((string) $ld['ansprechpartner']) : '';
  $ldErsatz = ['{name}' => $ldAn, '{partner}' => (string) $p['name'], '{link}' => Partner::link($p)];
  $ldWa = $tel !== '' ? PartnerAnschreiben::waNummer($tel, (string) ($p['land'] ?? 'IT') ?: 'IT') : '';
  $ldWaHref = $ldWa !== '' ? 'https://wa.me/' . $ldWa . '?text=' . rawurlencode(strtr(Texte::h($L['wa_text'], $ldSp), $ldErsatz)) : '';
  $ldMailHref = $mail !== '' ? 'mailto:' . rawurlencode($mail) . '?subject=' . rawurlencode(Texte::h($L['mail_betreff'], $ldSp)) . '&body=' . rawurlencode(strtr(Texte::h($L['mail_text'], $ldSp), $ldErsatz)) : '';
  $ldPost = is_array($ccLeadPost ?? null) ? $ccLeadPost : [];
?>
<main id="cc-start" tabindex="-1" class="cc-akte">
  <p class="cc-zurueck"><a href="<?= $h($lListe()) ?>">← <?= $h($l($L['zurueck'])) ?></a></p>
  <div class="cc-seitenkopf cc-auf">
    <h1><i class="cc-punkt <?= $h(['heiss' => 'rot', 'warm' => 'gelb', 'normal' => 'blau', 'spaeter' => 'grau'][$ldPrio]) ?>" aria-hidden="true"></i><?= $h((string) $ld['name']) ?></h1>
    <p class="cc-lead"><?= $h(implode(' · ', array_filter([trim((string) $ld['ansprechpartner']), $lBranche((string) $ld['branche']), (string) $ld['ort'],
        $l($L['quelle_l']) . ': ' . $l($L['quellen'][(string) $ld['quelle']] ?? $L['quellen']['eigen']), $l($L['seit'], ['{datum}' => $lDatum((string) $ld['created_at'])])]))) ?></p>
  </div>
  <?php if ($lmText !== ''): ?><div class="hinweis <?= $h($lmArt) ?>" role="<?= $lmArt === 'gut' ? 'status' : 'alert' ?>" style="margin:0 0 16px"><?= $h($lmText) ?></div><?php endif; ?>
  <?php if (!empty($ld['uebergeben_am'])): ?><div class="hinweis gut" style="margin:0 0 16px"><?= $h($l($L['ue_am'], ['{datum}' => $lDatum((string) $ld['uebergeben_am'])])) ?></div><?php endif; ?>

  <nav class="cc-aktionen cc-auf z2" aria-label="<?= $h($l($L['schnell']['anrufen'])) ?> · <?= $h($l($L['schnell']['whatsapp'])) ?>">
    <a class="<?= $tel === '' ? 'aus' : '' ?>" <?= $tel !== '' ? 'href="tel:' . $h(preg_replace('~[^\d+]~', '', $tel)) . '" data-cc-kontakt="anruf" data-id="' . $id . '"' : 'aria-disabled="true" title="' . $h($l($L['kein_tel'])) . '"' ?>>
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg><span><?= $h($l($L['schnell']['anrufen'])) ?></span></a>
    <a class="<?= $ldWaHref === '' ? 'aus' : '' ?>" <?= $ldWaHref !== '' ? 'href="' . $h($ldWaHref) . '" target="_blank" rel="noopener" data-cc-kontakt="whatsapp" data-id="' . $id . '"' : 'aria-disabled="true" title="' . $h($l($L['kein_tel'])) . '"' ?>>
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9.5c.5 2 2.5 4 4.5 4.5l1-1.2 2 .9-.3 1.6c-3.6.3-8-4.1-7.7-7.7l1.6-.3.9 2z"/></svg><span><?= $h($l($L['schnell']['whatsapp'])) ?></span></a>
    <a class="<?= $ldMailHref === '' ? 'aus' : '' ?>" <?= $ldMailHref !== '' ? 'href="' . $h($ldMailHref) . '" data-cc-kontakt="email" data-id="' . $id . '"' : 'aria-disabled="true" title="' . $h($l($L['kein_mail'])) . '"' ?>>
      <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/></svg><span><?= $h($l($L['schnell']['email'])) ?></span></a>
    <a href="#notiz"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19h4L19 9l-4-4L5 15z"/><path d="M13.5 6.5l4 4"/></svg><span><?= $h($l($L['schnell']['notiz'])) ?></span></a>
    <a href="#aufgabe"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8.5 12l2.5 2.5 5-5"/></svg><span><?= $h($l($L['schnell']['aufgabe'])) ?></span></a>
    <a <?= !empty($ld['uebergeben_am']) ? 'class="aus" aria-disabled="true"' : 'href="#uebergabe" class="gold"' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h12"/><path d="M12 6l6 6-6 6"/><path d="M20 4v16"/></svg><span><?= $h($l($L['schnell']['uebergeben'])) ?></span></a>
  </nav>

  <div class="cc-raster">
    <section class="cc-karte cc-auf z2">
      <form method="post" action="<?= $h($lAkte($id)) ?>" class="cc-zeile" data-cc-auto>
        <?= $lFormKopf('lead_stufe', $id) ?>
        <label class="cc-feld"><span><?= $h($l($L['stufe'])) ?></span><select name="stufe">
          <?php foreach (PartnerLeads::STUFEN as $st): ?><option value="<?= $h($st) ?>"<?= $ld['stufe'] === $st ? ' selected' : '' ?>><?= $h($l($L['stufen'][$st])) ?></option><?php endforeach; ?></select></label>
        <noscript><button class="knopf" type="submit"><?= $h($l($L['speichern'])) ?></button></noscript>
      </form>
      <form method="post" action="<?= $h($lAkte($id)) ?>" class="cc-zeile" data-cc-auto>
        <?= $lFormKopf('lead_prio', $id) ?>
        <label class="cc-feld"><span><?= $h($l($L['prioritaet'])) ?></span><select name="prio">
          <option value=""<?= $ld['prioritaet'] === null ? ' selected' : '' ?>><?= $h($l($L['prio_auto']) . ' (' . $l($L['prio'][$ldPrio]) . ')') ?></option>
          <?php foreach (PartnerLeads::PRIO as $pr): ?><option value="<?= $h($pr) ?>"<?= $ld['prioritaet'] === $pr ? ' selected' : '' ?>><?= $h($l($L['prio'][$pr])) ?></option><?php endforeach; ?></select></label>
        <noscript><button class="knopf" type="submit"><?= $h($l($L['speichern'])) ?></button></noscript>
      </form>
      <form method="post" action="<?= $h($lAkte($id)) ?>">
        <?= $lFormKopf('lead_naechster', $id) ?>
        <label class="cc-feld"><span><?= $h($l($L['naechster'])) ?></span><input name="text" maxlength="160" value="<?= $h((string) $ld['naechster_schritt']) ?>" placeholder="<?= $h($l($L['naechster_ph'])) ?>"></label>
        <div class="cc-zeile"><label class="cc-feld"><span><?= $h($l($L['faellig'])) ?></span><input type="date" name="datum" value="<?= $h((string) ($ld['naechster_am'] ?? '')) ?>"></label>
          <button class="knopf" type="submit"><?= $h($l($L['speichern'])) ?></button></div>
      </form>
    </section>

    <section class="cc-karte cc-auf z3" id="aufgabe">
      <h2 class="cc-titel"><?= $h($l($L['schnell']['aufgabe'])) ?></h2>
      <?php if ($ldAufgaben): ?>
        <ul class="cc-aufgaben">
          <?php foreach ($ldAufgaben as $a): $spaet = $a['faellig_am'] !== null && $a['faellig_am'] < date('Y-m-d'); ?>
            <li class="<?= $spaet ? 'spaet' : '' ?>"><span><?= $h((string) $a['text']) ?><?php if ($a['faellig_am']): ?><small><?= $h($l($L[$spaet ? 'ueberfaellig' : 'faellig_kurz'], ['{datum}' => $lDatum((string) $a['faellig_am'])])) ?></small><?php endif; ?></span>
              <form method="post" action="<?= $h($lAkte($id)) ?>"><?= $lFormKopf('lead_aufgabe_ok', $id) ?><input type="hidden" name="eintrag" value="<?= (int) $a['id'] ?>"><button class="knopf" type="submit">✓ <?= $h($l($L['erledigt'])) ?></button></form></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <form method="post" action="<?= $h($lAkte($id)) ?>#aufgabe">
        <?= $lFormKopf('lead_aufgabe', $id) ?>
        <label class="cc-feld"><span class="sr-nur"><?= $h($l($L['schnell']['aufgabe'])) ?></span><input name="text" maxlength="300" required placeholder="<?= $h($l($L['aufgabe_ph'])) ?>"></label>
        <div class="cc-zeile"><label class="cc-feld"><span><?= $h($l($L['faellig'])) ?></span><input type="date" name="faellig" value="<?= $h(date('Y-m-d', strtotime('+3 days'))) ?>"></label>
          <button class="knopf" type="submit">+ <?= $h($l($L['schnell']['aufgabe'])) ?></button></div>
      </form>
      <form method="post" action="<?= $h($lAkte($id)) ?>#aufgabe" class="cc-nachfassen">
        <?= $lFormKopf('lead_aufgabe', $id) ?><input type="hidden" name="text" value="<?= $h($l($L['nachfassen'])) ?>"><input type="hidden" name="faellig" value="<?= $h(date('Y-m-d', strtotime('+3 days'))) ?>">
        <button class="knopf" type="submit"><?= $h($l($L['nachfassen'])) ?></button>
      </form>
    </section>

    <section class="cc-karte cc-breit cc-auf z3" id="notiz">
      <h2 class="cc-titel"><?= $h($l($L['schnell']['notiz'])) ?></h2>
      <form method="post" action="<?= $h($lAkte($id)) ?>#verlauf">
        <?= $lFormKopf('lead_notiz', $id) ?>
        <label class="cc-feld"><span class="sr-nur"><?= $h($l($L['schnell']['notiz'])) ?></span><textarea name="text" rows="3" maxlength="1000" required placeholder="<?= $h($l($L['notiz_ph'])) ?>"></textarea></label>
        <div class="cc-fuss"><button class="knopf" type="submit"><?= $h($l($L['speichern'])) ?></button></div>
      </form>
    </section>

    <section class="cc-breit cc-auf z4" id="verlauf">
      <h2 class="cc-titel"><?= $h($l($L['verlauf'])) ?></h2>
      <?php if (!$ldVerlauf): ?><p class="cc-leer"><?= $h($l($L['verlauf_leer'])) ?></p><?php else: ?>
        <ol class="cc-verlauf">
          <?php foreach ($ldVerlauf as $v): $txt = (string) $v['text'];
            if ($v['art'] === 'stufe' && str_contains($txt, '→')) { [$von, $nach] = explode('→', explode(' ', $txt)[0], 2);
              $txt = $l($L['stufen'][$von] ?? ['it' => $von, 'de' => $von, 'en' => $von]) . ' → ' . $l($L['stufen'][$nach] ?? ['it' => $nach, 'de' => $nach, 'en' => $nach]); } ?>
            <li class="a-<?= $h((string) $v['art']) ?><?= $v['erledigt_am'] ? ' fertig' : '' ?>"><time datetime="<?= $h((string) $v['created_at']) ?>"><?= $h($lDatum((string) $v['created_at']) . ' ' . date('H:i', (int) strtotime((string) $v['created_at']))) ?></time>
              <b><?= $h($l($L['arten'][(string) $v['art']] ?? $L['arten']['system'])) ?></b><?php if ($txt !== ''): ?><span><?= nl2br($h($txt)) ?></span><?php endif; ?></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <details class="cc-karte cc-breit cc-klapp cc-auf z4" id="daten">
      <summary><?= $h($l($L['daten'])) ?></summary>
      <form method="post" action="<?= $h($lAkte($id)) ?>" class="cc-form">
        <?= $lFormKopf('lead_daten', $id) ?>
        <div class="cc-felder">
          <label class="cc-feld breit"><span><?= $h($l($L['f']['name'])) ?> *</span><input name="name" required minlength="2" maxlength="120" value="<?= $h((string) $ld['name']) ?>"></label>
          <label class="cc-feld"><span><?= $h($l($L['f']['ansprechpartner'])) ?></span><input name="ansprechpartner" maxlength="80" value="<?= $h((string) $ld['ansprechpartner']) ?>"></label>
          <label class="cc-feld"><span><?= $h($l($L['f']['branche'])) ?></span><select name="branche"><option value=""><?= $h($l($L['f']['keine'])) ?></option>
            <?php foreach ($lBranchen as $bk => $bv): ?><option value="<?= $h($bk) ?>"<?= $ld['branche'] === $bk ? ' selected' : '' ?>><?= $h((string) ($bv[$sprache] ?? $bv['de'])) ?></option><?php endforeach; ?></select></label>
          <label class="cc-feld"><span><?= $h($l($L['f']['ort'])) ?></span><input name="ort" maxlength="80" value="<?= $h((string) $ld['ort']) ?>"></label>
          <label class="cc-feld"><span><?= $h($l($L['f']['telefon'])) ?></span><input name="telefon" type="tel" maxlength="40" value="<?= $h($tel) ?>"></label>
          <label class="cc-feld"><span><?= $h($l($L['f']['email'])) ?></span><input name="email" type="email" maxlength="190" value="<?= $h($mail) ?>"></label>
          <label class="cc-feld breit"><span><?= $h($l($L['f']['website'])) ?></span><input name="website" maxlength="255" value="<?= $h((string) $ld['website']) ?>"></label>
        </div>
        <div class="cc-fuss"><button class="knopf" type="submit"><?= $h($l($L['speichern'])) ?></button></div>
      </form>
    </details>

    <?php if (empty($ld['uebergeben_am'])): ?>
    <details class="cc-karte cc-breit cc-klapp cc-auf z4" id="uebergabe"<?= in_array((string) ($ccLeadMeldung ?? ''), ['angaben', 'm_einverstanden', 'm_genug', 'panne'], true) ? ' open' : '' ?>>
      <summary><?= $h($l($L['ue_titel'])) ?></summary>
      <p class="cc-lead" style="margin:0 0 12px"><?= $h($l($L['ue_text'])) ?></p>
      <form method="post" action="<?= $h($lAkte($id)) ?>#uebergabe" class="cc-form">
        <?= $lFormKopf('lead_uebergeben', $id) ?>
        <div class="cc-felder">
          <label class="cc-feld"><span><?= $h($l($L['f']['ansprechpartner'])) ?></span><input name="ansprechpartner" maxlength="80" value="<?= $h((string) ($ldPost['ansprechpartner'] ?? $ld['ansprechpartner'])) ?>"></label>
          <label class="cc-feld"><span><?= $h($l($L['f']['email'])) ?> *</span><input name="email" type="email" required maxlength="190" value="<?= $h((string) ($ldPost['email'] ?? $mail)) ?>"></label>
          <label class="cc-feld"><span><?= $h($l($L['f']['telefon'])) ?></span><input name="telefon" type="tel" maxlength="40" value="<?= $h((string) ($ldPost['telefon'] ?? $tel)) ?>"></label>
          <label class="cc-feld breit"><span><?= $h($l($L['ue_anliegen'])) ?></span><textarea name="anliegen" rows="3" maxlength="1500"><?= $h((string) ($ldPost['anliegen'] ?? '')) ?></textarea></label>
        </div>
        <label class="cc-haken"><input type="checkbox" name="einverstanden" value="1" required> <span><?= $h($l($L['ue_ok'])) ?></span></label>
        <div class="cc-fuss"><button class="knopf haupt" type="submit"><?= $h($l($L['ue_knopf'])) ?></button></div>
      </form>
    </details>
    <?php endif; ?>

    <details class="cc-breit cc-ende cc-auf z4">
      <summary class="knopf"><?= $h($l($L['archiv'])) ?></summary>
      <form method="post" action="<?= $h($lListe()) ?>" class="cc-karte" style="margin-top:10px">
        <?= $lFormKopf('lead_archiv', $id) ?>
        <p class="cc-lead" style="margin:0 0 12px"><?= $h($l($L['archiv_frage'])) ?></p>
        <button class="knopf" type="submit"><?= $h($l($L['archiv_ja'])) ?></button>
      </form>
    </details>
  </div>
</main>
<?php endif; ?>
