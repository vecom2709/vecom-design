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
require_once dirname(__DIR__) . '/src/WmBestellung.php';
$wmZahlweg = WmBestellung::zahlweg();
$wmAdressen = !$wmNurLesen && (int) ($p['id'] ?? 0) > 0 ? WmBestellung::adressen((int) $p['id']) : [];
$wmBestellungen = !$wmNurLesen && (int) ($p['id'] ?? 0) > 0 ? WmBestellung::fuerPartner((int) $p['id']) : [];
$wmLaender = ['IT' => 'Italia', 'DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz / Svizzera', 'FR' => 'France', 'ES' => 'España',
    'NL' => 'Nederland', 'BE' => 'België / Belgique', 'LU' => 'Luxembourg', 'PT' => 'Portugal', 'MT' => 'Malta', 'SM' => 'San Marino'];
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
  .wm-bestellen{border-top:1px solid var(--linie);padding-top:12px;display:grid;gap:10px}
  .wm-bestellen h4{margin:0;font-size:15px}
  .wm-bfeld{border:0;padding:0;margin:0;display:grid;gap:10px;min-width:0}
  .wm-wahl{display:grid;gap:6px}
  .wm-wahl label{display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid var(--linie2);border-radius:12px;cursor:pointer;font-size:14.5px}
  .wm-wahl label span{flex:1}
  .wm-wahl label:has(input:checked),.wm-adr:has(input:checked){border-color:rgba(241,211,139,.7);background:rgba(241,211,139,.07)}
  .wm-unter{margin:4px 0 0;font-size:12.5px;color:var(--leise)}
  .wm-adr{display:flex;gap:10px;align-items:flex-start;padding:9px 12px;border:1px solid var(--linie2);border-radius:12px;font-size:13.5px;cursor:pointer;color:var(--dim)}
  .wm-neu{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .wm-neu label{display:grid;gap:4px;font-size:12.5px;color:var(--leise);min-width:0}
  .wm-neu input,.wm-neu select{width:100%;box-sizing:border-box;min-height:40px;font:inherit;font-size:15px}
  .wm-neu .wm-breit{grid-column:1 / -1}
  .wm-bfeld:has(input[name=adresse][value="neu"]:not(:checked)) .wm-neu[data-nur-neu]{display:none}
  .wm-best{border:1px solid var(--linie);border-radius:12px;padding:12px;display:grid;gap:6px;margin-bottom:10px}
  .wm-best-kopf,.wm-best-fuss{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;font-size:14px}
  .wm-status{font-size:12.5px;padding:3px 10px;border-radius:999px;border:1px solid var(--linie2);color:var(--dim)}
  .wm-s-bezahlt,.wm-s-beim_drucker{border-color:rgba(241,211,139,.6);color:#f1d38b}
  .wm-s-versendet{border-color:rgba(120,200,140,.55);color:#9fe0b0}
  .wm-s-storniert{opacity:.7}
  @media (max-width:520px){ .wm-neu{grid-template-columns:1fr} }
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
        <?php /* Phase 3: bestellen — nur mit freigegebener Druckdatei. In der
                 Verwaltungsvorschau steht das Formular gesperrt da. */
          $wmDarf = $wmNurLesen || !empty($wmSt['freigegeben']);
          $wmM = (string) ($_GET['wm'] ?? ''); ?>
        <div class="wm-bestellen" id="wm-bestellen">
          <h4><?= $h($W('bestellen')) ?></h4>
          <?php if (!$wmNurLesen && in_array($wmM, ['adresse', 'freigabe_fehlt', 'zuviel_offen', 'nicht_verfuegbar'], true)): ?>
            <p class="wm-meldung" role="alert"><?= $h($W('m_' . $wmM)) ?></p>
          <?php endif; ?>
          <?php if (!$wmDarf): ?>
            <p class="wm-meta" style="margin:0"><?= $h($W('erst_freigeben')) ?></p>
          <?php else: ?>
          <form<?= $wmNurLesen ? '' : ' method="post" action="' . $h($selbst()) . '"' ?>>
            <fieldset class="wm-bfeld"<?= $wmNurLesen ? ' disabled' : '' ?>>
              <?php if (!$wmNurLesen): ?><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>"><input type="hidden" name="tat" value="wm_bestellen"><?php endif; ?>
              <div class="wm-wahl" role="radiogroup" aria-label="<?= $h($W('auflage')) ?>">
                <?php foreach ($wmP['varianten'] as $wmI => $wmV): ?>
                  <label><input type="radio" name="variante" value="<?= (int) $wmV['id'] ?>"<?= $wmI === 0 ? ' checked' : '' ?> required>
                    <span><?= $h($wmV['name']) ?></span><b><?= $h(Werbemittel::euro((int) $wmV['preis_cent'])) ?></b></label>
                <?php endforeach; ?>
              </div>
              <p class="wm-unter"><?= $h($W('lieferadresse')) ?></p>
              <?php foreach ($wmAdressen as $wmAi => $wmA): ?>
                <label class="wm-adr"><input type="radio" name="adresse" value="<?= (int) $wmA['id'] ?>"<?= $wmAi === 0 ? ' checked' : '' ?>>
                  <span><?= $h($wmA['name'] . ($wmA['firma'] !== '' ? ' · ' . $wmA['firma'] : '') . ', ' . $wmA['strasse'] . ', ' . $wmA['plz'] . ' ' . $wmA['ort'] . ' (' . $wmA['land'] . ')') ?></span></label>
              <?php endforeach; ?>
              <?php if ($wmAdressen): ?><label class="wm-adr"><input type="radio" name="adresse" value="neu"> <span><?= $h($W('neue_adresse')) ?></span></label><?php else: ?><input type="hidden" name="adresse" value="neu"><?php endif; ?>
              <div class="wm-neu"<?= $wmAdressen ? ' data-nur-neu="1"' : '' ?>>
                <label><?= $h($W('a_name')) ?><input name="name" autocomplete="name" value="<?= $h($wmAdressen ? '' : (string) ($p['name'] ?? '')) ?>"></label>
                <label><?= $h($W('a_firma')) ?><input name="firma" autocomplete="organization"></label>
                <label class="wm-breit"><?= $h($W('a_strasse')) ?><input name="strasse" autocomplete="street-address"></label>
                <label><?= $h($W('a_plz')) ?><input name="plz" autocomplete="postal-code" inputmode="numeric"></label>
                <label><?= $h($W('a_ort')) ?><input name="ort" autocomplete="address-level2"></label>
                <label><?= $h($W('a_land')) ?><select name="land" autocomplete="country">
                  <?php foreach ($wmLaender as $wmLc => $wmLn): ?><option value="<?= $wmLc ?>"<?= $wmLc === 'IT' ? ' selected' : '' ?>><?= $h($wmLn) ?></option><?php endforeach; ?>
                </select></label>
                <label><?= $h($W('a_telefon')) ?><input name="telefon" type="tel" autocomplete="tel"></label>
              </div>
              <label class="wm-haken"><input type="checkbox" name="verbindlich" value="1" required> <span><?= $h($W('verbindlich')) ?></span></label>
              <button class="knopf haupt"><?= $h($W($wmZahlweg === 'stripe' ? 'knopf_stripe' : 'knopf_anfrage')) ?></button>
              <p class="wm-meta" style="margin:0"><?= $h($W($wmZahlweg === 'stripe' ? 'stripe_satz' : 'anfrage_satz')) ?></p>
            </fieldset>
          </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>

<?php /* Phase 3: Meine Bestellungen */ $wmM = (string) ($_GET['wm'] ?? ''); if (!$wmNurLesen && ($wmBestellungen || in_array($wmM, ['angefragt', 'danke', 'abgebrochen', 'stripe'], true))): ?>
<div class="block pt" id="wm-bestellungen" data-reiter="werbemittel">
  <h2><?= $h($W('meine')) ?></h2>
  <?php if (in_array($wmM, ['angefragt', 'danke', 'abgebrochen', 'stripe'], true)): ?>
    <p class="wm-meldung<?= in_array($wmM, ['angefragt', 'danke'], true) ? ' gut' : '' ?>" role="status"><?= $h($W('m_' . $wmM)) ?></p>
  <?php endif; ?>
  <?php foreach ($wmBestellungen as $wmB): $wmPos = $wmB['positionen'][0] ?? null; ?>
    <div class="wm-best">
      <div class="wm-best-kopf"><b><?= $h($wmB['nummer']) ?></b><span class="wm-status wm-s-<?= $h($wmB['status']) ?>"><?= $h($W('s_' . $wmB['status'])) ?></span></div>
      <div class="wm-meta" style="margin:0"><?= $h(Fmt::datum((string) $wmB['created_at'])) ?><?php if ($wmPos): ?> · <?= $h($wmPos['name'] . ' · ' . $wmPos['variante']) ?><?php endif; ?></div>
      <div class="wm-best-fuss"><span><?= $h($W('summe')) ?> <b><?= $h(Werbemittel::euro((int) $wmB['summe_cent'])) ?></b></span>
        <?php if ($wmB['status'] === 'versendet' && !empty($wmB['tracking'])): ?>
          <?php if (!empty($wmB['tracking_url'])): ?><a href="<?= $h((string) $wmB['tracking_url']) ?>" target="_blank" rel="noopener"><?= $h($W('sendung')) ?> · <?= $h((string) $wmB['tracking']) ?></a>
          <?php else: ?><span><?= $h($W('sendung')) ?>: <?= $h((string) $wmB['tracking']) ?></span><?php endif; ?>
        <?php endif; ?>
        <?php if (in_array($wmB['status'], ['angefragt', 'offen'], true) && $wmZahlweg === 'stripe'): ?>
          <form method="post" action="<?= $h($selbst()) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
            <input type="hidden" name="tat" value="wm_bezahlen"><input type="hidden" name="bestellung" value="<?= (int) $wmB['id'] ?>">
            <button class="knopf haupt"><?= $h($W('jetzt_bezahlen')) ?></button></form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

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
