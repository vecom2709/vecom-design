"""Werbespot aus mehreren Einstellungen (01.10.2026, Uwe: „Die Bilder und
Videos aus Blender sollen nicht nur die Standard-Bilder und -Videos sein,
sondern hochprofessionelle, fotorealistische 3D-Werbevideos — individuell,
je Branche und auch für Vecom Design.“)

Bisher war ein Blender-Film eine einzige ruhige Fahrt (8 s, ±17° um das
Motiv). Ein Werbespot erzählt dagegen in Einstellungen, wie ein Kameramann
am Set dreht:

  auftakt  4,0 s  Weit, langsame Fahrt heran — der Ort stellt sich vor
  detail   3,5 s  Nah mit langer Brennweite, Schärfe zieht aufs Motiv
  gleiten  4,0 s  Tief und seitlich, Vordergrund und Motiv laufen gegen-
                  einander (Parallaxe) — das, was ein Foto nie zeigt
  bogen    4,5 s  Bogen ums Motiv in Augenhöhe
  finale   4,0 s  Kran: hoch und zurück, endet im Heldenbild, auf dem der
                  Abspann steht
  held     2,6 s  (nur im Vecom-Spot) eine Einstellung je Branche

Die Kamera wird je Bild als Schlüssel gesetzt (nicht nur versetzt): nur so
rechnet Cycles echte Bewegungsunschärfe (180°-Verschluss wie beim Film).
Zwischen zwei Einstellungen liegen ungerechnete Bilder in der Zeitleiste
mit fortgesetzter Bewegung — sonst verschmierte die Unschärfe am Schnitt
die eine Einstellung in die nächste.

Jede Einstellung wird vorher geprüft (Strahl von der Kamera aufs Motiv und
zurück an fünf Zeitpunkten): Steht etwas im Weg oder steckt die Kamera in
einem Stuhl, kommt die Gegenseite, dann höher und weiter, zuletzt der
sichere Bogen in Heldenhöhe. Was gewählt wurde, steht im Bericht.
"""
import math

from mathutils import Vector

FPS = 24
LUECKE = 6                      # ungerechnete Bilder zwischen zwei Einstellungen
PLAN = [('auftakt', 4.0), ('detail', 3.5), ('gleiten', 4.0), ('bogen', 4.5), ('finale', 4.0)]
ABSPANN_S = 2.8                 # so lange steht der Abspann auf dem Ende der letzten Einstellung


def _weich(t):
    t = max(0.0, min(1.0, t))
    return t * t * (3 - 2 * t)


def _sanft(t):                  # stärker an- und auslaufend (Kranfahrt, Auftakt)
    t = max(0.0, min(1.0, t))
    return t * t * t * (t * (t * 6 - 15) + 10)


def _l(a, b, t):
    return a + (b - a) * t


def basis(cam, cam_d, ziel, groesse_l, basis_z, boden_z):
    """Alles, was die Einstellungen aus dem eingemessenen Heldenbild brauchen."""
    d0 = cam.location - ziel
    return dict(ziel=ziel.copy(), w0=math.atan2(-d0.x, -d0.y), rad0=math.hypot(d0.x, d0.y), h0=d0.z,
                lens0=cam_d.lens, blende0=cam_d.dof.aperture_fstop, L=groesse_l, basis_z=basis_z, boden_z=boden_z)


def _ort(z, w, rad, h):
    return Vector((z.x - math.sin(w) * rad, z.y - math.cos(w) * rad, z.z + h))


def kamera(name, t, B, s, v=0):
    """Kamera der Einstellung `name` zur Zeit t (0..1).
    s = Seite (+1/-1), v = Ausweichstufe (0 Plan, 1 höher/weiter, 2 Heldenhöhe).
    Liefert (Ort, Blickpunkt, Brennweite mm, Schärfe in m oder None = auf den Blickpunkt, Blende)."""
    z, w0, r0, h0, f0, k0, L = B['ziel'], B['w0'], B['rad0'], B['h0'], B['lens0'], B['blende0'], B['L']
    hf, rf = (1.0, 1.0) if v == 0 else ((1.25, 1.15) if v == 1 else (1.0, 1.0))
    if name == 'auftakt':
        e = _sanft(t)
        p = _ort(z, w0 + s * math.radians(_l(11.0, 4.0, e)), r0 * _l(1.34, 1.08, e) * rf, h0 * 1.06 * hf)
        return p, z, f0 * 0.82, None, k0
    if name == 'detail' and v < 2:
        e = _weich(t)
        p = _ort(z, w0 - s * math.radians(26.0 + _l(-3.0, 3.0, e)), r0 * 0.5 * rf, h0 * 0.74 * hf)
        dist = (z - p).length
        # Schärfe zieht in den ersten 45 % vom Vordergrund aufs Motiv (Rack Focus)
        return p, z, min(135.0, f0 * 1.4), dist * _l(0.7, 1.0, _weich(t / 0.45)), max(1.8, k0 * 0.6)
    if name == 'gleiten' and v < 2:
        e = _weich(t)
        wc = w0 + s * math.radians(18.0)
        # tief: knapp über dem Schärfepunkt (ein Viertel der Heldenhöhe darüber). Probe 01.10.2026
        # gastro: die halbe Motivhöhe über dem Boden (0,39 m) sah unter den Tisch statt über die Tafel.
        hz = (h0 * 0.25 if h0 > 0.05 else 0.02) if v == 0 else h0 * 0.6
        quer = Vector((math.cos(wc), -math.sin(wc), 0.0))
        versatz = s * _l(-0.22, 0.22, e) * L
        p = _ort(z, wc, r0 * 0.9 * rf, hz) + quer * versatz
        return p, z + quer * versatz * 0.35, f0 * 0.9, None, k0
    if name == 'finale':
        e = _sanft(t)
        hh = h0 if h0 > 0.05 else 0.1 * L
        p = _ort(z, w0 + s * math.radians(_l(6.0, 0.0, e)), r0 * _l(0.92, 1.12, e) * rf, hh * _l(0.78, 1.3, e) * hf)
        return p, z, f0, None, k0
    if name == 'held':
        e = _weich(t)
        # Probe 01.10.2026: mit 1,12 -> 0,97 stand die Kochinsel im Hochformat klein im Raum
        p = _ort(z, w0 + s * math.radians(_l(9.0, -3.0, e)), r0 * _l(1.0, 0.84, e) * rf, h0 * hf)
        return p, z, f0, None, k0
    # bogen (auch der sichere Rückfall jeder anderen Einstellung)
    e = _weich(t)
    p = _ort(z, w0 - s * math.radians(_l(32.0, -8.0, e)), r0 * _l(1.0, 0.9, e) * rf, h0 * _l(1.0, 0.93, e) * hf)
    return p, z, f0, None, k0


def _frei(sc, deps, p, blick, ignorieren, boden_z):
    """Sieht die Kamera das Motiv, und steckt sie nirgends drin?"""
    if p.z < boden_z + 0.04:
        return False
    d = blick - p
    dist = d.length
    if dist < 0.03:
        return False
    for start, richtung in ((p, d.normalized()), (blick, -d.normalized())):
        treffer, ort, _n, _i, obj, _m = sc.ray_cast(deps, start, richtung, distance=dist)
        if not treffer or obj is None or obj.name in ignorieren:
            continue
        weg = (ort - start).length
        if start is p and weg < dist * 0.55:
            return False            # etwas steht zwischen Kamera und Motiv
        if start is blick and dist - weg < 0.08:
            return False            # die Kamera steckt in einer Fläche
    return True


def waehlen(sc, deps, B, namen, seite, ignorieren):
    """Je Einstellung Seite und Ausweichstufe, die frei sind. Liefert [(name, s, v, ausweg)]."""
    wahl = []
    for name in namen:
        gefunden = None
        for v in (0, 1):
            for s in (seite, -seite):
                if all(_frei(sc, deps, *kamera(name, t, B, s, v)[:2], ignorieren, B['boden_z']) for t in (0.0, 0.25, 0.5, 0.75, 1.0)):
                    gefunden = (name, s, v, '' if (v, s) == (0, seite) else ('Gegenseite' if v == 0 else 'höher und weiter'))
                    break
            if gefunden:
                break
        wahl.append(gefunden or (name, seite, 2, 'sicherer Bogen'))
        seite = -seite              # Einstellungen wechseln die Seite: lebendiger Schnitt
    return wahl


def zeitleiste(cam, cam_d, B, wahl, dauer):
    """Setzt die Kameraschlüssel aller Einstellungen. Liefert [(name, erstes Bild, Anzahl)]."""
    import bpy
    bpy.context.preferences.edit.keyframe_new_interpolation_type = 'LINEAR'
    cam_d.clip_start = 0.01
    stuecke, f0 = [], 1
    vorher = None
    for name, s, v, _aus in wahl:
        n = max(24, int(round(FPS * dauer.get(name, 4.0))))
        zust = []
        for i in range(n):
            p, blick, lens, fokus, blende = kamera(name, i / (n - 1), B, s, v)
            zust.append((p, blick, lens, fokus if fokus is not None else (blick - p).length, blende))
        # je ein fortgesetztes Bild davor und danach (für die Bewegungsunschärfe am Schnitt)
        def weiter(a, b):
            return tuple((2 * x - y) if not isinstance(x, Vector) else (x * 2 - y) for x, y in zip(a, b))
        reihe = [(f0 - 1, weiter(zust[0], zust[1]))] + [(f0 + i, z) for i, z in enumerate(zust)] + [(f0 + n, weiter(zust[-1], zust[-2]))]
        for f, (p, blick, lens, fokus, blende) in reihe:
            cam.location = p
            q = (blick - p).to_track_quat('-Z', 'Y')
            cam.rotation_euler = q.to_euler('XYZ', vorher) if vorher is not None else q.to_euler('XYZ')
            vorher = cam.rotation_euler.copy()
            cam_d.lens = lens
            cam_d.dof.focus_distance = max(0.02, fokus)
            cam_d.dof.aperture_fstop = blende
            cam.keyframe_insert('location', frame=f)
            cam.keyframe_insert('rotation_euler', frame=f)
            cam_d.keyframe_insert('lens', frame=f)
            cam_d.dof.keyframe_insert('focus_distance', frame=f)
            cam_d.dof.keyframe_insert('aperture_fstop', frame=f)
        stuecke.append((name, f0, n))
        f0 += n + LUECKE
    return stuecke
