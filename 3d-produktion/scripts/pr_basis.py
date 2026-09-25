"""pr_basis.py -- gemeinsame Bausteine fuer die Produktdemos (Blender 5).

Jede Demo (pr_wein.py, pr_lkw.py, ...) baut ihre Szene aus diesen Teilen:
Drehkoerper mit Umfangs-UV, Etikettenmantel, gefaste Kaesten mit UV in
Metern, Scharnier-Empties (dieselbe Form wie die Autotueren, damit
produkt-echtzeit.js sie ohne Sonderweg dreht) und ein Export mit
KHR_materials_variants samt Traegerobjekt.

Einheiten: Meter. Blender Z oben; glTF Y oben (Umrechnung beim Export).
"""
import bpy, bmesh, json, math, os, struct
import numpy as np
from mathutils import Vector, Matrix

P = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\branchen'
TEX = os.path.join(P, 'quelle', 'tex')
_bilder = {}


def log(*a):
    print('[produkt]', *a, flush=True)


def leeren():
    bpy.ops.wm.read_factory_settings(use_empty=True)
    sc = bpy.context.scene
    sc.unit_settings.system = 'METRIC'; sc.unit_settings.scale_length = 1.0
    return sc


def bild(name, farbe=False):
    if name not in _bilder:
        im = bpy.data.images.load(os.path.join(TEX, name + '.png'))
        im.colorspace_settings.name = 'sRGB' if farbe else 'Non-Color'
        _bilder[name] = im
    return _bilder[name]


def stoff(name, farbe=(0.8, 0.8, 0.8), rau=0.5, metall=0.0, trans=0.0, ior=1.5, coat=0.0, coat_rau=0.03,
          spec=0.5, sheen=0.0, emiss=None, farbkarte=None, mr=None, normal=None, normal_staerke=1.0, kachel=None,
          alpha=None, disp=0.0):
    """Principled BSDF. Karten: farbkarte (sRGB), mr (glTF: G Rauheit, B Metall),
    normal (OpenGL). kachel = Meter je Kachel (UV in Metern) -> Mapping-Knoten,
    der als KHR_texture_transform exportiert wird."""
    m = bpy.data.materials.new(name); m.use_nodes = True
    nt = m.node_tree; b = nt.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = (*farbe, 1)
    b.inputs['Roughness'].default_value = rau
    b.inputs['Metallic'].default_value = metall
    b.inputs['Transmission Weight'].default_value = trans
    b.inputs['IOR'].default_value = ior
    b.inputs['Coat Weight'].default_value = coat
    b.inputs['Coat Roughness'].default_value = coat_rau
    b.inputs['Specular IOR Level'].default_value = spec
    if disp and 'Dispersion' in b.inputs:            # Kehrwert der Abbe-Zahl (ab Blender 4.2)
        b.inputs['Dispersion'].default_value = disp
    if sheen:
        b.inputs['Sheen Weight'].default_value = sheen
    if emiss:
        b.inputs['Emission Color'].default_value = (*emiss[0], 1); b.inputs['Emission Strength'].default_value = emiss[1]
    vek = None
    if kachel and (farbkarte or mr or normal):
        koord = nt.nodes.new('ShaderNodeTexCoord'); abb = nt.nodes.new('ShaderNodeMapping')
        abb.inputs['Scale'].default_value = (1 / kachel, 1 / kachel, 1)
        nt.links.new(koord.outputs['UV'], abb.inputs['Vector']); vek = abb.outputs['Vector']

    def tex(n, f=False):
        t = nt.nodes.new('ShaderNodeTexImage'); t.image = bild(n, f)
        if vek is not None:
            nt.links.new(vek, t.inputs['Vector'])
        return t
    if farbkarte:
        t = tex(farbkarte, True)
        if tuple(farbe) != (1, 1, 1) and farbe != (1.0, 1.0, 1.0) and farbkarte.startswith('innen-') and False:
            pass
        nt.links.new(t.outputs['Color'], b.inputs['Base Color'])
        if alpha:
            nt.links.new(t.outputs['Alpha'], b.inputs['Alpha'])
    if mr:
        t = tex(mr); sep = nt.nodes.new('ShaderNodeSeparateColor')
        nt.links.new(t.outputs['Color'], sep.inputs['Color'])
        nt.links.new(sep.outputs['Green'], b.inputs['Roughness']); nt.links.new(sep.outputs['Blue'], b.inputs['Metallic'])
    if normal:
        t = tex(normal); nm = nt.nodes.new('ShaderNodeNormalMap'); nm.inputs['Strength'].default_value = normal_staerke
        nt.links.new(t.outputs['Color'], nm.inputs['Color']); nt.links.new(nm.outputs['Normal'], b.inputs['Normal'])
    return m


def netz(name, V, F, stoffe, fidx=None, uv=None, glatt=True, kante_winkel=None):
    """Objekt aus Punkten/Flaechen (Listen), UV je Ecke (Liste je Flaeche)."""
    me = bpy.data.meshes.new(name)
    me.from_pydata([tuple(v) for v in V], [], [tuple(f) for f in F])
    for s in stoffe:
        me.materials.append(s)
    if fidx is not None:
        me.polygons.foreach_set('material_index', np.asarray(fidx, np.int32))
    if uv is not None:
        lay = me.uv_layers.new(name='UVMap')
        flach = [c for f in uv for c in f]
        lay.data.foreach_set('uv', np.asarray(flach, np.float32).ravel())
    me.polygons.foreach_set('use_smooth', np.full(len(F), glatt))
    me.update()
    o = bpy.data.objects.new(name, me); bpy.context.scene.collection.objects.link(o)
    if kante_winkel is not None:
        # Auto-Glaettung mit Kantenwinkel (Blender 4.1+: Modifier-freie Variante)
        try:
            with bpy.context.temp_override(active_object=o, selected_objects=[o], selected_editable_objects=[o]):
                bpy.ops.object.shade_auto_smooth(angle=math.radians(kante_winkel))
        except Exception:
            pass
    return o


def drehkoerper(name, profil, n, stoffe, fidx_profil=None, glatt=True, kante_winkel=None, u_skala=1.0):
    """Profil [(r, z), ...] um die Z-Achse. r=0 an den Enden schliesst mit
    einem Faecher. UV: u = Umfang (0..u_skala), v = Bogenlaenge/Gesamtlaenge.
    fidx_profil: Materialindex je Profilabschnitt (len(profil)-1)."""
    pr = np.asarray(profil, float)
    s = np.r_[0, np.cumsum(np.hypot(np.diff(pr[:, 0]), np.diff(pr[:, 1])))]
    v = s / s[-1]
    V, F, UV, FI = [], [], [], []
    idx = {}
    for i, (r, z) in enumerate(pr):
        if r < 1e-9:
            idx[(i, 0)] = len(V); V.append((0.0, 0.0, z))
            continue
        for j in range(n):
            a = 2 * math.pi * j / n
            idx[(i, j)] = len(V); V.append((r * math.cos(a), r * math.sin(a), z))
    for i in range(len(pr) - 1):
        m = 0 if fidx_profil is None else fidx_profil[i]
        for j in range(n):
            j1 = (j + 1) % n
            u0, u1 = j / n * u_skala, (j + 1) / n * u_skala
            a0 = idx.get((i, j)) if pr[i, 0] > 1e-9 else idx[(i, 0)]
            b0 = idx.get((i, j1)) if pr[i, 0] > 1e-9 else idx[(i, 0)]
            a1 = idx.get((i + 1, j)) if pr[i + 1, 0] > 1e-9 else idx[(i + 1, 0)]
            b1 = idx.get((i + 1, j1)) if pr[i + 1, 0] > 1e-9 else idx[(i + 1, 0)]
            if pr[i, 0] <= 1e-9:
                F.append((a0, a1, b1)); UV.append([((u0 + u1) / 2, v[i]), (u0, v[i + 1]), (u1, v[i + 1])])
            elif pr[i + 1, 0] <= 1e-9:
                F.append((a0, a1, b0)); UV.append([(u0, v[i]), ((u0 + u1) / 2, v[i + 1]), (u1, v[i])])
            else:
                F.append((a0, a1, b1, b0)); UV.append([(u0, v[i]), (u0, v[i + 1]), (u1, v[i + 1]), (u1, v[i])])
            FI.append(m)
    o = netz(name, V, F, stoffe, FI, UV, glatt, kante_winkel)
    # Umlaufsinn nach aussen (geschlossene Koerper; offene nach Mehrheit)
    bm = bmesh.new(); bm.from_mesh(o.data)
    bmesh.ops.remove_doubles(bm, verts=bm.verts, dist=1e-7)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    # Harte Kanten ab 30 Grad Knick. Ohne sie mittelt die glatte Schattierung
    # die Normalen ueber die 90-Grad-Kante: eine flache Glasscheibe bekommt
    # dann nach aussen kippende Normalen und bricht wie eine Lupe (Probe
    # Saphirglas 23.09.2026). Harte Kanten gehen als geteilte Normalen ins glTF.
    grenze = math.radians(30.0 if kante_winkel is None else kante_winkel)
    for e in bm.edges:
        if e.is_manifold and e.calc_face_angle(0.0) > grenze:
            e.smooth = False
    bm.to_mesh(o.data); bm.free(); o.data.update()
    return o


def mantel(name, r, z0, z1, a0, a1, n, stoff_, nz=2, auswaerts=True):
    """Zylinderausschnitt (Etikett) mit UV 0..1. Winkel in Rad; die Schrift
    liest sich von aussen richtig (u laeuft gegen den Uhrzeigersinn, von oben)."""
    V, F, UV = [], [], []
    for k in range(nz + 1):
        z = z0 + (z1 - z0) * k / nz
        for j in range(n + 1):
            a = a0 + (a1 - a0) * j / n
            V.append((r * math.cos(a), r * math.sin(a), z))
    for k in range(nz):
        for j in range(n):
            i0 = k * (n + 1) + j; i1 = i0 + n + 1
            f = (i0, i0 + 1, i1 + 1, i1)
            F.append(f if auswaerts else f[::-1])
            uv = [(j / n, k / nz), ((j + 1) / n, k / nz), ((j + 1) / n, (k + 1) / nz), (j / n, (k + 1) / nz)]
            UV.append(uv if auswaerts else uv[::-1])
    return netz(name, V, F, [stoff_], None, UV, True)


def kasten(name, gx, gy, gz, stoffe_, fase=0.002, ort=(0, 0, 0), maserung_hoch=False):
    """Quader (gx, gy, gz), Mitte unten bei ort, mit Fase an allen Kanten
    (Bevel) und UV in Metern (Box-Projektion)."""
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    for v in bm.verts:
        v.co.x *= gx; v.co.y *= gy; v.co.z = v.co.z * gz + gz / 2
    if fase > 0:
        bmesh.ops.bevel(bm, geom=list(bm.edges), offset=fase, segments=2, affect='EDGES', profile=0.5)
    me = bpy.data.meshes.new(name); bm.to_mesh(me); bm.free()
    for s in (stoffe_ if isinstance(stoffe_, (list, tuple)) else [stoffe_]):
        me.materials.append(s)
    uvl = me.uv_layers.new(name='UVMap')
    for poly in me.polygons:
        nx, ny, nz = (abs(c) for c in poly.normal)
        for li in poly.loop_indices:
            co = me.vertices[me.loops[li].vertex_index].co
            if maserung_hoch:          # Holzmaserung laeuft entlang der Hoehe (u = z)
                uvl.data[li].uv = (co.z, co.y) if nx >= max(ny, nz) else ((co.z, co.x) if ny >= nz else (co.y, co.x))
            else:
                uvl.data[li].uv = (co.y, co.z) if nx >= max(ny, nz) else ((co.x, co.z) if ny >= nz else (co.x, co.y))
    for poly in me.polygons:
        poly.use_smooth = True
    o = bpy.data.objects.new(name, me); bpy.context.scene.collection.objects.link(o)
    o.location = ort
    try:
        with bpy.context.temp_override(active_object=o, selected_objects=[o], selected_editable_objects=[o]):
            bpy.ops.object.shade_auto_smooth(angle=math.radians(35))
    except Exception:
        pass
    return o


def angel(name, ort, achse, winkel, kinder):
    """Scharnier-Empty wie bei den Autotueren: extras achse (glTF-Koordinaten),
    winkel (Grad), seite. Die Kinder drehen im Web um diese Achse."""
    e = bpy.data.objects.new(name, None); bpy.context.scene.collection.objects.link(e)
    e.location = ort
    a = Vector(achse).normalized()
    e['achse'] = [a.x, a.z, -a.y]; e['winkel'] = float(winkel); e['seite'] = 1.0
    bpy.context.view_layer.update()
    for o in kinder:
        o.parent = e; o.matrix_parent_inverse = e.matrix_world.inverted()
    return e


def glb_lesen(p):
    b = open(p, 'rb').read()
    n = struct.unpack('<I', b[12:16])[0]
    g = json.loads(b[20:20 + n]); off = 20 + n
    bl = struct.unpack('<I', b[off:off + 4])[0]
    return g, b[off + 8: off + 8 + bl]


def glb_schreiben(p, g, binb):
    js = json.dumps(g, separators=(',', ':')).encode(); js += b' ' * ((4 - len(js) % 4) % 4)
    binb += b'\0' * ((4 - len(binb) % 4) % 4)
    out = struct.pack('<III', 0x46546C67, 2, 12 + 8 + len(js) + 8 + len(binb))
    out += struct.pack('<II', len(js), 0x4E4F534A) + js + struct.pack('<II', len(binb), 0x004E4942) + binb
    open(p, 'wb').write(out)


def exportieren(was, web, varianten, zuordnung, herkunft):
    """varianten: Namen; zuordnung: {Basismaterial: [Material je Variante]}.
    Stoffe, die nur in Varianten vorkommen, haengen an einem Traeger unter
    dem Boden, der danach aus der Szene genommen wird (der Exporter schreibt
    nur Materialien, die an einem Netz haengen)."""
    extra = [m for liste in zuordnung.values() for m in liste[1:]]
    V, F, FI = [], [], []
    for k, _m in enumerate(extra):
        o = len(V); V += [(0, 0, -5 - k * .01), (.001, 0, -5 - k * .01), (0, .001, -5 - k * .01)]; F.append((o, o + 1, o + 2)); FI.append(k)
    if extra:
        tr = netz('varianten_traeger', V, F, extra, FI, [[(0, 0), (1, 0), (0, 1)]] * len(F))
    ziel = os.path.join(P, 'quelle', f"{was}{'-web' if web else ''}.glb")
    bpy.ops.export_scene.gltf(filepath=ziel, export_format='GLB', export_yup=True, export_apply=True,
                              export_texcoords=True, export_normals=True, export_materials='EXPORT', export_extras=True)
    g, binb = glb_lesen(ziel)
    idx = {m.get('name'): i for i, m in enumerate(g['materials'])}
    g.setdefault('extensions', {})['KHR_materials_variants'] = {'variants': [{'name': n} for n in varianten]}
    if 'KHR_materials_variants' not in g.setdefault('extensionsUsed', []):
        g['extensionsUsed'].append('KHR_materials_variants')
    abb = {idx[b.name]: [idx.get(m.name, idx[b.name]) for m in liste] for b, liste in zuordnung.items() if b.name in idx}
    belegt = 0
    for me in g['meshes']:
        for pr in me['primitives']:
            if pr.get('material') in abb:
                pr.setdefault('extensions', {})['KHR_materials_variants'] = {
                    'mappings': [{'material': m, 'variants': [k]} for k, m in enumerate(abb[pr['material']])]}
                belegt += 1
    for i, nd in enumerate(g['nodes']):
        if nd.get('name') == 'varianten_traeger':
            for szn in g.get('scenes', []):
                if i in szn.get('nodes', []):
                    szn['nodes'].remove(i)
    g.setdefault('asset', {})['extras'] = {'herkunft': herkunft}
    glb_schreiben(ziel, g, binb)
    log('GLB', ziel, os.path.getsize(ziel), 'Varianten', varianten, 'Primitive', belegt)
    return ziel


def speichern(was):
    bpy.ops.wm.save_as_mainfile(filepath=os.path.join(P, f'{was}-bau.blend'))


def prisma(name, umriss, z0, z1, stoffe_, fase=0.0, segmente=2, glatt_winkel=35, uv_skala=1.0, uv_versatz=(0.0, 0.0)):
    """Senkrecht (Z) extrudierter Umriss [(x, y), ...] (gegen den Uhrzeigersinn)
    von z0 bis z1, optional mit Fase an allen Kanten. UV = (x, y) in Metern."""
    bm = bmesh.new()
    unten = [bm.verts.new((x, y, z0)) for x, y in umriss]
    f = bm.faces.new(unten)
    bm.normal_update()
    if f.normal.z > 0:
        f.normal_flip()
    ext = bmesh.ops.extrude_face_region(bm, geom=[f])
    oben = [v for v in ext['geom'] if isinstance(v, bmesh.types.BMVert)]
    for v in oben:
        v.co.z = z1
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    if fase > 0:
        bmesh.ops.bevel(bm, geom=list(bm.edges), offset=fase, segments=segmente, affect='EDGES', profile=0.5, clamp_overlap=True)
    me = bpy.data.meshes.new(name); bm.to_mesh(me); bm.free()
    for s in (stoffe_ if isinstance(stoffe_, (list, tuple)) else [stoffe_]):
        me.materials.append(s)
    uvl = me.uv_layers.new(name='UVMap')
    for poly in me.polygons:
        for li in poly.loop_indices:
            co = me.vertices[me.loops[li].vertex_index].co
            uvl.data[li].uv = (co.x * uv_skala + uv_versatz[0], co.y * uv_skala + uv_versatz[1])
    for poly in me.polygons:
        poly.use_smooth = True
    o = bpy.data.objects.new(name, me); bpy.context.scene.collection.objects.link(o)
    try:
        with bpy.context.temp_override(active_object=o, selected_objects=[o], selected_editable_objects=[o]):
            bpy.ops.object.shade_auto_smooth(angle=math.radians(glatt_winkel))
    except Exception:
        pass
    return o


def zahnrad(z, r_kopf, r_fuss, n_pro_zahn=6, loch=None):
    """Umriss eines Zahnrads (Zykloid angenaehert): z Zaehne."""
    pts = []
    for k in range(z):
        for i in range(n_pro_zahn):
            t = (k + i / n_pro_zahn) / z
            a = 2 * math.pi * t
            phase = (i / n_pro_zahn)
            r = r_fuss + (r_kopf - r_fuss) * (0.5 - 0.5 * math.cos(2 * math.pi * min(1.0, phase * 1.6)) if phase < 0.625 else 0.0)
            pts.append((r * math.cos(a), r * math.sin(a)))
    return pts


def kreis(r, n=64, mitte=(0, 0), a0=0.0):
    return [(mitte[0] + r * math.cos(a0 + 2 * math.pi * i / n), mitte[1] + r * math.sin(a0 + 2 * math.pi * i / n)) for i in range(n)]


def flach_schattieren(o):
    for p in o.data.polygons:
        p.use_smooth = False
    return o


# ---------------------------------------------------------------- aus pr_salon.py uebernommen
def rundkasten(name, gx, gy, gz, r, stoffe_, ort=(0, 0, 0), n=10):
    """Quader gx x gy x gz mit Rundung r an allen Kanten (Polster): Wuerfel-
    gitter, jeder Punkt auf den abgerundeten Koerper projiziert. Mitte unten
    bei ort. UV in Metern nach der Hauptrichtung der Normale."""
    hx, hy, hz = gx / 2, gy / 2, gz / 2
    r = min(r, hx, hy, hz)
    V, F = [], []
    idx = {}

    def punkt(p):
        key = tuple(round(c, 6) for c in p)
        if key not in idx:
            idx[key] = len(V); V.append(p)
        return idx[key]
    t = np.linspace(-1, 1, n + 1)
    for ax in range(3):
        for s in (-1, 1):
            for i in range(n):
                for j in range(n):
                    quad = []
                    for (a, b) in ((i, j), (i + 1, j), (i + 1, j + 1), (i, j + 1)):
                        p = [0.0, 0.0, 0.0]
                        p[ax] = s; p[(ax + 1) % 3] = t[a]; p[(ax + 2) % 3] = t[b]
                        quad.append(punkt(tuple(p)))
                    F.append(tuple(quad) if s > 0 else tuple(quad[::-1]))
    halb = np.array([hx, hy, hz]); innen = halb - r
    W = []
    for p in V:
        q = np.array(p) * halb
        k = np.clip(q, -innen, innen)
        d = q - k; ld = np.linalg.norm(d)
        W.append(tuple(k + (d / ld * r if ld > 1e-9 else d)))
    W = [(x, y, z + hz) for (x, y, z) in W]
    uv = []
    for f in F:
        ps = [np.array(W[k]) for k in f]
        nrm = np.cross(ps[1] - ps[0], ps[2] - ps[0]); a = np.abs(nrm)
        if a[2] >= max(a[0], a[1]):
            uv.append([(p[0], p[1]) for p in ps])
        elif a[1] >= a[0]:
            uv.append([(p[0], p[2]) for p in ps])
        else:
            uv.append([(p[1], p[2]) for p in ps])
    o = netz(name, W, F, stoffe_, None, uv, True)
    bm = bmesh.new(); bm.from_mesh(o.data)
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(o.data); bm.free()
    o.location = ort
    return o


def rohr(name, punkte, radius, stoffe_):
    cu = bpy.data.curves.new(name + '_k', 'CURVE'); cu.dimensions = '3D'
    cu.bevel_depth = radius; cu.bevel_resolution = 4; cu.use_fill_caps = True
    sp = cu.splines.new('POLY'); sp.points.add(len(punkte) - 1)
    for i, p in enumerate(punkte):
        sp.points[i].co = (*p, 1.0)
    o = bpy.data.objects.new(name + '_k', cu); bpy.context.scene.collection.objects.link(o)
    bpy.context.view_layer.update()
    me = bpy.data.meshes.new_from_object(o.evaluated_get(bpy.context.evaluated_depsgraph_get()))
    bpy.data.objects.remove(o, do_unlink=True)
    me.name = name; me.materials.clear()
    for s in stoffe_:
        me.materials.append(s)
    for p in me.polygons:
        p.use_smooth = True
    ob = bpy.data.objects.new(name, me); bpy.context.scene.collection.objects.link(ob)
    return ob



