<?php /* AutoBuild Phase 10 (07.10.2026): alle Live-Seiten mit Ampel — was rot ist, steht oben. Regeln in Betrieb.php. */
require_once dirname(__DIR__) . '/src/Betrieb.php';
$btFarbe = ['rot' => 'var(--rot, #c0392b)', 'gelb' => 'var(--gelb, #b7791f)', 'gruen' => 'var(--gruen, #2e7d32)'];
$btWort = ['rot' => 'Handeln', 'gelb' => 'Ansehen', 'gruen' => 'Läuft'];
$btZahl = array_count_values(array_map(static fn($r) => $r['ampel'], $liste));
?>
<div class="kopf"><div><h1>Betrieb</h1>
  <p style="color:var(--leise);font-size:14px;margin-top:6px">Jede Seite, die wir gebaut und veröffentlicht haben. Täglich: unverändert gegenüber der Freigabe? Wöchentlich: Links, Kontakt, Rechtliches, Jahreszahl.
    Repariert wird nichts von selbst. Eine offene Zahlung schaltet nie eine Seite ab — sie steht nur hier.</p></div></div>

<div class="block">
  <h2>Live-Seiten <span style="font-size:13px;font-weight:500;color:var(--leise)"><?= count($liste) ?> · <?php foreach (['rot', 'gelb', 'gruen'] as $a): ?><span style="color:<?= $btFarbe[$a] ?>">● <?= (int) ($btZahl[$a] ?? 0) ?></span> <?php endforeach; ?></span></h2>
  <?php if (!$liste): ?><div class="leer">Noch keine Seite im Betrieb. Nach dem ersten Livegang erscheint sie hier.</div><?php else: ?>
  <div class="tabellenrahmen"><table style="font-size:14.5px"><thead><tr><th style="width:110px">Stand</th><th>Projekt</th><th>Domain</th><th>Live</th><th>Was auffällt</th></tr></thead><tbody>
  <?php foreach ($liste as $r): $s = $r['stand']; ?>
    <tr>
      <td><b style="color:<?= $btFarbe[$r['ampel']] ?>">● <?= Fmt::h($btWort[$r['ampel']]) ?></b></td>
      <td><a href="<?= Fmt::h(url('projekte/' . (int) $r['id'])) ?>#betrieb"><?= Fmt::h(Fmt::name((string) $r['name'], (string) ($r['firma'] ?? ''), (string) $r['kunde'])) ?></a>
        <div class="akq-klein" style="color:var(--leise)"><?= Fmt::h(Fmt::name((string) ($r['firma'] ?? ''), (string) $r['kunde'])) ?></div></td>
      <td><?= Fmt::h((string) $s['domain']) ?></td>
      <td>V<?= (int) ($s['live']['nummer'] ?? 0) ?><div class="akq-klein" style="color:var(--leise)">seit <?= Fmt::h(Fmt::datum((string) $s['seit'])) ?></div></td>
      <td><?= $r['gruende'] ? Fmt::h(implode(' · ', $r['gruende'])) : '<span style="color:var(--leise)">nichts</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>

<?php if ($admin): ?>
<div class="block">
  <h2>Einstellungen</h2>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="betrieb_einstellungen">
    <label style="font-size:14px">Stundensatz für Zusatzangebote (€)<br><input name="stundensatz" inputmode="decimal" style="width:140px" value="<?= Betrieb::stundensatz() > 0 ? Fmt::h(number_format(Betrieb::stundensatz() / 100, 2, ',', '')) : '' ?>" placeholder="z. B. 50,00"></label>
    <label style="font-size:14px">Kostenwächter: Bauläufe je Projekt<br><input name="grenze" type="number" min="1" max="500" style="width:140px" value="<?= Betrieb::grenzeVorgabe() ?>"></label>
    <button class="knopf">Speichern</button>
  </form>
  <p class="akq-klein" style="margin:8px 0 0">Kontingent: Minuten pro Monat aus dem Betreuungspaket (Einstellungen → Preise, „inklusive Minuten“). Kleine Inhaltsänderungen zählen mit 0 Minuten. Ohne Vertrag gilt nach dem Livegang <?= Betrieb::NACHBESSERUNG_TAGE ?> Tage Nachbesserung, danach ist jeder Wunsch ein Zusatz mit Angebot.</p>
</div>
<?php endif; ?>
