<?php
/**
 * EINSTELLUNGEN → TELEGRAM (30.09.2026)
 *
 * Der Bot ist ein weiterer Eingang, keine zweite Verwaltung: Was dort
 * abgeschickt wird, steht als ganz normale Anfrage unter „Anfragen“ — mit
 * Kunde, Eingangsmail und Zuruf aufs Handy. Hier wird nur die Leitung
 * eingerichtet.
 *
 * Reihenfolge wie beim Einrichten: Token → Webhook anmelden → prüfen.
 */
$tg = $daten['telegram'] ?? [];
$pr = $daten['telegramPruefung'] ?? null;
$link = !empty($tg['name']) ? 'https://t.me/' . $tg['name'] : '';
?>

<div class="block">
  <h2>Telegram-Bot</h2>

  <?php if (!empty($tg['bereit'])): ?>
    <div class="hinweis gut">Läuft<?= $tg['name'] !== '' ? ' als <b>@' . Fmt::h((string) $tg['name']) . '</b>' : '' ?><?=
      $tg['angemeldet'] !== '' ? ' — angemeldet seit ' . Fmt::h((string) $tg['angemeldet']) : '' ?>.</div>
  <?php elseif (!empty($tg['token'])): ?>
    <div class="hinweis">Token ist hinterlegt (<?= $tg['name'] !== '' ? '@' . Fmt::h((string) $tg['name']) . ', ' : '' ?>endet auf <code><?= Fmt::h((string) $tg['ende']) ?></code>),
      aber der Webhook ist noch nicht angemeldet. Der Bot antwortet erst danach.</div>
  <?php else: ?>
    <div class="hinweis">Noch nicht eingerichtet.</div>
  <?php endif; ?>

  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:12px 0 14px">
    <b>Was der Bot kann (Stufe 1 und 2):</b> Begrüßung, Sprache (IT/DE/EN, bleibt fest),
    die acht Fragen aus dem Baukasten mit dem Richtwert aus derselben Rechnung wie auf
    der Website, Datenschutzhinweis, Anfrage absenden, persönliche Beratung anfragen,
    Weiterleitung zu Website-Check, Domain &amp; Hosting und zum persönlichen Bereich.
    Kunden verbinden Telegram im persönlichen Bereich mit ihrem Konto (Einmal-Link) und sehen dann
    ihren Projektstand, schreiben dir, schicken Dateien ins Projekt und bekommen zu jeder Mail an sie
    einen kurzen Hinweis (nur der Betreff, abschaltbar).
    Preise und Fragen ändern sich hier nie von Hand — sie kommen aus dem Baukasten.
  </p>

  <?php if (is_array($pr)): ?>
    <div class="hinweis <?= !empty($pr['ok']) ? 'gut' : '' ?>" style="margin-bottom:14px">
      <b><?= Fmt::h((string) $pr['text']) ?></b>
      <?php foreach ((array) ($pr['zeilen'] ?? []) as $z): ?><br><?= Fmt::h((string) $z) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php /* autocomplete="new-password": sonst füllt Chrome ein gespeichertes
       Passwort ein (siehe Zuruf-Block unter E-Mail & Zuruf). */ ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_speichern">
    <div class="feld"><label>Bot-Token von @BotFather
      <span style="color:var(--leise);font-weight:400">— wird verschlüsselt abgelegt, danach nur noch die letzten vier Zeichen sichtbar</span></label>
      <input name="token" type="password" autocomplete="new-password" spellcheck="false"
             placeholder="<?= !empty($tg['token']) ? '•••• hinterlegt, endet auf ' . Fmt::h((string) $tg['ende']) : '123456789:AA…' ?>"></div>
    <button class="knopf<?= empty($tg['token']) ? ' haupt' : '' ?>">Token prüfen und speichern</button>
  </form>

  <?php if (!empty($tg['token'])): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:14px">
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_anmelden">
        <button class="knopf<?= empty($tg['bereit']) ? ' haupt' : '' ?>"><?= empty($tg['bereit']) ? 'Webhook anmelden' : 'Webhook neu anmelden' ?></button></form>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_pruefen">
        <button class="knopf">Verbindung prüfen</button></form>
      <?php if (!empty($tg['bereit'])): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
              data-frage="Den Webhook abmelden? Der Bot antwortet danach niemandem mehr, bis er neu angemeldet wird." data-ja="Ja, abmelden">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_abmelden">
          <button class="knopf">Webhook abmelden</button></form>
      <?php endif; ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
            data-frage="Token und Prüfwort löschen und den Bot abmelden? Die Chats und Anfragen bleiben." data-ja="Ja, entfernen">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_weg">
        <button class="knopf">Bot entfernen</button></form>
    </div>
  <?php endif; ?>

  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:14px 0 0">
    Adresse, die Telegram aufruft: <code style="user-select:all"><?= Fmt::h((string) ($tg['adresse'] ?? '')) ?></code><br>
    <?php if ($link !== ''): ?>
      Link zum Bot: <a href="<?= Fmt::h($link) ?>" target="_blank" rel="noopener"><?= Fmt::h($link) ?></a>
      · für einen Partner: <code style="user-select:all"><?= Fmt::h($link) ?>?start=p_CODE</code><br>
    <?php endif; ?>
    Chats bisher: <?= (int) ($tg['chats'] ?? 0) ?> · davon mit gesendeter Anfrage: <?= (int) ($tg['abgeschickt'] ?? 0) ?> · mit Kundenkonto verbunden: <?= (int) ($tg['verbunden'] ?? 0) ?>
    <?php if (!empty($tg['letzte'])): ?> · zuletzt aktiv: <?= Fmt::h((string) $tg['letzte']) ?><?php endif; ?>
  </p>
</div>

<?php if (!empty($tg['bereit'])): $tgK = (array) ($tg['kanal'] ?? []); $tgOffen = Telegram::botOffen(); ?>
<div class="block" id="botzugang">
  <h2>Wer darf in den Bot-Chat?</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:0 0 10px">
    <?php if ($tgOffen): ?>
      <b>Offen für alle:</b> Interessenten nutzen den Bot (Preis, Check, Anfrage), verbundene Kunden ihren Projekt-Kanal mit Hinweisen.
    <?php else: ?>
      <b>Nur für Sie (Admin).</b> Alle anderen bekommen im Bot nur einen kurzen Hinweis mit zwei Knöpfen: zum Kanal und ins Vecom-Fenster (Mini-App über dem Kanal mit Preis, Website-Check, Anfrage, Partner werden, Telegram-Bots). Kunden-Hinweise per Telegram ruhen, im persönlichen Bereich gibt es keinen Telegram-Knopf.
    <?php endif; ?>
  </p>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_bot_offen">
    <?php if (!$tgOffen): ?><input type="hidden" name="offen" value="1"><?php endif; ?>
    <button class="knopf"><?= $tgOffen ? 'Nur noch für mich (Admin)' : 'Wieder für alle öffnen' ?></button>
  </form>
</div>

<div class="block">
  <h2>Telegram-Kanal</h2>
  <?php if (!empty($tgK['id'])): ?>
    <div class="hinweis gut">Verbunden mit <b><?= Fmt::h((string) ($tgK['titel'] ?: $tgK['id'])) ?></b><?=
      !empty($tgK['link']) ? ' — <a href="' . Fmt::h((string) $tgK['link']) . '" target="_blank" rel="noopener">' . Fmt::h((string) $tgK['link']) . '</a>' : '' ?>.
      <?php if (!empty($tg['kanal_zuletzt'])): ?>Letzter Beitrag: <?= Fmt::h((string) $tg['kanal_zuletzt']) ?>.<?php endif; ?></div>
    <?php /* Growth Engine T1 (01.10.2026): Stand aus dem täglichen Lauf, nicht bei jedem Aufruf Telegram fragen. */
      $tgSt = null; try { $tgSt = Db::one("SELECT tag, zahl FROM tg_tage WHERE art = 'kanal_stand' ORDER BY tag DESC LIMIT 1"); } catch (Throwable $e) { } ?>
    <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:10px 0 0">
      <b>Mitglieder:</b> <?= $tgSt ? number_format((int) $tgSt['zahl'], 0, ',', '.') . ' (Stand ' . Fmt::h(date('d.m.Y', strtotime((string) $tgSt['tag']))) . ')' : 'noch nicht gemessen — der tägliche Lauf trägt die Zahl ein' ?>.
      Beitritte je Quelle zählen über eigene Einladungslinks, die eine <a href="<?= Fmt::h(url('kampagnen')) ?>">Kampagne</a> anlegt. Dafür braucht der Bot im Kanal das Recht „Nutzer einladen“.</p>

    <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:12px 0 8px">
      <b>Menü im Kanal:</b> ein angehefteter Beitrag mit zwölf Knöpfen (Preis berechnen, Website prüfen, KI &amp; Automatisierung,
      Telegram-Bots erstellen, Partner werden …).
      <?php require_once dirname(__DIR__, 2) . '/src/TelegramApp.php'; ?><?= TelegramApp::name() !== '' ? 'Ein Tipp öffnet das Vecom-Fenster (Mini-App) direkt über dem Kanal, an dieser Stelle.' : 'Ein Tipp öffnet den Bot direkt an dieser Stelle.' ?>
      <?= Telegram::einstellung('tg_kanal_menue_id') !== '' ? 'Steht im Kanal — erneut klicken aktualisiert denselben Beitrag.' : 'Noch nicht veröffentlicht.' ?>
    </p>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0 0 6px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_kanal_menue">
      <button class="knopf<?= Telegram::einstellung('tg_kanal_menue_id') === '' ? ' haupt' : '' ?>"><?= Telegram::einstellung('tg_kanal_menue_id') === '' ? 'Menü im Kanal veröffentlichen' : 'Menü im Kanal aktualisieren' ?></button>
    </form>

    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:14px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_kanal_posten">
      <div class="feld"><label>Neuer Beitrag
        <span style="color:var(--leise);font-weight:400">— reiner Text, erscheint so, wie er hier steht (höchstens <?= Telegram::KANAL_MAX ?> Zeichen)</span></label>
        <textarea name="text" rows="6" maxlength="<?= Telegram::KANAL_MAX ?>" required><?= Fmt::h((string) ($daten['telegramEntwurf'] ?? '')) ?></textarea></div>
      <div class="feld"><label>Knopf unter dem Beitrag
        <span style="color:var(--leise);font-weight:400">— führt in den Bot; leer lassen für keinen Knopf</span></label>
        <input name="knopf" maxlength="40" value="💬 Preis-Richtwert im Bot"></div>
      <button class="knopf<?= Telegram::einstellung('tg_kanal_menue_id') !== '' ? ' haupt' : '' ?>">Im Kanal veröffentlichen</button>
    </form>

    <?php /* ---- Kanal-Vorschläge 2, 5, 6 (01.10.2026, Uwe: „Alles“) ---- */
      $tgP = $daten['tgPlan'] ?? null; $tgG = (array) ($daten['tgGruppe'] ?? []); $tgU = (array) ($daten['tgUmfragen'] ?? []);
      $tgUE = (array) ($daten['tgUmfrageEntwurf'] ?? []); require_once dirname(__DIR__, 2) . '/src/TelegramUmfrage.php'; ?>
    <details id="plan" style="margin-top:16px"<?= $tgP && !$tgP['e']['an'] ? ' open' : '' ?>>
      <summary>Redaktionsplan — <?= $tgP && $tgP['e']['an'] ? 'an, nächster Lauf ' . Fmt::h(date('d.m.', (int) $tgP['naechster'])) . ' ' . Fmt::h(MkAutopilot::TAGE[(int) date('N', (int) $tgP['naechster'])] ?? '') : 'aus' ?></summary>
      <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:10px 0">
        Einmal je Woche schreibt Claude drei Beiträge für den Kanal — für die italienische Zielgruppe, die am längsten nichts bekommen hat,
        zweisprachig (erst Italienisch, dann Deutsch), ohne Bilder. Das Thema wechselt jede Woche (Tipp, Vorher/Nachher, aus dem Website-Check, Frage aus der Praxis).
        Die Entwürfe kommen zur Freigabe per Telegram und unter <a href="<?= Fmt::h(url('freigabe?land=IT')) ?>">Freigabe</a>; was du freigibst, geht zur nächsten freien Zeit raus (einer je Tag, 18:30).
        Ohne deinen Klick geht nichts in den Kanal.
        <?php if ($tgP && $tgP['e']['an']): ?><br>Nächstes Thema: <i><?= Fmt::h(explode(' · ', (string) $tgP['thema'])[0]) ?></i><?php endif; ?>
      </p>
      <?php if ($tgP): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_plan">
        <label style="display:flex;gap:8px;align-items:center;margin:0 4px 11px 0;cursor:pointer"><input type="checkbox" name="an" value="1"<?= $tgP['e']['an'] ? ' checked' : '' ?>> Plan an</label>
        <div class="feld" style="margin:0"><label>Tag</label><select name="tag"><?php foreach (MkAutopilot::TAGE as $tn => $tw): ?><option value="<?= $tn ?>"<?= $tn === $tgP['e']['tag'] ? ' selected' : '' ?>><?= Fmt::h($tw) ?></option><?php endforeach; ?></select></div>
        <div class="feld" style="margin:0"><label>ab</label><select name="stunde"><?php for ($th = 5; $th <= 22; $th++): ?><option value="<?= $th ?>"<?= $th === $tgP['e']['stunde'] ? ' selected' : '' ?>><?= $th ?>:00</option><?php endfor; ?></select></div>
        <button class="knopf">Speichern</button>
      </form>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_plan_jetzt">
        <button class="knopf">Diese Woche jetzt schreiben lassen</button>
      </form>
      <?php endif; ?>
    </details>

    <details id="umfrage" style="margin-top:12px"<?= $tgUE ? ' open' : '' ?>>
      <summary>Umfrage im Kanal<?= $tgU ? ' — zuletzt: ' . Fmt::h(mb_strimwidth((string) $tgU[0]['frage'], 0, 48, '…')) : '' ?></summary>
      <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:10px 0">
        Ein Tipp statt eines Formulars: Leser stimmen anonym ab — wir sehen nur die Zahl der Stimmen je Antwort.
        Zweisprachig wie der Kanal (Italienisch · Deutsch). Vorlage wählen oder selbst schreiben.
      </p>
      <form method="post" action="<?= Fmt::h(url('')) ?>" id="tg-umfrage">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_umfrage">
        <div class="feld"><label>Vorlage</label>
          <select id="tg-umfrage-vorlage"><option value="">— eigene Frage —</option><?php foreach (TelegramUmfrage::VORSCHLAEGE as $vi => $v): ?><option value="<?= $vi ?>"><?= Fmt::h(mb_strimwidth($v[0], 0, 70, '…')) ?></option><?php endforeach; ?></select></div>
        <div class="feld"><label>Frage <span style="color:var(--leise);font-weight:400">— höchstens <?= TelegramUmfrage::FRAGE_MAX ?> Zeichen</span></label>
          <input name="frage" maxlength="<?= TelegramUmfrage::FRAGE_MAX ?>" required value="<?= Fmt::h((string) ($tgUE['frage'] ?? '')) ?>"></div>
        <?php for ($ai = 0; $ai < 6; $ai++): ?>
          <div class="feld" style="margin-bottom:6px"><label><?= $ai === 0 ? 'Antworten <span style="color:var(--leise);font-weight:400">— 2 bis ' . TelegramUmfrage::ANTWORTEN_MAX . ', je höchstens ' . TelegramUmfrage::ANTWORT_MAX . ' Zeichen; leere fallen weg</span>' : '' ?></label>
            <input name="antwort[]" maxlength="<?= TelegramUmfrage::ANTWORT_MAX ?>" aria-label="Antwort <?= $ai + 1 ?>"<?= $ai < 2 ? ' required' : '' ?> value="<?= Fmt::h((string) ($tgUE['antwort'][$ai] ?? '')) ?>"></div>
        <?php endfor; ?>
        <button class="knopf">Umfrage im Kanal senden</button>
      </form>
      <script>
      (function () {
        var V = <?= json_encode(TelegramUmfrage::VORSCHLAEGE, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        var f = document.getElementById('tg-umfrage'), w = document.getElementById('tg-umfrage-vorlage');
        if (!f || !w) { return; }
        w.addEventListener('change', function () {
          var v = V[w.value]; if (!v) { return; }
          f.elements['frage'].value = v[0];
          var a = f.querySelectorAll('input[name="antwort[]"]');
          for (var i = 0; i < a.length; i++) { a[i].value = v[1][i] || ''; }
        });
      })();
      </script>
      <?php if ($tgU): ?>
        <div style="margin-top:14px;display:grid;gap:12px">
          <?php foreach ($tgU as $u): $um = max(1, max(array_column($u['antworten'], 'stimmen') ?: [0])); ?>
            <div style="border:1px solid var(--linie);border-radius:12px;padding:12px 14px">
              <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><b style="font-size:14px"><?= Fmt::h((string) $u['frage']) ?></b>
                <span style="color:var(--leise);font-size:12.5px"><?= Fmt::h(date('d.m.Y', strtotime((string) $u['angelegt_am']))) ?> · <?= (int) $u['gesamt'] ?> Stimmen · <?= $u['status'] === 'beendet' ? 'beendet' : 'läuft' ?></span></div>
              <?php foreach ($u['antworten'] as $an): ?>
                <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:4px 10px;margin-top:8px;font-size:13px">
                  <span><?= Fmt::h($an['text']) ?></span><span><?= (int) $an['stimmen'] ?><?= (int) $u['gesamt'] > 0 ? ' · ' . round(100 * (int) $an['stimmen'] / (int) $u['gesamt']) . ' %' : '' ?></span>
                  <span style="grid-column:1/-1;height:6px;border-radius:3px;background:var(--linie)"><span style="display:block;height:6px;border-radius:3px;background:var(--gelb);width:<?= (int) round(100 * (int) $an['stimmen'] / $um) ?>%"></span></span>
                </div>
              <?php endforeach; ?>
              <?php if ($u['status'] !== 'beendet'): ?>
                <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
                  <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_umfrage_ende"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="knopf">Umfrage beenden</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </details>

    <details id="kommentare" style="margin-top:12px"<?= empty($tgG['schutz']) ? ' open' : '' ?>>
      <summary>Kommentare unter den Beiträgen — <?= !empty($tgG['schutz']) ? 'geschützt' . ($tgG['titel'] !== '' ? ' (' . Fmt::h((string) $tgG['titel']) . ')' : '') : ($tgG['id'] !== '' ? 'Gruppe gefunden, Bot darf noch nicht löschen' : 'noch nicht eingerichtet') ?></summary>
      <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:10px 0">
        Kommentare laufen in Telegram über eine Diskussionsgruppe, die am Kanal hängt. Der Bot ist dort Admin mit einem einzigen Recht,
        <b>Nachrichten löschen</b>, und entfernt Kommentare mit Link, @Erwähnung, E-Mail-Adresse, Telefonnummer, Weiterleitung, Knöpfen
        oder „als fremder Kanal“ — außer sie kommen vom Team oder zeigen auf Vecom selbst. Er sperrt niemanden und speichert keinen Kommentar,
        nur die Zahl je Tag (Wochenbericht). Fragen von außen meldet er dir mit Link. In fremde Gruppen geholt, verlässt er sie sofort.
      </p>
      <ol style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:0 0 10px;padding-left:20px">
        <li>In Telegram: Kanal › Bearbeiten › <b>Diskussion</b> › Gruppe hinzufügen (neue Gruppe anlegen).</li>
        <li>In der Gruppe: Verwalten › Administratoren › <b>@<?= Fmt::h((string) ($tg['name'] ?? '')) ?></b> hinzufügen, nur „Nachrichten löschen“.</li>
        <li>Hier prüfen.</li>
      </ol>
      <form method="post" action="<?= Fmt::h(url('')) ?>">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_gruppe_pruefen">
        <button class="knopf<?= empty($tgG['schutz']) ? ' haupt' : '' ?>">Kommentare prüfen</button>
      </form>
    </details>
  <?php else: ?>
    <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:0 0 14px">
      Im Kanal veröffentlichst du Neuigkeiten; der Bot postet sie für dich. Dafür muss er dort Admin sein —
      mit „Beiträge veröffentlichen“, mehr braucht er nicht.
    </p>
  <?php endif; ?>

  <?php require_once dirname(__DIR__, 2) . '/src/TelegramApp.php'; $tgApp = TelegramApp::name(); ?>
  <details style="margin-top:14px"<?= !empty($tgK['id']) && $tgApp === '' ? ' open' : '' ?>>
    <summary>Mini-App „Preis-Rechner“<?= $tgApp !== '' ? ' — angemeldet als ' . Fmt::h($tgApp) : ' — noch nicht angemeldet' ?></summary>
    <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:10px 0">
      Die Knöpfe „Preis berechnen“, „Neue Website“ und „Website verbessern“ im Menü-Beitrag öffnen dann den Konfigurator
      als Fenster über dem Kanal, statt in den Bot zu wechseln. Bei @BotFather mit <code>/newapp</code> anmelden,
      als Adresse <code style="user-select:all"><?= Fmt::h(TelegramApp::adresse()) ?></code>, danach hier den Kurznamen eintragen
      und oben „Menü im Kanal aktualisieren“.
    </p>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_app_speichern">
      <div class="feld"><label>Kurzname der Mini-App <span style="color:var(--leise);font-weight:400">— wie bei @BotFather, z. B. rechner; leer = aus</span></label>
        <input name="app" spellcheck="false" maxlength="30" value="<?= Fmt::h($tgApp) ?>"></div>
      <button class="knopf">Speichern</button>
    </form>
  </details>

  <details style="margin-top:14px"<?= empty($tgK['id']) ? ' open' : '' ?>>
    <summary><?= empty($tgK['id']) ? 'Kanal hinterlegen' : 'Kanal ändern' ?></summary>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_kanal_speichern">
      <div class="feld"><label>Kanal <span style="color:var(--leise);font-weight:400">— Kennung (-100…) oder @Name; leer = lösen</span></label>
        <input name="kanal" spellcheck="false" value="<?= Fmt::h((string) ($tgK['id'] ?? '')) ?>"></div>
      <div class="feld"><label>Link für Besucher <span style="color:var(--leise);font-weight:400">— bei einem privaten Kanal der Einladungslink (https://t.me/+…)</span></label>
        <input name="kanal_link" spellcheck="false" value="<?= Fmt::h((string) ($tgK['link'] ?? '')) ?>"></div>
      <button class="knopf">Prüfen und speichern</button>
    </form>
  </details>
</div>
<?php endif; ?>

<?php if (!empty($tg['bereit'])): $tgAdm = $daten['telegramAdmin'] ?? null; ?>
<div class="block">
  <h2>Dein Telegram als Fenster zur Verwaltung</h2>
  <?php if ($tgAdm): ?>
    <div class="hinweis gut">Verbunden. Die Zurufe (neue Anfrage, Störung …) kommen auch in Telegram an, und im Bot-Menü steht „🛠 Verwaltung“ mit der Lage.
      Zugang nur, solange dieses Telegram-Konto Besitzer oder Admin des Kanals ist; normale Nutzer sehen den Punkt nie.</div>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"
          data-frage="Dein Telegram von der Verwaltung trennen?" data-ja="Ja, trennen">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_admin_trennen">
      <button class="knopf">Trennen</button></form>
  <?php else: ?>
    <p style="color:var(--dim);font-size:13.5px;line-height:1.65;margin:0 0 14px">
      Ein Klick öffnet Telegram mit einem Einmal-Link (30 Minuten gültig). Danach bekommst du dort dieselben
      Zurufe wie per WhatsApp und siehst die Lage — nur Zahlen und Knöpfe in die Verwaltung, keine Kundennamen.
      Gearbeitet wird weiter hier.
      <br><b>Zwei Schlösser:</b> Öffnen Sie den Link mit dem Telegram-Konto, das Besitzer oder Admin des Kanals ist —
      mit jedem anderen Konto wird nicht verbunden, und wer die Kanal-Rolle später verliert, verliert auch den Zugang im Bot.
    </p>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="telegram_admin_verbinden">
      <button class="knopf haupt">Mein Telegram verbinden</button></form>
  <?php endif; ?>
</div>
<?php endif; ?>
