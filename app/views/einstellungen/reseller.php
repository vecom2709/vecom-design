<?php
/* DER RESELLER AUF EINEN BLICK (26.09.2026)
   ==========================================================================
   Was im KAS steht -- Kunden-Accounts, Domains, Postfaecher, Speicher -- und
   daneben, was bei Vecom vereinbart ist. Vecom ist die Quelle: Weicht der
   KAS ab, steht es hier, und korrigiert wird in der Kundenakte ("KAS auf
   Vecom-Wert setzen"), nie andersherum.

   Gelesen wird nur auf Knopfdruck (fuenf Aufrufe, je ~2 s Flutbremse), der
   Stand wird ohne Passwort gemerkt. */
require_once __DIR__ . '/../../src/Kas.php';
require_once __DIR__ . '/../../src/Hosting.php';
$rsStand = json_decode((string) sicher(static fn() => Db::wert("SELECT svalue FROM settings WHERE skey = 'kas_reseller_stand'", [], ''), ''), true);
$rsVecom = [];
foreach (sicher(static fn() => Db::all("SELECT * FROM hosting_auftraege WHERE kas_login IS NOT NULL AND status IN ('angelegt','aktiv','in_arbeit')"), []) as $rsH) {
    $rsVecom[(string) $rsH['kas_login']] = Hosting::speicherVon($rsH);
}
$rs = is_array($rsStand) ? Kas::resellerAuswerten($rsStand, $rsVecom) : null;
$rsZahl = static fn(?int $n): string => $n === null ? '—' : ($n < 0 ? 'unbegrenzt' : number_format($n, 0, ',', '.'));
?>
<div class="block"><h2>Reseller (KAS)
  <?php if ($rs): ?><span class="mehr" style="color:var(--leise);font-size:12.5px;font-weight:400">Stand <?= Fmt::h(Fmt::zeit((string) $rsStand['am'])) ?></span><?php endif; ?></h2>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0 0 14px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="kas_reseller_lesen">
    <button class="knopf<?= $rs ? '' : ' haupt' ?>"><?= $rs ? 'Neu auslesen' : 'Jetzt auslesen' ?></button>
    <span style="color:var(--leise);font-size:12.5px;margin-left:8px">Nur lesen — im KAS wird nichts verändert. Dauert etwa zehn Sekunden.</span>
  </form>
  <?php if (!Kas::bereit()): ?>
    <p class="hinweis">Noch kein KAS-Zugang hinterlegt — unter <a href="<?= Fmt::h(url('einstellungen?b=zugaenge')) ?>">Zugänge &amp; Schutz</a> eintragen.</p>
  <?php endif; ?>
  <?php if ($rs): ?>
    <?php foreach ((array) ($rsStand['fehler'] ?? []) as $rsF): ?><p class="hinweis schlecht" style="font-size:13px"><?= Fmt::h((string) $rsF) ?></p><?php endforeach; ?>
    <?php foreach ((array) ($rsStand['hinweise'] ?? []) as $rsF): ?><p style="color:var(--leise);font-size:12.5px"><?= Fmt::h((string) $rsF) ?></p><?php endforeach; ?>

    <div class="kacheln" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:6px 0 16px">
      <?php foreach ([['Kunden-Accounts', count($rs['kunden'])], ['Domains (Hauptaccount)', $rs['domains']],
                      ['Subdomains (Hauptaccount)', $rs['subdomains']], ['Postfächer (Hauptaccount)', $rs['postfaecher']]] as [$rsN, $rsW]): ?>
        <div style="border:1px solid var(--linie);border-radius:10px;padding:10px 12px">
          <div style="font-size:22px;font-weight:700"><?= (int) $rsW ?></div><div style="color:var(--leise);font-size:12.5px"><?= Fmt::h($rsN) ?></div></div>
      <?php endforeach; ?>
    </div>

    <h3 style="font-size:14px;margin:0 0 6px">Kontingente des Reseller-Vertrags</h3>
    <div class="tabellenrahmen"><table>
      <thead><tr><th>Was</th><th>Höchstens</th><th>Belegt</th><th>Frei</th></tr></thead><tbody>
      <?php foreach ($rs['kontingente'] as $rsK): ?>
        <tr><td><?= Fmt::h($rsK['name']) ?></td>
          <?php foreach (['max', 'belegt', 'frei'] as $rsS): ?>
            <td><?= $rsK['schluessel'] === 'max_webspace' && $rsK[$rsS] !== null && $rsK[$rsS] > 0 ? Fmt::h(Hosting::gb((int) $rsK[$rsS])) : Fmt::h($rsZahl($rsK[$rsS])) ?></td>
          <?php endforeach; ?></tr>
      <?php endforeach; ?>
      <?php if (!$rs['kontingente']): ?><tr><td colspan="4" style="color:var(--leise)">Keine Angaben.</td></tr><?php endif; ?>
    </tbody></table></div>

    <h3 style="font-size:14px;margin:18px 0 6px">Speicher je Kunde — Vecom gegen KAS</h3>
    <p style="color:var(--leise);font-size:12.5px;margin:0 0 8px">
      Vereinbart sind zusammen <b><?= Fmt::h(Hosting::gb((int) $rs['summe_vereinbart_mb'])) ?></b><?=
        $rs['pool_mb'] !== null ? ' von ' . Fmt::h(Hosting::gb((int) $rs['pool_mb'])) . ' im Reseller-Vertrag' : '' ?>.
      <?php if ($rs['ueberbucht']): ?><span class="marke2 schlecht">mehr vereinbart, als der Vertrag hat</span><?php endif; ?></p>
    <div class="tabellenrahmen"><table>
      <thead><tr><th>Account</th><th>Kunde (Kommentar)</th><th>Vereinbart (Vecom)</th><th>Im KAS</th><th>Belegt</th><th></th></tr></thead><tbody>
      <?php foreach ($rs['kunden'] as $rsA): ?>
        <tr><td><code><?= Fmt::h($rsA['login']) ?></code></td><td><?= Fmt::h($rsA['kommentar']) ?></td>
          <td><?= $rsA['vecom_mb'] !== null ? Fmt::h(Hosting::gb((int) $rsA['vecom_mb'])) : '—' ?></td>
          <td><?= $rsA['kas_mb'] === null ? '—' : ($rsA['kas_mb'] < 0 ? 'unbegrenzt' : Fmt::h(Hosting::gb((int) $rsA['kas_mb']))) ?></td>
          <td><?= $rsA['belegt_mb'] !== null ? Fmt::h(Hosting::gb((int) $rsA['belegt_mb'])) : '—' ?></td>
          <td><span class="marke2 <?= ['passt' => 'gut', 'weicht_ab' => 'warn', 'unbegrenzt' => 'warn', 'nicht_in_vecom' => ''][$rsA['zustand']] ?>"><?= Fmt::h([
              'passt' => 'passt', 'weicht_ab' => 'weicht ab', 'unbegrenzt' => 'ohne Grenze', 'nicht_in_vecom' => 'bei Vecom unbekannt'][$rsA['zustand']]) ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$rs['kunden']): ?><tr><td colspan="6" style="color:var(--leise)">Im Reseller-Vertrag gibt es noch keinen Kunden-Account.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php if ($rs['vecom_ohne_kas']): ?>
      <p class="hinweis schlecht" style="font-size:13px;margin-top:10px">Bei Vecom eingetragen, im KAS nicht gefunden: <?= Fmt::h(implode(', ', $rs['vecom_ohne_kas'])) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
