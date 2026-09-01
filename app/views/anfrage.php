<div class="kopf"><div><h1><?= Fmt::h($a['name']) ?></h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">Anfrage vom <?= Fmt::h(Fmt::datum($a['created_at'])) ?>
  <?php if ($a['customer_id']): ?> · <a href="<?= Fmt::h(url('kunden/' . (int) $a['customer_id'])) ?>">Kundenakte</a><?php endif; ?></p></div>
  <a class="knopf" href="<?= Fmt::h(url('anfragen')) ?>">Zurück</a>
</div>

<div class="block">
  <h2>Kontakt</h2>
  <div class="zeile"><span>E-Mail</span><b><a href="mailto:<?= Fmt::h($a['email']) ?>"><?= Fmt::h($a['email']) ?></a></b></div>
  <?php if ($a['telefon']): ?><div class="zeile"><span>Telefon</span><b><?= Fmt::h($a['telefon']) ?></b></div><?php endif; ?>
  <?php if ($a['website']): ?><div class="zeile"><span>Bestehende Seite</span><b><?= Fmt::h($a['website']) ?></b></div><?php endif; ?>
  <div class="zeile"><span>Sprache</span><b><?= Fmt::h(strtoupper((string) $a['sprache'])) ?></b></div>
  <?php if ($a['paket_name']): ?><div class="zeile"><span>Gewähltes Paket</span><b><?= Fmt::h($a['paket_name']) ?></b></div><?php endif; ?>
</div>

<div class="block">
  <h2>Seine private Seite</h2>
  <p style="color:var(--leise);font-size:13px;margin:0 0 10px">Dieselbe Adresse, die in der Eingangsbestätigung
  steht. Dort sieht er seine Anfrage, kann schreiben und Unterlagen hochladen. Kein Konto, kein Passwort —
  wer den Link hat, kommt hinein. Also nur an ihn weitergeben.</p>
  <?php $vorgang = sicher(static fn() => $a['token'] ? Anfrage::link((string) $a['token']) : '', ''); ?>
  <?php if ($vorgang): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="text" readonly value="<?= Fmt::h($vorgang) ?>" onclick="this.select()"
             style="flex:1;min-width:280px;font-size:13px">
      <a class="knopf" href="<?= Fmt::h($vorgang) ?>" target="_blank" rel="noopener">Ansehen</a>
    </div>
  <?php else: ?>
    <p style="color:var(--leise);font-size:13px;margin:0">Für diese Anfrage gibt es keinen Zugang.</p>
  <?php endif; ?>
</div>

<div class="block">
  <h2>Was er geschrieben hat</h2>
  <pre style="white-space:pre-wrap;font:inherit;color:var(--dim);line-height:1.6;margin:0"><?= Fmt::h((string) $a['nachricht']) ?></pre>
</div>

<?php if ($a['order_id']): ?>
  <div class="block">
    <div class="hinweis gut">Aus dieser Anfrage ist eine Bestellung geworden.
      <a href="<?= Fmt::h(url('bestellungen/' . (int) $a['order_id'])) ?>">Bestellung öffnen</a></div>
  </div>
<?php else: ?>
  <div class="block">
    <h2>Bestellung daraus machen</h2>
    <p style="color:var(--leise);font-size:13px">Der Kunde ist schon angelegt. Beim Anlegen entsteht die Anzahlung
    automatisch; den Zahlungslink erzeugst du danach in der Bestellung.</p>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="anfrage_bestellung">
      <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
      <select name="paket_id" required style="min-width:220px">
        <option value="">Paket wählen …</option>
        <?php foreach ($pakete as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $a['package_id'] ? 'selected' : '' ?>>
            <?= Fmt::h($p['name']) ?> — <?= Fmt::h(Fmt::geld((int) $p['price_cents'], (string) $p['currency'])) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="knopf haupt">Bestellung anlegen</button>
    </form>
  </div>

  <div class="block">
    <h2>Stand</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;flex-wrap:wrap">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="anfrage_status">
      <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
      <?php foreach (['neu' => 'Neu', 'in_arbeit' => 'In Arbeit', 'erledigt' => 'Erledigt'] as $wert => $wort): ?>
        <button class="knopf <?= $a['status'] === $wert ? 'haupt' : '' ?>" name="status" value="<?= $wert ?>"><?= $wort ?></button>
      <?php endforeach; ?>
    </form>
  </div>
<?php endif; ?>
