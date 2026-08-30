<div class="kopf">
  <h1>Dashboard</h1>
  <div class="rechts">
    <form class="leiste" action="<?= Fmt::h(url('suche')) ?>">
      <input type="search" name="q" placeholder="Kunde, Bestellung, Projekt …">
    </form>
    <a class="knopf haupt" href="<?= Fmt::h(url('bestellungen/neu')) ?>">Bestellung erfassen</a>
  </div>
</div>

<div class="karten">
  <div class="karte"><h3>Gesamtumsatz</h3><div class="wert"><?= Fmt::geld($z['finanzen']['gesamtumsatz']) ?></div>
    <div class="neben">Schnitt je Zahlung <?= Fmt::geld($z['finanzen']['schnitt']) ?></div></div>
  <div class="karte"><h3>Diesen Monat</h3><div class="wert"><?= Fmt::geld($z['finanzen']['monatsumsatz']) ?></div>
    <div class="neben">Heute <?= Fmt::geld($z['finanzen']['heute']) ?></div></div>
  <div class="karte"><h3>Offene Zahlungen</h3><div class="wert"><?= Fmt::geld($z['finanzen']['offen']) ?></div>
    <div class="neben"><?= $z['finanzen']['fehlgeschlagen'] ?> fehlgeschlagen</div></div>
  <div class="karte"><h3>Bestellungen</h3><div class="wert"><?= $z['bestellungen']['gesamt'] ?></div>
    <div class="neben"><?= $z['bestellungen']['neu'] ?> neu · <?= $z['bestellungen']['in_bearbeitung'] ?> laufend · <?= $z['bestellungen']['abgeschlossen'] ?> fertig</div></div>
  <div class="karte"><h3>Projekte</h3><div class="wert"><?= $z['projekte']['laufend'] ?></div>
    <div class="neben"><?= $z['projekte']['feedback'] ?> im Feedback · <?= $z['projekte']['deadline'] ?> mit naher Deadline</div></div>
  <div class="karte"><h3>Kunden</h3><div class="wert"><?= $z['kunden']['gesamt'] ?></div>
    <div class="neben"><?= $z['kunden']['neu'] ?> neu diesen Monat · <?= $z['kunden']['aktiv'] ?> aktiv</div></div>
  <div class="karte"><h3>Onboarding</h3><div class="wert"><?= $z['onboarding']['offen'] ?></div>
    <div class="neben"><?= $z['onboarding']['abgeschlossen'] ?> abgeschlossen · <?= $z['kommunikation']['ungelesen'] ?> ungelesene Nachrichten</div></div>
  <div class="karte"><h3>Websites</h3><div class="wert"><?= $z['websites']['online'] ?>/<?= $z['websites']['gesamt'] ?></div>
    <div class="neben"><?= $z['websites']['offline'] ?> offline · <?= $z['websites']['ssl'] ?> SSL · <?= $z['websites']['domain'] ?> Domain</div></div>
</div>

<div class="zwei">
  <div>
    <div class="block">
      <h2>Umsatz der letzten zwölf Monate</h2>
      <?php $max = max(1, max(array_column($verlauf, 'summe'))); ?>
      <div class="saeulen">
        <?php foreach ($verlauf as $m): ?>
          <div style="height:<?= max(2, (int) round($m['summe'] / $max * 100)) ?>%"
               title="<?= Fmt::h($m['monat']) ?>: <?= Fmt::geld($m['summe']) ?>">
            <span><?= Fmt::h(substr($m['monat'], 5)) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($max <= 1): ?><div class="leer">Noch keine bezahlten Zahlungen — sobald die erste eingeht, steht sie hier.</div><?php endif; ?>
    </div>

    <div class="block">
      <h2>Letzte Aktivitäten <a class="mehr" href="<?= Fmt::h(url('aktivitaeten')) ?>">alle ansehen</a></h2>
      <?php if (!$aktivitaeten): ?><div class="leer">Noch nichts passiert.</div><?php else: ?>
      <ul class="verlauf">
        <?php foreach ($aktivitaeten as $a): ?>
          <li><span class="punkt"></span>
            <span><?= Fmt::h($a['title']) ?><br><small><?= Fmt::h($a['actor']) ?></small></span>
            <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="block">
      <h2>Wichtige Benachrichtigungen</h2>
      <?php if (!$meldungen): ?><div class="leer">Nichts Offenes.</div><?php else: ?>
      <ul class="verlauf">
        <?php foreach ($meldungen as $m): ?>
          <li><span class="punkt" style="background:var(--<?= $m['level']==='schlecht'?'rot':($m['level']==='gut'?'gruen':'cyan') ?>)"></span>
            <span><?= Fmt::h($m['title']) ?><br><small><?= Fmt::h($m['body']) ?></small></span>
            <span class="wann"><?= Fmt::h(Fmt::seit($m['created_at'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <div class="block">
      <h2>Anstehende Deadlines</h2>
      <?php if (!$deadlines): ?><div class="leer">Keine Termine hinterlegt.</div><?php else: ?>
      <ul class="verlauf">
        <?php foreach ($deadlines as $d): ?>
          <li><span class="punkt" style="background:var(--<?= strtotime($d['deadline']) < time() ? 'rot' : 'gelb' ?>)"></span>
            <span><a href="<?= Fmt::h(url('projekte/' . $d['id'])) ?>"><?= Fmt::h($d['name']) ?></a><br>
              <small><?= Fmt::h($d['kunde']) ?></small></span>
            <span class="wann"><?= Fmt::h(Fmt::datum($d['deadline'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <div class="block">
      <h2>Beliebteste Pakete</h2>
      <?php if (!$pakete): ?><div class="leer">Noch keine Bestellungen.</div><?php else: ?>
      <table><tbody>
        <?php foreach ($pakete as $p): ?>
          <tr><td><?= Fmt::h($p['name']) ?></td>
              <td class="num"><?= (int) $p['anzahl'] ?>×</td>
              <td class="num"><?= Fmt::geld((int) $p['umsatz']) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
      <?php endif; ?>
    </div>
  </div>
</div>
