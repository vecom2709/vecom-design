<?php
/* UMZÜGE & DNS (AI Office Stufe 5, 07.10.2026). Daten: $liste, $vorgang, $dnsDomain, $dnsStaende,
   $dnsUebersicht, $kundenWahl. Uwe: „Eigene Seite + Pre-Flight“, „Vorher + täglich, MX/NS-Alarm“.
   Die drei Umzüge (Domain, Website, Post) bleiben in der Kundenakte, wo sie bedient werden —
   hier steht, wie weit der ganze Umzug ist, was der Pre-Flight gefunden hat und wie das DNS
   vorher aussah. Warum so gebaut: app/src/MigrationCenter.php und app/src/DnsSchutz.php. */
require_once dirname(__DIR__) . '/src/MigrationCenter.php';
$liste = $liste ?? []; $dnsStaende = $dnsStaende ?? []; $dnsUebersicht = $dnsUebersicht ?? []; $kundenWahl = $kundenWahl ?? [];
$farbe = static fn(string $st): string => match ($st) {
    'abgeschlossen', 'bereit', 'laeuft' => 'gut', 'fehlgeschlagen' => 'schlecht', 'wartet', 'preflight' => 'warnung', default => '' };
$ampel = static fn(string $a): array => match ($a) {
    'ok' => ['gut', 'in Ordnung'], 'warn' => ['warnung', 'Hinweis'], 'rot' => ['schlecht', 'Blocker'], default => ['', 'offen'] };
$offen = array_filter($liste, static fn($m) => !in_array((string) $m['stand'], MigrationCenter::ENDE, true));
?>
<div class="kopf"><div><h1>Umzüge &amp; DNS</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Jeder Umzug eines Kunden als ein Vorgang — Domain, Website und Post zusammen. Bedient werden die Teile weiter in der
    Kundenakte; hier stehen der gemeinsame Stand, der Pre-Flight und jeder festgehaltene DNS-Stand.</p></div></div>

<div class="block uz-zahlen">
  <div><b><?= count($offen) ?></b><span>Umzüge offen</span></div>
  <div><b><?= count(array_filter($offen, static fn($m) => $m['stand'] === 'wartet')) ?></b><span>warten auf den Kunden</span></div>
  <div><b><?= count(array_filter($offen, static fn($m) => $m['stand'] === 'fehlgeschlagen')) ?></b><span>fehlgeschlagen</span></div>
  <div><b><?= count($dnsUebersicht) ?></b><span>Domains unter DNS-Wache</span></div>
</div>

<?php if ($vorgang): $v = $vorgang; [$pfA, $pfW] = $ampel((string) ($v['preflight_ampel'] ?? '')); ?>
  <div class="block" id="vorgang">
    <p style="margin:0 0 8px"><a href="<?= Fmt::h(url('umzuege')) ?>">← Alle Umzüge</a></p>
    <h2><?= Fmt::h((string) ($v['domain'] ?: 'ohne Domain')) ?> · <?= Fmt::h($v['kunde']) ?>
      <span class="mehr"><span class="marke2 <?= $farbe((string) $v['stand']) ?>"><?= Fmt::h(MigrationCenter::STAENDE[$v['stand']] ?? $v['stand']) ?></span></span></h2>
    <p style="color:var(--leise);font-size:12.5px;margin:0 0 12px">Umzug #<?= (int) $v['id'] ?> · angelegt <?= Fmt::h(Fmt::zeit((string) $v['created_at'])) ?>
      · <a href="<?= Fmt::h(url('kunden/' . (int) $v['customer_id'])) ?>">Kundenakte öffnen</a>
      <?php if ($v['domain']): ?> · <a href="<?= Fmt::h(url('umzuege?dns=' . rawurlencode((string) $v['domain']))) ?>">DNS-Stände</a><?php endif; ?></p>
    <?php if (!empty($v['notiz'])): ?><p style="font-size:13.5px;margin:0 0 12px"><?= Fmt::h((string) $v['notiz']) ?></p><?php endif; ?>

    <h3 class="uz-h3">Teile</h3>
    <?php if (!$v['teile']): ?>
      <p style="color:var(--dim);margin:0 0 12px">Noch keiner. Domain-, Seiten- und Mailumzug legst du in der Kundenakte an — sie erscheinen dann hier von selbst.</p>
    <?php else: ?>
      <table class="schlicht uz-stapel" style="margin-bottom:12px"><tbody>
        <?php foreach ($v['teile'] as $t): ?>
          <tr><td style="width:18%"><b><?= Fmt::h(MigrationCenter::ARTEN[$t['art']]) ?></b></td>
            <td><?= Fmt::h($t['titel']) ?></td>
            <td><span class="marke2 <?= ['fertig' => 'gut', 'fehler' => 'schlecht', 'wartet_kunde' => 'warnung'][$t['gemeinsam']] ?? '' ?>"><?= Fmt::h($t['wort']) ?></span>
              <?= $t['fehler'] !== '' ? '<br><small style="color:var(--rot)">' . Fmt::h(mb_substr($t['fehler'], 0, 160)) . '</small>' : '' ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>

    <h3 class="uz-h3">Pre-Flight <?php if ($v['preflight']): ?><span class="marke2 <?= $pfA ?>" style="margin-left:6px"><?= Fmt::h($pfW) ?></span>
      <small style="color:var(--leise);font-weight:400"> · <?= Fmt::h(Fmt::zeit((string) $v['preflight_am'])) ?></small><?php endif; ?></h3>
    <?php if ($v['preflight']): ?>
      <div class="uz-pf">
        <?php foreach ($v['preflight']['punkte'] as $pp): [$pa, $pw] = $ampel((string) $pp['ampel']); ?>
          <div class="uz-pf-zeile"><span class="marke2 <?= $pa ?>"><?= Fmt::h($pw) ?></span><div><b><?= Fmt::h((string) $pp['punkt']) ?></b><p><?= Fmt::h((string) $pp['text']) ?></p></div></div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color:var(--dim);margin:0 0 10px">Noch nicht gelaufen. Er liest Domain, DNS, Post, Website, das Ziel bei Vecom und die Zustimmungen — und hält dabei den DNS-Stand fest. Geändert wird nichts.</p>
    <?php endif; ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 4px">
      <?php if (!in_array((string) $v['stand'], ['abgeschlossen'], true)): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="migration_preflight">
          <input type="hidden" name="id" value="<?= (int) $v['id'] ?>"><button class="knopf<?= in_array((string) $v['stand'], ['neu', 'preflight', 'fehlgeschlagen', 'zurueckgerollt'], true) ? ' haupt' : '' ?>"><?= $v['preflight'] ? 'Pre-Flight neu' : 'Pre-Flight starten' ?></button></form>
      <?php endif; ?>
      <?php foreach ($v['erlaubt'] as $nach): if (in_array($nach, ['preflight', 'bereit'], true)) { continue; } ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" class="uz-stand"><?= Csrf::feld() ?><input type="hidden" name="tat" value="migration_stand">
          <input type="hidden" name="id" value="<?= (int) $v['id'] ?>"><input type="hidden" name="nach" value="<?= Fmt::h($nach) ?>">
          <?php if (in_array($nach, ['fehlgeschlagen', 'zurueckgerollt'], true)): ?><input name="grund" placeholder="Warum?" maxlength="300" required aria-label="Grund"><?php endif; ?>
          <button class="knopf stumm">→ <?= Fmt::h(MigrationCenter::STAENDE[$nach]) ?></button></form>
      <?php endforeach; ?>
    </div>

    <h3 class="uz-h3" style="margin-top:16px">Verlauf</h3>
    <table class="schlicht"><tbody>
      <?php foreach ($v['verlauf'] as $h): ?>
        <tr><td style="width:1%;white-space:nowrap;color:var(--leise)"><?= Fmt::h(Fmt::zeit((string) $h['created_at'])) ?></td>
          <td><?= $h['von'] ? Fmt::h(MigrationCenter::STAENDE[$h['von']] ?? $h['von']) . ' → ' : '' ?><b><?= Fmt::h(MigrationCenter::STAENDE[$h['nach']] ?? $h['nach']) ?></b>
            <?= $h['grund'] ? '<br><small style="color:var(--dim)">' . Fmt::h((string) $h['grund']) . '</small>' : '' ?></td>
          <td style="width:1%;white-space:nowrap;color:var(--leise)"><?= Fmt::h((string) $h['wer']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
<?php elseif ($dnsDomain === ''): ?>
  <div class="block"><h2>Umzüge</h2>
    <?php if (!$liste): ?>
      <p style="margin:0 0 12px;color:var(--dim)">Noch keiner. Sobald in einer Kundenakte ein Domain-, Seiten- oder Mailumzug entsteht, steht er hier — oder du legst einen geplanten unten an.</p>
    <?php else: ?>
      <table class="schlicht uz-stapel"><thead><tr><th>Domain</th><th>Kunde</th><th>Teile</th><th>Pre-Flight</th><th>Stand</th></tr></thead><tbody>
        <?php foreach ($liste as $m): [$la, $lw] = $ampel((string) ($m['preflight_ampel'] ?? '')); ?>
          <tr style="<?= in_array((string) $m['stand'], MigrationCenter::ENDE, true) ? 'opacity:.6' : '' ?>">
            <td><a href="<?= Fmt::h(url('umzuege?id=' . (int) $m['id'])) ?>"><b><?= Fmt::h((string) ($m['domain'] ?: '#' . $m['id'])) ?></b></a></td>
            <td><?= Fmt::h($m['kunde']) ?></td>
            <td><?= Fmt::h(implode(' · ', array_map(static fn($t) => MigrationCenter::ARTEN[$t['art']], $m['teile'])) ?: '–') ?></td>
            <td><?= $m['preflight_am'] ? '<span class="marke2 ' . $la . '">' . Fmt::h($lw) . '</span>' : '<span style="color:var(--leise)">–</span>' ?></td>
            <td><span class="marke2 <?= $farbe((string) $m['stand']) ?>"><?= Fmt::h(MigrationCenter::STAENDE[$m['stand']] ?? $m['stand']) ?></span></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
    <details style="margin-top:14px"><summary style="cursor:pointer;font-size:13.5px;color:var(--dim)">Geplanten Umzug anlegen …</summary>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px;max-width:520px"><?= Csrf::feld() ?>
        <input type="hidden" name="tat" value="migration_anlegen">
        <div class="feld"><label for="uz-kunde">Kunde</label><select id="uz-kunde" name="kunde" required>
          <option value="">— wählen —</option>
          <?php foreach ($kundenWahl as $kw): ?><option value="<?= (int) $kw['id'] ?>"><?= Fmt::h(trim((string) ($kw['company'] ?: $kw['name']))) ?></option><?php endforeach; ?>
        </select></div>
        <div class="feld"><label for="uz-domain">Domain</label><input id="uz-domain" name="domain" placeholder="firma.it" required></div>
        <div class="feld"><label for="uz-notiz">Notiz (optional)</label><input id="uz-notiz" name="notiz" maxlength="500"></div>
        <button class="knopf">Anlegen</button></form></details>
  </div>
<?php endif; ?>

<?php if ($dnsDomain !== ''): ?>
  <div class="block" id="dns">
    <p style="margin:0 0 8px"><a href="<?= Fmt::h(url('umzuege')) ?>">← Alle Umzüge</a></p>
    <h2>DNS-Stände <?= Fmt::h($dnsDomain) ?></h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0 0 12px"><?= Csrf::feld() ?>
      <input type="hidden" name="tat" value="dns_festhalten"><input type="hidden" name="domain" value="<?= Fmt::h($dnsDomain) ?>">
      <button class="knopf">Jetzt festhalten</button>
      <span style="color:var(--leise);font-size:12.5px;margin-left:8px">Liest das öffentliche DNS (und die KAS-Zone, solange der Zugang des Kunden-Accounts am Auftrag liegt). Ändert nichts.</span></form>
    <?php if (!$dnsStaende): ?><p style="margin:0;color:var(--dim)">Noch kein Stand festgehalten.</p><?php endif; ?>
    <?php foreach ($dnsStaende as $i => $s):
        $vorher = $dnsStaende[$i + 1] ?? null;
        $vg = $vorher ? DnsSchutz::vergleich($vorher['oeffentlich'], $s['oeffentlich']) : null;
        $rw = $s['anlass'] === 'nachher' ? DnsSchutz::rueckweg((int) $s['id']) : null; ?>
      <details class="uz-dns" <?= $i === 0 ? 'open' : '' ?>>
        <summary><b>#<?= (int) $s['id'] ?></b> · <?= Fmt::h(['vorher' => 'vor einer Änderung', 'nachher' => 'nach einer Änderung', 'taeglich' => 'tägliche Wache',
            'hand' => 'von Hand / Pre-Flight', 'exit' => 'fürs Exit-Paket'][$s['anlass']] ?? $s['anlass']) ?> · <?= Fmt::h(Fmt::zeit((string) $s['created_at'])) ?>
          · <?= count($s['oeffentlich']) ?> Einträge<?= (int) $s['kas_ok'] ? ' · mit KAS-Zone' : '' ?>
          <?php if ($vg && ($vg['neu'] || $vg['weg'])): ?> · <span class="marke2 warnung">geändert</span><?php endif; ?>
          <?php if ($s['zurueck_am']): ?> · <span class="marke2">zurückgenommen</span><?php endif; ?></summary>
        <?php if ($vg && ($vg['neu'] || $vg['weg'])): ?>
          <div class="uz-diff">
            <?php foreach ($vg['weg'] as $z): ?><div class="weg">− <?= Fmt::h($z) ?></div><?php endforeach; ?>
            <?php foreach ($vg['neu'] as $z): ?><div class="neu">+ <?= Fmt::h($z) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($rw && $rw['ok']): ?>
          <div class="hinweis" style="margin:10px 0">
            <b>Was diese Änderung getan hat</b>
            <?php foreach ((array) ($s['aenderungen']['umgeschrieben'] ?? []) as $u): ?>
              <div style="font-size:13px">umgeschrieben: <?= Fmt::h($u['typ'] . ' ' . ($u['name'] !== '' ? $u['name'] : '@')) ?> „<?= Fmt::h((string) $u['alt_daten']) ?>“ → „<?= Fmt::h((string) $u['neu_daten']) ?>“</div>
            <?php endforeach; ?>
            <?php foreach ($rw['hand'] as $hz): ?><div style="font-size:13px">hinzugefügt: <?= Fmt::h($hz) ?> <span style="color:var(--leise)">(zurück nur von Hand im KAS)</span></div><?php endforeach; ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:8px"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="dns_zurueck"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="domain" value="<?= Fmt::h($dnsDomain) ?>">
              <button class="knopf">Zurücknehmen</button></form>
          </div>
        <?php elseif ($s['zurueck_am']): ?>
          <p style="font-size:12.5px;color:var(--leise)">Zurückgenommen <?= Fmt::h(Fmt::zeit((string) $s['zurueck_am'])) ?> von <?= Fmt::h((string) $s['zurueck_von']) ?>: <?= Fmt::h((string) $s['zurueck_text']) ?></p>
        <?php endif; ?>
        <div class="tabellenrahmen"><table class="schlicht"><tbody>
          <?php foreach ($s['oeffentlich'] as $e): ?>
            <tr><td style="width:24%"><code><?= Fmt::h((string) $e['name']) ?></code></td><td style="width:9%"><?= Fmt::h((string) $e['typ']) ?></td>
              <td><code style="word-break:break-all"><?= Fmt::h((string) $e['wert']) ?></code></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$vorgang && $dnsDomain === ''): ?>
  <div class="block" id="dns-wache"><h2>DNS-Wache <span class="mehr"><span class="marke2"><?= count($dnsUebersicht) ?></span></span></h2>
    <p style="color:var(--dim);font-size:13.5px;margin:0 0 10px">Einmal am Tag liest die Verwaltung das öffentliche DNS jeder Kundendomain. Ändern sich MX, Nameserver, SPF oder DMARC,
      kommt eine Meldung und ein Zuruf an dich. Ein unveränderter Stand wird höchstens einmal die Woche neu abgelegt.</p>
    <?php if (!$dnsUebersicht): ?>
      <p style="margin:0;color:var(--leise)">Noch keine Kundendomain — sie kommen mit Hosting-Aufträgen und überwachten Websites.</p>
    <?php else: ?>
      <table class="schlicht uz-stapel"><tbody>
        <?php foreach ($dnsUebersicht as $d): ?>
          <tr><td><a href="<?= Fmt::h(url('umzuege?dns=' . rawurlencode($d['domain']))) ?>"><?= Fmt::h($d['domain']) ?></a></td>
            <td style="color:var(--dim)"><?= $d['letzter'] ? 'zuletzt ' . Fmt::h(Fmt::seit((string) $d['letzter']['created_at'])) : 'noch kein Stand' ?></td>
            <td style="width:1%;white-space:nowrap"><?= $d['nachher_offen'] ? '<span class="marke2 warnung">' . (int) $d['nachher_offen'] . ' Änderung(en) mit Rückweg</span>' : '' ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<style>
  .uz-zahlen{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
  .uz-zahlen div{display:flex;flex-direction:column;gap:2px}
  .uz-zahlen b{font-size:20px}
  .uz-zahlen span{color:var(--leise);font-size:12.5px}
  .uz-h3{font-size:14px;margin:6px 0 8px;font-weight:650}
  .uz-pf{display:flex;flex-direction:column;gap:2px}
  .uz-pf-zeile{display:grid;grid-template-columns:110px 1fr;gap:10px;align-items:start;padding:8px 0;border-top:1px solid var(--linie,rgba(255,255,255,.08))}
  .uz-pf-zeile:first-child{border-top:0}
  .uz-pf-zeile .marke2{justify-self:start}
  .uz-pf-zeile p{margin:2px 0 0;font-size:13.5px;line-height:1.55;color:var(--dim)}
  .uz-stand{display:flex;gap:6px;margin:0;align-items:center}
  .uz-stand input{width:180px;margin:0}
  .uz-stand button,.uz-tun button{white-space:nowrap}
  .uz-dns{border:1px solid var(--linie,rgba(255,255,255,.08));border-radius:10px;padding:10px 12px;margin-top:10px}
  .uz-dns summary{cursor:pointer;font-size:13.5px}
  .uz-diff{font-family:ui-monospace,monospace;font-size:12.5px;margin:10px 0;word-break:break-all}
  .uz-diff .weg{color:var(--rot)} .uz-diff .neu{color:var(--gruen,#5bbf7a)}
  @media (max-width:640px){
    .uz-zahlen{grid-template-columns:repeat(2,minmax(0,1fr))}
    .uz-stapel{width:100%}
    .uz-stapel tbody{display:block}
    .uz-stapel thead{display:none}
    .uz-stapel tr{display:block;padding:10px 0;border-top:1px solid var(--linie,rgba(255,255,255,.08))}
    .uz-stapel td{display:block;border:0;padding:2px 0;width:auto!important}
    .uz-pf-zeile{grid-template-columns:1fr}
  }
</style>
