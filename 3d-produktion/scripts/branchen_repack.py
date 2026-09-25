"""GLB neu packen: Bilder ersetzen, Material fuer das Lenkrad-Emblem abtrennen.

Warum im GLB und nicht erst in Blender: Die Logos (Khronos, 3D Commerce) sind
fremde Marken. Sie sollen gar nicht erst in die Blender-Datei und damit in
keine Ableitung -- Poster, Web-GLB, Unreal -- geraten koennen.
"""
import json, struct, sys, copy

def lesen(p):
    b = open(p, 'rb').read()
    n = struct.unpack('<I', b[12:16])[0]
    g = json.loads(b[20:20 + n])
    off = 20 + n
    bl = struct.unpack('<I', b[off:off + 4])[0]
    binb = b[off + 8: off + 8 + bl]
    return g, binb

def schreiben(p, g, binb):
    js = json.dumps(g, separators=(',', ':')).encode()
    js += b' ' * ((4 - len(js) % 4) % 4)
    binb += b'\0' * ((4 - len(binb) % 4) % 4)
    total = 12 + 8 + len(js) + 8 + len(binb)
    out = struct.pack('<III', 0x46546C67, 2, total)
    out += struct.pack('<II', len(js), 0x4E4F534A) + js
    out += struct.pack('<II', len(binb), 0x004E4942) + binb
    open(p, 'wb').write(out)

def neu_packen(g, binb, ersatz):
    """ersatz: {image_index: bytes}. Alle bufferViews neu hintereinander."""
    bild_bv = {img['bufferView']: i for i, img in enumerate(g['images']) if 'bufferView' in img}
    neu = bytearray()
    for bi, bv in enumerate(g['bufferViews']):
        if bi in bild_bv and bild_bv[bi] in ersatz:
            daten = ersatz[bild_bv[bi]]
        else:
            o = bv.get('byteOffset', 0)
            daten = binb[o:o + bv['byteLength']]
        while len(neu) % 4:
            neu += b'\0'
        bv['byteOffset'] = len(neu)
        bv['byteLength'] = len(daten)
        neu += daten
    g['buffers'][0]['byteLength'] = len(neu)
    return bytes(neu)

if __name__ == '__main__':
    quelle, ziel = sys.argv[1], sys.argv[2]
    g, binb = lesen(quelle)
    ersatz = {
        3: open('tex/neu-kennzeichen.png', 'rb').read(),
        10: open('tex/neu-reifen.png', 'rb').read(),
        11: open('tex/neu-reifen-normal.png', 'rb').read(),
    }
    for i in ersatz:
        g['images'][i]['mimeType'] = 'image/png'
    # Lenkrad-Emblem: eigenes Material ohne Leuchttextur. Es las mitten aus dem
    # Khronos-Schriftzug (u 0,38-0,58) -- mit dem neuen Kennzeichen stuende dort
    # ein halbes "COM" auf dem Lenkrad.
    hw = next(i for i, m in enumerate(g['materials']) if m.get('name') == 'Hardware')
    m = copy.deepcopy(g['materials'][hw]); m['name'] = 'Emblem'
    m.pop('emissiveTexture', None); m['emissiveFactor'] = [0, 0, 0]
    g['materials'].append(m); em = len(g['materials']) - 1
    for nd in g['nodes']:
        if nd.get('name') == 'InteriorSteeringEmblem':
            for p in g['meshes'][nd['mesh']]['primitives']:
                p['material'] = em
    # Lackflocken: 30-fach gekachelt mit Staerke 0,3 standen sie im ersten
    # vollen Poster als grobes Sandkorn auf der ganzen Karosserie. Echte
    # Effektpigmente sind Hundertstelmillimeter gross -- aus 6 m Abstand
    # erscheinen sie nur als feines Glitzern im Glanzlicht. Viermal feiner,
    # gut ein Drittel so stark.
    for mat in g['materials']:
        if not (mat.get('name') or '').startswith('Paint'):
            continue
        nt = mat.get('normalTexture')
        if not nt:
            continue
        nt['scale'] = round(nt.get('scale', 1.0) * 0.35, 4)
        tt = nt.setdefault('extensions', {}).setdefault('KHR_texture_transform', {})
        tt['scale'] = [v * 4 for v in tt.get('scale', [1, 1])]
    # Perlmutt: Die Vorlage ("Pearly Swirly") legt eine gekachelte Dicken-
    # textur unter den Duennfilm. Im Poster stand das als gruen-rosa Schlieren
    # auf der Flanke -- wie verschmutzt. Echter Perlmuttlack schimmert
    # gleichmaessig und nur unter flachem Winkel: ein Film ohne Textur,
    # schwaecher, mit fester Dicke.
    for mat in g['materials']:
        if mat.get('name') == 'Paint 1 Pearl':
            ir = mat['extensions']['KHR_materials_iridescence']
            ir.pop('iridescenceThicknessTexture', None)
            ir['iridescenceFactor'] = 0.3
            ir['iridescenceThicknessMinimum'] = 380
            ir['iridescenceThicknessMaximum'] = 380
    binb = neu_packen(g, binb, ersatz)
    g.setdefault('asset', {})['extras'] = {
        'herkunft': 'Khronos glTF-Sample-Assets "CarConcept" (CC BY 4.0, Eric Chadwick / Darmstadt Graphics Group), '
                    'Logos entfernt und Kennzeichen ersetzt fuer Vecom Design'}
    schreiben(ziel, g, binb)
    print('geschrieben', ziel, len(binb))
