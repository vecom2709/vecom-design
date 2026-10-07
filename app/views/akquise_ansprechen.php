<?php
/** @var array $f @var array $befunde @var array $analysen @var bool $gesperrt */
/* ANSPRECHEN (29.09.2026, Uwe: Ja zu K2 und K3)
   Ein Kasten für alles: fertige Texte für E-Mail, WhatsApp, Anruf und Besuch
   in der Sprache des Betriebs. E-Mail und WhatsApp öffnen Uwes eigenes
   Programm -- frei erst nach einer Zustimmung (Gate). Ohne Zustimmung führt
   der Kasten zu Anruf oder Besuch und darunter zu „Hat zugestimmt“. */
require_once dirname(__DIR__) . '/src/AkquiseAnsprechen.php';
require_once dirname(__DIR__) . '/src/AkquiseMail.php';
$anFid = (int) $f['id'];
$anAnalyse = '';
foreach ($analysen as $x) { if ((int) $x['aktiv'] === 1) { $anAnalyse = AkquiseAnalyse::adresse($x); break; } }
$anSp = in_array($_GET['sp'] ?? '', ['it', 'de', 'en'], true) ? (string) $_GET['sp'] : null;
$an = AkquiseAnsprechen::paket($f, $befunde, $anSp, $anAnalyse);
$anStand = AkquiseAnsprechen::stand($f);
$anRes = PartnerRecherche::reserviertVon($anFid);
$anStart = $an['frei']['email'] ? 'email' : ($an['frei']['whatsapp'] ? 'whatsapp' : ($an['tel'] ? 'anruf' : 'besuch'));
$anWaVor = (string) ($f['whatsapp'] ?? '') ?: (preg_match('~^(\+39|0039)?3\d{8,9}$~', (string) preg_replace('~[\s./-]~', '', (string) ($f['telefon'] ?? ''))) ? (string) $f['telefon'] : '');
$anH = static fn(?string $s): string => Fmt::h((string) $s);
?>
<style>
  .an-kopf{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px}
  .an-kopf h2{margin:0}
  .an-sprache{display:flex;gap:4px}
  .an-sprache a{font-size:var(--fs-klein);padding:3px 9px;border-radius:999px;border:1px solid var(--linie);color:var(--dim)}
  .an-sprache a.an{color:var(--text);border-color:var(--linie2);background:var(--flaeche2)}
  .an-hilfe{font-size:14px;line-height:1.55;color:var(--dim);margin:6px 0 12px}
  .an-wege{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;background:var(--flaeche2);border:1px solid var(--linie);border-radius:12px;padding:4px;margin-bottom:12px}
  .an-wege button{font:inherit;font-size:13.5px;padding:9px 4px;border-radius:9px;border:0;background:none;color:var(--dim);cursor:pointer;display:flex;gap:6px;justify-content:center;align-items:center}
  .an-wege button[aria-selected="true"]{background:var(--flaeche);color:var(--text);box-shadow:0 0 0 1px var(--linie2) inset}
  .an-wege button .zu{font-size:var(--fs-klein);opacity:.7}
  .an-wege button:focus-visible{outline:2px solid var(--cyan);outline-offset:1px}
  .an-feld{margin:0 0 8px}
  .an-feld label{display:block;font-size:var(--fs-mini);color:var(--leise);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px}
  .an-text{width:100%;box-sizing:border-box;font:inherit;font-size:14px;line-height:1.55;min-height:260px;resize:vertical}
  .an-knoepfe{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px}
  .an-zu{border:1px dashed var(--linie2);border-radius:12px;padding:14px;font-size:14px;line-height:1.55;color:var(--dim)}
  .an-zu b{color:var(--text)}
  .an-skript{list-style:none;margin:0;padding:0;display:grid;gap:10px}
  .an-skript li{border-left:3px solid var(--linie2);padding:2px 0 2px 12px}
  .an-skript li.satz{border-left-color:var(--cyan)}
  .an-skript small{display:block;font-size:var(--fs-mini);color:var(--leise);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px}
  .an-skript p{margin:0;font-size:15px;line-height:1.55;color:var(--text)}
  .an-ja{margin-top:16px;border-top:1px solid var(--linie);padding-top:14px}
  .an-ja summary{cursor:pointer;font-weight:650;font-size:15px}
  .an-ja .reihe2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .an-ja .weg{display:flex;gap:14px;margin:10px 0}
  .an-ja .weg label,.an-ja .kanal{display:flex;gap:8px;align-items:center;font-size:14px;color:var(--text)}
  .an-ja input[type=radio],.an-ja input[type=checkbox]{width:auto;flex:none}
  .an-ja .kanal{margin:10px 0 4px}
  .an-ja blockquote{margin:8px 0;padding:10px 12px;border-radius:10px;background:var(--flaeche2);font-size:14px;line-height:1.5;color:var(--text)}
  @media (max-width:520px){ .an-wege{grid-template-columns:repeat(2,1fr)} .an-ja .reihe2{grid-template-columns:1fr} }
</style>
<div class="block akq-fokus" id="ansprechen">
  <div class="an-kopf">
    <h2>Ansprechen</h2>
    <span class="akq-ampel <?= $anH($anStand['farbe']) ?>"><i></i><?= $anH($anStand['wort']) ?></span>
  </div>
  <?php if ($gesperrt || $anStand['farbe'] === 'rot'): ?>
    <div class="leer">Nicht ansprechen — der Betrieb hat abgelehnt oder steht auf der Sperrliste.</div>
  <?php elseif ($anRes): ?>
    <div class="leer">Partner <?= $anH((string) $anRes['name']) ?> kümmert sich um diesen Betrieb (bis <?= $anH(date('d.m.Y', strtotime((string) $anRes['bis']))) ?>). Bis dahin nicht selbst ansprechen.</div>
  <?php else: ?>
    <?php /* Hilfesatz (07.10.2026, Uwe: „zeigt immer noch muss anrufen oder vorbeigehen“): Er sagte „Er hat zugestimmt“,
             sobald die Mail öffnen durfte -- seit dem 06.10. also auch ohne Zustimmung. Jetzt drei ehrliche Fälle. */ ?>
    <p class="an-hilfe"><?= $anStand['farbe'] === 'gruen'
      ? 'Er hat zugestimmt: ' . ($an['frei']['whatsapp'] ? 'E-Mail oder WhatsApp' : 'E-Mail') . ' öffnen, lesen, selbst senden. Die Folge-Mails laufen danach automatisch.'
      : ($an['frei']['email']
        ? '<b>E-Mail geht:</b> im Reiter „E-Mail“ den Text prüfen und in deinem eigenen Mailprogramm öffnen — ohne dokumentierten Versandgrund bestätigst du vorher den Hinweis. WhatsApp öffnet erst nach seiner Zustimmung: am einfachsten in der Mail danach fragen und unten „Hat zugestimmt“ eintragen.'
        : 'Keine E-Mail-Adresse bekannt und noch keine Zustimmung für WhatsApp. Anrufen oder vorbeigehen und unten eine Zustimmung eintragen — oder unter „Angaben ändern“ eine E-Mail-Adresse ergänzen, dann geht die Mail.') ?></p>
    <div class="an-kopf" style="margin:0 0 8px">
      <span class="akq-klein">Texte auf <?= $anH(AkquiseText::SPRACHEN[$an['sprache']]) ?></span>
      <nav class="an-sprache" aria-label="Sprache der Texte">
        <?php foreach (AkquiseText::SPRACHEN as $k => $w): ?>
          <a href="<?= $anH(url('akquise/' . $anFid) . '?sp=' . $k . '#ansprechen') ?>" class="<?= $k === $an['sprache'] ? 'an' : '' ?>" lang="<?= $k ?>"><?= strtoupper($k) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <div class="an-wege" role="tablist" aria-label="Weg">
      <?php foreach (['email' => 'E-Mail', 'whatsapp' => 'WhatsApp', 'anruf' => 'Anruf', 'besuch' => 'Besuch'] as $k => $w):
        $zu = in_array($k, ['email', 'whatsapp'], true) && !$an['frei'][$k]; ?>
        <button type="button" role="tab" id="an-t-<?= $k ?>" aria-controls="an-p-<?= $k ?>" aria-selected="<?= $k === $anStart ? 'true' : 'false' ?>"<?= $k === $anStart ? '' : ' tabindex="-1"' ?>>
          <?= $anH($w) ?><?= $zu ? '<span class="zu" aria-label="noch gesperrt">🔒</span>' : '' ?></button>
      <?php endforeach; ?>
    </div>

    <?php foreach (['email', 'whatsapp'] as $k): $frei = $an['frei'][$k]; ?>
      <div role="tabpanel" id="an-p-<?= $k ?>" aria-labelledby="an-t-<?= $k ?>"<?= $k === $anStart ? '' : ' hidden' ?>>
        <?php if (!$frei && $k === 'email' && AkquiseMail::kann($f)['entwurf']): $x = $an['email']; ?>
          <?php /* Entwurf immer (06.10.2026): bearbeiten und kopieren ja, Öffnen/Senden erst mit dokumentiertem Versandgrund. */ ?>
          <div class="an-zu" style="margin-bottom:10px"><b>Entwurf — noch kein Versand.</b> Du kannst den Text bearbeiten und kopieren.
            Senden geht erst, wenn oben bei „E-Mail“ ein Versandgrund dokumentiert und freigegeben ist. <a href="#versandgrund">Versandgrund dokumentieren</a></div>
          <div class="an-feld"><label for="an-betreff">Betreff</label><input id="an-betreff" value="<?= $anH($x['betreff']) ?>" data-an="betreff"></div>
          <div class="an-feld"><label for="an-text-email">Text — Entwurf</label>
            <textarea id="an-text-email" class="an-text" data-an="text-email" rows="16"><?= $anH($x['text']) ?></textarea></div>
          <?php $ws = ['kanal' => 'email', 'senden' => false, 'betreff' => $x['betreff'], 'text' => $x['text'], 'betreffFeld' => 'an-betreff', 'textFeld' => 'an-text-email'];
                require __DIR__ . '/akquise_werkstatt.php'; /* Modul D: Prüfliste auch im Entwurf */ ?>
          <div class="an-knoepfe">
            <button class="knopf" type="button" data-an-kopieren="text-email">Text kopieren</button>
            <span class="akq-klein">an <?= $anH((string) (Akquise::normEmail((string) $f['email']) ?? $f['email'])) ?></span>
          </div>
        <?php elseif (!$frei): ?>
          <div class="an-zu"><b>Erst nach seiner Zustimmung.</b> Eine Werbe-<?= $k === 'email' ? 'Mail' : 'Nachricht' ?> ohne Zustimmung ist in Italien und Deutschland verboten — auch von Hand.
            <?= $k === 'whatsapp' && $an['frei']['email'] ? 'Frag in deiner E-Mail, ob du ihm per WhatsApp schreiben darfst — oder ruf an bzw. geh vorbei.' : 'Ruf an oder geh vorbei (Reiter „Anruf“ oder „Besuch“).' ?> Sagt er Ja, unten „Hat zugestimmt“ ausfüllen: Dann steht hier der fertige Text zum Öffnen.
            <?php if ($k === 'whatsapp' && AkquiseGate::einwilligungDeckt($f, 'email')): ?><br><span class="akq-klein">Für E-Mail hat er schon zugestimmt — für WhatsApp noch nicht.</span><?php endif; ?></div>
        <?php else: $x = $an[$k]; ?>
          <?php if ($k === 'email'): /* Senden über das eigene Mailprogramm (06.10.2026): mailto-Link, das System verschickt nichts selbst */ ?>
            <form method="post" action="<?= $anH(url('akquise')) ?>" id="an-direkt" data-an-mailto><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_mail_mailto"><input type="hidden" name="firma" value="<?= $anFid ?>">
            <div class="an-feld"><label for="an-betreff">Betreff</label><input id="an-betreff" name="betreff" required maxlength="200" value="<?= $anH($x['betreff']) ?>" data-an="betreff"></div>
          <?php endif; ?>
          <div class="an-feld"><label for="an-text-<?= $k ?>">Text <span style="text-transform:none;letter-spacing:0">— du kannst ihn hier noch ändern</span></label>
            <textarea id="an-text-<?= $k ?>" class="an-text" data-an="text-<?= $k ?>"<?= $k === 'email' ? ' name="text" required' : '' ?> rows="<?= $k === 'email' ? 16 : 9 ?>"><?= $anH($x['text']) ?></textarea></div>
          <?php if ($k === 'whatsapp'): $ws = ['kanal' => 'whatsapp', 'senden' => false, 'betreff' => '', 'text' => $x['text'], 'betreffFeld' => '', 'textFeld' => 'an-text-whatsapp'];
                require __DIR__ . '/akquise_werkstatt.php'; endif; ?>
          <?php if ($k === 'email'): ?>
            <p class="akq-klein" style="margin:0 0 8px">„Senden“ öffnet <b>dein eigenes Mailprogramm</b> (Outlook, Apple Mail …) mit Empfänger, Betreff und Text — du schickst die Mail dort selbst ab, mit deiner eigenen Adresse.
              Ein Abmeldelink wird angehängt. Gespeichert wird nur der Zeitpunkt.<?php $anK = AkquiseMail::kann($f); echo !$anK['freigabe'] ? ' <b>Kein Versandgrund dokumentiert</b> — vor dem Öffnen bestätigst du den Hinweis in der Prüfung.' : ($anK['werbung'] ? '' : ' <b>Der dokumentierte Grund deckt keine Werbung</b> — nur die Antwort bzw. geschäftliche Nachricht.'); ?></p>
            <?php $ws = ['kanal' => 'email', 'senden' => true, 'betreff' => $x['betreff'], 'text' => $x['text'], 'betreffFeld' => 'an-betreff', 'textFeld' => 'an-text-email'];
                  require __DIR__ . '/akquise_werkstatt.php'; /* Modul D: dieselbe Prüfung läuft beim Senden auf dem Server */ ?>
            <button class="knopf haupt">Senden — im Mailprogramm öffnen</button>
            <span class="akq-klein" data-an-mailto-status></span>
            </form>
          <?php endif; ?>
          <div class="an-knoepfe">
            <?php if ($k === 'whatsapp'): ?>
            <a class="knopf haupt" data-an-oeffnen="whatsapp" href="<?= $anH((string) $x['link']) ?>" target="_blank" rel="noopener noreferrer">In WhatsApp öffnen</a>
            <?php endif; ?>
            <button class="knopf" type="button" data-an-kopieren="text-<?= $k ?>">Text kopieren</button>
            <span class="akq-klein" data-an-status="<?= $k ?>"><?= $k === 'email' ? 'an ' . $anH((string) (Akquise::normEmail((string) $f['email']) ?? $f['email'])) : 'an ' . $anH((string) $f['whatsapp']) ?></span>
          </div>
          <p class="akq-klein" style="margin-top:8px">Beim Öffnen wird der Zeitpunkt im Verlauf vermerkt. Antwortet er „STOP“: oben bei „E-Mail“ „Nicht kontaktieren“ setzen.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php foreach (['anruf', 'besuch'] as $k): ?>
      <div role="tabpanel" id="an-p-<?= $k ?>" aria-labelledby="an-t-<?= $k ?>"<?= $k === $anStart ? '' : ' hidden' ?>>
        <?php if ($k === 'anruf'): ?>
          <div class="an-knoepfe" style="margin:0 0 12px">
            <?php if ($an['tel']): ?><a class="knopf haupt" href="<?= $anH($an['tel']) ?>">Anrufen: <?= $anH((string) $f['telefon']) ?></a>
            <?php else: ?><span class="akq-klein">Keine Telefonnummer bekannt — unter „Angaben ändern“ eintragen.</span><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="an-knoepfe" style="margin:0 0 12px">
            <a class="knopf" href="<?= $anH(url('akquise/' . $anFid . '/vorort')) ?>">Vor Ort zeigen (Handy)</a>
            <form method="post" action="<?= $anH(url('akquise')) ?>" style="display:inline;margin:0"><?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_karte"><input type="hidden" name="firma" value="<?= $anFid ?>">
              <button class="knopf">QR-Karte zum Dalassen</button></form>
            <?php if (trim((string) ($f['adresse'] ?? '')) !== ''): ?>
              <a class="knopf" target="_blank" rel="noopener" href="<?= $anH('https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(trim($f['name'] . ' ' . $f['adresse'] . ' ' . ($f['plz'] ?? '') . ' ' . ($f['stadt'] ?? '')))) ?>">Route</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <ol class="an-skript" lang="<?= $anH($an['sprache']) ?>">
          <?php foreach ($an[$k] as [$was, $satz]): ?>
            <li class="<?= str_starts_with($was, 'Wenn ja') ? 'satz' : '' ?>"><small lang="de"><?= $anH($was) ?></small><p><?= $anH($satz) ?></p></li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endforeach; ?>

    <details class="an-ja" id="zugestimmt"<?= !$an['frei']['email'] && !$an['frei']['whatsapp'] ? ' open' : '' ?>>
      <summary><?= $an['frei']['email'] || $an['frei']['whatsapp'] ? 'Weitere Zustimmung eintragen (z. B. WhatsApp dazu)' : 'Hat zugestimmt — eintragen' ?></summary>
      <form method="post" action="<?= $anH(url('akquise')) ?>" style="margin-top:10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_zugestimmt"><input type="hidden" name="firma" value="<?= $anFid ?>">
        <div class="weg" role="radiogroup" aria-label="Wie">
          <label><input type="radio" name="weg" value="anruf" checked> am Telefon</label>
          <label><input type="radio" name="weg" value="besuch"> beim Besuch</label>
        </div>
        <div class="an-feld"><label for="an-person">Wer hat zugestimmt?</label>
          <input id="an-person" name="person" required minlength="2" maxlength="80" value="<?= $anH((string) ($f['ansprechpartner'] ?? '')) ?>" placeholder="z. B. Maria Rossi, Inhaberin"></div>
        <label class="kanal"><input type="checkbox" name="per_email" value="1" checked data-an-schalter="an-mail"> per E-Mail</label>
        <div class="an-feld"><input id="an-mail" name="email" type="email" maxlength="190" value="<?= $anH((string) ($f['email'] ?? '')) ?>" placeholder="E-Mail-Adresse, die er genannt hat" aria-label="E-Mail-Adresse"></div>
        <?php if (!AkquiseAnsprechen::nurMail($f)): ?>
        <label class="kanal"><input type="checkbox" name="per_whatsapp" value="1"<?= $anWaVor !== '' ? ' checked' : '' ?> data-an-schalter="an-wa"> per WhatsApp</label>
        <div class="an-feld"><input id="an-wa" name="whatsapp" inputmode="tel" maxlength="40" value="<?= $anH($anWaVor) ?>" placeholder="+39 3…" aria-label="WhatsApp-Nummer"></div>
        <?php else: ?><p class="akq-klein" style="margin:4px 0 0">Deutscher Betrieb: nur E-Mail.</p><?php endif; ?>
        <p class="akq-klein" style="margin:10px 0 0">Diese Frage hast du vorgelesen oder gezeigt, und er hat Ja gesagt (wird so gespeichert):</p>
        <blockquote lang="<?= $anH($an['sprache']) ?>"><?= $anH($an['wortlaut']) ?></blockquote>
        <label class="akq-haken"><input type="checkbox" name="vorgelesen" value="1" required> Ja — vorgelesen oder gezeigt, und er hat zugestimmt.</label>
        <label class="akq-haken"><input type="checkbox" name="bereich" value="1" checked> Seinen persönlichen Bereich (Dashboard) gleich per Mail schicken</label>
        <button class="knopf haupt">Speichern — er hat zugestimmt</button>
      </form>
      <form method="post" action="<?= $anH(url('akquise')) ?>" style="margin-top:10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_kein_interesse"><input type="hidden" name="firma" value="<?= $anFid ?>">
        <button class="knopf">Kein Interesse — nie mehr ansprechen</button>
      </form>
    </details>
  <?php endif; ?>
</div>
<script>
(function () {
  var box = document.getElementById('ansprechen'); if (!box) return;
  var tabs = [].slice.call(box.querySelectorAll('[role=tab]'));
  function zeige(t) {
    tabs.forEach(function (x) { var an = x === t; x.setAttribute('aria-selected', an ? 'true' : 'false'); x.tabIndex = an ? 0 : -1;
      document.getElementById(x.getAttribute('aria-controls')).hidden = !an; });
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { zeige(t); });
    t.addEventListener('keydown', function (e) {
      var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : null; if (n === null) return;
      var z = tabs[(n + tabs.length) % tabs.length]; zeige(z); z.focus(); e.preventDefault();
    });
  });
  /* Geänderter Text → der Öffnen-Link nimmt ihn mit */
  function neu(k) {
    var a = box.querySelector('[data-an-oeffnen="' + k + '"]'); if (!a) return;
    var text = box.querySelector('[data-an="text-' + k + '"]').value, h = a.getAttribute('href');
    if (k === 'email') {
      var b = box.querySelector('[data-an="betreff"]').value;
      a.setAttribute('href', h.split('?')[0] + '?subject=' + encodeURIComponent(b) + '&body=' + encodeURIComponent(text));
    } else { a.setAttribute('href', h.split('?')[0] + '?text=' + encodeURIComponent(text)); }
  }
  box.querySelectorAll('[data-an]').forEach(function (el) {
    el.addEventListener('input', function () { neu(el.getAttribute('data-an') === 'text-whatsapp' ? 'whatsapp' : 'email'); });
  });
  box.querySelectorAll('[data-an-kopieren]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = box.querySelector('[data-an="' + b.getAttribute('data-an-kopieren') + '"]');
      var betreff = b.getAttribute('data-an-kopieren') === 'text-email' ? box.querySelector('[data-an="betreff"]').value + '\n\n' : '';
      if (navigator.clipboard) { navigator.clipboard.writeText(betreff + t.value).then(function () { b.textContent = '✓ Kopiert'; }); }
    });
  });
  /* Beim Öffnen still im Verlauf vermerken */
  var csrf = box.querySelector('input[name=_csrf]');
  box.querySelectorAll('[data-an-oeffnen]').forEach(function (a) {
    a.addEventListener('click', function () {
      if (!csrf) return;
      var d = new FormData(); d.append('_csrf', csrf.value); d.append('tat', 'akq_manuell'); d.append('firma', '<?= $anFid ?>');
      d.append('kanal', a.getAttribute('data-an-oeffnen')); d.append('still', '1');
      try { fetch(<?= json_encode(url('akquise')) ?>, { method: 'POST', body: d, keepalive: true, credentials: 'same-origin' }); } catch (e) {}
      var s = box.querySelector('[data-an-status="' + a.getAttribute('data-an-oeffnen') + '"]'); if (s) s.textContent = '✓ im Verlauf vermerkt';
    });
  });
  /* Senden über das eigene Mailprogramm (06.10.2026): der Server prüft und erzeugt den Link, gespeichert wird nur der Zeitpunkt */
  var mf = box.querySelector('[data-an-mailto]');
  if (mf && window.fetch) {
    mf.addEventListener('submit', function (e) {
      e.preventDefault();
      var st = mf.querySelector('[data-an-mailto-status]'), d = new FormData(mf); d.append('js', '1');
      fetch(mf.getAttribute('action'), { method: 'POST', body: d, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) { if (st) st.textContent = '⛔ ' + (j.fehler || 'Nicht möglich.'); return; }
          if (j.lang && navigator.clipboard) { navigator.clipboard.writeText(j.text).catch(function () {}); }
          if (st) st.textContent = j.lang ? '✓ Mailprogramm geöffnet — der Text ist lang und liegt zusätzlich in der Zwischenablage, falls dein Programm ihn kürzt.' : '✓ Mailprogramm geöffnet — dort selbst auf Senden drücken.';
          window.location.href = j.link;
        })
        .catch(function () { mf.submit(); });
    });
  }
  /* Weg abgewählt → Feld nicht mehr Pflicht */
  box.querySelectorAll('[data-an-schalter]').forEach(function (c) {
    var f = document.getElementById(c.getAttribute('data-an-schalter'));
    function s() { f.disabled = !c.checked; f.required = c.checked; }
    c.addEventListener('change', s); s();
  });
})();
</script>
<script src="/assets/js/akquise-werkstatt.js" defer></script>
