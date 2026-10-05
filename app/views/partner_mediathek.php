<?php
/* Mediathek der Partner pflegen (Phase 3, 05.10.2026, Uwe: „Gleich mit Tabelle und Verwaltung“).
   Jede Karte hat einen Zweck, Branchen und Kanäle (leer = alle). Text mit {link} und {name};
   fehlt {link}, hängt das System den Link des Partners an. Sichtbar ist nur „aktiv“.
   Daneben zeigt die Mediathek beim Partner, was es schon gibt (Beitrag des Tages, Branchen-Texte,
   Werbe-Vorlagen) — die werden unter „Vorlagen pflegen“ geändert, nicht hier. */
$M = Texte::PARTNER_MKT;
$de = static fn(array $t): string => Texte::h($t, 'de');
$sprachen = ['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'];
$stand = ['aktiv' => ['gut', 'aktiv'], 'entwurf' => ['', 'Entwurf'], 'archiv' => ['', 'Archiv']];
$formular = static function (?array $z) use ($M, $de, $sprachen): void {
    $id = $z ? (int) $z['id'] : 0;
    $br = $z ? PartnerMediathek::liste((string) $z['branchen']) : [];
    $ka = $z ? PartnerMediathek::liste((string) $z['kanaele']) : []; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" enctype="multipart/form-data" style="margin-top:10px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_mediathek"><?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
        <div class="feld" style="margin:0"><label>Zweck</label><select name="zweck">
          <?php foreach (PartnerMediathek::ZWECKE as $zw): ?><option value="<?= $zw ?>"<?= ($z['zweck'] ?? '') === $zw ? ' selected' : '' ?>><?= Fmt::h($de($M['zwecke'][$zw])) ?></option><?php endforeach; ?></select></div>
        <div class="feld" style="margin:0"><label>Stand</label><select name="status">
          <?php foreach (PartnerMediathek::STATUS as $st): ?><option value="<?= $st ?>"<?= ($z['status'] ?? 'entwurf') === $st ? ' selected' : '' ?>><?= $st ?></option><?php endforeach; ?></select></div>
        <div class="feld" style="margin:0"><label>Reihenfolge (klein = weiter oben)</label><input type="text" name="sort" inputmode="numeric" value="<?= (int) ($z['sort'] ?? 100) ?>"></div>
        <div class="feld" style="margin:0"><label>Verweis auf einen Bereich (statt Text)</label><select name="anker"><option value="">— kein Verweis —</option>
          <?php foreach (PartnerMediathek::ANKER as $an): ?><option value="<?= $an ?>"<?= ($z['anker'] ?? '') === $an ? ' selected' : '' ?>>#<?= $an ?></option><?php endforeach; ?></select></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:12px">
        <?php foreach ($sprachen as $l => $wie): ?>
          <div>
            <div class="feld" style="margin:0 0 8px"><label>Titel · <?= $wie ?></label><input type="text" name="titel_<?= $l ?>" maxlength="120" value="<?= Fmt::h((string) ($z['titel_' . $l] ?? '')) ?>"></div>
            <div class="feld" style="margin:0"><label>Text zum Teilen · <?= $wie ?> <span style="font-weight:400;color:var(--leise)">({link}, {name})</span></label>
              <textarea name="text_<?= $l ?>" rows="6" maxlength="<?= PartnerMediathek::TEXT_MAX ?>" style="font-size:13.5px;line-height:1.5"><?= Fmt::h((string) ($z['text_' . $l] ?? '')) ?></textarea></div>
          </div>
        <?php endforeach; ?>
      </div>
      <fieldset style="border:0;padding:0;margin:12px 0 0"><legend style="font-size:12.5px;font-weight:600;color:var(--dim);margin-bottom:6px">Branchen (keine = alle)</legend>
        <div style="display:flex;flex-wrap:wrap;gap:6px 14px;font-size:13.5px">
          <?php foreach (PartnerBranche::ALLE as $b): ?><label><input type="checkbox" name="branchen[]" value="<?= $b ?>"<?= in_array($b, $br, true) ? ' checked' : '' ?>> <?= Fmt::h($de(Texte::BRANCHEN_LISTE[$b])) ?></label><?php endforeach; ?>
        </div></fieldset>
      <fieldset style="border:0;padding:0;margin:12px 0 0"><legend style="font-size:12.5px;font-weight:600;color:var(--dim);margin-bottom:6px">Kanäle (keine = alle)</legend>
        <div style="display:flex;flex-wrap:wrap;gap:6px 14px;font-size:13.5px">
          <?php foreach (PartnerMediathek::KANAELE as $k): ?><label><input type="checkbox" name="kanaele[]" value="<?= $k ?>"<?= in_array($k, $ka, true) ? ' checked' : '' ?>> <?= Fmt::h($de($M['kanaele'][$k])) ?></label><?php endforeach; ?>
        </div></fieldset>
      <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;margin-top:12px">
        <?php if (!empty($z['bild_am'])): ?>
          <img src="<?= Fmt::h(url('partner/mediathek') . '?bild=' . $id . '&v=' . substr(md5((string) $z['bild_am']), 0, 8)) ?>" alt="" style="height:72px;border-radius:8px;border:1px solid var(--linie)">
          <label style="font-size:13px"><input type="checkbox" name="bild_weg" value="1"> Bild entfernen</label>
        <?php endif; ?>
        <div class="feld" style="margin:0;min-width:240px"><label>Bild (JPG, PNG, WebP, höchstens <?= intdiv(PartnerMediathek::BILD_MAX_BYTE, 1_000_000) ?> MB)</label>
          <input type="file" name="bild" accept="image/jpeg,image/png,image/webp"></div>
        <button class="knopf haupt"><?= $id ? 'Speichern' : 'Karte anlegen' ?></button>
      </div>
    </form>
<?php };
$gezaehlt = array_count_values(array_column($karten, 'status'));
?>
<div class="kopf"><h1>Partner-Mediathek</h1><a class="knopf" href="<?= Fmt::h(url('partner')) ?>">← Partner</a></div>
<div class="block" style="padding:14px 18px">
  <p style="font-size:13.5px;line-height:1.7;margin:0;color:var(--dim)">Fertige Inhalte, die Partner unter <b style="color:var(--text)">MARKETING › Mediathek</b> finden — nach Zweck, Branche und Kanal.
    <b style="color:var(--text)">{link}</b> wird zum Link des Partners (fehlt er, hängt das System ihn an), <b style="color:var(--text)">{name}</b> zu seinem Namen.
    Öffentliche Kanäle (Instagram, Facebook, TikTok, LinkedIn) bekommen #Werbung/#adv/#ad, wenn der Text keine Kennzeichnung trägt.
    Beitrag des Tages, Branchen-Texte und Werbe-Vorlagen erscheinen dort zusätzlich — die änderst du unter <a href="<?= Fmt::h(url('partner/vorlagen')) ?>">Vorlagen pflegen</a>.</p>
  <p style="font-size:12.5px;margin:8px 0 0;color:var(--leise)"><?= (int) ($gezaehlt['aktiv'] ?? 0) ?> aktiv · <?= (int) ($gezaehlt['entwurf'] ?? 0) ?> Entwurf · <?= (int) ($gezaehlt['archiv'] ?? 0) ?> Archiv</p>
</div>

<div class="block">
  <details<?= isset($_GET['neu']) || !$karten ? ' open' : '' ?>><summary style="cursor:pointer;font-size:15px;font-weight:650">+ Neue Karte</summary>
    <?php $formular(null); ?>
  </details>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 8px">Karten</h2>
  <?php if (!$karten): ?><p style="color:var(--leise);font-size:13px">Noch keine.</p><?php endif; ?>
  <?php foreach ($karten as $z): [$mk, $wort] = $stand[(string) $z['status']] ?? ['', (string) $z['status']]; $titel = trim((string) ($z['titel_de'] ?: $z['titel_it'] ?: $z['titel_en'])); ?>
    <details id="m-<?= (int) $z['id'] ?>" style="border-top:1px solid var(--linie);padding:10px 0">
      <summary style="cursor:pointer;font-size:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <?php if (!empty($z['bild_am'])): ?><img src="<?= Fmt::h(url('partner/mediathek') . '?bild=' . (int) $z['id'] . '&v=' . substr(md5((string) $z['bild_am']), 0, 8)) ?>" alt="" style="height:34px;border-radius:6px"><?php endif; ?>
        <b><?= Fmt::h($titel) ?></b>
        <span class="marke2 <?= $mk ?>"><?= Fmt::h($wort) ?></span>
        <span style="font-size:12px;color:var(--leise)"><?= Fmt::h($de($M['zwecke'][(string) $z['zweck']] ?? ['de' => (string) $z['zweck']])) ?>
          <?= $z['anker'] !== '' ? ' · Verweis #' . Fmt::h((string) $z['anker']) : '' ?>
          · <?= $z['branchen'] === '' ? 'alle Branchen' : Fmt::h(implode(', ', array_map(static fn($b) => $de(Texte::BRANCHEN_LISTE[$b] ?? ['de' => $b]), PartnerMediathek::liste((string) $z['branchen'])))) ?>
          · <?= $z['kanaele'] === '' ? 'alle Kanäle' : Fmt::h(implode(', ', array_map(static fn($k) => $de($M['kanaele'][$k] ?? ['de' => $k]), PartnerMediathek::liste((string) $z['kanaele'])))) ?></span>
      </summary>
      <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
        <?php foreach (['aktiv' => 'Aktiv schalten', 'entwurf' => 'Zurück auf Entwurf', 'archiv' => 'Archivieren'] as $st => $knopf): if ($st === $z['status']) { continue; } ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_mediathek_status"><input type="hidden" name="id" value="<?= (int) $z['id'] ?>"><input type="hidden" name="status" value="<?= $st ?>">
            <button class="knopf<?= $st === 'aktiv' ? ' haupt' : '' ?>" style="min-height:34px;padding:6px 12px;font-size:13px"><?= $knopf ?></button></form>
        <?php endforeach; ?>
      </div>
      <?php $formular($z); ?>
    </details>
  <?php endforeach; ?>
</div>
