<?php
/* Akquise-CRM Modul G (06.10.2026): Vom Interesse zum Auftrag — Preisrechner → Bedarf → Angebot → Auftrag → Projekt.
   Eingebunden aus akquise_firma.php (Überblick, rechte Spalte, ganz oben). Daten: $f, $fid.
   Uwe: Kunde „automatisch bei Gewonnen“ (und mit dem ersten Angebot), Provision „immer automatisch“ bei Reservierung,
   Angebot „ein Knopf, bestehender Editor“. Preise und Provision sieht nur der Admin (Rechte::geld). */
require_once dirname(__DIR__) . '/src/AkquiseKunde.php';
$auH = static fn(?string $s): string => Fmt::h((string) $s);
$auS = AkquiseKunde::stand($f);
/* Ein Ding je Bildschirm: Bei einem frisch gefundenen Betrieb wären fünf leere Schritte nur Lärm.
   Sichtbar ab Interesse, mit Kunde oder sobald er seinen Bereich geöffnet hat. */
require_once dirname(__DIR__) . '/src/AkquiseCrm.php';
if (!$auS['kunde'] && !$auS['zugang'] && !in_array(AkquiseCrm::spalte($fid), ['interesse', 'bedarf', 'angebot_erstellt', 'angebot_gesendet', 'nachfassen', 'gewonnen'], true)) { return; }
$auGeld = Rechte::geld();
$auK = $auS['kunde'];
$auA = $auS['angebote'];
$auNeu = $auA[0] ?? null;
$auStatus = ['entwurf' => 'Entwurf, noch nicht gesendet', 'gesendet' => 'beim Kunden', 'angenommen' => 'angenommen', 'abgelehnt' => 'abgelehnt',
             'abgelaufen' => 'abgelaufen', 'zurueckgezogen' => 'zurückgezogen'];
$auGesendet = (bool) array_filter($auA, static fn($a) => in_array((string) $a['status'], ['gesendet', 'angenommen'], true));
$auSchritte = [
    ["Preis\u{00AD}rechner", $auS['zugang'] || $auS['bedarf'] ? 'ja' : 'nein', $auS['bedarf'] ? 'begonnen' : ($auS['zugang'] ? 'Bereich geöffnet' : 'noch nicht')],
    ['Bedarf', $auS['bedarf_fertig'] ? 'ja' : 'nein', $auS['bedarf_fertig'] ? 'ausgefüllt' : 'offen'],
    ['Angebot', $auGesendet ? 'ja' : ($auNeu ? 'halb' : 'nein'), $auNeu ? ($auStatus[(string) $auNeu['status']] ?? (string) $auNeu['status']) : 'keins'],
    ['Auftrag', $auS['auftrag'] ? 'ja' : 'nein', $auS['auftrag'] ? (string) $auS['auftrag']['order_no'] : 'offen'],
    ['Projekt', $auS['projekt'] ? 'ja' : 'nein', $auS['projekt'] ? 'läuft' : 'offen'],
];
$auPartner = ($auK && $auGeld) ? AkquiseKunde::provisionVoraus((int) $auK['id'], $auNeu ? AkquiseKunde::betrag($auNeu) : 0) : null;
$auAusBedarf = $auS['bedarf_fertig'] && !array_filter($auA, static fn($a) => (int) ($a['bedarf_id'] ?? 0) === (int) $auS['bedarf_fertig']['id']);
?>
<style>
  .au-weg{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:4px;list-style:none;margin:0 0 12px;padding:0}
  .au-weg li{border:1px solid var(--linie);border-radius:10px;padding:6px 7px;font-size:12px;line-height:1.35;background:var(--flaeche2);hyphens:manual}
  .au-weg li b{display:block;font-size:12.5px;color:var(--text)}
  @media (max-width:560px){.au-weg{grid-template-columns:1fr}.au-weg li{display:flex;justify-content:space-between;gap:8px}.au-weg li span{text-align:right}}
  .au-weg li span{color:var(--leise)}
  .au-weg li.ja{border-color:rgba(120,220,160,.45)} .au-weg li.ja b::before{content:"✓ ";color:#7fdca0}
  .au-weg li.halb{border-color:rgba(255,200,90,.45)} .au-weg li.halb b::before{content:"◐ ";color:var(--gelb)}
  .au-zeile{font-size:13.5px;line-height:1.6;margin:0 0 6px;color:var(--dim)}
  .au-zeile a{text-decoration:underline}
  .au-liste{margin:6px 0 10px;padding:0;list-style:none;font-size:13.5px}
  .au-liste li{display:flex;gap:8px;justify-content:space-between;border-bottom:1px solid var(--linie);padding:6px 0}
  .au-liste .z{font-variant-numeric:tabular-nums;white-space:nowrap}
  .au-form{display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end;margin-top:8px}
  .au-form .feld{margin:0} .au-form input{width:130px;padding-top:6px;padding-bottom:6px}
  .au-prov{font-size:13px;border-left:3px solid var(--linie2);padding:4px 10px;margin:8px 0;color:var(--dim)}
</style>
<div class="block" id="auftrag">
  <h2>Vom Interesse zum Auftrag</h2>
  <ol class="au-weg" aria-label="Weg zum Auftrag" lang="de">
    <?php foreach ($auSchritte as [$w, $st, $txt]): ?><li class="<?= $st ?>"><b><?= $auH($w) ?></b><span><?= $auH($txt) ?></span></li><?php endforeach; ?>
  </ol>

  <?php if (!$auK): ?>
    <p class="au-zeile">Noch kein Kunde. Er entsteht von selbst, sobald der Betrieb auf „Gewonnen“ steht<?= $auGeld ? ' — oder hier mit dem ersten Angebot' : '' ?>.</p>
    <?php if (!$auS['email']): ?>
      <p class="au-zeile">⚠ Dafür fehlt eine E-Mail-Adresse. <a href="<?= $auH(url('akquise/' . $fid . '?ansicht=profil')) ?>">Im Profil eintragen</a>.</p>
    <?php elseif ($auGeld): ?>
      <form method="post" action="<?= $auH(url('')) ?>" class="au-form"><?= Csrf::feld() ?><input type="hidden" name="tat" value="angebot_aus_akquise"><input type="hidden" name="firma" value="<?= (int) $fid ?>">
        <div class="feld"><label for="au-fest">Festpreis (€)</label><input id="au-fest" name="festpreis" inputmode="decimal" placeholder="z. B. 1.490" required></div>
        <button class="knopf klein">Angebotsentwurf anlegen</button>
      </form>
      <p class="akq-klein" style="margin-top:6px">Legt den Kunden (<?= $auH($auS['email']) ?>) und einen Entwurf an. Gesendet wird erst im Angebot, von dir.</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="au-zeile">Kunde: <a href="<?= $auH(url('kunden/' . (int) $auK['id'])) ?>"><?= $auH(trim((string) $auK['company']) !== '' ? (string) $auK['company'] : (string) $auK['name']) ?></a>
      <span class="akq-klein">· <?= $auH((string) $auK['email']) ?></span></p>

    <?php if ($auA): ?>
      <ul class="au-liste">
        <?php foreach ($auA as $a): ?>
          <li><span><?php if ($auGeld): ?><a href="<?= $auH(url('angebote/' . (int) $a['id'])) ?>"><?= $auH((string) $a['nummer']) ?></a><?php else: ?><?= $auH((string) $a['nummer']) ?><?php endif; ?>
            · <?= $auH($auStatus[(string) $a['status']] ?? (string) $a['status']) ?></span>
            <?php if ($auGeld): ?><span class="z"><?= AkquiseKunde::betrag($a) > 0 ? Fmt::geld(AkquiseKunde::betrag($a), (string) ($a['currency'] ?: 'EUR')) : '—' ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($auPartner): ?>
      <div class="au-prov">Partner <a href="<?= $auH(url('partner/' . $auPartner['pid'])) ?>"><?= $auH($auPartner['partner']) ?></a> · Satz <?= $auH($auPartner['satz']) ?>
        <?php if ($auPartner['hinweis']): ?> — <?= $auH($auPartner['hinweis']) ?>
        <?php elseif ($auNeu && AkquiseKunde::betrag($auNeu) > 0): ?> · bei <?= $auH((string) $auNeu['nummer']) ?> voraussichtlich <b><?= Fmt::geld($auPartner['cents']) ?></b> Provision (gebucht wird erst bei Zahlung)<?php endif; ?></div>
    <?php endif; ?>

    <?php if ($auS['auftrag']): ?><p class="au-zeile">Auftrag <?= $auH((string) $auS['auftrag']['order_no']) ?><?= $auGeld ? ' · ' . Fmt::geld((int) $auS['auftrag']['price_cents']) : '' ?> · <?= $auH((string) $auS['auftrag']['status']) ?></p><?php endif; ?>
    <?php if ($auS['projekt']): ?><p class="au-zeile">Projekt: <a href="<?= $auH(url('projekte/' . (int) $auS['projekt']['id'])) ?>"><?= $auH((string) $auS['projekt']['name']) ?></a></p><?php endif; ?>

    <?php if ($auGeld && !$auS['auftrag']): ?>
      <?php if ($auAusBedarf): ?>
        <form method="post" action="<?= $auH(url('')) ?>" class="au-form"><?= Csrf::feld() ?><input type="hidden" name="tat" value="angebot_aus_akquise"><input type="hidden" name="firma" value="<?= (int) $fid ?>">
          <button class="knopf klein haupt">Angebot aus dem Preisrechner anlegen</button></form>
      <?php endif; ?>
      <details style="margin-top:8px"<?= !$auA && !$auAusBedarf ? ' open' : '' ?>><summary class="akq-klein" style="cursor:pointer">Neues Angebot mit Festpreis</summary>
        <form method="post" action="<?= $auH(url('')) ?>" class="au-form"><?= Csrf::feld() ?><input type="hidden" name="tat" value="angebot_aus_akquise"><input type="hidden" name="firma" value="<?= (int) $fid ?>">
          <div class="feld"><label for="au-fest2">Festpreis (€)</label><input id="au-fest2" name="festpreis" inputmode="decimal" placeholder="z. B. 1.490" required></div>
          <button class="knopf klein">Angebotsentwurf anlegen</button>
        </form>
      </details>
    <?php endif; ?>
  <?php endif; ?>
</div>
