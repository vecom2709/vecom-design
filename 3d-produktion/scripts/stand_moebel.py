# Standmoeblierung - und damit der Massstab.
#
# Jedes Stueck hier hat ein Mass, das jeder Mensch im Gefuehl hat:
# Tresen 1,10 m, Barhocker Sitz 0,75 m, Stehtisch 1,10 m, Monitor 55
# Zoll, Feuerloescher 0,60 m, Steckdose 0,30 m ueber Boden. Das ist
# die Antwort auf Pruefung 7 - der Massstab wird nicht behauptet,
# sondern durch bekannte Dinge belegt.
import bpy, bmesh, math

sammlung = bpy.data.collections.get('Standmoebel')
if sammlung:
    for o in list(sammlung.objects):
        bpy.data.objects.remove(o, do_unlink=True)
else:
    sammlung = bpy.data.collections.new('Standmoebel')
    bpy.context.scene.collection.children.link(sammlung)

def material(name, farbe, metall, rauheit, leucht=None, staerke=0.0):
    m = bpy.data.materials.get(name)
    if m is None:
        m = bpy.data.materials.new(name)
    m.use_nodes = True
    b = None
    for k in m.node_tree.nodes:
        if k.type == 'BSDF_PRINCIPLED':
            b = k
    b.inputs['Base Color'].default_value = (farbe[0], farbe[1], farbe[2], 1.0)
    b.inputs['Metallic'].default_value = metall
    b.inputs['Roughness'].default_value = rauheit
    if leucht:
        b.inputs['Emission Color'].default_value = (leucht[0], leucht[1], leucht[2], 1.0)
        b.inputs['Emission Strength'].default_value = staerke
    return m

M_TEPPICH  = material('M_Messeteppich', (0.032, 0.034, 0.040), 0.0, 0.92)
M_PODEST   = material('M_Standpodest',  (0.026, 0.027, 0.031), 0.0, 0.30)
M_KANTE    = material('M_Kantenprofil', (0.310, 0.325, 0.340), 0.92, 0.22)
M_KORPUS   = material('M_Korpus',       (0.043, 0.045, 0.050), 0.0, 0.40)
M_ARBEIT   = material('M_Arbeitsplatte',(0.185, 0.195, 0.210), 0.0, 0.34)
M_STAHLM   = material('M_MoebelStahl',  (0.290, 0.305, 0.320), 0.90, 0.28)
M_SITZ     = material('M_Sitzpolster',  (0.020, 0.048, 0.120), 0.0, 0.78)
M_SCHIRM   = material('M_Bildschirm',   (0.006, 0.006, 0.007), 0.0, 0.18,
                      (0.62, 0.74, 1.00), 1.9)
M_PAPIER   = material('M_Prospekt',     (0.560, 0.575, 0.600), 0.0, 0.62)
M_ROT      = material('M_Feuerloescher',(0.240, 0.018, 0.014), 0.0, 0.38)
M_DOSE     = material('M_Bodendose',    (0.150, 0.155, 0.165), 0.60, 0.45)

_teile = []

def kasten(x0, y0, z0, x1, y1, z1, mat):
    me = bpy.data.meshes.new('t')
    bm = bmesh.new()
    bmesh.ops.create_cube(bm, size=1.0)
    bm.to_mesh(me); bm.free()
    o = bpy.data.objects.new('t', me)
    o.scale = (abs(x1-x0), abs(y1-y0), abs(z1-z0))
    o.location = ((x0+x1)/2.0, (y0+y1)/2.0, (z0+z1)/2.0)
    o.data.materials.append(mat)
    sammlung.objects.link(o)
    _teile.append(o)
    return o

def walze(x, y, z0, z1, r, mat, seiten=20):
    me = bpy.data.meshes.new('w')
    bm = bmesh.new()
    bmesh.ops.create_cone(bm, cap_ends=True, cap_tris=False, segments=seiten,
                          radius1=r, radius2=r, depth=1.0)
    bm.to_mesh(me); bm.free()
    o = bpy.data.objects.new('w', me)
    o.scale = (1, 1, abs(z1-z0))
    o.location = (x, y, (z0+z1)/2.0)
    o.data.materials.append(mat)
    sammlung.objects.link(o)
    _teile.append(o)
    return o

def zusammen(name):
    global _teile
    if not _teile:
        return None
    bpy.ops.object.select_all(action='DESELECT')
    for o in _teile:
        o.select_set(True)
    bpy.context.view_layer.objects.active = _teile[0]
    if len(_teile) > 1:
        bpy.ops.object.join()
    z = bpy.context.view_layer.objects.active
    z.name = name
    z.data.name = name
    _teile = []
    return z

# --- Standpodest: 100 mm hoch, mit Kantenprofil -----------------
# Ein Messestand steht fast immer auf einem Podest. Die Kante ist
# der Punkt, an dem man Hoehe ablesen kann.
SX0, SX1 = -8.0, 8.0
SY0, SY1 = -6.5, 9.5
H = 0.10
kasten(SX0, SY0, 0.0, SX1, SY1, H - 0.012, M_PODEST)
kasten(SX0, SY0, H - 0.012, SX1, SY1, H, M_TEPPICH)
for x0, y0, x1, y1 in ((SX0, SY0, SX0 + 0.03, SY1),
                       (SX1 - 0.03, SY0, SX1, SY1),
                       (SX0, SY0, SX1, SY0 + 0.03),
                       (SX0, SY1 - 0.03, SX1, SY1)):
    kasten(x0, y0, 0.0, x1, y1, H, M_KANTE)
zusammen('Stand_Podest')

# --- Tresen: 3,2 x 0,9 x 1,10 m ---------------------------------
TX, TY = -4.6, 3.2
kasten(TX - 1.6, TY - 0.45, H, TX + 1.6, TY + 0.45, H + 1.06, M_KORPUS)
kasten(TX - 1.65, TY - 0.50, H + 1.06, TX + 1.65, TY + 0.50, H + 1.10, M_ARBEIT)
kasten(TX - 1.58, TY - 0.46, H + 0.02, TX + 1.58, TY - 0.44, H + 0.14, M_KANTE)
# Prospekte liegen auf dem Tresen - 21 x 29,7 cm, DIN A4
for i in range(3):
    kasten(TX - 0.9 + i * 0.34, TY - 0.10, H + 1.10,
           TX - 0.69 + i * 0.34, TY + 0.20, H + 1.113, M_PAPIER)
zusammen('Stand_Tresen')

# --- Barhocker: Sitz 0,75 m -------------------------------------
for hx, hy in ((-2.6, 2.4), (-2.6, 4.0)):
    walze(hx, hy, H, H + 0.72, 0.028, M_STAHLM)
    walze(hx, hy, H, H + 0.03, 0.22, M_STAHLM)
    walze(hx, hy, H + 0.72, H + 0.79, 0.19, M_SITZ)
    walze(hx, hy, H + 0.20, H + 0.23, 0.15, M_STAHLM)
zusammen('Stand_Barhocker')

# --- Stehtisch: 1,10 m ------------------------------------------
walze(-0.2, 5.6, H, H + 1.06, 0.035, M_STAHLM)
walze(-0.2, 5.6, H, H + 0.025, 0.30, M_STAHLM)
walze(-0.2, 5.6, H + 1.06, H + 1.10, 0.35, M_ARBEIT)
zusammen('Stand_Stehtisch')

# --- Monitor 55 Zoll auf Standfuss ------------------------------
# 1,22 x 0,70 m Bildflaeche, Mitte auf 1,50 m - Augenhoehe.
MX, MY = 3.4, 6.8
walze(MX, MY, H, H + 0.03, 0.30, M_STAHLM)
kasten(MX - 0.04, MY - 0.04, H, MX + 0.04, MY + 0.04, H + 1.12, M_STAHLM)
kasten(MX - 0.63, MY - 0.03, H + 1.10, MX + 0.63, MY + 0.03, H + 1.82, M_KORPUS)
kasten(MX - 0.61, MY - 0.035, H + 1.12, MX + 0.61, MY - 0.031, H + 1.80, M_SCHIRM)
zusammen('Stand_Monitor')

# --- Prospektstaender -------------------------------------------
PX, PY = 5.6, 4.2
kasten(PX - 0.02, PY - 0.02, H, PX + 0.02, PY + 0.02, H + 1.32, M_STAHLM)
walze(PX, PY, H, H + 0.02, 0.22, M_STAHLM)
for i in range(4):
    z = H + 0.52 + i * 0.26
    kasten(PX - 0.13, PY - 0.14, z, PX + 0.13, PY + 0.02, z + 0.015, M_STAHLM)
    kasten(PX - 0.105, PY - 0.12, z + 0.015, PX + 0.105, PY - 0.03, z + 0.032, M_PAPIER)
zusammen('Stand_Prospekte')

# --- Feuerloescher und Bodendose --------------------------------
# Beide sind Normteile. Der Loescher ist 0,60 m hoch, die Bodendose
# 0,30 x 0,30 m - zwei weitere Massstaebe, die niemand hinterfragt.
walze(7.4, -5.6, H, H + 0.60, 0.075, M_ROT)
walze(7.4, -5.6, H + 0.60, H + 0.66, 0.030, M_STAHLM)
kasten(7.30, -5.72, H, 7.50, -5.68, H + 0.04, M_STAHLM)
kasten(-1.2, 1.2, H - 0.002, -0.9, 1.5, H + 0.004, M_DOSE)
kasten(2.8, 1.2, H - 0.002, 3.1, 1.5, H + 0.004, M_DOSE)
zusammen('Stand_Normteile')

netze = [o for o in bpy.data.objects if o.type == 'MESH']
print('Objekte gesamt: %d' % len(netze))
ZIEL = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\vecom-showroom_Halle.blend'
bpy.ops.wm.save_as_mainfile(filepath=ZIEL)
print('gespeichert')
