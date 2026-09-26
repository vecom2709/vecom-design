<div class="kopf"><div><h1>Nachrichten</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Alles, was zwischen dir und deinen Kunden hin und her geht. Antworten kannst du im Projekt —
    und bei allem, was noch kein Projekt hat, in der Kundenakte.</p></div></div>

<?php /* Partner schreiben über ihre Partnerseite (26.09.2026) -- hier nur, was
         noch offen ist oder frisch war; beantwortet wird in der Partnerakte. */
      require_once dirname(__DIR__) . '/src/PartnerPost.php';
      $pnListe = array_slice(PartnerPost::offeneFuerVecom(20), 0, 8); ?>
<?php if ($pnListe): ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Von Partnern</h2>
  <table><tbody>
  <?php foreach ($pnListe as $n): $neu = $n['gelesen_am'] === null; ?>
    <tr><td><a href="<?= Fmt::h(url('partner/' . (int) $n['partner_id'])) ?>#nachrichten"><?= Fmt::h((string) $n['partner']) ?></a>
          <?php if ($neu): ?><br><span class="marke2 warnung">ungelesen</span><?php endif; ?></td>
        <td style="max-width:460px"><span style="white-space:pre-wrap;overflow-wrap:anywhere;<?= $neu ? '' : 'color:var(--dim)' ?>"><?= Fmt::h(mb_substr((string) $n['text'], 0, 300)) ?></span></td>
        <td style="white-space:nowrap;color:var(--leise);font-size:13px"><?= Fmt::h(date('d.m. H:i', strtotime((string) $n['created_at']))) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<div class="block">
  <?php if (!$liste): ?>
    <div class="leer">Noch keine Nachrichten.</div>
  <?php else: ?>
    <table><thead><tr><th>Von</th><th>Nachricht</th><th>Projekt</th><th>Wann</th></tr></thead><tbody>
    <?php foreach ($liste as $m): ?>
      <?php $vomKunden = $m['sender'] === 'kunde'; $neu = $vomKunden && $m['read_at'] === null; ?>
      <tr>
        <td><?= $vomKunden
              ? '<a href="' . Fmt::h(url('kunden/' . (int) $m['customer_id'])) . '">' . Fmt::h(Fmt::name($m['firma'], $m['kunde'])) . '</a>'
              : '<span style="color:var(--leise)">du</span>' ?>
          <?php if ($neu): ?><br><span class="marke2 warnung">ungelesen</span><?php endif; ?></td>
        <td style="max-width:460px"><span style="white-space:pre-wrap;overflow-wrap:anywhere;<?= $neu ? '' : 'color:var(--dim)' ?>"><?= Fmt::h(mb_substr((string) $m['body'], 0, 400)) ?><?= mb_strlen((string) $m['body']) > 400 ? '…' : '' ?></span></td>
        <td><?= $m['project_id']
              ? '<a href="' . Fmt::h(url('projekte/' . (int) $m['project_id'])) . '">' . Fmt::h((string) $m['projekt']) . '</a>'
              // Noch kein Projekt: dann fuehrt der Weg zur Akte. Ein Strich
              // half niemandem — man sah die Nachricht und nicht, wo man
              // antwortet.
              : '<a href="' . Fmt::h(url('kunden/' . (int) $m['customer_id'])) . '" style="color:var(--leise)">Kundenakte</a>' ?></td>
        <td style="white-space:nowrap;color:var(--leise)"><?= Fmt::h(Fmt::seit($m['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
