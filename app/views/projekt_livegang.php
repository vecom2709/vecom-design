<?php
/* AutoBuild Phase 9 (06.10.2026): Lieferprüfung, Livegang, Übergabe.
   Regeln in Lieferung.php und Veroeffentlichung::stand; hier wird gezeigt und abgehakt. */
require_once dirname(__DIR__) . '/src/Lieferung.php';
require_once dirname(__DIR__) . '/src/Veroeffentlichung.php';
require_once dirname(__DIR__) . '/src/BauAuftrag.php';
$lgP = Db::one('SELECT live_version_id, veroeffentlicht_am, veroeffentlicht_domain, livecheck, livecheck_am, uebergabe, uebergabe_am, uebergabe_frei_am FROM projects WHERE id = ?', [(int) $p['id']]) ?: [];
/* Kandidat: die neueste geprüfte Fassung, die noch nicht live ist — sonst die neueste. */
$lgListe = Versionen::liste((int) $p['id']);
$lgV = null;
foreach ($lgListe as $lgX) { if (!empty($lgX['geprueft_am']) && (int) $lgX['id'] !== (int) ($lgP['live_version_id'] ?? 0)) { $lgV = Versionen::laden((int) $lgX['id']); break; } }
$lgV ??= $lgListe ? Versionen::laden((int) $lgListe[0]['id']) : null;
$lgAdmin = Auth::istAdmin();
$lgForm = static fn(string $tat, string $inhalt, string $stil = 'display:inline-flex;gap:6px;flex-wrap:wrap;align-items:center;margin:6px 6px 0 0'): string => '<form method="post" action="' . Fmt::h(url('')) . '" style="' . $stil . '">'
    . Csrf::feld() . '<input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="id" value="' . (int) $p['id'] . '">' . $inhalt . '</form>';
?>
<?php if ($lgListe): ?>
<div class="block" id="livegang">
  <h2>Livegang <span style="font-size:var(--fs-klein);color:var(--leise);font-weight:500">Lieferprüfung → veröffentlichen (Sicherung vorher) → Live-Prüfung → Übergabe</span></h2>
  <?php if ($lgV): $lgAuto = Lieferung::automatisch((int) $p['id'], $lgV); $lgL = Lieferung::fuer((int) $lgV['id']); $lgSt = Veroeffentlichung::stand((int) $p['id'], (int) $lgV['id']); ?>
    <h3 style="margin:4px 0;font-size:16px">Lieferprüfung für V<?= (int) $lgV['nummer'] ?></h3>
    <ul style="list-style:none;margin:0;padding:0;font-size:14px">
      <?php foreach ($lgAuto as $lgA): ?>
        <li style="padding:3px 0;color:<?= $lgA['ok'] ? 'var(--dim)' : ($lgA['schwer'] ? 'var(--rot, #c0392b)' : 'var(--gelb, #b7791f)') ?>"><?= $lgA['ok'] ? '✓' : ($lgA['schwer'] ? '✗' : '!') ?> <?= Fmt::h($lgA['text']) ?><?= $lgA['detail'] !== '' && !$lgA['ok'] ? ' — ' . Fmt::h($lgA['detail']) : '' ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($lgL): ?>
      <p style="font-size:15px;margin:10px 0 0;color:var(--gruen, #2e7d32)"><b>✓ Lieferprüfung abgehakt</b> von <?= Fmt::h((string) $lgL['von']) ?> am <?= Fmt::h(Fmt::datum((string) $lgL['created_at'])) ?><?= (string) ($lgL['notiz'] ?? '') !== '' ? ' · ' . Fmt::h((string) $lgL['notiz']) : '' ?></p>
    <?php elseif ($lgAdmin): ?>
      <?php $lgHaken = ''; foreach (Lieferung::MANUELL as $lgK => $lgT) { $lgHaken .= '<label style="display:flex;gap:8px;align-items:flex-start;font-size:14px;margin:4px 0"><input type="checkbox" name="haken[]" value="' . $lgK . '" style="margin-top:3px;width:20px;height:20px;min-width:0;flex:0 0 20px;padding:0"> <span style="flex:1 1 auto;text-align:left">' . Fmt::h($lgT) . '</span></label>'; } ?>
      <?= $lgForm('lieferung_bestaetigen', '<input type="hidden" name="version" value="' . (int) $lgV['id'] . '"><div style="flex-basis:100%;margin-top:6px"><b style="font-size:14px">Selbst angesehen:</b>' . $lgHaken . '</div><input name="notiz" maxlength="500" placeholder="Notiz (optional)" style="min-width:min(320px,100%)"><button class="knopf">Lieferprüfung abhaken</button>', 'display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:8px 0 0') ?>
    <?php else: ?><p class="akq-klein">Abhaken kann nur ein Admin.</p><?php endif; ?>
    <?php if ($lgSt['auftrag'] && $lgAdmin): ?>
      <div style="margin-top:10px">
        <?php if ($lgSt['bereit']): ?>
          <?= $lgForm('veroeffentlichen', '<input type="hidden" name="version" value="' . (int) $lgV['id'] . '"><button class="knopf haupt">V' . (int) $lgV['nummer'] . ' auf ' . Fmt::h((string) $lgSt['auftrag']['domain']) . ' veröffentlichen</button>') ?>
        <?php else: ?>
          <p class="akq-klein" style="margin:0">Noch nicht bereit zum Veröffentlichen: <?= Fmt::h(implode(' ', $lgSt['gruende'])) ?></p>
        <?php endif; ?>
      </div>
    <?php elseif (!$lgSt['auftrag']): ?>
      <p class="akq-klein" style="margin:8px 0 0">Die Domain liegt nicht bei uns — dann Paket herunterladen und von Hand übertragen; die Lieferprüfung gilt trotzdem.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($lgP['veroeffentlicht_am'])): $lgLc = json_decode((string) ($lgP['livecheck'] ?? ''), true) ?: null; ?>
    <h3 style="margin:16px 0 4px;font-size:16px">Nach dem Livegang</h3>
    <p style="font-size:14px;margin:0">Live seit <?= Fmt::h(Fmt::datum((string) $lgP['veroeffentlicht_am'])) ?> auf <b><?= Fmt::h((string) $lgP['veroeffentlicht_domain']) ?></b>
      <?= (int) ($lgP['live_version_id'] ?? 0) > 0 ? '· V' . (int) Db::wert('SELECT nummer FROM projekt_versionen WHERE id = ?', [(int) $lgP['live_version_id']], 0) : '' ?></p>
    <?php if ($lgLc): ?><p style="font-size:14px;margin:4px 0 0;color:<?= !empty($lgLc['ok']) ? 'var(--gruen, #2e7d32)' : 'var(--rot, #c0392b)' ?>"><?= !empty($lgLc['ok']) ? '✓' : '✗' ?> <?= Fmt::h((string) $lgLc['text']) ?> <span class="akq-klein" style="color:var(--leise)">(<?= Fmt::h(Fmt::datum((string) $lgP['livecheck_am'])) ?>)</span></p><?php endif; ?>
    <?php if ($lgAdmin): ?>
      <?= $lgForm('lieferung_livecheck', '<button class="knopf klein">Live-Seite prüfen</button>') ?>
      <?= $lgForm('uebergabe_erstellen', '<button class="knopf klein">' . (empty($lgP['uebergabe']) ? 'Übergabe erstellen' : 'Übergabe neu erstellen') . '</button>') ?>
      <?php if (!empty($lgP['uebergabe'])): ?>
        <?= empty($lgP['uebergabe_frei_am']) ? $lgForm('uebergabe_frei', '<button class="knopf klein">Dem Kunden zeigen</button>') : $lgForm('uebergabe_zu', '<button class="knopf klein">Vor dem Kunden verbergen</button>') ?>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($lgP['uebergabe'])): ?>
      <details style="margin-top:8px"<?= empty($lgP['uebergabe_frei_am']) ? ' open' : '' ?>><summary style="cursor:pointer;font-size:14px">Übergabe-Dokument <?= !empty($lgP['uebergabe_frei_am']) ? '· 👁 der Kunde sieht es seit ' . Fmt::h(Fmt::datum((string) $lgP['uebergabe_frei_am'])) : '· noch nicht freigegeben' ?></summary>
        <div style="font-size:15px;line-height:1.55;border:1px solid var(--linie, #eee);border-radius:8px;padding:6px 14px;margin-top:6px"><?= BauAuftrag::alsHtml((string) $lgP['uebergabe']) ?></div>
        <p style="margin:8px 0 0"><a class="knopf klein" href="<?= Fmt::h(url('projekte/' . (int) $p['id'] . '/uebergabe.pdf')) ?>">Als PDF</a></p>
      </details>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
