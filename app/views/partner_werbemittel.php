<?php
/* ==========================================================================
   Marketing Center im Partnerbereich (03.10.2026, Phase 1).
   Eingebunden aus partner.php — und aus der Verwaltung („Als Partner
   ansehen“), dann mit $wmNurLesen = true und ohne Download-Links.

   Gesetzt sein müssen: $p, $sprache, $h, $wmKatalog (Werbemittel::katalog),
   $wmNurLesen, und — nur im Partnerbereich — $selbst.

   Der Katalog trägt nur Name, Format und Endpreis. Einkauf und Marge
   kommen hier gar nicht erst an (Werbemittel::katalog).
   ========================================================================== */
$W = static fn(string $k): string => Texte::h(Texte::PARTNER_WERBEMITTEL[$k] ?? [], $sprache);
$wmLink = PartnerWerbung::link($p, 'qr');
$wmName = PartnerKarten::name($p);
$wmDl = static fn(string $art): string => $wmNurLesen ? '#' : $selbst(['wmqr' => $art]);
?>
<style>
  .wm-kopf{display:grid;grid-template-columns:auto 1fr;gap:18px;align-items:center}
  .wm-qr{background:#fff;border-radius:12px;padding:8px;line-height:0;width:max-content}
  .wm-qr svg{width:148px;height:148px;display:block}
  .wm-daten{display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:0;font-size:14.5px}
  .wm-daten dt{color:var(--leise)}
  .wm-daten dd{margin:0;color:var(--text);min-width:0;overflow-wrap:anywhere}
  .wm-daten code{font-size:13.5px;color:var(--dim)}
  .wm-knoepfe{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
  .wm-kat{margin:18px 0 8px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#f1d38b}
  .wm-produkt{border:1px solid var(--linie);border-radius:14px;padding:14px;display:grid;gap:12px;margin-bottom:12px}
  .wm-produkt img{width:100%;height:auto;border-radius:8px;display:block;background:#15130f}
  .wm-produkt h3{margin:0;font-size:17px}
  .wm-produkt .wm-text{margin:4px 0 0;color:var(--dim);font-size:14.5px;line-height:1.5}
  .wm-meta{font-size:12.5px;color:var(--leise);margin-top:6px}
  .wm-varianten{width:100%;border-collapse:collapse;font-size:14.5px}
  .wm-varianten td{padding:8px 0;border-top:1px solid var(--linie)}
  .wm-varianten td:last-child{text-align:right;font-weight:600;white-space:nowrap;color:var(--text)}
  .wm-bald{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;color:var(--dim);line-height:1.5}
  .wm-bald b{display:block;color:var(--text)}
  .wm-kit{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
  .wm-meldung{margin:0;padding:10px 12px;border-radius:10px;border:1px solid var(--linie2);font-size:14px;color:var(--text)}
  .wm-meldung.gut{border-color:rgba(120,200,140,.5);background:rgba(120,200,140,.08)}
  .wm-frei,.wm-entwurf{display:grid;gap:6px;padding:12px;border-radius:12px;font-size:14px}
  .wm-frei{border:1px solid rgba(120,200,140,.45);background:rgba(120,200,140,.06)}
  .wm-frei b{color:#9fe0b0}
  .wm-entwurf{border:1px solid rgba(241,211,139,.55);background:rgba(241,211,139,.06)}
  .wm-entwurf .knopf{justify-self:start}
  .wm-haken{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;line-height:1.5;color:var(--dim);cursor:pointer}
  .wm-haken input{width:20px;height:20px;flex:none;margin-top:1px}
  .wm-gestalten summary{cursor:pointer;color:#f1d38b;font-size:14.5px;padding:4px 0}
  .wm-gestalten form{display:grid;gap:10px;margin-top:8px}
  .wm-gestalten fieldset{border:0;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:6px}
  .wm-gestalten legend{font-size:12.5px;color:var(--leise);margin-bottom:4px;width:100%}
  .wm-gestalten label{display:inline-flex;gap:6px;align-items:center;min-height:38px;padding:6px 12px;border:1px solid var(--linie2);border-radius:999px;font-size:13.5px;cursor:pointer}
  .wm-gestalten label:has(input:checked){border-color:rgba(241,211,139,.7);background:rgba(241,211,139,.09);color:var(--text)}
  .wm-gestalten button{justify-self:start}
  @media (max-width:520px){ .wm-kopf{grid-template-columns:1fr} .wm-qr{margin:0 auto} }
</style>

<div class="block pt" id="werbemittel" data-reiter="werbemittel">
  <h2>Marketing Center</h2>
  <p class="lead"><?= $h($W('lead')) ?></p>

  <h3 style="font-size:15px;margin:16px 0 10px"><?= $h($W('daten')) ?></h3>
  <div class="wm-kopf">
    <div class="wm-qr"><?= QrBild::svg($wmLink, 148, 2) ?></div>
    <div>
      <dl class="wm-daten">
        <dt><?= $h($W('name')) ?></dt><dd><?= $h($wmName) ?></dd>
        <dt><?= $h($W('id')) ?></dt><dd><b><?= $h((string) $p['code']) ?></b></dd>
        <dt><?= $h($W('link')) ?></dt><dd><code><?= $h((string) preg_replace('~^https?://~', '', $wmLink)) ?></code></dd>
      </dl>
      <p class="wm-meta"><?= $h($W('qr_satz')) ?></p>
      <?php if (!$wmNurLesen): ?>
        <div class="wm-knoepfe">
          <a class="knopf" href="<?= $h($wmDl('svg')) ?>" download><?= $h($W('qr_svg')) ?></a>
          <a class="knopf" href="<?= $h($wmDl('png')) ?>" download><?= $h($W('qr_png')) ?></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="block pt" id="werbemittel-katalog" data-reiter="werbemittel">
  <h2><?= $h($W('katalog')) ?></h2>
  <?php foreach ($wmKatalog as $wmK): ?>
    <p class="wm-kat"><?= $h($wmK['name']) ?></p>
    <?php foreach ($wmK['produkte'] as $wmP): ?>
      <?php /* Phase 2: Stand der Druckdatei dieses Partners (Entwurf/Freigabe). Die
               Vorschau zeigt die zuletzt gewählte Fassung, sonst Stil a. */
        $wmSt = !$wmNurLesen && (int) ($p['id'] ?? 0) > 0 ? Werbemittel::stand((int) $p['id'], (int) $wmP['id']) : ['entwurf' => null, 'freigegeben' => null];
        $wmJetzt = $wmSt['entwurf']['wahl'] ?? $wmSt['freigegeben']['wahl'] ?? ['stil' => 'a', 'sprache' => $sprache, 'kontakt' => 'email']; ?>
      <article class="wm-produkt" id="wm-p<?= (int) $wmP['id'] ?>">
        <?php if ($wmP['vorlage'] === 'visitenkarte'):
          $wmBild = $wmNurLesen
            ? 'data:image/jpeg;base64,' . base64_encode(PartnerKarten::vorschau($p, 'a', $sprache))
            : $selbst(['vk' => $wmJetzt['stil'], 'f' => 'vorschau', 'vks' => $wmJetzt['sprache'], 'ks' => $wmJetzt['kontakt']]); ?>
          <img src="<?= $h($wmBild) ?>" width="720" height="231" loading="lazy" decoding="async"
               alt="<?= $h(strtr($W('vorschau_alt'), ['{name}' => $wmP['name']])) ?>">
        <?php endif; ?>
        <div>
          <h3><?= $h($wmP['name']) ?><?php if (isset($wmP['sichtbar']) && !$wmP['sichtbar']): ?> <span class="marke2 warnung" style="font-size:12px">für Partner noch aus</span><?php endif; ?></h3>
          <?php if ($wmP['text'] !== ''): ?><p class="wm-text"><?= $h($wmP['text']) ?></p><?php endif; ?>
          <p class="wm-meta"><?= $wmP['format'] !== '' ? $h($W('format') . ' ' . $wmP['format']) . ' · ' : '' ?><?= $h($wmP['nummer']) ?> · <?= $h(strtr($W('ab'), ['{preis}' => Werbemittel::euro((int) $wmP['ab_cent'])])) ?></p>
        </div>
        <table class="wm-varianten" aria-label="<?= $h($W('je_auflage')) ?>">
          <?php foreach ($wmP['varianten'] as $wmV): ?>
            <tr><td><?= $h($wmV['name']) ?></td><td><?= $h(Werbemittel::euro((int) $wmV['preis_cent'])) ?></td></tr>
          <?php endforeach; ?>
        </table>
        <?php if (!$wmNurLesen && $wmP['vorlage'] === 'visitenkarte'):
          $wmMeldung = (string) ($_GET['wm'] ?? '');
          $wmWahlText = static fn(array $w): string => (PartnerKarten::STILE[$w['stil'] ?? ''][$sprache] ?? ($w['stil'] ?? ''))
              . ' · ' . (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'][$w['sprache'] ?? ''] ?? '')
              . ' · ' . PartnerKarten::kontakt($p, (string) ($w['kontakt'] ?? 'email')); ?>
          <?php if (in_array($wmMeldung, ['entwurf', 'frei', 'veraltet', 'zuviel', 'fehler'], true)): ?>
            <p class="wm-meldung<?= in_array($wmMeldung, ['entwurf', 'frei'], true) ? ' gut' : '' ?>" role="status"><?= $h($W('m_' . $wmMeldung)) ?></p>
          <?php endif; ?>
          <?php if ($wmSt['freigegeben']): $wmF = $wmSt['freigegeben']; ?>
            <div class="wm-frei">
              <b>✓ <?= $h(strtr($W('frei_titel'), ['{datum}' => Fmt::datum((string) $wmF['freigegeben_am'])])) ?></b>
              <span><?= $h($wmWahlText($wmF['wahl'])) ?></span>
              <a href="<?= $h($selbst(['wmpdf' => (int) $wmF['id']])) ?>" target="_blank" rel="noopener"><?= $h($W('pdf_ansehen')) ?></a>
              <span class="wm-meta" style="margin:0"><?= $h($W('frei_satz')) ?></span>
            </div>
          <?php endif; ?>
          <?php if ($wmSt['entwurf']): $wmE = $wmSt['entwurf']; ?>
            <form class="wm-entwurf" method="post" action="<?= $h($selbst()) ?>#wm-p<?= (int) $wmP['id'] ?>">
              <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>"><input type="hidden" name="tat" value="wm_freigeben">
              <input type="hidden" name="produkt" value="<?= (int) $wmP['id'] ?>"><input type="hidden" name="entwurf" value="<?= (int) $wmE['id'] ?>">
              <input type="hidden" name="hash" value="<?= $h((string) $wmE['datei_hash']) ?>">
              <b><?= $h($W('entwurf_titel')) ?></b>
              <span><?= $h($wmWahlText($wmE['wahl'])) ?></span>
              <a class="knopf" href="<?= $h($selbst(['wmpdf' => (int) $wmE['id']])) ?>" target="_blank" rel="noopener"><?= $h($W('pdf_ansehen')) ?></a>
              <label class="wm-haken"><input type="checkbox" name="geprueft" value="1" required> <span><?= $h($W('pruef_haken')) ?></span></label>
              <button class="knopf haupt"><?= $h($W('freigeben')) ?></button>
            </form>
          <?php endif; ?>
          <details class="wm-gestalten"<?= !$wmSt['entwurf'] && !$wmSt['freigegeben'] ? ' open' : '' ?>>
            <summary><?= $h($W('gestalten')) ?></summary>
            <form method="post" action="<?= $h($selbst()) ?>#wm-p<?= (int) $wmP['id'] ?>">
              <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>"><input type="hidden" name="tat" value="wm_entwurf">
              <input type="hidden" name="produkt" value="<?= (int) $wmP['id'] ?>">
              <fieldset><legend><?= $h($W('stil')) ?></legend>
                <?php foreach (PartnerKarten::STILE as $wmS => $wmSn): if (!PartnerKarten::gibt($wmS)) { continue; } ?>
                  <label><input type="radio" name="stil" value="<?= $h($wmS) ?>"<?= $wmS === $wmJetzt['stil'] ? ' checked' : '' ?>> <?= $h($wmSn[$sprache] ?? $wmSn['de']) ?></label>
                <?php endforeach; ?>
              </fieldset>
              <fieldset><legend><?= $h(Texte::h(Texte::PARTNER['vk_sprache'] ?? [], $sprache)) ?></legend>
                <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $wmL => $wmLn): ?>
                  <label><input type="radio" name="sprache" value="<?= $wmL ?>"<?= $wmL === $wmJetzt['sprache'] ? ' checked' : '' ?>> <?= $wmLn ?></label>
                <?php endforeach; ?>
              </fieldset>
              <fieldset><legend><?= $h(Texte::h(Texte::PARTNER['vk_kontakt'] ?? [], $sprache)) ?></legend>
                <?php foreach (PartnerKarten::KONTAKTE as $wmKo): ?>
                  <label><input type="radio" name="kontakt" value="<?= $wmKo ?>"<?= $wmKo === $wmJetzt['kontakt'] ? ' checked' : '' ?>> <?= $h(PartnerKarten::kontakt($p, $wmKo)) ?></label>
                <?php endforeach; ?>
              </fieldset>
              <button class="knopf<?= $wmSt['entwurf'] || $wmSt['freigegeben'] ? '' : ' haupt' ?>"><?= $h($W('erzeugen')) ?></button>
            </form>
          </details>
        <?php endif; ?>
        <div class="wm-bald"><span aria-hidden="true">⏳</span><span><b><?= $h($W('bald')) ?></b><?= $h($W('bald_satz')) ?></span></div>
      </article>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>

<div class="block pt" id="werbemittel-kit" data-reiter="werbemittel">
  <h2><?= $h($W('kit')) ?></h2>
  <p class="lead"><?= $h($W('kit_satz')) ?></p>
  <div class="wm-kit">
    <?php foreach (['werbung', 'medien', 'branchen', 'gutschein'] as $wmZiel): ?>
      <?php if ($wmNurLesen): /* In der Verwaltung gibt es die Ziele nicht. */ ?><span class="knopf stumm"><?= $h($W('kit_' . $wmZiel)) ?></span>
      <?php else: ?><a class="knopf" href="#<?= $wmZiel ?>"><?= $h($W('kit_' . $wmZiel)) ?></a><?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
