<?php
/**
 * Marketing · Kanäle verbinden (01.10.2026, Uwe: Ja zu P1–P3).
 * Erwartet: $stand (MkKanaele::stand), $fehl (MkKanaele::fehlgeschlagen), $geplant (MkKanaele::geplant),
 *           $me (MetaSeite::einstellungen), $handy (MkHandy::stand, optional).
 */
require __DIR__ . '/mk_stil.php';
$fmtZeit = static fn(string $t): string => $t !== '' ? ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int) date('w', strtotime($t))] . ' ' . date('d.m. H:i', strtotime($t)) : '';
$handy = $handy ?? ['bereit' => false, 'text' => ''];
$csrf = static fn(): string => '<input type="hidden" name="_csrf" value="' . Fmt::h(Csrf::token()) . '">';
?>
<div class="mk-kopf">
  <div>
    <h1>Kanäle verbinden</h1>
    <div class="weg">Hier siehst du, ob deine Beiträge ankommen — welcher Kanal verbunden ist, was als Nächstes rausgeht und was nicht geklappt hat.</div>
  </div>
</div>

<?php if ($fehl): ?>
<div class="block" id="fehler" style="border-color:rgba(230,110,90,.55)">
  <h2>Nicht rausgegangen <span class="mehr"><?= count($fehl) ?> · zweimal versucht</span></h2>
  <?php foreach ($fehl as $x): ?>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 0;border-top:1px solid var(--linie)">
      <div style="flex:1;min-width:240px"><a href="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>"><b><?= Fmt::h((string) $x['titel']) ?></b></a> · <?= Fmt::h(MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform']) ?>
        <div class="mk-fein"><?= Fmt::h((string) $x['post_fehler']) ?></div></div>
      <form method="post" action="<?= Fmt::h(url('kanaele')) ?>" style="margin:0"><?= $csrf() ?><input type="hidden" name="tat" value="inhalt_neu_versuchen"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><button class="knopf haupt">Jetzt noch einmal posten</button></form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="block">
  <h2>Postet Vecom selbst <span class="mehr">zur Sendezeit 18:30, je Kanal höchstens ein Beitrag am Tag</span></h2>
  <div class="mk-kanaele">
    <?php foreach (MkKanaele::AUTO as $kk => $kn): $s = $stand[$kk]; $g = $geplant[$kk] ?? null; ?>
      <div class="mk-kanal <?= $s['bereit'] ? 'gut' : 'offen' ?>">
        <div class="mk-kanal__kopf"><i aria-hidden="true"></i><b><?= Fmt::h($kn) ?></b></div>
        <p><?= Fmt::h($s['text']) ?></p>
        <p class="mk-fein"><?= $g ? $g['n'] . ' eingeplant · nächster ' . Fmt::h($fmtZeit($g['naechst'])) : 'nichts eingeplant' ?></p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if ($s['bereit']): ?>
            <form method="post" action="<?= Fmt::h(url('kanaele')) ?>" style="margin:0"><?= $csrf() ?><input type="hidden" name="tat" value="kanal_pruefen"><input type="hidden" name="kanal" value="<?= $kk ?>"><button class="knopf">Verbindung prüfen</button></form>
          <?php endif; ?>
          <a class="knopf<?= $s['bereit'] ? '' : ' haupt' ?>" href="<?= Fmt::h(str_starts_with($s['einrichten'], '#') ? $s['einrichten'] : url($s['einrichten'])) ?>"><?= $s['bereit'] ? 'Ändern' : 'Jetzt verbinden' ?></a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="mk-fein" style="margin:10px 0 0">„Verbindung prüfen“ liest nur den Namen der Seite bzw. des Kanals — es wird nichts gepostet.</p>
</div>

<div class="block">
  <h2>Kommt aufs Handy <span class="mehr">TikTok, LinkedIn, Google-Profil und YouTube erlauben kleinen Konten kein automatisches Posten ohne Prüfung</span></h2>
  <p style="margin:0 0 10px;max-width:75ch;line-height:1.6">Zur Sendezeit schickt dir der Vecom-Bot das fertige Stück per Telegram: Bild oder Video und den Text mit Link. Du tippst auf „Teilen“, wählst die App, fügst den Text ein — dann im Bot „Gepostet“ drücken. So zählt jeder Klick trotzdem.</p>
  <div class="mk-kanaele">
    <?php foreach (MkKanaele::HANDY as $kk => $kn): $g = $geplant[$kk] ?? null; $kAuto = !empty(($pf ?? [])[$kk]['bereit']); ?>
      <div class="mk-kanal <?= $handy['bereit'] || $kAuto ? 'gut' : 'offen' ?>">
        <div class="mk-kanal__kopf"><i aria-hidden="true"></i><b><?= Fmt::h($kn) ?></b></div>
        <?php if ($kAuto): ?><p class="mk-fein">postet jetzt automatisch</p><?php endif; ?>
        <p class="mk-fein"><?= $g ? $g['n'] . ' eingeplant · nächster ' . Fmt::h($fmtZeit($g['naechst'])) : 'nichts eingeplant' ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="mk-fein" style="margin:10px 0 0"><?= Fmt::h($handy['text']) ?></p>
  <p class="mk-fein" style="margin:4px 0 0">Voll automatisch geht es, sobald die Plattformen die App geprüft haben — <a href="#voll">die Anträge stehen unten</a>.</p>
</div>

<?php require_once dirname(__DIR__) . '/src/MkPlattform.php'; $pf = $pf ?? [];
$pfAntrag = [
  'linkedin' => ['Dauer: meist Tage bis wenige Wochen · braucht eingetragene Firma und eine geschäftliche E-Mail (keine gmx-Adresse)', [
     'Auf <b>linkedin.com/developers</b> eine neue App anlegen und mit der Vecom-Unternehmensseite verknüpfen; der Seiten-Admin bestätigt.',
     'Unter „Products“ nur <b>„Community Management API“</b> beantragen (Firmenname, Adresse, Website, Datenschutzerklärung).',
     'Unter „Auth“ die Rückruf-Adresse unten eintragen, Client-ID und Client-Secret hier speichern, die Organisations-ID der Seite eintragen.',
     'Nach der Freigabe: „Verbinden“ drücken (du musst Admin der Seite sein), dann den Haken setzen. Nach 12 Monaten verlangt LinkedIn die Standard-Stufe mit kurzem Bildschirmvideo.']],
  'google' => ['Dauer: laut Google bis zu 14 Tage · das Profil muss bestätigt und mindestens 60 Tage alt sein, mit eingetragener Website', [
     'Auf <b>console.cloud.google.com</b> ein Projekt anlegen, die Projektnummer notieren.',
     'Das <b>„GBP API contact form“</b> ausfüllen, Option „Application for Basic API Access“, mit der E-Mail, die Inhaber des Profils ist.',
     'Nach der Freigabe die APIs „My Business Account Management“, „Business Information“ und „Google My Business API“ aktivieren; OAuth-Zustimmungsbildschirm auf <b>„In production“</b> stellen (sonst verfällt die Verbindung nach 7 Tagen).',
     'OAuth-Client (Webanwendung) mit der Rückruf-Adresse unten anlegen, Client-ID und -Secret hier speichern, accountId/locationId eintragen, „Verbinden“, Haken setzen.']],
  'youtube' => ['Dauer: meist einige Wochen · bis zur Prüfung lädt YouTube jedes Video nur privat hoch', [
     'Im selben oder einem eigenen Google-Cloud-Projekt die <b>YouTube Data API v3</b> aktivieren, Zustimmungsbildschirm auf „In production“.',
     'OAuth-Client (Webanwendung) mit der Rückruf-Adresse unten anlegen, Client-ID und -Secret hier speichern, „Verbinden“ mit dem Vecom-Kanal.',
     'Das Formular <b>„YouTube API Services – Audit and Quota Extension“</b> (support.google.com/youtube/contact/yt_api_form) ausfüllen: Zweck „eigene Shorts vom eigenen Server“, Datenschutzerklärung, Screenshot dieser Seite.',
     'Nach bestandener Prüfung den Haken setzen. Bis 100 Uploads am Tag sind frei.']],
  'tiktok' => ['Dauer: Tage bis Wochen · bis zur Prüfung nur „nur ich“ sichtbar und das Konto muss privat sein', [
     'Auf <b>developers.tiktok.com</b> eine App anlegen, Produkt <b>„Content Posting API“</b> mit „Direct Post“ hinzufügen, Rechte video.publish und video.upload beantragen.',
     'Rückruf-Adresse unten eintragen, Client-Key und -Secret hier speichern, „Verbinden“ mit dem Vecom-Konto.',
     'Prüfung beantragen. TikTok verlangt, dass vor dem Posten Konto, Vorschau, Sichtbarkeit und Kennzeichnung gezeigt werden — das übernimmt der Freigabe-Stapel (dein Ja je Stück).',
     'Nach der Freigabe den Haken setzen; sonst bleibt TikTok beim Handy-Weg.']],
]; ?>
<div class="block" id="voll">
  <h2>Voll automatisch: LinkedIn, Google, YouTube, TikTok <span class="mehr">nach Prüfung durch die Plattform — bis dahin kommt alles aufs Handy</span></h2>
  <p style="margin:0 0 12px;max-width:80ch;line-height:1.6">Alle vier lassen eigene Beiträge vom eigenen Server posten, aber erst, wenn sie die App geprüft haben. Je Plattform: Antrag stellen (Schritte unten), Schlüssel hier eintragen, „Verbinden“, Haken „Freigabe erhalten“. Die Schlüssel liegen verschlüsselt auf dem Server und werden nie angezeigt.</p>
  <?php foreach (MkPlattform::ALLE as $pk => [$pn]): $e = $pf[$pk] ?? MkPlattform::einstellungen($pk) + ['bereit' => false]; [$pDauer, $pSchritte] = $pfAntrag[$pk]; ?>
    <details class="mk-pf" id="pf-<?= $pk ?>"<?= $e['client_id'] !== '' && !$e['bereit'] ? ' open' : '' ?>>
      <summary><i class="mk-pf__punkt <?= $e['bereit'] ? 'gut' : ($e['verbunden'] ? 'halb' : '') ?>" aria-hidden="true"></i><b><?= Fmt::h($pn) ?></b>
        <span class="mk-fein"><?= $e['bereit'] ? 'postet automatisch' : ($e['verbunden'] ? 'verbunden — wartet auf den Haken „Freigabe erhalten“' : ($e['client_id'] !== '' ? 'Schlüssel gespeichert — noch nicht verbunden' : 'noch nicht beantragt · kommt per Handy')) ?></span></summary>
      <p class="mk-fein" style="margin:8px 0 4px"><?= Fmt::h($pDauer) ?></p>
      <ol style="line-height:1.65;max-width:82ch;margin:0 0 10px"><?php foreach ($pSchritte as $sch): ?><li><?= $sch ?></li><?php endforeach; ?></ol>
      <?php $pfA = MkPlattform::antrag($pk); /* 01.10.2026: fertige Antworten fürs Formular, nur kopieren */ ?>
      <details class="mk-pf__antrag" id="antrag-<?= $pk ?>">
        <summary>Antragstexte zum Kopieren <span class="mk-fein">— englisch wie das Formular, aus den Firmendaten</span></summary>
        <?php foreach ($pfA['voraus'] as $pv): ?><p class="hinweis" style="margin:8px 0;max-width:82ch;line-height:1.55"><?= Fmt::h($pv) ?></p><?php endforeach; ?>
        <?php foreach ($pfA['felder'] as $fi => [$pft, $pfw]): $pfId = 'pfa-' . $pk . '-' . $fi; ?>
          <div class="feld" style="margin:8px 0">
            <label for="<?= $pfId ?>"><?= Fmt::h($pft) ?></label>
            <div class="mk-link"><textarea id="<?= $pfId ?>" readonly rows="<?= mb_strlen($pfw) > 160 ? (int) min(10, ceil(mb_strlen($pfw) / 110) + 1) : 1 ?>" style="flex:1 1 320px;min-width:0"><?= Fmt::h($pfw) ?></textarea><button class="knopf" type="button" data-kopieren="<?= $pfId ?>">Kopieren</button></div>
          </div>
        <?php endforeach; ?>
      </details>
      <p style="margin:0 0 10px"><span class="mk-fein">Rückruf-Adresse (Redirect URI) für die Plattform:</span><br><code style="overflow-wrap:anywhere"><?= Fmt::h(MkPlattform::rueckrufAdresse($pk)) ?></code></p>
      <form method="post" action="<?= Fmt::h(url('kanaele')) ?>" class="mk-formular">
        <?= $csrf() ?><input type="hidden" name="tat" value="plattform_speichern"><input type="hidden" name="plattform" value="<?= $pk ?>">
        <div class="feld"><label for="pf_<?= $pk ?>_id"><?= $pk === 'tiktok' ? 'Client-Key' : 'Client-ID' ?></label><input id="pf_<?= $pk ?>_id" name="client_id" autocomplete="off" value="<?= Fmt::h((string) $e['client_id']) ?>"></div>
        <div class="feld"><label for="pf_<?= $pk ?>_s">Client-Secret <span class="mk-fein"><?= $e['secret'] ? '(hinterlegt — leer lassen zum Behalten)' : '' ?></span></label><input id="pf_<?= $pk ?>_s" name="secret" type="password" autocomplete="off"></div>
        <?php if (MkPlattform::OAUTH[$pk]['id_wort'] !== ''): ?><div class="feld breit"><label for="pf_<?= $pk ?>_k"><?= Fmt::h(MkPlattform::OAUTH[$pk]['id_wort']) ?></label><input id="pf_<?= $pk ?>_k" name="konto" inputmode="numeric" value="<?= Fmt::h((string) $e['konto']) ?>"></div><?php else: ?><input type="hidden" name="konto" value="<?= Fmt::h((string) $e['konto']) ?>"><?php endif; ?>
        <label class="mk-haken breit"><input type="checkbox" name="freigabe" value="1"<?= $e['freigabe'] ? ' checked' : '' ?>> Freigabe der Plattform erhalten — ab jetzt automatisch posten</label>
        <div class="breit" style="display:flex;gap:8px;flex-wrap:wrap"><button class="knopf">Speichern</button></div>
      </form>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
        <?php if ($e['client_id'] !== '' && $e['secret']): ?><form method="post" action="<?= Fmt::h(url('kanaele')) ?>" style="margin:0"><?= $csrf() ?><input type="hidden" name="tat" value="plattform_verbinden"><input type="hidden" name="plattform" value="<?= $pk ?>"><button class="knopf haupt"><?= $e['verbunden'] ? 'Neu verbinden' : 'Verbinden' ?></button></form><?php endif; ?>
        <?php if ($e['verbunden']): ?><form method="post" action="<?= Fmt::h(url('kanaele')) ?>" style="margin:0"><?= $csrf() ?><input type="hidden" name="tat" value="plattform_trennen"><input type="hidden" name="plattform" value="<?= $pk ?>"><button class="knopf">Trennen</button></form><span class="mk-fein" style="align-self:center">verbunden seit <?= Fmt::h((string) $e['verbunden_am']) ?></span><?php endif; ?>
      </div>
    </details>
  <?php endforeach; ?>
</div>
<style>
  .mk-pf{border-top:1px solid var(--linie);padding:12px 0}
  .mk-pf summary{cursor:pointer;display:flex;gap:10px;align-items:center;flex-wrap:wrap;font-size:16px}
  .mk-pf__punkt{width:12px;height:12px;border-radius:50%;background:var(--linie2);flex:none}
  .mk-pf__punkt.halb{background:var(--gelb,#e0b84a)} .mk-pf__punkt.gut{background:var(--gruen,#3fb27f)}
</style>

<?php require_once dirname(__DIR__) . '/src/MkKampagne.php'; require __DIR__ . '/mk_eingehend.php'; ?>

<div class="block" id="meta">
  <h2>Facebook-Seite und Instagram verbinden <span class="mehr">einmal, etwa 15 Minuten — dieselbe Meta-App wie bei WhatsApp</span></h2>
  <details<?= $stand['facebook']['bereit'] ? '' : ' open' ?>><summary style="cursor:pointer">So geht es Schritt für Schritt</summary>
    <ol style="line-height:1.7;max-width:80ch">
      <li>Auf <b>business.facebook.com</b> → Unternehmenseinstellungen: deine <b>Facebook-Seite</b> und dein <b>Instagram-Konto</b> (Business-Konto, mit der Seite verknüpft) zum Unternehmen „Vecom Design“ hinzufügen.</li>
      <li>Auf <b>developers.facebook.com</b> in derselben App wie bei WhatsApp die Produkte <b>„Facebook Login for Business“</b> und <b>„Instagram“</b> hinzufügen.</li>
      <li>Beim <b>Systembenutzer</b> Seite und Instagram-Konto zuweisen und einen dauerhaften Schlüssel erzeugen mit <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>pages_manage_posts</code>, <code>pages_manage_metadata</code>, <code>leads_retrieval</code>, <code>instagram_basic</code>, <code>instagram_content_publish</code>, <code>instagram_manage_comments</code>, <code>instagram_manage_messages</code>, <code>pages_messaging</code>.</li>
      <li><b>Seiten-ID</b>: auf der Seite unter Info → Seitentransparenz. <b>Instagram-Konto-ID</b>: im Business Manager unter Instagram-Konten. Beides unten eintragen, dazu den Schlüssel — dann „Verbindung prüfen“.</li>
    </ol></details>
  <form method="post" action="<?= Fmt::h(url('kanaele')) ?>" class="mk-formular" style="margin-top:10px">
    <?= $csrf() ?><input type="hidden" name="tat" value="kanal_meta_speichern">
    <div class="feld"><label for="kz_seite">Seiten-ID</label><input id="kz_seite" name="seite_id" inputmode="numeric" value="<?= Fmt::h((string) $me['seite_id']) ?>"></div>
    <div class="feld"><label for="kz_ig">Instagram-Konto-ID <span class="mk-fein">(leer = nur Facebook)</span></label><input id="kz_ig" name="ig_id" inputmode="numeric" value="<?= Fmt::h((string) $me['ig_id']) ?>"></div>
    <div class="feld breit"><label for="kz_token">Dauerhafter Schlüssel <span class="mk-fein"><?= $me['token'] ? '(hinterlegt — leer lassen zum Behalten)' : '' ?></span></label><input id="kz_token" name="token" type="password" autocomplete="off"></div>
    <input type="hidden" name="sprache" value="<?= Fmt::h((string) $me['sprache']) ?>">
    <div class="breit"><button class="knopf haupt">Speichern</button></div>
  </form>
</div>

<div class="block" id="telegram">
  <h2>Telegram-Kanal</h2>
  <p style="margin:0 0 10px;max-width:75ch;line-height:1.6">Bot und Kanal richtest du unter Einstellungen › Telegram ein: Bot-Schlüssel eintragen, Bot als Admin in deinen Kanal holen, Kanal-ID speichern. Derselbe Bot schickt dir auch die Handy-Stücke.</p>
  <a class="knopf" href="<?= Fmt::h(url('einstellungen?b=telegram')) ?>">Zu Einstellungen › Telegram</a>
</div>

<style>
  .mk-kanaele{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px}
  .mk-kanal{border:1px solid var(--linie2);border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:6px}
  .mk-kanal p{margin:0;line-height:1.5}
  .mk-kanal__kopf{display:flex;gap:10px;align-items:center;font-size:16px}
  .mk-kanal__kopf i{width:12px;height:12px;border-radius:50%;background:var(--gelb,#e0b84a);flex:none}
  .mk-kanal.gut .mk-kanal__kopf i{background:var(--gruen,#3fb27f)}
  .mk-kanal.gut{border-color:rgba(63,178,127,.45)}
</style>
