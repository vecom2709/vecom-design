"""Erzeugt die Hintergründe der Partner-Werbemittel (Marketing Center, 04.10.2026).

Gleiche Bausteine wie die Visitenkarten (../visitenkarten/gen.py: Logo als Vektor,
Goldverläufe, gebürstetes Metall, vier Stile A–D), aber in den Formaten der
Druckereien. Einheit: 1 mm = 10 CSS-px. Dynamische Teile (Name, Link, Kontakt,
QR-Module) setzt später PHP (WmDruck) auf; ihre Lage steht in layout.json.

Aufruf (im Ordner tools/visitenkarten, wegen paths.json/bbox.json/fonts):
    python3 ../werbemittel/gen.py <ausgabe> [format …]
"""
import json, os, sys
sys.path.insert(0, os.getcwd())
import gen as K                      # die Visitenkarten-Bausteine
from playwright.sync_api import sync_playwright

# Format: Endmaß (mm), Beschnitt (mm), dpi
FORMATE = {
    'flyer_a6': {'b': 105, 'h': 148, 'beschnitt': 3, 'dpi': 300},
    'flyer_a5': {'b': 148, 'h': 210, 'beschnitt': 3, 'dpi': 300},
    # Aufkleber rund Ø 5 cm, Flyeralarm-Datenblatt aufkl_mini_rund_5,0: Datenformat 5,4 × 5,4 cm,
    # Sicherheitsabstand 4 mm — also 2 mm Beschnitt, alles Wichtige im Kreis Ø 42 mm (04.10.2026).
    'aufkleber_50': {'b': 50, 'h': 50, 'beschnitt': 2, 'dpi': 300, 'einseitig': True, 'stile': 'AD'},
    # Roll-up 85 × 200 cm, Flyeralarm-Datenblätter der 85×200-Roll-ups (rollupba/rollupbl_85x200_sydr):
    # Datenformat 87 × 227 cm, unten 25 cm in der Kassette (unsichtbar). 100 dpi reichen für Lesen
    # aus 1–3 m; PHP lädt das Bild nie in GD (zu groß), sondern bettet es so ins PDF (04.10.2026).
    'rollup_85': {'b': 850, 'h': 2250, 'beschnitt': 10, 'dpi': 150, 'einseitig': True, 'stile': 'AD', 'gross': True},
    # Tasse 11 oz, Printful „White Glossy Mug“ (Variante 1320, Sublimation): Rundum-Bild 9 × 3,5 Zoll bei 300 dpi
    # (2700 × 1050 px). Ohne Beschnitt — das Bild IST die Druckfläche; Printful::flaechePruefen vergleicht vor jedem
    # Auftrag das Seitenverhältnis mit dem, was Printful selbst meldet, und sendet bei Abweichung nichts.
    'tasse_11': {'b': 228.6, 'h': 88.9, 'beschnitt': 0, 'dpi': 300, 'einseitig': True, 'stile': 'AD', 'tasse': True},
    # Printful-Geschenke (05.10.2026). Maße: Printfuls Druckflächen, vom Server abgefragt (Printful::druckflaechenHolen,
    # Verwaltung „Printful-Druckflächen“, 05.10.2026 00:30). 'px' = genau diese Pixel; die Bildschirmaufnahme wird darauf
    # gerechnet, weil 1 mm = 10 Einheiten nicht immer auf ganze Pixel aufgeht.
    # Notizbuch (474/12141, 5,5 × 8,5 Zoll): 1725 × 2625 px = Endformat + 1/8 Zoll Beschnitt, Füllung „cover“.
    'notizbuch': {'b': 139.7, 'h': 215.9, 'beschnitt': 3.175, 'dpi': 300, 'px': [1725, 2625], 'stile': 'AD', 'geschenk': 'hoch'},
    # Edelstahlflasche 500 ml (382/10798, weiß): Rundum 2557 × 1582 px.
    'flasche': {'b': 216.5, 'h': 133.9, 'beschnitt': 0, 'dpi': 300, 'px': [2557, 1582], 'einseitig': True, 'stile': 'AD', 'geschenk': 'rund'},
    # Kork-Untersetzer 95 × 95 mm, runde Ecken (611/15662): 1181 × 1181 px = 100 mm → 2,5 mm Beschnitt.
    'untersetzer': {'b': 95, 'h': 95, 'beschnitt': 2.5, 'dpi': 300, 'px': [1181, 1181], 'einseitig': True, 'stile': 'AD', 'geschenk': 'quadrat'},
    # Bio-Baumwollbeutel schwarz (367/10457, DTG): 1500 × 1500 px bei 150 dpi = 25,4 cm. Grund durchsichtig (PNG).
    'beutel': {'b': 254, 'h': 254, 'beschnitt': 0, 'dpi': 150, 'px': [1500, 1500], 'einseitig': True, 'stile': 'A', 'geschenk': 'beutel', 'durchsichtig': True},
}

T = {
    'titel1': {'de': 'Die Website,', 'it': 'Il sito web', 'en': 'The website'},
    'titel2': {'de': 'die Ihr Betrieb verdient.', 'it': 'che la sua attività merita.', 'en': 'your business deserves.'},
    'unter': {'de': 'Webdesign · Logo Design · Branding', 'it': 'Webdesign · Logo Design · Branding', 'en': 'Web design · Logo design · Branding'},
    'p1': {'de': 'Websites nach Maß – für Handy und Computer', 'it': 'Siti su misura – per smartphone e computer', 'en': 'Custom websites – for phone and desktop'},
    'p2': {'de': 'Logo und Markenauftritt aus einer Hand', 'it': 'Logo e immagine coordinata in un’unica mano', 'en': 'Logo and brand identity from one source'},
    'p3': {'de': 'Kostenlose Analyse Ihrer jetzigen Website', 'it': 'Analisi gratuita del suo sito attuale', 'en': 'Free analysis of your current website'},
    'cta1': {'de': 'Kostenlose Website-Analyse', 'it': 'Analisi gratuita del sito', 'en': 'Free website analysis'},
    'cta2': {'de': 'Code scannen und mehr erfahren', 'it': 'Scansioni il codice e scopra di più', 'en': 'Scan the code to learn more'},
    'ansprech': {'de': 'Ihr Ansprechpartner', 'it': 'Il suo referente', 'en': 'Your contact'},
    'jetzt': {'de': 'Jetzt scannen', 'it': 'Scansiona ora', 'en': 'Scan now'},
    'website': {'de': 'Ihre neue Website', 'it': 'Il suo nuovo sito', 'en': 'Your new website'},
    'leistungen': {'de': 'WEB · BRANDING · 3D · DIGITAL', 'it': 'WEB · BRANDING · 3D · DIGITAL', 'en': 'WEB · BRANDING · 3D · DIGITAL'},
}

def grund(stil, W, H, hinten=False):
    """Hintergrund im Stil der Visitenkarten, aufs Format gestreckt."""
    g = ''
    if stil == 'D':
        g += f'<rect width="{W}" height="{H}" fill="#f4efe6"/><rect width="{W}" height="{H}" filter="url(#papier)"/>'
        e = W * (0.24 if hinten else 0.34)
        g += f'<polygon points="0,0 {e},0 0,{e*1.3}" fill="url(#gold)" filter="url(#folie)"/>'
        g += f'<polyline points="{e*1.1},0 0,{e*1.45}" stroke="url(#gold)" stroke-width="3" fill="none"/>'
        g += f'<polygon points="{W},{H} {W},{H-e*1.3} {W-e},{H}" fill="url(#gold)" filter="url(#folie)"/>'
        g += f'<polyline points="{W-e*1.1},{H} {W},{H-e*1.45}" stroke="url(#gold)" stroke-width="3" fill="none"/>'
        return g
    g += K.schwarz_grund()
    if stil == 'A':
        k = 0.62
        # Bänder nur in den Ecken — nie quer durch Text (Lesbarkeit geht vor, Uwe 03.09.2026)
        baender = [(W*0.80, W*0.12, 'url(#dunkelgold)', .95), (W*0.93, W*0.045, 'url(#gold)', .95)]
        if not hinten:
            baender += [(-W*0.78, W*0.16, 'url(#dunkelgold)', .9), (-W*0.64, W*0.07, 'url(#gold)', 1)]
        for x0, br, fill, op in baender:
            g += K.band(x0, br, k=k, fill=fill, op=op, filt='gebuerstet')
        g += K.linie(W*0.93, k=k) + ('' if hinten else K.linie(-W*0.64, k=k))
    elif stil == 'B':
        e = W * 0.3
        g += f'<polygon points="{W},{H-e} {W},{H} {W-e*1.2},{H}" fill="url(#gold)" filter="url(#gebuerstet)"/>'
        g += f'<polyline points="{W-e*1.38},{H} {W},{H-e*1.15}" stroke="url(#gold)" stroke-width="2.4" fill="none"/>'
        g += f'<polygon points="0,0 {e*1.1},0 0,{e*0.9}" fill="url(#gold)" filter="url(#gebuerstet)" opacity=".9"/>'
        g += f'<polyline points="{e*1.28},0 0,{e*1.05}" stroke="url(#gold)" stroke-width="2.4" fill="none"/>'
    elif stil == 'C':
        for x0, op, br in [(-W*0.9, .8, 2.4), (-W*0.85, .5, 1.4), (W*0.55, .8, 2.4), (W*0.62, .5, 1.4)]:
            g += K.linie(x0, k=0.95, breite=br, op=op)
        r = K.B
        g += f'<rect x="{r-12}" y="{r-12}" width="{W-2*r+24}" height="{H-2*r+24}" fill="none" stroke="url(#gold)" stroke-width="44"/>'
        g += f'<rect x="{r+3}" y="{r+3}" width="{W-2*r-6}" height="{H-2*r-6}" fill="none" stroke="#ffcf6a" stroke-width="7" opacity=".35" filter="url(#glut)"/>'
    return g

def text(x, y, s, gr, gewicht, farbe, anker='middle', extra=''):
    return f'<text x="{x}" y="{y}" text-anchor="{anker}" font-family="MS" font-size="{gr}" font-weight="{gewicht}" fill="{farbe}" {extra}>{s}</text>'

def flyer_vorn(stil, lang, W, H):
    hell = stil == 'D'
    hellt, gold = ('#1f1a13', '#9a6f25') if hell else ('#f6f1e6', '#e6b85c')
    g = grund(stil, W, H)
    lw = W * 0.40
    l, unten = K.logo_gross(W/2, H*0.10, lw, hell=hell)
    g += l
    y = unten + W*0.05
    g += f'<rect x="{W/2-W*0.06}" y="{y}" width="{W*0.12}" height="3" fill="url(#goldH)"/>'
    gr = W * 0.062
    # Überschrift (04.10.2026, Marketingcenter Schritt 4): nicht mehr im Bild. Der Partner wählt eine der
    # freigegebenen Überschriften (Texte::WM_TITEL), PHP (WmDruck::titelMalen) setzt sie genau hierhin —
    # Schrift, Größe, Farben und Lage stehen fest, nur der Wortlaut wechselt.
    y += gr * 1.9
    titel = {'x': round(W/2), 'y1': round(y), 'y2': round(y + gr*1.2), 'size': round(gr), 'font': 700,
             'farbe1': hellt, 'farbe2': gold, 'max': round(W*0.84)}
    y += gr * 1.2
    y += gr * 1.3
    g += text(W/2, y, T['unter'][lang], gr*0.42, 500, hellt, extra='letter-spacing="1.5" opacity=".9"')
    # drei Punkte
    y += gr * 1.6
    x = W * 0.16
    for k in ('p1', 'p2', 'p3'):
        g += f'<rect x="{x}" y="{y - gr*0.33}" width="{gr*0.22}" height="{gr*0.22}" transform="rotate(45 {x+gr*0.11} {y-gr*0.22})" fill="url(#gold)"/>'
        g += text(x + gr*0.55, y, T[k][lang], gr*0.42, 500, hellt, anker='start')
        y += gr * 0.95
    # Fuß: Adresse
    g += text(W/2, H - K.B - W*0.07, 'vecom-design.it', gr*0.5, 600, gold, extra='letter-spacing="2"')
    return g, {'titel': titel}

def flyer_hinten(stil, lang, W, H):
    hell = stil == 'D'
    hellt, gold = ('#1f1a13', '#9a6f25') if hell else ('#f6f1e6', '#e6b85c')
    g = grund(stil, W, H, hinten=True)
    lay = {}
    gr = W * 0.062
    y = K.B + H * 0.11
    g += text(W/2, y, T['cta1'][lang], gr*0.78, 700, gold)
    y += gr * 0.95
    g += text(W/2, y, T['cta2'][lang], gr*0.45, 500, hellt, extra='opacity=".9"')
    qs = W * 0.40
    qx, qy = W/2 - qs/2, y + gr * 0.9
    box, modul = K.qr_box(qx, qy, qs, 'B' if stil != 'D' else 'D')
    g += box
    lay['qr'] = modul
    y = qy + qs + gr * 1.05
    g += text(W/2, y, T['jetzt'][lang], gr*0.42, 600, hellt, extra='letter-spacing="3"')
    y += gr * 0.5
    g += f'<rect x="{W/2-W*0.06}" y="{y}" width="{W*0.12}" height="3" fill="url(#goldH)"/>'
    # Ansprechpartner
    y += gr * 1.25
    X = W * 0.16
    g += text(X, y, T['ansprech'][lang], gr*0.42, 500, hellt, anker='start', extra='opacity=".85"')
    y += gr * 0.95
    lay['name'] = {'x': round(X), 'y': round(y), 'size': round(gr*0.62), 'font': 700, 'farbe': hellt, 'max': round(W - 2*X)}
    for key, ic in (('link', 'globus'), ('kontakt', 'brief')):
        y += gr * 0.95
        g += K.icon(ic, X + gr*0.2, y - gr*0.16, gold, gr*0.032)
        lay[key] = {'x': round(X + gr*0.78), 'y': round(y), 'size': round(gr*0.42), 'font': 500, 'farbe': hellt, 'max': round(W - 2*X - gr*0.78)}
    # Fuß: liegendes Logo
    lh = W * 0.065
    g += K.logo_quer(W/2 - lh*2.6, H - K.B - W*0.06 - lh, lh)
    return g, lay

def aufkleber(stil, lang, W, H):
    """Runder Aufkleber: V-Marke, „Ihre neue Website“, Code des Partners, „Jetzt scannen“ — alles im Sicherkreis."""
    hell = stil == 'D'
    hellt, gold = ('#1f1a13', '#9a6f25') if hell else ('#f6f1e6', '#e6b85c')
    cx, cy, r = W/2, H/2, (W - 2*K.B) / 2
    g = (f'<rect width="{W}" height="{H}" fill="#f4efe6"/><rect width="{W}" height="{H}" filter="url(#papier)"/>' if hell
         else K.schwarz_grund())
    # Goldring knapp innerhalb der Schnittkante (Blitzer fallen nicht auf: der Grund läuft bis in den Beschnitt)
    g += f'<circle cx="{cx}" cy="{cy}" r="{r - 14}" fill="none" stroke="url(#gold)" stroke-width="9"/>'
    m, _, _ = K.logo_defs('gold')
    mx, my, mw, mh = K.BB['marke']
    mb = 70; k = mb / mw
    g += f'<g filter="url(#{"praegunghell" if hell else "praegung"})"><g transform="translate({cx - (mx + mw/2)*k},{cy - 200 - my*k}) scale({k})">{m}</g></g>'
    g += text(cx, cy - 110, T['website'][lang], 25, 700, gold, extra='letter-spacing="0.5"')
    qs = 236   # Modulfläche 85 % = 2 cm: der Code ist nie kleiner als 2 cm (Regel der Kette)
    box, modul = K.qr_box(cx - qs/2, cy - 90, qs, 'B' if not hell else 'D')
    g += box
    g += text(cx, cy + 178, T['jetzt'][lang], 23, 700, hellt, extra='letter-spacing="2"')   # Ecken der Zeile im Sicherkreis (4 mm)
    return g, {'qr': modul, 'einseitig': True}

def rollup(stil, lang, W, H):
    """Roll-up: oben Logo, Botschaft, drei Leistungen; Mitte der große Code des Partners; darunter eine
    Platte für seinen Link (setzt PHP). Die unteren 25 cm verschwinden in der Kassette — dort nur Grund."""
    hell = stil == 'D'
    hellt, gold = ('#1f1a13', '#9a6f25') if hell else ('#f6f1e6', '#e6b85c')
    g = (f'<rect width="{W}" height="{H}" fill="#f4efe6"/><rect width="{W}" height="{H}" filter="url(#papier)"/>' if hell
         else K.schwarz_grund())
    B = K.B
    sicht = B + 20000                       # Unterkante des Sichtbaren (Rest steckt in der Kassette)
    # Goldbänder oben und am Sichtende
    g += f'<rect x="0" y="0" width="{W}" height="{B + 260}" fill="url(#goldH)" opacity=".95"/>'
    g += f'<rect x="0" y="{sicht - 420}" width="{W}" height="420" fill="url(#goldH)" opacity=".95"/>'
    l, unten = K.logo_gross(W/2, B + 1000, 3400, hell=hell)
    g += l
    y = unten + 1500
    # Überschrift wie beim Flyer: setzt PHP. Das große Bild lädt PHP nie (GD) — darum gibt es den Grund
    # hinter der Überschrift als eigenen Streifen (Datei {stil}-titelgrund.jpg, Lage 'grund'): PHP schreibt
    # die Überschrift auf den Streifen und legt ihn im PDF deckungsgleich über die Vorlage.
    titel = {'x': round(W/2), 'y1': round(y), 'y2': round(y + 680), 'size': 560, 'font': 700,
             'farbe1': hellt, 'farbe2': gold, 'max': round(W - 2*B - 1000), 'grund': [0, round(y - 640), W, 640 + 680 + 260]}
    y += 680
    y += 520
    g += text(W/2, y, T['unter'][lang], 240, 500, hellt, extra='letter-spacing="8" opacity=".9"')
    y += 900
    for k in ('p1', 'p2', 'p3'):
        g += f'<rect x="{B + 900}" y="{y - 190}" width="130" height="130" transform="rotate(45 {B + 965} {y - 125})" fill="url(#gold)"/>'
        g += text(B + 1250, y, T[k][lang], 250, 500, hellt, anker='start')
        y += 520
    qs = 3700
    qx, qy = W/2 - qs/2, y + 400
    box, modul = K.qr_box(qx, qy, qs, 'B' if not hell else 'D')
    g += box
    y = qy + qs + 750
    g += text(W/2, y, T['jetzt'][lang], 320, 700, gold, extra='letter-spacing="20"')
    # Platte für den Link des Partners (PHP schreibt ihn hinein, mittig, Grundfarbe = Platte)
    py = y + 350; ph = 1050; px = B + 700
    platte = '#ffffff' if hell else '#0b0a08'
    g += f'<rect x="{px}" y="{py}" width="{W - 2*px}" height="{ph}" rx="140" fill="{platte}" stroke="url(#gold)" stroke-width="40"/>'
    lay = {'qr': modul, 'einseitig': True, 'gross': True, 'titel': titel,
           'link': {'x': round(W/2), 'y': round(py + ph*0.66), 'size': 430, 'max': round(W - 2*px - 600), 'farbe': hellt,
                    'grund': platte, 'platte': [round(px + 60), round(py + 60), round(W - 2*px - 120), round(ph - 120)]}}
    g += text(W/2, sicht - 900, 'vecom-design.it', 360, 700, gold, extra='letter-spacing="6"')
    return g, lay

def tasse(stil, lang, W, H):
    """Tasse rundum: links (für Rechtshänder zum Gegenüber gewandt) die Marke, rechts Ansprechpartner und Code.
    Die beiden Enden treffen sich am Henkel — dort 12 mm frei."""
    hell = stil == 'D'
    hellt, gold = ('#1f1a13', '#9a6f25') if hell else ('#f6f1e6', '#e6b85c')
    g = (f'<rect width="{W}" height="{H}" fill="#f4efe6"/><rect width="{W}" height="{H}" filter="url(#papier)"/>' if hell
         else K.schwarz_grund())
    # feine Goldlinien oben und unten über die ganze Breite
    for y in (70, H - 76):
        g += f'<rect x="0" y="{y}" width="{W}" height="6" fill="url(#goldH)"/>'
    # Marke links
    l, unten = K.logo_gross(W * 0.25, 150, 360, hell=hell)
    g += l
    g += text(W * 0.25, unten + 120, T['leistungen'][lang], 30, 600, hellt, extra='letter-spacing="6" opacity=".9"')
    # Trenner in der Mitte (gegenüber dem Henkel)
    g += f'<rect x="{W / 2 - 2}" y="{H * 0.25}" width="4" height="{H * 0.5}" fill="url(#gold)" opacity=".7"/>'
    # Ansprechpartner rechts: Code links, Text rechts daneben
    qs = 300
    qx, qy = W / 2 + 120, H / 2 - qs / 2 - 20
    box, modul = K.qr_box(qx, qy, qs, 'B' if not hell else 'D')
    g += box
    g += text(qx + qs / 2, qy + qs + 66, T['jetzt'][lang].upper(), 26, 700, gold, extra='letter-spacing="5"')
    tx = qx + qs + 70
    g += text(tx, H / 2 - 120, T['ansprech'][lang], 32, 500, hellt, anker='start', extra='opacity=".85"')
    g += f'<rect x="{tx}" y="{H / 2 - 96}" width="70" height="4" fill="url(#goldH)"/>'
    rest = W - 120 - tx
    lay = {'qr': modul, 'einseitig': True,
           'name': {'x': round(tx), 'y': round(H / 2 - 10), 'size': 58, 'font': 700, 'farbe': hellt, 'max': round(rest)},
           'link': {'x': round(tx), 'y': round(H / 2 + 60), 'size': 36, 'font': 600, 'farbe': gold, 'max': round(rest)},
           'kontakt': {'x': round(tx), 'y': round(H / 2 + 118), 'size': 32, 'font': 500, 'farbe': hellt, 'max': round(rest)}}
    return g, lay

def geschenk(stil, lang, W, H, art, form):
    """Printful-Geschenke (05.10.2026). Maße = Printfuls eigene Druckflächen (vom Server abgefragt, siehe FORMATE).
    form: 'hoch' (Notizbuch, vorn Marke, hinten Ansprechpartner), 'rund' (Flasche rundum), 'quadrat'
    (Untersetzer), 'beutel' (schwarzer Stoffbeutel, Grund durchsichtig — der Stoff ist der Grund)."""
    hell = stil == 'D'
    hellt, gold = ('#1f1a13', '#9a6f25') if hell else ('#f6f1e6', '#e6b85c')
    if form == 'beutel':
        g = ''
    else:
        g = (f'<rect width="{W}" height="{H}" fill="#f4efe6"/><rect width="{W}" height="{H}" filter="url(#papier)"/>' if hell else K.schwarz_grund())
    B = K.B
    lay = {'einseitig': form != 'hoch'}
    qstil = 'D' if hell else 'B'

    def stapel(cx, top, breite, qs):
        """Code oben, darunter „Jetzt scannen“, Ansprechpartner, Name, Link, Kontakt — mittig."""
        out = ''
        box, modul = K.qr_box(cx - qs / 2, top, qs, qstil)
        out += box
        y = top + qs + qs * 0.26
        out += text(cx, y, T['jetzt'][lang].upper(), qs * 0.085, 700, gold, extra=f'letter-spacing="{qs*0.018}"')
        y += qs * 0.32
        out += text(cx, y, T['ansprech'][lang], qs * 0.1, 500, hellt, extra='opacity=".85"')
        f = {'name': (y + qs * 0.2, qs * 0.17, 700, hellt), 'link': (y + qs * 0.39, qs * 0.115, 600, gold), 'kontakt': (y + qs * 0.55, qs * 0.1, 500, hellt)}
        felder = {k: {'x': round(cx - breite / 2), 'y': round(v[0]), 'size': round(v[1]), 'font': v[2], 'farbe': v[3], 'max': round(breite), 'mitte': True} for k, v in f.items()}
        return out, modul, felder

    def seitlich(qx, mitte_y, qs, rechts_bis):
        """Code links, Ansprechpartner rechts daneben (wie die Tasse)."""
        out = ''
        qy = mitte_y - qs / 2
        box, modul = K.qr_box(qx, qy, qs, qstil)
        out += box
        out += text(qx + qs / 2, qy + qs + qs * 0.22, T['jetzt'][lang].upper(), qs * 0.087, 700, gold, extra=f'letter-spacing="{qs*0.017}"')
        tx = qx + qs + qs * 0.23
        e = qs / 300                        # Tasse: Code 300, Schrift 58/36/32
        out += text(tx, mitte_y - 100 * e, T['ansprech'][lang], 32 * e, 500, hellt, anker='start', extra='opacity=".85"')
        out += f'<rect x="{tx}" y="{mitte_y - 76 * e}" width="{70 * e}" height="{4 * e}" fill="url(#goldH)"/>'
        rest = rechts_bis - tx
        felder = {'name': {'x': round(tx), 'y': round(mitte_y + 10 * e), 'size': round(58 * e), 'font': 700, 'farbe': hellt, 'max': round(rest)},
                  'link': {'x': round(tx), 'y': round(mitte_y + 80 * e), 'size': round(36 * e), 'font': 600, 'farbe': gold, 'max': round(rest)},
                  'kontakt': {'x': round(tx), 'y': round(mitte_y + 138 * e), 'size': round(32 * e), 'font': 500, 'farbe': hellt, 'max': round(rest)}}
        return out, modul, felder

    if form == 'hoch' and art == 'vorn':     # Notizbuch vorn: Spiralbindung links → Inhalt etwas nach rechts
        cx = W / 2 + W * 0.03
        l, unten = K.logo_gross(cx, H * 0.26, W * 0.46, hell=hell)
        g += l
        g += f'<rect x="{cx - W*0.07}" y="{unten + H*0.045}" width="{W*0.14}" height="{max(3, W*0.004)}" fill="url(#goldH)"/>'
        g += text(cx, unten + H * 0.095, T['leistungen'][lang], W * 0.03, 600, hellt, extra=f'letter-spacing="{W*0.006}" opacity=".9"')
        for y in (B + H * 0.04, H - B - H * 0.04):
            g += f'<rect x="0" y="{y}" width="{W}" height="{max(3, H*0.0025)}" fill="url(#goldH)"/>'
        return g, {}
    if form == 'hoch':                         # Notizbuch hinten
        cx = W / 2 - W * 0.03                  # Rückseite: Bindung rechts
        qs = W * 0.34
        b, modul, felder = stapel(cx, H * 0.24, W * 0.72, qs)
        g += b
        lh = W * 0.055
        g += K.logo_quer(cx - lh * 2.6, H - B - H * 0.1, lh)
        for y in (B + H * 0.04, H - B - H * 0.04):
            g += f'<rect x="0" y="{y}" width="{W}" height="{max(3, H*0.0025)}" fill="url(#goldH)"/>'
        lay.update({'qr': modul, **felder})
        return g, lay
    if form == 'rund':                         # Flasche rundum: links die Marke, rechts Code und Ansprechpartner
        for y in (H * 0.1, H * 0.9):
            g += f'<rect x="0" y="{y}" width="{W}" height="{H*0.006}" fill="url(#goldH)"/>'
        l, unten = K.logo_gross(W * 0.25, H * 0.2, H * 0.36, hell=hell)
        g += l
        g += text(W * 0.25, unten + H * 0.08, T['leistungen'][lang], H * 0.026, 600, hellt, extra=f'letter-spacing="{H*0.005}" opacity=".9"')
        g += f'<rect x="{W / 2 - 2}" y="{H * 0.3}" width="4" height="{H * 0.4}" fill="url(#gold)" opacity=".7"/>'
        b, modul, felder = seitlich(W / 2 + W * 0.05, H * 0.47, 300, W - W * 0.04)    # Code 30 mm — Flasche Ø 7 cm, wenig Wölbung
        g += b
        lay.update({'qr': modul, **felder})
        return g, lay
    if form == 'beutel':                       # 25,4 × 25,4 cm auf schwarzem Stoff: Marke groß, darunter Code + Ansprechpartner
        l, unten = K.logo_gross(W / 2, H * 0.06, W * 0.3, hell=False)
        g += l
        g += text(W / 2, unten + H * 0.06, T['leistungen'][lang], W * 0.022, 600, hellt, extra=f'letter-spacing="{W*0.005}"')
        qs = W * 0.21                          # 53 mm
        b, modul, felder = seitlich(W * 0.12, unten + H * 0.1 + qs / 2 + H * 0.04, qs, W * 0.95)
        g += b
        lay.update({'qr': modul, **felder})
        return g, lay
    # Untersetzer 95 × 95 mm, runde Ecken: Marke oben, Code und Ansprechpartner darunter
    l, unten = K.logo_gross(W / 2, B + H * 0.07, W * 0.15, hell=hell)
    g += l
    qs = W * 0.2                               # 20 mm — Code ≥ 15 mm (QrPruefung)
    b, modul, felder = stapel(W / 2, unten + H * 0.035, W * 0.78, qs)
    g += b
    lay.update({'qr': modul, **felder})
    return g, lay

def seite(fmt, stil, art, lang):
    f = FORMATE[fmt]
    K.B = f['beschnitt'] * 10
    W = round((f['b'] + 2*f['beschnitt']) * 10)
    H = round((f['h'] + 2*f['beschnitt']) * 10)
    K.W, K.H = W, H
    if f.get('gross'):
        inhalt, lay = rollup(stil, lang, W, H)
    elif f.get('tasse'):
        inhalt, lay = tasse(stil, lang, W, H)
    elif f.get('geschenk'):
        inhalt, lay = geschenk(stil, lang, W, H, art, f['geschenk'])
    elif f.get('einseitig'):
        inhalt, lay = aufkleber(stil, lang, W, H)
    else:
        inhalt, lay = (flyer_vorn if art == 'vorn' else flyer_hinten)(stil, lang, W, H)
    html = f"""<!doctype html><html><head><meta charset="utf-8"><style>{K.FONTFACE}
html,body{{margin:0;padding:0;background:{'transparent' if f.get('durchsichtig') else '#000'}}} svg{{display:block}}</style></head><body>
<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">{K.defs()}{inhalt}</svg></body></html>"""
    return html, lay, W, H, f['dpi']

if __name__ == '__main__':
    out = sys.argv[1]
    nur = sys.argv[2:] or list(FORMATE)
    with sync_playwright() as p:
        # Ohne GPU-Rasterung: Bei 150 dpi (Roll-up 5138 × 13406 px) lieferte die GPU teils schwarze Kacheln (04.10.2026).
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium' if os.path.exists('/opt/pw-browsers/chromium') else None,
                              args=['--disable-gpu', '--disable-gpu-rasterization'] if os.environ.get('WM_OHNE_GPU', '1') == '1' else [])
        for fmt in nur:
            os.makedirs(f'{out}/{fmt}', exist_ok=True)
            layout = {}
            for stil in os.environ.get('WM_STILE') or FORMATE[fmt].get('stile', 'ABCD'):
                for art in (('vorn',) if FORMATE[fmt].get('einseitig') else ('vorn', 'hinten')):
                    for lang in ('de', 'it', 'en'):
                        html, lay, W, H, dpi = seite(fmt, stil, art, lang)
                        ziel = f'{out}/{fmt}/{stil.lower()}-{art}-{lang}.png'
                        if FORMATE[fmt].get('gross'):
                            # Großformat in Streifen (04.10.2026): Ein Bild von 5138 × 13406 px am Stück — und auch
                            # Ausschnitte einer riesigen Seite — lieferte Chromium gemessen mit schwarzen, nicht
                            # gezeichneten Kacheln. Darum ist das Fenster nur einen Streifen hoch, die Grafik wird
                            # darunter verschoben, und jeder Streifen ist vollständig sichtbar, bevor er fotografiert wird.
                            from PIL import Image
                            Image.MAX_IMAGE_PIXELS = None
                            STREIFEN = 1200
                            pg = b.new_page(viewport={'width': W, 'height': STREIFEN}, device_scale_factor=dpi / 25.4 / 10)
                            pg.set_content(html.replace('<body>', '<body style="overflow:hidden"><div id="wanne" style="will-change:transform">', 1)
                                                .replace('</body>', '</div></body>', 1))
                            pg.wait_for_timeout(500)
                            teile, y = [], 0
                            while y < H:
                                h = min(STREIFEN, H - y)
                                pg.evaluate(f"document.getElementById('wanne').style.transform = 'translateY(-{y}px)'")
                                pg.wait_for_timeout(700)
                                pfad = f'{ziel}.{y}.png'
                                pg.screenshot(path=pfad, clip={'x': 0, 'y': 0, 'width': W, 'height': h}, timeout=300000)
                                teile.append(pfad); y += h
                            bilder = [Image.open(t).convert('RGB') for t in teile]
                            hoehe = round(H * dpi / 254)
                            ganz = Image.new('RGB', (bilder[0].width, sum(x.height for x in bilder)))
                            yy = 0
                            for x in bilder: ganz.paste(x, (0, yy)); yy += x.height
                            ganz.crop((0, 0, ganz.width, min(ganz.height, hoehe))).save(ziel)
                            for t in teile: os.remove(t)
                        else:
                            pg = b.new_page(viewport={'width': W, 'height': H}, device_scale_factor=dpi / 25.4 / 10)
                            pg.set_content(html); pg.wait_for_timeout(200)
                            pg.screenshot(path=ziel, clip={'x': 0, 'y': 0, 'width': W, 'height': H}, timeout=300000,
                                          omit_background=bool(FORMATE[fmt].get('durchsichtig')))
                        pg.close()
                        if FORMATE[fmt].get('px'):
                            from PIL import Image
                            bild = Image.open(ziel)
                            if bild.size != tuple(FORMATE[fmt]['px']):
                                bild.resize(tuple(FORMATE[fmt]['px']), Image.LANCZOS).save(ziel)
                        if lang == 'de':
                            # Vorderseite bringt die Überschrift, Rückseite Name/Link/Kontakt/Code: beides ins Layout.
                            layout.setdefault(stil.lower(), {}).update(lay)
                            if FORMATE[fmt].get('gross') and 'titel' in lay:
                                from PIL import Image
                                Image.MAX_IMAGE_PIXELS = None
                                gx, gy, gw, gh = lay['titel']['grund']
                                k = dpi / 254
                                y0 = round(gy * k); h0 = round(gh * k)
                                Image.open(ziel).convert('RGB').crop((0, y0, round(gw * k), y0 + h0)).save(f'{out}/{fmt}/{stil.lower()}-titelgrund.png')
                                # Lage auf ganze Pixel gerundet zurückrechnen, damit der Streifen bündig sitzt
                                lay['titel']['grund'] = [0, y0 / k, gw, h0 / k]
                        print(fmt, stil, art, lang, flush=True)
            json.dump({'b': FORMATE[fmt]['b'], 'h': FORMATE[fmt]['h'], 'beschnitt': FORMATE[fmt]['beschnitt'], 'einseitig': bool(FORMATE[fmt].get('einseitig')), 'gross': bool(FORMATE[fmt].get('gross')), **({'durchsichtig': True} if FORMATE[fmt].get('durchsichtig') else {}), **({'px': FORMATE[fmt]['px']} if FORMATE[fmt].get('px') else {}), 'stile': layout}, open(f'{out}/{fmt}/layout.json', 'w'), indent=1)
        b.close()
