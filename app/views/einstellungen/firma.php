<div class="block">
  <h2>Firmendaten</h2>
  <p style="color:var(--dim);font-size:13.5px;margin:8px 0 16px;line-height:1.6">
    Das, was oben auf jedem Beleg steht. Solange <b>keine Partita IVA</b> eingetragen ist, stellt die
    Verwaltung <b>Zahlungsbelege</b> aus — keine Rechnungen im steuerlichen Sinn. Sobald du die Nummer
    hier einträgst, heißen die Dokumente Rechnung und bekommen einen eigenen Nummernkreis ab 1.
  </p>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="firma_speichern">
    <input type="hidden" name="zurueck" value="einstellungen?b=firma">
    <div class="zwei">
      <div>
        <div class="feld"><label>Firma</label><input name="firma_name" value="<?= Fmt::h((string) ($firma['firma_name'] ?? '')) ?>"></div>
        <div class="feld"><label>Inhaber</label><input name="firma_inhaber" value="<?= Fmt::h((string) ($firma['firma_inhaber'] ?? '')) ?>"></div>
        <div class="feld"><label>Straße und Hausnummer</label><input name="firma_strasse" value="<?= Fmt::h((string) ($firma['firma_strasse'] ?? '')) ?>"></div>
        <div style="display:flex;gap:10px">
          <div class="feld" style="flex:0 0 110px"><label>PLZ</label><input name="firma_plz" value="<?= Fmt::h((string) ($firma['firma_plz'] ?? '')) ?>"></div>
          <div class="feld" style="flex:1"><label>Ort</label><input name="firma_ort" value="<?= Fmt::h((string) ($firma['firma_ort'] ?? '')) ?>"></div>
        </div>
        <div class="feld"><label>Land</label><input name="firma_land" value="<?= Fmt::h((string) ($firma['firma_land'] ?? '')) ?>"></div>
      </div>
      <div>
        <div class="feld"><label>E-Mail</label><input name="firma_email" value="<?= Fmt::h((string) ($firma['firma_email'] ?? '')) ?>"></div>
        <div class="feld"><label>Telefon</label><input name="firma_telefon" value="<?= Fmt::h((string) ($firma['firma_telefon'] ?? '')) ?>"></div>
        <div class="feld"><label>Website</label><input name="firma_web" value="<?= Fmt::h((string) ($firma['firma_web'] ?? '')) ?>"></div>
        <div class="feld"><label>Bank</label><input name="firma_bank" value="<?= Fmt::h((string) ($firma['firma_bank'] ?? '')) ?>"></div>
        <div class="feld"><label>IBAN</label><input name="firma_iban" value="<?= Fmt::h((string) ($firma['firma_iban'] ?? '')) ?>"></div>
      </div>
    </div>

    <div class="zwei" style="margin-top:4px">
      <div>
        <div class="feld"><label>Partita IVA</label>
          <input name="firma_piva" placeholder="noch keine" value="<?= Fmt::h((string) ($firma['firma_piva'] ?? '')) ?>">
          <small style="color:var(--leise);font-size:12px">Leer lassen, solange du keine hast — dann bleiben es Belege.</small></div>
      </div>
      <div>
        <div class="feld"><label>Codice fiscale</label><input name="firma_steuernr" value="<?= Fmt::h((string) ($firma['firma_steuernr'] ?? '')) ?>"></div>
      </div>
    </div>
    <div class="zwei" style="margin-top:4px">
      <div>
        <div class="feld"><label>Steuerregime</label>
          <?php $reg = (string) ($firma['firma_regime'] ?? 'normal'); ?>
          <select name="firma_regime">
            <option value="normal" <?= $reg !== 'forfettario' ? 'selected' : '' ?>>Normal — IVA wird ausgewiesen</option>
            <option value="forfettario" <?= $reg === 'forfettario' ? 'selected' : '' ?>>Regime forfettario — keine IVA</option>
          </select>
          <small style="color:var(--leise);font-size:12px">Wirkt erst mit einer Partita IVA. Im forfettario steht der
            gesetzliche Hinweis nach L. 190/2014 automatisch auf der Rechnung, und ab 77,47 € der Vermerk zur Marca da bollo.</small></div>
      </div>
      <div>
        <div class="feld"><label>Mehrwertsteuersatz in Prozent</label>
          <input name="firma_mwst" style="max-width:140px" value="<?= Fmt::h((string) ($firma['firma_mwst'] ?? '0')) ?>">
          <small style="color:var(--leise);font-size:12px">Die Preise auf der Website gelten als Endpreise — die Steuer
            wird herausgerechnet, nicht aufgeschlagen.</small></div>
      </div>
    </div>

    <?php
      $hatPiva = trim((string) ($firma['firma_piva'] ?? '')) !== '';
      $satzEin = (float) str_replace(',', '.', (string) ($firma['firma_mwst'] ?? '0'));
    ?>
    <?php if (!$hatPiva): ?>
      <div class="hinweis<?= $satzEin > 0 ? ' warnung' : '' ?>" style="margin:14px 0 4px">
        <b>Solange keine Partita IVA hier steht</b>, heißen die Dokumente <b>Zahlungsbeleg</b> (BE-…), es wird
        <b>keine Steuer ausgewiesen</b> — auch dann nicht, wenn oben ein Satz eingetragen ist —, und unten steht
        der Satz „keine Rechnung im steuerlichen Sinn".
        <?php if ($satzEin > 0): ?>
          <br><br>Gerade stehen <b><?= Fmt::h(rtrim(rtrim(number_format($satzEin, 2, ',', '.'), '0'), ',')) ?> %</b> im
          Feld, ohne dass sie irgendwo wirken. Das ist in Ordnung als Vormerkung — sobald du die Nummer einträgst,
          greift der Satz sofort und aus den Belegen werden Rechnungen (RE-…) mit eigener Nummernreihe ab 0001.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="hinweis gut" style="margin:14px 0 4px">
        <b>Partita IVA hinterlegt.</b> Die Dokumente heißen <b>Rechnung</b> (RE-…)
        <?= (string) ($firma['firma_regime'] ?? 'normal') === 'forfettario'
            ? 'und tragen den Hinweis nach L. 190/2014, ohne IVA.'
            : 'und weisen die Steuer aus.' ?>
      </div>
    <?php endif; ?>
    <div class="feld"><label>Hinweis auf dem Beleg</label>
      <textarea name="firma_hinweis" rows="3" placeholder="Den genauen Wortlaut gibt dir dein Commercialista."><?= Fmt::h((string) ($firma['firma_hinweis'] ?? '')) ?></textarea></div>
    <button class="knopf haupt">Firmendaten speichern</button>
  </form>
</div>
