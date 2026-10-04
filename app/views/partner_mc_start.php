<?php
/* ==========================================================================
   Marketing Center: Startseite mit den 13 Bereichen (04.10.2026,
   Partner-Marketingcenter Schritt 2, Uwe: „ja“).

   Eingebunden aus partner_werbemittel.php. Gesetzt sein müssen dort:
   $mcB (Bereich => Produkte), $mcZahl (Bereich => Kartenzeile),
   $mcDa (Bereich => bool: gibt es Inhalt), $wmNurLesen, $sprache, $h.

   FILTER: Jeder Teil der Seite trägt data-mc-teil="uebersicht print …".
   Ein Klick auf eine Karte setzt <html data-mc="print">, und eine CSS-Regel je
   Bereich blendet alles aus, was nicht dazugehört. Ohne Skript sind die
   Karten Sprungmarken und alles steht untereinander.
   ========================================================================== */
$MC = static fn(string $k): string => Texte::h(Texte::MARKETINGCENTER[$k] ?? [], $sprache);
$MCb = static fn(string $b): string => Texte::h(Texte::MARKETINGCENTER['b'][$b] ?? [], $sprache);
$MCbs = static fn(string $b): string => Texte::h(Texte::MARKETINGCENTER['bs'][$b] ?? [], $sprache);
$mcIcon = [
    'uebersicht'   => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
    'print'        => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10h6M7 14h4"/>',
    'pos'          => '<path d="M4 9l1.5-4.5h13L20 9"/><path d="M4 9h16v1.5a2.7 2.7 0 0 1-5.3 0 2.7 2.7 0 0 1-5.4 0 2.7 2.7 0 0 1-5.3 0z"/><path d="M5.5 13v7h13v-7"/><path d="M10 20v-4h4v4"/>',
    'textil'       => '<path d="M8.5 4L3.5 7l2 4 2.5-1.2V20h8V9.8l2.5 1.2 2-4-5-3a3.5 3.5 0 0 1-7 0z"/>',
    'fahrzeug'     => '<path d="M3.5 15V12l2.2-4.5h12.6L20.5 12v3"/><path d="M3 15h18v2.5H3z"/><circle cx="7.5" cy="17.5" r="1.6"/><circle cx="16.5" cy="17.5" r="1.6"/>',
    'event'        => '<path d="M7 3h10v14H7z"/><path d="M5 21h14M12 17v4"/><path d="M9.5 7h5M9.5 10h3"/>',
    'digital'      => '<rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M11 18.5h2"/>',
    'premium'      => '<path d="M6 4h12l3 5-9 11L3 9z"/><path d="M3 9h18M9.5 4L12 20l2.5-16"/>',
    'starter'      => '<path d="M3.5 7.5L12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5L12 12l8.5-4.5M12 12v9"/>',
    'designs'      => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
    'bestellungen' => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3.5V16h-7"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/>',
    'favoriten'    => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z"/>',
    'erfolge'      => '<path d="M5 20V11M11 20V5M17 20v-6M3 20h18"/>',
];
$mcSaetze = array_map('trim', explode('.', rtrim($MC('claim'), '. ')));
?>
<style>
  /* Markenfarben: Schwarz, sehr dunkles Braun, Anthrazit, warmes Gold (kein Gelb). */
  .mc-start{background:radial-gradient(120% 140% at 0% 0%,rgba(201,162,75,.10),transparent 55%),linear-gradient(160deg,#17130f,#0d0c0b 70%);border-color:rgba(227,194,122,.22)}
  .mc-held{display:grid;grid-template-columns:auto 1fr;gap:18px;align-items:center;margin-bottom:6px}
  .mc-held img{width:60px;height:47px;display:block}
  .mc-claim{margin:0;font-size:clamp(19px,3.2vw,28px);line-height:1.18;letter-spacing:.06em;font-weight:800;color:#f3ede2}
  .mc-claim span{display:block}
  .mc-claim span:last-child{background:linear-gradient(100deg,#f3dfa6,#d9b468 45%,#b8913f);-webkit-background-clip:text;background-clip:text;color:transparent}
  .mc-satz{margin:10px 0 0;color:var(--dim);font-size:14.5px;line-height:1.55;max-width:62ch}
  .mc-kurz{margin:10px 0 0;font-size:13px;color:#e3c27a;letter-spacing:.02em}
  .mc-karten{display:grid;grid-template-columns:repeat(auto-fill,minmax(158px,1fr));gap:10px;margin-top:18px}
  .mc-karte{position:relative;display:grid;gap:4px;align-content:start;min-height:112px;padding:14px 14px 12px;border-radius:14px;text-decoration:none;
    background:linear-gradient(165deg,#1b1713,#121010);border:1px solid rgba(227,194,122,.14);color:var(--text);transition:border-color .2s,transform .2s,box-shadow .2s}
  .mc-karte svg{width:26px;height:26px;fill:none;stroke:#e3c27a;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round;margin-bottom:4px}
  .mc-karte b{font-size:14.5px;line-height:1.25}
  .mc-karte small{font-size:12px;color:var(--leise);line-height:1.35}
  .mc-karte .mc-zahl{font-size:12px;color:#e3c27a;margin-top:2px}
  .mc-karte.mc-leer{opacity:.62}
  .mc-karte.mc-leer .mc-zahl{color:var(--leise)}
  .mc-karte:hover,.mc-karte:focus-visible{border-color:rgba(227,194,122,.5);transform:translateY(-1px)}
  .mc-karte[aria-current="true"]{border-color:rgba(227,194,122,.85);box-shadow:inset 0 0 0 1px rgba(227,194,122,.35),0 8px 24px rgba(0,0,0,.35)}
  @media (prefers-reduced-motion:reduce){.mc-karte{transition:none}.mc-karte:hover{transform:none}}
  @media (max-width:520px){
    .mc-karten{grid-template-columns:1fr 1fr;gap:8px}
    .mc-karte{min-height:0;padding:11px 12px}
    .mc-karte small{display:none}   /* am Handy: Name und Zahl reichen, sonst wird die Startseite sehr lang */
    .mc-held{grid-template-columns:1fr;gap:12px}
  }
  .mc-bereich{margin-top:22px}
  .mc-bereich > h3{display:flex;gap:10px;align-items:center;margin:0 0 10px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#f1d38b}
  .mc-bereich > h3 svg{width:20px;height:20px;fill:none;stroke:#e3c27a;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
  .mc-bald{border:1px dashed rgba(227,194,122,.3);border-radius:14px;padding:14px;display:grid;gap:8px;font-size:14px;color:var(--dim);line-height:1.5}
  .mc-bald b{color:var(--text)}
  .mc-dig{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px}
  .mc-dig a{display:flex;gap:10px;align-items:center;padding:12px 14px;border:1px solid var(--linie2);border-radius:12px;color:var(--text);text-decoration:none;font-size:14.5px}
  .mc-dig a:hover{border-color:rgba(227,194,122,.55)}
  .mc-dig a::after{content:"→";margin-left:auto;color:#e3c27a}
  .mc-stern{display:inline-flex;gap:6px;align-items:center;min-height:36px;padding:4px 12px;border-radius:999px;border:1px solid var(--linie2);background:transparent;color:var(--dim);font:inherit;font-size:13px;cursor:pointer}
  .mc-stern svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linejoin:round}
  .mc-stern[aria-pressed="true"]{color:#f1d38b;border-color:rgba(227,194,122,.6)}
  .mc-stern[aria-pressed="true"] svg{fill:#e3c27a;stroke:#e3c27a}
  .mc-kopfzeile{display:flex;gap:10px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}
  /* Designlinien (Schritt 3): Chips und Filter. */
  .mc-linien{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:16px}
  .mc-linien > span{font-size:12.5px;color:var(--leise);margin-right:2px}
  .mc-linien button{display:inline-flex;gap:8px;align-items:center;min-height:38px;padding:6px 14px;border-radius:999px;border:1px solid var(--linie2);background:transparent;color:var(--dim);font:inherit;font-size:13.5px;cursor:pointer}
  .mc-linien button i{display:inline-block;width:16px;height:16px;border-radius:4px;flex:none;box-shadow:inset 0 0 0 1px rgba(255,255,255,.18)}   /* Farbmuster, kein Radioknopf */
  .mc-linien button[aria-pressed="true"]{border-color:rgba(227,194,122,.8);color:var(--text);background:rgba(227,194,122,.08)}
  .mc-linien button:disabled{opacity:.45;cursor:default}
  .mc-linien button small{font-size:11px;color:var(--leise)}
  .mc-stile{display:grid!important;gap:8px!important}
  .mc-stillinie{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
  .mc-linienname{font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#cdb07a;min-width:160px}
  .mc-linie-leer{font-size:13.5px;color:var(--leise);margin:0 0 8px}
<?php foreach (Designlinie::LINIEN as $mcLi): ?>
  html[data-linie="<?= $mcLi ?>"] .wm-produkt[data-linien]:not([data-linien~="<?= $mcLi ?>"]){display:none}
  html[data-linie="<?= $mcLi ?>"] .mc-stillinie:not([data-linie="<?= $mcLi ?>"]){display:none}
<?php endforeach; ?>
<?php foreach (Marketingcenter::BEREICHE as $mcX): if ($mcX === 'uebersicht') { continue; } ?>
  html[data-mc="<?= $mcX ?>"] [data-mc-teil]:not([data-mc-teil~="<?= $mcX ?>"]){display:none!important}
<?php endforeach; ?>
  html[data-mc="uebersicht"] [data-mc-teil]:not([data-mc-teil~="uebersicht"]){display:none!important}
</style>

<div class="block pt mc-start" id="mc-start" data-reiter="werbemittel" data-mc-teil="<?= implode(' ', Marketingcenter::BEREICHE) ?>">
  <div class="mc-held">
    <img src="/assets/img/logo-mark.webp" width="60" height="47" alt="Vecom Design">
    <div>
      <h2 class="mc-claim"><?php foreach ($mcSaetze as $mcS): ?><span><?= $h($mcS) ?>.</span><?php endforeach; ?></h2>
    </div>
  </div>
  <p class="mc-satz"><?= $h($MC('satz')) ?></p>
  <?php if (!$wmNurLesen): ?>
    <p class="mc-kurz"><?= $h(strtr($MC('kurz'), ['{d}' => (string) count($mcDesigns), '{s}' => (string) $mcErfolge['summe']['scans'], '{b}' => (string) count($wmBestellungen)])) ?></p>
  <?php endif; ?>
  <nav class="mc-karten" aria-label="<?= $h($MC('aria')) ?>">
    <?php foreach (Marketingcenter::BEREICHE as $mcX): if (!isset($mcZahl[$mcX])) { continue; } ?>
      <a class="mc-karte<?= empty($mcDa[$mcX]) ? ' mc-leer' : '' ?>" href="#<?= $h($mcZiel[$mcX]) ?>" data-mc="<?= $mcX ?>"<?= $mcX === 'uebersicht' ? ' aria-current="true"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true"><?= $mcIcon[$mcX] ?></svg>
        <b><?= $h($MCb($mcX)) ?></b>
        <small><?= $h($MCbs($mcX)) ?></small>
        <span class="mc-zahl"><?= $h($mcZahl[$mcX]) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="mc-linien" role="group" aria-label="<?= $h($MC('linie')) ?>">
    <span><?= $h($MC('linie')) ?>:</span>
    <button type="button" data-linie="" aria-pressed="true"><?= $h($MC('linie_alle')) ?></button>
    <?php foreach (Designlinie::LINIEN as $mcLi): $mcF = Designlinie::FARBEN[$mcLi]; $mcIst = !empty($mcLinienDa[$mcLi]); ?>
      <button type="button" data-linie="<?= $mcLi ?>" aria-pressed="false" title="<?= $h(Texte::h(Texte::MARKETINGCENTER['linien_s'][$mcLi], $sprache)) ?>"<?= $mcIst ? '' : ' disabled' ?>>
        <i style="background:linear-gradient(135deg,<?= $mcF['grund'] ?> 55%,<?= $mcF['akzent'] ?> 55%)"></i><?= $h(Texte::h(Texte::MARKETINGCENTER['linien'][$mcLi], $sprache)) ?><?php if (!$mcIst): ?> <small>(<?= $h($MC('linie_bald')) ?>)</small><?php endif; ?></button>
    <?php endforeach; ?>
  </div>
</div>
<script>
/* Bereichsfilter: Karte → <html data-mc>. Die Karten sind Links — ohne Skript Sprungmarken. */
(function () {
  var nav = document.querySelector('#mc-start .mc-karten');
  if (!nav) { return; }
  var karten = [].slice.call(nav.querySelectorAll('[data-mc]'));
  function setzen(b, springen, merken) {
    document.documentElement.setAttribute('data-mc', b);
    if (merken) { try { sessionStorage.setItem('vd_mc_bereich', b); } catch (x) { } }
    karten.forEach(function (k) { k.setAttribute('aria-current', k.dataset.mc === b ? 'true' : 'false'); });
    if (springen && b !== 'uebersicht') {
      var ziel = [].slice.call(document.querySelectorAll('[data-mc-teil~="' + b + '"]')).filter(function (e) { return e.id !== 'mc-start' && e.offsetParent !== null; })[0];
      if (ziel) { ziel.scrollIntoView({ block: 'start' }); }
    }
  }
  nav.addEventListener('click', function (e) {
    var k = e.target.closest('[data-mc]');
    if (!k || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
    e.preventDefault();
    setzen(k.dataset.mc, true, true);
    try { history.replaceState(null, '', k.getAttribute('href')); } catch (x) { }
  });
  function anfang() {
  var h = location.hash.slice(1), b = 'uebersicht', treffer = false;
  karten.forEach(function (k) { if (h && k.getAttribute('href') === '#' + h) { b = k.dataset.mc; treffer = true; } });
  /* Zurück nach „Merken“, „Druckdatei erstellen“ … (#wm-p5): im zuletzt gewählten Bereich bleiben, wenn das Produkt dort steht. */
  if (!treffer && h) {
    var alt = null; try { alt = sessionStorage.getItem('vd_mc_bereich'); } catch (x) { }
    var ziel = document.getElementById(h), teil = ziel && ziel.closest('[data-mc-teil]:not(.block)');
    if (alt && teil && (' ' + teil.getAttribute('data-mc-teil') + ' ').indexOf(' ' + alt + ' ') !== -1) { b = alt; }
  }
  setzen(b, false);
  }
  setzen('uebersicht', false);   // sofort, damit nichts springt; der Rest steht erst nach dem Laden da
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', anfang); } else { anfang(); }
})();
/* Designlinie (Schritt 3): blendet Produkte und Stile anderer Linien aus und wählt in jedem
   Gestalter den ersten Stil der Linie — die Vorschau folgt sofort (change-Ereignis). */
(function () {
  var leiste = document.querySelector('#mc-start .mc-linien');
  if (!leiste) { return; }
  var knoepfe = [].slice.call(leiste.querySelectorAll('button[data-linie]'));
  var LEER = <?= json_encode($MC('linie_leer'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  function setzen(l, merken) {
    if (l) { document.documentElement.setAttribute('data-linie', l); } else { document.documentElement.removeAttribute('data-linie'); }
    knoepfe.forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.linie === l ? 'true' : 'false'); });
    if (merken) { try { sessionStorage.setItem('vd_mc_linie', l); } catch (x) { } }
    if (!l) { [].forEach.call(document.querySelectorAll('.mc-linie-leer'), function (e) { e.remove(); }); return; }
    [].forEach.call(document.querySelectorAll('form.wm-gestalter'), function (f) {
      var r = f.querySelector('input[name="stil"]:checked');
      if (r && r.closest('.mc-stillinie') && r.closest('.mc-stillinie').dataset.linie !== l) {
        var neu = f.querySelector('.mc-stillinie[data-linie="' + l + '"] input[name="stil"]');
        if (neu) { neu.checked = true; f.dispatchEvent(new Event('change', { bubbles: true })); }
      }
      var sel = f.querySelector('select[name="stil"]');
      if (sel) {
        [].forEach.call(sel.options, function (o) { var weg = o.dataset.linie !== l; o.hidden = weg; o.disabled = weg; });
        if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) {
          var erste = [].filter.call(sel.options, function (o) { return !o.disabled; })[0];
          if (erste) { sel.value = erste.value; f.dispatchEvent(new Event('change', { bubbles: true })); }
        }
      }
    });
    [].forEach.call(document.querySelectorAll('.mc-linie-leer'), function (e) { e.remove(); });
    [].forEach.call(document.querySelectorAll('.mc-bereich'), function (sec) {
      var alle = sec.querySelectorAll('.wm-produkt[data-linien]');
      if (alle.length && !sec.querySelector('.wm-produkt[data-linien~="' + l + '"]')) {
        var p = document.createElement('p'); p.className = 'mc-linie-leer'; p.textContent = LEER;
        sec.querySelector('h3').insertAdjacentElement('afterend', p);
      }
    });
  }
  leiste.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-linie]');
    if (!b || b.disabled) { return; }
    setzen(b.dataset.linie, true);
  });
  /* Ohne Linienwahl zeigen die Branchen-Flyer alle Branchen; nach dem Laden die gemerkte Linie. */
  function anfang() {
    var l = ''; try { l = sessionStorage.getItem('vd_mc_linie') || ''; } catch (x) { }
    var b = knoepfe.filter(function (k) { return k.dataset.linie === l && !k.disabled; })[0];
    if (b && l) { setzen(l, false); }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', anfang); } else { anfang(); }
})();
</script>
