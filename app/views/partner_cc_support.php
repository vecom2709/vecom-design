<?php
/* ==========================================================================
   SUPPORT im Command Center (Phase 7b, 05.10.2026; Spezifikation 40–42).
   Ohne ?ticket: neues Anliegen (Thema, Betreff, Nachricht, Bezug auf einen
   EIGENEN Kontakt oder eine EIGENE Bestellung) und die Liste der Anliegen mit
   Stand. Mit ?ticket=ID: der Verlauf und das Antwortfeld. Frühere Nachrichten
   ohne Ticket stehen zugeklappt darunter.
   Erwartet aus partner.php: $p, $sprache, $h, $selbst, $ccSupFehler, $ccSupPost.
   ========================================================================== */
require_once dirname(__DIR__) . '/src/PartnerTicket.php';
$SU = Texte::PARTNER_SUPPORT;
$su = static fn(array $t, array $r = []): string => strtr(Texte::h($t, $sprache), $r);
$suPid = (int) $p['id'];
$suM = (string) ($ccSupFehler ?? '') !== '' && ($ccSupFehler ?? '') !== 'ok' ? (string) $ccSupFehler : (string) ($_GET['m'] ?? '');
$suGut = in_array($suM, ['neu_ok', 'antw_ok'], true);
$suPost = is_array($ccSupPost ?? null) ? $ccSupPost : ['thema' => '', 'betreff' => '', 'text' => '', 'bezug' => ''];
$suT = isset($_GET['ticket']) ? PartnerTicket::laden($suPid, (int) $_GET['ticket']) : null;
$suDatum = static fn(string $d): string => date($sprache === 'en' ? 'd/m/Y H:i' : 'd.m.Y H:i', strtotime($d) ?: time());
$suStand = static fn(string $st): string => '<span class="cc-stufe tk-' . htmlspecialchars($st, ENT_QUOTES) . '">' . htmlspecialchars(Texte::h(Texte::PARTNER_SUPPORT['staende'][$st] ?? ['it' => $st], $sprache), ENT_QUOTES) . '</span>';
?>
<main id="cc-start" tabindex="-1">
<?php if ($suT): $suV = PartnerTicket::verlauf((int) $suT['id']); if (empty($adminBlick)) { PartnerTicket::gelesen($suPid, (int) $suT['id'], 'partner'); }   // Admin-Ansicht markiert nichts als gelesen ?>
  <p class="cc-zurueck"><a href="<?= $h($selbst(['cc' => 1, 'support' => 1])) ?>">← <?= $h($su($SU['zurueck'])) ?></a></p>
  <div class="cc-hallo cc-auf">
    <h1><?= $h((string) $suT['betreff']) ?></h1>
    <p class="cc-lead"><?= $suStand((string) $suT['stand']) ?> · <?= $h($su($SU['themen'][$suT['thema']] ?? $SU['themen']['sonstiges'])) ?> · <?= $h($suDatum((string) $suT['created_at'])) ?></p>
  </div>
  <?php if ($suM !== ''): ?><div class="hinweis <?= $suGut ? 'gut' : 'schlecht' ?>" role="<?= $suGut ? 'status' : 'alert' ?>" style="margin:0 0 14px"><?= $h($su($SU['m'][$suM] ?? $SU['m']['text'])) ?></div><?php endif; ?>
  <section class="cc-karte cc-auf z2">
    <div class="cc-chat">
      <?php foreach ($suV as $n): $ich = $n['von'] === 'partner'; ?>
        <div class="cc-blase <?= $ich ? 'ich' : 'wir' ?>"><?= $h((string) $n['text']) ?><small><?= $h(($ich ? $su($SU['du']) : $su($SU['wir'])) . ' · ' . $suDatum((string) $n['created_at'])) ?></small></div>
      <?php endforeach; ?>
    </div>
    <form method="post" action="<?= $h($selbst(['cc' => 1, 'support' => 1, 'ticket' => (int) $suT['id']])) ?>" id="t-ende" class="cc-ticket-antwort">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="ticket_antwort"><input type="hidden" name="ticket" value="<?= (int) $suT['id'] ?>">
      <label class="cc-feld"><span><?= $h($su($SU['antwort_feld'])) ?></span>
        <textarea name="text" rows="4" required maxlength="<?= PartnerPost::MAX_LAENGE ?>"><?= $h(($ccSupFehler ?? '') !== '' ? (string) $suPost['text'] : '') ?></textarea></label>
      <?php if ($suT['stand'] === 'erledigt'): ?><p class="cc-mail-fuss"><?= $h($su($SU['wieder_offen'])) ?></p><?php endif; ?>
      <button class="knopf haupt" type="submit"><?= $h($su($SU['antworten'])) ?></button>
    </form>
  </section>
<?php else: $suListe = PartnerTicket::liste($suPid); $suBez = PartnerTicket::bezugAuswahl($suPid);
  /* Erst suchen (Phase 7b-2): Academy (Academy::suche) und die häufigsten Fragen. Findet sich nichts,
     steht die Frage schon als Betreff im Formular darunter. */
  $suQ = mb_substr(trim((string) ($_GET['frage'] ?? '')), 0, 60);
  $suFaq = []; $suAk = [];
  if (mb_strlen($suQ) >= 2) {
      $suW = array_values(array_filter(preg_split('~\s+~u', mb_strtolower($suQ)) ?: [], static fn($w) => mb_strlen($w) >= 2));
      foreach (Texte::PARTNER_SUPPORT['faq'] as [$suF, $suA]) {
          $suT = mb_strtolower(Texte::h($suF, $sprache) . ' ' . Texte::h($suA, $sprache));
          if ($suW && !array_filter($suW, static fn($w) => !str_contains($suT, $w))) { $suFaq[] = [Texte::h($suF, $sprache), Texte::h($suA, $sprache)]; }
      }
      require_once dirname(__DIR__) . '/src/Academy.php';
      $suAk = array_slice((static function () use ($suQ, $sprache): array { try { return Academy::suche($suQ, $sprache); } catch (Throwable $e) { return []; } })(), 0, 5);
      if (!$suFaq && !$suAk && ($suPost['betreff'] ?? '') === '') { $suPost['betreff'] = $suQ; }
  }
  $suAkLink = static fn(array $tr): string => match ($tr['art']) {
      'einwand'  => $start(['ak' => 'einwaende', 'e' => $tr['slug']]),
      'leistung' => $start(['ak' => 'leistungen', 's' => $tr['slug']]) . '#s-' . $tr['slug'],
      'kontakt'  => $start(['ak' => 'kontakt', 'k' => $tr['slug']]) . '#k-' . $tr['slug'],
      default    => $start(['ak' => 'modul', 'm' => $tr['slug'], 'l' => (string) (int) ($tr['lektion'] ?? 0)]),
  };
  $suAlt = Db::all('SELECT von, text, created_at FROM partner_nachrichten WHERE partner_id = ? AND ticket_id IS NULL ORDER BY id DESC LIMIT 30', [$suPid]); ?>
  <div class="cc-hallo cc-auf">
    <h1><?= $h($su($SU['titel'])) ?></h1>
    <p class="cc-lead"><?= $h($su($SU['satz'])) ?></p>
  </div>
  <?php if ($suM !== ''): ?><div class="hinweis <?= $suGut ? 'gut' : 'schlecht' ?>" role="<?= $suGut ? 'status' : 'alert' ?>" style="margin:0 0 14px"><?= $h($su($SU['m'][$suM] ?? $SU['m']['text'])) ?></div><?php endif; ?>
  <section class="cc-auf z2 cc-hilfe" aria-labelledby="su-such-t">
    <h2 class="cc-titel" id="su-such-t"><?= $h($su($SU['suche'])) ?></h2>
    <form method="get" action="/partner.php" class="cc-hilfe-suche" role="search">
      <?php foreach (['t' => (string) $p['token'], 'cc' => '1', 'support' => '1'] as $suK => $suV): ?><input type="hidden" name="<?= $h($suK) ?>" value="<?= $h($suV) ?>"><?php endforeach; ?>
      <input type="search" name="frage" value="<?= $h($suQ) ?>" maxlength="60" placeholder="<?= $h($su($SU['suche_ph'])) ?>" aria-label="<?= $h($su($SU['suche'])) ?>">
      <button class="knopf" type="submit"><?= $h($su($SU['suche_knopf'])) ?></button>
    </form>
    <?php if (mb_strlen($suQ) >= 2): ?>
      <?php if ($suFaq || $suAk): ?>
        <p class="cc-hilfe-kopf"><?= $h($su($SU['suche_treffer'], ['{q}' => $suQ])) ?></p>
        <?php foreach ($suFaq as [$suF, $suA]): ?><details class="cc-faq" open><summary><?= $h($suF) ?></summary><p><?= $h($suA) ?></p></details><?php endforeach; ?>
        <?php if ($suAk): ?><p class="cc-hilfe-kopf"><?= $h($su($SU['suche_academy'])) ?></p>
          <ul class="cc-hilfe-ak"><?php foreach ($suAk as $tr): ?><li><a href="<?= $h($suAkLink($tr)) ?>"><b><?= $h((string) $tr['titel']) ?></b><small><?= $h((string) $tr['auszug']) ?></small></a></li><?php endforeach; ?></ul>
        <?php endif; ?>
      <?php else: ?>
        <p class="hinweis" role="status" style="margin:10px 0 0"><?= $h($su($SU['suche_leer'], ['{q}' => $suQ])) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="cc-karte cc-auf z2" aria-labelledby="su-neu-t" style="margin-top:18px">
    <h2 class="cc-titel" id="su-neu-t"><?= $h($su($SU['neu'])) ?></h2>
    <form method="post" action="<?= $h($selbst(['cc' => 1, 'support' => 1])) ?>">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="ticket_neu">
      <div class="cc-felder">
        <label class="cc-feld"><span><?= $h($su($SU['thema'])) ?></span><select name="thema" required>
          <?php foreach (PartnerTicket::THEMEN as $th): ?><option value="<?= $h($th) ?>"<?= $suPost['thema'] === $th ? ' selected' : '' ?>><?= $h($su($SU['themen'][$th])) ?></option><?php endforeach; ?></select></label>
        <label class="cc-feld"><span><?= $h($su($SU['bezug'])) ?></span><select name="bezug">
          <option value=""><?= $h($su($SU['bezug_kein'])) ?></option>
          <?php if ($suBez['lead']): ?><optgroup label="<?= $h($su($SU['bezug_lead'])) ?>"><?php foreach ($suBez['lead'] as $b): ?><option value="lead:<?= (int) $b['id'] ?>"<?= $suPost['bezug'] === 'lead:' . (int) $b['id'] ? ' selected' : '' ?>><?= $h((string) $b['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
          <?php if ($suBez['bestellung']): ?><optgroup label="<?= $h($su($SU['bezug_best'])) ?>"><?php foreach ($suBez['bestellung'] as $b): ?><option value="bestellung:<?= (int) $b['id'] ?>"<?= $suPost['bezug'] === 'bestellung:' . (int) $b['id'] ? ' selected' : '' ?>><?= $h((string) $b['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
        </select></label>
        <label class="cc-feld breit"><span><?= $h($su($SU['betreff'])) ?> *</span>
          <input name="betreff" required minlength="<?= PartnerTicket::BETREFF_MIN ?>" maxlength="<?= PartnerTicket::BETREFF_MAX ?>" value="<?= $h((string) $suPost['betreff']) ?>"<?= $suM === 'betreff' ? ' aria-invalid="true" autofocus' : '' ?>></label>
        <label class="cc-feld breit"><span><?= $h($su($SU['text'])) ?></span>
          <textarea name="text" rows="5" required maxlength="<?= PartnerPost::MAX_LAENGE ?>"><?= $h((string) $suPost['text']) ?></textarea></label>
      </div>
      <button class="knopf haupt" type="submit"><?= $h($su($SU['senden'])) ?></button>
    </form>
  </section>

  <section class="cc-auf z3" aria-labelledby="su-liste-t" style="margin-top:22px">
    <h2 class="cc-titel" id="su-liste-t"><?= $h($su($SU['liste'])) ?></h2>
    <?php if (!$suListe): ?><p class="cc-leer"><?= $h($su($SU['leer'])) ?></p><?php endif; ?>
    <ul class="cc-best">
      <?php foreach ($suListe as $t): ?>
        <li class="cc-teil"><a class="cc-ticket" href="<?= $h($selbst(['cc' => 1, 'support' => 1, 'ticket' => (int) $t['id']])) ?>">
          <span class="cc-best-kopf"><b><?= $h((string) $t['betreff']) ?></b><?= $suStand((string) $t['stand']) ?></span>
          <small><?= $h($su($SU['themen'][$t['thema']] ?? $SU['themen']['sonstiges'])) ?> · <?= $h($suDatum((string) $t['geaendert_am'])) ?>
            <?php if ((int) $t['neu'] > 0): ?> · <b class="cc-neu-antwort"><?= $h($su($SU['neu_antwort'])) ?></b><?php endif; ?></small></a></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <?php if ($suAlt): ?>
    <details class="cc-auf z3" style="margin-top:18px"><summary class="cc-titel" style="cursor:pointer"><?= $h($su($SU['frueher'])) ?> (<?= count($suAlt) ?>)</summary>
      <div class="cc-chat" style="margin-top:10px">
        <?php foreach (array_reverse($suAlt) as $n): $ich = $n['von'] === 'partner'; ?>
          <div class="cc-blase <?= $ich ? 'ich' : 'wir' ?>"><?= $h((string) $n['text']) ?><small><?= $h(($ich ? $su($SU['du']) : $su($SU['wir'])) . ' · ' . $suDatum((string) $n['created_at'])) ?></small></div>
        <?php endforeach; ?>
      </div></details>
  <?php endif; ?>
<?php endif; ?>
</main>
