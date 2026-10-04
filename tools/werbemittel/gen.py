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
    y += gr * 1.9
    g += text(W/2, y, T['titel1'][lang], gr, 700, hellt)
    y += gr * 1.2
    g += text(W/2, y, T['titel2'][lang], gr, 700, gold)
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
    return g, {}

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

def seite(fmt, stil, art, lang):
    f = FORMATE[fmt]
    K.B = f['beschnitt'] * 10
    W = (f['b'] + 2*f['beschnitt']) * 10
    H = (f['h'] + 2*f['beschnitt']) * 10
    K.W, K.H = W, H
    if f.get('einseitig'):
        inhalt, lay = aufkleber(stil, lang, W, H)
    else:
        inhalt, lay = (flyer_vorn if art == 'vorn' else flyer_hinten)(stil, lang, W, H)
    html = f"""<!doctype html><html><head><meta charset="utf-8"><style>{K.FONTFACE}
html,body{{margin:0;padding:0;background:#000}} svg{{display:block}}</style></head><body>
<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">{K.defs()}{inhalt}</svg></body></html>"""
    return html, lay, W, H, f['dpi']

if __name__ == '__main__':
    out = sys.argv[1]
    nur = sys.argv[2:] or list(FORMATE)
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium' if os.path.exists('/opt/pw-browsers/chromium') else None)
        for fmt in nur:
            os.makedirs(f'{out}/{fmt}', exist_ok=True)
            layout = {}
            for stil in FORMATE[fmt].get('stile', 'ABCD'):
                for art in (('vorn',) if FORMATE[fmt].get('einseitig') else ('vorn', 'hinten')):
                    for lang in ('de', 'it', 'en'):
                        html, lay, W, H, dpi = seite(fmt, stil, art, lang)
                        pg = b.new_page(viewport={'width': W, 'height': H}, device_scale_factor=dpi / 25.4 / 10)
                        pg.set_content(html); pg.wait_for_timeout(200)
                        pg.screenshot(path=f'{out}/{fmt}/{stil.lower()}-{art}-{lang}.png', clip={'x': 0, 'y': 0, 'width': W, 'height': H})
                        pg.close()
                        if lang == 'de' and (art == 'hinten' or FORMATE[fmt].get('einseitig')):
                            layout[stil.lower()] = lay
                        print(fmt, stil, art, lang, flush=True)
            json.dump({'b': FORMATE[fmt]['b'], 'h': FORMATE[fmt]['h'], 'beschnitt': FORMATE[fmt]['beschnitt'], 'einseitig': bool(FORMATE[fmt].get('einseitig')), 'stile': layout}, open(f'{out}/{fmt}/layout.json', 'w'), indent=1)
        b.close()
