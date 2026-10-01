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

<div class="block">
  <h2>Was die Wörter heißen</h2>
  <dl class="mk-woerter">
    <dt>Zielgruppe</dt><dd>Eine Branche in einem Land, von Claude recherchiert: Probleme, Fragen, Einwände, der beste Weg zum Kunden. Alles Weitere stützt sich darauf.</dd>
    <dt>Beitrag</dt><dd>Ein Stück zum Posten (Beitrag, Reel, Karussell, Anzeige) mit Bild oder Video — entsteht als Entwurf, geht erst nach deinem Ja raus.</dd>
    <dt>Link &amp; Kampagne</dt><dd>Jeder freigegebene Beitrag bekommt einen eigenen Kurzlink. Darüber siehst du unter „Zahlen“, welcher Beitrag Besucher und Kunden bringt.</dd>
    <dt>Kanal</dt><dd>Wo gepostet wird. Facebook, Instagram und Telegram postet Vecom selbst; TikTok, LinkedIn, Google-Profil und YouTube kommen dir aufs Handy.</dd>
  </dl>
</div>

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
  .mk-weg__titel em{font-style:normal;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#f1d38b}
  .mk-weg__inhalt p{margin:4px 0 0;line-height:1.5}
  .mk-weg__knopf .knopf{min-height:46px;display:inline-flex;align-items:center}
  @media(max-width:700px){.mk-weg__schritt{grid-template-columns:36px 1fr}.mk-weg__nr{width:36px;height:36px}.mk-weg__knopf{grid-column:1/-1}}
  .mk-woerter{display:grid;grid-template-columns:max-content 1fr;gap:8px 18px;margin:0;line-height:1.55}
  .mk-woerter dt{font-weight:700}.mk-woerter dd{margin:0;max-width:75ch}
  @media(max-width:700px){.mk-woerter{grid-template-columns:1fr}.mk-woerter dd{margin-bottom:6px}}
</style>
