<?php
/* Eine Anfrage aus dem E-Mail-Einstieg, solange der Interessent seinen Link
   noch nicht geöffnet hat (01.10.2026, Uwe: „kann kein Angebot senden, da
   nichts ankam“). Eingebunden in „Alle Kunden“ und in die Journey.
   Erwartet: $az (Zugang + ['mail' => Mail-Stand]), $azZurueck (Pfad). */
$azMail = $az['mail'] ?? ['status' => 'keine', 'fehler' => '', 'am' => ''];
$azWunsch = Zugang::WUENSCHE[(string) ($az['wunsch'] ?? '')] ?? '';
$azMailText = match ($azMail['status']) {
    'gesendet', 'ok', 'versendet' => 'Zugangslink verschickt am ' . date('d.m. H:i', strtotime((string) $azMail['am'])),
    'keine' => 'keine Mail im Protokoll',
    default => 'Mail NICHT verschickt' . ($azMail['fehler'] !== '' ? ': ' . $azMail['fehler'] : ''),
};
$azGut = in_array($azMail['status'], ['gesendet', 'ok', 'versendet'], true); ?>
<div class="anfrage-karte" style="border:1px solid var(--linie,rgba(255,255,255,.12));border-radius:10px;padding:12px 14px;margin:0 0 10px;line-height:1.6">
  <b><?= Fmt::h(trim((string) ($az['name'] ?? '')) !== '' ? (string) $az['name'] . ' · ' : '') ?><a href="mailto:<?= Fmt::h((string) $az['email']) ?>"><?= Fmt::h((string) $az['email']) ?></a></b>
  <span class="leise" style="display:inline"> · angefragt <?= Fmt::h(date('d.m.Y H:i', strtotime((string) $az['created_at']))) ?>
    <?= $azWunsch !== '' ? ' · Wunsch: ' . Fmt::h($azWunsch) : '' ?><?= !empty($az['partner_code']) ? ' · über Partner ' . Fmt::h((string) $az['partner_code']) : '' ?></span><br>
  <span class="marke2 <?= $azGut ? '' : 'schlecht' ?>"><?= Fmt::h($azMailText) ?></span>
  <span class="leise" style="display:inline"> · Link aus der Mail noch nicht geöffnet — darum noch kein Kunde.</span>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
    <form method="post" action="<?= Fmt::h(url('kunden')) ?>" style="margin:0"><?= Csrf::feld() ?><input type="hidden" name="tat" value="anfrage_annehmen"><input type="hidden" name="zugang_id" value="<?= (int) $az['id'] ?>">
      <button class="knopf haupt klein">Als Kunde anlegen — antworten und Angebot schicken</button></form>
    <form method="post" action="<?= Fmt::h(url('kunden')) ?>" style="margin:0"><?= Csrf::feld() ?><input type="hidden" name="tat" value="anfrage_erneut"><input type="hidden" name="zugang_id" value="<?= (int) $az['id'] ?>"><input type="hidden" name="zurueck" value="<?= Fmt::h($azZurueck) ?>">
      <button class="knopf klein">Zugangslink noch einmal schicken</button></form>
  </div>
</div>
