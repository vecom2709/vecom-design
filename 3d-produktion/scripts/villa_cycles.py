# -*- coding: utf-8 -*-
"""
villa_cycles.py — Die Bilder, die wie Fotos aussehen sollen.

WARUM CYCLES UND NICHT EEVEE
EEVEE rechnet kein Licht, das von Waenden zurueckgeworfen wird. Genau daran
scheitert jeder Innenraum: Die Sonne faellt durch die Suedfront, trifft den
Eichenboden -- und in der Wirklichkeit faerbt dieser Boden die halbe Decke
warm ein. In EEVEE bleibt sie kalt und blau, weil das einzige Licht im Raum
der Himmel ist. Genau so sah der erste Innenraum aus.

Cycles verfolgt die Strahlen weiter. Das kostet Minuten statt Sekunden --
auf Uwes Rechner, nicht in der Cloud, so wie es fuer diese Arbeit
festgelegt ist.

WAS HIER UMGEBAUT WIRD
  Glas    In EEVEE ist es Alpha mit Spiegelung, weil echte Brechung dort
          teuer ist und im Browser ohnehin fehlt. In Cycles wird daraus
          echte Transmission -- sonst sieht man durch die Scheibe hindurch
          ein Bild ohne Reflexion, und die Fassade verliert ihren Charakter.
  Wasser  Dasselbe, mit IOR 1.33.
  Leuchten Der Schirm bekommt Emission. Eine Leuchte, die nicht leuchtet,
          ist ein Ding an der Decke.
"""

import bpy
import math


def geraete_waehlen():
    """GPU nehmen, wenn es eine gibt. OPTIX vor CUDA vor HIP vor ONEAPI --
    und wenn nichts davon da ist, rechnet die CPU. Kein Abbruch: ein Bild,
    das laenger dauert, ist besser als keines."""
    p = bpy.context.preferences.addons.get('cycles')
    if p is None:
        return 'CPU', []
    v = p.preferences
    gewaehlt = 'NONE'
    for art in ('OPTIX', 'CUDA', 'HIP', 'ONEAPI', 'METAL'):
        try:
            v.compute_device_type = art
        except TypeError:
            continue
        v.get_devices()
        if any(d.type == art for d in v.devices):
            gewaehlt = art
            break
    namen = []
    if gewaehlt != 'NONE':
        v.get_devices()
        for d in v.devices:
            d.use = (d.type == gewaehlt) or (d.type == 'CPU' and gewaehlt == 'OPTIX')
            if d.use:
                namen.append('%s (%s)' % (d.name, d.type))
        bpy.context.scene.cycles.device = 'GPU'
    else:
        bpy.context.scene.cycles.device = 'CPU'
    return gewaehlt, namen


def _bsdf(mat):
    for n in mat.node_tree.nodes:
        if n.type == 'BSDF_PRINCIPLED':
            return n
    return None


def glas_echt():
    """Alpha-Glas auf echte Transmission umstellen."""
    for name, ior in (('Glas', 1.52), ('Wasser', 1.333)):
        mat = bpy.data.materials.get(name)
        if not mat:
            continue
        b = _bsdf(mat)
        if not b:
            continue
        b.inputs['Alpha'].default_value = 1.0
        if 'Transmission Weight' in b.inputs:
            b.inputs['Transmission Weight'].default_value = 1.0
        b.inputs['IOR'].default_value = ior
        b.inputs['Roughness'].default_value = 0.015 if name == 'Glas' else 0.03
        if name == 'Glas':
            b.inputs['Base Color'].default_value = (0.93, 0.96, 0.97, 1.0)
        mat.blend_method = 'OPAQUE'
        if hasattr(mat, 'use_backface_culling'):
            mat.use_backface_culling = False


def leuchten_an(staerke=14.0):
    """Der Leuchtenschirm strahlt. 14 W/m2 ist wenig gegen die Sonne und
    genau richtig: Man soll sehen, DASS die Leuchte an ist, nicht von ihr
    geblendet werden."""
    mat = bpy.data.materials.get('Leuchtenschirm')
    if not mat:
        return
    nt = mat.node_tree
    aus = None
    for n in list(nt.nodes):
        if n.type == 'OUTPUT_MATERIAL':
            aus = n
        else:
            nt.nodes.remove(n)
    if aus is None:
        aus = nt.nodes.new('ShaderNodeOutputMaterial')
    misch = nt.nodes.new('ShaderNodeMixShader')
    diff = nt.nodes.new('ShaderNodeBsdfDiffuse')
    diff.inputs['Color'].default_value = (0.94, 0.91, 0.85, 1.0)
    em = nt.nodes.new('ShaderNodeEmission')
    em.inputs['Color'].default_value = (1.0, 0.86, 0.66, 1.0)
    em.inputs['Strength'].default_value = staerke
    misch.inputs['Fac'].default_value = 0.82
    nt.links.new(diff.outputs[0], misch.inputs[1])
    nt.links.new(em.outputs[0], misch.inputs[2])
    nt.links.new(misch.outputs[0], aus.inputs[0])


def spiegel_echt():
    mat = bpy.data.materials.get('Spiegel')
    if not mat:
        return
    b = _bsdf(mat)
    if b:
        b.inputs['Metallic'].default_value = 1.0
        b.inputs['Roughness'].default_value = 0.02
        b.inputs['Base Color'].default_value = (0.93, 0.95, 0.96, 1.0)


def einstellen(proben=220, breite=1600, hoehe=900, rauschgrenze=0.012):
    sz = bpy.context.scene
    sz.render.engine = 'CYCLES'
    art, namen = geraete_waehlen()
    c = sz.cycles
    c.samples = proben
    c.use_adaptive_sampling = True
    c.adaptive_threshold = rauschgrenze
    c.use_denoising = True
    c.max_bounces = 10
    c.diffuse_bounces = 5
    c.glossy_bounces = 6
    c.transmission_bounces = 10
    c.transparent_max_bounces = 12
    c.caustics_reflective = False
    c.caustics_refractive = False
    c.blur_glossy = 1.0
    if hasattr(c, 'use_fast_gi'):
        c.use_fast_gi = False
    sz.render.resolution_x = breite
    sz.render.resolution_y = hoehe
    sz.render.resolution_percentage = 100
    sz.render.film_transparent = False
    sz.render.use_persistent_data = True

    glas_echt()
    spiegel_echt()
    leuchten_an()
    return {'geraet': art, 'namen': namen, 'proben': proben}
