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
    <?php foreach (MkKanaele::HANDY as $kk => $kn): $g = $geplant[$kk] ?? null; ?>
      <div class="mk-kanal <?= $handy['bereit'] ? 'gut' : 'offen' ?>">
        <div class="mk-kanal__kopf"><i aria-hidden="true"></i><b><?= Fmt::h($kn) ?></b></div>
        <p class="mk-fein"><?= $g ? $g['n'] . ' eingeplant · nächster ' . Fmt::h($fmtZeit($g['naechst'])) : 'nichts eingeplant' ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="mk-fein" style="margin:10px 0 0"><?= Fmt::h($handy['text']) ?></p>
  <p class="mk-fein" style="margin:4px 0 0">Voll automatisch geht es, sobald die Plattformen die App geprüft haben — die Anträge stehen unten.</p>
</div>

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
