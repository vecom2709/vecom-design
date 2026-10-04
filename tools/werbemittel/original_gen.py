"""Die 51 Branchen-Flyer im Stil der Originale, DE/IT/EN (04.10.2026).

Hintergrund: je Flyer das Bild von kie.ai, das aus dem Original ohne jede Schrift neu
aufgebaut wurde (original_bilder.py). Darauf hier: das ECHTE Vecom-Logo (Vektor aus
../visitenkarten), Titel, Schlagworte, Leistungen mit Symbolen, Handschrift-Slogan,
goldener Knopf, weiße QR-Fläche — Lage wie im Original der jeweiligen Stilfamilie a–d.
Der QR-Code und der Link darunter gehören dem Partner: beide setzt PHP (PartnerFlyer)
pro Partner ein; hier stehen nur ihre Plätze (liste.json: q und u).

Format A5 148×210 mm + 3 mm Beschnitt, 300 dpi. Einheit im SVG: 1 mm = 10 px.

Aufruf (im Ordner tools/visitenkarten, wegen paths.json/bbox.json/fonts):
    python3 ../werbemittel/original_gen.py <bilder> <ausgabe> [name …]
"""
import base64, io, json, os, re, sys
sys.path.insert(0, os.getcwd())
HIER = os.path.dirname(os.path.abspath(__file__))
sys.path.append(HIER)
import gen as K
from original_texte import F, FEST
from PIL import Image
from playwright.sync_api import sync_playwright

BR, HO, BE, DPI = 148, 210, 3, 300
W, H = (BR + 2*BE) * 10, (HO + 2*BE) * 10
PX = DPI / 25.4 / 10
RAND = BE*10 + 80                      # 8 mm vom Endformat
GOLD, WEISS, DUNKEL = '#ecc370', '#fbf7ef', '#17120a'
FARBE = {'gold': GOLD, 'text': WEISS, 'titel': GOLD}
def farben(hell):
    """Helle Vorlagen (Arztpraxis) wie im Original: dunkle Schrift, Gold dunkler."""
    FARBE.update({'gold': '#a87a22', 'text': '#13263f', 'titel': '#0f2f57'} if hell else {'gold': GOLD, 'text': WEISS, 'titel': GOLD})
NPM = os.environ.get('LUCIDE', '')     # Ordner lucide-static/icons, falls ein Symbol noch fehlt

def b64(fn): return base64.b64encode(open(fn, 'rb').read()).decode()
OSWALD = (f"@font-face{{font-family:OS;font-weight:700;src:url(data:font/woff2;base64,{b64('fonts/oswald-latin-700-normal.woff2')})}}"
          f"@font-face{{font-family:OS;font-weight:500;src:url(data:font/woff2;base64,{b64('fonts/oswald-latin-500-normal.woff2')})}}")

def symbol(name, cx, cy, gr, farbe, breite=2.0):
    """Lucide-Symbol (24er Raster) zentriert auf cx/cy, gr = Kantenlänge."""
    pfad = os.path.join(HIER, 'symbole', name + '.svg')
    if not os.path.exists(pfad) and NPM:
        os.makedirs(os.path.dirname(pfad), exist_ok=True)
        open(pfad, 'w').write(open(os.path.join(NPM, name + '.svg')).read())
    s = open(pfad).read()
    innen = re.sub(r'(?s)^.*?<svg[^>]*>|</svg>\s*$', '', s)
    innen = re.sub(r'<!--.*?-->', '', innen)
    k = gr / 24
    return (f'<g transform="translate({cx - gr/2:.1f},{cy - gr/2:.1f}) scale({k:.4f})" fill="none" stroke="{farbe}" '
            f'stroke-width="{breite}" stroke-linecap="round" stroke-linejoin="round">{innen}</g>')

def esc(s): return s.replace('&', '&amp;').replace('<', '&lt;')

def t(x, y, s, gr, gew, farbe, anker='start', fam='MS', maxb=None, extra=''):
    m = f' data-max="{maxb:.0f}"' if maxb else ''
    sch = '' if 'filter=' in extra else ' filter="url(#schatten)"'
    return (f'<text x="{x:.1f}" y="{y:.1f}" text-anchor="{anker}" font-family="{fam}" font-size="{gr:.1f}" '
            f'font-weight="{gew}" fill="{farbe}"{m}{sch} {extra}>{esc(s)}</text>')

def zeilen(x, y, s, gr, gew, farbe, abstand, **kw):
    out = ''
    for i, z in enumerate(s.split('\n')):
        out += t(x, y + i*abstand, z, gr, gew, farbe, **kw)
    return out

def logo(cx, top, breite, unter=True, lang='de'):
    g, unten = K.logo_gross(cx, top, breite, hell=FARBE['text'] != WEISS)
    # Hof hinter dem Logo: Schriftzug bleibt auch vor hellem Himmel lesbar (Uwe: Texte gut lesbar)
    hh = (unten - top) * 0.75 + breite * 0.2
    g = (f'<ellipse cx="{cx}" cy="{(top + unten)/2 + breite*0.08:.0f}" rx="{breite*1.15:.0f}" ry="{hh:.0f}" fill="url(#logohof)"/>') + g
    if unter:
        g += t(cx, unten + breite*0.17, FEST['unter'][lang], breite*0.105, 600, GOLD if FARBE['text'] == WEISS else FARBE['text'], anker='middle', extra='letter-spacing="1.5"',
               maxb=min(breite*1.9, 2*(cx - RAND)))
        unten += breite*0.16
    return g, unten

def knopf(x, y, b, h, lang, gr=46):
    return (f'<rect x="{x:.1f}" y="{y:.1f}" width="{b:.1f}" height="{h:.1f}" rx="{h*0.18:.1f}" fill="url(#goldH)" filter="url(#schatten)"/>'
            + t(x + b/2, y + h*0.64, FEST['cta'][lang], gr, 700, DUNKEL, anker='middle', maxb=b*0.9, extra='filter="none"'))

def qr(x, y, s):
    """Weiße Fläche für den Partner-Code. Rückgabe: SVG und q (Pixel der Ausgabe)."""
    svg = (f'<rect x="{x-8}" y="{y-8}" width="{s+16}" height="{s+16}" rx="18" fill="url(#gold)" filter="url(#schatten)"/>'
           f'<rect x="{x}" y="{y}" width="{s}" height="{s}" rx="12" fill="#ffffff"/>')
    return svg, [round(v*PX) for v in (x, y, s, s)]

def u_platz(x, y, gr, anker, farbe=None):
    """Platz für den Partner-Link (PHP schreibt ihn hinein)."""
    farbe = farbe or FARBE['text']
    return {'x': round(x*PX), 'y': round(y*PX), 'gr': round(gr*PX), 'anker': anker, 'farbe': farbe}

def punkt_icon(art, name, cx, cy, r):
    if art == 'voll':      # Stil a/c/d: goldener Kreis, dunkles Symbol
        return (f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="url(#gold)" filter="url(#schatten)"/>'
                + symbol(name, cx, cy, r*1.05, DUNKEL, 2.2))
    innen = 'rgba(255,255,255,.75)' if FARBE['text'] != WEISS else 'rgba(10,8,5,.55)'
    return (f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{innen}" stroke="url(#gold)" stroke-width="4"/>'
            + symbol(name, cx, cy, r*1.0, FARBE['gold'], 1.9))

def titel(x, y, fl, lang, gr, anker='start', maxb=None):
    """„WEBSITES FÜR“ + Titel. Lange Titel brechen wie im Original am „&“ um. Rückgabe: SVG, zusätzliche Höhe."""
    maxb = maxb or (W - 2*RAND)
    s = fl['titel'][lang]
    if '\n' not in s and ' & ' in s and len(s) * gr * 0.47 > maxb * 1.12:
        a, b = s.split(' & ', 1); s = a + ' &\n' + b
    out = t(x, y, FEST['fuer'][lang], gr*0.42, 500, FARBE['text'], anker=anker, fam='OS', extra='letter-spacing="4"')
    zl = s.split('\n')
    for i, z in enumerate(zl):
        out += t(x, y + gr*1.10 + i*gr*0.98, z, gr, 700, FARBE['titel'], anker=anker, fam='OS', maxb=maxb)
    return out, (len(zl) - 1) * gr * 0.98 + gr*0.12   # Abstand: Umlautpunkte (ÄRZTE) nicht an „FÜR“ kleben

def tags(x, y, fl, lang, gr=34):
    if not fl.get('tags'):
        return ''
    s = '   |   '.join(z[lang] for z in fl['tags'])
    return t(x, y, s, gr, 600, FARBE['text'], extra='letter-spacing="2.5"', maxb=W - 2*RAND)

def slogan(x, y, s, gr, anker='middle', farbe=None, dreh=-8):
    farbe = farbe or FARBE['text']
    return f'<g transform="rotate({dreh} {x} {y})">' + zeilen(x, y, s, gr, 400, farbe, gr*1.02, anker=anker, fam='KS') + '</g>'

# ---- die vier Stilfamilien -------------------------------------------------------------

def stil_a(fl, lang):
    g, q, u = '', None, None
    l, unten = logo(W/2, RAND - 20, 340, lang=lang)
    g += l
    qs = 300
    qx, qy = W - RAND - qs, H - RAND - qs - 70
    zwei = '\n' in fl['titel'][lang] or (' & ' in fl['titel'][lang] and len(fl['titel'][lang]) * 150 * 0.47 > (W - 2*RAND) * 1.12)
    y = H * (0.415 if zwei else 0.455)
    s_, z = titel(RAND, y, fl, lang, 150); g += s_
    y += 150 + 105 + z
    n = len(fl['punkte'])
    sp = min(138, (qy - 55 - y) / max(1, n - 1))      # letzter Punkt endet über dem Code
    for name, txt in fl['punkte']:
        g += punkt_icon('voll', name, RAND + 50, y - 20, 48)
        g += t(RAND + 130, y, txt[lang], 60, 500, FARBE['text'], maxb=W - 2*RAND - 130)
        y += sp
    s, q = qr(qx, qy, qs)
    g += s
    kb, kh = qx - RAND - 50, 100
    g += knopf(RAND, qy + qs - kh - 20, kb, kh, lang)
    u = u_platz(W/2, H - RAND + 10, 38, 'mitte')
    return g, q, u

def stil_b(fl, lang):
    g = ''
    l, unten = logo(RAND + 270, RAND - 20, 330, lang=lang)
    g += l
    y = unten + 130
    s_, z = titel(RAND, y, fl, lang, 140, maxb=W*0.70); g += s_
    y += 140 + 70 + z
    if fl.get('sub'):
        g += t(RAND, y, fl['sub'][lang], 58, 600, FARBE['text'], maxb=W*0.70)
    y += 135
    qs = 300
    qx, qy = RAND, H - RAND - qs - 80
    sp = min(112, (qy - 60 - y) / max(1, len(fl['punkte']) - 1))   # letzter Punkt über dem Code
    for name, txt in fl['punkte']:
        g += punkt_icon('ring', name, RAND + 42, y - 18, 40)
        g += t(RAND + 110, y, txt[lang], 50, 500, FARBE['text'], maxb=W*0.58)
        y += sp
    s, q = qr(qx, qy, qs)
    g += s
    lx = qx + qs + 70
    g += f'<rect x="{lx}" y="{qy + 20}" width="3" height="{qs + 40}" fill="url(#goldV)" opacity=".8"/>'
    if fl.get('slogan'):
        g += slogan((lx + W - RAND)/2 + 20, qy + qs*0.5, fl['slogan'][lang], 64)
    u = u_platz(qx, H - RAND + 10, 38, 'links')
    return g, q, u

def stil_c(fl, lang):
    g = ''
    l, unten = logo(RAND + 250, RAND - 20, 290, lang=lang)
    g += l
    if fl.get('slogan'):
        g += slogan(W - RAND - 230, RAND + 70, fl['slogan'][lang], 58)
    y = unten + 110
    s_, z = titel(RAND, y, fl, lang, 135); g += s_
    y += 135 + 58 + z
    g += tags(RAND, y, fl, lang, 38)
    y += 125
    schritte = [128 if '\n' in txt[lang] else 112 for _, txt in fl['punkte']]
    platz = (H * 0.855 - 270 - 70) - 70 - y                 # bis über den Code
    k = min(1.0, platz / max(1, sum(schritte[:-1]) + 50))
    for (name, txt), schritt in zip(fl['punkte'], schritte):
        g += punkt_icon('voll', name, RAND + 46, y - 16, 44)
        teile = txt[lang].split('\n')
        g += t(RAND + 120, y, teile[0], 50, 600, FARBE['text'], maxb=W - 2*RAND - 120)
        if len(teile) > 1:
            g += t(RAND + 120, y + 50, teile[1], 40, 400, FARBE['text'], maxb=W - 2*RAND - 120, extra='opacity=".85"')
        y += schritt * k
    band = H * 0.855
    qs = 270
    qx, qy = RAND, band - qs - 70
    s, q = qr(qx, qy, qs)
    g += s
    kx = qx + qs + 90
    g += symbol('sparkle', qx + qs + 45, qy + qs/2, 34, GOLD, 2)
    g += knopf(kx, qy + qs/2 - 55, W - RAND - kx - 120, 100, lang)
    u = u_platz(kx + (W - RAND - kx - 120)/2, qy + qs/2 + 115, 38, 'mitte')
    # Symbolleiste unten
    g += f'<rect x="0" y="{band}" width="{W}" height="{H - band}" fill="rgba(8,7,5,.82)"/>'
    g += f'<rect x="0" y="{band}" width="{W}" height="2" fill="url(#goldH)" opacity=".5"/>'
    n = len(fl['leiste']); sp = (W - 2*RAND) / n
    for i, (name, txt) in enumerate(fl['leiste']):
        cx = RAND + sp*(i + .5)
        g += symbol(name, cx, band + 90, 62, GOLD, 1.7)
        g += t(cx, band + 175, txt[lang], 28, 600, WEISS, anker='middle', maxb=sp*0.92, extra='letter-spacing="1"')
        if i:
            g += f'<rect x="{RAND + sp*i}" y="{band + 40}" width="2" height="{H - band - 110}" fill="{GOLD}" opacity=".35"/>'
    return g, q, u

def stil_d(fl, lang):
    g = ''
    l, unten = logo(RAND + 250, RAND - 20, 290, lang=lang)
    g += l
    if fl.get('slogan'):
        g += slogan(W - RAND - 230, RAND + 70, fl['slogan'][lang], 58)
    y = H * 0.425
    s_, z = titel(RAND, y - 0, fl, lang, 132); g += s_
    y += 132 + 56 + z
    g += tags(RAND, y, fl, lang, 36)
    y += 118
    qs = 280
    qx, qy = RAND, H - RAND - qs - 75
    sp = min(118, (qy - 80 - y) / max(1, len(fl['punkte']) - 1))
    for name, txt in fl['punkte']:
        g += punkt_icon('voll', name, RAND + 44, y - 4, 42)
        g += zeilen(RAND + 115, y - (12 if '\n' in txt[lang] else -8), txt[lang], 44, 500, FARBE['text'], 50, maxb=W*0.40)
        y += sp
    s, q = qr(qx, qy, qs)
    g += s
    kx = qx + qs + 220
    g += knopf(kx, qy + qs - 120, W - RAND - kx, 100, lang)
    u = u_platz(W/2, H - RAND + 10, 38, 'mitte')
    return g, q, u

STILE = {'a': stil_a, 'b': stil_b, 'c': stil_c, 'd': stil_d}

def bild(pfad):
    im = Image.open(pfad).convert('RGB')
    w, h = im.size
    zh = round(w * H / W)
    if zh < h:
        weg = h - zh; im = im.crop((0, round(weg*0.4), w, round(weg*0.4) + zh))
    else:
        zw = round(h * W / H); weg = w - zw; im = im.crop((weg//2, 0, weg//2 + zw, h))
    im = im.resize((round(W*PX), round(H*PX)), Image.LANCZOS)
    b = io.BytesIO(); im.save(b, 'JPEG', quality=92)
    return base64.b64encode(b.getvalue()).decode()

VERLAUF = {   # Abdunkeln, wo Schrift steht — je Familie
    'a': [(0, .75), (.22, .45), (.36, .1), (.5, .78), (1, .92)],
    'b': [(0, .5), (.22, .15), (.4, .25), (.75, .55), (1, .85)],
    'c': [(0, .55), (.2, .25), (.45, .35), (.8, .6), (1, .7)],
    'd': [(0, .5), (.18, .1), (.4, .2), (.55, .7), (1, .88)],
}

def seite(name, lang, b64bild):
    fl = F[name]
    hell = bool(fl.get('hell'))
    farben(hell)
    inhalt, q, u = STILE[fl['f']](fl, lang)
    grund = '#ffffff' if hell else '#000'
    stops = ''.join(f'<stop offset="{o}" stop-color="{grund}" stop-opacity="{a * (0.45 if hell else 1)}"/>' for o, a in VERLAUF[fl['f']])
    links = '<rect width="{W}" height="{H}" fill="url(#links)"/>'.format(W=W, H=H) if fl['f'] in 'bd' else ''
    defs = K.defs().replace('</defs>', f"""
 <linearGradient id="dunkel" x1="0" y1="0" x2="0" y2="1">{stops}</linearGradient>
 <radialGradient id="logohof"><stop offset="0" stop-color="{'#0f2f57' if hell else '#000'}" stop-opacity=".62"/><stop offset=".6" stop-color="{'#0f2f57' if hell else '#000'}" stop-opacity=".35"/><stop offset="1" stop-color="{'#0f2f57' if hell else '#000'}" stop-opacity="0"/></radialGradient>
 <linearGradient id="links" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="{grund}" stop-opacity=".55"/><stop offset=".6" stop-color="{grund}" stop-opacity="0"/></linearGradient>
 <filter id="schatten" x="-5%" y="-30%" width="110%" height="160%"><feDropShadow dx="0" dy="3" stdDeviation="6" flood-color="{'#ffffff' if hell else '#000'}" flood-opacity="{'.9' if hell else '.8'}"/></filter>
</defs>""")
    html = f"""<!doctype html><html><head><meta charset="utf-8"><style>{K.FONTFACE}{OSWALD}
html,body{{margin:0;padding:0;background:#000}} svg{{display:block}}</style></head><body>
<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">{defs}
<image href="data:image/jpeg;base64,{b64bild}" x="0" y="0" width="{W}" height="{H}" preserveAspectRatio="none"/>
<rect width="{W}" height="{H}" fill="url(#dunkel)"/>{links}{inhalt}</svg>
<script>document.fonts.ready.then(()=>{{for(const e of document.querySelectorAll('text[data-max]')){{
 let g=parseFloat(e.getAttribute('font-size')),m=parseFloat(e.dataset.max);
 while(e.getComputedTextLength()>m&&g>10){{g*=0.97;e.setAttribute('font-size',g.toFixed(1));}}}}
 document.body.dataset.fertig=1;}});</script></body></html>"""
    return html, q, u

if __name__ == '__main__':
    quelle, out = sys.argv[1], sys.argv[2]
    nur = sys.argv[3:] or list(F)
    os.makedirs(out, exist_ok=True)
    lp = f'{out}/liste.json'
    liste = json.load(open(lp)) if os.path.exists(lp) else {}
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium' if os.path.exists('/opt/pw-browsers/chromium') else None)
        for name in nur:
            pf = next((f'{quelle}/{v}{name}.{e}' for v in ('o2-', 'o-', '') for e in ('jpg', 'png') if os.path.exists(f'{quelle}/{v}{name}.{e}')), None)
            if not pf:
                print('fehlt', name); continue
            bb = bild(pf)
            for lang in ('de', 'it', 'en'):
                html, q, u = seite(name, lang, bb)
                pg = b.new_page(viewport={'width': W, 'height': H}, device_scale_factor=PX)
                pg.set_content(html); pg.wait_for_selector('body[data-fertig]', state='attached'); pg.wait_for_timeout(100)
                png = pg.screenshot(clip={'x': 0, 'y': 0, 'width': W, 'height': H})
                pg.close()
                Image.open(io.BytesIO(png)).convert('RGB').save(f'{out}/{name}.{lang}.jpg', 'JPEG', quality=86, dpi=(DPI, DPI), optimize=True, progressive=True)
                print(name, lang, flush=True)
            liste[name] = {'b': round(W*PX), 'h': round(H*PX), 'beschnitt': round(BE*10*PX), 'q': q, 'u': u}
            json.dump(liste, open(lp, 'w'), indent=1)
        b.close()
