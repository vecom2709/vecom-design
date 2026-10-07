<?php
/* Akquise › Betrieb › Profil & Notizen (Akquise-CRM Modul A, 06.10.2026).
   Eingebunden aus akquise_firma.php: $f, $fid, $crm (kanaele, notizen, kontakte, hauptsitz, filialen), $post.
   Was niemand eingetragen hat, steht als „—“ da — nichts wird geraten. */
require_once dirname(__DIR__) . '/src/AkquiseMail.php';
$v = static fn(string $k): string => Fmt::h((string) ($f[$k] ?? ''));
$feld = static function (string $name, string $label, string $typ = 'text', string $hilfe = '') use ($f): string {
    $wert = (string) ($f[$name] ?? '');
    if ($name === 'emails_weitere') { $wert = implode(', ', (array) (json_decode($wert, true) ?: [])); }
    return '<div class="feld"><label for="pf-' . $name . '">' . Fmt::h($label) . '</label><input id="pf-' . $name . '" name="' . $name . '" type="' . $typ . '" value="' . Fmt::h($wert) . '"'
        . ($hilfe !== '' ? ' placeholder="' . Fmt::h($hilfe) . '"' : '') . '></div>';
};
$zustandWort = ['offen' => ['noch nicht benutzt', ''], 'benutzt' => ['benutzt', 'warnung'], 'antwort' => ['Antwort erhalten', 'gut'], 'pausiert' => ['pausiert', 'warnung'],
                'gesperrt' => ['gesperrt', 'schlecht'], 'kein_weg' => ['kein Kontaktweg', '']];
$sichtWort = ['ich' => 'nur für mich', 'admin' => 'nur Vecom', 'team' => 'Team (auch der zuständige Partner)'];
$freigabe = trim((string) ($f['einwilligung'] ?? ''));
if ($freigabe === '' && (int) ($f['email_send_allowed'] ?? 0) === 1) {   // Versandgrund aus dem Kommunikationsstatus E-Mail
    $freigabe = 'E-Mail — ' . (AkquiseMail::GRUENDE[(string) ($f['email_legal_basis'] ?? '')][0] ?? 'Versandgrund dokumentiert');
}
$hart = in_array((string) ($f['sperr_art'] ?? ''), AkquiseCrm::SPERR_HART, true);
?>
<style>
  .pf-gitter{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:2px 14px}
  .pf-gitter .feld{margin:0 0 10px}
  .pf-kat{font-size:var(--fs-mini);text-transform:uppercase;letter-spacing:.07em;color:var(--leise);margin:16px 0 8px;grid-column:1/-1}
  .pf-quelle{display:flex;gap:6px 16px;flex-wrap:wrap;font-size:var(--fs-klein);color:var(--dim);padding:10px 12px;border:1px dashed var(--linie2);border-radius:10px;margin:0 0 14px}
  .pf-quelle b{color:var(--text);font-weight:600}
  .pf-kanaele{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px}
  .pf-kanal{border:1px solid var(--linie);border-radius:12px;padding:10px 12px;background:var(--flaeche2);display:flex;flex-direction:column;gap:6px;font-size:var(--fs-klein)}
  .pf-kanal b{font-size:14px}
  .pf-kanal form{margin:0;display:flex;gap:6px;flex-wrap:wrap;align-items:center}
  .pf-kanal input[type=date]{width:auto;padding:4px 8px;min-height:0;font-size:var(--fs-klein)}
  .pf-kanal-mehr summary{cursor:pointer;font-size:var(--fs-klein);color:var(--cyan)}
  .pf-kanal-mehr[open]{display:flex;flex-direction:column;gap:6px}
  .pf-kanal-mehr[open] summary{margin-bottom:2px}
  .pf-notiz{border:1px solid var(--linie);border-radius:12px;padding:10px 12px;margin:0 0 8px;background:var(--flaeche2)}
  .pf-notiz.an{border-color:rgba(241,211,139,.5)}
  .pf-notiz p{margin:0 0 6px;white-space:pre-wrap;font-size:14px;line-height:1.5}
  .pf-notiz .unter{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:var(--fs-klein);color:var(--leise)}
  .pf-notiz form{margin:0;display:inline}
</style>

<div class="zwei">
<div>
  <div class="block" id="stammdaten">
    <h2>Profil</h2>
    <div class="pf-quelle" id="quelle">
      <span>Quelle: <b><?= Fmt::h(AkquiseCrm::quelle($f)) ?></b></span>
      <span>Link: <?php if (!empty($f['quelle_url'])): ?><a href="<?= $v('quelle_url') ?>" target="_blank" rel="noopener nofollow">öffnen</a><?php else: ?>—<?php endif; ?></span>
      <span>gefunden am <b><?= Fmt::h(Fmt::datum((string) $f['recherchiert_am'])) ?></b><?= !empty($f['gefunden_von']) ? ' von <b>' . $v('gefunden_von') . '</b>' : '' ?></span>
      <span>zuletzt geändert <?= Fmt::h(Fmt::datum((string) $f['updated_at'])) ?></span>
      <span>Kennung <?= $v('kennung') ?></span>
    </div>
    <?= $post('akq_profil_speichern', '
      <div class="pf-gitter">
        <div class="pf-kat">Betrieb</div>' . $feld('name', 'Firmenname') . $feld('rechtsform', 'Rechtsform', 'text', 'z. B. S.r.l., GmbH') .
        '<div class="feld"><label for="pf-branche">Branche</label><select id="pf-branche" name="branche">' . implode('', array_map(static fn($k, $b) => '<option value="' . Fmt::h($k) . '"' . ((string) $f['branche'] === $k ? ' selected' : '') . '>' . Fmt::h(Akquise::branchenName($k)) . '</option>', array_keys(Akquise::branchen()), Akquise::branchen())) . '</select></div>' .
        $feld('unterbranche', 'Unterbranche') . $feld('adresse', 'Adresse') . $feld('plz', 'PLZ') . $feld('stadt', 'Ort') . $feld('kreis', 'Provinz / Landkreis') . $feld('region', 'Region') .
        $feld('filiale_von', 'Filiale von (Nr. des Hauptsitzes)', 'number', 'leer = Hauptsitz') .
        '<div class="pf-kat">Ansprechpartner &amp; Kontakt</div>' . $feld('ansprechpartner', 'Ansprechpartner') . $feld('position', 'Position') . $feld('email', 'E-Mail', 'email') .
        $feld('emails_weitere', 'Weitere E-Mails', 'text', 'durch Komma getrennt') . $feld('telefon', 'Telefon', 'tel') . $feld('mobil', 'Mobil', 'tel') . $feld('whatsapp', 'WhatsApp-Nummer', 'tel') .
        '<div class="pf-kat">Online</div>' . $feld('url', 'Website', 'url', 'https://…') . $feld('google_profil', 'Google-Unternehmensprofil', 'url', 'https://…') . $feld('facebook', 'Facebook', 'url', 'https://…') .
        $feld('instagram', 'Instagram', 'url', 'https://…') . $feld('linkedin', 'LinkedIn', 'url', 'https://…') . $feld('xing', 'Xing', 'url', 'https://…') .
        '<div class="pf-kat">Quelle &amp; nächster Schritt</div>
        <div class="feld"><label for="pf-quelle_art">Quelle</label><select id="pf-quelle_art" name="quelle_art"><option value="">— (aus der Suche)</option>' . implode('', array_map(static fn($k, $w) => '<option value="' . $k . '"' . (($f['quelle_art'] ?? '') === $k ? ' selected' : '') . '>' . Fmt::h($w) . '</option>', array_keys(AkquiseCrm::QUELLEN), AkquiseCrm::QUELLEN)) . '</select></div>' .
        $feld('quelle_url', 'Original-Link der Quelle', 'url', 'https://…') . $feld('gefunden_von', 'Gefunden von') . $feld('naechster_schritt', 'Nächster Schritt', 'text', 'z. B. Chef vormittags anrufen') . $feld('naechster_am', 'am', 'date') . '
      </div>
      <input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil">
      <button class="knopf haupt">Profil speichern</button>') ?>
  </div>

  <div class="block" id="kontakte">
    <h2>Weitere Ansprechpartner</h2>
    <?php if (!$crm['kontakte']): ?><div class="leer">Nur der oben eingetragene.</div><?php endif; ?>
    <?php foreach ($crm['kontakte'] as $k): ?>
      <div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap;padding:8px 0;border-top:1px solid var(--linie);font-size:14px">
        <b><?= Fmt::h((string) $k['name']) ?></b><span class="akq-klein"><?= Fmt::h(implode(' · ', array_filter([(string) $k['position'], (string) $k['email'], (string) $k['telefon'], (string) $k['notiz']]))) ?></span>
        <?= $post('akq_kontakt_weg', '<input type="hidden" name="kontakt" value="' . (int) $k['id'] . '"><input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#kontakte"><button class="knopf klein">Entfernen</button>', ' style="margin-left:auto"') ?>
      </div>
    <?php endforeach; ?>
    <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13.5px;color:var(--cyan)">Ansprechpartner hinzufügen</summary>
      <?= $post('akq_kontakt_neu', '<div class="pf-gitter" style="margin-top:10px">' . '<div class="feld"><label>Name</label><input name="name" required minlength="2" maxlength="120"></div>
        <div class="feld"><label>Position</label><input name="position" maxlength="80"></div><div class="feld"><label>E-Mail</label><input name="email" type="email" maxlength="190"></div>
        <div class="feld"><label>Telefon</label><input name="telefon" maxlength="40"></div><div class="feld"><label>Notiz</label><input name="notiz" maxlength="255"></div></div>
        <input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#kontakte"><button class="knopf">Hinzufügen</button>') ?>
    </details>
  </div>
</div>

<div>
  <div class="block" id="kanaele">
    <h2>Kanäle</h2>
    <p class="akq-klein" style="margin:-4px 0 10px">
      <?= $freigabe !== '' ? 'Dokumentierte Kommunikationsfreigabe: ' . Fmt::h($freigabe) . (($f['einwilligung_kanaele'] ?? '') !== '' ? ' (Kanäle: ' . Fmt::h((string) $f['einwilligung_kanaele']) . ')' : '') . '.'
                            : 'Keine dokumentierte Kommunikationsfreigabe vorhanden.' ?>
      Ob ein Kanal genutzt werden darf, entscheiden die Regeln unter „Regeln &amp; Versand“ — hier steht nur, was war.</p>
    <?php /* Kanäle ohne Kontaktweg stehen nur als Zeile da: acht Karten mit je drei Knöpfen waren mehr Lärm als Auskunft. */
      $ohneWeg = array_filter($crm['kanaele'], static fn($ka) => $ka['zustand'] === 'kein_weg' && empty($ka['naechster']));
      $mitWeg = array_diff_key($crm['kanaele'], $ohneWeg); ?>
    <div class="pf-kanaele">
      <?php foreach ($mitWeg as $kk => $ka): [$zw, $zt] = $zustandWort[$ka['zustand']] ?? [$ka['zustand'], '']; ?>
        <div class="pf-kanal" id="kanal-<?= $kk ?>">
          <div style="display:flex;justify-content:space-between;gap:6px;align-items:center"><b><?= Fmt::h($ka['wort']) ?></b><span class="marke2 <?= $zt ?>"><?= Fmt::h($zw) ?></span></div>
          <?php if (!empty($ka['mailstatus'])): ?><a class="akq-klein" href="<?= Fmt::h(url('akquise/' . $fid)) ?>#mailstatus"><?= $ka['mailstatus'][0] . ' ' . Fmt::h($ka['mailstatus'][2]) ?></a><?php endif; ?>
          <span class="akq-klein">Letzter Kontakt: <?= $ka['letzter'] ? Fmt::h(date('d.m.Y', strtotime((string) $ka['letzter']))) : '—' ?> · nächster: <?= $ka['naechster'] ? Fmt::h(date('d.m.Y', strtotime((string) $ka['naechster']))) : '—' ?></span>
          <?php if ($ka['zustand'] !== 'gesperrt'): ?>
          <details class="pf-kanal-mehr"><summary>Ändern</summary>
            <?= $post('akq_kanal', '<input type="hidden" name="kanal" value="' . $kk . '"><input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#kanaele">'
              . '<button class="knopf klein" name="was" value="' . ($ka['zustand'] === 'pausiert' ? 'fortsetzen' : 'pausieren') . '">' . ($ka['zustand'] === 'pausiert' ? 'Fortsetzen' : 'Pausieren') . '</button>'
              . (in_array($kk, ['linkedin', 'xing', 'formular', 'besuch', 'telefon'], true) ? '<button class="knopf klein" name="was" value="benutzt">Kontakt vermerken</button>' : '')) ?>
            <?= $post('akq_kanal', '<input type="hidden" name="kanal" value="' . $kk . '"><input type="hidden" name="was" value="naechster"><input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#kanaele">'
              . '<input type="date" name="datum" value="' . Fmt::h((string) ($ka['naechster'] ?? '')) . '" aria-label="Nächster Kontakt über ' . Fmt::h($ka['wort']) . '"><button class="knopf klein">Nächsten Kontakt setzen</button>') ?>
          </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($ohneWeg): ?><p class="akq-klein" id="kanaele-ohne" style="margin:10px 0 0">Ohne Kontaktweg: <?= Fmt::h(implode(' · ', array_column($ohneWeg, 'wort'))) ?> — erst Nummer oder Profil oben eintragen.</p><?php endif; ?>
    </div>
  </div>

  <div class="block" id="notizen">
    <h2>Notizen</h2>
    <?= $post('akq_notiz_neu', '<div class="feld"><label for="pf-notiz">Neue Notiz</label><textarea id="pf-notiz" name="text" rows="3" maxlength="2000" required placeholder="z. B. Chef vormittags erreichbar"></textarea></div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 12px"><select name="sichtbar" style="width:auto">'
      . implode('', array_map(static fn($k, $w) => '<option value="' . $k . '"' . ($k === 'team' ? ' selected' : '') . '>' . Fmt::h($w) . '</option>', array_keys($sichtWort), $sichtWort))
      . '</select><label class="akq-haken" style="margin:0"><input type="checkbox" name="angeheftet" value="1"> anheften</label>
      <input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#notizen"><button class="knopf">Notiz speichern</button></div>') ?>
    <?php if (!$crm['notizen'] && trim((string) ($f['notiz'] ?? '')) === ''): ?><div class="leer">Noch keine Notizen.</div><?php endif; ?>
    <?php foreach ($crm['notizen'] as $n): ?>
      <div class="pf-notiz<?= (int) $n['angeheftet'] === 1 ? ' an' : '' ?>">
        <p><?= Fmt::h((string) $n['text']) ?></p>
        <div class="unter"><?= (int) $n['angeheftet'] === 1 ? '📌 ' : '' ?><?= Fmt::h((string) $n['autor']) ?> · <?= Fmt::h(date('d.m.Y H:i', strtotime((string) $n['created_at']))) ?> · <span class="marke2"><?= Fmt::h($sichtWort[$n['sichtbar']] ?? $n['sichtbar']) ?></span>
          <?php if ($n['partner_id'] === null): ?>
            <?= $post('akq_notiz_aendern', '<input type="hidden" name="notiz" value="' . (int) $n['id'] . '"><input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#notizen"><button class="knopf klein" name="was" value="heften">' . ((int) $n['angeheftet'] === 1 ? 'Lösen' : 'Anheften') . '</button><button class="knopf klein" name="was" value="loeschen">Löschen</button>') ?>
          <?php endif; ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (trim((string) ($f['notiz'] ?? '')) !== ''): ?>
      <div class="pf-notiz"><p><?= Fmt::h((string) $f['notiz']) ?></p><div class="unter">Ältere Notiz (Stammdaten)</div></div>
    <?php endif; ?>
  </div>

  <?php if ($crm['hauptsitz'] || $crm['filialen']): ?>
  <div class="block" id="standorte">
    <h2>Standorte</h2>
    <?php if ($crm['hauptsitz']): ?><p style="margin:0 0 6px;font-size:14px">Filiale von <a href="<?= Fmt::h(url('akquise/' . (int) $crm['hauptsitz']['id'])) ?>"><?= Fmt::h((string) $crm['hauptsitz']['name']) ?></a> (<?= Fmt::h((string) $crm['hauptsitz']['stadt']) ?>)</p><?php endif; ?>
    <?php foreach ($crm['filialen'] as $fl): ?><p style="margin:0 0 4px;font-size:14px">Filiale: <a href="<?= Fmt::h(url('akquise/' . (int) $fl['id'])) ?>"><?= Fmt::h((string) $fl['name']) ?></a> (<?= Fmt::h((string) $fl['stadt']) ?>)</p><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php /* Modul H: Kampagnen dieses Betriebs — dazunehmen und herausnehmen. */
    require_once dirname(__DIR__) . '/src/AkquiseKampagne.php';
    $pfKa = AkquiseKampagne::vonFirma($fid); $pfKaIds = array_map('intval', array_column($pfKa, 'id'));
    $pfKaFrei = array_filter(AkquiseKampagne::liste(true), static fn($k) => !in_array((int) $k['id'], $pfKaIds, true)); ?>
  <?php if ($pfKa || $pfKaFrei): ?>
  <div class="block" id="kampagnen">
    <h2>Kampagnen</h2>
    <?php foreach ($pfKa as $k): ?>
      <div style="display:flex;gap:8px;align-items:center;margin:0 0 6px;font-size:14px"><a href="<?= Fmt::h(url('akquise/kampagnen/' . (int) $k['id'])) ?>"><?= Fmt::h((string) $k['name']) ?></a>
        <?= $k['status'] !== 'aktiv' ? '<span class="akq-klein">beendet</span>' : '' ?>
        <?= $post('akq_kampagne_weg', '<input type="hidden" name="kampagne" value="' . (int) $k['id'] . '"><input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#kampagnen"><button class="knopf klein">Herausnehmen</button>', ' style="margin-left:auto"') ?></div>
    <?php endforeach; ?>
    <?php if (!$pfKa): ?><p class="akq-klein" style="margin:0 0 8px">In keiner Kampagne.</p><?php endif; ?>
    <?php if ($pfKaFrei): ?>
      <?= $post('akq_kampagne_hinzu', '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"><select name="kampagne" aria-label="Kampagne">'
          . implode('', array_map(static fn($k) => '<option value="' . (int) $k['id'] . '">' . Fmt::h((string) $k['name']) . '</option>', $pfKaFrei))
          . '</select><input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#kampagnen"><button class="knopf klein">Aufnehmen</button></div>') ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php /* Dokumente je Betrieb (06.10.2026): nur Verwaltung; archivieren statt löschen, endgültig löschen nur der Admin. */
    require_once dirname(__DIR__) . '/src/AkquiseDokument.php';
    $pfDok = AkquiseDokument::liste($fid); $pfDokArchiv = AkquiseDokument::liste($fid, true); $pfAdmin = Rechte::rolle() === 'admin';
    $pfDokZeile = static function (array $d, bool $archiv) use ($post, $fid, $pfAdmin): string {
        $zu = '<input type="hidden" name="dokument" value="' . (int) $d['id'] . '"><input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#dokumente">';
        $knoepfe = $archiv
            ? $post('akq_dok_zurueck', $zu . '<button class="knopf klein">Zurückholen</button>') . ($pfAdmin ? $post('akq_dok_loeschen', $zu . '<button class="knopf klein">Endgültig löschen</button>') : '')
            : $post('akq_dok_wichtig', $zu . '<button class="knopf klein" title="Oben anheften">' . ((int) $d['wichtig'] ? '★ wichtig' : '☆') . '</button>') . $post('akq_dok_archiv', $zu . '<button class="knopf klein">Archivieren</button>');
        return '<li class="pf-dok' . ((int) $d['wichtig'] && !$archiv ? ' wichtig' : '') . '"><div><a href="' . Fmt::h(url('akquise/' . $fid . '/dokument/' . (int) $d['id'])) . '">' . Fmt::h((string) $d['orig_name']) . '</a>'
            . '<span class="pf-art">' . Fmt::h(AkquiseDokument::ARTEN[(string) $d['art']] ?? (string) $d['art']) . '</span>'
            . '<div class="unter">' . Fmt::h(date('d.m.Y', strtotime((string) $d['created_at']))) . ' · ' . Fmt::h((string) $d['hochgeladen_von']) . ' · ' . Fmt::h(Fmt::bytes((int) $d['size_bytes']))
            . ($d['notiz'] ? ' · ' . Fmt::h((string) $d['notiz']) : '') . ($archiv ? ' · archiviert ' . Fmt::h(date('d.m.Y', strtotime((string) $d['archiviert_am']))) : '') . '</div></div>'
            . '<div class="pf-dok-k">' . $knoepfe . '</div></li>';
    }; ?>
  <style>
    .pf-doks{list-style:none;margin:0 0 10px;padding:0}
    .pf-dok{display:flex;gap:10px;justify-content:space-between;align-items:flex-start;border-bottom:1px solid var(--linie);padding:8px 0;font-size:14px}
    .pf-dok.wichtig a{font-weight:650}
    .pf-dok .unter{font-size:var(--fs-klein);color:var(--leise);margin-top:2px}
    .pf-art{font-size:var(--fs-klein);margin-left:8px;padding:1px 8px;border-radius:999px;border:1px solid var(--linie2);color:var(--dim)}
    .pf-dok-k{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
    .pf-dok-hoch{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:8px;align-items:end}
    .pf-dok-hoch .breit{grid-column:span 2} .pf-dok-hoch .feld{margin:0} .pf-dok-hoch select,.pf-dok-hoch input[type=text]{width:100%}
    @media (max-width:560px){.pf-dok{flex-direction:column}.pf-dok-hoch .breit{grid-column:auto}}
  </style>
  <div class="block" id="dokumente">
    <h2>Dokumente <?= $pfDok ? '<span class="marke2">' . count($pfDok) . '</span>' : '' ?></h2>
    <?php if ($pfDok): ?><ul class="pf-doks"><?php foreach ($pfDok as $d): ?><?= $pfDokZeile($d, false) ?><?php endforeach; ?></ul>
    <?php else: ?><p class="akq-klein" style="margin:0 0 10px">Noch keine Dokumente — zum Beispiel ein Einwilligungsnachweis, ein Foto vom Besuch oder ein unterschriebenes Angebot.</p><?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('akquise')) ?>" enctype="multipart/form-data" class="pf-dok-hoch"><?= Csrf::feld() ?>
      <input type="hidden" name="tat" value="akq_dok_hoch"><input type="hidden" name="firma" value="<?= (int) $fid ?>">
      <div class="feld breit"><label for="pf-datei">Datei (PDF, Bild, Office, höchstens <?= Fmt::h(Fmt::bytes(Ablage::grenze())) ?>)</label><input id="pf-datei" type="file" name="datei" required></div>
      <div class="feld"><label for="pf-art">Art</label><select id="pf-art" name="art"><?php foreach (AkquiseDokument::ARTEN as $k => $w): ?><option value="<?= $k ?>"<?= $k === 'sonstiges' ? ' selected' : '' ?>><?= Fmt::h($w) ?></option><?php endforeach; ?></select></div>
      <div class="feld breit"><label for="pf-dnotiz">Notiz</label><input id="pf-dnotiz" type="text" name="notiz" maxlength="255" placeholder="optional"></div>
      <label class="akq-klein" style="display:flex;gap:6px;align-items:center;justify-content:flex-start;padding-bottom:10px"><input type="checkbox" name="wichtig" value="1" style="width:auto;margin:0"> wichtig — oben anheften</label>
      <div><button class="knopf">Hochladen</button></div>
    </form>
    <p class="akq-klein" style="margin:8px 0 0">Nur für die Verwaltung sichtbar, nie für Partner. Wird der Betrieb Kunde, stehen die Dokumente auch in der Kundenakte.</p>
    <?php if ($pfDokArchiv): ?>
      <details style="margin-top:10px"><summary class="akq-klein" style="cursor:pointer">Archiv (<?= count($pfDokArchiv) ?>)<?= $pfAdmin ? '' : ' — endgültig löschen kann nur der Admin' ?></summary>
        <ul class="pf-doks"><?php foreach ($pfDokArchiv as $d): ?><?= $pfDokZeile($d, true) ?><?php endforeach; ?></ul></details>
    <?php endif; ?>
  </div>

  <div class="block" id="sperre">
    <h2>Sperrstatus</h2>
    <?php if ((string) ($f['sperr_art'] ?? '') !== ''): ?>
      <p style="margin:0 0 10px;font-size:14px">🔴 <b><?= Fmt::h(AkquiseCrm::SPERR_ARTEN[$f['sperr_art']] ?? (string) $f['sperr_art']) ?></b><?= $f['sperr_grund'] ? ' — ' . $v('sperr_grund') : '' ?> · seit <?= Fmt::h(date('d.m.Y', strtotime((string) $f['sperr_am']))) ?></p>
      <?php if (!$hart): ?><?= $post('akq_sperrart_loesen', '<input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#sperre"><button class="knopf">Sperre lösen</button>') ?>
      <?php else: ?><p class="akq-klein" style="margin:0">Diese Sperre steht auf der Sperrliste und gilt dauerhaft (Regeln &amp; Versand).</p><?php endif; ?>
    <?php elseif ((int) $f['gesperrt'] === 1): ?>
      <p style="margin:0;font-size:14px">🔴 Gesperrt (Sperrliste oder Widerspruch). Lösen nur unter „Regeln &amp; Versand“.</p>
    <?php else: ?>
      <p class="akq-klein" style="margin:0 0 10px">Nicht gesperrt.</p>
      <?= $post('akq_sperrart', '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end"><div class="feld" style="margin:0"><label for="pf-art">Sperren als</label><select id="pf-art" name="art" style="width:auto">'
        . implode('', array_map(static fn($k, $w) => '<option value="' . $k . '">' . Fmt::h($w) . (in_array($k, AkquiseCrm::SPERR_HART, true) ? ' (dauerhaft)' : '') . '</option>', array_keys(AkquiseCrm::SPERR_ARTEN), AkquiseCrm::SPERR_ARTEN))
        . '</select></div><div class="feld" style="margin:0;flex:1;min-width:180px"><label for="pf-grund">Grund</label><input id="pf-grund" name="grund" maxlength="255"></div>
        <input type="hidden" name="zurueck" value="akquise/' . $fid . '?ansicht=profil#sperre"><button class="knopf">Sperren</button></div>') ?>
    <?php endif; ?>
  </div>
</div>
</div>
