<?php
/* Demo-Vorschauen in der Kundenakte (06.10.2026, Uwe: „Die Demo-Seiten der Kunden sollen auch in seiner
   Kundenakte angezeigt werden, die wir freigeben und auch wieder löschen können, und auch Änderungen usw.“).
   Dieselben Taten wie unter „Freigeben“ — hier mit Rücksprung in die Akte. */
require_once dirname(__DIR__) . '/src/MkDemo.php';
$kdListe = MkDemo::fuerKundeAlle((int) $k['id']);
if ($kdListe === []) { return; }
$kdForm = static function (string $tat, int $id, string $inhalt, string $attr = ''): string {
    return '<form method="post" action="' . Fmt::h(url('')) . '" style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0"' . $attr . '>' . Csrf::feld()
        . '<input type="hidden" name="tat" value="' . Fmt::h($tat) . '"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="zurueck" value="kunde">' . $inhalt . '</form>';
};
$kdFarbe = ['wartet' => 'warnung', 'fertig' => 'warnung', 'freigegeben' => 'gut', 'verworfen' => '', 'fehler' => 'schlecht'];
?>
<div class="block" id="demos"><h2>Demo-Vorschau <span style="font-size:var(--fs-klein);color:var(--leise);font-weight:500">vom Kunden angefragt · erst dein Ja schickt ihm den Link</span></h2>
  <?php foreach ($kdListe as $kd): $kdId = (int) $kd['id']; $kdSt = (string) $kd['status'];
        $kdAbgelaufen = $kdSt === 'freigegeben' && (string) $kd['gueltig_bis'] !== '' && (string) $kd['gueltig_bis'] < date('Y-m-d'); ?>
    <div style="border:1px solid var(--linie);border-radius:12px;padding:12px 14px;margin-top:10px">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between">
        <div><b><?= Fmt::h((string) ($kd['firma'] ?: 'Startseite')) ?></b>
          <span class="marke2 <?= $kdFarbe[$kdSt] ?? '' ?>" style="margin-left:6px"><?= Fmt::h($kdAbgelaufen ? 'abgelaufen' : (MkDemo::STATUS[$kdSt] ?? $kdSt)) ?></span>
          <div style="font-size:var(--fs-klein);color:var(--leise);margin-top:3px">
            angefragt <?= Fmt::h(Fmt::datum((string) $kd['created_at'])) ?> · <?= Fmt::h(strtoupper((string) $kd['sprache'])) ?>
            <?php if ($kd['url']): ?> · Vorlage: <?= Fmt::h((string) parse_url((string) $kd['url'], PHP_URL_HOST)) ?><?php endif; ?>
            <?php if ($kdSt === 'freigegeben'): ?> · verschickt <?= Fmt::h(Fmt::datum((string) $kd['freigegeben_am'])) ?> · gültig bis <?= Fmt::h(Fmt::datum((string) $kd['gueltig_bis'])) ?> · <?= (int) $kd['aufrufe'] ?> Aufrufe<?php endif; ?>
          </div></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <?php if ((int) $kd['hat_seite'] === 1): ?><a class="knopf klein<?= $kdSt === 'fertig' ? ' haupt' : '' ?>" href="<?= Fmt::h(url('demo/' . $kdId)) ?>" target="_blank" rel="noopener">Ansehen</a><?php endif; ?>
          <?php if ($kdSt === 'freigegeben' && !$kdAbgelaufen): ?><a class="knopf klein" href="<?= Fmt::h(MkDemo::adresse($kd)) ?>" target="_blank" rel="noopener">Link des Kunden</a><?php endif; ?>
          <?php if ($kdSt === 'fertig'): ?><?= $kdForm('demo_freigeben', $kdId, '<button class="knopf klein haupt">Freigeben — Link an den Kunden</button>') ?><?php endif; ?>
          <?php if ($kdSt === 'freigegeben'): ?><?= $kdForm('demo_verlaengern', $kdId, '<button class="knopf klein">+' . MkDemo::GUELTIG_TAGE . ' Tage</button>') ?><?php endif; ?>
          <?php if (in_array($kdSt, ['wartet', 'fertig', 'fehler'], true)): ?><?= $kdForm('demo_verwerfen', $kdId, '<button class="knopf klein">Verwerfen</button>') ?><?php endif; ?>
          <?= $kdForm('demo_loeschen', $kdId, '<button class="knopf klein">Löschen</button>', '') ?>
        </div>
      </div>
      <?php if ($kd['zusammenfassung']): ?><p style="font-size:var(--fs-klein);color:var(--dim);margin:8px 0 0;line-height:1.5"><?= Fmt::h((string) $kd['zusammenfassung']) ?></p><?php endif; ?>
      <?php if ($kdSt === 'fehler' && $kd['fehler']): ?><p style="font-size:var(--fs-klein);color:var(--gelb);margin:8px 0 0">Nicht geklappt: <?= Fmt::h((string) $kd['fehler']) ?></p><?php endif; ?>
      <?php if ($kdSt === 'wartet'): ?><p style="font-size:var(--fs-klein);color:var(--leise);margin:8px 0 0">Dein PC baut die Seite<?= $kd['hinweis'] ? ' — mit deinem Hinweis: „' . Fmt::h((string) $kd['hinweis']) . '“' : '' ?>.</p><?php endif; ?>
      <?php if (in_array($kdSt, ['fertig', 'fehler', 'freigegeben'], true)): ?>
        <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13.5px">Änderungen wünschen — neu bauen lassen</summary>
          <?= $kdForm('demo_nochmal', $kdId, '<input name="hinweis" maxlength="600" placeholder="z. B. Farben heller, Öffnungszeiten oben, Foto der Terrasse zuerst" style="min-width:min(420px,100%)" required><button class="knopf klein">Neu bauen</button>', ' style="margin-top:8px"') ?>
          <?php if ($kdSt === 'freigegeben'): ?><p style="font-size:var(--fs-klein);color:var(--leise);margin:6px 0 0">Während des Neubaus ruht der Link des Kunden. Danach ansehen und wieder freigeben — er bekommt dann eine neue Mail mit dem Link.</p><?php endif; ?>
        </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
