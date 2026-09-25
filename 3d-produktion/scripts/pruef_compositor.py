# -*- coding: utf-8 -*-
"""Wie sieht der Compositor in Blender 5.2 wirklich aus?

5.2 hat 'Scene.node_tree' durch 'Scene.compositing_node_group' ersetzt --
der Compositor ist jetzt eine Knotengruppe wie jede andere. Statt zu
raten, welche Knoten es dort noch gibt, wird hier gelistet.
"""
import bpy

ng = bpy.data.node_groups.new('Pruef', 'CompositorNodeTree')
print('[komp] Gruppe: %s' % ng)
print('[komp] interface vorhanden: %s' % hasattr(ng, 'interface'))

geht, geht_nicht = [], []
kandidaten = [
    'NodeGroupInput', 'NodeGroupOutput',
    'CompositorNodeRLayers', 'CompositorNodeViewer', 'CompositorNodeImage',
    'ShaderNodeMath', 'ShaderNodeMapRange', 'ShaderNodeMix',
    'ShaderNodeRGB', 'ShaderNodeValue', 'ShaderNodeClamp',
    'CompositorNodeMath', 'CompositorNodeMapRange', 'CompositorNodeMixRGB',
    'CompositorNodeMix', 'CompositorNodeNormalize', 'CompositorNodeValToRGB',
    'CompositorNodeAlphaOver', 'CompositorNodeZcombine',
]
for k in kandidaten:
    try:
        n = ng.nodes.new(k)
        geht.append(k)
        ng.nodes.remove(n)
    except Exception:
        geht_nicht.append(k)
print('[komp] GEHT: %s' % geht)
print('[komp] GEHT NICHT: %s' % geht_nicht)

if hasattr(ng, 'interface'):
    for it in ng.interface.items_tree:
        print('[komp] Schnittstelle: %s %s %s'
              % (getattr(it, 'in_out', '?'), getattr(it, 'socket_type', '?'),
                 getattr(it, 'name', '?')))

try:
    rl = ng.nodes.new('CompositorNodeRLayers')
    print('[komp] RLayers Ausgaenge: %s' % [o.name for o in rl.outputs])
except Exception as e:
    print('[komp] RLayers: %s' % e)

bpy.data.node_groups.remove(ng)
print('[komp] FERTIG')
