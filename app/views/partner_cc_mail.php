<?php
/* ==========================================================================
   E-MAIL im Command Center (Phase 7a, 05.10.2026; Spezifikation 45).
   Nur für Partner mit @vecom-Adresse (partner.php leitet sonst gar nicht hierher).
   Oben: neue E-Mail (Von steht fest, An, Betreff PFLICHT, Nachricht, Sprache der
   Fußzeile). Aus der Kundenakte kommt sie vorausgefüllt (?an_lead=ID). Darunter:
   was gesendet wurde. Die Pflichtregel steht hier als required/pattern und in
   assets/js/partner-cc.js (#pm-form) — die eigentliche Prüfung macht
   PartnerMail::mailtoErzeugen auf dem Server. Seit 06.10.2026 sendet das System nicht mehr selbst: „Senden“
   öffnet das eigene Mailprogramm des Partners (mailto), gespeichert wird nur der Zeitpunkt.
   Erwartet aus partner.php: $p, $sprache, $h, $selbst, $ccMailFehler, $ccMailPost.
   ========================================================================== */
require_once dirname(__DIR__) . '/src/PartnerMail.php';
require_once dirname(__DIR__) . '/src/PartnerLeads.php';
require_once dirname(__DIR__) . '/src/PartnerAnschreiben.php';
$PM = Texte::PARTNER_MAIL;
$pm = static fn(array $t, array $r = []): string => strtr(Texte::h($t, $sprache), $r);
$pmAdresse = (string) ($p['vecom_adresse'] ?? '');   // seit 06.10.2026 nur ein Hinweis: gesendet wird aus dem eigenen Programm
$pmListe = PartnerMail::liste((int) $p['id']);
$pmM = (string) ($ccMailFehler ?? '') !== '' ? (string) $ccMailFehler : (($_GET['m'] ?? '') === 'ok' ? 'ok' : '');
$pmPost = is_array($ccMailPost ?? null) ? $ccMailPost : null;
// Vorausgefüllt aus der Kundenakte: Adresse, Betreff und Text in der Sprache des Betriebs (wie der mailto-Link bisher).
$pmLead = null;
$pmVor = ['an' => '', 'betreff' => '', 'text' => '', 'lead' => null, 'fsprache' => $sprache];
$pmLeadId = $pmPost['lead'] ?? ((int) ($_GET['an_lead'] ?? 0) ?: null);
if ($pmLeadId !== null) { $pmLead = PartnerLeads::laden((int) $p['id'], (int) $pmLeadId); }
if ($pmLead && !$pmPost) {
    $L = Texte::PARTNER_LEADS;
    $pmMail = trim((string) $pmLead['email']);
    $pmHost = (string) $pmLead['domain'] !== '' ? (string) $pmLead['domain'] : (string) substr((string) strrchr($pmMail, '@'), 1);
    $pmSp = $pmHost !== '' ? PartnerAnschreiben::spracheZurAdresse($pmHost, $sprache) : $sprache;
    $pmAn = trim((string) $pmLead['ansprechpartner']) !== '' ? ' ' . trim((string) $pmLead['ansprechpartner']) : '';
    $pmVor = ['an' => $pmMail, 'betreff' => Texte::h($L['mail_betreff'], $pmSp), 'lead' => (int) $pmLead['id'], 'fsprache' => $pmSp,
              'text' => strtr(Texte::h($L['mail_text'], $pmSp), ['{name}' => $pmAn, '{partner}' => (string) $p['name'], '{link}' => Partner::link($p)])];
}
if ($pmPost) { $pmVor = $pmPost; }
$pmDatum = static fn(string $d): string => date($sprache === 'en' ? 'd/m/Y H:i' : 'd.m.Y H:i', strtotime($d) ?: time());
?>
<main id="cc-start" tabindex="-1">
  <p class="cc-zurueck"><a href="<?= $h($selbst(['cc' => 1, 'kunden' => 1])) ?>">← <?= $h(Texte::h(Texte::PARTNER_LEADS['zurueck'], $sprache)) ?></a></p>
  <div class="cc-hallo cc-auf">
    <h1><?= $h($pm($PM['titel'])) ?></h1>
    <p class="cc-lead"><?= $h($pm($PM['satz'])) ?><?= $pmAdresse !== '' ? ' ' . $h($pm($PM['satz_adresse'], ['{adresse}' => $pmAdresse])) : '' ?></p>
  </div>
  <?php if ($pmM !== ''): ?>
    <div class="hinweis <?= $pmM === 'ok' ? 'gut' : 'schlecht' ?> cc-auf" role="<?= $pmM === 'ok' ? 'status' : 'alert' ?>" style="margin:0 0 16px" id="pm-meldung"><?= $h($pm($PM['m'][$pmM] ?? $PM['m']['fehler'])) ?></div>
  <?php endif; ?>

  <section class="cc-karte cc-auf z2" aria-labelledby="pm-neu-t">
    <h2 class="cc-titel" id="pm-neu-t"><?= $h($pm($PM['neu'])) ?></h2>
    <?php if ($pmLead): ?><p class="cc-mail-bezug"><?= $h($pm($PM['zum_lead'], ['{name}' => (string) $pmLead['name']])) ?></p><?php endif; ?>
    <form method="post" action="<?= $h($selbst(['cc' => 1, 'mail' => 1])) ?>" class="cc-mail" id="pm-form" data-betreff-min="<?= PartnerMail::BETREFF_MIN ?>"
          data-ok="<?= $h($pm($PM['m']['ok'])) ?>" data-lang="<?= $h($pm($PM['m']['lang'])) ?>" novalidate>
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="mail_senden">
      <?php if ($pmLead): ?><input type="hidden" name="lead" value="<?= (int) $pmLead['id'] ?>"><?php endif; ?>
      <div class="cc-felder">
        <label class="cc-feld breit"><span><?= $h($pm($PM['an'])) ?></span>
          <input type="email" name="an" required maxlength="190" autocomplete="off" inputmode="email" value="<?= $h((string) $pmVor['an']) ?>" placeholder="<?= $h($pm($PM['an_hilfe'])) ?>"></label>
        <label class="cc-feld breit"><span><?= $h($pm($PM['betreff'])) ?> *</span>
          <input name="betreff" id="pm-betreff" required minlength="<?= PartnerMail::BETREFF_MIN ?>" maxlength="<?= PartnerMail::BETREFF_MAX ?>" pattern=".*\S.*\S.*\S.*"
                 value="<?= $h((string) $pmVor['betreff']) ?>" aria-describedby="pm-betreff-h"<?= str_starts_with($pmM, 'betreff_') ? ' aria-invalid="true" autofocus' : '' ?>>
          <small id="pm-betreff-h" class="cc-mail-pflicht"><?= $h($pm($PM['betreff_pflicht'])) ?></small></label>
        <label class="cc-feld breit"><span><?= $h($pm($PM['text'])) ?></span>
          <textarea name="text" rows="10" required minlength="<?= PartnerMail::TEXT_MIN ?>" maxlength="<?= PartnerMail::TEXT_MAX ?>"><?= $h((string) $pmVor['text']) ?></textarea></label>
        <label class="cc-feld"><span><?= $h($pm($PM['sprache'])) ?></span><select name="fsprache">
          <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $pmK => $pmW): ?><option value="<?= $pmK ?>"<?= $pmVor['fsprache'] === $pmK ? ' selected' : '' ?>><?= $pmW ?></option><?php endforeach; ?></select></label>
      </div>
      <p class="cc-mail-fuss"><?= $h($pm($PM['fuss_hilfe'])) ?></p>
      <div class="cc-mail-senden">
        <button class="knopf haupt" type="submit"><?= $h($pm($PM['senden'])) ?></button>
        <small id="pm-status" role="status"><?= $h($pm($PM['senden_hilfe'])) ?></small>
      </div>
    </form>
  </section>

  <section class="cc-auf z3" aria-labelledby="pm-aus-t" style="margin-top:22px">
    <h2 class="cc-titel" id="pm-aus-t"><?= $h($pm($PM['ausgang'])) ?></h2>
    <?php if (!$pmListe): ?><p class="cc-leer"><?= $h($pm($PM['ausgang_leer'])) ?></p><?php endif; ?>
    <ul class="cc-best">
      <?php foreach ($pmListe as $pmX): $pmSt = $pmX['abgemeldet_am'] !== null ? 'abgemeldet' : (string) $pmX['status']; ?>
        <li class="cc-teil">
          <div class="cc-best-kopf"><b><?= $h((string) ($pmX['betreff'] ?? '') !== '' ? (string) $pmX['betreff'] : $pm($PM['ohne_betreff'])) ?></b>
            <span class="cc-stufe st-<?= $h($pmSt) ?>"><?= $h($pm($PM['status'][$pmSt] ?? $PM['status']['gesendet'])) ?></span></div>
          <small><?= $h((string) $pmX['an']) ?> · <?= $h($pmDatum((string) $pmX['created_at'])) ?></small>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</main>
