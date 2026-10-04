"""Branchen-Flyer A5 in DE/IT/EN (04.10.2026, Uwe: „die Flyer einzeln in Deutsch, Italienisch
und Englisch“). Ersetzt die alten Collagen-Flyer nicht, sondern kommt dazu.

Hintergrund: ein Foto je Branche (kie.ai, nano-banana-pro, ohne Text — branchen_bilder.py).
Darauf setzen wir selbst: Logo, Branche, vier Leistungen, Slogan, Aufforderung, eine weiße
QR-Fläche. Den echten Code des Partners setzt später PHP (PartnerFlyer/WmDruck) hinein;
ihre Lage steht in liste.json. Text kommt aus branchen_texte.py — nie aus dem Bildmodell,
weil das Modell Wörter verdreht („WEBSITEN“, „Weoseiten“ in den alten Collagen).

Format A5 148×210 mm + 3 mm Beschnitt, 300 dpi. Einheit im SVG: 1 mm = 10 px.

Aufruf (im Ordner tools/visitenkarten, wegen paths.json/bbox.json/fonts):
    python3 ../werbemittel/branchen_gen.py <fotos> <ausgabe> [branche …]
"""
import base64, io, json, os, sys
sys.path.insert(0, os.getcwd())
sys.path.append(os.path.dirname(os.path.abspath(__file__)))  # nach cwd: „gen“ ist das der Visitenkarten
import gen as K
from branchen_texte import G, B
from PIL import Image
from playwright.sync_api import sync_playwright

BR, HO, BE, DPI = 148, 210, 3, 300
W, H = (BR + 2*BE) * 10, (HO + 2*BE) * 10
PX = DPI / 25.4 / 10              # Ausgabepixel je SVG-Einheit
SICHER = BE*10 + 90               # 9 mm Abstand vom Endformat

def foto(pfad):
    """Foto auf das Seitenverhältnis zuschneiden (oben 35 %, unten 65 % weg — unten ist ohnehin Schatten)."""
    im = Image.open(pfad).convert('RGB')
    w, h = im.size
    zh = round(w * H / W)
    if zh < h:
        weg = h - zh
        im = im.crop((0, round(weg*0.35), w, round(weg*0.35) + zh))
    else:
        zw = round(h * W / H); weg = w - zw
        im = im.crop((weg//2, 0, weg//2 + zw, h))
    im = im.resize((round(W*PX), round(H*PX)), Image.LANCZOS)
    b = io.BytesIO(); im.save(b, 'JPEG', quality=92)
    return base64.b64encode(b.getvalue()).decode()

def t(x, y, s, gr, gew, farbe, anker='middle', extra='', maxb=None, fam='MS'):
    m = f' data-max="{maxb}"' if maxb else ''
    return f'<text x="{x}" y="{y}" text-anchor="{anker}" font-family="{fam}" font-size="{gr}" font-weight="{gew}" fill="{farbe}"{m} {extra}>{s}</text>'

def esc(s):
    return s.replace('&', '&amp;').replace('<', '&lt;')

def vorn(slug, lang, bild):
    gruppe, titel, sub, punkte, slogan = B[slug]
    weiss, gold = '#fbf7ef', '#e9bd62'
    g = f'<image href="data:image/jpeg;base64,{bild}" x="0" y="0" width="{W}" height="{H}" preserveAspectRatio="none"/>'
    # Abdunkeln nur, wo Text steht: oben für Titel, unten für Leistungen und Code.
    g += f'<rect width="{W}" height="{H}" fill="url(#oben)"/><rect width="{W}" height="{H}" fill="url(#unten)"/>'
    l, unten = K.logo_gross(W/2, SICHER - 10, 230)
    g += l
    y = unten + 120
    g += t(W/2, y, G['websites_fuer'][lang], 50, 600, gold, extra='letter-spacing="14"')
    y += 128
    g += t(W/2, y, esc(titel[lang]), 112, 700, weiss, maxb=W - 2*SICHER, extra='letter-spacing="2" filter="url(#schatten)"')
    y += 82
    g += t(W/2, y, esc(sub[lang]), 52, 500, weiss, maxb=W - 2*SICHER, extra='filter="url(#schatten)"')
    # Unten: vier Leistungen, Slogan, Aufforderung + QR
    qs = 300
    qx, qy = W - SICHER - qs, H - SICHER - qs - 20
    y0 = qy - 520
    g += f'<rect x="{SICHER}" y="{y0}" width="140" height="4" fill="url(#goldH)"/>'
    y = y0 + 95
    for p in punkte:
        g += f'<rect x="{SICHER+4}" y="{y-26}" width="20" height="20" transform="rotate(45 {SICHER+14} {y-16})" fill="url(#gold)"/>'
        g += t(SICHER + 58, y, esc(p[lang]), 50, 500, weiss, anker='start', maxb=W - 2*SICHER - 58, extra='filter="url(#schatten)"')
        y += 76
    y += 40
    g += t(SICHER, y, esc(slogan[lang]), 70, 400, gold, anker='start', fam='KS', maxb=W - 2*SICHER)
    # Aufforderung links neben dem Code
    bx, bh = SICHER, 104
    by = qy + qs - bh - 70
    g += t(bx, by - 46, esc(G['cta'][lang]), 58, 700, weiss, anker='start', maxb=qx - bx - 60)
    g += f'<rect x="{bx}" y="{by}" width="{qx - bx - 60}" height="{bh}" rx="52" fill="url(#goldH)"/>'
    g += t(bx + (qx - bx - 60)/2, by + 68, 'www.vecom-design.it', 46, 700, '#1a140b', extra='letter-spacing="1"', maxb=qx - bx - 110)
    g += t(bx, qy + qs + 12, esc(G['scan'][lang]) + '  →', 40, 500, weiss, anker='start', extra='opacity=".85"')
    box, _ = K.qr_box(qx, qy, qs, 'B')
    g += box
    return g, [qx, qy, qs, qs]

def seite(slug, lang, bild):
    inhalt, q = vorn(slug, lang, bild)
    defs = K.defs().replace('</defs>', f"""
 <linearGradient id="oben" x1="0" y1="0" x2="0" y2="1">
  <stop offset="0" stop-color="#000" stop-opacity=".82"/><stop offset=".24" stop-color="#000" stop-opacity=".55"/>
  <stop offset=".40" stop-color="#000" stop-opacity="0"/></linearGradient>
 <linearGradient id="unten" x1="0" y1="0" x2="0" y2="1">
  <stop offset=".46" stop-color="#000" stop-opacity="0"/><stop offset=".62" stop-color="#000" stop-opacity=".78"/>
  <stop offset="1" stop-color="#000" stop-opacity=".94"/></linearGradient>
 <filter id="schatten" x="-5%" y="-30%" width="110%" height="160%"><feDropShadow dx="0" dy="3" stdDeviation="7" flood-color="#000" flood-opacity=".75"/></filter>
</defs>""")
    html = f"""<!doctype html><html><head><meta charset="utf-8"><style>{K.FONTFACE}
html,body{{margin:0;padding:0;background:#000}} svg{{display:block}}</style></head><body>
<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">{defs}{inhalt}</svg>
<script>document.fonts.ready.then(()=>{{for(const e of document.querySelectorAll('text[data-max]')){{
 let g=parseFloat(e.getAttribute('font-size')),m=parseFloat(e.dataset.max);
 while(e.getComputedTextLength()>m&&g>10){{g*=0.97;e.setAttribute('font-size',g.toFixed(1));}}}}
 document.body.dataset.fertig=1;}});</script></body></html>"""
    return html, q

if __name__ == '__main__':
    fotos, out = sys.argv[1], sys.argv[2]
    nur = sys.argv[3:] or list(B)
    os.makedirs(out, exist_ok=True)
    liste = json.load(open(f'{out}/liste.json')) if os.path.exists(f'{out}/liste.json') else {}
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium' if os.path.exists('/opt/pw-browsers/chromium') else None)
        for slug in nur:
            q = next((f'{fotos}/{slug}.{e}' for e in ('png', 'jpg') if os.path.exists(f'{fotos}/{slug}.{e}')), None)
            if not q:
                print('fehlt', slug); continue
            bild = foto(q)
            for lang in ('de', 'it', 'en'):
                html, q = seite(slug, lang, bild)
                pg = b.new_page(viewport={'width': W, 'height': H}, device_scale_factor=PX)
                pg.set_content(html); pg.wait_for_selector('body[data-fertig]', state='attached'); pg.wait_for_timeout(100)
                png = pg.screenshot(clip={'x': 0, 'y': 0, 'width': W, 'height': H})
                pg.close()
                Image.open(io.BytesIO(png)).convert('RGB').save(f'{out}/{slug}.{lang}.jpg', 'JPEG', quality=86, dpi=(DPI, DPI), optimize=True, progressive=True)
                print(slug, lang, flush=True)
            liste[slug] = {'b': round(W*PX), 'h': round(H*PX), 'beschnitt': round(BE*10*PX), 'q': [round(v*PX) for v in q]}
            json.dump(liste, open(f'{out}/liste.json', 'w'), indent=1)   # nach jeder Branche: ein Abbruch verliert nichts
        b.close()
