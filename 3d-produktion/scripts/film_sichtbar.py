"""film_sichtbar.py -- Werbefilm „Sichtbar werden" (Uwe, 28.09.2026: Ja).

Nacht in einer sizilianischen Altstadtgasse. Die Läden sind dunkel, die
Rollläden unten. Einer nach dem anderen geht auf und das Licht fällt auf den
nassen Stein; auf den Tischen draußen leuchten Telefone mit echten, von Vecom
gebauten Websites auf. Die Kamera steigt über die Dächer, die ganze Stadt
wird hell, und aus dem Licht formt sich das Vecom-Logo in Gold.

Drehbuch (24 B/s, 45 s = 1080 Bilder; Uwe 28.09.2026: Trailer 45 s + 15 s,
Sprecher „Stille zu Licht“, drei Sprachen, tiefe Männerstimme):
  S1     0-119   Etablieren: dunkle Gasse, Laternen, Mondlicht, nasser Stein
                 „Jede Stadt hat ihre Geschichten. Doch nachts … sieht sie niemand."
  S2   120-359   Aufwachen: Rollläden gehen hoch, Licht fällt auf die Gasse
                 „Ihre Kunden entscheiden heute online. In Sekunden. …"
  S3   360-551   Nähe: Tisch vor der Bar, Telefon leuchtet auf (Website)
  S3B  552-695   Buchladen: Laptop auf der Theke zeigt eine Website
  S4   696-887   Aufstieg: Kran über die Dächer, die Stadt wird hell
  S5   888-1079  Marke: Lichtpunkte steigen auf und werden zum goldenen Logo
Kurzfassung 15 s: Ausschnitte aus S2, S3 und S5, eigener kurzer Sprechertext.

Aufruf (Blender 5, Hintergrund):
  blender -b -P film_sichtbar.py -- bau                baut die Szene, speichert film-sichtbar.blend
  blender -b film-sichtbar.blend -P film_sichtbar.py -- probe <format> <bilder> [prozent] [samples]
  blender -b film-sichtbar.blend -P film_sichtbar.py -- voll <format> <von> <bis>
  format: quer (1920x1080) | hoch (1080x1920)

Maßstab: Meter, Z oben. Gasse entlang +Y, Mitte x = 0, Fassaden bei x = ±B/2.
Materialien: Poly Haven (CC0), in quelle/ geladen von film-quelle-laden.ps1.
"""
import bpy, bmesh, math, os, sys, json, random
import numpy as np
from mathutils import Vector, Matrix, Euler

HIER = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HIER)
import pr_basis as B

BASIS = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion'
FILM = os.path.join(BASIS, 'film-sichtbar')
Q = os.path.join(FILM, 'quelle')
TEXA = os.path.join(BASIS, 'branchen', 'quelle', 'tex', 'arbeiten')
BLEND = os.path.join(FILM, 'film-sichtbar.blend')
LOGO_SVG = os.path.join(FILM, 'vecom-logo.svg')

argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
MODUS = argv[0] if argv else 'bau'

FPS = 24
GASSE_B = 3.4            # Breite zwischen den Fassaden
GASSE_L = 64.0           # Länge des gebauten Stücks
EG_H = 3.7               # Erdgeschoss
OG_H = 3.2               # Obergeschoss
RNG = random.Random(19740928)

# Zeitplan (Bilder)
S1, S2, S3, S3B, S4, S5, ENDE = 0, 120, 360, 552, 696, 888, 1080
# Belichtung je Einstellung (EV zur Grundbelichtung 1.3), gemessen an der Vorschau 28.09.:
# S1/S2 hatten 67 % Pixel unter 16/255 (versinkt auf dem Handy), S3B Mittelwert 135 (zu hell).
BELICHTUNG = {'S1': 0.55, 'S3': 0.0, 'S3B': -0.9, 'S4': 0.15}
LOGO_LICHT = 0.5    # Faktor auf die drei Logolichter (Gold-Test 28.09.: 1.0 überstrahlt)
REFLEX_STAERKE = 1.0   # Messung 28.09.: 2.5 bleicht die Schrift aus (AgX), 1.0 hält das Gold satt
GOLD = (1.0, 0.72, 0.29)
FENSTER_W = 450.0
WEISSABGLEICH = 4200.0   # Licht durchs Schaufenster je Laden (W, 3100 K)
S3B_ZOOM = 1.5      # S3B fährt zusätzlich auf (Laptop am Ende groß im Bild; Vorschau 28.09.)


# ====================================================================== Stoffe
def _datei(ordner, teil):
    d = os.path.join(Q, 'tex', ordner)
    for f in sorted(os.listdir(d)):
        if teil in f:
            return os.path.join(d, f)
    return None


_bild_cache = {}


def bild(pfad, farbe):
    k = (pfad, farbe)
    if k not in _bild_cache:
        im = bpy.data.images.load(pfad, check_existing=True)
        im.colorspace_settings.name = 'sRGB' if farbe else 'Non-Color'
        _bild_cache[k] = im
    return _bild_cache[k]


def knoten(nt, typ, x=0, y=0, **werte):
    n = nt.nodes.new(typ); n.location = (x, y)
    for k, v in werte.items():
        if k in n.inputs:
            n.inputs[k].default_value = v
        else:
            setattr(n, k, v)
    return n


# Mix-Knoten: gleichnamige Eingänge je Datentyp -- nur über den Index sicher
# (inputs['A'] liefert immer die Float-Variante).
MIX_IDX = {'FLOAT': (2, 3, 0), 'VECTOR': (4, 5, 1), 'RGBA': (6, 7, 2)}


def mix(nt, typ, x, y, a, b, fac, blend='MIX'):
    """a/b: Socket oder Wert, fac: Socket oder Zahl. Gibt den Ausgang zurück."""
    n = nt.nodes.new('ShaderNodeMix'); n.location = (x, y); n.data_type = typ
    if typ == 'RGBA':
        n.blend_type = blend
    ia, ib, io = MIX_IDX[typ]
    for sock, wert in ((n.inputs[ia], a), (n.inputs[ib], b)):
        if hasattr(wert, 'is_output'):
            nt.links.new(wert, sock)
        else:
            sock.default_value = wert
    if hasattr(fac, 'is_output'):
        nt.links.new(fac, n.inputs[0])
    else:
        n.inputs[0].default_value = fac
    return n.outputs[io]


def ph_stoff(name, ordner, kachel=1.0, tint=(1, 1, 1), hell=1.0, rau_mul=1.0, rau_add=0.0,
             normal=1.0, nass=0.0, feucht_unten=0.0, streifen=0.0, metall=0.0, drehen=0.0):
    """Poly-Haven-Material aus Farbe, Normal (GL), Rauheit. UV in Metern
    (kasten/prisma liefern das), kachel = Meter je Texturkachel.
    Imperfections nach Ursache:
      nass          Pfützen in den Senken (Rauschen), glänzend und dunkler
      feucht_unten  aufsteigende Feuchte am Sockel (dunkler bis ~0,9 m)
      streifen      Regenspuren, senkrecht verlaufend."""
    m = bpy.data.materials.new(name); m.use_nodes = True
    nt = m.node_tree; b = nt.nodes['Principled BSDF']; b.location = (400, 0)
    uv = knoten(nt, 'ShaderNodeTexCoord', -1400, 0)
    mp = knoten(nt, 'ShaderNodeMapping', -1200, 0)
    mp.inputs['Scale'].default_value = (1 / kachel, 1 / kachel, 1)
    mp.inputs['Rotation'].default_value = (0, 0, drehen)
    nt.links.new(uv.outputs['UV'], mp.inputs['Vector'])
    fd = _datei(ordner, '_diff_'); fn = _datei(ordner, '_nor_gl_'); fr = _datei(ordner, '_rough_')
    td = knoten(nt, 'ShaderNodeTexImage', -900, 300); td.image = bild(fd, True)
    nt.links.new(mp.outputs['Vector'], td.inputs['Vector'])
    col = mix(nt, 'RGBA', -500, 300, td.outputs['Color'], (tint[0] * hell, tint[1] * hell, tint[2] * hell, 1), 1.0, 'MULTIPLY')
    rau = None
    if fr:
        trr = knoten(nt, 'ShaderNodeTexImage', -900, 0); trr.image = bild(fr, False)
        nt.links.new(mp.outputs['Vector'], trr.inputs['Vector'])
        rm = knoten(nt, 'ShaderNodeMath', -600, 0, operation='MULTIPLY_ADD')
        rm.inputs[1].default_value = rau_mul; rm.inputs[2].default_value = rau_add
        nt.links.new(trr.outputs['Color'], rm.inputs[0]); rau = rm.outputs['Value']
    nrm = None
    if fn:
        tn = knoten(nt, 'ShaderNodeTexImage', -900, -300); tn.image = bild(fn, False)
        nt.links.new(mp.outputs['Vector'], tn.inputs['Vector'])
        nm = knoten(nt, 'ShaderNodeNormalMap', -600, -300); nm.inputs['Strength'].default_value = normal
        nt.links.new(tn.outputs['Color'], nm.inputs['Color']); nrm = nm.outputs['Normal']
    pos = knoten(nt, 'ShaderNodeNewGeometry', -1400, -600)
    if feucht_unten > 0:
        sep = knoten(nt, 'ShaderNodeSeparateXYZ', -1200, -600)
        nt.links.new(pos.outputs['Position'], sep.inputs['Vector'])
        rn = knoten(nt, 'ShaderNodeTexNoise', -1200, -800, **{'Scale': 1.3, 'Detail': 6.0})
        nt.links.new(pos.outputs['Position'], rn.inputs['Vector'])
        grenze = knoten(nt, 'ShaderNodeMath', -1000, -700, operation='MULTIPLY_ADD')
        grenze.inputs[1].default_value = 0.5; grenze.inputs[2].default_value = 0.6
        nt.links.new(rn.outputs['Fac'], grenze.inputs[0])
        mr = knoten(nt, 'ShaderNodeMapRange', -800, -600)
        nt.links.new(sep.outputs['Z'], mr.inputs['Value'])
        nt.links.new(grenze.outputs['Value'], mr.inputs['From Max'])
        mr.inputs['From Min'].default_value = 0.0; mr.inputs['To Min'].default_value = feucht_unten; mr.inputs['To Max'].default_value = 0.0
        col = mix(nt, 'RGBA', -300, 200, col, (0.45, 0.43, 0.40, 1), mr.outputs['Result'], 'MULTIPLY')
    if streifen > 0:
        sp = knoten(nt, 'ShaderNodeMapping', -1200, -1000); sp.inputs['Scale'].default_value = (3.0, 3.0, 0.12)
        nt.links.new(pos.outputs['Position'], sp.inputs['Vector'])
        sn = knoten(nt, 'ShaderNodeTexNoise', -1000, -1000, **{'Scale': 2.0, 'Detail': 8.0, 'Roughness': 0.65})
        nt.links.new(sp.outputs['Vector'], sn.inputs['Vector'])
        sr = knoten(nt, 'ShaderNodeMapRange', -800, -1000)
        sr.inputs['From Min'].default_value = 0.55; sr.inputs['From Max'].default_value = 0.75
        sr.inputs['To Min'].default_value = 0.0; sr.inputs['To Max'].default_value = streifen
        nt.links.new(sn.outputs['Fac'], sr.inputs['Value'])
        col = mix(nt, 'RGBA', -200, 100, col, (0.55, 0.52, 0.48, 1), sr.outputs['Result'], 'MULTIPLY')
    if nass > 0:
        # Pfützen: großräumiges Rauschen, in den Fugen tiefer (Rauheitskarte hoch = Fuge)
        pn = knoten(nt, 'ShaderNodeTexNoise', -1200, -1300, **{'Scale': 0.35, 'Detail': 4.0, 'Roughness': 0.5})
        nt.links.new(pos.outputs['Position'], pn.inputs['Vector'])
        pm = knoten(nt, 'ShaderNodeMapRange', -1000, -1300)
        pm.inputs['From Min'].default_value = 0.56; pm.inputs['From Max'].default_value = 0.68
        nt.links.new(pn.outputs['Fac'], pm.inputs['Value'])
        mix_n = knoten(nt, 'ShaderNodeMath', -800, -1300, operation='MULTIPLY')
        mix_n.inputs[1].default_value = nass
        nt.links.new(pm.outputs['Result'], mix_n.inputs[0]); pf = mix_n.outputs['Value']
        # überall feucht: Rauheit runter; in Pfützen fast Spiegel
        if rau is not None:
            rau = mix(nt, 'FLOAT', 100, -200, rau, 0.02, pf)
        col = mix(nt, 'RGBA', 0, 300, col, (0.55, 0.55, 0.58, 1), pf, 'MULTIPLY')
        if nrm is not None:
            nrm = mix(nt, 'VECTOR', 100, -400, nrm, pos.outputs['Normal'], pf)
    nt.links.new(col, b.inputs['Base Color'])
    if rau is not None:
        nt.links.new(rau, b.inputs['Roughness'])
    if nrm is not None:
        nt.links.new(nrm, b.inputs['Normal'])
    b.inputs['Metallic'].default_value = metall
    return m


def farbstoff(name, farbe, rau=0.5, metall=0.0, emiss=None, **kw):
    return B.stoff(name, farbe, rau=rau, metall=metall, emiss=emiss, **kw)


def licht_stoff(name, farbe_k=2700, staerke=6.0, textur=None):
    """Leuchtende Fläche (Schaufenster-Hintergrund, Fenster hinter Vorhang).
    Emission in Cycles ist Licht -- es fällt wirklich auf die Gasse."""
    m = bpy.data.materials.new(name); m.use_nodes = True
    nt = m.node_tree
    for n in list(nt.nodes):
        nt.nodes.remove(n)
    out = knoten(nt, 'ShaderNodeOutputMaterial', 600, 0)
    em = knoten(nt, 'ShaderNodeEmission', 300, 0)
    bb = knoten(nt, 'ShaderNodeBlackbody', 0, 100); bb.inputs['Temperature'].default_value = farbe_k
    em.inputs['Strength'].default_value = staerke
    if textur is not None:
        nt.links.new(mix(nt, 'RGBA', 150, 100, bb.outputs['Color'], textur(nt), 1.0, 'MULTIPLY'), em.inputs['Color'])
    else:
        nt.links.new(bb.outputs['Color'], em.inputs['Color'])
    nt.links.new(em.outputs['Emission'], out.inputs['Surface'])
    return m


def schluessel_wert(sock, werte, interp='BEZIER'):
    """[(bild, wert), ...] auf einen Node-Eingang schreiben."""
    for f, v in werte:
        sock.default_value = v
        sock.keyframe_insert('default_value', frame=f)


# ====================================================================== Bauteile
def box(name, x0, x1, y0, y1, z0, z1, stoff, fase=0.004):
    o = B.kasten(name, x1 - x0, y1 - y0, z1 - z0, [stoff], fase=fase, ort=((x0 + x1) / 2, (y0 + y1) / 2, z0))
    return o


def sammlung(name):
    c = bpy.data.collections.get(name) or bpy.data.collections.new(name)
    if c.name not in bpy.context.scene.collection.children:
        bpy.context.scene.collection.children.link(c)
    return c


def in_sammlung(o, c):
    for alt in list(o.users_collection):
        alt.objects.unlink(o)
    c.objects.link(o)
    for k in o.children:
        in_sammlung(k, c)
    return o


_modell_namen = {}


def ph_modell(name):
    """Poly-Haven-Modell (.blend) anhängen; gibt die Wurzelobjekte zurück."""
    d = os.path.join(Q, 'modelle', name)
    datei = next((os.path.join(d, f) for f in os.listdir(d) if f.endswith('.blend')), None)
    with bpy.data.libraries.load(datei, link=False) as (quelle, ziel):
        ziel.objects = list(quelle.objects)
    wurzeln = []
    for o in ziel.objects:
        if o is None:
            continue
        bpy.context.scene.collection.objects.link(o)
        if o.parent is None:
            wurzeln.append(o)
    bpy.context.view_layer.update()
    _modell_namen[name] = wurzeln
    return wurzeln


def kopie(wurzeln, ort, dreh=0.0, skala=1.0, sammlung_=None):
    """Tiefe Kopie einer Modellgruppe (Netze geteilt -- spart Speicher).
    Skala relativ zur realen Höhe des Modells (siehe HOEHE)."""
    for n, w in _modell_namen.items():
        if w is wurzeln:
            skala *= normskala(n, wurzeln)
            break
    neu = []
    abbild = {}
    for o in [w for w in wurzeln] + [k for w in wurzeln for k in w.children_recursive]:
        c = o.copy(); abbild[o] = c
        (sammlung_ or bpy.context.scene.collection).objects.link(c)
    for o, c in abbild.items():
        if o.parent in abbild:
            c.parent = abbild[o.parent]
    leer = bpy.data.objects.new('gruppe', None)
    (sammlung_ or bpy.context.scene.collection).objects.link(leer)
    for w in wurzeln:
        abbild[w].parent = leer
    leer.location = ort; leer.rotation_euler = (0, 0, dreh); leer.scale = (skala, skala, skala)
    return leer


HOEHE = {'potted_plant_01': 1.05, 'potted_plant_02': 0.8, 'planter_pot_clay': 0.55, 'wine_barrel_01': 0.92, 'wooden_crate_02': 0.32,
         'ceramic_vase_01': 0.34, 'Lantern_01': 0.5, 'lemon': 0.075, 'standing_chalkboard_01': 1.0, 'folding_wooden_stool': 0.6,
         'wine_bottles_01': 0.31}
_masse = {}


def normskala(name, wurzeln):
    """Faktor, der das Modell auf seine reale Höhe bringt (Poly Haven ist
    meist schon in Metern -- geprüft wird trotzdem, statt es anzunehmen)."""
    if name not in _masse:
        lo, hi = grenzen(wurzeln)
        h = max(hi.z - lo.z, 1e-6)
        _masse[name] = HOEHE.get(name, h) / h
    return _masse[name]


def verstecken(wurzeln):
    for o in wurzeln + [k for w in wurzeln for k in w.children_recursive]:
        o.hide_render = True; o.hide_viewport = True


def mesh_verbinden(objs, name):
    """Viele kleine Teile zu einem Objekt (weniger Objekte = schneller)."""
    objs = [o for o in objs if o is not None]
    if not objs:
        return None
    ctx = {'active_object': objs[0], 'selected_objects': objs, 'selected_editable_objects': objs}
    with bpy.context.temp_override(**ctx):
        bpy.ops.object.join()
    objs[0].name = name
    return objs[0]


# ====================================================================== Fassaden
class Fassade:
    """Koordinaten einer Hausfront: u entlang der Gasse (= y), v Höhe (= z),
    w Tiefe ins Haus (0 = Fassadenebene, negativ = vorstehend).
    seite -1: linke Häuserzeile (Front schaut nach +x), +1: rechte."""

    def __init__(self, seite):
        self.s = seite

    def x(self, w):
        return self.s * (GASSE_B / 2 + w)

    def box(self, name, u0, u1, v0, v1, w0, w1, stoff, fase=0.004):
        xa, xb = sorted((self.x(w0), self.x(w1)))
        return box(name, xa, xb, u0, u1, v0, v1, stoff, fase)

    def punkt(self, u, v, w):
        return Vector((self.x(w), u, v))

    def dreh_z(self):
        """Rotation für Objekte, deren Vorderseite -y zeigt, damit sie zur Gasse schauen."""
        return math.radians(90) if self.s < 0 else math.radians(-90)


def fenster(F, M, u, v, b, h, teile, art='zu', balkon=False, licht=None, rng=RNG):
    """Fenster mit Kalksteinrahmen, Sohlbank, Laibung und Persiane.
    art: 'zu' (Läden geschlossen), 'offen' (Läden aufgeklappt, Glas, dahinter
    dunkel oder -- mit licht -- warm beleuchtet), 'halb' (ein Laden offen)."""
    R = 0.15   # Rahmenbreite
    teile.append(F.box('rahmen_l', u - b / 2 - R, u - b / 2, v - 0.02, v + h + 0.02, -0.035, 0.02, M['kalk'], 0.006))
    teile.append(F.box('rahmen_r', u + b / 2, u + b / 2 + R, v - 0.02, v + h + 0.02, -0.035, 0.02, M['kalk'], 0.006))
    teile.append(F.box('sturz', u - b / 2 - R - 0.03, u + b / 2 + R + 0.03, v + h, v + h + 0.22, -0.05, 0.02, M['kalk'], 0.008))
    if not balkon:
        teile.append(F.box('sohlbank', u - b / 2 - R - 0.05, u + b / 2 + R + 0.05, v - 0.09, v, -0.09, 0.02, M['kalk'], 0.008))
    # Laibung (die Wand ist hier durchbrochen: Leibungsflächen zeigen Tiefe)
    teile.append(F.box('laib_u', u - b / 2, u + b / 2, v - 0.02, v, 0.0, 0.26, M['kalk'], 0.002))
    teile.append(F.box('laib_o', u - b / 2, u + b / 2, v + h, v + h + 0.02, 0.0, 0.26, M['kalk'], 0.002))
    # Persiane: zwei Flügel, Lamellen per Textur, leicht unterschiedlich
    pw = b / 2
    if art == 'zu':
        for i, uu in enumerate((u - b / 4, u + b / 4)):
            teile.append(F.box('persiana', uu - pw / 2 + 0.004, uu + pw / 2 - 0.004, v + 0.01, v + h - 0.01, 0.20, 0.235, M['persiana'], 0.004))
    else:
        # aufgeklappte Flügel liegen flach an der Fassade neben dem Rahmen
        seiten = (-1, 1) if art == 'offen' else (rng.choice((-1, 1)),)
        for sg in seiten:
            u0 = u + sg * (b / 2 + R + 0.01)
            teile.append(F.box('persiana_offen', min(u0, u0 + sg * pw), max(u0, u0 + sg * pw), v + 0.01, v + h - 0.01, -0.07, -0.04, M['persiana'], 0.004))
        if art == 'halb':
            sg = -seiten[0]
            uu = u + sg * b / 4
            teile.append(F.box('persiana', uu - pw / 2 + 0.004, uu + pw / 2 - 0.004, v + 0.01, v + h - 0.01, 0.20, 0.235, M['persiana'], 0.004))
        # Fensterflügel mit Glas
        teile.append(F.box('fl_rahmen', u - b / 2, u + b / 2, v, v + h, 0.24, 0.26, M['holz_dunkel'], 0.003))
        glas = F.box('glas', u - b / 2 + 0.06, u + b / 2 - 0.06, v + 0.06, v + h - 0.06, 0.235, 0.245, M['glas'], 0.0)
        # dahinter: Vorhang, beleuchtet oder dunkel
        hinter = F.box('innen', u - b / 2 - 0.1, u + b / 2 + 0.1, v - 0.1, v + h + 0.1, 0.42, 0.44, licht if licht is not None else M['innen_dunkel'], 0.0)
        return [glas, hinter]
    return []


def balkon(F, M, u, v, b, teile, pflanzen=None):
    """Steinplatte auf Konsolen, schmiedeeisernes Geländer (Stäbe als Array)."""
    L = b + 0.7; T = 0.62
    teile.append(F.box('balkon', u - L / 2, u + L / 2, v - 0.14, v, -T, 0.02, M['kalk'], 0.012))
    for k in (-1, 0, 1):
        uk = u + k * (L / 2 - 0.18)
        teile.append(konsole_geschwungen(F, M, uk, v, T))
    # Geländer: Handlauf, Fußleiste, Stäbe
    eisen = []
    for (uu0, uu1, w0, w1) in ((u - L / 2 + 0.04, u + L / 2 - 0.04, -T + 0.03, -T + 0.06),):
        eisen.append(F.box('handlauf', uu0, uu1, v + 0.96, v + 1.0, w0, w1, M['eisen'], 0.006))
        eisen.append(F.box('fussleiste', uu0, uu1, v + 0.02, v + 0.05, w0, w1, M['eisen'], 0.004))
    for sg in (-1, 1):
        uu = u + sg * (L / 2 - 0.05)
        eisen.append(F.box('handlauf_s', uu - 0.015, uu + 0.015, v + 0.96, v + 1.0, -T + 0.03, 0.0, M['eisen'], 0.006))
    n = int((L - 0.1) / 0.12)
    for i in range(n + 1):
        uu = u - L / 2 + 0.05 + i * (L - 0.1) / n
        eisen.append(F.box('stab', uu - 0.008, uu + 0.008, v + 0.03, v + 0.97, -T + 0.037, -T + 0.053, M['eisen'], 0.002))
        # Seitenstäbe
    for sg in (-1, 1):
        uu = u + sg * (L / 2 - 0.05)
        for j in range(1, 5):
            ww = -T + 0.045 + j * (T - 0.06) / 5
            eisen.append(F.box('stab_s', uu - 0.008, uu + 0.008, v + 0.03, v + 0.97, ww - 0.008, ww + 0.008, M['eisen'], 0.002))
    teile.extend(eisen)
    if pflanzen:
        for sg in (-1, 1):
            if RNG.random() < 0.7:
                p = F.punkt(u + sg * (L / 2 - 0.25), v, -T + 0.25)
                kopie(pflanzen[RNG.randrange(len(pflanzen))], p, RNG.uniform(0, 6.28), RNG.uniform(0.75, 1.0))


def haus(F, M, u0, u1, hoehe, putz, laden=None, rng=RNG, pflanzen=None, stein_ecke=True):
    """Ein Haus: Erdgeschoss (Laden oder Tür), Obergeschosse mit Fenstern,
    Gesims, Traufe. Gibt ein Dict mit Laden-Teilen zurück (Rollladen, Licht)."""
    teile = []; lichtflaechen = []
    breite = u1 - u0
    TW = 0.55                               # Wandstärke der Front
    tief = 9.0
    # ---------------- Obergeschosse als Stücke um die Fensteröffnungen
    n_og = max(1, int(round((hoehe - EG_H - 0.6) / OG_H)))
    oben = EG_H + n_og * OG_H
    nf = max(1, int((breite - 0.9) / 2.25))
    us = [u0 + breite * (i + 0.5) / nf for i in range(nf)]
    fb, fh = 1.02, 1.95
    for k in range(n_og):
        vb = EG_H + k * OG_H
        vs = vb + 0.85 if k > 0 else vb + 0.35      # im 1. OG Balkontüren
        h = fh if k > 0 else 2.35
        # Wandstücke: unter/über den Öffnungen, dazwischen Pfeiler
        teile.append(F.box('wand_brust', u0, u1, vb, vs, 0.0, TW, putz, 0.003))
        teile.append(F.box('wand_sturz', u0, u1, vs + h, vb + OG_H, 0.0, TW, putz, 0.003))
        kanten = [u0] + [x for uu in us for x in (uu - fb / 2, uu + fb / 2)] + [u1]
        for i in range(0, len(kanten), 2):
            if kanten[i + 1] - kanten[i] > 0.01:
                teile.append(F.box('pfeiler', kanten[i], kanten[i + 1], vs, vs + h, 0.0, TW, putz, 0.003))
        mit_balkon = (k == 0 and rng.random() < 0.65)
        for i, uu in enumerate(us):
            r = rng.random()
            art = 'zu' if r < 0.62 else ('offen' if r < 0.84 else 'halb')
            lf = None
            if art != 'zu' and rng.random() < 0.45:
                lf = M['fensterlicht'][rng.randrange(len(M['fensterlicht']))]
            extra = fenster(F, M, uu, vs, fb if k > 0 else 1.1, h, teile, art, balkon=mit_balkon, licht=lf, rng=rng)
            teile.extend(e for e in extra if e is not None)
            if lf is not None:
                lichtflaechen.append(extra[-1])
            if mit_balkon and (nf == 1 or rng.random() < 0.8):
                balkon(F, M, uu, vs, 1.1, teile, pflanzen)
        # Geschossgesims
        if k == 0:
            teile.append(F.box('gurtgesims', u0, u1, vb - 0.05, vb + 0.12, -0.08, 0.02, M['kalk'], 0.01))
    # Hauptgesims und Traufe
    teile.append(F.box('gesims1', u0, u1, oben, oben + 0.16, -0.10, 0.02, M['kalk'], 0.012))
    teile.append(F.box('gesims2', u0, u1, oben + 0.16, oben + 0.30, -0.22, 0.02, M['kalk'], 0.012))
    teile.append(F.box('attika', u0, u1, oben + 0.30, oben + 0.62, 0.0, TW, putz, 0.004))
    # Masse dahinter (wird nie von vorn gesehen, trägt Licht und Dach)
    teile.append(F.box('masse_og', u0, u1, EG_H, oben + 0.3, TW, tief, putz, 0.0))
    # Eckquader aus Kalkstein
    if stein_ecke:
        for uu in (u0, u1 - 0.42):
            for j, vv in enumerate(np.arange(0.7, oben, 0.36)):
                bq = 0.42 if j % 2 == 0 else 0.28
                ua = uu if uu == u0 else u1 - bq
                teile.append(F.box('ecke', ua, ua + bq, vv, vv + 0.35, -0.014, 0.01, M['kalk'], 0.004))   # Fuge 1 cm, flach vorstehend
    # Dach: flache Terrasse mit Brüstung oder Satteldach mit Coppi
    dach = None
    if rng.random() < 0.55:
        dh = 1.6
        xs = [F.x(TW), F.x(tief)]
        x0, x1 = min(xs), max(xs)
        # Satteldach, First parallel zur Gasse
        umr = [(x0 - 0.25, oben + 0.3), (x1 + 0.25, oben + 0.3), ((x0 + x1) / 2, oben + 0.3 + dh)]
        bm = bmesh.new()
        vs_ = []
        for yy in (u0 - 0.05, u1 + 0.05):
            vs_.append([bm.verts.new((x, yy, z)) for x, z in umr])
        bm.faces.new((vs_[0][0], vs_[0][1], vs_[1][1], vs_[1][0]))
        bm.faces.new((vs_[0][1], vs_[0][2], vs_[1][2], vs_[1][1]))
        bm.faces.new((vs_[0][2], vs_[0][0], vs_[1][0], vs_[1][2]))
        bm.faces.new(vs_[0][::-1]); bm.faces.new(vs_[1])
        bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
        me = bpy.data.meshes.new('dach'); bm.to_mesh(me); bm.free()
        me.materials.append(M['dach'])
        uvl = me.uv_layers.new(name='UVMap')
        for poly in me.polygons:
            nrm = poly.normal
            for li in poly.loop_indices:
                co = me.vertices[me.loops[li].vertex_index].co
                # UV: y entlang, Hangrichtung
                uvl.data[li].uv = (co.y, (abs(co.x) + co.z) * 0.9)
        dach = bpy.data.objects.new('dach', me); bpy.context.scene.collection.objects.link(dach)
    else:
        # Flachdach-Terrasse: Fliesen, Brüstung rundum, Wassertanks, Antenne --
        # so sieht es von oben in jeder sizilianischen Altstadt aus
        zt = oben + 0.3
        teile.append(F.box('terrasse', u0, u1, zt, zt + 0.04, TW, tief, M['terrasse'], 0.0))
        for (a0, a1, b0, b1) in ((u0, u1, tief - 0.25, tief), (u0, u0 + 0.25, TW, tief), (u1 - 0.25, u1, TW, tief)):
            teile.append(F.box('bruestung', a0, a1, zt, zt + 0.95, b0, b1, putz, 0.004))
            teile.append(F.box('abdeckung', a0 - 0.02, a1 + 0.02, zt + 0.95, zt + 1.0, b0 - 0.02, b1 + 0.02, M['kalk'], 0.004))
        for i in range(rng.randint(1, 3)):
            p = F.punkt(rng.uniform(u0 + 0.8, u1 - 0.8), zt + 0.04, rng.uniform(TW + 1.5, tief - 1.2))
            r = rng.uniform(0.45, 0.62); hh = rng.uniform(0.9, 1.3)
            t = B.drehkoerper('tank', [(0.0, 0.0), (r, 0.0), (r, hh), (r * 0.92, hh + 0.08), (0.2, hh + 0.12), (0.2, hh + 0.2), (0.0, hh + 0.2)], 40,
                              [M['tank'][rng.randrange(len(M['tank']))]])
            t.location = p; teile.append(t)
        if rng.random() < 0.7:
            p = F.punkt(rng.uniform(u0 + 0.5, u1 - 0.5), zt + 0.04, rng.uniform(TW + 1.0, tief - 1.0))
            teile.append(B.rohr('mast', [(p.x, p.y, p.z), (p.x, p.y, p.z + 2.2)], 0.018, [M['antenne']]))
            for j in range(5):
                zz = p.z + 1.4 + j * 0.16; ll = 0.55 - j * 0.07
                teile.append(B.rohr('element', [(p.x, p.y - ll, zz), (p.x, p.y + ll, zz)], 0.006, [M['antenne']]))
    # ---------------- Erdgeschoss
    info = {'u0': u0, 'u1': u1, 'lichter': lichtflaechen}
    if laden is None:
        teile.append(F.box('eg_masse', u0, u1, 0.0, EG_H, TW, tief, putz, 0.0))
        # Haustür mit Steinrahmen und Oberlicht
        ut = u0 + breite * rng.uniform(0.3, 0.7); tb = 1.25; th = 2.55
        teile.append(F.box('eg_l', u0, ut - tb / 2, 0.0, EG_H, 0.0, TW, putz, 0.003))
        teile.append(F.box('eg_r', ut + tb / 2, u1, 0.0, EG_H, 0.0, TW, putz, 0.003))
        teile.append(F.box('eg_s', ut - tb / 2, ut + tb / 2, th, EG_H, 0.0, TW, putz, 0.003))
        teile.append(F.box('tuer_rahmen_l', ut - tb / 2 - 0.24, ut - tb / 2, 0.0, th + 0.02, -0.05, 0.02, M['kalk'], 0.008))
        teile.append(F.box('tuer_rahmen_r', ut + tb / 2, ut + tb / 2 + 0.24, 0.0, th + 0.02, -0.05, 0.02, M['kalk'], 0.008))
        teile.append(F.box('tuer_sturz', ut - tb / 2 - 0.3, ut + tb / 2 + 0.3, th, th + 0.34, -0.07, 0.02, M['kalk'], 0.01))
        teile.append(F.box('tuer', ut - tb / 2, ut + tb / 2, 0.0, th, 0.30, 0.36, M['tuer'], 0.004))
        teile.append(F.box('stufe', ut - tb / 2 - 0.1, ut + tb / 2 + 0.1, 0.0, 0.14, -0.3, 0.3, M['kalk'], 0.01))
        info['tuer'] = (ut, th)
    else:
        ob = min(2.6, breite - 1.4); oh = 2.9
        uc = u0 + breite / 2 + laden.get('versatz', 0.0)
        a, bb = uc - ob / 2, uc + ob / 2
        teile.append(F.box('eg_l', u0, a, 0.0, EG_H, 0.0, TW, putz, 0.003))
        teile.append(F.box('eg_r', bb, u1, 0.0, EG_H, 0.0, TW, putz, 0.003))
        teile.append(F.box('eg_s', a, bb, oh, EG_H, 0.0, TW, putz, 0.003))
        # Kalksteinrahmen mit Schlussstein
        teile.append(F.box('l_rahmen_l', a - 0.26, a, 0.0, oh + 0.02, -0.05, 0.02, M['kalk'], 0.008))
        teile.append(F.box('l_rahmen_r', bb, bb + 0.26, 0.0, oh + 0.02, -0.05, 0.02, M['kalk'], 0.008))
        teile.append(F.box('l_sturz', a - 0.32, bb + 0.32, oh, oh + 0.36, -0.07, 0.02, M['kalk'], 0.01))
        teile.append(F.box('l_schluss', uc - 0.16, uc + 0.16, oh - 0.04, oh + 0.46, -0.11, 0.02, M['kalk'], 0.01))
        # Ladenraum
        LT = 5.2
        teile.append(F.box('l_boden', a - 0.5, bb + 0.5, -0.02, 0.0, TW, LT, M['ladenboden'], 0.0))
        teile.append(F.box('l_decke', a - 0.5, bb + 0.5, 3.3, 3.34, TW, LT, M['ladenwand'], 0.0))
        teile.append(F.box('l_rueck', a - 0.5, bb + 0.5, 0.0, 3.34, LT, LT + 0.1, M['ladenwand'], 0.0))
        teile.append(F.box('l_seite1', a - 0.6, a - 0.5, 0.0, 3.34, TW, LT, M['ladenwand'], 0.0))
        teile.append(F.box('l_seite2', bb + 0.5, bb + 0.6, 0.0, 3.34, TW, LT, M['ladenwand'], 0.0))
        teile.append(F.box('l_masse1', u0, a - 0.6, 0.0, EG_H, TW, tief, putz, 0.0))
        teile.append(F.box('l_masse2', bb + 0.6, u1, 0.0, EG_H, TW, tief, putz, 0.0))
        teile.append(F.box('l_masse3', a - 0.6, bb + 0.6, 3.34, EG_H, TW, tief, M['masse'], 0.0))
        teile.append(F.box('l_masse4', a - 0.6, bb + 0.6, 0.0, EG_H, LT + 0.1, tief, M['masse'], 0.0))
        # Schaufenster: Metallrahmen, Glas, Tür in der Mitte
        rah = []
        rah.append(F.box('sf_unten', a, bb, 0.0, 0.42, 0.34, 0.40, M['eisen'], 0.004))
        for uu in (a + 0.02, uc - 0.45, uc + 0.45, bb - 0.02):
            rah.append(F.box('sf_pfosten', uu - 0.025, uu + 0.025, 0.0, oh, 0.34, 0.40, M['eisen'], 0.003))
        rah.append(F.box('sf_kaempfer', a, bb, 2.25, 2.3, 0.34, 0.40, M['eisen'], 0.003))
        teile.extend(rah)
        teile.append(F.box('sf_glas', a + 0.03, bb - 0.03, 0.42, oh - 0.02, 0.365, 0.372, M['glas'], 0.0))
        # Rollladen (wird animiert, daher eigenes Objekt)
        rolllade = F.box('rolllade_' + laden['name'], a + 0.01, bb - 0.01, 0.0, oh + 0.01, 0.10, 0.13, M['rolllade'], 0.002)
        # Führungsschienen
        for uu in (a, bb):
            teile.append(F.box('schiene', uu - 0.03, uu + 0.03, 0.0, oh, 0.08, 0.15, M['eisen'], 0.003))
        info.update({'rolllade': rolllade, 'oh': oh, 'a': a, 'b': bb, 'uc': uc, 'LT': LT})
    # Sockel (aufsteigende Feuchte trägt das Putzmaterial selbst)
    teile.append(F.box('sockel', u0, u1, 0.0, 0.55, -0.03, 0.02, M['sockel'], 0.008) if laden is None else None)
    haus_obj = mesh_verbinden([t for t in teile if t is not None and t not in lichtflaechen], 'haus')
    info['obj'] = haus_obj; info['dach'] = dach; info['oben'] = oben
    return info


# ====================================================================== Läden
def buecher(F, u0, u1, v, w0, w1, anzahl_reihen, rng):
    """Bücherreihen aus einfachen Blöcken mit zufälligen Leinenfarben."""
    teile = []
    farben = [(0.35, 0.08, 0.06), (0.08, 0.14, 0.28), (0.12, 0.22, 0.12), (0.55, 0.42, 0.20), (0.62, 0.58, 0.50), (0.18, 0.10, 0.06), (0.05, 0.05, 0.06)]
    stoffe = [B.stoff('buch%d' % i, c, rau=0.62, sheen=0.25) for i, c in enumerate(farben)]
    for r in range(anzahl_reihen):
        uu = u0
        vv = v + r * 0.42
        while uu < u1 - 0.05:
            d = rng.uniform(0.022, 0.05); hh = rng.uniform(0.2, 0.3)
            t = F.box('buch', uu, uu + d, vv, vv + hh, w0 + rng.uniform(0, 0.03), w1, stoffe[rng.randrange(len(stoffe))], 0.002)
            teile.append(t); uu += d + 0.002
    return teile


def buch_vorrat(modelle):
    """Einzelne Bücher aus den Poly-Haven-Sets: (Objekt, Dicke, Tiefe, Höhe, Anker lokal).
    In beiden Sets liegt die Dicke auf x, die Tiefe auf y, der Rücken zeigt nach -y."""
    vorrat = []
    for n in ('decorative_book_set_01', 'book_encyclopedia_set_01'):
        for w in modelle.get(n, []):
            for o in [w] + list(w.children_recursive):
                if o.type != 'MESH' or not any(k in o.name for k in ('book', 'catalogue')):
                    continue
                bb = [Vector(c) for c in o.bound_box]
                lo = Vector((min(c.x for c in bb), min(c.y for c in bb), min(c.z for c in bb)))
                hi = Vector((max(c.x for c in bb), max(c.y for c in bb), max(c.z for c in bb)))
                d = (hi - lo) * o.scale.x
                anker = Vector(((lo.x + hi.x) / 2, lo.y, lo.z))          # unten, Mitte, Rückenkante
                vorrat.append((o, d.x, d.y, d.z, anker))
    return vorrat


def buch_setzen(o, anker, ort, dreh_z, kipp=0.0, liegend=False):
    """Linked Copy eines Buchs: Rückenkante unten-mittig an ort, Rücken zur Gasse."""
    c = o.copy(); bpy.context.scene.collection.objects.link(c)
    c.parent = None; c.hide_render = False; c.hide_viewport = False
    R = Matrix.Rotation(dreh_z, 4, 'Z')
    if liegend:
        R = R @ Matrix.Rotation(math.radians(90), 4, 'Y')
    if kipp:
        R = R @ Matrix.Rotation(kipp, 4, 'Y')
    c.matrix_world = Matrix.Translation(ort) @ R @ Matrix.Diagonal((o.scale.x, o.scale.y, o.scale.z, 1.0)) @ Matrix.Translation(-anker)
    return c


def buecher_reihe(F, u0, u1, v, w_vorn, vorrat, rng):
    """Ein Regalboden: Bücher dicht an dicht, Rücken bündig vorn (± 1–2 cm),
    ab und zu eine Lücke, ein angelehntes Buch oder ein liegender Stapel."""
    dz = F.dreh_z(); u = u0 + rng.uniform(0.0, 0.03)
    while u < u1 - 0.05:
        r = rng.random()
        if r < 0.02:
            u += rng.uniform(0.03, 0.07); continue                  # kleine Lücke
        if r < 0.06 and u < u1 - 0.3:                                # liegender Stapel
            hz = 0.0
            for _ in range(rng.randint(2, 5)):
                o, dx, dy, dh, ank = vorrat[rng.randrange(len(vorrat))]
                ua_ = u if -F.s > 0 else u + dh                      # liegend reicht das Buch in Richtung -s entlang der Fassade
                buch_setzen(o, ank, F.punkt(ua_, v + 0.035 + hz + dx / 2, w_vorn + rng.uniform(0, 0.02)), dz + rng.uniform(-0.04, 0.04), liegend=True)
                hz += dx
            u += 0.26; continue
        o, dx, dy, dh, ank = vorrat[rng.randrange(len(vorrat))]
        if dh > 0.52:
            continue
        kipp = 0.0
        if r > 0.96:
            kipp = math.radians(rng.uniform(8, 16)) * rng.choice((-1, 1))
        buch_setzen(o, ank, F.punkt(u + dx / 2, v + 0.035, w_vorn + rng.uniform(0.0, 0.025)), dz, kipp=kipp)
        u += dx + rng.uniform(0.0, 0.002) + (dh * abs(math.sin(kipp)) if kipp else 0.0)


def buecher_tisch(F, uc, v, wc, vorrat, rng):
    """Auslage auf dem Tisch: liegende Stapel und einige aufgestellte Bücher."""
    dz = F.dreh_z()
    for i, du in enumerate((-0.42, -0.14, 0.14, 0.42)):
        hz = 0.0
        for _ in range(rng.randint(2, 6)):
            o, dx, dy, dh, ank = vorrat[rng.randrange(len(vorrat))]
            ua_ = uc + du - dh / 2 if -F.s > 0 else uc + du + dh / 2
            buch_setzen(o, ank, F.punkt(ua_, v + hz + dx / 2, wc + rng.uniform(-0.25, 0.1)), dz + rng.uniform(-0.1, 0.1), liegend=True)
            hz += dx


def laden_einrichten(F, M, info, art, modelle, rng):
    """Theke, Regale, Ware, Deckenleuchten. Gibt die Lampen-Lichter zurück."""
    a, b, uc, LT = info['a'], info['b'], info['uc'], info['LT']
    teile = []
    ua, ub = a - 0.45, b + 0.45
    # Regal an der Rückwand: drei Böden
    for j, vv in enumerate((0.9, 1.45, 2.0, 2.55)):
        teile.append(F.box('regal', ua + 0.1, ub - 0.1, vv, vv + 0.035, LT - 0.42, LT - 0.02, M['holz'], 0.004))
    teile.append(F.box('regal_s1', ua + 0.1, ua + 0.14, 0.0, 2.8, LT - 0.42, LT - 0.02, M['holz'], 0.004))
    teile.append(F.box('regal_s2', ub - 0.14, ub - 0.1, 0.0, 2.8, LT - 0.42, LT - 0.02, M['holz'], 0.004))
    # Theke seitlich
    teile.append(F.box('theke', ua + 0.2, ua + 0.85, 0.0, 1.0, 1.6, 3.8, M['holz_dunkel'], 0.01))
    teile.append(F.box('theke_platte', ua + 0.15, ua + 0.9, 1.0, 1.04, 1.55, 3.85, M['marmor'], 0.006))
    if art == 'libreria':
        # echte Buchmodelle (Poly Haven, CC0) statt bunter Klötze (Uwe 28.09.: „Bücher zu künstlich")
        buch_teile = buch_vorrat(modelle)
        if buch_teile:
            for vv in (0.935, 1.485, 2.035, 2.585):
                buecher_reihe(F, ua + 0.16, ub - 0.16, vv, LT - 0.40, buch_teile, rng)
        else:
            for vv in (0.935, 1.485, 2.035):
                teile.extend(buecher(F, ua + 0.16, ub - 0.16, vv, LT - 0.34, LT - 0.06, 1, rng))
        teile.append(F.box('tisch', uc - 0.6, uc + 0.6, 0.0, 0.78, 1.6, 2.6, M['holz'], 0.008))
        if buch_teile:
            buecher_tisch(F, uc, 0.78, 2.1, buch_teile, rng)
        else:
            teile.extend(buecher(F, uc - 0.55, uc + 0.5, 0.78, 1.7, 2.5, 1, rng))
    elif art in ('bar', 'enoteca') and 'wine_bottles_01' in modelle:
        for vv in (0.935, 1.485, 2.035, 2.585):
            for uu in np.arange(ua + 0.3, ub - 0.3, 0.42):
                kopie(modelle['wine_bottles_01'], F.punkt(uu, vv, LT - 0.2), F.dreh_z() + rng.uniform(-0.3, 0.3), 1.0)
        if art == 'enoteca' and 'wine_barrel_01' in modelle:
            for uu in (uc - 0.7, uc + 0.7):
                kopie(modelle['wine_barrel_01'], F.punkt(uu, 0.0, 2.4), rng.uniform(0, 6.28), 0.9)
    elif art == 'ceramiche':
        vasen = [modelle[k] for k in ('ceramic_vase_01', 'planter_pot_clay') if k in modelle]
        for vv in (0.935, 1.485, 2.035):
            for uu in np.arange(ua + 0.35, ub - 0.3, 0.5):
                if vasen:
                    kopie(vasen[rng.randrange(len(vasen))], F.punkt(uu, vv, LT - 0.22), rng.uniform(0, 6.28), rng.uniform(0.55, 0.8))
        # Teller an der Wand: bemalte Scheiben (Caltagirone-Art)
        for i in range(10):
            uu = ua + 0.4 + (i % 5) * (ub - ua - 0.8) / 4; vv = 2.95 if i < 5 else 0.55
            t = F.box('teller', uu - 0.17, uu + 0.17, vv - 0.17, vv + 0.17, LT - 0.03, LT, M['teller'][i % len(M['teller'])], 0.008)
            teile.append(t)
    elif art == 'alimentari':
        if 'wooden_crate_02' in modelle:
            for k, uu in enumerate(np.arange(ua + 0.4, ub - 0.3, 0.55)):
                for vv in (0.0, 0.9, 1.45):
                    kopie(modelle['wooden_crate_02'], F.punkt(uu, vv, LT - 0.25), F.dreh_z(), 0.9)
    # Deckenleuchten: sichtbare Glühlampen + Flächenlicht für das Raumlicht
    lampen = []
    for uu in (uc - 0.7, uc + 0.7):
        p = F.punkt(uu, 3.05, 2.4)
        kabel = B.rohr('kabel', [(p.x, p.y, 3.3), (p.x, p.y, 3.1)], 0.004, [M['eisen']]); teile.append(kabel)
        schirm = B.drehkoerper('schirm', [(0.0, 0.0), (0.03, 0.0), (0.16, -0.14), (0.155, -0.145), (0.028, -0.006), (0.0, -0.006)], 48, [M['messing']])
        schirm.location = (p.x, p.y, 3.12); teile.append(schirm)
        birne = B.drehkoerper('birne', [(0.0, 0.0), (0.03, -0.02), (0.035, -0.07), (0.0, -0.11)], 32, [M['birne']])
        birne.location = (p.x, p.y, 3.08); lampen.append(birne)
    ld = bpy.data.lights.new('laden_' + art, 'AREA'); ld.shape = 'RECTANGLE'
    ld.size = b - a + 0.6; ld.size_y = LT - 1.2; ld.energy = 0.0
    ld.use_nodes = True
    bbn = ld.node_tree.nodes.new('ShaderNodeBlackbody'); bbn.inputs['Temperature'].default_value = 3100
    ld.node_tree.links.new(bbn.outputs['Color'], ld.node_tree.nodes['Emission'].inputs['Color'])
    lo = bpy.data.objects.new('laden_' + art, ld); bpy.context.scene.collection.objects.link(lo)
    lo.location = F.punkt(uc, 3.25, (LT + 0.6) / 2)
    lo.rotation_euler = (0, 0, math.radians(90))
    # Licht, das durchs Schaufenster auf das nasse Pflaster fällt. Das Deckenlicht allein
    # erreicht die Gasse kaum (Vorschau 28.09.: Einschalten der Läden fast unsichtbar).
    # Fläche = Schaufenster, knapp hinter dem Glas, strahlt in die Gasse; für die Kamera
    # und in Spiegelungen unsichtbar, damit kein Rechteck im Glas steht.
    lf = bpy.data.lights.new('fenster_' + art, 'AREA'); lf.shape = 'RECTANGLE'
    lf.size = b - a; lf.size_y = info['oh'] - 0.5; lf.energy = 0.0
    lf.use_nodes = True
    bbf = lf.node_tree.nodes.new('ShaderNodeBlackbody'); bbf.inputs['Temperature'].default_value = 3100
    lf.node_tree.links.new(bbf.outputs['Color'], lf.node_tree.nodes['Emission'].inputs['Color'])
    lfo = bpy.data.objects.new('fenster_' + art, lf); bpy.context.scene.collection.objects.link(lfo)
    lfo.location = F.punkt(uc, 0.42 + (info['oh'] - 0.5) / 2, 0.55)
    raus = Vector((-F.s, 0.0, -0.18))
    lfo.rotation_euler = raus.to_track_quat('-Z', 'Y').to_euler()     # lokal X entlang der Fassade, Y hoch
    for eig in ('visible_camera', 'visible_glossy', 'visible_transmission'):
        try:
            setattr(lfo, eig, False)
        except Exception:
            pass
    info['fensterlicht'] = lf
    return teile, lampen, ld


def laden_schalten(info, ld, birnen_stoff, t, M):
    """Licht an bei Bild t (kurzes Aufflackern wie eine Leuchtstoffröhre ist hier
    falsch -- warme LED-Glühlampen kommen weich hoch), Rollladen ab t+6 in 50
    Bildern hoch, mit langsamem Anlauf und Auslauf."""
    E = 260.0
    ld.energy = 0.0; ld.keyframe_insert('energy', frame=t)
    ld.energy = E * 0.55; ld.keyframe_insert('energy', frame=t + 4)
    ld.energy = E; ld.keyframe_insert('energy', frame=t + 14)
    em = birnen_stoff.node_tree.nodes['Principled BSDF'].inputs['Emission Strength']
    schluessel_wert(em, [(t, 0.0), (t + 4, 45.0), (t + 14, 80.0)])
    lf = info.get('fensterlicht')
    if lf is not None:                  # wächst mit dem Rollladen: Licht tritt erst aus, wenn er hochgeht
        lf.energy = 0.0; lf.keyframe_insert('energy', frame=t + 6)
        lf.energy = FENSTER_W; lf.keyframe_insert('energy', frame=t + 56)
    r = info['rolllade']
    r.location.z = 0.0; r.keyframe_insert('location', index=2, frame=t + 6)
    r.location.z = info['oh'] + 0.02; r.keyframe_insert('location', index=2, frame=t + 56)


# ====================================================================== Straße, Requisiten
def strasse(M):
    teile = []
    # Basolato-Pflaster, leicht zur Mitte geneigt (Regenrinne in der Mitte)
    bm = bmesh.new()
    xs = np.linspace(-GASSE_B / 2 - 0.1, GASSE_B / 2 + 0.1, 17)
    ys = np.linspace(-9, GASSE_L + 3, 120)
    vs = {}
    for i, x in enumerate(xs):
        for j, y in enumerate(ys):
            z = 0.035 * (abs(x) / (GASSE_B / 2))                   # Quergefälle
            vs[i, j] = bm.verts.new((x, y, z))
    for i in range(len(xs) - 1):
        for j in range(len(ys) - 1):
            bm.faces.new((vs[i, j], vs[i + 1, j], vs[i + 1, j + 1], vs[i, j + 1]))
    me = bpy.data.meshes.new('strasse'); bm.to_mesh(me); bm.free()
    me.materials.append(M['strasse'])
    uvl = me.uv_layers.new(name='UVMap')
    for poly in me.polygons:
        poly.use_smooth = True
        for li in poly.loop_indices:
            co = me.vertices[me.loops[li].vertex_index].co
            uvl.data[li].uv = (co.x, co.y)
    o = bpy.data.objects.new('strasse', me); bpy.context.scene.collection.objects.link(o)
    return o


def laterne(F, M, u, v, modell):
    """Wandlaterne auf schmiedeeisernem Arm, warmes Licht (2200 K)."""
    arm = [F.box('arm', u - 0.02, u + 0.02, v + 0.28, v + 0.32, -0.55, 0.0, M['eisen'], 0.004),
           F.box('arm_platte', u - 0.08, u + 0.08, v, v + 0.4, -0.02, 0.0, M['eisen'], 0.004)]
    p = F.punkt(u, v - 0.2, -0.5)
    if modell:
        kopie(modell, (p.x, p.y, p.z - 0.05), F.dreh_z(), 1.0)
    ld = bpy.data.lights.new('laterne', 'POINT'); ld.energy = 70.0; ld.shadow_soft_size = 0.05
    ld.use_nodes = True
    bb = ld.node_tree.nodes.new('ShaderNodeBlackbody'); bb.inputs['Temperature'].default_value = 2600
    ld.node_tree.links.new(bb.outputs['Color'], ld.node_tree.nodes['Emission'].inputs['Color'])
    lo = bpy.data.objects.new('laterne', ld); bpy.context.scene.collection.objects.link(lo)
    lo.location = (p.x, p.y, p.z + 0.12)
    return arm


def kabel_quer(M, y, z, durchhang=0.35):
    pts = []
    for i in range(13):
        t = i / 12
        x = -GASSE_B / 2 + GASSE_B * t
        pts.append((x, y + 0.2 * math.sin(t * 3.1), z - durchhang * 4 * t * (1 - t)))
    return B.rohr('kabel', pts, 0.006, [M['kabel']])


def waescheleine(M, y, z, rng):
    teile = [kabel_quer(M, y, z, 0.22)]
    x = -GASSE_B / 2 + 0.3
    while x < GASSE_B / 2 - 0.5:
        w = rng.uniform(0.35, 0.8); h = rng.uniform(0.45, 0.95)
        t = (x + GASSE_B / 2) / GASSE_B
        zz = z - 0.22 * 4 * t * (1 - t)
        stoff = M['waesche'][rng.randrange(len(M['waesche']))]
        # leicht gewölbtes Tuch: Gitter mit Durchhang und Wind
        bm = bmesh.new(); nu, nv = 6, 6; vs = {}
        for i in range(nu + 1):
            for j in range(nv + 1):
                uu = x + w * i / nu; vv = zz - h * j / nv
                bauch = 0.05 * math.sin(math.pi * i / nu) * (j / nv) + 0.02 * math.sin(i * 1.7 + j)
                vs[i, j] = bm.verts.new((uu, y + bauch, vv))
        for i in range(nu):
            for j in range(nv):
                bm.faces.new((vs[i, j], vs[i + 1, j], vs[i + 1, j + 1], vs[i, j + 1]))
        me = bpy.data.meshes.new('tuch'); bm.to_mesh(me); bm.free(); me.materials.append(stoff)
        for p_ in me.polygons: p_.use_smooth = True
        o = bpy.data.objects.new('tuch', me); bpy.context.scene.collection.objects.link(o)
        mod = o.modifiers.new('dicke', 'SOLIDIFY'); mod.thickness = 0.003
        teile.append(o)
        x += w + rng.uniform(0.08, 0.25)
    return teile


# ====================================================================== Materialien
def materialien():
    M = {}
    M['strasse'] = ph_stoff('Basolato', 'large_grey_tiles', 2.0, (0.95, 0.93, 0.9), 0.75, rau_mul=0.85, nass=1.0, normal=1.2)
    M['putze'] = [
        ph_stoff('Putz Ocker', 'yellow_plaster', 3.0, (1.0, 0.9, 0.74), 1.0, feucht_unten=0.55, streifen=0.35),
        ph_stoff('Putz Creme', 'white_plaster_rough_02', 3.0, (0.93, 0.85, 0.72), 0.85, feucht_unten=0.55, streifen=0.4),
        ph_stoff('Putz Rot', 'red_plaster_weathered', 3.0, (1.0, 0.86, 0.8), 0.9, feucht_unten=0.5, streifen=0.3),
        ph_stoff('Putz Pfirsich', 'worn_plaster_wall', 3.0, (1.0, 0.82, 0.66), 0.9, feucht_unten=0.55, streifen=0.35),
    ]
    M['kalk'] = ph_stoff('Kalkstein', 'old_sandstone_02', 1.2, (1.0, 0.94, 0.82), 1.0, rau_add=0.05)
    M['sockel'] = ph_stoff('Sockel', 'old_sandstone_02', 1.0, (0.78, 0.73, 0.66), 0.8, feucht_unten=0.6)
    M['persianen'] = [ph_stoff('Persiana Grün', 'distressed_painted_planks', 1.0, (0.5, 0.72, 0.56), 0.85, drehen=math.pi / 2),
                      ph_stoff('Persiana Braun', 'distressed_painted_planks', 1.0, (0.62, 0.42, 0.28), 0.8, drehen=math.pi / 2),
                      ph_stoff('Persiana Grau', 'distressed_painted_planks', 1.0, (0.78, 0.8, 0.78), 0.8, drehen=math.pi / 2)]
    M['persiana'] = M['persianen'][0]
    M['rolllade'] = ph_stoff('Rollladen', 'worn_shutter', 1.0, (0.9, 0.9, 0.88), 0.85)
    M['dach'] = ph_stoff('Coppi', 'clay_roof_tiles_02', 1.5, (1, 0.95, 0.9), 0.9)
    M['tuer'] = ph_stoff('Haustür', 'rough_pine_door', 2.6, (0.62, 0.48, 0.38), 0.75)
    M['holz'] = ph_stoff('Regalholz', 'wood_table_worn', 1.2, (0.82, 0.66, 0.52), 0.85)     # echtes Holz statt Einheitsfarbe
    M['holz_dunkel'] = B.stoff('Holz dunkel', (0.07, 0.045, 0.03), rau=0.4, coat=0.3, coat_rau=0.2)
    M['marmor'] = ph_stoff('Marmor', 'marble_01', 1.0, (0.9, 0.88, 0.85), 0.8, rau_mul=0.7)     # statt weißer Kunststoffplatte
    M['eisen'] = B.stoff('Schmiedeeisen', (0.028, 0.027, 0.03), rau=0.48, metall=0.7)
    M['messing'] = B.stoff('Messing', (0.78, 0.6, 0.34), rau=0.3, metall=1.0)
    M['glas'] = B.stoff('Glas', (1, 1, 1), rau=0.015, trans=1.0, ior=1.5)
    # Dünne Flachglasscheibe: Schattenstrahlen gehen durch (Transmission ~90 %). Ohne das
    # blockiert Cycles das Ladenlicht an der Scheibe und die Lichtinseln auf dem nassen
    # Pflaster fehlen -- das Einschalten der Läden wäre im Film kaum zu sehen (Vorschau 28.09.).
    nt = M['glas'].node_tree; pb = nt.nodes['Principled BSDF']
    aus = next(n for n in nt.nodes if n.type == 'OUTPUT_MATERIAL')
    lp_ = nt.nodes.new('ShaderNodeLightPath'); tr = nt.nodes.new('ShaderNodeBsdfTransparent')
    tr.inputs['Color'].default_value = (0.9, 0.9, 0.9, 1.0)
    mx = nt.nodes.new('ShaderNodeMixShader')
    nt.links.new(lp_.outputs['Is Shadow Ray'], mx.inputs[0])
    nt.links.new(pb.outputs[0], mx.inputs[1]); nt.links.new(tr.outputs[0], mx.inputs[2])
    nt.links.new(mx.outputs[0], aus.inputs['Surface'])
    M['innen_dunkel'] = B.stoff('Innen dunkel', (0.012, 0.011, 0.012), rau=0.9)
    M['masse'] = B.stoff('Masse', (0.28, 0.26, 0.23), rau=0.9)
    M['ladenboden'] = ph_stoff('Ladenboden', 'large_grey_tiles', 0.9, (1.0, 0.93, 0.86), 0.9, rau_mul=0.6)
    M['ladenwand'] = ph_stoff('Ladenwand', 'white_plaster_rough_02', 4.5, (0.96, 0.93, 0.87), 1.0, normal=0.35)
    M['kabel'] = B.stoff('Kabel', (0.02, 0.02, 0.02), rau=0.5)
    M['birne'] = B.stoff('Birne', (1.0, 0.85, 0.6), emiss=((1.0, 0.62, 0.3), 0.0))
    M['terrasse'] = ph_stoff('Terrasse', 'large_grey_tiles', 0.7, (0.78, 0.5, 0.36), 0.8, rau_mul=0.9, nass=0.6)
    M['tank'] = [B.stoff('Tank blau', (0.08, 0.2, 0.42), rau=0.45), B.stoff('Tank beige', (0.62, 0.56, 0.45), rau=0.5),
                 B.stoff('Tank weiss', (0.7, 0.7, 0.68), rau=0.45)]
    M['antenne'] = B.stoff('Antenne', (0.5, 0.5, 0.52), rau=0.3, metall=1.0)
    M['teller'] = [B.stoff('Teller%d' % i, c, rau=0.15, coat=0.8, coat_rau=0.05) for i, c in
                   enumerate(((0.05, 0.18, 0.55), (0.75, 0.55, 0.08), (0.08, 0.35, 0.2), (0.65, 0.2, 0.05)))]
    M['waesche'] = [B.stoff('Wäsche%d' % i, c, rau=0.85, sheen=0.5) for i, c in
                    enumerate(((0.78, 0.78, 0.75), (0.12, 0.22, 0.45), (0.5, 0.08, 0.06), (0.72, 0.55, 0.2), (0.7, 0.7, 0.72)))]

    def vorhang(nt):
        w = knoten(nt, 'ShaderNodeTexWave', -300, 200, **{'Scale': 7.0, 'Distortion': 3.0, 'Detail': 3.0})
        w.bands_direction = 'X'
        mr = knoten(nt, 'ShaderNodeMapRange', -100, 200); mr.inputs['To Min'].default_value = 0.35
        nt.links.new(w.outputs['Fac'], mr.inputs['Value'])
        return mr.outputs['Result']
    M['fensterlicht'] = [licht_stoff('Fensterlicht %d' % i, k, st, vorhang) for i, (k, st) in enumerate(((2400, 2.2), (2700, 1.6), (3000, 2.8)))]
    return M


# ====================================================================== Licht, Welt
def welt():
    sc = bpy.context.scene
    w = bpy.data.worlds.new('Nacht'); sc.world = w; w.use_nodes = True
    nt = w.node_tree; bg = nt.nodes['Background']
    hd = os.path.join(Q, 'hdri'); datei = next((os.path.join(hd, f) for f in os.listdir(hd) if f.startswith('qwantani_night')), None)
    env = knoten(nt, 'ShaderNodeTexEnvironment', -400, 0); env.image = bpy.data.images.load(datei)
    mp = knoten(nt, 'ShaderNodeMapping', -600, 0); mp.inputs['Rotation'].default_value = (0, 0, math.radians(140))
    tc = knoten(nt, 'ShaderNodeTexCoord', -800, 0)
    nt.links.new(tc.outputs['Generated'], mp.inputs['Vector']); nt.links.new(mp.outputs['Vector'], env.inputs['Vector'])
    # Nachthimmel tiefblau statt grau (das Bild ist ein Tageshimmel-Verlauf ohne Sonne)
    nt.links.new(mix(nt, 'RGBA', -200, 0, env.outputs['Color'], (0.42, 0.52, 0.95, 1), 1.0, 'MULTIPLY'), bg.inputs['Color'])
    bg.inputs['Strength'].default_value = 0.11
    # Mond: kühles, hartes Licht von schräg oben -- trifft die oberen Fassaden,
    # die Gasse unten bleibt den Lampen und Läden überlassen
    md = bpy.data.lights.new('Mond', 'SUN'); md.energy = 0.07; md.angle = math.radians(0.52)
    md.color = (0.62, 0.74, 1.0)
    mo = bpy.data.objects.new('Mond', md); sc.collection.objects.link(mo)
    mo.rotation_euler = (math.radians(38), math.radians(-12), math.radians(205))


# ====================================================================== Kamera
def kamera_setzen(cam, bild, ort, ziel, lens=None, fokus=None, blende=None):
    sc = bpy.context.scene
    cam.location = ort
    cam.rotation_euler = (Vector(ziel) - Vector(ort)).to_track_quat('-Z', 'Y').to_euler()
    cam.keyframe_insert('location', frame=bild); cam.keyframe_insert('rotation_euler', frame=bild)
    if lens is not None:
        cam.data.lens = lens; cam.data.keyframe_insert('lens', frame=bild)
    if fokus is not None:
        cam.data.dof.focus_distance = fokus; cam.data.dof.keyframe_insert('focus_distance', frame=bild)
    if blende is not None:
        cam.data.dof.aperture_fstop = blende; cam.data.dof.keyframe_insert('aperture_fstop', frame=bild)


def kamera_neu(name, sensor_fit='HORIZONTAL'):
    sc = bpy.context.scene
    cd = bpy.data.cameras.new(name); cd.sensor_width = 36; cd.sensor_fit = sensor_fit
    cd.dof.use_dof = True; cd.dof.aperture_fstop = 2.8; cd.clip_start = 0.02; cd.clip_end = 4000
    o = bpy.data.objects.new(name, cd); sc.collection.objects.link(o)
    return o


def kamera_atmen(cam, grad=0.12, zeit=70.0, seed=0):
    """Leichte Handkamera-/Kranbewegung: Rauschen auf der Drehung (Zehntelgrad, langsam).
    Eine mathematisch glatte Fahrt verrät CGI sofort."""
    ad = cam.animation_data
    if not ad or not ad.action:
        return
    try:
        kurven = list(ad.action.fcurves)
    except AttributeError:
        kurven = [fc for lay in ad.action.layers for st in lay.strips for cb in st.channelbags for fc in cb.fcurves]
    for fc in kurven:
        if fc.data_path == 'rotation_euler':
            m = fc.modifiers.new('NOISE'); m.scale = zeit; m.strength = math.radians(grad) * (0.6 if fc.array_index == 1 else 1.0)
            m.phase = 13.0 * (fc.array_index + 1) + seed; m.depth = 1


def glatte_kurven(obj):
    ad = obj.animation_data
    if not ad or not ad.action:
        return
    try:
        kurven = ad.action.fcurves
    except AttributeError:          # Blender 5: Aktionen mit Ebenen/Kanaltüten
        kurven = [fc for lay in ad.action.layers for st in lay.strips for cb in st.channelbags for fc in cb.fcurves]
    for fc in kurven:
        for k in fc.keyframe_points:
            k.interpolation = 'BEZIER'; k.handle_left_type = 'AUTO_CLAMPED'; k.handle_right_type = 'AUTO_CLAMPED'


def belichtung_setzen():
    """Belichtung je Einstellung als Schlüssel mit harter Kante an den Schnitten."""
    sc = bpy.context.scene; vs = sc.view_settings
    starts = {'S1': S1, 'S3': S3, 'S3B': S3B, 'S4': S4}
    if sc.animation_data and sc.animation_data.action:
        try:
            kurven = list(sc.animation_data.action.fcurves)
        except AttributeError:
            kurven = [fc for lay in sc.animation_data.action.layers for st in lay.strips for cb in st.channelbags for fc in cb.fcurves]
        for c in kurven:
            if c.data_path == 'view_settings.exposure':
                while c.keyframe_points:
                    c.keyframe_points.remove(c.keyframe_points[0])
    for name, f in starts.items():
        vs.exposure = 1.3 + BELICHTUNG[name]; vs.keyframe_insert('exposure', frame=f)
    act = sc.animation_data.action
    try:
        kurven = list(act.fcurves)
    except AttributeError:
        kurven = [fc for lay in act.layers for st in lay.strips for cb in st.channelbags for fc in cb.fcurves]
    for c in kurven:
        if c.data_path == 'view_settings.exposure':
            for k in c.keyframe_points:
                k.interpolation = 'CONSTANT'


def logo_licht_setzen(faktor):
    for o in bpy.context.scene.objects:
        if o.type != 'LIGHT' or not o.name.startswith('logo_'):
            continue
        act = o.data.animation_data.action
        try:
            kurven = list(act.fcurves)
        except AttributeError:
            kurven = [fc for lay in act.layers for st in lay.strips for cb in st.channelbags for fc in cb.fcurves]
        for c in kurven:
            if c.data_path == 'energy':
                for k in c.keyframe_points:
                    if k.co.y > 0:
                        k.co.y = LOGO_BASIS[o.name] * faktor
                c.update()


LOGO_BASIS = {'logo_fuehrung': 26000.0, 'logo_kante': 16000.0, 'logo_front': 9000.0}


# ====================================================================== Rendern
def render_einstellen(fmt, prozent=100, samples=None):
    sc = bpy.context.scene; r = sc.render
    r.engine = 'CYCLES'
    prefs = bpy.context.preferences.addons['cycles'].preferences
    for typ in ('OPTIX', 'CUDA'):
        try:
            prefs.compute_device_type = typ; prefs.get_devices()
            if any(dv.type == typ for dv in prefs.devices):
                for dv in prefs.devices:
                    dv.use = dv.type == typ
                break
        except Exception:
            pass
    sc.cycles.device = 'GPU'
    r.resolution_x, r.resolution_y = (1920, 1080) if fmt == 'quer' else (1080, 1920)
    r.resolution_percentage = int(prozent)
    r.fps = FPS
    sc.cycles.samples = int(samples or 512)
    sc.cycles.use_adaptive_sampling = True; sc.cycles.adaptive_threshold = 0.012
    sc.cycles.use_denoising = True
    try:
        sc.cycles.denoiser = 'OPTIX'
    except Exception:
        pass
    sc.cycles.max_bounces = 10; sc.cycles.diffuse_bounces = 4; sc.cycles.glossy_bounces = 6
    sc.cycles.transmission_bounces = 8; sc.cycles.caustics_reflective = False; sc.cycles.caustics_refractive = False
    sc.cycles.blur_glossy = 0.6; sc.cycles.sample_clamp_indirect = 6.0
    try:
        sc.cycles.use_light_tree = True
    except Exception:
        pass
    r.use_motion_blur = True; r.motion_blur_shutter = 0.5
    sc.view_settings.view_transform = 'AgX'
    try:
        sc.view_settings.look = 'AgX - Medium High Contrast'
    except Exception:
        pass
    if not (sc.animation_data and sc.animation_data.action):
        sc.view_settings.exposure = 1.3
    # Weißabgleich wie bei einer echten Nachtaufnahme (~4200 K): Laternen bleiben warm, aber nicht
    # orange; Himmel und Mondlicht werden kühl. Vorher war alles einheitlich orange -- der
    # stärkste „CGI"-Eindruck in der Vorschau (Uwe 28.09.: „viel zu künstlich").
    try:
        sc.view_settings.use_white_balance = True
        sc.view_settings.white_balance_temperature = WEISSABGLEICH
        sc.view_settings.white_balance_tint = 0.0
    except Exception as e:
        print('WEISSABGLEICH NICHT GESETZT', e)
    r.image_settings.file_format = 'PNG'; r.image_settings.color_mode = 'RGB'; r.image_settings.color_depth = '16'
    r.film_transparent = False
    # Kamera je Format
    for o in sc.objects:
        if o.type == 'CAMERA':
            o.hide_render = False
    sc.camera = bpy.data.objects.get('kamera_' + fmt) or sc.camera
    for m in sc.timeline_markers:
        if m.camera is not None:
            ziel = bpy.data.objects.get(m.camera.name.replace('quer', fmt).replace('hoch', fmt))
            if ziel:
                m.camera = ziel


# ====================================================================== Stadt (S4)
def fenster_raster_stoff(name, ordner, tint, welle_sock_name='welle'):
    """Fassade für die Stadt in der Ferne: echter Putz (Poly Haven), darauf ein
    Fensterraster (UV in Metern). Geschlossene Läden sind dunkles Holz; ein
    Teil der Fenster ist offen und warm erleuchtet -- aber erst, wenn die
    Lichtwelle (Wert „welle", Meter vom Gassenmittelpunkt) das Haus erreicht."""
    m = ph_stoff(name, ordner, 3.0, tint, 0.62, feucht_unten=0.4, streifen=0.35)
    nt = m.node_tree; b = nt.nodes['Principled BSDF']
    farbe_alt = b.inputs['Base Color'].links[0].from_socket
    uv = knoten(nt, 'ShaderNodeTexCoord', -1800, -1600)
    sep = knoten(nt, 'ShaderNodeSeparateXYZ', -1600, -1600); nt.links.new(uv.outputs['UV'], sep.inputs['Vector'])
    def op(o, a, bb=None, x=-1400, y=-1600):
        n = knoten(nt, 'ShaderNodeMath', x, y, operation=o)
        for i, v in enumerate((a, bb)):
            if v is None: continue
            if hasattr(v, 'is_output'): nt.links.new(v, n.inputs[i])
            else: n.inputs[i].default_value = v
        return n.outputs['Value']
    cu = op('DIVIDE', sep.outputs['X'], 3.0); cv = op('DIVIDE', sep.outputs['Y'], 3.2)
    fu = op('FRACT', cu); fv = op('FRACT', cv)
    iu = op('FLOOR', cu); iv = op('FLOOR', cv)
    mu = op('MULTIPLY', op('GREATER_THAN', fu, 0.35), op('LESS_THAN', fu, 0.65))
    mv = op('MULTIPLY', op('GREATER_THAN', fv, 0.3), op('LESS_THAN', fv, 0.78))
    maske = op('MULTIPLY', mu, mv)
    oi = knoten(nt, 'ShaderNodeObjectInfo', -1800, -2000)
    zelle = knoten(nt, 'ShaderNodeCombineXYZ', -1200, -1900)
    nt.links.new(iu, zelle.inputs['X']); nt.links.new(iv, zelle.inputs['Y']); nt.links.new(oi.outputs['Random'], zelle.inputs['Z'])
    wn = knoten(nt, 'ShaderNodeTexWhiteNoise', -1000, -1900); wn.noise_dimensions = '3D'
    nt.links.new(zelle.outputs['Vector'], wn.inputs['Vector'])
    an = op('LESS_THAN', wn.outputs['Value'], 0.36, x=-800, y=-1900)
    abst = knoten(nt, 'ShaderNodeVectorMath', -1400, -2200, operation='DISTANCE')
    nt.links.new(oi.outputs['Location'], abst.inputs[0]); abst.inputs[1].default_value = (0.0, 28.0, 0.0)
    welle = knoten(nt, 'ShaderNodeValue', -1400, -2400); welle.name = welle_sock_name; welle.label = welle_sock_name
    schon = op('LESS_THAN', abst.outputs['Value'], welle.outputs['Value'], x=-1100, y=-2300)
    licht = op('MULTIPLY', op('MULTIPLY', maske, an, x=-600, y=-1900), schon, x=-400, y=-1900)
    # Fensterfläche: geschlossene Persiane (dunkles Holz), offen+hell: Innenlicht
    col = mix(nt, 'RGBA', -200, -1500, farbe_alt, (0.05, 0.06, 0.045, 1), maske)
    nt.links.new(col, b.inputs['Base Color'])
    bbn = knoten(nt, 'ShaderNodeBlackbody', -400, -2200); bbn.inputs['Temperature'].default_value = 2500
    nt.links.new(bbn.outputs['Color'], b.inputs['Emission Color'])
    nt.links.new(op('MULTIPLY', licht, 4.0, x=-200, y=-2000), b.inputs['Emission Strength'])
    return m, welle


def stadt(M):
    """Altstadt auf dem Hang zum Meer: einfache Häuser mit Fensterraster,
    Kuppeln, Glockenturm, Gelände, Meer. Die Gasse selbst bleibt ausgespart."""
    # Häuser, Fenster, Dächer, Laternen: film_stadt.py (28.09.: echte Fenster statt Textur-Raster,
    # keine Fenster mehr auf den Dächern)
    import film_stadt
    import types
    wellen = film_stadt.bauen(types.SimpleNamespace(**globals()), M)
    teile = []
    def boden(y):
        return 0.0 if y < 70 else -0.07 * (y - 70)
    # Kuppeln und Turm (Barock): Tambour, Kuppel, Laterne
    for (x, y, r) in ((-38, 64, 6.5), (44, 132, 5.5)):
        z0 = boden(y)
        teile.append(B.kasten('kirche_schiff', 2.6 * r, 3.6 * r, 16, [M['kalk']], fase=0.0, ort=(x, y, z0 - 1)))
        tb = B.drehkoerper('tambour', [(0.0, 0.0), (r, 0.0), (r, 5.0), (0.0, 5.0)], 48, [M['kalk']]); tb.location = (x, y, z0 + 15)
        ku = B.drehkoerper('kuppel', [(0.0, 0.0)] + [(r * 1.02 * math.cos(a), r * 1.15 * math.sin(a)) for a in np.linspace(0, math.pi / 2, 16)], 64, [M['dach']])
        ku.location = (x, y, z0 + 20); teile += [tb, ku]
        la = B.drehkoerper('laterne', [(0.0, 0.0), (1.1, 0.0), (1.1, 2.6), (0.3, 3.4), (0.0, 3.6)], 24, [M['kalk']]); la.location = (x, y, z0 + 20 + r * 1.15); teile.append(la)
    tz = boden(96); turm = B.kasten('turm', 6, 6, 30, [M['kalk']], fase=0.02, ort=(-22, 96, tz - 1)); teile.append(turm)
    teile.append(B.drehkoerper('turmhelm', [(0.0, 0.0), (3.4, 0.0), (0.0, 6.0)], 4, [M['dach']]))
    teile[-1].location = (-22, 96, tz + 29); teile[-1].rotation_euler = (0, 0, math.radians(45))
    # Gelände: Hang mit Rauschen
    bpy.ops.mesh.primitive_grid_add(x_subdivisions=160, y_subdivisions=160, size=1)
    g = bpy.context.active_object; g.name = 'gelaende'; g.scale = (900, 900, 1); g.location = (0, 150, 0)
    bpy.ops.object.transform_apply(scale=True, location=True)
    for v in g.data.vertices:
        x, y = v.co.x, v.co.y
        z = boden(y) - 1.2
        if abs(x) > 160:
            z += (abs(x) - 160) * 0.18                        # Hügel links und rechts
        z += 2.5 * math.sin(x * 0.02) * math.cos(y * 0.017)
        if y > 238:
            z = min(z, -20.0 - (y - 238) * 0.02)
        v.co.z = z
    g.data.materials.append(ph_stoff('Erde', 'old_sandstone_02', 6.0, (0.35, 0.32, 0.26), 0.5))
    uvl = g.data.uv_layers.new(name='UVMap')
    for poly in g.data.polygons:
        for li in poly.loop_indices:
            co = g.data.vertices[g.data.loops[li].vertex_index].co; uvl.data[li].uv = (co.x, co.y)
    # Meer: Wasser mit feinen Wellen, spiegelt Mond und Stadt
    bpy.ops.mesh.primitive_plane_add(size=1); meer = bpy.context.active_object; meer.name = 'meer'
    meer.scale = (4000, 2000, 1); meer.location = (0, 1240, -19.5)
    mm = bpy.data.materials.new('Meer'); mm.use_nodes = True
    nt = mm.node_tree; bsdf = nt.nodes['Principled BSDF']
    bsdf.inputs['Base Color'].default_value = (0.004, 0.008, 0.012, 1); bsdf.inputs['Roughness'].default_value = 0.06
    wn = knoten(nt, 'ShaderNodeTexNoise', -600, -200, **{'Scale': 0.08, 'Detail': 10.0, 'Roughness': 0.6})
    tc = knoten(nt, 'ShaderNodeTexCoord', -800, -200); nt.links.new(tc.outputs['Object'], wn.inputs['Vector'])
    bu = knoten(nt, 'ShaderNodeBump', -300, -200); bu.inputs['Strength'].default_value = 0.35; bu.inputs['Distance'].default_value = 0.4
    nt.links.new(wn.outputs['Fac'], bu.inputs['Height']); nt.links.new(bu.outputs['Normal'], bsdf.inputs['Normal'])
    meer.data.materials.append(mm)
    # Lichtwelle von der Gasse aus: S4 beginnt bei 0, zum Ende von S4 hat sie die Stadt erfasst
    for w in wellen:
        schluessel_wert(w.outputs[0], [(S4 - 1, 0.0), (S4 + 20, 20.0), (S5 - 10, 420.0)])
    mesh_verbinden([t for t in teile if t.name.startswith('stadtdach') or t.name.startswith(('tambour', 'kuppel', 'laterne', 'kirche_schiff', 'turm'))], 'stadt_bauten')


# ====================================================================== Logo (S5)
def logo_bauen():
    """Vecom-Logo in Gold aus vecom-logo.json (Bildmarke, VECOM, DESIGN), als
    Kurven mit Tiefe und runder Fase -- die Fase fängt das Licht, erst sie macht
    Gold glaubwürdig. 1 Logopixel = 0,1 m; Mitte des Logos im Ursprung."""
    d = json.load(open(os.path.join(FILM, 'vecom-logo.json')))
    gold = bpy.data.materials.new('Gold'); gold.use_nodes = True
    nt = gold.node_tree; b = nt.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = (*GOLD, 1); b.inputs['Metallic'].default_value = 1.0
    rausch = knoten(nt, 'ShaderNodeTexNoise', -600, -100, **{'Scale': 60.0, 'Detail': 6.0})
    mr = knoten(nt, 'ShaderNodeMapRange', -400, -100); mr.inputs['To Min'].default_value = 0.16; mr.inputs['To Max'].default_value = 0.26
    nt.links.new(rausch.outputs['Fac'], mr.inputs['Value']); nt.links.new(mr.outputs['Result'], b.inputs['Roughness'])
    bbn = knoten(nt, 'ShaderNodeBlackbody', -400, -400); bbn.inputs['Temperature'].default_value = 1900
    nt.links.new(bbn.outputs['Color'], b.inputs['Emission Color'])
    b.inputs['Emission Strength'].default_value = 0.0
    tiefe = {'marke': 0.9, 'wort': 0.55, 'design': 0.35}
    objs = []
    for gruppe, konturen in d.items():
        cu = bpy.data.curves.new('logo_' + gruppe, 'CURVE'); cu.dimensions = '2D'; cu.fill_mode = 'BOTH'
        cu.extrude = tiefe[gruppe] / 2; cu.bevel_depth = 0.06 if gruppe != 'design' else 0.035; cu.bevel_resolution = 3
        cu.offset = -cu.bevel_depth
        for k in konturen:
            sp = cu.splines.new('POLY'); sp.points.add(len(k) - 1)
            for i, (x, y) in enumerate(k):
                sp.points[i].co = ((x - 280) * 0.1, -(y - 200) * 0.1, 0.0, 1.0)
            sp.use_cyclic_u = True
        o = bpy.data.objects.new('logo_' + gruppe, cu); bpy.context.scene.collection.objects.link(o)
        cu.materials.append(gold)
        objs.append(o)
    wurzel = bpy.data.objects.new('logo', None); bpy.context.scene.collection.objects.link(wurzel)
    for o in objs:
        o.parent = wurzel; o.rotation_euler = (math.radians(90), 0, 0)   # aufrecht, Vorderseite -y
    return wurzel, objs, gold


def teilchen(logo_objs, anzahl=2600):
    """Lichtpunkte steigen aus der Stadt auf und setzen sich zum Logo
    zusammen. Geometry Nodes: Punkte tragen Start (über der Stadt) und
    Verzögerung; die Position läuft auf einem Bogen zum Ziel auf der
    Logofläche. Bewegungsunschärfe macht aus den Punkten Lichtspuren."""
    bpy.context.view_layer.update()
    dg = bpy.context.evaluated_depsgraph_get()
    ziele = []
    for o in logo_objs:
        me = bpy.data.meshes.new_from_object(o.evaluated_get(dg))
        me.transform(o.matrix_world)
        tri = [(Vector(me.vertices[p.vertices[0]].co), Vector(me.vertices[p.vertices[i]].co), Vector(me.vertices[p.vertices[i + 1]].co))
               for p in me.polygons if p.normal.y < -0.5 for i in range(1, len(p.vertices) - 1)]
        fl = np.array([((b - a).cross(c - a)).length / 2 for a, b, c in tri]); fl /= fl.sum()
        wahl = np.random.default_rng(3).choice(len(tri), int(anzahl * 0.34), p=fl)
        for t in wahl:
            a, b, c = tri[t]; r1, r2 = np.random.random(2)
            if r1 + r2 > 1: r1, r2 = 1 - r1, 1 - r2
            ziele.append(a + (b - a) * r1 + (c - a) * r2 + Vector((0, -0.05, 0)))
        bpy.data.meshes.remove(me)
    rng = np.random.default_rng(11)
    me = bpy.data.meshes.new('teilchen'); me.from_pydata([tuple(z) for z in ziele], [], []); me.update()
    start = me.attributes.new('start', 'FLOAT_VECTOR', 'POINT'); verz = me.attributes.new('verz', 'FLOAT', 'POINT')
    st = np.column_stack([rng.uniform(-110, 110, len(ziele)), rng.uniform(-20, 180, len(ziele)), rng.uniform(8, 22, len(ziele))])
    start.data.foreach_set('vector', st.astype(np.float32).ravel())
    verz.data.foreach_set('value', rng.uniform(0, 20, len(ziele)).astype(np.float32))
    o = bpy.data.objects.new('teilchen', me); bpy.context.scene.collection.objects.link(o)
    ng = bpy.data.node_groups.new('Teilchenflug', 'GeometryNodeTree')
    ng.interface.new_socket('Geometry', in_out='INPUT', socket_type='NodeSocketGeometry')
    ng.interface.new_socket('Geometry', in_out='OUTPUT', socket_type='NodeSocketGeometry')
    N = ng.nodes; L = ng.links
    gi = N.new('NodeGroupInput'); go = N.new('NodeGroupOutput')
    zeit = N.new('GeometryNodeInputSceneTime')
    a_st = N.new('GeometryNodeInputNamedAttribute'); a_st.data_type = 'FLOAT_VECTOR'; a_st.inputs['Name'].default_value = 'start'
    a_vz = N.new('GeometryNodeInputNamedAttribute'); a_vz.data_type = 'FLOAT'; a_vz.inputs['Name'].default_value = 'verz'
    pos = N.new('GeometryNodeInputPosition')
    def m(o_, a, b=None):
        n = N.new('ShaderNodeMath'); n.operation = o_
        for i, v in enumerate((a, b)):
            if v is None: continue
            if hasattr(v, 'is_output'): L.new(v, n.inputs[i])
            else: n.inputs[i].default_value = v
        return n.outputs[0]
    # f = smoothstep((frame - S5 - verz) / 58)
    t = m('SUBTRACT', m('SUBTRACT', zeit.outputs['Frame'], float(S5)), a_vz.outputs['Attribute'])
    f = m('MINIMUM', m('MAXIMUM', m('DIVIDE', t, 50.0), 0.0), 1.0)
    f = m('MULTIPLY', m('MULTIPLY', f, f), m('SUBTRACT', 3.0, m('MULTIPLY', f, 2.0)))
    vm = N.new('ShaderNodeMix'); vm.data_type = 'VECTOR'
    L.new(f, vm.inputs[0]); L.new(a_st.outputs['Attribute'], vm.inputs[4]); L.new(pos.outputs[0], vm.inputs[5])
    bogen = m('MULTIPLY', m('SINE', m('MULTIPLY', f, math.pi)), 26.0)
    hoch = N.new('ShaderNodeCombineXYZ'); L.new(bogen, hoch.inputs['Z'])
    summe = N.new('ShaderNodeVectorMath'); summe.operation = 'ADD'
    L.new(vm.outputs[1], summe.inputs[0]); L.new(hoch.outputs[0], summe.inputs[1])
    sp = N.new('GeometryNodeSetPosition'); L.new(gi.outputs[0], sp.inputs['Geometry']); L.new(summe.outputs[0], sp.inputs['Position'])
    # sichtbar erst ab Start, verschwinden, wenn das Gold steht
    groesse = m('MULTIPLY', m('GREATER_THAN', t, 0.0), m('SUBTRACT', 1.0, m('MINIMUM', m('MAXIMUM', m('DIVIDE', m('SUBTRACT', zeit.outputs['Frame'], float(S5 + 70)), 14.0), 0.0), 1.0)))
    kugel = N.new('GeometryNodeMeshIcoSphere'); kugel.inputs['Radius'].default_value = 0.07; kugel.inputs['Subdivisions'].default_value = 1
    iop = N.new('GeometryNodeInstanceOnPoints')
    L.new(sp.outputs[0], iop.inputs['Points']); L.new(kugel.outputs['Mesh'], iop.inputs['Instance'])
    L.new(groesse, iop.inputs['Scale'])
    smat = N.new('GeometryNodeSetMaterial')
    lm = licht_stoff('Lichtpunkt', 2100, 14.0); smat.inputs['Material'].default_value = lm
    L.new(iop.outputs[0], smat.inputs['Geometry']); L.new(smat.outputs[0], go.inputs[0])
    md = o.modifiers.new('Flug', 'NODES'); md.node_group = ng
    return o


def reflexkarte():
    """Reflexkarte hinter der Kamera, nur für Spiegelungen sichtbar (wie im Produktstudio):
    dunkle Fläche mit zwei warmen Lichtbändern. Ohne sie spiegelt das Gold nur den hellen
    Horizont und wird champagnerweiß (Messung 28.09.: Sättigung 0.26 statt ~0.55)."""
    if bpy.data.objects.get('logo_reflexkarte'):
        return bpy.data.objects['logo_reflexkarte']
    bpy.ops.mesh.primitive_plane_add(size=1.0, location=(0.0, -6.0, 50.0))
    k = bpy.context.active_object; k.name = 'logo_reflexkarte'
    k.scale = (160.0, 70.0, 1.0)
    k.rotation_euler = (math.radians(-90), 0.0, 0.0)          # Normale zeigt nach +y (zum Logo)
    mat = bpy.data.materials.new('Reflexkarte'); mat.use_nodes = True
    nt = mat.node_tree; nt.nodes.clear()
    aus = nt.nodes.new('ShaderNodeOutputMaterial'); em = nt.nodes.new('ShaderNodeEmission')
    tc = nt.nodes.new('ShaderNodeTexCoord'); sep = nt.nodes.new('ShaderNodeSeparateXYZ')
    cr = nt.nodes.new('ShaderNodeValToRGB')
    nt.links.new(tc.outputs['Generated'], sep.inputs[0]); nt.links.new(sep.outputs['Y'], cr.inputs['Fac'])
    e = cr.color_ramp; e.interpolation = 'EASE'
    e.elements[0].position = 0.0; e.elements[0].color = (0.004, 0.003, 0.002, 1)
    e.elements[1].position = 1.0; e.elements[1].color = (0.004, 0.003, 0.002, 1)
    for pos, farbe in ((0.30, (0.004, 0.003, 0.002, 1)), (0.36, (1.0, 0.72, 0.40, 1)), (0.42, (0.004, 0.003, 0.002, 1)),
                       (0.62, (0.004, 0.003, 0.002, 1)), (0.70, (0.9, 0.62, 0.30, 1)), (0.80, (0.004, 0.003, 0.002, 1))):
        el = e.elements.new(pos); el.color = farbe
    nt.links.new(cr.outputs['Color'], em.inputs['Color']); em.inputs['Strength'].default_value = REFLEX_STAERKE
    nt.links.new(em.outputs[0], aus.inputs['Surface'])
    k.data.materials.append(mat)
    for eig in ('visible_camera', 'visible_diffuse', 'visible_transmission', 'visible_volume_scatter', 'visible_shadow'):
        try:
            setattr(k, eig, False)
        except Exception:
            pass
    k.visible_glossy = True
    return k


def logo_szene():
    wurzel, objs, gold = logo_bauen()
    reflexkarte()
    wurzel.location = (0.0, 150.0, 48.0)
    bpy.context.view_layer.update()
    teilchen(objs)
    # Gold: erst glühend (Licht, das sich sammelt), dann kühlt es zu Metall ab
    em = gold.node_tree.nodes['Principled BSDF'].inputs['Emission Strength']
    # Spitze niedrig halten: bei 1.3 zieht AgX das Glühen nach Weiß (Vorschau 28.09., Bild 936)
    schluessel_wert(em, [(S5 + 41, 0.45), (S5 + 72, 0.55), (S5 + 104, 0.0)])
    # Sichtbarkeit: Logo taucht mit den ersten ankommenden Punkten auf
    for o in objs + [wurzel]:
        o.hide_render = True; o.keyframe_insert('hide_render', frame=S5 + 40)
        o.hide_render = False; o.keyframe_insert('hide_render', frame=S5 + 41)
    for o in objs:
        o.scale = (0.96, 0.96, 0.02); o.keyframe_insert('scale', frame=S5 + 41)
        o.scale = (1, 1, 1.0); o.keyframe_insert('scale', frame=S5 + 88)
    # Licht für das Gold: warmes Führungslicht von unten links (Stadtschein),
    # kühles Kantenlicht von oben rechts (Mond) -- ohne Licht bleibt Gold schwarz
    for name, ort, farbe, e, gr in (('logo_fuehrung', (-40, 95, 20), (1.0, 0.72, 0.42), 26000.0, 22.0),
                                     ('logo_kante', (45, 185, 90), (0.72, 0.8, 1.0), 16000.0, 18.0),
                                     ('logo_front', (0, 60, 50), (1.0, 0.88, 0.7), 9000.0, 30.0)):
        ld = bpy.data.lights.new(name, 'AREA'); ld.size = gr; ld.energy = 0.0; ld.color = farbe
        ld.keyframe_insert('energy', frame=S5 + 30); ld.energy = e; ld.keyframe_insert('energy', frame=S5 + 90)
        lo = bpy.data.objects.new(name, ld); bpy.context.scene.collection.objects.link(lo); lo.location = ort
        lo.rotation_euler = (Vector((0, 150, 48)) - Vector(ort)).to_track_quat('-Z', 'Y').to_euler()
        # Licht nur aufs Logo (sonst hellt es Stadt und Meer auf)
        try:
            lo.visible_diffuse = True
        except Exception:
            pass
    # Adresse unter dem Logo, klein, in Gold
    fnt = os.path.join(FILM, 'Montserrat-600.ttf')
    td = bpy.data.curves.new('adresse', 'FONT'); td.body = 'vecom-design.it'; td.align_x = 'CENTER'; td.size = 2.6
    if os.path.exists(fnt):
        td.font = bpy.data.fonts.load(fnt)
    td.extrude = 0.08; td.bevel_depth = 0.02
    to = bpy.data.objects.new('adresse', td); bpy.context.scene.collection.objects.link(to)
    to.parent = wurzel; to.location = (0, 0, -24.5); to.rotation_euler = (math.radians(90), 0, 0)
    td.materials.append(gold)
    to.scale = (0.001, 0.001, 0.001); to.keyframe_insert('scale', frame=S5 + 84)
    to.scale = (1, 1, 1); to.keyframe_insert('scale', frame=S5 + 100)
    return wurzel


# ====================================================================== Aufbau
MODELLE = ['potted_plant_01', 'potted_plant_02', 'planter_pot_clay', 'wine_bottles_01', 'wine_barrel_01', 'wooden_crate_02',
           'ceramic_vase_01', 'Lantern_01', 'outdoor_table_chair_set_01', 'lemon', 'standing_chalkboard_01',
           'folding_wooden_stool', 'water_manhole_cover', 'book_encyclopedia_set_01', 'decorative_book_set_01',
           'exterior_aircon_unit', 'utility_box_01', 'metal_trash_can', 'potted_plant_04']
LAEDEN = {-1: {15.5: 'ceramiche', 27.5: 'enoteca', 39.5: 'libreria'}, 1: {20.5: 'bar', 33.5: 'alimentari'}}
EINSCHALTEN = {'ceramiche': 150, 'bar': 192, 'enoteca': 234, 'alimentari': 274, 'libreria': 314}


def grenzen(objs):
    pts = []
    for o in objs:
        for k in [o] + list(o.children_recursive):
            if k.type == 'MESH':
                pts += [k.matrix_world @ Vector(c) for c in k.bound_box]
    if not pts:
        return Vector((0, 0, 0)), Vector((0, 0, 0))
    lo = Vector((min(p.x for p in pts), min(p.y for p in pts), min(p.z for p in pts)))
    hi = Vector((max(p.x for p in pts), max(p.y for p in pts), max(p.z for p in pts)))
    return lo, hi


def kirche(M, lat_modell):
    """Abschluss der Gasse: Kirchenfront aus Kalksteinquadern über fünf Stufen."""
    y0 = GASSE_L + 1.4
    stein = ph_stoff('Quader', 'sandstone_blocks_08', 2.2, (1.0, 0.93, 0.8), 0.95, feucht_unten=0.4, streifen=0.3)
    teile = []
    for i in range(5):
        teile.append(box('stufe', -3.0, 3.0, GASSE_L - 1.8 + i * 0.64, y0, i * 0.16, (i + 1) * 0.16, M['kalk'], 0.01))
    z0 = 0.8
    teile.append(box('front_l', -8, -1.2, y0, y0 + 1.2, z0, 17.5, stein, 0.004))
    teile.append(box('front_r', 1.2, 8, y0, y0 + 1.2, z0, 17.5, stein, 0.004))
    teile.append(box('front_o', -1.2, 1.2, y0, y0 + 1.2, z0 + 4.2, 17.5, stein, 0.004))
    teile.append(box('portal_tuer', -1.2, 1.2, y0 + 0.2, y0 + 0.3, z0, z0 + 4.2, M['tuer'], 0.004))
    teile.append(box('portal_laibung_l', -1.2, -1.05, y0, y0 + 0.2, z0, z0 + 4.2, M['kalk'], 0.004))
    teile.append(box('portal_laibung_r', 1.05, 1.2, y0, y0 + 0.2, z0, z0 + 4.2, M['kalk'], 0.004))
    pl = bpy.data.lights.new('portal_licht', 'AREA'); pl.size = 2.0; pl.energy = 60.0; pl.color = (1.0, 0.78, 0.52)
    po = bpy.data.objects.new('portal_licht', pl); bpy.context.scene.collection.objects.link(po)
    po.location = (0.0, y0 - 0.9, z0 + 4.4); po.rotation_euler = (math.radians(35), 0, 0)
    po.visible_camera = False
    for x in (-1.5, 1.5):
        teile.append(box('portal_saeule', x - 0.28, x + 0.28, y0 - 0.25, y0, z0, z0 + 4.8, M['kalk'], 0.01))
    teile.append(box('portal_giebel', -2.0, 2.0, y0 - 0.35, y0, z0 + 4.8, z0 + 5.3, M['kalk'], 0.012))
    for x in (-7.6, -4.2, 4.2, 7.6):
        teile.append(box('pilaster', x - 0.35, x + 0.35, y0 - 0.2, y0, z0, 16.5, M['kalk'], 0.008))
    teile.append(box('gesims', -8.2, 8.2, y0 - 0.4, y0 + 0.1, 16.5, 17.1, M['kalk'], 0.012))
    def glas_masswerk(nt):
        """Rosette von innen erleuchtet: Bernsteinglas, 12 Speichen und 3 Ringe als
        dunkles Bleimaßwerk, einzelne Felder gedämpft rot/blau (statt Bonbon-Mosaik)."""
        tc = knoten(nt, 'ShaderNodeTexCoord', -1400, 200); sep = knoten(nt, 'ShaderNodeSeparateXYZ', -1200, 200)
        nt.links.new(tc.outputs['Object'], sep.inputs['Vector'])
        def op(o, a, b_=None):
            n_ = knoten(nt, 'ShaderNodeMath', -1000, 200, operation=o)
            for i, v_ in enumerate((a, b_)):
                if v_ is None: continue
                if hasattr(v_, 'is_output'): nt.links.new(v_, n_.inputs[i])
                else: n_.inputs[i].default_value = v_
            return n_.outputs['Value']
        x, y = sep.outputs['X'], sep.outputs['Y']
        r = op('SQRT', op('ADD', op('MULTIPLY', x, x), op('MULTIPLY', y, y)))
        ang = op('DIVIDE', op('ADD', op('ARCTAN2', y, x), math.pi), 2 * math.pi)
        feld = op('FRACT', op('MULTIPLY', ang, 12.0))
        speiche = op('LESS_THAN', op('MINIMUM', feld, op('SUBTRACT', 1.0, feld)), op('DIVIDE', 0.035, op('MAXIMUM', r, 0.05)))
        ring = op('MAXIMUM', op('LESS_THAN', op('ABSOLUTE', op('SUBTRACT', r, 0.38)), 0.03),
                  op('MAXIMUM', op('LESS_THAN', op('ABSOLUTE', op('SUBTRACT', r, 0.78)), 0.03), op('GREATER_THAN', r, 1.1)))
        blei = op('MAXIMUM', speiche, ring)
        idx = op('FLOOR', op('MULTIPLY', ang, 12.0))
        wn = knoten(nt, 'ShaderNodeTexWhiteNoise', -800, 400); wn.noise_dimensions = '1D'
        nt.links.new(op('ADD', idx, op('FLOOR', op('MULTIPLY', r, 3.0))), wn.inputs['W'])
        cr = knoten(nt, 'ShaderNodeValToRGB', -600, 400); e = cr.color_ramp; e.interpolation = 'CONSTANT'
        e.elements[0].position = 0.0; e.elements[0].color = (1.0, 0.62, 0.26, 1)
        e.elements[1].position = 0.88; e.elements[1].color = (0.55, 0.2, 0.1, 1)
        el = e.elements.new(0.95); el.color = (0.2, 0.24, 0.4, 1)
        nt.links.new(wn.outputs['Value'], cr.inputs['Fac'])
        return mix(nt, 'RGBA', -300, 300, cr.outputs['Color'], (0.0, 0.0, 0.0, 1), blei)
    rose = B.drehkoerper('rosette', [(0.0, 0.0), (1.15, 0.0), (1.15, 0.03), (0.0, 0.03)], 64, [licht_stoff('Rosette', 2100, 0.16, glas_masswerk)])
    rose.rotation_euler = (math.radians(90), 0, 0); rose.location = (0, y0 - 0.01, 10.6)
    ring = B.drehkoerper('rosette_ring', [(1.1, 0.0), (1.45, 0.0), (1.45, 0.25), (1.1, 0.25)], 64, [M['kalk']])
    ring.rotation_euler = (math.radians(90), 0, 0); ring.location = (0, y0 - 0.02, 10.6); teile.append(ring)
    mesh_verbinden(teile, 'kirche')
    F = Fassade(1)
    for x in (-2.4, 2.4):
        ld = bpy.data.lights.new('kirchlaterne', 'POINT'); ld.energy = 55.0; ld.shadow_soft_size = 0.06
        ld.color = (1.0, 0.72, 0.42)
        lo = bpy.data.objects.new('kirchlaterne', ld); bpy.context.scene.collection.objects.link(lo)
        lo.location = (x, y0 - 0.55, z0 + 3.4)
        if lat_modell:
            kopie(lat_modell, (x, y0 - 0.55, z0 + 3.2), math.radians(180), 1.0)


def einzel_setzen(o, ort, dreh_z, skala=1.0):
    """Ein einzelnes Objekt (ohne Gruppe) als Linked Copy; Ursprung bleibt."""
    c = o.copy(); bpy.context.scene.collection.objects.link(c)
    c.parent = None; c.hide_render = False; c.hide_viewport = False
    c.location = ort; c.rotation_euler = (0, 0, dreh_z); c.scale = (skala,) * 3
    return c


def gassen_details(M, mod, haeuser, rng):
    """Was eine echte sizilianische Gasse ausmacht und der Vorschau fehlte: Fallrohre an den
    Hausgrenzen, Klimageräte zwischen den Fenstern, Zählerkästen, Kabel an der Wand,
    Mülleimer. Alles aus echten Maßen; Modelle von Poly Haven (CC0)."""
    rohr_st = [B.stoff('Fallrohr grau', (0.36, 0.35, 0.33), rau=0.45), B.stoff('Fallrohr Kupfer', (0.28, 0.16, 0.09), rau=0.35, metall=0.8)]
    kabel_st = B.stoff('Wandkabel', (0.015, 0.015, 0.016), rau=0.55)
    klima = [o for w in mod.get('exterior_aircon_unit', []) for o in [w] + list(w.children_recursive) if o.type == 'MESH']
    teile = []
    for i, (F, u0, u1, info, art) in enumerate(haeuser):
        oben = info.get('oben') or 10.0
        breite = u1 - u0
        # Fallrohr an der linken Hausgrenze (Regen von der Traufe), unten mit Knie
        if i % 2 == 0 or rng.random() < 0.4:
            uu = u0 + 0.12; w = -0.09
            st = rohr_st[0] if rng.random() < 0.75 else rohr_st[1]
            p0 = F.punkt(uu, oben + 0.25, w); p1 = F.punkt(uu, 0.25, w); p2 = F.punkt(uu, 0.05, w - 0.18)
            teile.append(B.rohr('fallrohr', [tuple(p0), tuple(p1), tuple(p2)], 0.045, [st]))
            for vv in np.arange(1.2, oben, 2.0):                                  # Rohrschellen
                teile.append(F.box('schelle', uu - 0.06, uu + 0.06, vv, vv + 0.04, -0.15, 0.0, M['eisen'], 0.004))
        # Kabel: waagerecht über dem Erdgeschoss, mit Durchhang zwischen Schellen
        if rng.random() < 0.7:
            vv = EG_H + rng.uniform(-0.35, -0.1)
            pts = []
            for k, uu in enumerate(np.linspace(u0 + 0.05, u1 - 0.05, 7)):
                pts.append(tuple(F.punkt(uu, vv - (0.025 if k % 2 else 0.0), -0.03)))
            teile.append(B.rohr('wandkabel', pts, 0.009, [kabel_st]))
        # Klimagerät zwischen zwei Fenstern eines Obergeschosses
        nf = max(1, int((breite - 0.9) / 2.25))
        if klima and nf >= 2 and rng.random() < 0.55:
            us = [u0 + breite * (k + 0.5) / nf for k in range(nf)]
            k = rng.randrange(nf - 1); um = (us[k] + us[k + 1]) / 2
            og = rng.randint(1, max(1, int(round((oben - EG_H - 0.6) / OG_H)) - 1))
            vv = EG_H + og * OG_H + 0.55
            einzel_setzen(klima[rng.randrange(len(klima))], F.punkt(um, vv, -0.2), F.dreh_z(), 0.85)
        # Zählerkasten neben der Haustür (nicht bei Läden)
        if not art and 'utility_box_01' in mod and rng.random() < 0.35 and info.get('tuer'):
            ut, th = info['tuer']
            kopie(mod['utility_box_01'], F.punkt(min(u1 - 0.45, ut + 1.05), 0.0, -0.2), F.dreh_z(), 0.9)
    # Mülleimer, ein paar Pflanzen mehr
    if 'metal_trash_can' in mod:
        F = Fassade(1); kopie(mod['metal_trash_can'], F.punkt(24.8, 0.0, -0.35), F.dreh_z() + 0.3, 1.0)
        F = Fassade(-1); kopie(mod['metal_trash_can'], F.punkt(46.2, 0.0, -0.35), F.dreh_z() - 0.2, 1.0)
    if 'potted_plant_04' in mod:
        for (sd, uu) in ((1, 9.3), (-1, 33.1), (1, 44.0)):
            F = Fassade(sd); kopie(mod['potted_plant_04'], F.punkt(uu, 0.0, -0.3), rng.uniform(0, 6.28), rng.uniform(0.9, 1.2))
    mesh_verbinden([t for t in teile if t is not None and t.type == 'MESH' and not t.modifiers], 'gassen_details')


def konsole_geschwungen(F, M, uk, v, T):
    """Balkonkonsole als geschwungene Kalksteinmensole (S-Profil) statt gestapelter Quader."""
    L = T - 0.12
    prof = [(0.0, v - 0.14), (-L, v - 0.14), (-L, v - 0.21)]
    for t in np.linspace(0.0, 1.0, 14)[1:]:
        s_ = t * t * (3 - 2 * t)
        prof.append((-L * (1 - s_) - 0.05 * math.sin(math.pi * t), v - 0.21 - 0.44 * t))
    prof.append((0.0, v - 0.65))
    bm = bmesh.new()
    ringe = []
    for uu in (uk - 0.07, uk + 0.07):
        ringe.append([bm.verts.new((F.x(w), uu, z)) for w, z in prof])
    bm.faces.new(ringe[0]); bm.faces.new(ringe[1][::-1])
    n = len(prof)
    for i in range(n):
        j = (i + 1) % n
        bm.faces.new((ringe[0][i], ringe[0][j], ringe[1][j], ringe[1][i]))
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    me = bpy.data.meshes.new('konsole'); bm.to_mesh(me); bm.free(); me.materials.append(M['kalk'])
    uvl = me.uv_layers.new(name='UVMap')
    for poly in me.polygons:
        for li in poly.loop_indices:
            co = me.vertices[me.loops[li].vertex_index].co; uvl.data[li].uv = (co.y + co.x, co.z)
    o = bpy.data.objects.new('konsole', me); bpy.context.scene.collection.objects.link(o)
    return o


def bau():
    B.leeren()
    sc = bpy.context.scene
    sc.render.fps = FPS; sc.frame_start = S1; sc.frame_end = ENDE - 1
    M = materialien()
    mod = {}
    for n in MODELLE:
        try:
            mod[n] = ph_modell(n)
        except Exception as e:
            print('MODELL FEHLT', n, e)
    welt()
    strasse(M)
    pflanzen = [mod[k] for k in ('potted_plant_01', 'potted_plant_02') if k in mod]
    laeden = {}
    haeuser = []
    for seite in (-1, 1):
        F = Fassade(seite)
        u = -9.0 if seite < 0 else -7.2
        while u < GASSE_L + 1.4:
            br = RNG.uniform(5.2, 7.6)
            if u + br > GASSE_L + 1.4:
                br = GASSE_L + 1.4 - u
                if br < 2.5:
                    break
            mitte = u + br / 2
            art = next((a for yy, a in LAEDEN[seite].items() if abs(yy - mitte) < br / 2), None)
            putz = M['putze'][RNG.randrange(len(M['putze']))]
            M['persiana'] = M['persianen'][RNG.randrange(len(M['persianen']))]
            info = haus(F, M, u, u + br, RNG.uniform(10.2, 14.2), putz, laden={'name': art} if art else None, pflanzen=pflanzen)
            haeuser.append((F, u, u + br, info, art))
            if art:
                info['F'] = F; laeden[art] = info
            elif RNG.random() < 0.6 and pflanzen:
                ut = info['tuer'][0]
                for sg in (-1, 1):
                    kopie(pflanzen[RNG.randrange(len(pflanzen))], F.punkt(ut + sg * 1.0, 0.0, -0.35), RNG.uniform(0, 6.28), RNG.uniform(0.9, 1.25))
            u += br
    # Läden einrichten und schalten
    for art, info in laeden.items():
        F = info['F']
        teile, birnen, ld = laden_einrichten(F, M, info, art, mod, RNG)
        mesh_verbinden(teile, 'einrichtung_' + art)
        bst = B.stoff('Birne ' + art, (1.0, 0.85, 0.6), emiss=((1.0, 0.62, 0.3), 0.0))
        for bo in birnen:
            bo.data.materials.clear(); bo.data.materials.append(bst)
        laden_schalten(info, ld, bst, EINSCHALTEN[art], M)
    gassen_details(M, mod, haeuser, RNG)
    # Draußen vor den Läden
    if 'alimentari' in laeden and 'wooden_crate_02' in mod:
        F = laeden['alimentari']['F']; uc = laeden['alimentari']['uc']
        lo, hi = grenzen(mod['wooden_crate_02'])
        for k, du in enumerate((-1.75, 1.75)):
            p = F.punkt(uc + du, 0.0, -0.45)
            kopie(mod['wooden_crate_02'], p, F.dreh_z(), 1.0)
            if 'lemon' in mod:
                llo, lhi = grenzen(mod['lemon'])
                for i in range(46):
                    q = p + Vector((RNG.uniform(-0.18, 0.18), RNG.uniform(-0.24, 0.24), HOEHE['wooden_crate_02'] - 0.03 + RNG.uniform(0, 0.05)))
                    kopie(mod['lemon'], q, RNG.uniform(0, 6.28), RNG.uniform(0.9, 1.1))
    if 'ceramiche' in laeden and 'planter_pot_clay' in mod:
        F = laeden['ceramiche']['F']; uc = laeden['ceramiche']['uc']
        for du in (-1.8, 1.8):
            kopie(mod['planter_pot_clay'], F.punkt(uc + du, 0.0, -0.35), RNG.uniform(0, 6.28), 1.3)
    tisch = None
    if 'bar' in laeden and 'outdoor_table_chair_set_01' in mod:
        F = laeden['bar']['F']; uc = laeden['bar']['uc']
        tisch = kopie(mod['outdoor_table_chair_set_01'], F.punkt(uc - 0.35, 0.0, -0.72), F.dreh_z() + math.radians(8), 1.0)
        if 'standing_chalkboard_01' in mod:
            kopie(mod['standing_chalkboard_01'], F.punkt(uc + 1.75, 0.0, -0.35), F.dreh_z() + math.radians(-20), 1.0)
    # Laternen, Kabel, Wäsche
    lat = mod.get('Lantern_01')
    teile = []
    for seite, u, v in ((1, -1.5, 3.55), (-1, 5.2, 3.55), (1, 11.6, 3.55), (-1, 18.4, 3.55), (1, 25.8, 3.55), (-1, 31.2, 3.55), (1, 37.4, 3.55), (-1, 44.6, 3.55), (1, 51.0, 3.55), (-1, 57.4, 3.55)):
        teile += laterne(Fassade(seite), M, u, v, lat)
    for y, z in ((6.0, 6.8), (13.6, 7.6), (35.2, 6.9), (47.0, 7.8)):
        teile.append(kabel_quer(M, y, z))
    for y, z in ((9.6, 7.4), (22.4, 6.6), (40.2, 8.1)):
        teile += waescheleine(M, y, z, RNG)
    mesh_verbinden([t for t in teile if t.type == 'MESH' and not t.modifiers], 'gassendetails')
    kirche(M, lat)
    stadt(M)
    logo_szene()
    # Originale der Modelle ausblenden (nur Kopien sind in der Szene)
    for w in mod.values():
        verstecken(w)
    # ------------------------------------------------ Telefon auf dem Bartisch
    import pr_arbeiten as PA
    S = PA.stoffe()
    handy_info = None
    if tisch is not None:
        bpy.context.view_layer.update()
        lo, hi = grenzen([tisch])
        F = laeden['bar']['F']; uc = laeden['bar']['uc']
        tp = F.punkt(uc - 0.35, 0.0, -0.72)
        platte = hi.z
        PA.TISCH_Z = platte
        handy, ecken = PA.telefon('handy', (tp.x - 0.05, tp.y - 0.08, platte + 0.0005), math.radians(200), os.path.join(TEXA, 'jonika-handy.png'), S, hell=1.0)
        PA.tasse('espresso', (tp.x + 0.12, tp.y + 0.1))
        em = bpy.data.materials['handy Anzeige'].node_tree.nodes['Principled BSDF'].inputs['Emission Strength']
        schluessel_wert(em, [(S3 + 22, 0.0), (S3 + 28, 2.2)])
        handy_info = {'ort': list(tp), 'platte': platte}
    # ------------------------------------------------ Laptop auf der Theke im Buchladen (S3B)
    laptop_info = None
    if 'libreria' in laeden:
        info = laeden['libreria']; F = info['F']
        # Laptop hinter der linken Schaufensterscheibe (zwischen Pfosten a+0.02 und uc-0.45),
        # nicht hinter der Mauer: Vorschau 28.09. zeigte bei S3B nur Putz (Sichtlinie durch eg_l).
        ul = info['a'] + 0.28
        lp = F.punkt(ul, 1.04, 2.05)
        lap, ecken_l = PA.laptop('laptop_buch', (lp.x, lp.y, lp.z + 0.001), F.dreh_z(),
                                 os.path.join(TEXA, 'trendonix-laptop.png'), S, oeffnung=108.0, hell=1.0)
        laptop_info = {'ort': lp, 'F': F, 'u': ul, 'a': info['a'], 'uc': info['uc']}
    # ------------------------------------------------ Kameras S1-S3
    for fmt, fit, lens1, lens3 in (('quer', 'HORIZONTAL', 30, 55), ('hoch', 'VERTICAL', 24, 42)):
        c = kamera_neu('kamera_' + fmt, fit)
        kamera_setzen(c, S1, (0.45, -2.2, 1.62), (-0.15, 30.0, 2.9), lens1, 18.0, 4.0)
        kamera_setzen(c, S2 - 1, (0.35, 3.6, 1.6), (-0.1, 34.0, 2.7), lens1, 16.0, 4.0)
        kamera_setzen(c, S3 - 1, (0.15, 12.4, 1.55), (0.25, 44.0, 2.4), lens1, 14.0, 4.0)
        glatte_kurven(c)
        if handy_info:
            c3 = kamera_neu('kamera_%s_s3' % fmt, fit)
            tp = Vector(handy_info['ort']); z = handy_info['platte']
            ziel = Vector((tp.x - 0.05, tp.y - 0.08, z))
            laden_ziel = Vector((tp.x - 0.4, tp.y + 7.0, 1.7))
            oben = ziel + Vector((-0.16, -0.2, 0.46))
            kamera_setzen(c3, S3, oben, ziel, lens3, (ziel - oben).length, 2.8)
            kamera_setzen(c3, S3 + 96, ziel + Vector((-0.2, -0.24, 0.5)), ziel, lens3, (ziel - oben).length + 0.07, 2.8)
            weg = ziel + Vector((-1.5, -1.6, 0.62))
            kamera_setzen(c3, S3B - 1, weg, laden_ziel, lens3 * 0.72, (laden_ziel - weg).length, 2.8)
            glatte_kurven(c3)
    if laptop_info:
        for fmt, fit, lb in (('quer', 'HORIZONTAL', 50), ('hoch', 'VERTICAL', 38)):
            cb = kamera_neu('kamera_%s_s3b' % fmt, fit)
            F = laptop_info['F']; u = laptop_info['u']; lp = laptop_info['ort']
            ziel = lp + Vector((0, 0, 0.14))
            # Kamera vor der Scheibe, Sichtlinie trifft das Glas zwischen den Pfosten (prüft pruefe_sicht)
            a = F.punkt(u + 0.34, 1.5, -1.45); b_ = F.punkt(u + 0.2, 1.32, -0.28)
            kamera_setzen(cb, S3B, a, ziel, lb, (ziel - a).length, 2.8)
            kamera_setzen(cb, S4 - 1, b_, ziel, lb * S3B_ZOOM, (ziel - b_).length, 2.8)
            glatte_kurven(cb)
    # ------------------------------------------------ Kameras S4-S5: Kran über die Dächer, dann zum Logo
    for fmt, fit, l0, l1, l2 in (('quer', 'HORIZONTAL', 28, 30, 38), ('hoch', 'VERTICAL', 20, 20, 24)):
        c = kamera_neu('kamera_%s_s4' % fmt, fit)
        kamera_setzen(c, S4, (0.1, 28.5, 1.7), (0.0, 66.0, 3.5), l0, 30.0, 5.6)
        kamera_setzen(c, S4 + 40, (0.0, 28.2, 12.0), (0.0, 86.0, 8.0), l0, 60.0, 5.6)
        kamera_setzen(c, S5 - 1, (0.0, 6.0, 40.0), (0.0, 160.0, 26.0), l1, 120.0, 5.6)
        kamera_setzen(c, S5 + 60, (0.0, 18.0, 44.0), (0.0, 150.0, 48.0), l2, 132.0, 5.6)
        kamera_setzen(c, ENDE - 1, (0.0, 30.0, 45.5), (0.0, 150.0, 47.5), l2, 120.0, 5.6)
        glatte_kurven(c)
    for o in list(sc.objects):
        if o.type == 'CAMERA' and o.name.startswith('kamera_'):
            kamera_atmen(o, 0.08 if '_s3' in o.name else (0.05 if '_s4' in o.name else 0.12), seed=sum(map(ord, o.name)) % 50)
    sc.camera = bpy.data.objects['kamera_quer']
    m = sc.timeline_markers.new('S4', frame=S4); m.camera = bpy.data.objects['kamera_quer_s4']
    m = sc.timeline_markers.new('S1', frame=S1); m.camera = bpy.data.objects['kamera_quer']
    if handy_info:
        m = sc.timeline_markers.new('S3', frame=S3); m.camera = bpy.data.objects['kamera_quer_s3']
    if laptop_info:
        m = sc.timeline_markers.new('S3B', frame=S3B); m.camera = bpy.data.objects['kamera_quer_s3b']
    render_einstellen('quer')
    belichtung_setzen()
    logo_licht_setzen(LOGO_LICHT)
    os.makedirs(FILM, exist_ok=True)
    bpy.ops.wm.save_as_mainfile(filepath=BLEND)
    print('BAU FERTIG', len(bpy.data.objects), 'Objekte', flush=True)


def rendern(fmt, bilder, prozent=100, samples=None, ordner='probe'):
    sc = bpy.context.scene
    render_einstellen(fmt, prozent, samples)
    ziel = os.path.join(FILM, 'render', fmt, ordner); os.makedirs(ziel, exist_ok=True)
    for f in bilder:
        sc.frame_set(f)
        sc.render.filepath = os.path.join(ziel, 'bild-%04d.png' % f)
        if ordner not in ('probe',) and os.path.exists(sc.render.filepath):
            continue
        bpy.ops.render.render(write_still=True)
        print('BILD', fmt, f, flush=True)


def sicht(schritt=6):
    """Verdeckungsprüfung (28.09.2026, nach dem S3B-Fehler: Kamera sah nur Putz).
    Für jedes Bild im Abstand `schritt`: 5x5 Strahlen aus der aktiven Kamera. Glas und
    Wasser werden durchschossen. Gemeldet wird, wenn von den 9 mittleren Strahlen mindestens
    6 eine undurchsichtige Fläche treffen, die näher liegt als 45 % der Schärfeentfernung
    (Gassenwände am Bildrand sind gewollt, eine Wand in der Bildmitte nicht)."""
    sc = bpy.context.scene; dg = bpy.context.evaluated_depsgraph_get()
    durch = ('glas', 'wasser', 'glass')
    befunde = 0
    for f in range(S1, ENDE, schritt):
        sc.frame_set(f); dg = bpy.context.evaluated_depsgraph_get()
        cam = sc.camera
        for mk in sorted(sc.timeline_markers, key=lambda m: m.frame):
            if mk.frame <= f and mk.camera:
                cam = mk.camera
        cd = cam.data; mw = cam.matrix_world
        fokus = cd.dof.focus_distance if cd.dof.use_dof else 10.0
        rahmen = [mw @ v for v in cd.view_frame(scene=sc)]
        o = mw.translation
        nah = 0; treffer_namen = {}
        for i in range(5):
            for j in range(5):
                ui, vj = (i + 0.5) / 5, (j + 0.5) / 5
                # view_frame: 0 oben rechts, 1 unten rechts, 2 unten links, 3 oben links
                oben = rahmen[3].lerp(rahmen[0], ui); unten = rahmen[2].lerp(rahmen[1], ui)
                ziel = oben.lerp(unten, vj)
                d = (ziel - o).normalized(); start = o.copy(); weg = 0.0
                for _ in range(8):
                    ok, loc, nrm, idx, obj, _m = sc.ray_cast(dg, start, d, distance=max(0.01, fokus * 3 - weg))
                    if not ok:
                        break
                    mat = ''
                    try:
                        me = obj.evaluated_get(dg).data
                        mat = me.materials[me.polygons[idx].material_index].name.lower() if me.materials else ''
                    except Exception:
                        pass
                    weg += (loc - start).length
                    if any(k in mat for k in durch):
                        start = loc + d * 0.002; continue
                    if weg < 0.45 * fokus and 1 <= i <= 3 and 1 <= j <= 3:
                        nah += 1; k = obj.name + '/' + mat
                        treffer_namen[k] = treffer_namen.get(k, 0) + 1
                    break
        if nah >= 6:
            befunde += 1
            haupt = max(treffer_namen, key=treffer_namen.get)
            print('VERDECKT Bild %4d  Kamera %-18s %d/9 Mitte-Strahlen nah, v. a. %s (Fokus %.1f m)' % (f, cam.name, nah, haupt, fokus), flush=True)
    print('SICHT FERTIG', befunde, 'Befunde', flush=True)


if __name__ == '__main__':
    if MODUS == 'sicht':
        sicht(int(argv[1]) if len(argv) > 1 else 6)
    elif MODUS == 'bau':
        bau()
    elif MODUS == 'probe':
        fmt = argv[1] if len(argv) > 1 else 'quer'
        bilder = [int(x) for x in (argv[2] if len(argv) > 2 else '60,200,300').split(',')]
        rendern(fmt, bilder, float(argv[3]) if len(argv) > 3 else 50, int(argv[4]) if len(argv) > 4 else 128)
    elif MODUS == 'voll':
        fmt = argv[1]; von, bis = int(argv[2]), int(argv[3])
        rendern(fmt, range(von, bis + 1), 100, None, 'voll')
    elif MODUS == 'zoom_s3b':
        # Nachbesserung ohne Neubau: Brennweite am Ende von S3B auf lb * S3B_ZOOM setzen
        for name, lb in (('kamera_quer_s3b', 50.0), ('kamera_hoch_s3b', 38.0)):
            o = bpy.data.objects.get(name)
            if o is None:
                continue
            act = o.data.animation_data.action
            try:
                kurven = list(act.fcurves)
            except AttributeError:          # Blender 5: Aktionen mit Ebenen
                kurven = [fc for lay in act.layers for st in lay.strips for cb in st.channelbags for fc in cb.fcurves]
            for c in kurven:
                if c.data_path != 'lens':
                    continue
                for k in c.keyframe_points:
                    if abs(k.co.x - (S4 - 1)) < 0.5:
                        k.co.y = lb * S3B_ZOOM
                c.update()
            bpy.context.scene.frame_set(S4 - 1)
            print('ZOOM', name, round(o.data.lens, 1), flush=True)
        bpy.ops.wm.save_mainfile()
    elif MODUS == 'nachbessern':
        # Belichtung je Einstellung + Logolicht ohne Neubau (Faktor optional als Argument)
        if len(argv) > 1:
            LOGO_LICHT = float(argv[1])
        belichtung_setzen(); logo_licht_setzen(LOGO_LICHT); reflexkarte()
        rk = bpy.data.materials.get('Reflexkarte')
        if rk:
            next(n for n in rk.node_tree.nodes if n.type == 'EMISSION').inputs['Strength'].default_value = REFLEX_STAERKE
        g = bpy.data.materials.get('Gold')
        if g:
            g.node_tree.nodes['Principled BSDF'].inputs['Base Color'].default_value = (*GOLD, 1)
        for o in bpy.context.scene.objects:          # Schaufensterlicht auf FENSTER_W
            if o.type == 'LIGHT' and o.name.startswith('fenster_') and o.data.animation_data:
                act = o.data.animation_data.action
                try:
                    kurven = list(act.fcurves)
                except AttributeError:
                    kurven = [fc for lay in act.layers for st in lay.strips for cb in st.channelbags for fc in cb.fcurves]
                for c in kurven:
                    for k in c.keyframe_points:
                        if c.data_path == 'energy' and k.co.y > 0:
                            k.co.y = FENSTER_W
                    c.update()
        for f in (S1, S3, S3B, S4):
            bpy.context.scene.frame_set(f); print('EV', f, round(bpy.context.scene.view_settings.exposure, 2), flush=True)
        bpy.ops.wm.save_mainfile()
    elif MODUS == 'logo_test':
        # Gold-Wirkung messen: Logolichter skalieren, Bild rendern, Sättigung ausgeben
        sc = bpy.context.scene
        bild = int(argv[1]); faktoren = [float(x) for x in argv[2].split(',')]
        ziel = os.path.join(FILM, 'render', 'quer', 'probe-logo'); os.makedirs(ziel, exist_ok=True)
        lichter = [o for o in sc.objects if o.type == 'LIGHT' and o.name.startswith('logo_')]
        basis = {}
        for o in lichter:
            act = o.data.animation_data.action
            try:
                kurven = list(act.fcurves)
            except AttributeError:
                kurven = [fc for lay in act.layers for st in lay.strips for cb in st.channelbags for fc in cb.fcurves]
            basis[o.name] = [(k, k.co.y) for c in kurven if c.data_path == 'energy' for k in c.keyframe_points]
        render_einstellen('quer', 50, 128)
        for fa in faktoren:
            for o in lichter:
                for k, y in basis[o.name]:
                    k.co.y = y * fa
                o.data.animation_data.action  # noqa
            sc.frame_set(bild)
            sc.render.filepath = os.path.join(ziel, 'bild-%04d-%.2f.png' % (bild, fa))
            bpy.ops.render.render(write_still=True)
            print('LOGO', bild, fa, [round(o.data.energy) for o in lichter], flush=True)
    elif MODUS == 'teil':
        # Bildbereich in einen eigenen Ordner (z. B. korrigierte Einstellung neu, ohne alte Bilder zu löschen)
        fmt = argv[1]; von, bis = int(argv[2]), int(argv[3]); ordner = argv[4]
        rendern(fmt, range(von, bis + 1), float(argv[5]) if len(argv) > 5 else 50, int(argv[6]) if len(argv) > 6 else 48, ordner)
    elif MODUS == 'vorschau':
        # ganzer Film in halber Größe, wenig Samples: Tempo, Schnitt, Licht prüfen
        fmt = argv[1]; schritt = int(argv[2]) if len(argv) > 2 else 1
        rendern(fmt, range(S1, ENDE, schritt), 50, 48, 'vorschau')
