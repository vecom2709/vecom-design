import bpy
def wert(i):
    try:
        v = i.default_value
        try: return tuple(round(x, 3) for x in v)
        except TypeError: return round(v, 3)
    except AttributeError: return None
for name in ['M_Marke', 'M_Chrom', 'M_MetallGeb', 'M_MetallPfosten', 'M_Wand', 'M_Decke', 'M_Boden', 'M_LichtBlau', 'M_LichtCyan']:
    m = bpy.data.materials.get(name)
    if not m: continue
    print('==== ', name)
    for n in m.node_tree.nodes:
        extra = ''
        if n.type == 'VALTORGB':
            extra = ' RAMPE ' + str([(round(e.position, 3), tuple(round(c, 3) for c in e.color)) for e in n.color_ramp.elements])
        if n.type == 'TEX_IMAGE' and n.image:
            extra = ' BILD ' + n.image.name
        offen = {i.name: wert(i) for i in n.inputs if not i.is_linked and i.enabled and wert(i) is not None and i.name in ('Base Color','Color','Color1','Color2','Metallic','Roughness','Anisotropic','Specular Tint','Coat Weight','Coat Roughness','Scale','Fac','Strength','Emission Color')}
        print('KN', n.type, n.name, offen, extra)
    for l in m.node_tree.links:
        print('LK', l.from_node.name, l.from_socket.name, '->', l.to_node.name, l.to_socket.name)
w = bpy.context.scene.world
for n in w.node_tree.nodes:
    if n.type == 'TEX_ENVIRONMENT': print('HDRI', n.image.name if n.image else None, n.image.filepath if n.image else '')
    if n.type == 'BACKGROUND': print('BG', wert(n.inputs['Strength']))
for m in bpy.data.materials:
    if m.name.startswith('M_Display'):
        for n in m.node_tree.nodes:
            if n.type == 'TEX_IMAGE' and n.image: print('DISPLAY', m.name, n.image.name, n.image.size[:])
cam = bpy.data.objects['Kamera']
bpy.context.scene.frame_set(122)
print('KAM122', tuple(round(v, 3) for v in cam.matrix_world.translation), cam.data.lens, cam.data.dof.use_dof, cam.data.dof.aperture_fstop)
v = bpy.data.objects['Marke_V']
print('V dims', tuple(round(x, 3) for x in v.dimensions), 'rot', tuple(round(x, 3) for x in v.rotation_euler), 'anim', v.animation_data is not None)
