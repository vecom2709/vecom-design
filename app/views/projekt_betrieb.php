<?php
/* AutoBuild Phase 10 (07.10.2026): Karte „Betrieb“ — auf einen Blick, wie es der Live-Seite geht. Regeln in Betrieb.php. */
require_once dirname(__DIR__) . '/src/Betrieb.php';
$bt = Betrieb::stand((int) $p['id']);
$btAdmin = Auth::istAdmin();
$btForm = static fn(string $tat, string $inhalt): string => '<form method="post" action="' . Fmt::h(url('')) . '" style="display:inline-flex;gap:6px;flex-wrap:wrap;align-items:center;margin:6px 6px 0 0">'
    . Csrf::feld() . '<input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="id" value="' . (int) $p['id'] . '">' . $inhalt . '</form>';
$btZeile = static fn(bool|null $ok, string $titel, string $text): string => '<li style="padding:4px 0;display:flex;gap:8px"><span style="flex:0 0 18px;color:'
    . ($ok === null ? 'var(--leise)' : ($ok ? 'var(--gruen, #2e7d32)' : 'var(--rot, #c0392b)')) . '">' . ($ok === null ? '·' : ($ok ? '✓' : '✗')) . '</span><span><b>' . Fmt::h($titel) . '</b> ' . $text . '</span></li>';
?>
<?php if ($bt['im_betrieb']): ?>
<div class="block" id="betrieb">
  <h2>Betrieb <span style="font-size:var(--fs-klein);color:var(--leise);font-weight:500">live seit <?= Fmt::h(Fmt::datum((string) $bt['seit'])) ?> auf <?= Fmt::h($bt['domain']) ?> · V<?= (int) ($bt['live']['nummer'] ?? 0) ?></span></h2>
  <ul style="list-style:none;margin:0;padding:0;font-size:14.5px">
    <?php
      $ws = (string) ($bt['website']['status'] ?? '');
      echo $btZeile($bt['website'] ? $ws === 'online' : null, 'Erreichbar:', $bt['website'] ? Fmt::h($ws ?: 'noch nicht gemessen') . (!empty($bt['website']['last_ok_at']) ? ' · zuletzt ok ' . Fmt::h(Fmt::seit((string) $bt['website']['last_ok_at'])) : '') : 'nicht in der Überwachung („Laufen die Seiten?“)');
      echo $btZeile($bt['ssl_tage'] === null ? null : $bt['ssl_tage'] >= 14, 'Zertifikat:', $bt['ssl_tage'] === null ? 'noch nicht gemessen' : ($bt['ssl_tage'] < 0 ? 'abgelaufen' : 'noch ' . (int) $bt['ssl_tage'] . ' Tage'));
      echo $btZeile($bt['livecheck'] ? !empty($bt['livecheck']['ok']) : null, 'Live-Prüfung:', $bt['livecheck'] ? Fmt::h((string) $bt['livecheck']['text']) : 'noch nicht');
      echo $btZeile($bt['abweichung'] ? !empty($bt['abweichung']['ok']) : null, 'Unverändert:', $bt['abweichung'] ? Fmt::h((string) $bt['abweichung']['text']) . ' <span class="akq-klein" style="color:var(--leise)">(' . Fmt::h(Fmt::seit((string) $bt['abweichung_am'])) . ')</span>' : 'wird heute Nacht zum ersten Mal verglichen');
      $sc = $bt['seitencheck'];
      echo $btZeile($sc ? (int) $sc['befunde'] === 0 : null, 'Seitenprüfung:', $sc ? ((int) $sc['befunde'] === 0 ? 'alles in Ordnung' : (int) $sc['befunde'] . ' Befund(e)') . ' <span class="akq-klein" style="color:var(--leise)">(' . Fmt::h(Fmt::seit((string) $bt['seitencheck_am'])) . ')</span>' : 'läuft einmal pro Woche');
      echo $btZeile($bt['sicherung'] ? true : null, 'Letzte Sicherung:', $bt['sicherung'] ? Fmt::h(Fmt::datum((string) $bt['sicherung']['created_at'])) . ' · ' . Fmt::h((string) $bt['sicherung']['orig_name']) : 'noch keine (entsteht vor jeder Veröffentlichung)');
      $k = $bt['kontingent'];
      echo $btZeile(null, 'Betreuung:', $k['vertrag'] === null ? 'kein Vertrag' . ($k['in_nachbesserung'] ? ' · Nachbesserung bis ' . Fmt::h(Fmt::datum((string) $k['nachbesserung_bis'])) : ' · neue Wünsche sind Zusatz') : Fmt::h((string) $k['vertrag']) . ' · ' . (int) $k['verbraucht'] . ' / ' . (int) $k['minuten'] . ' Min. in diesem Monat');
      echo $btZeile($bt['offene_wuensche'] === 0, 'Offene Wünsche:', (string) (int) $bt['offene_wuensche']);
      echo $btZeile($bt['zahlung_offen'] === 0, 'Überfällige Raten:', (string) (int) $bt['zahlung_offen'] . ($bt['zahlung_offen'] > 0 ? ' — die Seite bleibt online; du entscheidest.' : ''));
      echo $btZeile($bt['bau_laeufe'] < $bt['grenze'], 'Kostenwächter:', (int) $bt['bau_laeufe'] . ' von ' . (int) $bt['grenze'] . ' Bauläufen');
    ?>
  </ul>
  <?php if ($sc && (int) $sc['befunde'] > 0): ?>
    <details style="margin-top:6px"><summary style="cursor:pointer;font-size:14px">Befunde der Seitenprüfung</summary>
      <ul style="font-size:14px;margin:6px 0 0"><?php foreach ($sc['punkte'] as $pk): if ($pk['ok']) { continue; } ?><li><?= Fmt::h($pk['name']) ?><?= $pk['detail'] !== '' ? ' — ' . Fmt::h($pk['detail']) : '' ?></li><?php endforeach; ?></ul></details>
  <?php endif; ?>
  <?php if ($btAdmin): ?>
    <div style="margin-top:8px">
      <?= $btForm('betrieb_pruefen', '<button class="knopf klein">Jetzt prüfen</button>') ?>
      <?php if ($bt['abweichung'] && empty($bt['abweichung']['ok']) && $bt['live']): ?>
        <?= $btForm('veroeffentlichen', '<input type="hidden" name="version" value="' . (int) $bt['live']['id'] . '"><button class="knopf klein haupt">V' . (int) $bt['live']['nummer'] . ' wiederherstellen (Sicherung vorher)</button>') ?>
      <?php endif; ?>
      <?php if ($bt['bau_laeufe'] >= $bt['grenze']): ?>
        <?= $btForm('betrieb_grenze', '<button class="knopf klein">' . Betrieb::GRENZE_SCHRITT . ' weitere Bauläufe freigeben</button>') ?>
      <?php endif; ?>
      <a class="knopf klein" style="margin-top:6px" href="<?= Fmt::h(url('projekte/' . (int) $p['id'] . '/betriebsbericht.pdf')) ?>">Betriebsbericht (Vormonat, PDF)</a>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
