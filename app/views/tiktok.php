<?php
/**
 * Marketing · „Auf TikTok veröffentlichen“ — die Bestätigungsseite je Video
 * (02.10.2026, Uwe: „mache nun alles für TikTok“ → „Komplett einrichten“).
 *
 * TikTok verlangt diese Seite für Direct Post (Content Sharing Guidelines,
 * gelesen 02.10.2026). Was hier steht, steht da, weil TikTok es so will:
 *   - Kontoname und Bild aus frischer creator_info (bei jedem Aufruf neu)
 *   - Sichtbarkeit OHNE Vorauswahl, nur die erlaubten Stufen
 *   - Kommentar, Duett, Stitch ohne Haken; gesperrt, wo das Konto sie abschaltet
 *   - Werbekennzeichnung aus; an: „Your brand“ und/oder „Branded content“ mit
 *     den vorgegebenen Hinweisen, Markenpartnerschaft nie „Nur ich“
 *   - der Satz zur Music Usage Confirmation wörtlich (englisch, wie verlangt)
 *   - Vorschau, Text änderbar, Höchstdauer geprüft, erst der Klick sendet
 *   - nach dem Senden: „kann einige Minuten dauern“ und der Stand von TikTok
 * Die Regeln stehen zusätzlich im Server (MkPlattform::ttPruefen) — wer das
 * Formular umgeht, kommt damit nicht durch.
 *
 * Erwartet: $x (MkInhalt::laden), $video (gewähltes Video oder null),
 *           $konto (MkPlattform::ttKonto), $dauer (Sekunden oder null),
 *           $freigabe (Prüfung durch TikTok bestanden), $ki (Video ist KI-erzeugt)
 */
require __DIR__ . '/mk_stil.php';
$ids = json_decode((string) ($x['post_ids'] ?? ''), true) ?: [];
$gesendet = (string) ($ids['tt'] ?? '');
$titelVorschlag = MkInhalt::kopiertext($x);
$zuLang = $konto['ok'] && $konto['max_sek'] > 0 && $dauer !== null && $dauer > $konto['max_sek'];
?>
<style>
.tt-seite{display:grid;gap:18px;grid-template-columns:minmax(0,300px) minmax(0,1fr);align-items:start}
@media (max-width:820px){.tt-seite{grid-template-columns:1fr}}
.tt-vorschau video{width:100%;aspect-ratio:9/16;object-fit:cover;border-radius:14px;background:#000;display:block}
.tt-konto{display:flex;gap:12px;align-items:center;margin:0 0 14px}
.tt-konto img{width:44px;height:44px;border-radius:50%;object-fit:cover;flex:none}
.tt-konto b{display:block}
.tt-gruppe{border:1px solid var(--linie);border-radius:12px;padding:12px 14px;margin:0 0 12px;min-width:0}
.tt-gruppe legend{padding:0 6px;font-weight:600}
.tt-haken{display:flex;gap:8px;align-items:center;margin:6px 0}
.tt-haken input{width:auto;margin:0}
.tt-haken.aus{opacity:.45}
.tt-schalter{display:flex;gap:10px;align-items:center;justify-content:space-between}
.tt-hinweis{font-size:var(--fs-klein);margin:6px 0 0 26px;color:var(--text2,#9aa)}
.tt-erklaerung{font-size:var(--fs-klein);line-height:1.5;margin:10px 0}
.tt-erklaerung a{color:inherit}
.tt-knoepfe{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.tt-knoepfe [disabled]{opacity:.5;cursor:not-allowed}
</style>

<div class="mk-kopf">
  <div>
    <h1>Auf TikTok veröffentlichen</h1>
    <div class="weg"><?= Fmt::h($x['titel']) ?> · <?= MkInhalt::spracheMarke((string) $x['sprache']) ?></div>
  </div>
  <a class="knopf" href="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>">‹ Zurück zum Stück</a>
</div>

<?php if ($gesendet !== ''): ?>
<div class="block" id="tt-stand" data-stand-url="<?= Fmt::h(url('tiktok/' . (int) $x['id']) . '?stand=1') ?>">
  <h2><?= ($ids['tt_modus'] ?? '') === 'entwurf' ? 'Als Entwurf an die TikTok-App geschickt' : 'An TikTok gesendet' ?></h2>
  <p style="margin:0 0 8px">After you finish publishing your content, it may take a few minutes for the content to process and be visible on your profile.<br>
    <span class="mk-fein">Nach dem Senden kann es einige Minuten dauern, bis TikTok das Video verarbeitet hat und es im Profil sichtbar ist.</span></p>
  <p style="margin:0"><b>Stand:</b> <span data-stand-text>wird abgefragt …</span></p>
</div>
<script>
(function () {
  var box = document.getElementById('tt-stand'); if (!box) { return; }
  var ziel = box.querySelector('[data-stand-text]'), n = 0;
  function frage() {
    fetch(box.getAttribute('data-stand-url'), {credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (d) {
      ziel.textContent = d.text || 'unbekannt';
      if (!d.fertig && ++n < 40) { setTimeout(frage, 6000); }
    }).catch(function () { ziel.textContent = 'Stand gerade nicht abrufbar.'; });
  }
  frage();
})();
</script>
<?php endif; ?>

<?php if (!$konto['ok']): ?>
<div class="block"><div class="hinweis schlecht" style="margin:0"><?= Fmt::h($konto['fehler']) ?></div>
  <p class="mk-fein" style="margin:10px 0 0"><a href="<?= Fmt::h(url('kanaele#pf-tiktok')) ?>">Kanäle › Verbinden &amp; Posten</a></p></div>
<?php elseif ($video === null): ?>
<div class="block"><div class="hinweis schlecht" style="margin:0">Für dieses Stück ist noch kein Video gewählt. Erst am Stück ein Video erzeugen und wählen.</div></div>
<?php else: ?>

<?php if (!$freigabe): ?>
<div class="hinweis" style="margin:0 0 14px">Die App ist von TikTok noch nicht geprüft: Bis dahin bietet TikTok nur „Nur ich“ an, und das Konto muss privat sein. „Als Entwurf in die TikTok-App“ geht trotzdem — dort postest du mit einem Tipp öffentlich.</div>
<?php endif; ?>

<form method="post" action="<?= Fmt::h(url('tiktok/' . (int) $x['id'])) ?>" class="tt-seite" id="tt-form">
  <input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>">
  <input type="hidden" name="tat" value="tiktok_senden">
  <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">

  <div class="block tt-vorschau">
    <h2>Vorschau</h2>
    <video src="<?= Fmt::h(url('medien/' . (int) $video['id'])) ?>" controls preload="metadata" playsinline></video>
    <p class="mk-fein" style="margin:8px 0 0"><?= $dauer !== null ? 'Länge ' . Fmt::h(str_replace('.', ',', (string) round($dauer, 1))) . ' s' : 'Länge unbekannt' ?><?= $konto['max_sek'] > 0 ? ' · erlaubt bis ' . (int) $konto['max_sek'] . ' s' : '' ?></p>
    <?php if ($zuLang): ?><div class="hinweis schlecht" style="margin:8px 0 0">Das Video ist länger, als dieses Konto posten darf. Bitte ein kürzeres Video wählen.</div><?php endif; ?>
  </div>

  <div class="block">
    <div class="tt-konto">
      <?php if ($konto['bild'] !== ''): ?><img src="<?= Fmt::h($konto['bild']) ?>" alt="" referrerpolicy="no-referrer"><?php endif; ?>
      <div><span class="mk-fein">Wird gepostet auf dem Konto</span><b><?= Fmt::h($konto['nickname'] !== '' ? $konto['nickname'] : $konto['nutzer']) ?></b><?php if ($konto['nutzer'] !== ''): ?><span class="mk-fein">@<?= Fmt::h($konto['nutzer']) ?></span><?php endif; ?></div>
    </div>

    <div class="feld">
      <label for="tt_titel">Text zum Video <span class="mk-fein">(Hashtags und @Erwähnungen erkennt TikTok; bis 2.200 Zeichen)</span></label>
      <textarea id="tt_titel" name="titel" rows="6" maxlength="2200" required><?= Fmt::h($titelVorschlag) ?></textarea>
    </div>

    <div class="feld">
      <label for="tt_sicht">Wer darf das Video sehen?</label>
      <select id="tt_sicht" name="sichtbarkeit" required>
        <option value="" selected disabled>Bitte wählen</option>
        <?php foreach ($konto['stufen'] as $st): ?>
          <option value="<?= Fmt::h($st) ?>"><?= Fmt::h(MkPlattform::TT_STUFEN[$st]) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="tt-hinweis" id="tt_privat" hidden style="margin-left:0">Branded content visibility cannot be set to private</p>
    </div>

    <fieldset class="tt-gruppe">
      <legend>Andere dürfen</legend>
      <?php foreach ([['kommentare', 'Kommentieren (Allow Comment)', $konto['kommentar_aus']], ['duett', 'Duett (Allow Duet)', $konto['duett_aus']], ['stitch', 'Stitch (Allow Stitch)', $konto['stitch_aus']]] as [$n, $wort, $aus]): ?>
        <label class="tt-haken<?= $aus ? ' aus' : '' ?>"><input type="checkbox" name="<?= $n ?>" value="1"<?= $aus ? ' disabled' : '' ?>> <?= Fmt::h($wort) ?><?= $aus ? ' <span class="mk-fein">— im Konto abgeschaltet</span>' : '' ?></label>
      <?php endforeach; ?>
    </fieldset>

    <fieldset class="tt-gruppe">
      <legend>Kennzeichnung</legend>
      <label class="tt-haken"><input type="checkbox" name="ki" value="1"<?= $ki ? ' checked' : '' ?>> KI-erzeugter Inhalt (AI-generated content)</label>
      <div class="tt-schalter" style="margin-top:8px">
        <label class="tt-haken" style="margin:0"><input type="checkbox" name="werbung" value="1" id="tt_werbung"> Werbung kennzeichnen (Disclose video content)</label>
      </div>
      <p class="mk-fein" style="margin:4px 0 0 26px">Wirbt das Video für Vecom (Preise, Angebot, „schreiben Sie uns“), hier einschalten und „Your brand“ wählen — TikTok verlangt die Kennzeichnung für Werbung in eigener Sache.</p>
      <div id="tt_werbung_wahl" hidden>
        <label class="tt-haken"><input type="checkbox" name="eigene_marke" value="1" id="tt_eigen"> Your brand <span class="mk-fein">— Werbung für das eigene Geschäft</span></label>
        <p class="tt-hinweis" data-hinweis="eigen" hidden>Your photo/video will be labeled as 'Promotional content'</p>
        <label class="tt-haken"><input type="checkbox" name="markenpartner" value="1" id="tt_partner"> Branded content <span class="mk-fein">— bezahlte Partnerschaft für Dritte</span></label>
        <p class="tt-hinweis" data-hinweis="partner" hidden>Your photo/video will be labeled as 'Paid partnership'</p>
      </div>
    </fieldset>

    <p class="tt-erklaerung" id="tt_erklaerung">By posting, you agree to TikTok's <a href="<?= Fmt::h(MkPlattform::TT_MUSIK) ?>" target="_blank" rel="noopener noreferrer">Music Usage Confirmation</a></p>
    <p class="tt-erklaerung" id="tt_erklaerung_marke" hidden>By posting, you agree to TikTok's <a href="<?= Fmt::h(MkPlattform::TT_MARKE) ?>" target="_blank" rel="noopener noreferrer">Branded Content Policy</a> and <a href="<?= Fmt::h(MkPlattform::TT_MUSIK) ?>" target="_blank" rel="noopener noreferrer">Music Usage Confirmation</a></p>

    <label class="tt-haken"><input type="checkbox" name="zustimmung" value="1" id="tt_zust" required> Ja, dieses Video soll jetzt an TikTok gehen.</label>

    <div class="tt-knoepfe" style="margin-top:12px">
      <span id="tt_knopf_huelle" title=""><button class="knopf haupt" name="modus" value="posten" id="tt_senden"<?= $zuLang ? ' disabled' : '' ?>>Auf TikTok veröffentlichen</button></span>
      <button class="knopf" name="modus" value="entwurf" formnovalidate id="tt_entwurf">Als Entwurf in die TikTok-App</button>
    </div>
    <p class="mk-fein" style="margin:8px 0 0">„Als Entwurf“: Das Video landet in der TikTok-App (Benachrichtigung im Postfach); Text, Sichtbarkeit und Kennzeichnung wählst du dort und tippst auf „Posten“.</p>
  </div>
</form>

<script>
(function () {
  var f = document.getElementById('tt-form'); if (!f) { return; }
  var sicht = f.querySelector('#tt_sicht'), werbung = f.querySelector('#tt_werbung'), wahl = f.querySelector('#tt_werbung_wahl'),
      eigen = f.querySelector('#tt_eigen'), partner = f.querySelector('#tt_partner'), zust = f.querySelector('#tt_zust'),
      knopf = f.querySelector('#tt_senden'), huelle = f.querySelector('#tt_knopf_huelle'), titel = f.querySelector('#tt_titel'),
      e1 = f.querySelector('#tt_erklaerung'), e2 = f.querySelector('#tt_erklaerung_marke'), zuLang = <?= $zuLang ? 'true' : 'false' ?>;
  var nurIch = sicht.querySelector('option[value="SELF_ONLY"]');
  function stand() {
    var an = werbung.checked;
    wahl.hidden = !an;
    if (!an) { eigen.checked = false; partner.checked = false; }
    f.querySelector('[data-hinweis="eigen"]').hidden = !(eigen.checked && !partner.checked);
    f.querySelector('[data-hinweis="partner"]').hidden = !partner.checked;
    /* Markenpartnerschaft ist nie privat: „Nur ich“ sperren — und wechseln, falls schon gewählt. */
    if (nurIch) {
      nurIch.disabled = partner.checked;
      nurIch.title = partner.checked ? 'Branded content visibility cannot be set to private' : '';
      if (partner.checked && sicht.value === 'SELF_ONLY') { sicht.value = ''; }
      f.querySelector('#tt_privat').hidden = !partner.checked;
    }
    e1.hidden = partner.checked; e2.hidden = !partner.checked;
    var keineWahl = an && !eigen.checked && !partner.checked;
    huelle.title = keineWahl ? 'You need to indicate if your content promotes yourself, a third party, or both' : '';
    knopf.disabled = zuLang || keineWahl || sicht.value === '' || !zust.checked || titel.value.trim() === '';
  }
  [sicht, werbung, eigen, partner, zust, titel].forEach(function (el) { el.addEventListener('change', stand); el.addEventListener('input', stand); });
  stand();
})();
</script>
<?php endif; ?>
