"""film_stadt.py -- Altstadt für „Sichtbar werden" (S4/S5), neu am 28.09.2026.

Uwe zur Vorschau: „viel zu künstlich … die Häuser haben Fenster am Dach".
Die alte Stadt war ein Kasten je Haus mit einem Fensterraster als Textur auf
ALLEN Flächen -- auch auf den Flachdächern. Hier ist jedes Haus aus echten
Teilen gebaut:

- Wände nur senkrecht (Putz aus Poly Haven, UV in Metern)
- Dach: Satteldach mit Coppi oder Dachterrasse mit Brüstung, Fliesen,
  Wassertanks (so sieht jede sizilianische Altstadt von oben aus)
- Fenster als Geometrie: Kalksteinrahmen, Sohlbank, Persiane geschlossen,
  offen mit dunklem Glas oder erleuchtet; manche mit Balkon und Geländer
- Erleuchtete Fenster: warm, in der Farbe leicht verschieden, gehen mit der
  Lichtwelle (Abstand zum Gassenmittelpunkt) an -- wie bisher

Alles landet in wenigen zusammengefassten Netzen je Material (schnell in Cycles).
Aufgerufen aus film_sichtbar.bau() über bauen(W, M); W ist das Hauptmodul.
"""
import bpy, bmesh, math, random
import numpy as np
from mathutils import Vector, Matrix

ZENTRUM = Vector((0.0, 28.0, 0.0))


class Netz:
    """Sammelt Quader in einem bmesh, UV in Metern (Projektion je Flächennormale)."""

    def __init__(self, name, stoff):
        self.name, self.stoff = name, stoff
        self.bm = bmesh.new(); self.uv = self.bm.loops.layers.uv.new('UVMap')

    def quader(self, M4, sx, sy, sz, ohne_oben=False, ohne_unten=True):
        """Quader mit Mittelpunkt im lokalen Ursprung der Matrix M4 (Welt)."""
        h = (sx / 2, sy / 2, sz / 2)
        ecken = [M4 @ Vector((x * h[0], y * h[1], z * h[2])) for z in (-1, 1) for y in (-1, 1) for x in (-1, 1)]
        v = [self.bm.verts.new(p) for p in ecken]
        fl = [(0, 1, 5, 4), (2, 6, 7, 3), (0, 4, 6, 2), (1, 3, 7, 5)]      # -y, +y, -x, +x
        if not ohne_oben:
            fl.append((4, 5, 7, 6))
        if not ohne_unten:
            fl.append((0, 2, 3, 1))
        for f in fl:
            try:
                ff = self.bm.faces.new([v[i] for i in f])
            except ValueError:
                continue
            ff.normal_update()
            n = ff.normal
            for lp in ff.loops:
                co = lp.vert.co
                if abs(n.z) > 0.7:
                    lp[self.uv].uv = (co.x, co.y)
                elif abs(n.x) > abs(n.y):
                    lp[self.uv].uv = (co.y, co.z)
                else:
                    lp[self.uv].uv = (co.x, co.z)

    def zylinder(self, unten, r, h, seg=14):
        """Stehender Zylinder mit Deckel (Wassertank), UV grob abgewickelt."""
        ring_u = [self.bm.verts.new(unten + Vector((r * math.cos(a), r * math.sin(a), 0))) for a in np.linspace(0, 2 * math.pi, seg, endpoint=False)]
        ring_o = [self.bm.verts.new(v.co + Vector((0, 0, h))) for v in ring_u]
        for i in range(seg):
            j = (i + 1) % seg
            f = self.bm.faces.new((ring_u[i], ring_u[j], ring_o[j], ring_o[i]))
            for lp, (uu, vv) in zip(f.loops, ((i, 0), (i + 1, 0), (i + 1, h), (i, h))):
                lp[self.uv].uv = (uu * 2 * math.pi * r / seg, vv)
        f = self.bm.faces.new(ring_o)
        for lp in f.loops:
            lp[self.uv].uv = (lp.vert.co.x, lp.vert.co.y)

    def flaeche(self, pts):
        v = [self.bm.verts.new(p) for p in pts]
        ff = self.bm.faces.new(v); ff.normal_update(); n = ff.normal
        for lp in ff.loops:
            co = lp.vert.co
            lp[self.uv].uv = (co.x, co.y) if abs(n.z) > 0.7 else ((co.y, co.z) if abs(n.x) > abs(n.y) else (co.x, co.z))

    def objekt(self):
        if not self.bm.faces:
            self.bm.free(); return None
        me = bpy.data.meshes.new(self.name); self.bm.to_mesh(me); self.bm.free()
        me.materials.append(self.stoff)
        o = bpy.data.objects.new(self.name, me); bpy.context.scene.collection.objects.link(o)
        return o


def fenster_licht_stoff(W):
    """Erleuchtete Fenster: Vorhang-Verlauf, Farbe 2500–3400 K je Fenster
    (Weißrauschen über die Position), Lichtwelle über den Abstand zur Gasse."""
    m = bpy.data.materials.new('Stadtfenster Licht'); m.use_nodes = True
    nt = m.node_tree; nt.nodes.clear()
    aus = W.knoten(nt, 'ShaderNodeOutputMaterial', 600, 0)
    em = W.knoten(nt, 'ShaderNodeEmission', 400, 0)
    geo = W.knoten(nt, 'ShaderNodeNewGeometry', -1000, 0)
    # je Fenster eine Zufallszahl: Position auf 1,5 m gerastert
    ras = W.knoten(nt, 'ShaderNodeVectorMath', -800, 0, operation='SNAP'); ras.inputs[1].default_value = (1.5, 1.5, 1.5)
    nt.links.new(geo.outputs['Position'], ras.inputs[0])
    wn = W.knoten(nt, 'ShaderNodeTexWhiteNoise', -600, 0); wn.noise_dimensions = '3D'
    nt.links.new(ras.outputs[0], wn.inputs['Vector'])
    kt = W.knoten(nt, 'ShaderNodeMapRange', -400, 100); kt.inputs['To Min'].default_value = 2500; kt.inputs['To Max'].default_value = 3400
    nt.links.new(wn.outputs['Value'], kt.inputs['Value'])
    bb = W.knoten(nt, 'ShaderNodeBlackbody', -200, 100); nt.links.new(kt.outputs['Result'], bb.inputs['Temperature'])
    hl = W.knoten(nt, 'ShaderNodeMapRange', -400, -150); hl.inputs['To Min'].default_value = 1.2; hl.inputs['To Max'].default_value = 4.5
    nt.links.new(wn.outputs['Color'], hl.inputs['Value'])
    # Lichtwelle
    ab = W.knoten(nt, 'ShaderNodeVectorMath', -600, -400, operation='DISTANCE')
    nt.links.new(geo.outputs['Position'], ab.inputs[0]); ab.inputs[1].default_value = tuple(ZENTRUM)
    welle = W.knoten(nt, 'ShaderNodeValue', -600, -600); welle.name = 'welle'; welle.label = 'welle'
    an = W.knoten(nt, 'ShaderNodeMath', -400, -450, operation='LESS_THAN')
    nt.links.new(ab.outputs['Value'], an.inputs[0]); nt.links.new(welle.outputs[0], an.inputs[1])
    st = W.knoten(nt, 'ShaderNodeMath', 200, -200, operation='MULTIPLY')
    nt.links.new(hl.outputs['Result'], st.inputs[0]); nt.links.new(an.outputs[0], st.inputs[1])
    nt.links.new(bb.outputs['Color'], em.inputs['Color']); nt.links.new(st.outputs[0], em.inputs['Strength'])
    nt.links.new(em.outputs[0], aus.inputs['Surface'])
    return m, welle


def bauen(W, M):
    rng = random.Random(7)
    B = W.B
    putze = [W.ph_stoff('Stadtputz %d' % i, o, 2.5, t, h, feucht_unten=0.35, streifen=0.3) for i, (o, t, h) in enumerate((
        ('yellow_plaster', (1.0, 0.9, 0.74), 0.85), ('white_plaster_rough_02', (0.92, 0.86, 0.76), 0.8),
        ('red_plaster_weathered', (1.0, 0.86, 0.8), 0.8), ('worn_plaster_wall', (1.0, 0.84, 0.7), 0.8),
        ('white_plaster_rough_02', (0.78, 0.78, 0.76), 0.75)))]
    wand = [Netz('stadt_wand_%d' % i, p) for i, p in enumerate(putze)]
    kalk = Netz('stadt_kalk', M['kalk'])
    dach = Netz('stadt_dach', M['dach'])
    terr = Netz('stadt_terrasse', M['terrasse'])
    glas_st = W.B.stoff('Stadtglas', (0.012, 0.013, 0.015), rau=0.06)
    glas = Netz('stadt_glas', glas_st)
    pers = [Netz('stadt_persiana_%d' % i, p) for i, p in enumerate(M['persianen'])]
    eisen = Netz('stadt_eisen', M['eisen'])
    tank = [Netz('stadt_tank_%d' % i, t) for i, t in enumerate(M['tank'])]
    licht_st, welle = fenster_licht_stoff(W)
    licht = Netz('stadt_fensterlicht', licht_st)
    lampen = []

    def boden(y):
        return 0.0 if y < 70 else -0.07 * (y - 70)

    n = 0; nf = 0; nl = 0
    for gx in np.arange(-150, 151, 11.0):
        for gy in np.arange(-70, 235, 11.0):
            x = gx + rng.uniform(-3.5, 3.5) + 4.0 * math.sin(gy * 0.05); y = gy + rng.uniform(-3.5, 3.5)
            if abs(x) < 12.5 and -12 < y < W.GASSE_L + 12:
                continue
            if rng.random() < 0.12:
                continue
            bx = rng.uniform(6.5, 10.5); by = rng.uniform(6.5, 10.5)
            etagen = rng.choice((2, 3, 3, 3, 4, 4, 5)); h = 1.0 + 3.6 + (etagen - 1) * 3.2 + rng.uniform(0.2, 0.6)
            z0 = boden(y) - 1.0
            rz = math.radians(rng.uniform(-6, 6))
            Mh = Matrix.Translation((x, y, z0)) @ Matrix.Rotation(rz, 4, 'Z')
            wi = rng.randrange(len(wand))
            # Wände (ohne Deckel, das Dach sitzt oben drauf)
            wand[wi].quader(Mh @ Matrix.Translation((0, 0, h / 2)), bx, by, h, ohne_oben=True)
            # Dach
            if rng.random() < 0.55:
                dh = rng.uniform(1.3, 2.1); ue = 0.3
                pts = lambda sx, sz: Mh @ Vector((sx, 0, sz))
                # Satteldach, First entlang der längeren Seite
                if bx >= by:
                    a = [Mh @ Vector((sx, sy, h + sz)) for sx, sy, sz in ((-bx / 2 - ue, -by / 2 - ue, 0), (bx / 2 + ue, -by / 2 - ue, 0), (bx / 2 + ue, 0, dh), (-bx / 2 - ue, 0, dh))]
                    b = [Mh @ Vector((sx, sy, h + sz)) for sx, sy, sz in ((bx / 2 + ue, by / 2 + ue, 0), (-bx / 2 - ue, by / 2 + ue, 0), (-bx / 2 - ue, 0, dh), (bx / 2 + ue, 0, dh))]
                    g1 = [Mh @ Vector((sx, sy, h + sz)) for sx, sy, sz in ((-bx / 2, -by / 2, 0), (-bx / 2, 0, dh), (-bx / 2, by / 2, 0))]
                    g2 = [Mh @ Vector((sx, sy, h + sz)) for sx, sy, sz in ((bx / 2, by / 2, 0), (bx / 2, 0, dh), (bx / 2, -by / 2, 0))]
                else:
                    a = [Mh @ Vector((sx, sy, h + sz)) for sx, sy, sz in ((-bx / 2 - ue, by / 2 + ue, 0), (-bx / 2 - ue, -by / 2 - ue, 0), (0, -by / 2 - ue, dh), (0, by / 2 + ue, dh))]
                    b = [Mh @ Vector((sx, sy, h + sz)) for sx, sy, sz in ((bx / 2 + ue, -by / 2 - ue, 0), (bx / 2 + ue, by / 2 + ue, 0), (0, by / 2 + ue, dh), (0, -by / 2 - ue, dh))]
                    g1 = [Mh @ Vector((sx, sy, h + sz)) for sx, sy, sz in ((bx / 2, -by / 2, 0), (0, -by / 2, dh), (-bx / 2, -by / 2, 0))]
                    g2 = [Mh @ Vector((sx, sy, h + sz)) for sx, sy, sz in ((-bx / 2, by / 2, 0), (0, by / 2, dh), (bx / 2, by / 2, 0))]
                dach.flaeche(a); dach.flaeche(b)
                wand[wi].flaeche(g1); wand[wi].flaeche(g2)
                # Traufgesims
                for sgn in (-1, 1):
                    if bx >= by:
                        kalk.quader(Mh @ Matrix.Translation((0, sgn * (by / 2 + 0.08), h - 0.1)), bx + 0.3, 0.3, 0.2)
                    else:
                        kalk.quader(Mh @ Matrix.Translation((sgn * (bx / 2 + 0.08), 0, h - 0.1)), 0.3, by + 0.3, 0.2)
            else:
                # Dachterrasse: Boden, Brüstung rundum, Abdeckung, 1–3 Wassertanks
                terr.quader(Mh @ Matrix.Translation((0, 0, h - 0.02)), bx - 0.05, by - 0.05, 0.04, ohne_oben=False)
                for (cx, cy, sx, sy) in ((0, -by / 2 + 0.12, bx, 0.24), (0, by / 2 - 0.12, bx, 0.24), (-bx / 2 + 0.12, 0, 0.24, by - 0.48), (bx / 2 - 0.12, 0, 0.24, by - 0.48)):
                    wand[wi].quader(Mh @ Matrix.Translation((cx, cy, h + 0.45)), sx, sy, 0.9, ohne_oben=True)
                    kalk.quader(Mh @ Matrix.Translation((cx, cy, h + 0.93)), sx + 0.06, sy + 0.06, 0.06, ohne_oben=False)
                for i in range(rng.randint(1, 3)):
                    r = rng.uniform(0.45, 0.6); hh = rng.uniform(0.9, 1.3)
                    p = Mh @ Vector((rng.uniform(-bx / 2 + 1, bx / 2 - 1), rng.uniform(-by / 2 + 1, by / 2 - 1), h))
                    tank[rng.randrange(len(tank))].zylinder(p, r, hh)
            # Fenster auf allen vier Seiten
            for seite, (nx, ny, laenge) in enumerate(((0, -1, bx), (0, 1, bx), (-1, 0, by), (1, 0, by))):
                spalten = max(1, int((laenge - 1.2) / 2.7))
                abst = laenge / spalten
                for e in range(1, etagen):                                         # Erdgeschoss: Türen, von oben kaum zu sehen
                    balkon_etage = rng.random() < 0.3
                    zb = 1.0 + 3.6 + (e - 1) * 3.2                                 # Geschossboden
                    zf = zb + (0.1 if balkon_etage else 0.9)                       # Unterkante Fenster/Balkontür
                    for s in range(spalten):
                        if rng.random() < 0.1:
                            continue
                        t = -laenge / 2 + abst * (s + 0.5)
                        # lokales Fensterkoordinatensystem: Ursprung an der Wand, x entlang, y nach außen
                        if nx == 0:
                            Mf = Mh @ Matrix.Translation((t, ny * by / 2, zf)) @ Matrix.Rotation(0 if ny < 0 else math.pi, 4, 'Z')
                        else:
                            Mf = Mh @ Matrix.Translation((nx * bx / 2, t, zf)) @ Matrix.Rotation(-math.pi / 2 if nx < 0 else math.pi / 2, 4, 'Z')
                        # Mf: lokale -y zeigt aus der Wand heraus
                        fb, fh = 1.0, (2.2 if balkon_etage else 1.6)
                        kalk.quader(Mf @ Matrix.Translation((0, -0.03, fh / 2)), fb + 0.34, 0.06, fh + 0.3)      # Rahmen
                        kalk.quader(Mf @ Matrix.Translation((0, -0.1, -0.06)), fb + 0.45, 0.2, 0.08, ohne_oben=False)  # Sohlbank
                        r = rng.random()
                        zustand = 'zu' if r < 0.5 else ('dunkel' if r < 0.82 else 'licht')
                        if zustand == 'zu':
                            pers[rng.randrange(len(pers))].quader(Mf @ Matrix.Translation((0, -0.07, fh / 2)), fb, 0.03, fh)
                        else:
                            (licht if zustand == 'licht' else glas).quader(Mf @ Matrix.Translation((0, -0.065, fh / 2)), fb - 0.04, 0.02, fh - 0.04)
                            # aufgeklappte Läden neben dem Fenster
                            p = pers[rng.randrange(len(pers))]
                            for sg in (-1, 1):
                                p.quader(Mf @ Matrix.Translation((sg * (fb / 2 + 0.17 + fb / 4), -0.08, fh / 2)), fb / 2, 0.03, fh)
                            nl += zustand == 'licht'
                        if balkon_etage:
                            L = fb + 0.6; T = 0.6
                            kalk.quader(Mf @ Matrix.Translation((0, -T / 2, -0.06)), L, T, 0.12, ohne_oben=False, ohne_unten=False)
                            eisen.quader(Mf @ Matrix.Translation((0, -T + 0.02, 0.95)), L, 0.03, 0.04, ohne_oben=False, ohne_unten=False)
                            for k in range(int(L / 0.14) + 1):
                                eisen.quader(Mf @ Matrix.Translation((-L / 2 + k * L / int(L / 0.14), -T + 0.02, 0.47)), 0.015, 0.015, 0.94)
                        nf += 1
                # Wandlaterne neben der Haustür, Seiten zur Straße hin
                if rng.random() < 0.35:
                    if nx == 0:
                        lampen.append(Mh @ Vector((rng.uniform(-laenge / 3, laenge / 3), ny * (by / 2 + 0.25), 1.0 + 2.9)))
                    else:
                        lampen.append(Mh @ Vector((nx * (bx / 2 + 0.25), rng.uniform(-laenge / 3, laenge / 3), 1.0 + 2.9)))
            n += 1
    objs = [nz.objekt() for nz in wand + [kalk, dach, terr, glas, licht, eisen] + pers + tank]
    for o in objs:
        if o is not None:
            o.name = o.name.replace('stadt_', 'stadthaus_')                   # Export/Prüfung erkennt „stadthaus"
    # Wandlaternen der Stadt: warme Punkte, gehen mit der Welle an
    lm = W.licht_stoff('Stadtlaterne', 2400, 40.0)
    bm = bmesh.new()
    for p in lampen:
        r = bmesh.ops.create_icosphere(bm, subdivisions=1, radius=0.12)
        bmesh.ops.translate(bm, verts=r['verts'], vec=Vector(p))
    me = bpy.data.meshes.new('stadtlaternen'); bm.to_mesh(me); bm.free(); me.materials.append(lm)
    lo = bpy.data.objects.new('stadtlaternen', me); bpy.context.scene.collection.objects.link(lo)
    print('STADT NEU', n, 'Häuser,', nf, 'Fenster,', nl, 'erleuchtet,', len(lampen), 'Laternen', flush=True)
    return [welle]
