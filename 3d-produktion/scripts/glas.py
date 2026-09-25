# Glas an den Stand bauen.
#
# Ein Scan hilft bei Glas nicht: Glas lebt von Durchsicht, Brechung
# und Schlieren, nicht von einer fotografierten Oberflaeche. Es
# braucht also erst einmal Geometrie, an der es sichtbar wird.
#
# Zwei Bauteile, die jeder Premiumstand hat:
#   - eine Vitrine auf dem Tresen (Exponat unter Glas)
#   - eine Glasbrüstung an der Podestkante zum Gang
import bpy, bmesh, math

sammlung = bpy.data.collections.get('Glas')
if sammlung:
    for o in list(sammlung.objects):
        bpy.data.objects.remove(o, do_unlink=True)
else:
    sammlung = bpy.data.collections.new('Glas')
    bpy.context.scene.collection.children.link(sammlung)

def material(name, farbe, metall, rauheit, leucht=None, staerke=0.0):
    m = bpy.data.materials.get(name) or bpy.data.materials.new(name)
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

# Werte hier sind nur Platzhalter - das echte Glas wird in Unreal
# gebaut. Wichtig ist der Materialname, damit die Zuweisung greift.
M_GLAS  = material('M_Glas',       (0.92, 0.95, 0.97), 0.0, 0.02)
M_BESCH = material('M_Glasfassung',(0.30, 0.315, 0.33), 0.92, 0.26)
M_EXPO  = material('M_Exponat',    (0.045, 0.16, 0.42), 0.85, 0.22)

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

def zusammen(name):
    global _teile
    if not _teile:
        return
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

H = 0.10          # Podesthoehe
TX, TY = -4.6, 3.2   # Tresenlage aus stand_moebel.py
TH = H + 1.10        # Tresenoberkante

# --- Vitrine auf dem Tresen: 0,80 x 0,45 x 0,50 m ---------------
# Echtes Vitrinenglas ist 6 mm stark. Diese Dicke ist wichtig: an
# der Kante entsteht die gruene Tiefe, an der man Glas erkennt.
D = 0.006
vx0, vx1 = TX + 0.30, TX + 1.10
vy0, vy1 = TY - 0.22, TY + 0.23
vz0, vz1 = TH, TH + 0.50
kasten(vx0, vy0, vz0, vx0 + D, vy1, vz1, M_GLAS)
kasten(vx1 - D, vy0, vz0, vx1, vy1, vz1, M_GLAS)
kasten(vx0, vy0, vz0, vx1, vy0 + D, vz1, M_GLAS)
kasten(vx0, vy1 - D, vz0, vx1, vy1, vz1, M_GLAS)
kasten(vx0, vy0, vz1 - D, vx1, vy1, vz1, M_GLAS)
zusammen('Vitrine_Glas')

# Fassung und Sockel der Vitrine
kasten(vx0 - 0.015, vy0 - 0.015, TH - 0.02, vx1 + 0.015, vy1 + 0.015, TH, M_BESCH)
zusammen('Vitrine_Fassung')
# Exponat darunter: ein kleines Markenteil, 22 cm
kasten(TX + 0.62, TY - 0.05, TH, TX + 0.78, TY + 0.06, TH + 0.22, M_EXPO)
zusammen('Vitrine_Exponat')

# --- Glasbruestung an der Podestkante zum Gang ------------------
# 10 mm Sicherheitsglas in Punkthaltern, 1,05 m hoch - Standardmass.
BX0, BX1 = -1.4, 5.2
BY = -6.5 + 0.06
for i in range(4):
    x0 = BX0 + i * ((BX1 - BX0) / 4.0) + 0.04
    x1 = BX0 + (i + 1) * ((BX1 - BX0) / 4.0) - 0.04
    kasten(x0, BY - 0.005, H + 0.10, x1, BY + 0.005, H + 1.05, M_GLAS)
zusammen('Bruestung_Glas')

# Punkthalter: ohne sie schwebt das Glas, und genau daran erkennt
# man gerechnetes Glas sofort.
for i in range(4):
    x0 = BX0 + i * ((BX1 - BX0) / 4.0) + 0.04
    x1 = BX0 + (i + 1) * ((BX1 - BX0) / 4.0) - 0.04
    for x in (x0 + 0.12, x1 - 0.12):
        kasten(x - 0.025, BY - 0.035, H + 0.02, x + 0.025, BY + 0.035, H + 0.16, M_BESCH)
        kasten(x - 0.035, BY - 0.045, H + 0.14, x + 0.035, BY + 0.045, H + 0.20, M_BESCH)
zusammen('Bruestung_Halter')

# Handlauf oben - 42 mm Rohr, wie an jeder echten Bruestung
kasten(BX0, BY - 0.021, H + 1.05, BX1, BY + 0.021, H + 1.092, M_BESCH)
zusammen('Bruestung_Handlauf')

netze = [o for o in bpy.data.objects if o.type == 'MESH']
print('Objekte gesamt: %d' % len(netze))
for n in ('Vitrine_Glas', 'Vitrine_Fassung', 'Vitrine_Exponat',
          'Bruestung_Glas', 'Bruestung_Halter', 'Bruestung_Handlauf'):
    o = bpy.data.objects.get(n)
    print('  %-22s %s' % (n, 'da' if o else 'FEHLT'))

ZIEL = r'C:\Users\manue\Desktop\Vecom Design\3d-produktion\vecom-showroom_Halle.blend'
bpy.ops.wm.save_as_mainfile(filepath=ZIEL)
print('gespeichert')
