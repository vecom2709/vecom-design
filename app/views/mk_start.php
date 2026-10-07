<?php
/**
 * Marketing · Start (01.10.2026, Uwe: Ja zu G1 „Start mit Schritten“).
 * Erwartet: $land, $st (MkStart::schritte), $fehl (MkKanaele::fehlgeschlagen).
 */
require __DIR__ . '/mk_stil.php';
$name = MkLand::name($land);
$csrf = '<input type="hidden" name="_csrf" value="' . Fmt::h(Csrf::token()) . '">';
$knopf = static function (?array $k, bool $haupt) use ($csrf, $land): string {
    if ($k === null) { return ''; }
    [$art, $ziel, $wort] = $k;
    $kl = 'knopf' . ($haupt ? ' haupt' : '');
    if ($art === 'link') { return '<a class="' . $kl . '" href="' . Fmt::h(url($ziel)) . '">' . Fmt::h($wort) . '</a>'; }
    $mehr = $ziel === 'recherche_starten' ? '<input type="hidden" name="branche" value=""><input type="hidden" name="land" value="' . Fmt::h($land) . '">'
                                          : '<input type="hidden" name="land" value="' . Fmt::h($land) . '"><input type="hidden" name="zurueck" value="marketing">';
    return '<form method="post" action="' . Fmt::h(url($ziel === 'recherche_starten' ? 'zielgruppen' : 'freigabe')) . '" style="margin:0">' . $csrf
        . '<input type="hidden" name="tat" value="' . Fmt::h($ziel) . '">' . $mehr . '<button class="' . $kl . '">' . Fmt::h($wort) . '</button></form>';
};
?>
<div class="mk-kopf">
  <div>
    <h1><i class="mk-flagge mk-flagge--<?= strtolower($land) ?>" aria-hidden="true" style="display:inline-block;width:26px;height:18px;border-radius:2px;vertical-align:-1px;margin-right:6px"></i>Marketing in <?= Fmt::h($name) ?></h1>
    <div class="weg">Vier Schritte, immer dieselbe Reihenfolge. Der markierte ist jetzt dran — ein Klick, der Rest läuft von selbst.</div>
  </div>
</div>

<?php /* „Jetzt für dich“ (01.10.2026, Uwe: Ja zu „Heute in Marketing“): nur, was gerade Uwe braucht. */
  $jdEntwuerfe = (int) Db::wert("SELECT COUNT(*) FROM mk_inhalte WHERE status = 'entwurf' AND land = ?", [$land], 0);
  $jdZiel = (int) Db::wert("SELECT COUNT(*) FROM mk_zielgruppen WHERE status = 'entwurf' AND land = ?", [$land], 0);
  $jdAnm = (int) ($anm['offen'] ?? 0);
  $jdPunkte = array_filter([
      $jdEntwuerfe ? [$jdEntwuerfe . ' ' . ($jdEntwuerfe === 1 ? 'Beitrag wartet' : 'Beiträge warten') . ' auf dein Ja oder Nein', url('freigabe') . '?land=' . $land, 'Durchgehen'] : null,
      $jdZiel ? [$jdZiel . ' ' . ($jdZiel === 1 ? 'Zielgruppe wartet' : 'Zielgruppen warten') . ' auf deine Prüfung', url('zielgruppen') . '?land=' . $land, 'Prüfen'] : null,
      $fehl ? [count($fehl) . ' ' . (count($fehl) === 1 ? 'Beitrag ist' : 'Beiträge sind') . ' nicht rausgegangen', url('kanaele#fehler'), 'Ansehen'] : null,
      $jdAnm ? [$jdAnm . ' ' . ($jdAnm === 1 ? 'Anmeldung fehlt' : 'Anmeldungen fehlen') . ' noch — Konto anlegen, dann „Erledigt“', '#anmeldungen', 'Zur Liste'] : null,
  ]); ?>
<div class="block" id="jetzt" style="border-color:var(--metall)">
  <h2>Jetzt für dich <span class="mehr"><?= $jdPunkte ? count($jdPunkte) . ' Sache' . (count($jdPunkte) === 1 ? '' : 'n') : 'nichts' ?> · alles andere läuft von selbst</span></h2>
  <?php if (!$jdPunkte): ?><p style="margin:0">Nichts zu tun — Beiträge, Kanäle und Zählung laufen.</p><?php else: ?>
  <ul style="list-style:none;margin:0;padding:0;display:grid;gap:8px">
    <?php foreach (array_values($jdPunkte) as $ji => [$jText, $jZiel, $jKnopf]): ?>
      <li style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap"><span><?= Fmt::h($jText) ?></span><a class="knopf<?= $ji === 0 ? ' haupt' : '' ?>" href="<?= Fmt::h($jZiel) ?>"><?= Fmt::h($jKnopf) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<ol class="mk-weg" aria-label="Die vier Schritte in <?= Fmt::h($name) ?>">
  <?php foreach ($st['schritte'] as $x): $istDran = $x['nr'] === $st['dran']; ?>
    <li class="mk-weg__schritt<?= $x['fertig'] ? ' fertig' : '' ?><?= $istDran ? ' dran' : '' ?>"<?= $istDran ? ' aria-current="step"' : '' ?>>
      <span class="mk-weg__nr" aria-hidden="true"><?= $x['fertig'] ? '✓' : (int) $x['nr'] ?></span>
      <div class="mk-weg__inhalt">
        <div class="mk-weg__titel"><b><?= Fmt::h($x['titel']) ?></b> <span><?= Fmt::h($x['frage']) ?></span><?php if ($istDran): ?> <em>jetzt dran</em><?php endif; ?></div>
        <p><?= Fmt::h($x['text']) ?></p>
      </div>
      <div class="mk-weg__knopf"><?= $knopf($x['knopf'], $istDran) ?></div>
    </li>
  <?php endforeach; ?>
</ol>
<?php if ($st['dran'] === 0): ?>
  <p class="hinweis" style="margin:0 0 16px">Alles läuft in <?= Fmt::h($name) ?>. Nächste Woche reicht wieder ein Klick auf „Diese Woche werben“ — oder der Autopilot unter Freigeben macht es montags von selbst.</p>
<?php endif; ?>

<?php if ($fehl): ?>
<div class="block" style="border-color:rgba(230,110,90,.55)">
  <h2>Nicht rausgegangen <span class="mehr"><?= count($fehl) ?></span></h2>
  <ul style="margin:0 0 10px;padding-left:18px;line-height:1.6">
    <?php foreach (array_slice($fehl, 0, 5) as $x): ?><li><a href="<?= Fmt::h(url('inhalte/' . (int) $x['id'])) ?>"><?= Fmt::h((string) $x['titel']) ?></a> · <span class="mk-fein"><?= Fmt::h((string) $x['post_fehler']) ?></span></li><?php endforeach; ?>
  </ul>
  <a class="knopf haupt" href="<?= Fmt::h(url('kanaele#fehler')) ?>">Ansehen und neu posten</a>
</div>
<?php endif; ?>

<?php if (!empty($anm)): /* Anmeldungen (01.10.2026): direkt zur Seite, danach „Erledigt“ — der Rest geht von selbst */
  $anErster = false;   /* „Ein Ding je Bildschirm“: Gold trägt schon „Jetzt für dich“ oben */ ?>
<div class="block" id="anmeldungen">
  <h2>Anmeldungen <span class="mehr"><?= (int) $anm['offen'] ?> offen · Konto und Passwort legst du an, alles davor und danach macht Vecom</span></h2>
  <p class="mk-fein" style="margin:0 0 10px;max-width:86ch;line-height:1.6">„Zur Anmeldung“ öffnet die Seite in einem neuen Tab — bei Verzeichnissen legt derselbe Klick vorher die eigenen Zähl-Links an.
    Dort Konto anlegen und bestätigen, dann den <b>Ausfüll-Knopf</b> in der Lesezeichenleiste drücken: Er trägt Firmendaten, Texte und den eigenen Link ein (Einrichten unter Verzeichnisse › Alle Stellen und Werkzeuge).
    Zum Schluss hier „Erledigt“.</p>
  <div class="tabellenrahmen">
    <table class="mk-tab">
      <thead><tr><th>Wo · was du tust · was danach von selbst geht</th><th>Stand</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($anm['konten'] as $k): $kStand = ['verbunden' => 'postet selbst', 'angelegt' => 'angelegt', 'offen' => 'offen'][$k['stand']]; ?>
          <tr>
            <td class="mk-name"><b><?= Fmt::h($k['name']) ?></b><br><span class="mk-fein"><?= Fmt::h($k['tun']) ?><br>→ <?= Fmt::h($k['danach']) ?></span></td>
            <td><span class="marke2<?= $k['stand'] === 'verbunden' ? ' gut' : ($k['stand'] === 'angelegt' ? '' : ' warnung') ?>"><?= Fmt::h($kStand) ?></span></td>
            <td style="text-align:right"><div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
              <?php if ($k['stand'] === 'offen'): ?>
                <a class="knopf<?= $anErster ? ' haupt' : '' ?>" href="<?= Fmt::h($k['url']) ?>" target="_blank" rel="noopener">Zur Anmeldung ↗</a><?php $anErster = false; ?>
                <form method="post" action="<?= Fmt::h(url('marketing')) ?>" style="display:inline;margin:0"><?= $csrf ?><input type="hidden" name="tat" value="konto_vermerken"><input type="hidden" name="konto" value="<?= Fmt::h($k['schluessel']) ?>"><input type="hidden" name="angelegt" value="1"><button class="knopf">Erledigt</button></form>
              <?php elseif ($k['stand'] === 'angelegt'): ?>
                <a class="knopf" href="<?= Fmt::h(url(in_array($k['schluessel'], ['facebook', 'instagram'], true) ? 'kanaele#meta' : 'kanaele#pf-' . $k['schluessel'])) ?>">Verbinden</a>
              <?php else: ?>✓<?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
        <?php $anTeil = ''; foreach ($anm['eintraege'] as $e): ?>
          <?php if (($e['teil'] ?? 'land') !== $anTeil): $anTeil = (string) ($e['teil'] ?? 'land'); ?>
            <tr><td colspan="3" class="mk-fein" style="padding-top:14px"><b><?= $anTeil === 'international' ? '🇬🇧 International — englische Plattformen, gelten für beide Länder' : ($land === 'DE' ? '🇩🇪 Verzeichnisse in Deutschland' : '🇮🇹 Verzeichnisse in Italien') ?></b></td></tr>
          <?php endif; ?>
          <tr>
            <td class="mk-name"><b><?= Fmt::h((string) $e['name']) ?></b><br><span class="mk-fein"><?= Fmt::h((string) $e['kosten']) ?><?= trim((string) $e['konto']) !== '' ? ' · ' . Fmt::h((string) $e['konto']) : '' ?><br>→ <?= $e['status'] === 'eingereicht' ? 'Erinnerung nach einer Woche, falls noch nicht online; Besuche und Anfragen zählen über den eigenen Link' : 'Zähl-Links entstehen beim Öffnen; nach „Erledigt“ Erinnerung und Zählung' ?></span></td>
            <td><span class="marke2<?= $e['status'] === 'eingereicht' ? '' : ' warnung' ?>"><?= $e['status'] === 'eingereicht' ? 'eingereicht' : 'offen' ?></span></td>
            <td style="text-align:right"><div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
              <form method="post" action="<?= Fmt::h(url('marketing')) ?>" target="_blank" style="display:inline;margin:0"><?= $csrf ?><input type="hidden" name="tat" value="anmeldung_oeffnen"><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><button class="knopf<?= $e['status'] === 'offen' && $anErster ? ' haupt' : '' ?>"><?= $e['status'] === 'offen' ? 'Zur Anmeldung ↗' : 'Öffnen ↗' ?></button></form><?php if ($e['status'] === 'offen') { $anErster = false; } ?>
              <?php if ($e['status'] === 'offen'): ?>
                <form method="post" action="<?= Fmt::h(url('verzeichnisse')) ?>" style="display:inline;margin:0"><?= $csrf ?><input type="hidden" name="tat" value="verzeichnis_stand"><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><input type="hidden" name="status" value="eingereicht"><input type="hidden" name="zurueck" value="marketing#anmeldungen"><button class="knopf">Erledigt</button></form>
              <?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<details class="block">
  <summary style="cursor:pointer"><b>Was die Wörter heißen</b></summary>
  <dl class="mk-woerter">
    <dt>Zielgruppe</dt><dd>Eine Branche in einem Land, von Claude recherchiert: Probleme, Fragen, Einwände, der beste Weg zum Kunden. Alles Weitere stützt sich darauf.</dd>
    <dt>Beitrag</dt><dd>Ein Stück zum Posten (Beitrag, Reel, Karussell, Anzeige) mit Bild oder Video — entsteht als Entwurf, geht erst nach deinem Ja raus.</dd>
    <dt>Link &amp; Kampagne</dt><dd>Jeder freigegebene Beitrag bekommt einen eigenen Kurzlink. Darüber siehst du unter „Was es bringt“, welcher Beitrag Besucher und Kunden bringt.</dd>
    <dt>Kanal</dt><dd>Wo gepostet wird. Facebook, Instagram und Telegram postet Vecom selbst; TikTok, LinkedIn, Google-Profil und YouTube kommen dir aufs Handy.</dd>
  </dl>
</details>

<style>
  .mk-weg{list-style:none;margin:0 0 18px;padding:0;display:grid;gap:10px}
  .mk-weg__schritt{display:grid;grid-template-columns:44px 1fr auto;gap:14px;align-items:center;padding:14px 16px;border:1px solid var(--linie2);border-radius:14px;background:var(--flaeche2)}
  .mk-weg__schritt.dran{border-color:rgba(241,211,139,.75);box-shadow:0 0 0 3px rgba(241,211,139,.12)}
  .mk-weg__schritt.fertig:not(.dran){opacity:.82}
  .mk-weg__nr{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:750;font-size:18px;border:1px solid var(--linie2)}
  .mk-weg__schritt.fertig .mk-weg__nr{background:rgba(63,178,127,.18);border-color:rgba(63,178,127,.6);color:var(--gruen,#3fb27f)}
  .mk-weg__schritt.dran .mk-weg__nr{background:var(--metall);color:#16120b;border-color:transparent}
  .mk-weg__titel{font-size:17px;display:flex;gap:8px;align-items:baseline;flex-wrap:wrap}
  .mk-weg__titel span{color:var(--dim);font-size:14px}
  .mk-weg__titel em{font-style:normal;font-size:var(--fs-klein);font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#f1d38b}
  .mk-weg__inhalt p{margin:4px 0 0;line-height:1.5}
  .mk-weg__knopf .knopf{min-height:46px;display:inline-flex;align-items:center}
  @media(max-width:700px){.mk-weg__schritt{grid-template-columns:36px 1fr}.mk-weg__nr{width:36px;height:36px}.mk-weg__knopf{grid-column:1/-1}}
  .mk-woerter{display:grid;grid-template-columns:max-content 1fr;gap:8px 18px;margin:0;line-height:1.55}
  .mk-woerter dt{font-weight:700}.mk-woerter dd{margin:0;max-width:75ch}
  @media(max-width:700px){.mk-woerter{grid-template-columns:1fr}.mk-woerter dd{margin-bottom:6px}}
</style>
