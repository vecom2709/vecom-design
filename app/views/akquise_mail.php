<?php
/** @var array $f */
/* KOMMUNIKATIONSSTATUS E-MAIL (06.10.2026, Uwe: „Keine pauschale Sperre mehr.
   Stattdessen erhält jeder Betrieb einen Kommunikationsstatus.“)
   Die Adresse steht immer da und lässt sich kopieren; Entwürfe gehen immer
   (außer „Nicht kontaktieren“); versendet wird von Hand erst mit einem
   dokumentierten Versandgrund. Das System dokumentiert, es bewertet nicht. */
require_once dirname(__DIR__) . '/src/AkquiseMail.php';
$mFid = (int) $f['id'];
$mKann = AkquiseMail::kann($f);
[$mZeichen, $mFarbe, $mWort] = AkquiseMail::STATUS[$mKann['status']];
$mAdmin = Auth::rolle() === 'admin';
$mH = static fn(?string $s): string => Fmt::h((string) $s);
$mMail = (string) ($f['email'] ?? '');
$mVerlauf = AkquiseMail::verlauf($mFid);
$mForm = static function (string $tat, string $innen, string $extra = '') use ($mFid): string {
    return '<form method="post" action="' . Fmt::h(url('akquise')) . '"' . $extra . '>' . Csrf::feld()
        . '<input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="firma" value="' . $mFid . '">' . $innen . '</form>';
};
?>
<style>
  .ms-kopf{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
  .ms-kopf h2{margin:0}
  .ms-status{display:inline-flex;gap:6px;align-items:center;padding:5px 12px;border-radius:999px;font-size:14px;font-weight:650;border:1px solid var(--linie2)}
  .ms-status.rot{color:#ff9b9b;border-color:rgba(255,138,138,.4);background:rgba(255,138,138,.08)}
  .ms-status.gelb{color:var(--gelb);border-color:rgba(255,200,90,.4);background:rgba(255,200,90,.08)}
  .ms-status.gruen{color:#7fe0a8;border-color:rgba(127,224,168,.4);background:rgba(127,224,168,.08)}
  .ms-adresse{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:14px 0 4px;font-size:17px}
  .ms-adresse code{font-size:16px;padding:6px 10px;border-radius:9px;background:var(--flaeche2);user-select:all;word-break:break-all}
  .ms-fuenf{display:grid;grid-template-columns:repeat(auto-fit,minmax(96px,1fr));gap:6px;margin:12px 0}
  .ms-fuenf div{border:1px solid var(--linie);border-radius:10px;padding:8px 6px;font-size:12.5px;line-height:1.3;color:var(--dim);text-align:center;overflow-wrap:anywhere}
  .ms-fuenf b{display:block;font-size:15px;margin-bottom:2px}
  .ms-fuenf .ja b{color:#7fe0a8} .ms-fuenf .nein b{color:#ff9b9b}
  .ms-geht{font-size:14px;line-height:1.55;color:var(--dim);margin:8px 0}
  .ms-geht b{color:var(--text)}
  .ms-block details{margin-top:12px;border-top:1px solid var(--linie);padding-top:10px}
  .ms-block summary{cursor:pointer;font-weight:650;font-size:15px}
  .ms-block .reihe2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .ms-block .haken{display:flex;gap:8px;align-items:flex-start;font-size:13.5px;margin:4px 0;color:var(--text)}
  .ms-block .haken input{width:auto;flex:none;margin-top:3px}
  .ms-felder td{font-size:13px;padding:4px 8px;vertical-align:top}
  .ms-felder td:first-child{color:var(--leise);white-space:nowrap;font-family:ui-monospace,monospace;font-size:12px}
  @media (max-width:600px){ .ms-block .reihe2{grid-template-columns:1fr} }
</style>
<div class="block ms-block" id="mailstatus">
  <div class="ms-kopf">
    <h2>E-Mail</h2>
    <span class="ms-status <?= $mH($mFarbe) ?>" role="status"><?= $mZeichen ?> <?= $mH($mWort) ?></span>
  </div>

  <?php if ($mKann['gefunden']): ?>
    <div class="ms-adresse">
      <?php if ($mKann['senden']): ?>
        <a href="<?= $mH('mailto:' . rawurlencode($mMail)) ?>" data-ms-oeffnen="email"><code><?= $mH($mMail) ?></code></a>
      <?php else: ?>
        <code><?= $mH($mMail) ?></code>
      <?php endif; ?>
      <button class="knopf" type="button" data-ms-kopieren="<?= $mH($mMail) ?>">Kopieren</button>
      <?php if ((int) ($f['email_verified'] ?? 0) === 1): ?><span class="marke2">bestätigt</span><?php endif; ?>
    </div>
    <p class="akq-klein">Quelle der Adresse: <?= $mH((string) ($f['email_source'] ?? '') ?: 'nicht erfasst') ?></p>
  <?php else: ?>
    <p class="ms-geht">Noch keine E-Mail-Adresse bekannt. Unter „Angaben ändern“ eintragen, wenn der Betrieb eine nennt.</p>
  <?php endif; ?>

  <div class="ms-fuenf" aria-label="Was mit der Adresse geht">
    <?php foreach (['gefunden' => 'Gefunden', 'anzeigen' => 'Anzeigen', 'entwurf' => 'Entwurf', 'senden' => 'Versand von Hand', 'werbung' => 'Werbung'] as $mk => $mw): ?>
      <div class="<?= $mKann[$mk] ? 'ja' : 'nein' ?>"><b><?= $mKann[$mk] ? '✓' : '✗' ?></b><?= $mH($mw) ?></div>
    <?php endforeach; ?>
  </div>

  <?php if ($mKann['status'] === AkquiseMail::NICHT): ?>
    <p class="ms-geht"><b>Nicht kontaktieren.</b> Werbeversand bleibt auf allen Wegen blockiert.
      <?php if (!empty($f['email_dnc_reason'])): ?>Grund: <?= $mH(AkquiseMail::DNC_GRUENDE[(string) $f['email_dnc_reason']][0] ?? ((string) $f['email_dnc_reason'] === 'sperre' ? 'Sperre / Ablehnung' : (string) $f['email_dnc_reason'])) ?><?= !empty($f['email_dnc_note']) ? ' — ' . $mH((string) $f['email_dnc_note']) : '' ?><?= !empty($f['email_dnc_at']) ? ' (' . $mH(date('d.m.Y', strtotime((string) $f['email_dnc_at']))) . ')' : '' ?>.<?php endif; ?></p>
    <?php if ($mAdmin): ?>
      <details><summary>„Nicht kontaktieren“ aufheben (nur Admin, wird protokolliert)</summary>
        <?= $mForm('akq_mail_nicht_aufheben', '<div class="feld"><label>Begründung (Pflicht)</label><textarea name="begruendung" rows="3" required minlength="10" placeholder="z. B. Sperre versehentlich beim falschen Betrieb eingetragen"></textarea></div>
          <button class="knopf">Aufheben</button>', ' style="margin-top:10px"') ?>
      </details>
    <?php endif; ?>
  <?php else: ?>
    <p class="ms-geht">
      <?php if ($mKann['status'] === AkquiseMail::FREI): ?>
        <b>Versand freigegeben.</b> Nachricht unten bei „Ansprechen“ erstellen, bearbeiten, ansehen und von Hand senden — einzeln, nicht als Serie.
        <?= $mKann['werbung'] ? '' : 'Der dokumentierte Grund deckt keine Werbung — nur die Antwort bzw. die geschäftliche Nachricht.' ?>
      <?php elseif ($mKann['status'] === AkquiseMail::PRUEFEN): ?>
        <b>Manuelle Prüfung erforderlich.</b> Adresse anzeigen, kopieren und Entwürfe erstellen geht. Versendet wird erst, wenn ein Versandgrund dokumentiert ist, der eine Freigabe trägt.
      <?php else: ?>
        <b>Keine Versandfreigabe dokumentiert.</b> Möglich: Adresse anzeigen, kopieren, Entwurf erstellen und bearbeiten, Quelle ansehen.
        Nicht möglich: ungeprüfter automatischer Versand und Massenversand.
      <?php endif; ?>
      <?php if (!empty($f['email_legal_basis'])): ?>
        <br><span class="akq-klein">Dokumentiert: <?= $mH(AkquiseMail::GRUENDE[(string) $f['email_legal_basis']][0] ?? (string) $f['email_legal_basis']) ?>
          <?= !empty($f['email_legal_basis_date']) ? ' · ' . $mH(date('d.m.Y', strtotime((string) $f['email_legal_basis_date']))) : '' ?>
          <?= !empty($f['email_legal_basis_by']) ? ' · ' . $mH((string) $f['email_legal_basis_by']) : '' ?>
          <?= !empty($f['email_legal_basis_source']) ? ' · Nachweis: ' . $mH(mb_substr((string) $f['email_legal_basis_source'], 0, 160)) : '' ?></span>
      <?php endif; ?>
    </p>

    <details id="versandgrund"<?= $mKann['status'] === AkquiseMail::PRUEFEN ? ' open' : '' ?>><summary>Versandgrund dokumentieren</summary>
      <?php
        $mOpt = implode('', array_map(static fn($k, $g) => '<option value="' . $k . '"' . ((string) ($f['email_legal_basis'] ?? '') === $k ? ' selected' : '') . '>' . Fmt::h($g[0]) . '</option>', array_keys(AkquiseMail::GRUENDE), AkquiseMail::GRUENDE));
        $mHaken = implode('', array_map(static fn($k, $w) => '<label class="haken"><input type="checkbox" name="haken[' . $k . ']" value="1"> ' . Fmt::h($w) . '</label>', array_keys(AkquiseMail::BESTAND_HAKEN), AkquiseMail::BESTAND_HAKEN));
        echo $mForm('akq_mail_grund', '
          <div class="feld"><label>Grund (Pflicht)</label><select name="grund" required><option value="">Bitte wählen …</option>' . $mOpt . '</select></div>
          <div class="reihe2">
            <div class="feld"><label>Datum (Pflicht)</label><input type="date" name="datum" required max="' . date('Y-m-d') . '" value="' . date('Y-m-d') . '"></div>
            <div class="feld"><label>Bearbeiter</label><input value="' . Fmt::h(Auth::name() ?: 'Verwaltung') . '" readonly aria-readonly="true"></div>
          </div>
          <div class="feld"><label>Quelle / Nachweis (Pflicht)</label><input name="quelle" required minlength="3" maxlength="255" placeholder="z. B. Anfrage per E-Mail vom 02.10.2026, Auftrag Nr. 12, Anruf des Inhabers"></div>
          <div class="feld"><label>Notiz (Pflicht)</label><textarea name="notiz" rows="3" required minlength="3"></textarea></div>
          <fieldset style="border:1px solid var(--linie);border-radius:10px;padding:8px 10px;margin:6px 0">
            <legend class="akq-klein">Nur bei „Bestehender Kunde“ — alle vier bestätigen, sonst bleibt es bei „Prüfung erforderlich“</legend>' . $mHaken . '</fieldset>'
          . ($mAdmin ? '<label class="haken"><input type="checkbox" name="freigeben" value="1"> Nur bei „Sonstiger Grund“: Versand nach eigener Prüfung freigeben (Admin)</label>' : '') . '
          <p class="akq-klein">Freigabe je Grund: Einwilligung → Versand und Werbung · Bestandskunde → nur mit allen vier Punkten · eigener Kontakt, Anfrage, laufende Geschäftsbeziehung → einzelne Nachricht, keine Werbung · sonstiger Grund → Prüfung, Freigabe nur durch einen Admin.
            Das System hält fest, was du einträgst; ob ein Versand zulässig ist, entscheidest du.</p>
          <button class="knopf haupt">Dokumentieren</button>', ' style="margin-top:10px"');
      ?>
    </details>

    <?php if ($mKann['status'] === AkquiseMail::KEINE): ?>
      <details><summary>Zur Prüfung markieren</summary>
        <?= $mForm('akq_mail_pruefung', '<div class="feld"><label>Notiz</label><input name="notiz" maxlength="500" placeholder="z. B. Hat am Telefon nach einem Angebot gefragt — Grund klären"></div>
          <button class="knopf">Zur Prüfung markieren</button>', ' style="margin-top:10px"') ?>
      </details>
    <?php endif; ?>

    <details><summary>Nicht kontaktieren</summary>
      <?= $mForm('akq_mail_nicht', '<div class="reihe2">
          <div class="feld"><label>Grund</label><select name="grund" required><option value="">Bitte wählen …</option>'
          . implode('', array_map(static fn($k, $g) => '<option value="' . $k . '">' . Fmt::h($g[0]) . '</option>', array_keys(AkquiseMail::DNC_GRUENDE), AkquiseMail::DNC_GRUENDE)) . '</select></div>
          <div class="feld"><label>Notiz</label><input name="notiz" maxlength="255" placeholder="z. B. Antwort „STOP“ vom 05.10."></div></div>
        <p class="akq-klein">Der Betrieb kommt damit auf die Sperrliste (alle Wege). Aufheben kann nur ein Admin mit Begründung.</p>
        <button class="knopf">„Nicht kontaktieren“ setzen</button>', ' style="margin-top:10px"') ?>
    </details>
  <?php endif; ?>

  <?php if ($mAdmin): ?>
    <details><summary>Alle Felder (Admin)</summary>
      <table class="ms-felder" style="margin-top:8px"><tbody>
        <?php foreach (['email_found', 'email_verified', 'email_source', 'email_contact_status', 'email_send_allowed', 'email_marketing_consent', 'email_review_requested',
                        'email_legal_basis', 'email_legal_basis_date', 'email_legal_basis_source', 'email_legal_basis_note', 'email_legal_basis_by',
                        'email_do_not_contact', 'email_dnc_reason', 'email_dnc_note', 'email_dnc_at', 'einwilligung', 'einwilligung_kanaele', 'bestandskunde', 'gesperrt', 'compliance_status'] as $mFeld): ?>
          <tr><td><?= $mFeld ?></td><td><?= $mH($f[$mFeld] === null ? '—' : (string) $f[$mFeld]) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
      <div class="an-knoepfe" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
        <?php if ($mKann['status'] !== AkquiseMail::NICHT && ((int) ($f['email_send_allowed'] ?? 0) === 1 || !empty($f['email_legal_basis']) || (int) ($f['email_review_requested'] ?? 0) === 1)): ?>
          <?= $mForm('akq_mail_zurueck', '<input type="hidden" name="notiz" value="Vom Admin zurückgesetzt"><button class="knopf">Freigabe/Grund zurücknehmen</button>', ' style="margin:0"') ?>
        <?php endif; ?>
        <?php if ($mKann['gefunden']): ?>
          <?= $mForm('akq_mail_geprueft', '<input type="hidden" name="ja" value="' . ((int) ($f['email_verified'] ?? 0) === 1 ? '' : '1') . '"><button class="knopf">' . ((int) ($f['email_verified'] ?? 0) === 1 ? 'Bestätigung der Adresse entfernen' : 'Adresse als bestätigt markieren') . '</button>', ' style="margin:0"') ?>
        <?php endif; ?>
      </div>
      <p class="akq-klein" style="margin-top:6px">Einwilligungen aus Anruf, Besuch oder Double-Opt-in stehen weiter unten bei „Einwilligung“ und zählen hier automatisch als Freigabe.</p>
    </details>
  <?php endif; ?>

  <?php if ($mVerlauf): ?>
    <details><summary>Verlauf (<?= count($mVerlauf) ?>)</summary>
      <ul style="margin:8px 0 0;padding-left:18px;font-size:13px;line-height:1.6">
        <?php foreach ($mVerlauf as $mv): ?>
          <li><?= $mH(date('d.m.Y H:i', strtotime((string) $mv['created_at']))) ?> · <?= $mH((string) $mv['bearbeiter']) ?> ·
            <?= $mH(['grund' => 'Versandgrund', 'pruefung' => 'Prüfung angefordert', 'zurueck' => 'Freigabe zurückgenommen', 'dnc' => 'Nicht kontaktieren', 'dnc_aufgehoben' => '„Nicht kontaktieren“ aufgehoben', 'geprueft' => 'Adresse'][$mv['art']] ?? (string) $mv['art']) ?>
            <?php if ($mv['grund']): ?>: <?= $mH(AkquiseMail::GRUENDE[(string) $mv['grund']][0] ?? AkquiseMail::DNC_GRUENDE[(string) $mv['grund']][0] ?? (string) $mv['grund']) ?><?php endif; ?>
            <?= $mv['datum'] ? ' (' . $mH(date('d.m.Y', strtotime((string) $mv['datum']))) . ')' : '' ?>
            <?= $mv['art'] === 'grund' ? ((int) $mv['freigabe'] ? ' → freigegeben' . ((int) $mv['werbung'] ? ', auch Werbung' : '') : ' → Prüfung') : '' ?>
            <?= $mv['quelle'] ? '<br><span class="akq-klein">Nachweis: ' . $mH((string) $mv['quelle']) . '</span>' : '' ?>
            <?= $mv['notiz'] ? '<br><span class="akq-klein">' . nl2br($mH((string) $mv['notiz'])) . '</span>' : '' ?></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</div>
<script>
(function () {
  document.querySelectorAll('[data-ms-kopieren]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.getAttribute('data-ms-kopieren'), vorher = b.textContent;
      function ok() { b.textContent = 'Kopiert'; setTimeout(function () { b.textContent = vorher; }, 1600); }
      if (navigator.clipboard) { navigator.clipboard.writeText(t).then(ok, function () {}); }
    });
  });
  /* Klick auf die freigegebene Adresse: still im Verlauf vermerken (wie „In meinem Mailprogramm öffnen“) */
  var box = document.getElementById('mailstatus'), csrf = box && box.querySelector('input[name=_csrf]');
  document.querySelectorAll('[data-ms-oeffnen]').forEach(function (a) {
    a.addEventListener('click', function () {
      if (!csrf) return;
      var d = new FormData(); d.append('_csrf', csrf.value); d.append('tat', 'akq_manuell'); d.append('firma', '<?= $mFid ?>'); d.append('kanal', 'email'); d.append('still', '1');
      try { fetch(<?= json_encode(url('akquise')) ?>, { method: 'POST', body: d, keepalive: true, credentials: 'same-origin' }); } catch (e) {}
    });
  });
})();
</script>
