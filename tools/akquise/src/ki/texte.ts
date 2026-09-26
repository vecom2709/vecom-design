/* ==========================================================================
   Deutung und Kontaktvorlage durch Claude -- eine Firma, ein Aufruf.

   Claude bekommt NUR, was belegt ist: die Befunde mit Status und Beleg,
   die Messwerte, das mobile Bildschirmfoto. Er darf daraus deuten und
   formulieren, aber nichts hinzuerfinden. Die Verwaltung prueft den Text
   danach noch einmal selbst (Angstverkauf, Preise, unbelegte Zahlen); was
   sie beanstandet, geht einmal an Claude zurueck zum Nachbessern.
   ========================================================================== */
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { api } from '../api.js';
import { datenOrdner, konfig } from '../konfig.js';
import { log } from '../log.js';
import { BRANCHEN } from '../branchen.js';
import { cacheLesen, cacheSchluessel, cacheSchreiben, claude, jsonAus, kiMoeglich, type Nachricht } from './claude.js';

const PROMPT_STAND = '2026-09-24a';

const SYSTEM = `Du arbeitest für VECOM Design (Webdesign-Studio von Uwe Vetter, Aragona, Sizilien) als erfahrener UX-, Conversion- und Webdesign-Berater.
Du bekommst die Prüfergebnisse einer Unternehmenswebsite und ein Bildschirmfoto der mobilen Startseite. Du lieferst zwei Dinge als JSON:

1) "deutung" — für Uwe, auf Deutsch:
   - "loesung": 3–6 Sätze, konkrete VECOM-Lösung für genau diesen Betrieb (Reihenfolge: Bedarf → Geschäftsziel → Nutzer → Inhalt → Technik zuletzt).
   - "experience_sinnvoll": true/false — erzeugen Animation, Scrollytelling, 3D (Blender/Unreal/WebGL/WebGPU), 360° oder Konfigurator hier einen GESCHÄFTLICHEN Mehrwert? Nie nur, weil es technisch geht.
   - "experience": 1–2 Sätze Deutsch: die passende Idee und warum — oder warum nicht.
   - "experience_text": { "<sprache>": ein Satz in der Sprache der Firma, nur wenn experience_sinnvoll }
   - "befunde": höchstens 4 zusätzliche Beobachtungen, die man NUR auf dem Bildschirmfoto sieht (Design, UX, Conversion, Vertrauen): {"kategorie":"design|ux|conversion|vertrauen","code":"kurz_snake_case","schwere":1-4,"titel":"…","beschreibung":"…","wirkung":"…"}.
     Sachlich, nie abwertend (nicht „sieht schlecht aus", sondern z. B. „Die Gestaltung nutzt wenige visuelle Elemente, um Leistungen voneinander abzugrenzen"). Keine Wiederholung vorhandener Befunde.

2) "vorlage" — die Erstansprache in der Sprache der Firma ("sprache"), als E-Mail: {"sprache","betreff","text"}.
   Aufbau: (1) Anrede (mit Namen nur, wenn ein Ansprechpartner angegeben ist; sonst neutral) · (2) kurzer Grund, wer schreibt · (3) 1–3 konkrete Beobachtungen, NUR aus Befunden mit status VERIFIED · (4) verständlich, warum das relevant sein kann · (5) konkrete Verbesserungsidee · (6) dass VECOM Design solche Lösungen entwickelt · (7) Link https://www.vecom-design.it · (8) freundlicher Abschluss mit der vorgegebenen Signatur.
   Danach als letzte Zeile genau sinngemäß: Kontaktdaten stammen aus öffentlich zugänglichen Quellen; wer keine weitere Nachricht möchte, antwortet kurz — dann schreiben wir nicht mehr.

HARTE REGELN (werden automatisch geprüft, Verstöße werden abgelehnt):
- Keine Angst- oder Druckformulierungen („verlieren jeden Tag Kunden", „Tausende Euro", „Konkurrenz ist weit voraus", „letzte Chance", „garantiert").
- Keine Behauptung, die Seite „konvertiere nicht". Stattdessen: „mögliche Hürde", „kann Interessenten abbremsen", „erschwert die Kontaktaufnahme", „könnte die Zahl der Anfragen beeinflussen".
- KEINE Zahl, die nicht wörtlich in den Befunden/Messwerten steht. Keine Prozente, keine Prognosen. Keine Preise, kein Euro.
- Keine internen Wörter: Score, Lead, Opportunity, Akquise, VERIFIED, UNVERIFIED.
- Deutsch: Sie-Form. Italienisch: höfliche voi-Form an den Betrieb. Englisch: höflich-neutral. Natürlich klingend, nicht übersetzt wirkend, kurze Sätze.
- Höchstens 220 Wörter im Text. Kein Markdown, keine Aufzählungszeichen außer „– ".
- Nichts über den Betrieb erfinden (keine Geschichte, keine Bewertungen, keine Mitarbeiter).
- Gemessenes zuerst (Technik, Ladezeit, Mobil), „nicht gefunden"-Befunde (code beginnt mit fehlt_, *_fehlt, keine_*) nur zurückhaltend: „auf der Website haben wir … nicht gefunden" — nie „Sie haben kein …".

Antworte ausschließlich mit einem JSON-Objekt {"deutung": {...}, "vorlage": {...}}.`;

interface TextAuftrag {
  firma: { id: number; kennung: string; name: string; domain: string; url: string; land: string; stadt: string | null; branche: string | null; ansprechpartner: string | null };
  sprache: 'de' | 'it' | 'en';
  audit: { id: number; loesung: string | null; experience: string | null };
  befunde: { kategorie: string; code: string; schwere: number; titel: string; beschreibung: string | null; wirkung: string | null; messwert: string | null; beleg: string | null; status: string }[];
  absender: { inhaber: string; firma: string; ort: string; email: string; telefon: string };
}

function bildLesen(kennung: string): string | null {
  const p = join(datenOrdner('bilder'), `${kennung}-mobil.jpg`);
  return existsSync(p) ? readFileSync(p).toString('base64') : null;
}

function auftragText(a: TextAuftrag): string {
  const br = a.firma.branche ? BRANCHEN[a.firma.branche] : undefined;
  const sig = [a.absender.inhaber, `${a.absender.firma} · ${a.absender.ort}`, a.absender.email + (a.absender.telefon ? ` · ${a.absender.telefon}` : ''), 'https://www.vecom-design.it'].join('\n');
  return [
    `FIRMA: ${a.firma.name} · ${a.firma.domain} · ${a.firma.stadt ?? ''} (${a.firma.land})`,
    `BRANCHE: ${br?.de ?? a.firma.branche ?? 'unbekannt'}${br?.tourismus ? ' (touristisch)' : ''}`,
    `ANSPRECHPARTNER: ${a.firma.ansprechpartner ?? '— (neutral anreden)'}`,
    `SPRACHE DER VORLAGE: ${a.sprache}`,
    '',
    'BEFUNDE (nur VERIFIED dürfen in den Text):',
    ...a.befunde.map((b, i) => `${i + 1}. [${b.status}] ${b.kategorie}/${b.code} · Schwere ${b.schwere} · ${b.titel}`
      + (b.beleg ? `\n   Beleg: ${b.beleg.slice(0, 300)}` : '') + (b.messwert ? `\n   Messwert: ${b.messwert}` : '')),
    '',
    `SIGNATUR (genau so übernehmen):\n${sig}`,
    '',
    `Branchenidee aus unserem Katalog (nur Ausgangspunkt): ${br?.experience_idee ?? '—'}`,
  ].join('\n');
}

export async function textFuer(a: TextAuftrag): Promise<void> {
  const schluessel = cacheSchluessel(PROMPT_STAND, konfig.modell, a.audit.id, a.sprache, a.befunde.map((b) => [b.code, b.status]));
  let antwort = cacheLesen<any>(schluessel);
  if (!antwort) {
    if (!kiMoeglich()) { log.warn('claude', 'Kein Schlüssel oder Token-Obergrenze erreicht — übersprungen'); return; }
    const inhalt: any[] = [];
    const bild = bildLesen(a.firma.kennung);
    if (bild) inhalt.push({ type: 'image', source: { type: 'base64', media_type: 'image/jpeg', data: bild } });
    inhalt.push({ type: 'text', text: auftragText(a) });
    const r = await claude(SYSTEM, [{ role: 'user', content: inhalt }], 3000);
    antwort = jsonAus(r.text);
    antwort._verbrauch = { ein: r.ein, aus: r.aus };
    cacheSchreiben(schluessel, antwort);
    log.info('claude', `${a.firma.name}: Deutung + Text (${r.ein}+${r.aus} Token)`);
  } else {
    log.info('claude', `${a.firma.name}: aus dem Zwischenspeicher (0 Token)`);
  }

  const d = antwort.deutung ?? {};
  await api('deutung_melden', {
    audit_id: a.audit.id, loesung: d.loesung ?? '', experience: d.experience ?? '',
    ki: { experience_sinnvoll: !!d.experience_sinnvoll, experience_text: d.experience_text ?? {}, prompt: PROMPT_STAND },
    befunde: Array.isArray(d.befunde) ? d.befunde : [], ki_modell: konfig.modell,
  });

  let v = antwort.vorlage ?? {};
  let r = await api('vorlage_melden', { firma_id: a.firma.id, audit_id: a.audit.id, sprache: v.sprache ?? a.sprache, kanal: 'email', betreff: v.betreff ?? '', text: v.text ?? '' });
  if (Array.isArray(r.beanstandungen) && r.beanstandungen.length && kiMoeglich()) {
    // Einmal nachbessern lassen -- mit genau den Beanstandungen der Verwaltung.
    log.warn('claude', `${a.firma.name}: ${r.beanstandungen.length} Beanstandung(en) — Claude bessert nach`);
    const verlauf: Nachricht[] = [
      { role: 'user', content: [{ type: 'text', text: auftragText(a) }] },
      { role: 'assistant', content: JSON.stringify({ vorlage: v }) },
      { role: 'user', content: 'Die automatische Prüfung hat beanstandet:\n- ' + r.beanstandungen.join('\n- ')
        + '\nKorrigiere nur die Vorlage und antworte mit {"vorlage": {"sprache","betreff","text"}}.' },
    ];
    const k = await claude(SYSTEM, verlauf, 2000);
    v = jsonAus(k.text).vorlage ?? v;
    r = await api('vorlage_melden', { firma_id: a.firma.id, audit_id: a.audit.id, ersetzt: r.vorlage_id, sprache: v.sprache ?? a.sprache,
      kanal: 'email', betreff: v.betreff ?? '', text: v.text ?? '' });
  }
  if (Array.isArray(r.beanstandungen) && r.beanstandungen.length) {
    log.warn('claude', `${a.firma.name}: Entwurf bleibt mit Beanstandungen stehen — in der Verwaltung von Hand korrigieren`);
  }
}

export async function texteLauf(): Promise<void> {
  const r = await api('texte_holen', { score_min: konfig.scoreMinText, anzahl: konfig.texteProLauf });
  const liste = (r.firmen ?? []) as TextAuftrag[];
  log.info('texte', `${liste.length} Firma(en) ab Score ${konfig.scoreMinText} ohne Vorlage`);
  for (const a of liste) {
    try { await textFuer(a); } catch (e) { log.fehler('texte', `${a.firma.name}: ${(e as Error).message}`); }
  }
}
