<?php /* Gemeinsame Gestaltung der Marketing-Seiten (Überblick, Kampagnen). */ ?>
<style>
  .mk-kopf{display:flex;flex-wrap:wrap;gap:12px 18px;align-items:flex-end;justify-content:space-between;margin-bottom:14px}
  .mk-kopf h1{font-size:22px;font-weight:650;letter-spacing:-.01em;margin:0}
  .mk-kopf .weg{color:var(--leise);font-size:13px;margin-top:4px}
  .mk-chips{display:flex;flex-wrap:wrap;gap:6px}
  .mk-chips a{padding:6px 12px;border-radius:999px;border:1px solid var(--linie2);color:var(--dim);font-size:13px;text-decoration:none;white-space:nowrap}
  .mk-chips a[aria-current]{border-color:transparent;background:var(--metall);color:#16120b;font-weight:650}
  .mk-sicht a{padding:6px 14px}
  .mk-filter{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin:0 0 18px}
  .mk-filter input[type=date]{width:auto;padding:7px 10px;font-size:13px}
  .mk-filter .feld{margin:0}
  .mk-trend{display:block;font-size:12px;margin-top:4px;color:var(--leise)}
  .mk-trend.auf{color:var(--gruen)} .mk-trend.ab{color:var(--rot)}
  .karte .wert.mk-klein{font-size:22px}
  .mk-karten{grid-template-columns:repeat(4,minmax(0,1fr))}
  .mk-karten.mk-drei{grid-template-columns:repeat(3,minmax(0,1fr))}
  .mk-besten.mk-vier{grid-template-columns:repeat(4,minmax(0,1fr))}
  @media(max-width:1100px){.mk-karten,.mk-karten.mk-drei,.mk-besten.mk-vier{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:700px){.mk-besten,.mk-besten.mk-vier{grid-template-columns:minmax(0,1fr)}}
  .mk-inline{display:inline;font-style:normal}
  .kachel span.leise{margin:2px 0 0}
  .mk-breit .bl__zeile{grid-template-columns:minmax(0,15rem) 1fr 3rem}
  .mk-zweit{display:block;font-size:11.5px;color:var(--leise);font-style:normal}
  .mk-besten{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
  @media(max-width:1300px){.mk-besten{grid-template-columns:repeat(2,minmax(0,1fr))}}
  .mk-best{position:relative;border:1px solid var(--linie);border-radius:12px;padding:12px 14px;background:var(--flaeche2);min-width:0;display:flex;flex-direction:column;gap:3px}
  .mk-best h3{font-size:11.5px;font-weight:500;color:var(--leise);text-transform:uppercase;letter-spacing:.07em;margin:0 0 4px}
  .mk-best__name{font-size:17px;font-weight:650;line-height:1.25;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow-wrap:anywhere;padding-right:4px}
  .mk-best__zahl{font-size:13px;color:var(--dim)}
  .mk-best__zweit{font-size:12px;color:var(--leise);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .mk-best__leer{font-size:13px;color:var(--leise);margin:0}
  .mk-best__marke{align-self:flex-start;margin-top:6px;font-size:11px;padding:2px 8px}
  .mk-liste{margin:0;padding:0;list-style:none;display:grid;gap:8px;font-size:14px;line-height:1.5}
  .mk-liste li{padding-left:18px;position:relative}
  .mk-liste li::before{content:"";position:absolute;left:2px;top:.55em;width:8px;height:8px;border-radius:50%;background:var(--leise)}
  .mk-liste.probleme li::before{background:var(--rot)} .mk-liste.empf li::before{background:var(--gruen)}
  .mk-zwei{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
  .mk-verlauf{display:block;width:100%;height:150px}
  .mk-verlauf rect{fill:var(--blau)} .mk-verlauf rect.heute{fill:var(--cyan)}
  .mk-verlauf circle{fill:var(--gruen)}
  .mk-achse{display:flex;justify-content:space-between;font-size:11.5px;color:var(--leise);margin-top:4px}
  .mk-legende{display:flex;gap:14px;font-size:12px;color:var(--dim);margin-left:auto;font-weight:400}
  .mk-legende i{display:inline-block;width:9px;height:9px;border-radius:2px;background:var(--blau);margin-right:5px;vertical-align:-1px}
  .mk-legende i.p{border-radius:50%;background:var(--gruen)}
  .mk-quelle{font-size:12.5px;color:var(--leise);line-height:1.6}
  .mk-quelle summary{cursor:pointer;color:var(--dim)}
  .mk-trichter .trichter__stufe{grid-template-columns:56px minmax(0,1fr) minmax(0,21rem)}
  .mk-trichter .trichter__wort{white-space:normal}
  @media(max-width:900px){.mk-zwei{grid-template-columns:minmax(0,1fr)}}
  @media(max-width:700px){
    .mk-trichter .trichter__stufe{grid-template-columns:58px minmax(0,1fr);row-gap:2px;column-gap:10px}
    .mk-trichter .trichter__zahl{font-size:17px}
    .mk-trichter .trichter__wort{grid-column:2}
    .mk-kopf{align-items:flex-start}
    .mk-breit .bl__zeile{grid-template-columns:minmax(0,10rem) 1fr 2.5rem}
    .karten{grid-template-columns:repeat(2,minmax(0,1fr))}
    .karte{padding:12px 13px} .karte .wert{font-size:21px} .karte .wert.mk-klein{font-size:18px}
  }

  /* Kampagnen (Phase 3) */
  .mk-link{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .mk-link input{flex:1 1 260px;min-width:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;padding:8px 10px}
  .mk-qr{width:132px;height:132px;border-radius:10px;flex:0 0 auto;background:#fff;padding:4px}
  .mk-qr svg{display:block;width:100%;height:100%}
  .mk-teilen{display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap}
  .mk-teilen > div{flex:1 1 320px;min-width:0;display:grid;gap:10px}
  .mk-tab td,.mk-tab th{white-space:nowrap}
  .mk-tab td.mk-name{white-space:normal;min-width:250px}
  .mk-tab{font-size:13.5px}
  .mk-tab td.num .mk-budget{display:block;margin:6px 0 3px auto}
  @media(max-width:700px){.mk-tab td.mk-name{min-width:180px}}
  .mk-tab .num{text-align:right;font-variant-numeric:tabular-nums}
  .mk-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;color:var(--leise)}
  .mk-formular{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end}
  .mk-formular .feld{margin:0}
  .mk-formular .breit{grid-column:1/-1}
  .mk-fein{font-size:12.5px;color:var(--leise)}
  .mk-sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}
  /* Recherche per Knopf (01.10.2026) */
  .mk-auftrag__kopf{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between;margin-bottom:10px}
  .mk-auftrag__kopf h2{margin:0}
  .mk-ampel{display:inline-flex;gap:7px;align-items:center;font-size:13px;color:var(--dim)}
  .mk-ampel i{width:10px;height:10px;border-radius:50%;background:var(--leise);flex:none}
  .mk-ampel.gruen i{background:var(--gruen)}
  .mk-auftrag form.mk-filter{margin:0 0 8px}
  .mk-auftrag form.mk-filter select{max-width:100%;min-width:0}
  @media (max-width:640px){.mk-auftrag form.mk-filter select,.mk-auftrag form.mk-filter .knopf{width:100%!important}}
  .mk-auftrag .mk-tab{margin-top:12px}
  .mk-auftrag .mk-tab td{vertical-align:top}
  .mk-laeuft::before{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;background:currentColor;margin-right:6px;vertical-align:1px;animation:mkPuls 1.4s ease-in-out infinite}
  @keyframes mkPuls{50%{opacity:.25}}
  @media (prefers-reduced-motion:reduce){.mk-laeuft::before{animation:none}}
  /* Content-Studio (01.10.2026) */
  .mk-plattformen{border:0;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center}
  .mk-plattformen legend{font-size:13px;color:var(--dim);margin-bottom:6px;padding:0}
  .mk-haken{display:inline-flex;gap:6px;align-items:center;font-size:14px;cursor:pointer;min-height:32px}
  .mk-haken input{width:auto;margin:0}
  .mk-inhalte{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
  .mk-inhalt-karte{display:flex;flex-direction:column;gap:8px;border:1px solid var(--linie);border-radius:12px;padding:14px;background:var(--flaeche2);color:inherit;text-decoration:none;min-width:0;transition:border-color .18s cubic-bezier(.16,1,.3,1)}
  .mk-inhalt-karte:hover,.mk-inhalt-karte:focus-visible{border-color:var(--linie2)}
  .mk-inhalt-karte__marken{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
  .mk-inhalt-karte__titel{font-size:15px;line-height:1.3;overflow-wrap:anywhere}
  .mk-inhalt-karte__text{font-size:13px;line-height:1.5;color:var(--dim);overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
  .mk-inhalt-karte__fuss{display:flex;gap:8px;align-items:center;margin-top:auto;flex-wrap:wrap}
  .mk-vorschau{border:1px solid var(--linie);border-radius:14px;background:#fff;color:#1c1e21;padding:14px 16px;max-width:520px;font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
  .mk-vorschau__kopf{display:flex;gap:10px;align-items:center;margin-bottom:10px}
  .mk-vorschau__logo{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#c9a24d,#7a5a1e);flex:none}
  .mk-vorschau__name{font-weight:600;font-size:14px} .mk-vorschau__zweit{font-size:12px;color:#65676b}
  .mk-vorschau__text{white-space:pre-line;overflow-wrap:anywhere}
  .mk-vorschau__tags{color:#00376b;margin-top:6px;overflow-wrap:anywhere}
  .mk-vorschau__bild{margin:10px -16px;aspect-ratio:1/1;background:#eceff3;display:flex;align-items:center;justify-content:center;color:#65676b;font-size:12.5px;padding:18px;text-align:center}
  .mk-vorschau__leiste{display:flex;justify-content:space-between;gap:10px;align-items:center;background:#f0f2f5;margin:10px -16px -14px;padding:10px 16px;border-radius:0 0 14px 14px}
  .mk-vorschau__leiste b{display:block;font-size:14px} .mk-vorschau__leiste small{color:#65676b;font-size:12px}
  .mk-vorschau__knopf{background:#e4e6eb;border-radius:6px;padding:7px 12px;font-weight:600;font-size:13px;white-space:nowrap}
  .mk-vorschau.google .g-url{font-size:12.5px;color:#202124} .mk-vorschau.google .g-anz{font-weight:700;font-size:12px;margin-right:4px}
  .mk-vorschau.google .g-titel{color:#1a0dab;font-size:19px;line-height:1.3;margin:4px 0} .mk-vorschau.google .g-text{color:#4d5156;font-size:14px}
  .mk-folien{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(180px,220px);gap:10px;overflow-x:auto;padding-bottom:6px;margin:10px -16px;padding-left:16px;padding-right:16px}
  .mk-folie{aspect-ratio:4/5;border-radius:10px;background:#1c1e21;color:#fff;padding:14px;display:flex;flex-direction:column;justify-content:flex-end;gap:6px}
  .mk-folie b{font-size:16px;line-height:1.25} .mk-folie span{font-size:12.5px;opacity:.85;line-height:1.4} .mk-folie i{font-style:normal;font-size:11px;opacity:.6}
  .mk-szenen td{vertical-align:top;white-space:normal!important;font-size:13px}
  .mk-zaehler{font-size:12px;color:var(--leise);font-variant-numeric:tabular-nums} .mk-zaehler.zu{color:var(--rot);font-weight:600}
  .mk-zeilen{display:grid;gap:4px;margin:0;padding:0;list-style:none} .mk-zeilen li{display:flex;justify-content:space-between;gap:10px;font-size:14px;border-bottom:1px solid var(--linie);padding:5px 0}
  .mk-bearbeiten textarea{min-height:90px;font-size:14px;line-height:1.5}
  .mk-bearbeiten .feld{margin:0 0 12px}
  .mk-teilen > .mk-qr{flex:0 0 132px}
  .mk-medium{display:block;width:calc(100% + 32px);margin:10px -16px;max-height:640px;object-fit:contain;background:#0b0b0b}
  .mk-medien-knoepfe{display:flex;flex-wrap:wrap;gap:10px 22px;align-items:center}
  .mk-galerie{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:14px}
  .mk-galerie__stueck{margin:0;border:1px solid var(--linie);border-radius:12px;overflow:hidden;background:var(--flaeche2);display:flex;flex-direction:column}
  .mk-galerie__stueck.gewaehlt{border-color:var(--gruen)}
  .mk-galerie__stueck .mk-medium{width:100%;margin:0;max-height:300px}
  .mk-galerie__stueck figcaption{display:flex;flex-direction:column;gap:8px;padding:10px 12px;font-size:12.5px;color:var(--dim)}
  .mk-galerie__knoepfe{display:flex;flex-wrap:wrap;gap:6px}
  .mk-posten{display:flex;flex-wrap:wrap;gap:10px 14px;align-items:center;margin:0 0 16px;padding:0 0 14px;border-bottom:1px solid var(--linie)}
  @media(min-width:900px){.mk-schreiben > div.feld:first-of-type{grid-column:span 2}}
  .mk-schreiben > .breit{grid-column:1/-1}
  .mk-budget{display:inline-block;width:90px;height:6px;border-radius:99px;background:var(--flaeche2);overflow:hidden;vertical-align:middle;margin:6px 6px 2px 0}
  .mk-budget i{display:block;height:100%;border-radius:99px}
  .mk-budget.gross{width:100%;height:9px;margin:4px 0 6px}
  @media(max-width:700px){.mk-besten{grid-template-columns:minmax(0,1fr)}}
  /* Marketing-Studio 5 (01.10.2026): Deutschland und Italien klar getrennt */
  .mk-flagge{display:inline-block;width:18px;height:12px;border-radius:2px;flex:none;box-shadow:0 0 0 1px rgba(255,255,255,.28);vertical-align:-1px}
  .mk-flagge--it{background:linear-gradient(90deg,#009246 0 33.4%,#f4f5f0 33.4% 66.6%,#ce2b37 66.6%)}
  .mk-flagge--de{background:linear-gradient(180deg,#141414 0 33.4%,#dd0000 33.4% 66.6%,#ffce00 66.6%)}
  .mk-land{display:inline-flex;gap:7px;align-items:center;white-space:nowrap}
  .mk-laender{display:inline-flex;gap:4px;padding:4px;border:1px solid var(--linie2);border-radius:14px;background:var(--flaeche2);margin:0 0 16px;max-width:100%}
  .mk-laender a{display:inline-flex;gap:9px;align-items:center;min-height:44px;padding:0 18px;border-radius:10px;color:var(--dim);text-decoration:none;font-size:15px;font-weight:550;transition:background-color .18s cubic-bezier(.16,1,.3,1),color .18s cubic-bezier(.16,1,.3,1)}
  .mk-laender a:hover{color:var(--text,inherit)}
  .mk-laender a[aria-current]{background:var(--metall);color:#16120b;font-weight:700}
  .mk-laender a[aria-current] .mk-flagge{box-shadow:0 0 0 1px rgba(0,0,0,.35)}
  .mk-laender b{min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:var(--rot);color:#fff;font-size:12px;display:inline-flex;align-items:center;justify-content:center;font-weight:700}
  .mk-laender a[aria-current] b{background:#16120b;color:#fff}
  @media(max-width:520px){.mk-laender{display:flex}.mk-laender a{flex:1 1 0;justify-content:center;padding:0 10px}}
  .mk-schritte{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 16px;padding:0;list-style:none;counter-reset:mks}
  .mk-schritte li{counter-increment:mks;border:1px solid var(--linie);border-radius:12px;padding:12px 14px 12px 52px;position:relative;background:var(--flaeche2);font-size:13.5px;line-height:1.45;color:var(--dim)}
  .mk-schritte li::before{content:counter(mks);position:absolute;left:14px;top:12px;width:26px;height:26px;border-radius:50%;border:1px solid var(--linie2);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--text,inherit);font-size:13px}
  .mk-schritte li.dran{border-color:var(--linie2)} .mk-schritte li.dran::before{background:var(--metall);color:#16120b;border-color:transparent}
  .mk-schritte b{display:block;color:var(--text,inherit);font-size:14.5px;margin-bottom:2px}
  .mk-schritte a{white-space:nowrap}
  @media(max-width:800px){.mk-schritte{grid-template-columns:minmax(0,1fr)}}
  .mk-de{display:block;font-size:12.5px;color:var(--leise);margin-top:2px;line-height:1.45}
  .mk-de::before{content:"DE";display:inline-block;font-size:10px;font-weight:700;letter-spacing:.06em;border:1px solid var(--linie2);border-radius:4px;padding:0 4px;margin-right:6px;vertical-align:1px;color:var(--dim)}
  .mk-uebersetzung{border:1px dashed var(--linie2);border-radius:12px;padding:12px 14px;margin-top:14px;max-width:520px;font-size:13.5px;line-height:1.55;color:var(--dim);white-space:pre-line;overflow-wrap:anywhere}
  .mk-uebersetzung h3{margin:0 0 6px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise);font-weight:600;white-space:normal}
  .mk-hinweis-zeile{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;justify-content:space-between}
  .mk-fehlt{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0}
  .mk-fehlt form{margin:0} .mk-fehlt .knopf{width:auto}
  .mk-zg-karten{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px}
  /* Marketing-Studio 6: Freigabe-Stapel und Ein-Klick-Kampagne */
  .mk-stapel{border:1px solid var(--linie2);border-radius:16px;padding:16px 18px;background:var(--flaeche)}
  .mk-stapel__kopf{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px}
  /* Marketing-Studio 11: zwei Bilder (Kie.ai/Blender) nebeneinander — das bessere wählen */
  .mk-wahl{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 0}
  .mk-wahl__knopf{display:flex;flex-direction:column;gap:4px;align-items:center;padding:4px;border-radius:10px;border:1px solid var(--li2,#333);background:transparent;color:inherit;cursor:pointer;font-size:12px;min-height:44px}
  .mk-wahl__knopf img{width:84px;height:84px;object-fit:cover;border-radius:7px;display:block}
  .mk-wahl__knopf.ist{border-color:var(--gold,#c79a43);box-shadow:0 0 0 1px var(--gold,#c79a43)}
  .mk-wahl__video{width:84px;height:84px;display:grid;place-items:center;border-radius:7px;background:rgba(255,255,255,.06)}
  .mk-stapel__titel{font-size:19px;margin:0 0 14px;line-height:1.3}
  .mk-stapel__rechts{display:flex;flex-direction:column;gap:14px;min-width:0}
  .mk-stapel__entscheid{border:1px solid var(--linie2);border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:12px;background:var(--flaeche2)}
  .mk-stapel__knoepfe{display:flex;flex-wrap:wrap;gap:8px}
  .mk-stapel__knoepfe .knopf{min-height:48px;padding:0 18px;font-size:15px}
  .mk-stapel__knoepfe kbd{font:600 11px/1 ui-monospace,monospace;border:1px solid currentColor;border-radius:4px;padding:2px 5px;opacity:.6;margin-left:6px}
  @media(max-width:600px){.mk-stapel__knoepfe form{flex:1 1 100%}.mk-stapel__knoepfe .knopf{width:100%}.mk-stapel__knoepfe kbd{display:none}}
  .mk-start{border-color:var(--linie2)}
  .mk-start form{display:grid;gap:12px}
  .mk-start__wahl{display:flex;flex-wrap:wrap;gap:6px 18px}
  /* Handy: lange Auswahl (Motor, Autopilot) darf nicht über den Rand (Vorschau 01.10.2026: 168 px) */
  .mk-start__wahl .mk-haken{max-width:100%;flex-wrap:wrap}
  .mk-start__wahl select{max-width:100%;min-width:0}
  .mk-start form > *, .mk-start__wahl{min-width:0}
  .mk-start__schritte{margin:4px 0 0;padding-left:20px;font-size:13px;color:var(--dim);line-height:1.6}
  .mk-zahlen{display:flex;flex-wrap:wrap;gap:6px 18px;font-size:13.5px;color:var(--dim);margin:0 0 10px}
  .mk-zahlen b{color:var(--text);font-size:16px;margin-right:4px}
</style>
