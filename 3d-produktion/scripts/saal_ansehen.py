import bpy, sys
s = bpy.context.scene
print('DATEI', bpy.data.filepath)
print('SZENE', s.name, 'frames', s.frame_start, s.frame_end, 'aktuell', s.frame_current)
print('RENDER', s.render.engine, s.render.resolution_x, s.render.resolution_y, s.render.resolution_percentage)
c = s.cycles
print('CYCLES samples', c.samples, 'adaptiv', c.adaptive_threshold, 'device', c.device, 'denoise', c.use_denoising)
print('VIEW', s.view_settings.view_transform, s.view_settings.look, s.view_settings.exposure)
print('KAMERA', s.camera.name if s.camera else None)
for o in bpy.data.objects:
    mats = [m.name for m in getattr(o.data, 'materials', [])] if o.data and hasattr(o.data, 'materials') else []
    print('OBJ', o.type, o.name, tuple(round(v, 2) for v in o.location), mats)
for m in bpy.data.materials:
    if not m.use_nodes: continue
    for n in m.node_tree.nodes:
        if n.type == 'BSDF_PRINCIPLED':
            bc = n.inputs['Base Color']
            col = tuple(round(v, 3) for v in bc.default_value) if not bc.is_linked else 'verknuepft'
            print('MAT', m.name, 'base', col, 'metal', round(n.inputs['Metallic'].default_value, 2),
                  'rough', round(n.inputs['Roughness'].default_value, 2) if not n.inputs['Roughness'].is_linked else 'verkn',
                  'emit', tuple(round(v, 2) for v in n.inputs['Emission Color'].default_value), round(n.inputs['Emission Strength'].default_value, 2))
        if n.type == 'EMISSION':
            print('EMIS', m.name, tuple(round(v, 3) for v in n.inputs[0].default_value), n.inputs[1].default_value)
for l in bpy.data.lights:
    print('LICHT', l.name, l.type, tuple(round(v, 3) for v in l.color), l.energy)
w = s.world
if w and w.use_nodes:
    for n in w.node_tree.nodes:
        print('WELT', n.type, n.name)
