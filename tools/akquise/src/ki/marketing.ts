/* ==========================================================================
   Marketing-Recherche per Knopf (01.10.2026, Uwe: „Recherche soll automatisch
   starten, wenn in der Verwaltung geklickt wird — im Moment muss man Claude
   im Chat schreiben“).

   Ablauf (aus „steuern“, alle fünf Minuten):
     1. Auftrag abholen        api('marketing_auftrag_holen')
     2. Claude Code starten    mit Uwes claude.ai-Anmeldung (Max-Abo), NICHT
                               mit einem API-Schlüssel; nur Websuche und
                               Webseiten lesen, keine Dateien, keine Befehle
     3. Ergebnis prüfen        JSON nach Schema (--json-schema)
     4. Abliefern              als ENTWÜRFE (marketing_zielgruppe/_recherche)
     5. Zurückmelden           api('marketing_auftrag_melden')

   Freigeben kann von hier aus niemand — das geht nur in der Verwaltung.
   ========================================================================== */
import { spawn } from 'node:child_process';
import { existsSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { api } from '../api.js';
import { datenOrdner } from '../konfig.js';
import { log } from '../log.js';
import { medienLauf, type MedienAuftrag } from './kie.js';
import { vnLauf, type VnDaten } from './vorhernachher.js';
import { demoLauf, type DemoAuftrag } from './demo.js';
import { seiteLauf, type SeiteAuftrag } from './seite.js';
import { tonLauf, type TonAuftrag } from './ton.js';
import { dreiDLauf, type DreiDAuftrag } from './render3d.js';
import { szeneBauen } from './szene3d.js';
import { bauLauf } from './bau.js';

/** Länger darf Claude nicht recherchieren (die Verwaltung gibt nach 75 Minuten auf). */
const ZEITLIMIT_MS = 45 * 60_000;

/** Was Claude über Vecom wissen muss — Stand prezzi.html / de/preise.html (01.10.2026). */
const ANBIETER = `Vecom Design — Webdesign für Betriebe und Unternehmen jeder Größe, Sitz Provinz Agrigento (Sizilien), Kunden in Italien und Deutschland.
Preise (fest, im Kostenvoranschlag; Rechnung = Voranschlag): Ein-Seiten-Website 325–400 €, Website „vetrina“ 600–750 €,
mit Speisekarte/mehreren Sprachen/Buchung 1.000–1.650 €; jede weitere Seite 65–85 €, jede weitere Sprache 40–55 € pro Seite.
Betreuung („assistenza“) ab 39 €/Monat, freiwillig, eigener Vertrag. Domain gehört dem Kunden (15–30 €/Jahr).
Kostenvoranschlag und erstes Gespräch gratis; kostenloser Website-Check. Ton: persönlich, ohne Fachjargon.
Kleiner Einstieg (Festpreis 89 €, direkt buchbar): Google-Unternehmensprofil einrichten — Kategorie, Zeiten, Leistungen, Fotos,
Bewertungslink mit QR; die Bestätigung macht Google; keine Versprechen zur Platzierung.`;

/** Die eigenen Seiten je Land — Kunden in Deutschland landen auf den deutschen Seiten. */
function anbieter(land: string): string {
  return land === 'DE'
    ? `${ANBIETER}\nAnrede „Sie“. Deutsche Seiten: /de/ (Start), /de/preise.html, /de/website-restaurant-cafe.html, /de/website-friseur.html,
/de/website-handwerker.html, /de/website-kfz-werkstatt.html, /de/website-pension-ferienwohnung.html, /de/eigene-website-oder-booking.html,
kostenlose Website-Analyse /analisi.php?lang=de. Vecom betreut Kunden in Deutschland aus der Ferne (Videotermin, Telefon, E-Mail).`
    : `${ANBIETER}\nAnrede „Lei“. Eigene Seiten: siti-web-ristoranti, siti-web-bed-and-breakfast, siti-web-parrucchieri, siti-web-artigiani,
siti-web-trasporti, sito-o-booking (Direktbuchung statt Plattform), prezzi, kostenlose Analyse /analisi.php.`;
}

const LISTE = { type: 'array', items: { type: 'string' } };
const QUELLEN = {
  type: 'array', minItems: 1, maxItems: 20,
  items: { type: 'object', required: ['titel', 'url'], properties: { titel: { type: 'string' }, url: { type: 'string' }, datum: { type: 'string' } } },
};
/** Wege zum Kunden (Z3) — dieselben Schlüssel wie MkZielgruppe::KUNDENWEGE. Alle eingehend: der Betrieb meldet sich selbst. */
export const KUNDENWEGE = ['kommentar', 'website_check', 'demo', 'anzeige', 'partner', 'google_profil'] as const;

/** Das Schema, das Claude einhalten muss. Grenzen wie in MkZielgruppe::pruefen/rechercheMelden. */
export const SCHEMA = {
  type: 'object',
  required: ['zielgruppen', 'funde', 'zusammenfassung'],
  properties: {
    zusammenfassung: { type: 'string' },
    zielgruppen: {
      type: 'array',
      items: {
        type: 'object',
        required: ['branche', 'land', 'titel', 'kurz', 'ansprache', 'probleme', 'wuensche', 'einwaende', 'fragen', 'suchbegriffe', 'kanaele', 'botschaften', 'organisch', 'bezahlt', 'quellen', 'de', 'kundenweg'],
        properties: {
          branche: { type: 'string' }, land: { type: 'string' }, titel: { type: 'string' }, kurz: { type: 'string' }, ansprache: { type: 'string' },
          probleme: LISTE, wuensche: LISTE, einwaende: LISTE, fragen: LISTE, suchbegriffe: LISTE, kanaele: LISTE, botschaften: LISTE, organisch: LISTE,
          bezahlt: { type: 'object', required: ['zielgruppe', 'keywords', 'budget', 'hinweise'],
            properties: { zielgruppe: { type: 'string' }, keywords: LISTE, budget: { type: 'string' }, hinweise: { type: 'string' } } },
          quellen: QUELLEN,
          /* Marketing-Studio 5: deutsche Fassung der Kundensprache (nur für Italien; bei Deutschland leere Listen). */
          de: { type: 'object', required: ['einwaende', 'fragen', 'suchbegriffe', 'botschaften', 'keywords'],
            properties: { einwaende: LISTE, fragen: LISTE, suchbegriffe: LISTE, botschaften: LISTE, keywords: LISTE } },
          /* Z3 (01.10.2026, Uwe: „Mit Kundenweg“): der beste Weg vom ersten Kontakt zur Anfrage für genau diese Zielgruppe. */
          kundenweg: { type: 'object', required: ['weg', 'warum', 'angebot', 'stichwort', 'zweiter'],
            properties: { weg: { type: 'string', enum: [...KUNDENWEGE] }, warum: { type: 'string' }, angebot: { type: 'string' },
              stichwort: { type: 'string' }, zweiter: { type: 'string', enum: [...KUNDENWEGE] } } },
        },
      },
    },
    funde: {
      type: 'array',
      items: {
        type: 'object', required: ['art', 'titel', 'text', 'branche', 'land', 'relevanz', 'quellen'],
        properties: {
          art: { type: 'string', enum: ['thema', 'trend', 'frage', 'wettbewerb', 'plattform'] },
          titel: { type: 'string' }, text: { type: 'string' }, branche: { type: 'string' }, land: { type: 'string' },
          relevanz: { type: 'integer', minimum: 1, maximum: 5 },
          quellen: { ...QUELLEN, maxItems: 8 },
        },
      },
    },
  },
};

type Auftrag = {
  id: number; art?: 'recherche'; branche: string; land: string; beschreibung: string;
  zielgruppen_fuer: { branche: string; name: string }[];
  vorhandene_profile: Record<string, unknown>;
  daten: Record<string, unknown>;
};

/** Content-Studio (01.10.2026): Claude schreibt Inhalte für eine freigegebene Zielgruppe. */
type InhalteAuftrag = {
  id: number; art: 'inhalte'; branche: string; land: string; beschreibung: string;
  zielgruppe: { id: number; name: string; profil: Record<string, unknown> | null };
  plattformen: string[]; umfang: 'organisch' | 'bezahlt' | 'beides'; anzahl: number; thema: string;
  formate: Record<string, { wort: string; art: string; plattformen: string[] }>;
  grenzen: Record<string, number>; meta_cta: Record<string, string>; zielseite: string;
  funde: { id: number; art: string; titel: string; text: string; relevanz: number; gemerkt: boolean; quellen: string[] }[];
  bisherige_titel: string[];
  /** Marketing-Studio 6: Ein-Klick-Kampagne mit fester Mischung. */
  paket?: boolean;
  /** S1: Antwort auf „Kommentiere STICHWORT“ läuft automatisch (sonst Link-Aufruf). */
  kommentar_automatik?: boolean;
};

/** Die feste Mischung einer Ein-Klick-Kampagne — so, dass möglichst viel automatisch veröffentlicht werden kann. */
export function paketText(a: Pick<InhalteAuftrag, 'umfang' | 'anzahl'>): string {
  if (a.umfang === 'bezahlt') {
    return '1 meta_anzeige (facebook), 1 meta_anzeige (instagram, andere Botschaft), 2 google_anzeige (zwei Anzeigengruppen mit verschiedenen Suchabsichten, z. B. „Website erstellen“ und „Direktbuchungen/Online-Termine“)';
  }
  const organisch = '2 beitrag (instagram, je ein anderes Problem), 1 beitrag (facebook), 1 karussell (facebook — Rechnung oder Schritt-für-Schritt), 1 telegram, 1 beitrag (instagram oder facebook, eine häufige Frage beantworten)';
  return a.umfang === 'organisch' ? organisch : '2 beitrag (instagram), 1 beitrag (facebook), 1 karussell (facebook), 1 telegram, 1 meta_anzeige (facebook), 1 google_anzeige, 1 beitrag (instagram, eine häufige Frage beantworten)';
}

const FOLIEN = { type: 'array', items: { type: 'object', properties: { titel: { type: 'string' }, text: { type: 'string' } } } };
/** Schema für geschriebene Inhalte. Die Grenzen prüft die Verwaltung (MkInhalt::pruefen) noch einmal. */
export const SCHEMA_INHALTE = {
  type: 'object',
  required: ['inhalte', 'zusammenfassung'],
  properties: {
    zusammenfassung: { type: 'string' },
    inhalte: {
      type: 'array',
      items: {
        type: 'object',
        required: ['format', 'plattform', 'sprache', 'titel', 'felder', 'bildidee', 'bild_prompt', 'begruendung', 'fund_ids', 'uebersetzung'],
        properties: {
          format: { type: 'string', enum: ['beitrag', 'karussell', 'reel', 'story', 'telegram', 'profil', 'meta_anzeige', 'google_anzeige'] },
          plattform: { type: 'string' }, sprache: { type: 'string', enum: ['it', 'de', 'en'] }, titel: { type: 'string' },
          felder: {
            type: 'object',
            properties: {
              hook: { type: 'string' }, text: { type: 'string' }, hashtags: LISTE, cta: { type: 'string' }, knopf: { type: 'string' },
              folien: FOLIEN,
              szenen: { type: 'array', items: { type: 'object', properties: { sekunden: { type: 'integer' }, bild: { type: 'string' }, einblendung: { type: 'string' }, sprecher: { type: 'string' } } } },
              primaertexte: LISTE, ueberschriften: LISTE, beschreibung: { type: 'string' }, beschreibungen: LISTE,
              pfad1: { type: 'string' }, pfad2: { type: 'string' }, keywords: LISTE, ausschluesse: LISTE,
            },
          },
          bildidee: { type: 'string' }, bild_prompt: { type: 'string' }, begruendung: { type: 'string' },
          fund_ids: { type: 'array', items: { type: 'integer' } },
          uebersetzung: { type: 'string' },
        },
      },
    },
  },
};

/** Der Schreibauftrag an Claude. */
export function inhalteText(a: InhalteAuftrag): string {
  const sprache = a.land === 'DE' ? 'Deutsch, Anrede „Sie“' : 'Italienisch, Anrede „Lei“';
  const g = a.grenzen ?? {};
  return `Du schreibst Marketing-Inhalte für Vecom Design. Ergebnis sind ENTWÜRFE, die Uwe (Inhaber) prüft, ändert und freigibt.

AUFTRAG #${a.id}: ${a.beschreibung}
Zielgruppe: ${a.zielgruppe.name} · ${a.land}
Anzahl: genau ${a.anzahl} Stück · Umfang: ${a.umfang === 'beides' ? 'organisch und bezahlt (etwa zwei Drittel organisch)' : a.umfang === 'organisch' ? 'nur organische Beiträge' : 'nur Anzeigen'}
Plattformen: ${a.plattformen.join(', ')}${a.thema ? `\nThema: ${a.thema}` : ''}
Zielseite der Links: https://vecom-design.it${a.zielseite} — der kostenlose Website-Check (zwölf Punkte als Ampel in Sekunden, ohne Anmeldung;
danach auf Wunsch die ausführliche Analyse per E-Mail, und wer noch keine Website hat, findet dort den Weg zum Preis).${a.paket ? `

KAMPAGNEN-PAKET (Ein-Klick-Kampagne): genau diese Mischung, in dieser Reihenfolge — ${paketText(a)}.
Alle Stücke zusammen erzählen eine Kampagne: ein roter Faden (das wichtigste Problem des Profils), aber jedes Stück mit eigenem Blickwinkel.` : ''}

ERLAUBTE FORMATE (format → plattformen)
${Object.entries(a.formate).map(([k, v]) => `- ${k} (${v.wort}, ${v.art}): ${v.plattformen.join(', ')}`).join('\n')}
Verteile die Stücke sinnvoll über Plattformen und Formate; kein Format mehr als dreimal.

ÜBER VECOM
${anbieter(a.land)}

FELDER JE FORMAT (felder)
- beitrag: hook (erste Zeile, ≤${g.hook ?? 150}), text (≤${g.text ?? 2200}, Absätze mit Leerzeile), hashtags (3–8, ohne #), cta
- karussell: hook, folien 4–8 [{titel ≤${g.folie_titel ?? 60}, text ≤${g.folie_text ?? 250}}], text (Begleittext), hashtags, cta
- reel: hook (die ersten 2 Sekunden), szenen 4–8 [{sekunden, bild (was man sieht), einblendung (Text im Bild, ≤6 Wörter), sprecher}], text, hashtags, cta — 15–45 Sekunden gesamt
- story: folien 2–4 [{titel, text}], cta (Sticker-Text)
- telegram: text (≤${g.telegram ?? 1024}) — IMMER zweisprachig, weil der Kanal deutsch zuerst ist: „🇩🇪 “ + deutscher Text, Leerzeile, „🇮🇹 “ + derselbe Inhalt auf Italienisch (zusammen ≤${g.telegram ?? 1024}); knopf (≤${g.knopf ?? 40}) auf Deutsch
- profil (Google-Unternehmensprofil von Vecom): text (≤${g.profil ?? 1500}), cta
- meta_anzeige: primaertexte 2–3 Varianten (das Wichtige in den ersten ${g.primaertext_sichtbar ?? 125} Zeichen), ueberschriften 3–5 (≤${g.meta_ueberschrift_empf ?? 40}), beschreibung (≤30), cta = einer von ${Object.keys(a.meta_cta ?? {}).join(', ')}
- google_anzeige: ueberschriften 10–15 (JEDE ≤${g.g_ueberschrift ?? 30} Zeichen — zähle nach!), beschreibungen 4 (JEDE ≤${g.g_beschreibung ?? 90}), pfad1/pfad2 (≤15, ohne Leerzeichen),
  keywords 8–15 (ohne Match-Zeichen; Wörter, die jemand tippt, der eine Website kaufen will — Branche + Ort/Region + Absicht),
  ausschluesse 10–25 ausschließende Keywords in der Kundensprache (wer nur lernen, gratis basteln, einen Job oder Vorlagen sucht: z. B. „gratis“, „corso“, „lavoro“, „tutorial“, „wordpress“, „template“ bzw. „kostenlos“, „Kurs“, „Job“, „Vorlage“, „selber machen“)

REGELN — unbedingt
- Sprache: ${sprache} (Ausnahme: Format telegram ist deutsch und italienisch, siehe oben). titel, bildidee und begruendung auf Deutsch (für Uwe).
- Grundlage ist das freigegebene Zielgruppen-Profil unten: seine Probleme, Wünsche, Einwände, Fragen und Botschaften. Jedes Stück greift genau EINEN Punkt daraus auf.
- Zahlen und Fakten nur aus dem Profil, den Funden unten oder „Über Vecom“ — mit Herkunft im Text, wo es passt („laut FIPE 2025“). Nichts erfinden: keine Kundenstimmen, keine Ergebnisse, keine Rabatte, keine Fristen, die nicht belegt sind.
- Benutzte Funde in fund_ids eintragen (ihre id). Gemerkte Funde zuerst.
- Keine Links und keine Telefonnummern in die Texte — den eigenen Link setzt die Verwaltung bei der Freigabe. Auf Instagram/TikTok im Aufruf „Link in Bio“ verwenden.
- Ton: ruhig, konkret, respektvoll; Nutzen vor Technik. Höchstens zwei Emojis je Stück, keine in Anzeigen-Überschriften. Keine Superlative („il migliore“), keine Garantien, keine künstliche Eile, keine Namen von Mitbewerbern.
- Anzeigen: keine Aussagen, die persönliche Merkmale oder Notlagen unterstellen (Meta-Richtlinie) — „Per chi ha un ristorante …“ statt „Il tuo ristorante sta fallendo?“.
- KUNDENWEG des Profils (profil.kundenweg) bestimmt den roten Faden: ${a.kommentar_automatik
    ? 'ist er „kommentar“, enden die Reels und Beiträge auf Instagram/Facebook mit „Kommentiere STICHWORT“ (Stichwort aus dem Profil, in der Kundensprache; fehlt es: ' + (a.land === 'DE' ? 'CHECK' : 'SITO') + ') — die Antwort mit dem Link kommt automatisch; '
    : 'ist er „kommentar“, nutze vorerst den Link-Aufruf (die automatische Antwort ist noch nicht eingeschaltet); '}bei „demo“ wird die kostenlose Demo-Vorschau angeboten (auf Anfrage), sonst der Website-Check.
- Nie zu Kaltakquise per E-Mail, WhatsApp oder Anruf auffordern. JEDER Aufruf (cta, knopf, Anzeigen-Knopf) führt zum kostenlosen Website-Check
  — z. B. ${a.land === 'DE' ? '„Website kostenlos prüfen“, „Jetzt kostenlos testen“' : '„Verifichi gratis il suo sito“, „Analisi gratuita“'}; bei Meta-Anzeigen cta LEARN_MORE oder SIGN_UP, wenn passend.
- bildidee: ein konkretes Motiv aus dem echten Alltag der Branche, ruhiges Licht, keine Stockfoto-Klischees; Text im Bild höchstens 5 Wörter, groß und kontrastreich.
- bild_prompt: dasselbe Motiv als ENGLISCHER Prompt für einen Bildgenerator (Nano Banana Pro bzw. Veo): Motiv, Ort, Menschen (ohne bekannte Gesichter), Licht, Perspektive, Brennweite, Stimmung; fotorealistisch, keine Marken oder Logos; Text im Bild nur, wenn er trägt — wörtlich in Anführungszeichen, höchstens 5 Wörter. Bei Reels die Kernszene beschreiben.
- begruendung: 1–2 Sätze — welcher Punkt des Profils, warum dieses Format auf dieser Plattform.
- uebersetzung: ${a.land === 'DE' ? 'leer lassen ("") — das Stück ist schon deutsch.' : 'das ganze Stück auf Deutsch, damit Uwe es lesen kann (sinngemäß, gleiche Reihenfolge: Hook, Text bzw. Folien/Szenen, Aufruf; bei Anzeigen jede Variante und Überschrift in einer eigenen Zeile). Nur zum Lesen — gepostet wird das Original.'}
- Nicht wiederholen, was in „bisherige_titel“ steht.
- zusammenfassung: 2–3 Sätze auf Deutsch für Uwe.

ZIELGRUPPEN-PROFIL (freigegeben)
${JSON.stringify(a.zielgruppe.profil ?? {}, null, 1).slice(0, 40_000)}

FUNDE (mit id)
${JSON.stringify(a.funde ?? [], null, 1).slice(0, 30_000)}

BISHERIGE TITEL
${JSON.stringify(a.bisherige_titel ?? [])}`;
}

/** Der Auftrag an Claude — ein Text, alles drin, was er braucht. */
export function auftragText(a: Auftrag): string {
  const sprache = a.land === 'DE' ? 'Deutsch mit „Sie“' : 'Italienisch mit „Lei“';
  const ziele = a.zielgruppen_fuer.length
    ? a.zielgruppen_fuer.map((z) => `${z.branche} (${z.name})`).join(', ')
    : '— keine (nur Funde liefern, zielgruppen = [])';
  return `Du recherchierst Marketing-Grundlagen für Vecom Design. Ergebnis sind ENTWÜRFE, die Uwe (Inhaber) prüft und freigibt.

AUFTRAG #${a.id}: ${a.beschreibung}
Land: ${a.land}
Zielgruppen-Profile liefern für: ${ziele}
Dazu 8–16 neue Funde (Themen, Trends, häufige Fragen, Wettbewerb, Plattformen) für ${a.branche ? 'diese Branche' : 'die Branchen in den Daten'} in diesem Land.

ÜBER VECOM
${anbieter(a.land)}

VORGEHEN
1. Lies die Daten unten: eigene Zahlen je Branche (geprüfte Betriebe, ohne Website, häufigste Mängel, Kampagnen), vorhandene Profile, letzte Funde.
2. Recherchiere mit WebSearch und WebFetch — mindestens 10 Suchen, ruhig auf Italienisch bzw. Deutsch. Bevorzugt: Primärquellen
   (Istat/Destatis, Verbände wie FIPE, Federalberghi, Coldiretti, Confartigianato, DEHOGA, ZDH; Behörden; Hilfeseiten von Google, Meta,
   Booking, Airbnb), dann Fachpresse. Agentur-Blogs nur, wenn nichts Besseres da ist, und dann als solche kennzeichnen.
   Aktuell zuerst: Quellen aus den letzten 18 Monaten; ältere nur mit Jahreszahl.
3. Öffne die wichtigsten Quellen mit WebFetch und übernimm Zahlen genau so, wie sie dort stehen (mit Jahr und Bezug).

REGELN — unbedingt
- Jede Zahl und jede Behauptung stammt aus einer Quelle in „quellen“ oder aus den eigenen Vecom-Daten unten (dann „Vecom-Daten“ dazuschreiben).
  Was du nur vermutest, endet mit „(Annahme, prüfen)“. Erfinde nie Zahlen und nie URLs — nur Adressen, die du wirklich gesehen hast.
- Keine Personendaten: keine Namen von Betrieben oder Personen, keine Kontaktdaten.
- Nie Kaltakquise per E-Mail, WhatsApp, SMS, PEC, Anruf oder Brief empfehlen (Art. 130 Codice Privacy, § 7 UWG). Keine Rechtsberatung,
  keine Umsatzversprechen, keine Preis-Superlative.
- Sprache: Analyse auf Deutsch (titel, kurz, ansprache, probleme, wuensche, kanaele, organisch, bezahlt.zielgruppe/budget/hinweise,
  Fund-Titel und -Text). Was Kunden lesen oder tippen auf ${sprache}: einwaende (als wörtliches Zitat), fragen, suchbegriffe,
  botschaften, bezahlt.keywords.
- de (deutsche Fassung, damit Uwe es lesen kann): ${a.land === 'IT'
    ? 'einwaende, fragen, suchbegriffe, botschaften und keywords (= bezahlt.keywords) je Eintrag sinngemäß auf Deutsch — gleiche Reihenfolge, gleiche Anzahl wie das italienische Original.'
    : 'alle Listen leer ([]) — das Profil ist schon deutsch.'}
- Botschaften: kurz, konkret, mit Vecom-Fakten (Preis, Domain gehört dem Kunden, gratis Website-Check) — nichts versprechen,
  was oben nicht steht.
- Suchbegriffe und Keywords sind Vorschläge: Suchvolumen nicht behaupten; in bezahlt.budget auf den Keyword-Planer verweisen.
- Meta-Zielgruppen: nur Merkmale nennen, die es dort gibt, und „Verfügbarkeit im Werbeanzeigenmanager prüfen“ dazuschreiben.
- Grenzen: titel ≤160 Zeichen, kurz ≤1200, ansprache ≤800; probleme 3–12, wuensche/einwaende/fragen/organisch ≤12,
  suchbegriffe ≤20, kanaele/botschaften ≤10, keywords ≤25 (je ≤80 Zeichen); bezahlt.zielgruppe/hinweise ≤800, budget ≤400.
  Fund: titel ≤200, text ≤1500, relevanz 1–5 (5 = Vecom sollte sofort etwas daraus machen), 1–8 Quellen.
  branche = Schlüssel aus „wortschatz“ (z. B. restaurant) oder leer; land = ${a.land}.
- Vorhandenes Profil: überarbeiten statt neu erfinden — Tragendes behalten, Veraltetes ersetzen, Neues ergänzen; immer das vollständige Profil liefern.
- kundenweg: der Weg, auf dem diese Zielgruppe am wahrscheinlichsten selbst anfragt — begründet mit Recherche oder Vecom-Daten:
  kommentar (Reel/Beitrag „Kommentiere STICHWORT“, die Antwort mit dem Link kommt automatisch), website_check (kostenloser Website-Check),
  demo (kostenlose Demo-Vorschau der neuen Startseite, auf Anfrage), anzeige (Meta-Anzeige mit Sofortformular, ortsgenau),
  partner (Empfehlung über Steuerberater/commercialisti, Fotografen, Druckereien, Großhandel), google_profil (Google-Unternehmensprofil, 89 €).
  warum (Deutsch, ≤400), angebot (Deutsch, ≤200: was der Betrieb konkret bekommt), stichwort (EIN Wort in Großbuchstaben in der
  Kundensprache für die Kommentar-Aktion, z. B. ${a.land === 'DE' ? 'CHECK, WEBSITE, DEMO' : 'SITO, ANALISI, DEMO'}), zweiter (zweitbester Weg, anderer als weg).
  Nie Kaltakquise als Weg.
- Funde, deren Titel schon in „letzte_funde“ steht, nicht noch einmal liefern.
- zusammenfassung: 2–4 Sätze auf Deutsch für Uwe — was ist neu, was ist am wichtigsten.

VORHANDENE PROFILE
${JSON.stringify(a.vorhandene_profile ?? {}, null, 1).slice(0, 40_000)}

DATEN (ohne Personen)
${JSON.stringify(a.daten ?? {}, null, 1).slice(0, 60_000)}`;
}

/** Deutsche Fassung nachholen (Marketing-Studio 5): italienische Profile und Inhalte, die vor dem Umbau entstanden. */
type UebersetzenAuftrag = {
  id: number; art: 'uebersetzen'; beschreibung: string;
  profile: { id: number; titel: string; listen: Record<string, string[]> }[];
  inhalte: { id: number; format: string; felder: Record<string, unknown> }[];
  /** Texte, die Partner für ihre Empfehlungsseite in ihrer Sprache schreiben (03.10.2026, E4). */
  partnerseiten?: { id: number; hash: string; von: string; nach: string[]; texte: Record<string, string> }[];
};

const SEITENTEXTE = { type: 'object', properties: { titel: { type: 'string' }, lead: { type: 'string' }, p1: { type: 'string' }, p2: { type: 'string' }, p3: { type: 'string' } } };

export const SCHEMA_UEBERSETZEN = {
  type: 'object',
  required: ['profile', 'inhalte'],
  properties: {
    profile: { type: 'array', items: { type: 'object', required: ['id', 'de'], properties: {
      id: { type: 'integer' },
      de: { type: 'object', properties: { einwaende: LISTE, fragen: LISTE, suchbegriffe: LISTE, botschaften: LISTE, keywords: LISTE } },
    } } },
    inhalte: { type: 'array', items: { type: 'object', required: ['id', 'uebersetzung'], properties: { id: { type: 'integer' }, uebersetzung: { type: 'string' } } } },
    partnerseiten: { type: 'array', items: { type: 'object', required: ['id', 'texte'], properties: {
      id: { type: 'integer' }, texte: { type: 'object', properties: { it: SEITENTEXTE, de: SEITENTEXTE, en: SEITENTEXTE } },
    } } },
  },
};

export function uebersetzenText(a: UebersetzenAuftrag): string {
  return `Du übersetzt Marketing-Texte von Vecom Design aus dem Italienischen ins Deutsche — nur damit Uwe (Inhaber, Deutscher) sie lesen kann.
Gepostet wird weiterhin das italienische Original; ändere also nichts am Inhalt, erfinde nichts dazu, lass nichts weg.

AUFTRAG #${a.id}: ${a.beschreibung}
Keine Recherche nötig — nur übersetzen.

PROFILE: je Profil die Listen (einwaende, fragen, suchbegriffe, botschaften, keywords) Eintrag für Eintrag sinngemäß auf Deutsch —
gleiche Reihenfolge, gleiche Anzahl. Suchbegriffe und Keywords wörtlich-sinngemäß (damit Uwe versteht, wonach gesucht wird).
${JSON.stringify(a.profile ?? [], null, 1).slice(0, 60_000)}

INHALTE: je Stück das ganze Stück auf Deutsch als ein Lesetext — gleiche Reihenfolge wie im Original (Hook, Text bzw. Folien oder Szenen,
Aufruf; bei Anzeigen jede Variante und jede Überschrift in einer eigenen Zeile). Absätze mit Leerzeile.
${JSON.stringify(a.inhalte ?? [], null, 1).slice(0, 120_000)}

${(a.partnerseiten ?? []).length ? `PARTNERSEITEN: Texte, die ein Partner von Vecom Design für seine eigene Empfehlungsseite in seiner Sprache („von“)
geschrieben hat. Übersetze jede Seite in jede Sprache aus „nach“ — natürlich, werbend, im selben Ton und in der Du-/Sie-/Lei-Form, die dort üblich ist;
nichts dazuerfinden, nichts weglassen, keine Links. Höchstlängen: titel 80, lead 320, p1–p3 je 100 Zeichen. Leere Felder bleiben leer.
${JSON.stringify((a.partnerseiten ?? []).map(({ id, von, nach, texte }) => ({ id, von, nach, texte })), null, 1).slice(0, 40_000)}

` : ''}Liefere für jedes Profil, jedes Stück${(a.partnerseiten ?? []).length ? ' und jede Partnerseite' : ''} oben einen Eintrag mit seiner id.`;
}

async function uebersetzenLauf(a: UebersetzenAuftrag): Promise<void> {
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — Claude übersetzt`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: 0, text: a.beschreibung }).catch(() => {});
  const ordner = datenOrdner('marketing');
  try {
    const roh = await claudeAusfuehren(uebersetzenText(a), ordner, SCHEMA_UEBERSETZEN);
    writeFileSync(join(ordner, `auftrag-${a.id}.json`), roh);
    const innen = innenLesen(roh);
    if (!innen || !Array.isArray(innen.profile) || !Array.isArray(innen.inhalte)) throw new Error('Ergebnis ohne profile/inhalte.');
    // Die Kennung der Vorlage geht unverändert zurück: Hat der Partner inzwischen neu geschrieben, verwirft der Server die Übersetzung.
    const seiten = (Array.isArray(innen.partnerseiten) ? innen.partnerseiten : []).slice(0, 20)
      .map((s: { id: number; texte: unknown }) => ({ ...s, hash: (a.partnerseiten ?? []).find((q) => q.id === s.id)?.hash ?? '' }));
    const j = await api('marketing_uebersetzung', { profile: innen.profile.slice(0, 20), inhalte: innen.inhalte.slice(0, 100), partnerseiten: seiten });
    const fehler: string[] = j.fehler ?? [];
    const zahl = (j.profile ?? 0) + (j.inhalte ?? 0) + (j.partnerseiten ?? 0);
    await api('marketing_auftrag_melden', { id: a.id, ok: zahl > 0, zielgruppen: j.profile ?? 0, inhalte: j.inhalte ?? 0,
      text: fehler.length ? 'Übersprungen: ' + fehler.join('; ') : (zahl > 0 ? '' : 'Claude hat nichts Verwertbares geliefert.') });
    log.info('marketing', `Auftrag #${a.id} fertig: ${j.profile ?? 0} Profile, ${j.inhalte ?? 0} Inhalte auf Deutsch`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
}

/** Wo Claude Code liegt: CLAUDE_CLI, sonst der übliche Ort, sonst PATH. */
function claudePfad(): string {
  const env = process.env.CLAUDE_CLI;
  if (env && existsSync(env)) return env;
  const ueblich = join(process.env.USERPROFILE ?? process.env.HOME ?? '', '.local', 'bin', process.platform === 'win32' ? 'claude.exe' : 'claude');
  return existsSync(ueblich) ? ueblich : 'claude';
}

type Ergebnis = { zielgruppen: Record<string, unknown>[]; funde: Record<string, unknown>[]; zusammenfassung: string };

/** Das innere JSON aus der Antwort von „claude -p --output-format json“ (strukturierte Ausgabe oder Text). */
export function innenLesen(roh: string): any {
  let aussen: any;
  try { aussen = JSON.parse(roh.trim()); } catch { throw new Error('Claude hat kein JSON geliefert: ' + roh.slice(0, 300)); }
  if (aussen?.is_error || (aussen?.subtype && aussen.subtype !== 'success')) {
    throw new Error('Claude hat abgebrochen: ' + String(aussen?.result ?? aussen?.subtype ?? 'unbekannt').slice(0, 400));
  }
  let innen: any = aussen?.structured_output ?? null;
  if (!innen && typeof aussen?.result === 'string') {
    const t = aussen.result;
    const zaun = t.match(/```(?:json)?\s*([\s\S]*?)```/);
    const kern = zaun ? zaun[1] : t.slice(t.indexOf('{'), t.lastIndexOf('}') + 1);
    try { innen = JSON.parse(kern); } catch { throw new Error('Ergebnis nicht lesbar: ' + t.slice(0, 300)); }
  }
  return innen;
}

/** Aus der Antwort von „claude -p --output-format json“ das Ergebnis lesen. */
export function ergebnisLesen(roh: string): Ergebnis {
  const innen = innenLesen(roh);
  if (!innen || !Array.isArray(innen.zielgruppen) || !Array.isArray(innen.funde)) throw new Error('Ergebnis ohne zielgruppen/funde.');
  return { zielgruppen: innen.zielgruppen, funde: innen.funde, zusammenfassung: String(innen.zusammenfassung ?? '') };
}

function claudeAusfuehren(text: string, ordner: string, schema: object = SCHEMA, werkzeuge = 'WebSearch,WebFetch'): Promise<string> {
  /* Nur Uwes Anmeldung (claude.ai, Max-Abo): ein API-Schlüssel in der Umgebung
     würde stattdessen pro Aufruf abrechnen — Uwe: „soll über mein Abo“. */
  const umgebung: NodeJS.ProcessEnv = { ...process.env };
  for (const k of ['ANTHROPIC_API_KEY', 'ANTHROPIC_AUTH_TOKEN', 'ANTHROPIC_BASE_URL', 'CLAUDE_CODE_USE_BEDROCK', 'CLAUDE_CODE_USE_VERTEX']) delete umgebung[k];
  const argumente = [
    '-p', '--output-format', 'json',
    '--tools', werkzeuge,                     // höchstens WebSearch/WebFetch: keine Dateien, keine Befehle ('' = gar keine)
    ...(werkzeuge !== '' ? ['--allowedTools', werkzeuge] : []),
    '--permission-mode', 'dontAsk',
    '--safe-mode',                            // ohne CLAUDE.md, Skills, Plugins, MCP, Hooks
    '--no-session-persistence',
    '--json-schema', JSON.stringify(schema),
  ];
  return new Promise((fertig, scheitern) => {
    const p = spawn(claudePfad(), argumente, { cwd: ordner, env: umgebung, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
    let aus = '';
    let fehl = '';
    const uhr = setTimeout(() => { p.kill(); scheitern(new Error('Claude hat nach 45 Minuten nicht geantwortet.')); }, ZEITLIMIT_MS);
    p.stdout.on('data', (d) => { aus += d; });
    p.stderr.on('data', (d) => { fehl += d; });
    p.on('error', (e) => { clearTimeout(uhr); scheitern(new Error('Claude Code ließ sich nicht starten: ' + e.message)); });
    p.on('close', (code) => {
      clearTimeout(uhr);
      if (aus.trim() === '') { scheitern(new Error(`Claude Code endete ohne Antwort (Code ${code}): ${fehl.slice(0, 300)}`)); return; }
      fertig(aus);
    });
    p.stdin.end(text, 'utf8');
  });
}

/** Einen wartenden Auftrag abarbeiten. Gibt true zurück, wenn einer da war. */
/** AutoBuild Phase 5: einen Bau-Auftrag (Analyse/Pflichtenheft) erledigen — false = nichts zu tun. */
export async function bauAbholen(): Promise<boolean> {
  return bauLauf({ ausfuehren: claudeAusfuehren, lesen: innenLesen });
}

export async function marketingLauf(): Promise<boolean> {
  const r = await api('marketing_auftrag_holen');
  if (r.auftrag?.art === 'inhalte') { await inhalteLauf(r.auftrag as InhalteAuftrag); return true; }
  /* Marketing-Studio 9: Vorher/Nachher fotografiert der PC selbst — ohne Kie.ai, ohne Credits. */
  if (r.auftrag?.art === 'medien' && r.auftrag.vn) { await vnLauf(r.auftrag as MedienAuftrag & { vn: VnDaten }); return true; }
  /* Marketing-Studio 11: Blender/Unreal auf dem PC — keine Credits; die Verwaltung gibt sie nur im Nachtfenster heraus. */
  if (r.auftrag?.art === 'medien' && r.auftrag.drei_d) {
    await dreiDLauf(r.auftrag as DreiDAuftrag, (a, ordner) => szeneBauen(a, ordner, { ausfuehren: claudeAusfuehren, lesen: innenLesen }));
    return true;
  }
  if (r.auftrag?.art === 'medien') { await medienLauf(r.auftrag as MedienAuftrag); return true; }
  if (r.auftrag?.art === 'uebersetzen') { await uebersetzenLauf(r.auftrag as UebersetzenAuftrag); return true; }
  /* Marketing-Studio 10: Demo-Vorschau — Claude baut eine Startseite, Uwe gibt frei. */
  /* S6: Landingpage je Zielgruppe — nur Text, die Verwaltung baut die Seite. */
  if (r.auftrag?.art === 'seite') { await seiteLauf(r.auftrag as SeiteAuftrag, { ausfuehren: claudeAusfuehren, lesen: innenLesen }); return true; }
  if (r.auftrag?.art === 'demo') { await demoLauf(r.auftrag as DemoAuftrag, { ausfuehren: claudeAusfuehren, lesen: innenLesen }); return true; }
  /* Akquise-CRM D-2: einen Text umformulieren — ohne Werkzeuge, Ergebnis nur als Vorschlag. */
  if (r.auftrag?.art === 'ton') { await tonLauf(r.auftrag as TonAuftrag, { ausfuehren: claudeAusfuehren, lesen: innenLesen }); return true; }
  const a = r.auftrag as Auftrag | null;
  if (!a) return false;
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — Claude recherchiert`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: 0, text: a.beschreibung }).catch(() => {});
  const ordner = datenOrdner('marketing');
  const t0 = Date.now();
  try {
    const roh = await claudeAusfuehren(auftragText(a), ordner);
    writeFileSync(join(ordner, `auftrag-${a.id}.json`), roh);
    const e = ergebnisLesen(roh);
    const erlaubt = new Set(a.zielgruppen_fuer.map((z) => z.branche));
    let zg = 0;
    const fehler: string[] = [];
    for (const z of e.zielgruppen) {
      if (!erlaubt.has(String(z.branche)) || String(z.land).toUpperCase() !== a.land) { fehler.push(`Profil ${z.branche}/${z.land} nicht bestellt`); continue; }
      try { const j = await api('marketing_zielgruppe', { zielgruppe: z }); if (j.ok) zg++; }
      catch (x) { fehler.push(`${z.branche}: ${(x as Error).message}`); }
    }
    let neu = 0; let doppelt = 0;
    for (let i = 0; i < e.funde.length; i += 50) {
      try {
        const j = await api('marketing_recherche', { funde: e.funde.slice(i, i + 50) });
        neu += j.neu ?? 0; doppelt += j.doppelt ?? 0; fehler.push(...(j.fehler ?? []));
      } catch (x) { fehler.push('Funde: ' + (x as Error).message); }
    }
    const min = Math.round((Date.now() - t0) / 60_000);
    const text = [e.zusammenfassung.trim(), doppelt ? `${doppelt} Funde gab es schon.` : '', fehler.length ? 'Übersprungen: ' + fehler.join('; ') : '']
      .filter(Boolean).join('\n');
    await api('marketing_auftrag_melden', { id: a.id, ok: zg + neu > 0, zielgruppen: zg, funde: neu, text: text || (zg + neu > 0 ? '' : 'Claude hat nichts Verwertbares geliefert.') });
    log.info('marketing', `Auftrag #${a.id} fertig nach ${min} Min.: ${zg} Zielgruppen, ${neu} neue Funde${fehler.length ? ` (${fehler.length} übersprungen)` : ''}`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
  return true;
}

/** Content-Studio: schreiben lassen, als Entwürfe abliefern, zurückmelden. */
async function inhalteLauf(a: InhalteAuftrag): Promise<void> {
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — Claude schreibt`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: a.anzahl, text: a.beschreibung }).catch(() => {});
  const ordner = datenOrdner('marketing');
  const t0 = Date.now();
  try {
    if (!a.zielgruppe?.profil) throw new Error('Die Zielgruppe ist nicht (mehr) freigegeben.');
    const roh = await claudeAusfuehren(inhalteText(a), ordner, SCHEMA_INHALTE);
    writeFileSync(join(ordner, `auftrag-${a.id}.json`), roh);
    const innen = innenLesen(roh);
    if (!innen || !Array.isArray(innen.inhalte)) throw new Error('Ergebnis ohne inhalte.');
    const j = await api('marketing_inhalte', { auftrag_id: a.id, inhalte: innen.inhalte.slice(0, 30) });
    const fehler: string[] = j.fehler ?? [];
    const neu = j.neu ?? 0;
    const text = [String(innen.zusammenfassung ?? '').trim(), fehler.length ? 'Übersprungen: ' + fehler.join('; ') : ''].filter(Boolean).join('\n');
    await api('marketing_auftrag_melden', { id: a.id, ok: neu > 0, inhalte: neu, text: text || (neu > 0 ? '' : 'Claude hat nichts Verwertbares geliefert.') });
    log.info('marketing', `Auftrag #${a.id} fertig nach ${Math.round((Date.now() - t0) / 60_000)} Min.: ${neu} Entwürfe${fehler.length ? ` (${fehler.length} übersprungen)` : ''}`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
}
