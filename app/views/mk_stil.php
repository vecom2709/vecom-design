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
  .mk-tab td.mk-name{white-space:normal;min-width:180px}
  .mk-tab .num{text-align:right;font-variant-numeric:tabular-nums}
  .mk-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;color:var(--leise)}
  .mk-formular{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end}
  .mk-formular .feld{margin:0}
  .mk-formular .breit{grid-column:1/-1}
  .mk-fein{font-size:12.5px;color:var(--leise)}
  @media(max-width:700px){.mk-besten{grid-template-columns:minmax(0,1fr)}}
</style>
