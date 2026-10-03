<?php
/* Als Partner ansehen (03.10.2026, Marketing Center Phase 1).
   Rendert genau die Partneransicht (partner_werbemittel.php) mit
   $wmNurLesen = true: keine Download-Links, kein Partner-Cookie, kein
   Protokolleintrag beim Partner. Ausgeschaltete Produkte erscheinen
   markiert, damit Uwe sie vor dem Einschalten prüfen kann. */
require_once dirname(__DIR__) . '/src/Texte.php';
require_once dirname(__DIR__) . '/src/PartnerWerbung.php';
require_once dirname(__DIR__) . '/src/PartnerKarten.php';
require_once dirname(__DIR__) . '/src/QrBild.php';
$p = $partner;
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$wmKatalog = $katalog;
$wmNurLesen = true;
$selbst = null;
?>
<div class="kopf"><div><h1>Als Partner ansehen</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px;max-width:760px">
    So sieht der Reiter „Marketing Center“ im Partnerbereich aus — nur lesen. Downloads und
    Bestellknöpfe sind hier aus. Produkte mit „für Partner noch aus“ sieht der Partner erst,
    wenn du sie einschaltest.</p></div>
  <div class="rechts"><a class="knopf stumm" href="<?= Fmt::h(url('werbemittel')) ?>">Zurück zur Verwaltung</a></div>
</div>

<form method="get" action="<?= Fmt::h(url('werbemittel/vorschau')) ?>" class="block" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
  <div class="feld" style="margin:0;min-width:220px"><label>Partner</label>
    <select name="p" onchange="this.form.submit()">
      <?php if (!$liste): ?><option value="0">Beispiel: Maria Rossi (noch kein aktiver Partner)</option><?php endif; ?>
      <?php foreach ($liste as $l): ?><option value="<?= (int) $l['id'] ?>" <?= (int) $l['id'] === (int) $p['id'] ? 'selected' : '' ?>><?= Fmt::h($l['name'] . ' · ' . $l['code']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="feld" style="margin:0"><label>Sprache</label>
    <select name="sprache" onchange="this.form.submit()">
      <?php foreach (['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'] as $sk => $sn): ?><option value="<?= $sk ?>" <?= $sk === $sprache ? 'selected' : '' ?>><?= $sn ?></option><?php endforeach; ?>
    </select></div>
  <noscript><button class="knopf">Ansehen</button></noscript>
</form>

<?php if (!$wmKatalog): ?>
  <div class="block"><div class="leer">Noch kein Produkt mit Einkaufspreis. Solange es keins gibt, hat der
    Partnerbereich keinen Reiter „Marketing Center“.</div></div>
<?php endif; ?>
<div class="wm-vorschau" style="max-width:640px">
  <?php require __DIR__ . '/partner_werbemittel.php'; ?>
</div>
