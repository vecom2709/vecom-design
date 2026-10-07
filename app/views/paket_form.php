<?php
$feat  = $p && $p['features'] ? implode("\n", (array) json_decode((string) $p['features'], true)) : '';
$texte = $p && $p['texte'] ? (array) json_decode((string) $p['texte'], true) : [];
$sprachen = ['it' => 'Italienisch (Standard der Website)', 'de' => 'Deutsch', 'en' => 'Englisch'];
$tx = static function (array $texte, string $l, string $feld): string {
    $w = $texte[$l][$feld] ?? '';
    if ($feld === 'features') { return is_array($w) ? implode("\n", $w) : (string) $w; }
    return (string) $w;
};
?>
<div class="kopf"><h1><?= $p ? 'Paket bearbeiten' : 'Neues Paket' ?></h1>
  <div class="rechts"><a class="knopf stumm" href="https://vecom-design.it/#plans" target="_blank" rel="noopener">Auf der Website ansehen</a></div>
</div>

<div class="block" style="max-width:820px"><form method="post" action="<?= Fmt::h(url('')) ?>">
<?= Csrf::feld() ?><input type="hidden" name="tat" value="paket_speichern">
<input type="hidden" name="zurueck" value="pakete"><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">

<div class="reihe"><div class="feld"><label for="f_name">Name *</label><input id="f_name" name="name" required value="<?= Fmt::h($p['name'] ?? '') ?>"></div>
<div class="feld"><label for="f_slug">Kürzel (URL)</label><input id="f_slug" name="slug" value="<?= Fmt::h($p['slug'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label for="f_preis">Preis einmalig (€)</label><input id="f_preis" name="preis" value="<?= $p ? number_format($p['price_cents']/100, 2, ',', '') : '' ?>"></div>
<div class="feld"><label for="f_monat">Monatlich (€)</label><input id="f_monat" name="monat" value="<?= $p ? number_format($p['monthly_cents']/100, 2, ',', '') : '0' ?>"></div></div>
<?php /* Vertragsregeln (Migration 052): stehen hier, nicht im Code. Werden beim
         Vertragsschluss in den Vertrag kopiert -- eine Aenderung hier gilt nur
         fuer neue Vertraege. Leer = Standard (12 Monate, bis Monatsende). */ ?>
<div class="reihe"><div class="feld"><label for="f_mindest_monate">Mindestlaufzeit (Monate)</label><input id="f_mindest_monate" name="mindest_monate" type="number" min="0" max="60" value="<?= isset($p['mindest_monate']) ? (int) $p['mindest_monate'] : '' ?>" placeholder="12"></div>
<div class="feld"><label for="f_kuendigung_tage">Kündigungsfrist vor Monatsende (Tage)</label><input id="f_kuendigung_tage" name="kuendigung_tage" type="number" min="0" max="365" value="<?= isset($p['kuendigung_tage']) ? (int) $p['kuendigung_tage'] : '' ?>" placeholder="0"></div>
<div class="feld"><label for="f_inklusiv_minuten">Änderungszeit inklusive (Minuten/Monat)</label><input id="f_inklusiv_minuten" name="inklusiv_minuten" type="number" min="0" max="6000" value="<?= isset($p['inklusiv_minuten']) ? (int) $p['inklusiv_minuten'] : '' ?>" placeholder="—"></div></div>
<div class="feld"><label for="f_sub">Untertitel auf der Karte</label><input id="f_sub" name="sub" value="<?= Fmt::h($p['sub'] ?? '') ?>" placeholder="Gefunden werden und Anfragen bekommen"></div>
<div class="feld"><label for="f_features">Leistungen (eine je Zeile · Zeilen mit Doppelpunkt am Ende werden zur Zwischenüberschrift)</label>
  <textarea id="f_features" name="features" rows="7"><?= Fmt::h($feat) ?></textarea></div>
<div class="feld"><label for="f_ideal">„Ideal für …“</label><textarea id="f_ideal" name="ideal" rows="2"><?= Fmt::h($p['ideal'] ?? '') ?></textarea></div>
<div class="reihe"><div class="feld"><label for="f_detail_url">Link „Alle Details“</label><input id="f_detail_url" name="detail_url" value="<?= Fmt::h($p['detail_url'] ?? '') ?>" placeholder="pakete.html#starter"></div>
<div class="feld"><label for="f_sort">Reihenfolge auf der Website</label><input id="f_sort" name="sort" type="number" value="<?= (int) ($p['sort'] ?? 0) ?>"></div></div>

<div class="feld"><label>Sichtbarkeit</label>
  <label style="color:var(--text);display:block;margin-bottom:4px"><input type="checkbox" name="active" style="width:auto" <?= (!$p || $p['active']) ? 'checked' : '' ?>> aktiv (kann bestellt werden)</label>
  <label style="color:var(--text);display:block;margin-bottom:4px"><input type="checkbox" name="oeffentlich" style="width:auto" <?= (!$p || !empty($p['oeffentlich'])) ? 'checked' : '' ?>> auf vecom-design.it zeigen</label>
  <label style="color:var(--text);display:block;margin-bottom:4px"><input type="checkbox" name="popular" style="width:auto" <?= ($p && $p['popular']) ? 'checked' : '' ?>> als „Meistgefragt“ hervorheben</label>
  <label style="color:var(--text);display:block"><input type="checkbox" name="direktkauf" style="width:auto" <?= ($p && !empty($p['direktkauf'])) ? 'checked' : '' ?>> direkt auf der Website buchbar (Knopf „Jetzt buchen“)</label>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">Der Knopf erscheint erst, wenn Stripe eingerichtet ist und der Livemodus läuft.
  Zum Ausprobieren lässt er sich unter <a href="<?= Fmt::h(url('integrationen')) ?>">Integrationen</a> vorübergehend auch im Testmodus einblenden.</p>
</div>

<div style="margin:22px 0 10px;padding-top:16px;border-top:1px solid var(--linie)">
  <h2 style="font-size:14px;font-weight:600;margin-bottom:4px">Texte für die Website</h2>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-bottom:14px">Die Seite läuft auf Italienisch, Deutsch und Englisch.
  Was hier leer bleibt, wird durch die Angaben oben ersetzt — dann steht auf allen drei Sprachversionen derselbe Text.</p>
</div>

<?php foreach ($sprachen as $l => $titel): ?>
  <details <?= $l === 'it' ? 'open' : '' ?> style="margin-bottom:10px;border:1px solid var(--linie);border-radius:12px;padding:12px 14px">
    <summary style="cursor:pointer;font-size:13.5px;color:var(--dim)"><?= Fmt::h($titel) ?></summary>
    <div style="margin-top:12px">
      <div class="reihe">
        <div class="feld"><label for="f_t______l____name">Name</label><input id="f_t______l____name" name="t_<?= $l ?>_name" value="<?= Fmt::h($tx($texte, $l, 'name')) ?>"></div>
        <div class="feld"><label for="f_t______l____sub">Untertitel</label><input id="f_t______l____sub" name="t_<?= $l ?>_sub" value="<?= Fmt::h($tx($texte, $l, 'sub')) ?>"></div>
      </div>
      <div class="feld"><label for="f_t______l____features">Leistungen (eine je Zeile)</label>
        <textarea id="f_t______l____features" name="t_<?= $l ?>_features" rows="7"><?= Fmt::h($tx($texte, $l, 'features')) ?></textarea></div>
      <div class="feld"><label for="f_t______l____ideal">„Ideal für …“</label>
        <textarea id="f_t______l____ideal" name="t_<?= $l ?>_ideal" rows="2"><?= Fmt::h($tx($texte, $l, 'ideal')) ?></textarea></div>
    </div>
  </details>
<?php endforeach; ?>

<div style="margin:18px 0 10px;padding-top:16px;border-top:1px solid var(--linie)">
  <h2 style="font-size:14px;font-weight:600;margin-bottom:10px">Interne Angaben</h2>
</div>
<div class="feld"><label for="f_description">Beschreibung (nur intern)</label><textarea id="f_description" name="description" rows="2"><?= Fmt::h($p['description'] ?? '') ?></textarea></div>
<div class="reihe"><div class="feld"><label for="f_pages_count">Anzahl Seiten</label><input id="f_pages_count" name="pages_count" value="<?= Fmt::h($p['pages_count'] ?? '') ?>"></div>
<div class="feld"><label for="f_delivery_days">Bearbeitungszeit</label><input id="f_delivery_days" name="delivery_days" value="<?= Fmt::h($p['delivery_days'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label for="f_seo">SEO</label><input id="f_seo" name="seo" value="<?= Fmt::h($p['seo'] ?? '') ?>"></div>
<div class="feld"><label for="f_hosting">Hosting</label><input id="f_hosting" name="hosting" value="<?= Fmt::h($p['hosting'] ?? '') ?>"></div></div>
<div class="feld"><label for="f_extras">Zusätzliche Funktionen</label><textarea id="f_extras" name="extras" rows="2"><?= Fmt::h($p['extras'] ?? '') ?></textarea></div>

<button class="knopf haupt">Speichern</button> <a class="knopf stumm" href="<?= Fmt::h(url('pakete')) ?>">Abbrechen</a>
<p style="color:var(--leise);font-size:var(--fs-klein);margin-top:12px">Nach dem Speichern steht das Paket sofort auf vecom-design.it —
die Seite holt die Karten bei jedem Aufruf aus der Verwaltung.</p>
</form></div>
