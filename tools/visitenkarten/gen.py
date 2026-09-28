"""Erzeugt die Hintergründe der Partner-Visitenkarten (4 Stile × Vorder-/Rückseite × Sprache).

Einheit: 1 mm = 10 CSS-px. Leinwand 91 × 61 mm (85 × 55 Endformat + 3 mm Beschnitt).
Gerendert mit Chromium in 600 dpi. Dynamische Teile (Name, Link, Kontakt, QR-Module)
setzt später PHP (PartnerKarten) auf; ihre Lage steht in LAYOUT und wird als JSON exportiert.
"""
import json, base64, sys, os
from playwright.sync_api import sync_playwright

W, H, B = 910, 610, 30           # Leinwand und Beschnitt in px (10 px = 1 mm)
DPI = 450
SCALE = DPI / 25.4 / 10          # px je CSS-px → 2.3622
P = json.load(open('paths.json'))
BB = json.load(open('bbox.json'))
F = 'fonts/'
def b64(fn): return base64.b64encode(open(fn, 'rb').read()).decode()

FONTFACE = f"""
@font-face{{font-family:MS;font-weight:400;src:url(data:font/ttf;base64,{b64(F+'montserrat-400.ttf')})}}
@font-face{{font-family:MS;font-weight:500;src:url(data:font/ttf;base64,{b64(F+'montserrat-500.ttf')})}}
@font-face{{font-family:MS;font-weight:600;src:url(data:font/ttf;base64,{b64(F+'montserrat-600.ttf')})}}
@font-face{{font-family:MS;font-weight:700;src:url(data:font/ttf;base64,{b64(F+'montserrat-700.ttf')})}}
@font-face{{font-family:KS;src:url(data:font/ttf;base64,{b64(F+'kaushan.ttf')})}}
"""

TEXTE = {
    'partner': {'de': 'Partner', 'it': 'Partner', 'en': 'Partner'},
    'ansprech': {'de': 'Dein Ansprechpartner', 'it': 'Il tuo referente', 'en': 'Your contact'},
    'mehr': {'de': 'Mehr erfahren', 'it': 'Scopri di più', 'en': 'Learn more'},
    'linkkontakt': {'de': 'Link & Kontakt', 'it': 'Link e contatto', 'en': 'Link & contact'},
    'scanne': {'de': 'Scanne mich', 'it': 'Scansionami', 'en': 'Scan me'},
    'jetzt': {'de': 'Jetzt scannen', 'it': 'Scansiona ora', 'en': 'Scan now'},
}

def logo_defs(grad):
    marke = ''.join(p.replace('fill="#fff"', f'fill="url(#{grad})"') for p in P['marke'])
    wort = ''.join(p.replace('fill="#fff"', f'fill="url(#{grad})"') for k in 'VECOM' for p in P[k])
    design = ''.join(p.replace('fill="#fff"', f'fill="url(#{grad})"') for p in P['design'])
    return marke, wort, design

GOLD_STOPS = """
  <stop offset="0" stop-color="#8a5a1c"/><stop offset=".22" stop-color="#d9a94f"/>
  <stop offset=".42" stop-color="#fbe7a6"/><stop offset=".55" stop-color="#e2b75c"/>
  <stop offset=".72" stop-color="#b5812f"/><stop offset=".86" stop-color="#f1cf7d"/>
  <stop offset="1" stop-color="#8f6122"/>"""

def defs():
    return f"""<defs>
 <linearGradient id="gold" x1="0" y1="0" x2="1" y2="1">{GOLD_STOPS}</linearGradient>
 <linearGradient id="goldH" x1="0" y1="0" x2="1" y2="0">{GOLD_STOPS}</linearGradient>
 <linearGradient id="goldV" x1="0" y1="0" x2="0" y2="1">
  <stop offset="0" stop-color="#fbe3a0"/><stop offset=".5" stop-color="#d4a24a"/><stop offset="1" stop-color="#8f6122"/></linearGradient>
 <linearGradient id="dunkelgold" x1="0" y1="0" x2="1" y2="1">
  <stop offset="0" stop-color="#2a1f10"/><stop offset=".5" stop-color="#5a4320"/><stop offset="1" stop-color="#1c150b"/></linearGradient>
 <filter id="gebuerstet" x="0" y="0" width="100%" height="100%">
  <feTurbulence type="fractalNoise" baseFrequency="0.004 0.9" numOctaves="2" seed="7" result="n"/>
  <feColorMatrix in="n" type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 .55 -.18" result="h"/>
  <feComposite in="h" in2="SourceGraphic" operator="in" result="hs"/>
  <feBlend in="hs" in2="SourceGraphic" mode="soft-light"/>
 </filter>
 <filter id="korn" x="0" y="0" width="100%" height="100%">
  <feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" seed="3"/>
  <feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 .10 0"/>
 </filter>
 <filter id="papier" x="0" y="0" width="100%" height="100%">
  <feTurbulence type="fractalNoise" baseFrequency=".75" numOctaves="3" seed="11"/>
  <feColorMatrix type="matrix" values="0 0 0 0 .45  0 0 0 0 .38  0 0 0 0 .28  0 0 0 .09 0"/>
 </filter>
 <filter id="glut" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="6"/></filter>
 <filter id="glutweit" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="18"/></filter>
 <filter id="praegung" x="-10%" y="-10%" width="120%" height="120%">
  <feDropShadow dx="0" dy="1.6" stdDeviation="1.4" flood-color="#000" flood-opacity=".75"/>
  <feDropShadow dx="0" dy="-.6" stdDeviation=".3" flood-color="#fff3c8" flood-opacity=".35"/>
 </filter>
 <filter id="praegunghell" x="-10%" y="-10%" width="120%" height="120%">
  <feDropShadow dx="0" dy="1.2" stdDeviation="1" flood-color="#6b4a14" flood-opacity=".35"/>
 </filter>
 <filter id="folie" x="0" y="0" width="100%" height="100%">
  <feTurbulence type="fractalNoise" baseFrequency=".55" numOctaves="3" seed="21" result="n"/>
  <feColorMatrix in="n" type="matrix" values="0 0 0 0 1  0 0 0 0 .95  0 0 0 0 .8  0 0 0 .35 -.05" result="h"/>
  <feComposite in="h" in2="SourceGraphic" operator="in" result="hs"/>
  <feBlend in="hs" in2="SourceGraphic" mode="soft-light"/>
 </filter>
</defs>"""

def band(x0, breite, k=0.62, fill='url(#gold)', op=1, filt='gebuerstet'):
    """Diagonales Band: linke Kante läuft durch (x0, H) nach rechts oben (dx/dy = k)."""
    pts = f"{x0},{H} {x0+breite},{H} {x0+breite+k*H},0 {x0+k*H},0"
    f = f' filter="url(#{filt})"' if filt else ''
    return f'<polygon points="{pts}" fill="{fill}" opacity="{op}"{f}/>'

def linie(x0, k=0.62, farbe='#f6d88e', breite=1.6, glut=True, op=1):
    l = f'<line x1="{x0}" y1="{H}" x2="{x0+k*H}" y2="0" stroke="{farbe}" stroke-width="{breite}" opacity="{op}"/>'
    if glut:
        l = f'<line x1="{x0}" y1="{H}" x2="{x0+k*H}" y2="0" stroke="#ffcf6a" stroke-width="{breite*5}" opacity="{.55*op}" filter="url(#glut)"/>' + l
    return l

def logo_gross(cx, top, breite, hell=False):
    """Stehendes Logo: Marke, VECOM, DESIGN, zentriert. Gibt SVG und Unterkante zurück."""
    m, w, d = logo_defs('gold')
    mx, my, mw, mh = BB['marke']
    s = breite / mw
    ws = breite * 1.5 / 553.6
    ds = ws * 1.08
    f = 'praegunghell' if hell else 'praegung'
    out = f'<g filter="url(#{f})">'
    out += f'<g transform="translate({cx - (mx + mw/2)*s},{top - my*s}) scale({s})">{m}</g>'
    wy = top + mh*s + breite*0.13
    out += f'<g transform="translate({cx - 278.5*ws},{wy - 250*ws}) scale({ws})">{w}</g>'
    dy = wy + 86*ws + breite*0.085
    out += f'<g transform="translate({cx - 279*ds},{dy - 357*ds}) scale({ds})">{d}</g>'
    out += '</g>'
    return out, dy + 33*ds

def logo_quer(x, y, hoehe):
    """Liegendes Logo für die Rückseite: Marke links, VECOM/DESIGN rechts daneben."""
    m, w, d = logo_defs('gold')
    mx, my, mw, mh = BB['marke']
    s = hoehe / mh
    out = f'<g filter="url(#praegung)"><g transform="translate({x - mx*s},{y - my*s}) scale({s})">{m}</g>'
    wx = x + mw*s + hoehe*0.26
    ws = hoehe*0.55 / 86
    out += f'<g transform="translate({wx - 1.7*ws},{y + hoehe*0.06 - 250*ws}) scale({ws})">{w}</g>'
    ds = ws * 1.15
    mitte = wx - 1.7*ws + 278.5*ws
    out += f'<g transform="translate({mitte - 279*ds},{y + hoehe*0.74 - 357*ds}) scale({ds})">{d}</g></g>'
    return out

GLOBUS = '<g fill="none" stroke="{c}" stroke-width="1.6"><circle cx="0" cy="0" r="9"/><ellipse cx="0" cy="0" rx="4" ry="9"/><line x1="-9" y1="0" x2="9" y2="0"/><path d="M-7.6 -4.8 Q0 -2.4 7.6 -4.8 M-7.6 4.8 Q0 2.4 7.6 4.8"/></g>'
BRIEF = '<g fill="none" stroke="{c}" stroke-width="1.6" stroke-linejoin="round"><rect x="-10" y="-7" width="20" height="14" rx="1.5"/><path d="M-10 -6 L0 2 L10 -6"/></g>'
PERSON = '<g fill="none" stroke="{c}" stroke-width="1.6"><circle cx="0" cy="-4" r="4.2"/><path d="M-8 9 Q-8 1.5 0 1.5 Q8 1.5 8 9 Z"/></g>'
def icon(art, x, y, c, s=1.0):
    return f'<g transform="translate({x},{y}) scale({s})">' + {'globus': GLOBUS, 'brief': BRIEF, 'person': PERSON}[art].format(c=c) + '</g>'

def qr_box(x, y, s, stil):
    """Weiße QR-Fläche mit Goldrahmen. Gibt SVG und die Modul-Fläche (für PHP) zurück."""
    r = {'A': 3, 'B': 5, 'C': 10, 'D': 2}[stil]
    rahmen = 4.5 if stil in 'AB' else 3.2
    out = f'<rect x="{x-rahmen}" y="{y-rahmen}" width="{s+2*rahmen}" height="{s+2*rahmen}" rx="{r+2}" fill="url(#gold)" filter="url(#praegung)"/>'
    if stil == 'A':
        out += f'<rect x="{x-rahmen+1.4}" y="{y-rahmen+1.4}" width="{s+2*rahmen-2.8}" height="{s+2*rahmen-2.8}" rx="{r+1}" fill="#0c0b09"/>'
        out += f'<rect x="{x-1.6}" y="{y-1.6}" width="{s+3.2}" height="{s+3.2}" rx="{r}" fill="url(#gold)"/>'
    out += f'<rect x="{x}" y="{y}" width="{s}" height="{s}" rx="{r}" fill="#ffffff"/>'
    rand = s * 0.075
    return out, [x + rand, y + rand, s - 2*rand]

def schwarz_grund(tief='#0a0908', mitte='#17130d'):
    return (f'<radialGradient id="grund" cx=".45" cy=".4" r=".8"><stop offset="0" stop-color="{mitte}"/><stop offset="1" stop-color="{tief}"/></radialGradient>'
            f'<rect width="{W}" height="{H}" fill="url(#grund)"/><rect width="{W}" height="{H}" filter="url(#korn)"/>')

# ---------------------------------------------------------------------------
# Die vier Stile
# ---------------------------------------------------------------------------
def front(stil):
    g = ''
    hell = stil == 'D'
    if stil == 'A':
        g += schwarz_grund()
        g += band(-250, 150, fill='url(#dunkelgold)', op=.9)
        g += band(-150, 70, op=1)
        g += band(-80, 22, fill='url(#dunkelgold)', op=.85, filt=None)
        g += linie(-150) + linie(-80, breite=1.2, op=.8)
        g += band(505, 130, fill='url(#dunkelgold)', op=.95)
        g += band(640, 44, op=.95)
        g += band(700, 260, fill='url(#dunkelgold)', op=.9)
        g += linie(505, op=.7) + linie(640) + linie(684, breite=1.2, op=.8)
    elif stil == 'B':
        g += schwarz_grund('#080706', '#15120d')
        # dunkle Facetten links und rechts
        g += f'<polygon points="0,0 190,0 30,300 0,300" fill="#1d1a14" opacity=".75"/>'
        g += f'<polygon points="0,300 30,300 215,{H} 0,{H}" fill="#14110c" opacity=".8"/>'
        g += f'<polygon points="{W},0 700,0 {W-40},290 {W},290" fill="#1c1813" opacity=".7"/>'
        # goldene Winkel
        for pts in [f'200,0 40,300 230,{H}', f'170,0 10,300 200,{H}']:
            g += f'<polyline points="{pts}" fill="none" stroke="#ffcf6a" stroke-width="7" opacity=".35" filter="url(#glut)"/>'
            g += f'<polyline points="{pts}" fill="none" stroke="url(#gold)" stroke-width="1.8"/>'
        for pts in [f'690,0 {W-60},290 700,{H}', f'720,0 {W-30},290 730,{H}']:
            g += f'<polyline points="{pts}" fill="none" stroke="#ffcf6a" stroke-width="7" opacity=".3" filter="url(#glut)"/>'
            g += f'<polyline points="{pts}" fill="none" stroke="url(#gold)" stroke-width="1.8"/>'
        g += f'<polygon points="{W},{H-190} {W},{H} {W-230},{H}" fill="url(#gold)" filter="url(#gebuerstet)"/>'
        g += f'<polyline points="{W-265},{H} {W},{H-225}" stroke="url(#gold)" stroke-width="1.6" fill="none"/>'
    elif stil == 'C':
        g += schwarz_grund('#070605', '#15110b')
        for x0, op, br in [(-420, .9, 2), (-380, .5, 1.2), (560, .8, 2), (610, .5, 1.2), (760, .9, 2.2)]:
            g += f'<line x1="{x0}" y1="{H}" x2="{x0+0.95*H}" y2="0" stroke="#ffc85c" stroke-width="{br*14}" opacity="{op*.35}" filter="url(#glutweit)"/>'
            g += linie(x0, k=0.95, breite=br, op=op)
        g += band(-470, 60, k=0.95, fill='url(#dunkelgold)', op=.35, filt=None)
        g += band(640, 90, k=0.95, fill='url(#dunkelgold)', op=.35, filt=None)
        # Goldkante (nach dem Schnitt eine feine Goldlinie am Rand)
        g += f'<rect x="{B-11}" y="{B-11}" width="{W-2*B+22}" height="{H-2*B+22}" fill="none" stroke="url(#gold)" stroke-width="38"/>'
        g += f'<rect x="{B+2}" y="{B+2}" width="{W-2*B-4}" height="{H-2*B-4}" fill="none" stroke="#ffcf6a" stroke-width="6" opacity=".35" filter="url(#glut)"/>'
    elif stil == 'D':
        g += f'<rect width="{W}" height="{H}" fill="#f4efe6"/><rect width="{W}" height="{H}" filter="url(#papier)"/>'
        g += f'<polygon points="0,0 250,0 0,330" fill="url(#gold)" filter="url(#folie)"/>'
        g += f'<polyline points="275,0 0,362" stroke="url(#gold)" stroke-width="2.4" fill="none"/>'
        g += f'<polyline points="300,0 0,395" stroke="url(#gold)" stroke-width="1.2" fill="none" opacity=".8"/>'
        g += f'<polygon points="{W},{H} {W},{H-250} {W-190},{H}" fill="url(#gold)" filter="url(#folie)"/>'
        g += f'<polyline points="{W-215},{H} {W},{H-283}" stroke="url(#gold)" stroke-width="2.4" fill="none"/>'
    l, unten = logo_gross(W/2, 118, 215, hell=hell)
    g += l
    ly = unten + 20
    g += f'<rect x="{W/2-34}" y="{ly}" width="68" height="2.4" fill="url(#goldH)"/>'
    ty = ly + 44
    if stil == 'A':
        g += (f'<text x="{W/2}" y="{ty}" text-anchor="middle" font-family="MS" font-size="25" font-weight="500" fill="#f5efe2" letter-spacing=".4">'
              f'Web Branding <tspan font-weight="700" fill="#e8b85a">3D</tspan> Digital Experiences.</text>')
    else:
        farbe = '#8a6424' if hell else '#e2b660'
        ls = '3.2' if hell else '.6'
        g += (f'<text x="{W/2}" y="{ty}" text-anchor="middle" font-family="MS" font-size="{22 if hell else 25}" font-weight="500" '
              f'fill="{farbe}" letter-spacing="{ls}">Web Branding 3D Digital Experiences.</text>')
    return g, {}

def back(stil, lang):
    t = {k: v[lang] for k, v in TEXTE.items()}
    g = ''
    hell = stil == 'D'
    lay = {}
    textfarbe = '#1f1a13' if hell else '#f6f1e6'
    gold = '#a57a2c' if hell else '#e6b85c'
    X = 88                                   # linke Spalte
    teiler_x = 505
    qs = 228                                  # QR-Fläche (Kantenlänge)
    if stil == 'A':
        g += schwarz_grund()
        g += band(680, 70, op=.95) + band(760, 220, fill='url(#dunkelgold)', op=.9) + linie(680) + linie(750, breite=1.2, op=.8)
        g += f'<polygon points="{W},{H-240} {W},{H} {W-150},{H}" fill="url(#gold)" filter="url(#gebuerstet)" opacity=".95"/>'
        g += band(-330, 60, fill='url(#dunkelgold)', op=.8)
        g += logo_quer(X, 66, 58)
        y0 = 205
        g += f'<text x="{X}" y="{y0}" font-family="MS" font-size="27" font-weight="700" fill="{gold}">{t["partner"]}</text>'
        g += f'<text x="{X}" y="{y0+32}" font-family="MS" font-size="19" font-weight="500" fill="{textfarbe}" opacity=".9">{t["ansprech"]}</text>'
        g += f'<rect x="{X}" y="{y0+50}" width="56" height="2.4" fill="url(#goldH)"/>'
        lay['name'] = {'x': X, 'y': y0+105, 'size': 30, 'font': 700, 'farbe': textfarbe, 'max': teiler_x - X - 25}
        rows = [(y0+165, 'globus', 'link'), (y0+222, 'brief', 'kontakt')]
        for y, ic, key in rows:
            g += icon(ic, X+12, y-8, gold, 1.25)
            lay[key] = {'x': X+45, 'y': y, 'size': 22, 'font': 500, 'farbe': textfarbe, 'max': teiler_x - X - 60}
        qx, qy = 560, 118
        cap = f'<text x="{qx+qs/2-14}" y="{qy+qs+62}" text-anchor="middle" font-family="MS" font-size="22" font-weight="500" fill="{textfarbe}">{t["mehr"]}</text>'
        cap += f'<path d="M{qx+qs/2+ (len(t["mehr"])*6.3)} {qy+qs+54} h26 m-9 -8 l9 8 l-9 8" fill="none" stroke="{gold}" stroke-width="2.4"/>'
        cap += f'<rect x="{qx+qs/2-40}" y="{qy+qs+84}" width="60" height="2.4" fill="url(#goldH)"/>'
    elif stil == 'B':
        g += schwarz_grund('#080706', '#15120d')
        g += f'<polygon points="{W},0 {W},200 {W-210},0" fill="url(#gold)" filter="url(#gebuerstet)" opacity=".9"/>'
        g += f'<polyline points="{W-245},0 {W},235" stroke="url(#gold)" stroke-width="1.6" fill="none"/>'
        g += f'<polygon points="{W},{H} {W},{H-200} {W-170},{H}" fill="url(#gold)" filter="url(#gebuerstet)"/>'
        g += f'<polygon points="0,{H} 0,{H-150} 190,{H}" fill="url(#gold)" filter="url(#gebuerstet)" opacity=".95"/>'
        g += f'<polyline points="0,{H-185} 225,{H}" stroke="url(#gold)" stroke-width="1.6" fill="none"/>'
        y0 = 150
        g += f'<text x="{X}" y="{y0}" font-family="MS" font-size="38" font-weight="700" fill="{gold}">{t["partner"]}</text>'
        g += f'<text x="{X}" y="{y0+36}" font-family="MS" font-size="21" font-weight="500" fill="{textfarbe}" opacity=".9">{t["ansprech"]}</text>'
        g += f'<rect x="{X}" y="{y0+60}" width="72" height="2.4" fill="url(#goldH)"/>'
        rows = [(y0+128, 'person', 'name'), (y0+190, 'globus', 'link'), (y0+252, 'brief', 'kontakt')]
        for y, ic, key in rows:
            g += icon(ic, X+14, y-8, gold, 1.35)
            lay[key] = {'x': X+52, 'y': y, 'size': 26 if key == 'name' else 22, 'font': 600 if key == 'name' else 500,
                        'farbe': textfarbe, 'max': teiler_x - X - 70}
        qx, qy = 575, 105
        cap = f'<path d="M{qx+18} {qy+qs+88} q-2 -40 34 -52 m-12 -6 l12 6 l-7 11" fill="none" stroke="{gold}" stroke-width="2.4" stroke-linecap="round"/>'
        cap += f'<text x="{qx+72}" y="{qy+qs+66}" font-family="MS" font-size="23" font-weight="600" fill="{gold}">{t["linkkontakt"]}</text>'
        cap += f'<rect x="{qx+118}" y="{qy+qs+86}" width="70" height="2.4" fill="url(#goldH)"/>'
    elif stil == 'C':
        g += schwarz_grund('#070605', '#15110b')
        for x0, op, br in [(520, .8, 2), (570, .5, 1.2), (760, .9, 2.2)]:
            g += f'<line x1="{x0}" y1="{H}" x2="{x0+0.95*H}" y2="0" stroke="#ffc85c" stroke-width="{br*14}" opacity="{op*.3}" filter="url(#glutweit)"/>'
            g += linie(x0, k=0.95, breite=br, op=op)
        g += f'<rect x="{B-11}" y="{B-11}" width="{W-2*B+22}" height="{H-2*B+22}" fill="none" stroke="url(#gold)" stroke-width="38"/>'
        g += f'<rect x="{B+2}" y="{B+2}" width="{W-2*B-4}" height="{H-2*B-4}" fill="none" stroke="#ffcf6a" stroke-width="6" opacity=".3" filter="url(#glut)"/>'
        g += logo_quer(X, 70, 58)
        g += f'<rect x="{X}" y="{160}" width="72" height="2.4" fill="url(#goldH)"/>'
        y0 = 222
        g += f'<text x="{X}" y="{y0}" font-family="MS" font-size="24" font-weight="700" fill="{gold}">{t["partner"]}</text>'
        g += f'<text x="{X}" y="{y0+30}" font-family="MS" font-size="20" font-weight="500" fill="{textfarbe}" opacity=".9">{t["ansprech"]}</text>'
        lay['name'] = {'x': X, 'y': y0+74, 'size': 32, 'font': 700, 'farbe': textfarbe, 'max': teiler_x - X - 25}
        rows = [(y0+140, 'globus', 'link'), (y0+196, 'brief', 'kontakt')]
        for y, ic, key in rows:
            g += icon(ic, X+12, y-8, gold, 1.25)
            lay[key] = {'x': X+45, 'y': y, 'size': 22, 'font': 500, 'farbe': textfarbe, 'max': teiler_x - X - 60}
        qx, qy = 575, 112
        cap = f'<text x="{qx+qs/2+10}" y="{qy+qs+80}" text-anchor="middle" font-family="KS" font-size="40" fill="{gold}" transform="rotate(-6 {qx+qs/2} {qy+qs+70})">{t["scanne"]}</text>'
        cap += f'<path d="M{qx+qs/2-70} {qy+qs+104} q70 -14 150 -38" fill="none" stroke="{gold}" stroke-width="2.4" stroke-linecap="round"/>'
        cap += f'<path d="M{qx+qs+8} {qy+qs+40} q18 -26 6 -58 m-9 8 l9 -8 l6 11" fill="none" stroke="{gold}" stroke-width="2.4" stroke-linecap="round"/>'
    elif stil == 'D':
        g += f'<rect width="{W}" height="{H}" fill="#f4efe6"/><rect width="{W}" height="{H}" filter="url(#papier)"/>'
        g += f'<polygon points="0,0 170,0 0,210" fill="url(#gold)" filter="url(#folie)"/>'
        g += f'<polyline points="195,0 0,242" stroke="url(#gold)" stroke-width="2.4" fill="none"/>'
        g += f'<polygon points="{W},{H} {W},{H-230} {W-175},{H}" fill="url(#gold)" filter="url(#folie)"/>'
        g += f'<polyline points="{W-200},{H} {W},{H-262}" stroke="url(#gold)" stroke-width="2.4" fill="none"/>'
        X = 150
        y0 = 150
        g += f'<text x="{X}" y="{y0}" font-family="MS" font-size="22" font-weight="600" fill="{gold}" letter-spacing="7">{t["partner"].upper()}</text>'
        g += f'<rect x="{X}" y="{y0+22}" width="56" height="2.4" fill="url(#goldH)"/>'
        g += f'<text x="{X}" y="{y0+82}" font-family="MS" font-size="21" font-weight="500" fill="{textfarbe}" opacity=".85">{t["ansprech"]}</text>'
        lay['name'] = {'x': X, 'y': y0+128, 'size': 32, 'font': 600, 'farbe': textfarbe, 'max': teiler_x - X - 20}
        g += f'<rect x="{X}" y="{y0+158}" width="56" height="2.4" fill="url(#goldH)"/>'
        rows = [(y0+220, 'globus', 'link'), (y0+276, 'brief', 'kontakt')]
        for y, ic, key in rows:
            g += icon(ic, X+12, y-8, gold, 1.25)
            lay[key] = {'x': X+45, 'y': y, 'size': 21, 'font': 500, 'farbe': textfarbe, 'max': teiler_x - X - 60}
        qx, qy = 590, 120
        qs = 210
        cap = f'<text x="{qx+qs/2}" y="{qy+qs+58}" text-anchor="middle" font-family="MS" font-size="21" font-weight="600" fill="{textfarbe}" letter-spacing="3.5">{t["jetzt"]}</text>'
        cap += f'<rect x="{qx+qs/2-32}" y="{qy+qs+80}" width="64" height="2.4" fill="url(#goldH)"/>'
    g += f'<rect x="{teiler_x}" y="{120 if stil != "D" else 150}" width="2" height="{330 if stil != "D" else 300}" fill="url(#goldV)"/>'
    box, modul = qr_box(qx, qy, qs, stil)
    g += box + cap
    lay['qr'] = modul
    return g, lay

def seite(stil, art, lang='de', beispiel=False):
    if art == 'vorn':
        inhalt, lay = front(stil)
    else:
        inhalt, lay = back(stil, lang)
    extra = ''
    if beispiel and art == 'hinten':
        for k, txt in [('name', 'Vorname Nachname'), ('link', 'vecom-design.it/p/ABCD12'), ('kontakt', 'kontakt@vecom-design.it')]:
            L = lay[k]
            extra += f'<text x="{L["x"]}" y="{L["y"]}" font-family="MS" font-size="{L["size"]}" font-weight="{L["font"]}" fill="{L["farbe"]}">{txt}</text>'
        x, y, s = lay['qr']
        extra += f'<rect x="{x}" y="{y}" width="{s}" height="{s}" fill="#222" opacity=".25"/>'
    html = f"""<!doctype html><html><head><meta charset="utf-8"><style>{FONTFACE}
html,body{{margin:0;padding:0;background:#000}} svg{{display:block}}</style></head><body>
<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">{defs()}{inhalt}{extra}</svg></body></html>"""
    return html, lay

if __name__ == '__main__':
    out = sys.argv[1] if len(sys.argv) > 1 else 'out'
    beispiel = '--beispiel' in sys.argv
    os.makedirs(out, exist_ok=True)
    layout = {}
    with sync_playwright() as p:
        b = p.chromium.launch()
        pg = b.new_page(viewport={'width': W, 'height': H}, device_scale_factor=SCALE)
        for stil in 'ABCD':
            jobs = [('vorn', 'de')] + [('hinten', l) for l in (['de'] if beispiel else ['de', 'it', 'en'])]
            for art, lang in jobs:
                html, lay = seite(stil, art, lang, beispiel)
                pg.set_content(html)
                pg.wait_for_timeout(250)
                name = f'{stil.lower()}-{art}' + ('' if art == 'vorn' else f'-{lang}')
                pg.screenshot(path=f'{out}/{name}.png', clip={'x': 0, 'y': 0, 'width': W, 'height': H})
                if art == 'hinten':
                    layout[stil.lower()] = lay
                print(name, flush=True)
        b.close()
    json.dump(layout, open(f'{out}/layout.json', 'w'), indent=1)
