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
    <b>Was der Bot kann (Stufe 1):</b> Begrüßung, Sprache (IT/DE/EN, bleibt fest),
    die acht Fragen aus dem Baukasten mit dem Richtwert aus derselben Rechnung wie auf
    der Website, Datenschutzhinweis, Anfrage absenden, persönliche Beratung anfragen,
    Weiterleitung zu Website-Check, Domain &amp; Hosting und zum persönlichen Bereich.
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
    Chats bisher: <?= (int) ($tg['chats'] ?? 0) ?> · davon mit gesendeter Anfrage: <?= (int) ($tg['abgeschickt'] ?? 0) ?>
    <?php if (!empty($tg['letzte'])): ?> · zuletzt aktiv: <?= Fmt::h((string) $tg['letzte']) ?><?php endif; ?>
  </p>
</div>
