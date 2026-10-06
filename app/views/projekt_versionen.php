<?php
/* AutoBuild Phase 6 (06.10.2026): Fassungen V1, V2 … — Testfassung, geprüft, live, zurückrollen.
   Die Regeln stehen in Versionen.php und Veroeffentlichung::stand(); hier wird nur gezeigt und bedient. */
require_once dirname(__DIR__) . '/src/Versionen.php';
require_once dirname(__DIR__) . '/src/Veroeffentlichung.php';
require_once dirname(__DIR__) . '/src/BauAuftrag.php';
$pvListe = Versionen::liste((int) $p['id']);
$pvLive = (int) ($p['live_version_id'] ?? Db::wert('SELECT live_version_id FROM projects WHERE id = ?', [(int) $p['id']], 0));
$pvAdmin = Auth::istAdmin();
$pvNetlify = Versionen::netlifyBereit();
$pvForm = static fn(string $tat, int $vid, string $inhalt): string => '<form method="post" action="' . Fmt::h(url('')) . '" style="display:inline-flex;gap:6px;flex-wrap:wrap;align-items:center;margin:4px 6px 0 0">'
    . Csrf::feld() . '<input type="hidden" name="tat" value="' . $tat . '"><input type="hidden" name="id" value="' . (int) $p['id'] . '"><input type="hidden" name="version" value="' . $vid . '">' . $inhalt . '</form>';
?>
<?php if ($pvListe): ?>
<div id="versionen" style="margin-top:14px">
  <h3 style="margin:0 0 4px;font-size:16px">Fassungen <span style="font-size:12px;color:var(--leise);font-weight:500">erst Testfassung, dann geprüft, dann live · zurückrollen = frühere Fassung erneut veröffentlichen (vorher Sicherung)</span></h3>
  <?php foreach (array_slice($pvListe, 0, 8) as $pvI => $pv): ?>
    <?php $pvIstLive = (int) $pv['id'] === $pvLive; $pvSt = $pvAdmin ? Veroeffentlichung::stand((int) $p['id'], (int) $pv['id']) : null; ?>
    <div style="border-top:1px solid var(--linie, #eee);padding:10px 0;font-size:14px">
      <b style="font-size:16px">V<?= (int) $pv['nummer'] ?></b>
      <?php if ($pvIstLive): ?> <span style="background:var(--gruen, #2e7d32);color:#fff;border-radius:6px;padding:1px 8px;font-size:12px;font-weight:600">LIVE</span><?php endif; ?>
      <a href="<?= Fmt::h(url('dateien/' . (int) $pv['file_id'])) ?>"><?= Fmt::h((string) $pv['orig_name']) ?></a>
      <span class="akq-klein" style="color:var(--leise)"> · <?= Fmt::h(Versionen::QUELLEN[$pv['quelle']] ?? $pv['quelle']) ?> · <?= Fmt::h(Fmt::datum((string) $pv['created_at'])) ?><?= (string) ($pv['notiz'] ?? '') !== '' ? ' · ' . Fmt::h((string) $pv['notiz']) : '' ?></span>
      <div style="margin-top:4px;font-size:13px;color:var(--dim)">
        <?= !empty($pv['staging_url']) ? '✓ Testfassung: <a href="' . Fmt::h((string) $pv['staging_url']) . '" target="_blank" rel="noopener">' . Fmt::h(preg_replace('~^https://~', '', (string) $pv['staging_url'])) . '</a>' : '✗ noch nicht auf der Testfassung' ?>
        · <?= !empty($pv['geprueft_am']) ? '✓ geprüft von ' . Fmt::h((string) $pv['geprueft_von']) . ' am ' . Fmt::h(Fmt::datum((string) $pv['geprueft_am'])) : '✗ nicht geprüft' ?>
        <?= !empty($pv['live_am']) ? ' · zuletzt live ' . Fmt::h(Fmt::datum((string) $pv['live_am'])) : '' ?>
      </div>
      <?php /* Phase 7: automatische Tests und Review */ $pvT = json_decode((string) ($pv['tests'] ?? ''), true) ?: []; ?>
      <?php if ($pvT || !empty($pv['review_urteil'])): ?>
        <details style="margin-top:4px">
          <summary style="cursor:pointer;font-size:13px">
            <?php if ($pvT): $pvFehl = array_filter($pvT, static fn($x) => empty($x['ok'])); ?><?= (int) $pv['tests_ok'] === 1 ? '✓ Tests bestanden' : '✗ Tests mit Mängeln' ?> (<?= count($pvT) - count($pvFehl) ?>/<?= count($pvT) ?>)<?php endif; ?>
            <?php if (!empty($pv['review_urteil'])): ?> · <?= $pv['review_urteil'] === 'bestanden' ? '✓ Review bestanden' : '✗ Review: nachbessern' ?><?php endif; ?>
          </summary>
          <?php if ($pvT): ?><ul style="margin:6px 0 0 18px;padding:0;font-size:13px">
            <?php foreach ($pvT as $pvX): ?><li style="color:<?= !empty($pvX['ok']) ? 'var(--dim)' : (!empty($pvX['schwer']) ? 'var(--rot, #c0392b)' : 'var(--gelb, #b7791f)') ?>"><?= !empty($pvX['ok']) ? '✓' : '✗' ?> <?= Fmt::h((string) $pvX['name']) ?><?= !empty($pvX['detail']) && empty($pvX['ok']) ? ' — ' . Fmt::h((string) $pvX['detail']) : '' ?></li><?php endforeach; ?>
          </ul><?php endif; ?>
          <?php if (!empty($pv['review_text'])): ?><div style="font-size:14px;line-height:1.5;max-height:420px;overflow:auto;border:1px solid var(--linie, #eee);border-radius:8px;padding:6px 12px;margin-top:6px"><?= BauAuftrag::alsHtml((string) $pv['review_text']) ?></div><?php endif; ?>
        </details>
      <?php endif; ?>
      <?php if ($pvAdmin): ?>
        <div>
        <?php if (empty($pv['live_am'])): ?>
          <?php if ($pvNetlify): ?><?= $pvForm('version_netlify', (int) $pv['id'], '<button class="knopf klein">' . (empty($pv['staging_url']) ? 'Auf die Testfassung (Netlify)' : 'Neu auf die Testfassung') . '</button>') ?><?php endif; ?>
          <?php $pvFeld = $pvForm('version_staging', (int) $pv['id'], '<input name="url" required placeholder="Testadresse, z. B. kunde-v' . (int) $pv['nummer'] . '.netlify.app" style="min-width:min(280px,100%)"><button class="knopf klein">Testadresse eintragen</button>'); ?>
          <?php if (empty($pv['staging_url'])): ?><?= $pvFeld ?>
          <?php else: ?><details style="display:inline-block;margin:4px 6px 0 0"><summary style="cursor:pointer;font-size:13px">Testadresse ändern</summary><?= $pvFeld ?></details><?php endif; ?>
          <?php if (empty($pv['review_am'])): ?><?= $pvForm('version_review', (int) $pv['id'], '<button class="knopf klein">Review durch Claude</button>') ?><?php endif; ?>
          <?php if (!empty($pv['staging_url']) && empty($pv['geprueft_am'])): ?>
            <?= $pvForm('version_geprueft', (int) $pv['id'], '<button class="knopf klein">Angesehen — geprüft</button>') ?>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($pvSt && $pvSt['auftrag'] && $pvSt['bereit'] && !$pvIstLive): ?>
          <?= $pvForm('veroeffentlichen', (int) $pv['id'], '<button class="knopf klein">' . (!empty($pv['live_am']) ? '↩ Diese Fassung wieder veröffentlichen' : 'V' . (int) $pv['nummer'] . ' veröffentlichen') . '</button>') ?>
          <?php if (!empty($pv['live_am'])): ?><span class="akq-klein" style="color:var(--leise)">Vorher wird gesichert. Dateien, die es nur in neueren Fassungen gibt, bleiben liegen.</span><?php endif; ?>
        <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$pvNetlify && $pvAdmin): ?><p class="akq-klein" style="margin:6px 0 0;color:var(--leise)">Ohne Netlify-Schlüssel (config.local.php: <code>netlify_token</code>) wird die Testadresse von Hand eingetragen.</p><?php endif; ?>
</div>
<?php endif; ?>
