"""Logo als Polygone (Logopixel, y nach unten) fuer Blender -- aus logo_svg.py."""
import json, re, numpy as np
exec(open('logo_svg.py').read().split("svg=['<svg")[0])   # erzeugt pfade (SVG-d-Strings)
from fontTools.pens.basePen import BasePen
from fontTools.ttLib import TTFont
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen

class Flach(BasePen):
    def __init__(self, gs, schritt=0.35):
        super().__init__(gs); self.konturen=[]; self.k=None; self.s=schritt
    def _moveTo(self,p): self.k=[p]
    def _lineTo(self,p): self.k.append(p)
    def _qCurveToOne(self,p1,p2):
        p0=np.array(self.k[-1]); a=np.array(p1); b=np.array(p2)
        n=max(2,int(np.linalg.norm(b-p0)/self.s))
        for t in np.linspace(0,1,n+1)[1:]: self.k.append(tuple((1-t)**2*p0+2*(1-t)*t*a+t*t*b))
    def _curveToOne(self,p1,p2,p3):
        p0=np.array(self.k[-1]); a,b,c=map(np.array,(p1,p2,p3))
        n=max(2,int(np.linalg.norm(c-p0)/self.s))
        for t in np.linspace(0,1,n+1)[1:]: self.k.append(tuple((1-t)**3*p0+3*(1-t)**2*t*a+3*(1-t)*t*t*b+t**3*c))
    def _closePath(self):
        if self.k and len(self.k)>2: self.konturen.append(self.k)
        self.k=None
    _endPath=_closePath

def glyph_poly(fontfile, ch, box):
    f=TTFont(fontfile); gs=f.getGlyphSet(); name=f.getBestCmap()[ord(ch)]
    bp=BoundsPen(gs); gs[name].draw(bp); gx0,gy0,gx1,gy1=bp.bounds
    x0,x1,y0,y1=box; sx=(x1-x0)/(gx1-gx0); sy=(y1-y0)/(gy1-gy0)
    fl=Flach(gs); tp=TransformPen(fl,(sx,0,0,-sy,x0-gx0*sx,y1+gy0*sy)); gs[name].draw(tp)
    return [[(round(x,3),round(y,3)) for x,y in k] for k in fl.konturen]

def d_zu_poly(d):
    zahlen=re.findall(r'[-\d.]+',d); pts=np.array(list(map(float,zahlen))).reshape(-1,2)
    return [[(round(x,3),round(y,3)) for x,y in pts]]

out={'marke':[],'wort':[],'design':[]}
for d in pfade['marke']: out['marke']+=d_zu_poly(d)
for k in ('V','E','M'):
    for d in pfade[k]: out['wort']+=d_zu_poly(d)
out['wort']+=glyph_poly(OUT,'C',(233,311,250,336))
out['wort']+=glyph_poly(OUT,'O',(343,432,250,336))
for ch,b in zip('DESIGN',[(88,121,357,389),(165,192,357,389),(238,266,357,390),(312,319,357,389),(363,395,357,390),(440,470,357,389)]):
    out['design']+=glyph_poly(MON,ch,b)
json.dump(out,open('vecom-logo.json','w'))
print({k:(len(v),sum(len(p) for p in v)) for k,v in out.items()})
