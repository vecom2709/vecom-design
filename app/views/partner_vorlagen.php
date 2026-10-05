<?php
/* Werbevorlagen, FAQ und Leitfaden der Partner pflegen (27.09.2026).
   Jedes Feld zeigt den gültigen Text. Wer ihn unverändert lässt oder leert,
   bleibt beim Standard; nur Abweichungen werden gespeichert. */
$gruppen = [];
foreach ($katalog as $k => $v) { $gruppen[$v['gruppe']][$k] = $v; }
$sprachen = ['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'];
?>
<div class="kopf"><h1>Partner-Vorlagen</h1><a class="knopf" href="<?= Fmt::h(url('partner/mediathek')) ?>">Mediathek</a><a class="knopf" href="<?= Fmt::h(url('partner')) ?>">← Partner</a></div>
<div class="block" style="padding:14px 18px">
  <p style="font-size:13.5px;line-height:1.7;margin:0;color:var(--dim)">Was du hier änderst, sehen alle Partner sofort in ihrem Werbe-Paket.
    <b style="color:var(--text)">{link}</b> wird zum persönlichen Link des Partners, <b style="color:var(--text)">{name}</b> zu seinem Namen — beide müssen drinbleiben, wo sie im Standard stehen.
    Leeren oder unverändert lassen = Standard. Öffentliche Beiträge brauchen die Werbekennzeichnung (#Werbung / #adv / #ad).</p>
</div>
<?php foreach ($gruppen as $gruppe => $eintraege): ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 8px"><?= Fmt::h($gruppe) ?></h2>
  <?php foreach ($eintraege as $k => $v): $eigen = PartnerVorlagen::eigene($k); $anker = 'v-' . preg_replace('~[^a-z0-9]+~', '-', $k); ?>
    <details id="<?= Fmt::h($anker) ?>" style="border-top:1px solid var(--linie);padding:10px 0"<?= $eigen ? ' open' : '' ?>>
      <summary style="cursor:pointer;font-size:14px"><?= Fmt::h($v['titel']) ?>
        <?php if ($eigen): ?><span class="marke2 gut" style="margin-left:6px">eigene Fassung: <?= Fmt::h(strtoupper(implode(', ', array_keys($eigen)))) ?></span><?php endif; ?></summary>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_vorlage"><input type="hidden" name="schluessel" value="<?= Fmt::h($k) ?>">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">
          <?php foreach ($sprachen as $l => $wie): $std = (string) ($v['standard'][$l] ?? ''); $gilt = PartnerVorlagen::text($k, $l, $std); ?>
            <div class="feld" style="margin:0">
              <label><?= Fmt::h($wie) ?><?= isset($eigen[$l]) ? ' · <b style="color:var(--akzent,#f1d38b)">eigene</b>' : ' · Standard' ?></label>
              <textarea name="text[<?= $l ?>]" rows="<?= min(14, max(3, substr_count($gilt, "\n") + 1 + intdiv(mb_strlen($gilt), 38))) ?>" maxlength="<?= PartnerVorlagen::MAX ?>" style="font-size:13.5px;line-height:1.5"><?= Fmt::h($gilt) ?></textarea>
              <details style="margin-top:4px"><summary style="cursor:pointer;font-size:12px;color:var(--leise)">Vorschau beim Partner „Maria Rossi“</summary>
                <pre style="white-space:pre-wrap;font-family:inherit;font-size:12.5px;color:var(--dim);margin:6px 0 0"><?= Fmt::h(PartnerVorlagen::vorschau($k, $gilt)) ?></pre></details>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
          <button class="knopf">Speichern</button>
          <?php if ($eigen): ?><button class="knopf" type="submit" onclick="this.form.querySelectorAll('textarea').forEach(function(t){t.value=''})">Alle drei auf Standard</button><?php endif; ?>
        </div>
      </form>
    </details>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
