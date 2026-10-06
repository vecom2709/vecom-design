<?php
/* Umsatz-Chancen (AI Office Stufe 3, V5, 07.10.2026). Daten: $offen, $vorbei.
   Beim Öffnen sieht der Spürhund frisch nach (index.php) — deshalb kein „zuletzt nachgesehen“.
   Uwe: „Täglich, im Briefing + eigene Seite“. Hier wird nur gezeigt und verworfen — angeboten,
   nachgefasst und geantwortet wird in der Akte (mit ihrer Rückfrage) oder über einen Vorschlag
   von Claude in AI Freigaben. Kein lauter Knopf: Die Handlung liegt hinter „Akte öffnen“. */
require_once dirname(__DIR__) . '/src/Spuerhund.php';
$offen = $offen ?? []; $vorbei = $vorbei ?? [];
$link = static function (array $c): array {
    if (!empty($c['firma_id'])) { return [url('akquise/' . (int) $c['firma_id']), 'Betrieb öffnen']; }
    if (!empty($c['angebot_id']) && !empty($c['kunde_id'])) { return [url('kunden/' . (int) $c['kunde_id']), 'Kunde öffnen']; }
    if (!empty($c['angebot_id'])) { return [url('angebote'), 'Angebote öffnen']; }
    if (!empty($c['kunde_id'])) { return [url('kunden/' . (int) $c['kunde_id']), 'Kunde öffnen']; }
    return ['', ''];
};
$gruppen = [];
foreach ($offen as $c) { $gruppen[(string) $c['art']][] = $c; }
$monat = 0; $einmal = 0;
foreach ($offen as $c) { if ($c['wert_cents'] !== null) { if ($c['wert_art'] === 'monat') { $monat += (int) $c['wert_cents']; } else { $einmal += (int) $c['wert_cents']; } } }
?>
<div class="kopf"><div><h1>Umsatz-Chancen</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Was der Spürhund in deinen eigenen Daten gefunden hat. Er schreibt niemandem — anbieten, nachfassen und antworten
    tust du in der Akte, oder Claude legt dir einen fertigen Vorschlag in AI Freigaben.</p></div></div>

<div class="block uc-zahlen">
  <div><b><?= count($offen) ?></b><span>offen</span></div>
  <div><b><?= Fmt::h(Spuerhund::eur($monat)) ?></b><span>im Monat, wenn alles käme (Richtwert)</span></div>
  <div><b><?= Fmt::h(Spuerhund::eur($einmal)) ?></b><span>in offenen Angeboten</span></div>
  <div><b><?= count(array_filter($offen, static fn($c) => strtotime((string) $c['gefunden_am']) >= time() - 86400)) ?></b><span>neu seit gestern</span></div>
</div>

<?php if (!$offen): ?>
  <div class="block"><p style="margin:0;color:var(--dim)">Gerade nichts. Jede fertige Seite hat Betreuung und Hosting, kein Angebot und kein Interessent wartet.</p></div>
<?php endif; ?>

<?php foreach (Spuerhund::ARTEN as $art => [$titel, $wozu]): if (empty($gruppen[$art])) { continue; } ?>
  <div class="block"><h2><?= Fmt::h($titel) ?> <span class="mehr"><span class="marke2"><?= count($gruppen[$art]) ?></span></span></h2>
    <?php foreach ($gruppen[$art] as $c): [$ziel, $wort] = $link($c); ?>
      <div class="uc-zeile" id="c<?= (int) $c['id'] ?>">
        <div class="uc-text">
          <b><?= Fmt::h((string) $c['titel']) ?></b>
          <p><?= Fmt::h((string) $c['grund']) ?></p>
          <p class="uc-leise"><?= Fmt::h((string) $c['vorschlag']) ?><?= $c['wert_cents'] !== null ? ' · Richtwert ' . Fmt::h(Spuerhund::eur((int) $c['wert_cents'])) . ($c['wert_art'] === 'monat' ? ' im Monat' : '') : '' ?>
            · gefunden <?= Fmt::h(Fmt::seit((string) $c['gefunden_am'])) ?></p>
        </div>
        <div class="uc-tun">
          <?php if ($ziel !== ''): ?><a class="knopf" href="<?= Fmt::h($ziel) ?>" style="text-decoration:none"><?= Fmt::h($wort) ?></a><?php endif; ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="umsatz_chance_verwerfen"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input name="grund" placeholder="Warum nicht? (optional)" maxlength="200" aria-label="Grund fürs Verwerfen">
            <button class="knopf stumm">Verwerfen</button></form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if ($vorbei): ?>
  <div class="block"><h2>Zuletzt erledigt oder verworfen</h2>
    <table class="schlicht"><tbody>
      <?php foreach ($vorbei as $c): ?>
        <tr><td><?= Fmt::h((string) $c['titel']) ?><?php if (!empty($c['verworfen_grund'])): ?><br><small style="color:var(--leise)"><?= Fmt::h((string) $c['verworfen_grund']) ?></small><?php endif; ?></td>
          <td style="width:1%;white-space:nowrap"><span class="marke2 <?= $c['status'] === 'erledigt' ? 'gut' : '' ?>"><?= $c['status'] === 'erledigt' ? 'erledigt' : 'verworfen' ?></span></td>
          <td style="width:1%;white-space:nowrap;color:var(--dim)"><?= Fmt::h(Fmt::zeit((string) ($c['erledigt_am'] ?? $c['zuletzt_am']))) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>

<style>
  .uc-zahlen{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
  .uc-zahlen div{display:flex;flex-direction:column;gap:2px}
  .uc-zahlen b{font-size:20px}
  .uc-zahlen span{color:var(--leise);font-size:12.5px}
  .uc-zeile{display:flex;gap:16px;justify-content:space-between;align-items:flex-start;padding:12px 0;border-top:1px solid var(--linie,rgba(255,255,255,.08))}
  .uc-zeile:first-of-type{border-top:0}
  .uc-text p{margin:4px 0 0;font-size:13.5px;line-height:1.55}
  .uc-leise{color:var(--leise);font-size:12.5px!important}
  .uc-tun{display:flex;flex-direction:column;gap:8px;align-items:flex-end;min-width:240px}
  .uc-tun form{display:flex;gap:6px;margin:0}
  .uc-tun input{width:auto;min-width:150px}
  @media (max-width:640px){
    .uc-zahlen{grid-template-columns:repeat(2,minmax(0,1fr))}
    .uc-zeile{flex-direction:column}
    .uc-tun{align-items:stretch;min-width:0;width:100%}
    .uc-tun form{flex-wrap:wrap}
    .uc-tun input{flex:1;min-width:0}
  }
</style>
