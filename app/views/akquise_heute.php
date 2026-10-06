<?php
/* Akquise › Heute (Akquise-CRM Modul C, 06.10.2026). Daten: $kacheln (AkquiseCrm::heute), $naechster (?{id, grund}).
   Ein Ding je Bildschirm: der goldene Knopf öffnet den nächsten besten Kontakt — alles andere ist Überblick. */
$akqTeil = 'heute';
$nf = $naechster ? Db::one('SELECT id, name, stadt, branche, prio_score, prio_stufe FROM akq_firmen WHERE id = ?', [(int) $naechster['id']]) : null;
$offen = array_sum(array_map(static fn($k) => (int) $k['n'], array_intersect_key($kacheln, array_flip(['heiss', 'antworten', 'faellig', 'ohne_schritt']))));
$prio = static function (array $z): string {
    $st = AkquisePrio::STUFEN[(string) ($z['prio_stufe'] ?? '')] ?? null;
    return $st === null ? '' : '<span class="crm-prio p-' . Fmt::h((string) $z['prio_stufe']) . '" title="' . Fmt::h($st[1]) . '">' . $st[0] . ' ' . (int) $z['prio_score'] . '</span>';
};
$warteText = ['gelb' => 'seit über 24 h', 'rot' => 'seit über 48 h', 'admin' => 'seit über 72 h'];
?>
<style>
  .crm-start{display:flex;gap:18px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:20px 22px;border:1px solid rgba(241,211,139,.45);border-radius:18px;
    background:linear-gradient(135deg,rgba(241,211,139,.08),rgba(241,211,139,.02));margin:0 0 18px}
  .crm-start h2{margin:0 0 4px;font-size:18px}
  .crm-start p{margin:0;color:var(--dim);font-size:13.5px}
  .crm-start .knopf.haupt{min-height:48px;padding:12px 24px;font-size:15.5px}
  .crm-schnell{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
  .crm-schnell a{display:inline-flex;gap:8px;align-items:center;padding:8px 14px;border-radius:999px;border:1px solid var(--linie);color:var(--dim);font-size:13.5px;background:var(--flaeche)}
  .crm-schnell a b{color:var(--text)}
  .crm-kacheln{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:14px}
  .crm-kachel{border:1px solid var(--linie);border-radius:16px;background:var(--flaeche);padding:14px 16px;display:flex;flex-direction:column;gap:8px}
  .crm-kachel.t-schlecht{border-color:rgba(255,138,138,.45)} .crm-kachel.t-warnung{border-color:rgba(255,159,90,.35)} .crm-kachel.t-gut{border-color:rgba(74,222,128,.35)}
  .crm-kachel h3{margin:0;font-size:14.5px;display:flex;justify-content:space-between;gap:8px;align-items:baseline}
  .crm-kachel h3 span{font-size:22px;font-variant-numeric:tabular-nums}
  .crm-betrieb{display:grid;grid-template-columns:1fr auto;gap:2px 10px;padding:9px 0;border-top:1px solid var(--linie)}
  .crm-betrieb a{color:var(--text);font-weight:600;font-size:14px}
  .crm-betrieb small{grid-column:1/-1;color:var(--leise);font-size:12px;line-height:1.45}
  .crm-leer{color:var(--leise);font-size:13px;padding:6px 0}
  .crm-warte{font-size:11.5px;padding:1px 8px;border-radius:999px;margin-left:6px}
  .crm-warte.gelb{background:rgba(245,197,66,.15);color:#f5c542} .crm-warte.rot,.crm-warte.admin{background:rgba(255,138,138,.15);color:var(--rot)}
</style>
<div class="kopf"><div><h1>Akquise · Heute</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px"><?= $offen > 0 ? $offen . ' Dinge warten auf dich.' : 'Nichts Dringendes offen.' ?> Nachrichten gehen nie ungeprüft raus — jede Ansprache folgt den Regeln unter „Regeln & Versand“.</p></div></div>
<?php require __DIR__ . '/akquise_reiter.php'; ?>

<div class="crm-start" id="naechster">
  <div>
    <h2>Nächster bester Kontakt</h2>
    <?php if ($nf): ?>
      <p><b style="color:var(--text)"><?= Fmt::h((string) $nf['name']) ?></b> · <?= Fmt::h(implode(' · ', array_filter([(string) $nf['stadt'], Akquise::branchenName($nf['branche'])]))) ?> — <?= Fmt::h((string) $naechster['grund']) ?></p>
    <?php else: ?>
      <p>Gerade gibt es keinen offenen Kontakt mit hoher Priorität.</p>
    <?php endif; ?>
  </div>
  <?php if ($nf): ?><a class="knopf haupt" id="naechster-oeffnen" href="<?= Fmt::h(url('akquise/naechster')) ?>">Nächsten Kontakt öffnen</a><?php endif; ?>
</div>

<nav class="crm-schnell" aria-label="Schnellzugriff">
  <?php foreach (['heiss' => '🔥 Heiße Antworten', 'antworten' => 'Offene Antworten', 'faellig' => 'Follow-ups & Wiedervorlagen', 'ohne_schritt' => 'Ohne nächsten Schritt', 'jetzt' => 'Noch nicht angesprochen'] as $k => $w): ?>
    <a href="#k-<?= $k ?>"><?= Fmt::h($w) ?> <b><?= (int) ($kacheln[$k]['n'] ?? 0) ?></b></a>
  <?php endforeach; ?>
  <a href="<?= Fmt::h(url('angebote')) ?>">Angebote</a>
</nav>

<div class="crm-kacheln">
  <?php foreach ($kacheln as $k => $ka): ?>
    <section class="crm-kachel t-<?= Fmt::h($ka['ton']) ?>" id="k-<?= Fmt::h($k) ?>">
      <h3><?= Fmt::h($ka['titel']) ?> <span><?= (int) $ka['n'] ?></span></h3>
      <?php if (!$ka['zeilen']): ?><div class="crm-leer">Nichts offen.</div><?php endif; ?>
      <?php foreach ($ka['zeilen'] as $z): $w = in_array($k, ['heiss', 'antworten'], true) ? AkquiseCrm::warteStufe($z['seit'] ?? null) : ''; ?>
        <div class="crm-betrieb">
          <a href="<?= Fmt::h(url('akquise/' . (int) $z['id'])) ?>"><?= Fmt::h((string) $z['name']) ?></a>
          <span><?= $prio($z) ?></span>
          <small><?= Fmt::h(implode(' · ', array_filter([(string) $z['stadt'], Akquise::branchenName($z['branche']), isset($z['partner']) ? 'bei ' . $z['partner'] . ' bis ' . date('d.m.', strtotime((string) $z['seit'])) : '']))) ?>
            <?php if ($w !== ''): ?><span class="crm-warte <?= $w ?>">wartet <?= Fmt::h($warteText[$w]) ?></span><?php endif; ?>
            <?php if ($k === 'faellig' && !empty($z['seit']) && $z['seit'] !== '9999-12-31'): ?> · fällig seit <?= Fmt::h(date('d.m.Y', strtotime((string) $z['seit']))) ?><?php endif; ?>
            <?php $wy = AkquisePrio::warum($z); if ($wy !== '' && in_array($k, ['jetzt', 'ohne_schritt'], true)): ?><br><?= Fmt::h($wy) ?><?php endif; ?></small>
        </div>
      <?php endforeach; ?>
      <?php if ($ka['n'] > count($ka['zeilen'])): ?><div class="crm-leer">… und <?= (int) $ka['n'] - count($ka['zeilen']) ?> weitere</div><?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
