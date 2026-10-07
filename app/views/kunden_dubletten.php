<?php
/* Mögliche Dubletten (Phase 9b, Uwe: „Vorschlag + Zusammenführen per Klick“). Daten: $paare, $admin.
   Nie automatisch: „Ist dieselbe“ fragt nach, welcher Kunde bleibt, und führt erst dann zusammen. */
$zeile = static fn(array $k): string => '<a href="' . Fmt::h(url('kunden/' . (int) $k['id'])) . '"><b>' . Fmt::h((string) $k['name']) . '</b></a>'
    . ' <span style="color:var(--leise);font-size:var(--fs-klein)">' . Fmt::h((string) ($k['kundennr'] ?? '')) . '</span>'
    . '<div style="font-size:var(--fs-klein);color:var(--dim);line-height:1.5">' . Fmt::h(implode(' · ', array_filter([(string) $k['company'], (string) $k['email'], (string) $k['phone'], (string) $k['city']]))) . '</div>';
?>
<div class="kopf"><div><h1>Mögliche Dubletten</h1>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin-top:6px">Kunden, die gleich aussehen — gleiche Telefonnummer, P. IVA, E-Mail, Firma oder Firmen-Domain. Zusammengeführt wird nur auf deinen Klick; Rechnungen wandern dabei nie.</p></div>
  <div class="rechts"><a class="knopf" href="<?= Fmt::h(url('kunden')) ?>">Alle Kunden</a></div></div>

<?php if (!$paare): ?>
  <div class="block"><div class="leer">Keine möglichen Dubletten.</div></div>
<?php endif; ?>
<?php foreach ($paare as $i => $pr): $a = $pr['a']; $b = $pr['b']; ?>
  <div class="block" id="paar-<?= (int) $a['id'] ?>-<?= (int) $b['id'] ?>">
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin:0 0 10px"><?php foreach ($pr['gruende'] as $g): ?><span class="marke2 warnung">gleiche <?= Fmt::h($g) ?></span><?php endforeach; ?></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
      <div><?= $zeile($a) ?></div><div><?= $zeile($b) ?></div>
    </div>
    <?php if ($admin): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:12px;border-top:1px solid var(--linie);padding-top:12px">
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_zusammenfuehren">
        <div class="feld" style="margin:0"><label for="ziel-<?= $i ?>">Ist dieselbe — bleiben soll</label>
          <select id="ziel-<?= $i ?>" name="paar">
            <option value="<?= (int) $a['id'] ?>:<?= (int) $b['id'] ?>"><?= Fmt::h((string) $a['name']) ?> (älter)</option>
            <option value="<?= (int) $b['id'] ?>:<?= (int) $a['id'] ?>"><?= Fmt::h((string) $b['name']) ?></option>
          </select></div>
        <button class="knopf">Zusammenführen</button>
      </form>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_dublette_nein"><input type="hidden" name="a" value="<?= (int) $a['id'] ?>"><input type="hidden" name="b" value="<?= (int) $b['id'] ?>">
        <button class="knopf">Sind verschieden</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
