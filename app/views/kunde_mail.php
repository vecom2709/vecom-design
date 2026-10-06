<?php /* Eine verschickte E-Mail aus der Kundenakte (07.10.2026). */
require_once __DIR__ . '/../src/KundeMails.php';
$kmSt = KundeMails::STATUS[$m['status']] ?? [ucfirst((string) $m['status']), '']; ?>
<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('kunden/' . (int) $k['id'])) ?>#emails"><?= Fmt::h(Fmt::name($k['company'] ?? '', $k['name'] ?? '')) ?></a> · E-Mails</div>
  <h1 style="font-size:24px"><?= Fmt::h((string) $m['betreff']) ?></h1></div></div>
<div class="block">
  <table style="font-size:15px"><tbody>
    <tr><td style="width:140px">An</td><td><?= Fmt::h((string) $m['an']) ?></td></tr>
    <tr><td>Wann</td><td><?= Fmt::h(Fmt::zeit((string) $m['zeit'])) ?></td></tr>
    <tr><td>Stand</td><td><span class="marke2 <?= Fmt::h($kmSt[1]) ?>"><?= Fmt::h($kmSt[0]) ?></span><?= $m['status'] !== 'gesendet' && $m['fehler'] !== '' ? ' <span style="color:var(--rot)">' . Fmt::h((string) $m['fehler']) . '</span>' : '' ?></td></tr>
    <tr><td>Anlass</td><td><?= Fmt::h((string) $m['anlass']) ?></td></tr>
    <tr><td>Anhänge</td><td><?= $m['anhaenge'] ? Fmt::h(implode(', ', array_map(static fn($a) => $a['name'] . ' (' . max(1, (int) round($a['groesse'] / 1024)) . ' KB)', $m['anhaenge']))) : '—' ?></td></tr>
  </tbody></table>
  <?php if ($m['anhaenge']): ?><p class="akq-klein" style="margin:8px 0 0">Die Anhänge selbst liegen in der Akte (Belege, Angebote) und werden dort jederzeit neu erzeugt.</p><?php endif; ?>
</div>
<?php if ($m['html'] !== '' || $m['text'] !== ''): ?>
<div class="block" style="padding:0;overflow:hidden">
  <iframe title="Inhalt der E-Mail" src="<?= Fmt::h(url('kunden/' . (int) $k['id'] . '/mail/' . (int) $m['id'] . '/roh')) ?>" sandbox style="width:100%;height:78vh;border:0;background:#f6f4ef;display:block"></iframe>
</div>
<?php else: ?>
<div class="block"><div class="leer">Der Inhalt dieser E-Mail wurde noch nicht gespeichert — das macht die Verwaltung erst seit dem 07.10.2026.</div></div>
<?php endif; ?>
