"""Vecom-Logo als saubere Vektoren (28.09.2026) -- Grundlage fuer das 3D-Logo im Werbefilm.
Gerade Formen (Bildmarke, V, M, E-Balken) aus der Logodatei per OpenCV-Kontur, rund
(C, O und DESIGN) aus Schriftkonturen, die auf die gemessenen Buchstabenkaesten
eingepasst sind (Deckung gemessen: C 0,93, O 0,97, DESIGN 0,87-0,93)."""
import numpy as np, cv2
from PIL import Image, ImageFilter
from fontTools.ttLib import TTFont
from fontTools.pens.recordingPen import RecordingPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.boundsPen import BoundsPen
SRC='/tmp/claude-0/-home-claude/fb810757-6d64-56de-b4e2-2fbcddf7eb5d/scratchpad/wt/assets/img/logo-full-dark.webp'
a=Image.open(SRC).convert('RGBA'); al=a.split()[3]
S=16
def konturen(box, eps):
    x0,y0,x1,y1=box
    c=al.crop((x0-4,y0-4,x1+4,y1+4)).resize(((x1-x0+8)*S,(y1-y0+8)*S),Image.BICUBIC).filter(ImageFilter.GaussianBlur(S*0.7))
    m=(np.array(c)>128).astype('uint8')*255
    cs,hier=cv2.findContours(m,cv2.RETR_CCOMP,cv2.CHAIN_APPROX_NONE)
    out=[]
    for k in cs:
        if cv2.contourArea(k)<50*S*S/16: continue
        # Dominante Ecken (Geraden bleiben gerade), danach Rundungen gezielt
        # glätten: flache Knicke (Teil einer Kurve) weich abrunden, echte Ecken
        # nur minimal brechen -- wie eine Fase an einer gestanzten Kante.
        p=cv2.approxPolyDP(k,eps*S,True)[:,0,:].astype(float)/S+np.array([x0-4,y0-4])
        for _ in range(4):
            neu=[]; n=len(p)
            for i in range(n):
                a0,b0,c0=p[i-1],p[i],p[(i+1)%n]
                u=a0-b0; v=c0-b0; lu=np.linalg.norm(u); lv=np.linalg.norm(v)
                cosw=np.dot(u,v)/(lu*lv+1e-9); knick=180-np.degrees(np.arccos(np.clip(cosw,-1,1)))
                r=1.6 if knick<40 else 0.18
                d1=min(0.33*lu,r); d2=min(0.33*lv,r)
                neu.append(b0+u/lu*d1); neu.append(b0+v/lv*d2)
            p=np.array(neu)
        out.append(p)
    return out
def poly_d(p): return 'M'+' L'.join(f'{x:.3f},{y:.3f}' for x,y in p)+' Z'
pfade={}
pfade['marke']=[poly_d(p) for p in konturen((136,1,426,236),0.4)]
pfade['V']=[poly_d(p) for p in konturen((0,248,100,342),0.4)]
pfade['M']=[poly_d(p) for p in konturen((466,248,557,337),0.4)]
pfade['E']=[poly_d(p) for p in konturen((126,248,199,337),0.4)]
def glyph(fontfile, ch, box):
    f=TTFont(fontfile); gs=f.getGlyphSet(); name=f.getBestCmap()[ord(ch)]
    bp=BoundsPen(gs); gs[name].draw(bp); gx0,gy0,gx1,gy1=bp.bounds
    x0,x1,y0,y1=box
    sx=(x1-x0)/(gx1-gx0); sy=(y1-y0)/(gy1-gy0)
    sp=SVGPathPen(gs)
    tp=TransformPen(sp,(sx,0,0,-sy,x0-gx0*sx,y1+gy0*sy))
    gs[name].draw(tp)
    return sp.getCommands()
OUT='/tmp/claude-0/logo3d/fonts/Outfit-600-ro.ttf'; MON='/tmp/claude-0/logo3d/Montserrat-700-ro.ttf'
pfade['C']=[glyph(OUT,'C',(233,311,250,336))]
pfade['O']=[glyph(OUT,'O',(343,432,250,336))]
for ch,b in zip('DESIGN',[(88,121,357,389),(165,192,357,389),(238,266,357,390),(312,319,357,389),(363,395,357,390),(440,470,357,389)]):
    pfade.setdefault('design',[]).append(glyph(MON,ch,b))
svg=['<svg xmlns="http://www.w3.org/2000/svg" width="560" height="400" viewBox="0 0 560 400">']
for k,ds in pfade.items():
    svg.append(f'<g id="{k}">'+''.join(f'<path fill="#fff" fill-rule="evenodd" d="{d}"/>' for d in ds)+'</g>')
svg.append('</svg>')
open('/tmp/claude-0/logo3d/vecom-logo.svg','w').write('\n'.join(svg))
print({k:len(v) for k,v in pfade.items()}, {k:[d.count('L')+1 for d in v] for k,v in pfade.items() if k in ('marke','V','M','E')})
