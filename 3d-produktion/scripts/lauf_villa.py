# -*- coding: utf-8 -*-
"""Headless rechnen. Ein Prozess je Lauf, damit nichts haengenbleibt.

Aufruf:
  blender.exe -b -P lauf_villa.py -- <auftrag> [name=wert ...]

Auftraege:
  sonne    Sonnenstand-Reihe: dieselbe Kamera, mehrere Azimute.
  bilder   Der fertige Satz.
  probe    Ein einzelnes Bild, klein und schnell.

WARUM HEADLESS
Die Villa mit Gelaende und Gras hat die laufende Blender-Oberflaeche
abgestuerzt, als Cycles die Haare uebernahm. Ein eigener Prozess je Lauf
kostet zwanzig Sekunden Startzeit und kann nicht mehr die Sitzung
mitnehmen, in der gearbeitet wird.
"""
import os
import sys
import time
import traceback

import bpy

S = os.path.dirname(os.path.abspath(__file__))
R = os.path.join(os.path.dirname(S), 'render')
if S not in sys.path:
    sys.path.insert(0, S)


def lade(name):
    p = os.path.join(S, name)
    ns = {'__name__': name[:-3] + '_modul', '__file__': p}
    exec(compile(open(p, encoding='utf-8').read(), p, 'exec'), ns)
    return ns


def argumente():
    roh = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
    # PowerShell reicht ein Array je nach Aufrufform mal als mehrere
    # Argumente und mal als EINE Zeichenkette mit Kommas durch. Beides wird
    # hier gleich behandelt, statt sich auf eine Form zu verlassen.
    teile = []
    for stueck in roh:
        teile.extend([x for x in stueck.split(',') if x])
    auftrag = teile[0] if teile else 'probe'
    w = {}
    for stueck in teile[1:]:
        if '=' in stueck:
            k, v = stueck.split('=', 1)
            try:
                w[k] = float(v) if '.' in v else int(v)
            except ValueError:
                w[k] = v
    return auftrag, w


def bauen(halme=90000, gras=1, echt=1, moebel_aussen=1, wolken=0, dunst=1.0):
    """Reihenfolge ist nicht beliebig.

    Erst das Haus, dann das Gelaende (es loescht die alte Rasenplatte und
    baut die Zypressen neu), dann die Aussenmoebel (die Mulchringe suchen
    die Zypressenstaemme, die muessen also schon stehen), zuletzt Himmel
    und Dunst (die haengen an der fertigen Welt, nicht umgekehrt).

    WOLKEN SIND AUS, und das ist eine Messung, keine Meinung.
    Alle sieben Kameras stehen waagerecht (Architekturregel: keine
    stuerzenden Linien) mit 20 bis 35 mm. Der obere Bildrand liegt dadurch
    bei 19 bis 25 Grad ueber dem Horizont -- genau dort, wo eine
    Wolkenschicht in 1800 m Hoehe perspektivisch zu Streifen
    zusammenlaeuft. Drei Durchgaenge (Deckung 0,34 / 0,44 / 0,20,
    Wolkengroesse 2600 / 900 / 1500 m) endeten entweder unsichtbar oder
    als gestreifter Teppich ueber dem ganzen Himmel.
    villa_wolken.wolken() bleibt im Bestand: Sobald eine Kamera nach oben
    schaut -- Drohnenblick, Weitwinkel von unten -- zahlt sie sich aus.
    Fuer DIESE sieben Bilder ist ein klarer Himmel richtig, und ein
    klarer Himmel ist im Mittelmeersommer ohnehin der Normalfall.
    """
    lauf = lade('villa_lauf.py')
    d, kams, szene = lauf['aufbauen'](moebel=True, fasen=True, echt=bool(echt))
    garten = lade('villa_garten.py')
    info = garten['aufwerten'](mit_gras=bool(gras), halme=int(halme))
    print('[lauf] Gelaende %d Dreiecke, %d Baumteile, %d Halme'
          % (info['gelaende_dreiecke'], info['baumteile'], info['halme']))
    if moebel_aussen:
        aussen = lade('villa_aussen.py')
        print('[lauf] Aussen: %s' % aussen['moeblieren']())
    if wolken or dunst:
        wo = lade('villa_wolken.py')
        print('[lauf] Himmel: %s' % wo['alles'](
            deckung=0.20 if wolken else 0.0, dunst=float(dunst)))
    _SZENE[0] = szene
    return d, kams, szene


_SZENE = [None]


def gras_ausgeben(w):
    """Halmpositionen als Textdatei fuer Unreal.

    Ausgegeben werden die WURZELN der Basishalme im Weltkoordinatensystem
    von Blender, dazu die Flaechennormale an dieser Stelle. Ein Basishalm
    hat in Cycles sieben Kinder -- und genau sieben Blaetter hat das
    Bueschel SM_Grasbueschel, das nach Unreal exportiert wurde. Ein
    Basishalm wird drueben also zu einem Bueschel, nicht zu einem Halm.
    Das ist kein Zufall, sondern der Grund fuer die sieben.

    Format je Zeile:  x y z nx ny nz laenge
    Zahlen in Metern, Blender-Achsen. Die Spiegelung nach Unreal
    (y -> -y, mal 100) macht die Gegenseite, damit hier nichts
    Ungespiegeltes durch die Hintertuer wandert.
    """
    import bpy as _b
    import random
    ziel = str(w.get('ziel',
                     r'C:\Users\manue\Desktop\Vecom Design\werkzeug\gras-halme.txt'))
    hoechstens = int(w.get('hoechstens', 200000))

    ob = None
    for kandidat in ('p08_gelaende', 'p08_rasen'):
        ob = _b.data.objects.get(kandidat)
        if ob is not None:
            break
    if ob is None:
        print('[lauf] Kein Gelaendeobjekt gefunden.')
        return

    # WARUM NICHT DIE PARTIKEL SELBST
    # Erster Versuch: ps.particles auslesen. Im Hintergrundlauf sind das
    # 0 Teilchen -- Haarsysteme werden ohne Oberflaeche nicht ausgewertet.
    # Statt das Evaluieren zu erzwingen, wird hier die Quelle angezapft,
    # aus der das Haarsystem selbst schoepft: das Gelaendenetz und die
    # Vertexgruppe 'nah'. Das ergibt dieselbe Verteilung, dieselben
    # Hoehen, und es haengt an nichts, was beim naechsten Blender anders
    # heissen koennte.
    me = ob.data
    mw = ob.matrix_world
    gruppe = ob.vertex_groups.get('nah')
    gi = gruppe.index if gruppe is not None else None
    gewicht = [1.0] * len(me.vertices)
    if gi is not None:
        gewicht = [0.0] * len(me.vertices)
        for v in me.vertices:
            for g in v.groups:
                if g.group == gi:
                    gewicht[v.index] = g.weight
                    break
    me.calc_loop_triangles()
    dreiecke = list(me.loop_triangles)
    print('[lauf] Gelaende: %d Dreiecke, Gruppe nah: %s'
          % (len(dreiecke), 'ja' if gi is not None else 'nein'))

    # Auswahlgewicht je Dreieck = Flaeche mal mittlerer Gruppenwert.
    # Die Flaeche sorgt dafuer, dass grosse Dreiecke nicht benachteiligt
    # werden, der Gruppenwert fuer das Ausduennen nach aussen.
    # Jenseits von rmax waechst nichts mehr. Nicht aus Sparsamkeit:
    # Ein Rasenstueck von 26 cm ist auf 90 m Entfernung ein Sechstel
    # Pixel breit. Dort traegt nur noch die Textur, und Geometrie waere
    # Rechenzeit ohne Bild.
    rmax = float(w.get('rmax', 70.0))
    # WIE STARK DIE VERTEXGRUPPE ZAEHLT
    # Erster Lauf: Gewicht = Flaeche * nah^2. Das klang vernuenftig und
    # war falsch: 'nah' ist voll bis 40 m UM DEN NULLPUNKT, die Kamera
    # 'garten' steht aber auf 42 m. Das Gras war also genau dort am
    # duennsten, wo hingeschaut wird -- im Bild vom 19.09.2026 blieben
    # ein paar vereinzelte Buescheln im Vordergrund uebrig.
    # schaerfe=0 heisst: gleichmaessig bis rmax, und rmax macht die
    # Begrenzung. Das ist ehrlicher, weil die Grenze dann an einer Zahl
    # haengt und nicht an einer Gruppe, die fuer etwas anderes gemacht war.
    schaerfe = float(w.get('schaerfe', 0.0))
    summen = []
    lauf_summe = 0.0
    ecken = []
    draussen = 0
    # Ein harter Rand bei rmax waere im Bild eine Linie quer ueber die
    # Wiese. Ueber die letzten 30 Prozent laeuft die Dichte deshalb mit
    # einer Glaettungskurve aus.
    rweich = rmax * 0.7
    for t in dreiecke:
        a, b, c = (mw @ me.vertices[i].co for i in t.vertices)
        mitte = (a + b + c) / 3.0
        r = (mitte.x * mitte.x + mitte.y * mitte.y) ** 0.5
        if r > rmax:
            draussen += 1
            continue
        rand = 1.0
        if r > rweich:
            s = (r - rweich) / (rmax - rweich)
            rand = 1.0 - (s * s * (3.0 - 2.0 * s))
        flaeche = (b - a).cross(c - a).length * 0.5
        g = sum(gewicht[i] for i in t.vertices) / 3.0
        lauf_summe += flaeche * rand * (g ** schaerfe if schaerfe > 0.0 else 1.0)
        summen.append(lauf_summe)
        ecken.append((a, b, c, (mw.to_3x3() @ t.normal).normalized()))
    print('[lauf] %d Dreiecke innerhalb %.0f m, %d ausserhalb'
          % (len(ecken), rmax, draussen))
    if lauf_summe <= 0.0:
        print('[lauf] Keine bewachsene Flaeche gefunden.')
        return

    # WO KEIN GRAS HINGEHOERT, WIRD GESTRAHLT STATT GERECHNET
    # Im Bild vom 19.09.2026 wuchsen Halme durch die Terrassenplatte und
    # standen im Pool -- die Punkte kamen vom Gelaendenetz, und das laeuft
    # unter dem Haus durch. Eine Liste von Ausschlussrechtecken waere beim
    # naechsten Umbau falsch. Blender kann strahlen (Unreal im Kommandlet
    # nicht), also wird von oben nach unten geschossen: Nur wo der Strahl
    # ZUERST das Gelaende trifft, waechst etwas. Terrasse, Becken, Weg und
    # Haus schliessen sich damit von selbst aus, und die Hoehe stimmt
    # exakt, weil sie der Treffer liefert.
    import bisect
    import mathutils
    rng = random.Random(20260919)
    lang = float(w.get('laenge', 0.095))
    tiefe = _b.context.evaluated_depsgraph_get()
    szene = _b.context.scene
    ab = mathutils.Vector((0.0, 0.0, -1.0))
    gesamt = 0
    verdeckt = 0
    daneben = 0
    versuche = 0
    grenze = hoechstens * 6
    with open(ziel, 'w', encoding='utf-8') as f:
        f.write('# x y z nx ny nz laenge -- Blender-Meter\n')
        while gesamt < hoechstens and versuche < grenze:
            versuche += 1
            k = bisect.bisect_left(summen, rng.random() * lauf_summe)
            if k >= len(ecken):
                k = len(ecken) - 1
            a, b, c, nrm = ecken[k]
            u = rng.random()
            v = rng.random()
            if u + v > 1.0:
                u, v = 1.0 - u, 1.0 - v
            p = a + (b - a) * u + (c - a) * v
            treffer, ort, normale, _i, objekt, _m = szene.ray_cast(
                tiefe, (p.x, p.y, p.z + 60.0), ab, distance=120.0)
            # 22.09.2026: DURCH ALLES HINDURCH, WAS HOCH UEBER DEM BODEN
            # SCHWEBT. Unter dem offenen Sonnenschirm lag der Rasen in
            # Unreal als kahler, heller Fleck in Form des Schirms: Der
            # Strahl traf das Tuch in 2,2 m Hoehe und galt als verdeckt.
            # Unter einem Schirm, einer Baumkrone oder einer Liege waechst
            # aber Gras. Also: Liegt der Treffer mehr als 1 m ueber der
            # Stelle, wird darunter weitergesucht. Die Grenze liegt bewusst
            # so hoch: Terrasse und Bodenplatte (0,38 m ueber dem Rasen),
            # Wasserspiegel, Toepfe und Baumscheiben liegen darunter und
            # schliessen weiter aus -- mit 0,25 m waere der Strahl durch
            # Terrasse und Pool gegangen und haette Halme ins Becken
            # gestellt, weil das Gelaendenetz darunter durchlaeuft.
            for _ in range(8):
                if not treffer or objekt is None or objekt.name == ob.name:
                    break
                if ort.z - p.z <= 1.0:
                    break
                treffer, ort, normale, _i, objekt, _m = szene.ray_cast(
                    tiefe, (p.x, p.y, ort.z - 0.002), ab, distance=120.0)
            if not treffer:
                daneben += 1
                continue
            if objekt is None or objekt.name != ob.name:
                verdeckt += 1
                continue
            f.write('%.4f %.4f %.4f %.4f %.4f %.4f %.4f\n'
                    % (ort.x, ort.y, ort.z,
                       normale.x, normale.y, normale.z,
                       lang * (0.72 + rng.random() * 0.6)))
            gesamt += 1
    print('[lauf] %d Halme geschrieben (%d von Bauteilen verdeckt, '
          '%d ins Leere) nach %s' % (gesamt, verdeckt, daneben, ziel))


def laub_ausgeben(w):
    """Punkte auf der Oberflaeche aller Objekte eines Materials.

    Fuer die Zypressen: Auf jedem Punkt steht drueben ein kleines
    Nadelbuendel, ausgerichtet auf die Flaechennormale. Damit bekommt
    der glatte Spindelkoerper die ausgefranste Silhouette, an der man
    eine Zypresse ueberhaupt erst erkennt.

    Format wie bei gras_ausgeben:  x y z nx ny nz laenge
    """
    import bpy as _b
    import bisect
    import random
    ziel = str(w.get('ziel',
                     r'C:\Users\manue\Desktop\Vecom Design\werkzeug\laub-punkte.txt'))
    material = str(w.get('material', 'Zypresse'))
    hoechstens = int(w.get('hoechstens', 80000))

    objekte = []
    for ob in _b.data.objects:
        if ob.type != 'MESH' or not ob.data.materials:
            continue
        for m in ob.data.materials:
            if m is not None and m.name == material:
                objekte.append(ob)
                break
    if not objekte:
        print('[lauf] Keine Objekte mit Material %s.' % material)
        return
    print('[lauf] %d Objekte mit Material %s' % (len(objekte), material))

    summen = []
    lauf_summe = 0.0
    ecken = []
    for ob in objekte:
        me = ob.data
        mw = ob.matrix_world
        n3 = mw.to_3x3()
        me.calc_loop_triangles()
        for t in me.loop_triangles:
            a, b, c = (mw @ me.vertices[i].co for i in t.vertices)
            flaeche = (b - a).cross(c - a).length * 0.5
            if flaeche <= 0.0:
                continue
            lauf_summe += flaeche
            summen.append(lauf_summe)
            ecken.append((a, b, c, (n3 @ t.normal).normalized()))
    print('[lauf] %d Dreiecke, %.1f qm Oberflaeche' % (len(ecken), lauf_summe))
    if lauf_summe <= 0.0:
        return
    # 21.09.2026: 'dichte' (Punkte je Quadratmeter) statt einer festen
    # Anzahl. Der Stand vom 20.09. waren 220.000 Punkte auf 304,9 qm, also
    # 721,5 je qm. Ohne die acht alten Kegel ist die Flaeche kleiner; mit
    # derselben Anzahl waeren die uebrigen Baeume dichter benadelt worden
    # als vorher -- zwei Aenderungen in einem Lauf.
    if 'dichte' in w:
        hoechstens = int(lauf_summe * float(w['dichte']))
        print('[lauf] Dichte %.1f je qm -> %d Punkte' % (float(w['dichte']), hoechstens))

    rng = random.Random(4711)
    lang = float(w.get('laenge', 0.095))
    gesamt = 0
    with open(ziel, 'w', encoding='utf-8') as f:
        f.write('# x y z nx ny nz laenge -- Blender-Meter\n')
        for _ in range(hoechstens):
            k = bisect.bisect_left(summen, rng.random() * lauf_summe)
            if k >= len(ecken):
                k = len(ecken) - 1
            a, b, c, nrm = ecken[k]
            u = rng.random()
            v = rng.random()
            if u + v > 1.0:
                u, v = 1.0 - u, 1.0 - v
            p = a + (b - a) * u + (c - a) * v
            f.write('%.4f %.4f %.4f %.4f %.4f %.4f %.4f\n'
                    % (p.x, p.y, p.z, nrm.x, nrm.y, nrm.z,
                       lang * (0.55 + rng.random() * 0.7)))
            gesamt += 1
    print('[lauf] %d Laubpunkte geschrieben nach %s' % (gesamt, ziel))


def sonne_setzen(azimut, hoehe=27.0, staerke=None):
    """Sonnenstand zur Laufzeit -- ueber villa_szene, damit Himmelsmodell
    und Lampe zusammenbleiben. staerke ist nur noch fuer Versuche da; im
    Regelbetrieb ist die Lampe aus und der Himmel die Lichtquelle."""
    szene = _SZENE[0]
    if szene is not None and 'sonnenstand' in szene:
        szene['sonnenstand'](hoehe, azimut)
    if staerke is not None:
        ob = bpy.data.objects.get('Sonne')
        if ob is not None:
            ob.data.energy = staerke
    return azimut, hoehe


def rechnen(kams, szene, namen, vorsatz, proben, breite, hoehe,
            rauschgrenze=0.010, belichtung=None):
    cyc = lade('villa_cycles.py')
    info = cyc['einstellen'](proben=proben, breite=breite, hoehe=hoehe,
                             rauschgrenze=rauschgrenze)
    sz = bpy.context.scene
    # Haare als Baender: eine Flaeche je Segment statt eines Roehrchens.
    # Bei 0,2 mm Halmdicke sieht das niemand, spart aber die Haelfte.
    try:
        sz.cycles.hair_shape = 'RIBBONS'
    except Exception:
        pass
    raus = []
    for name in namen:
        if name not in kams:
            print('[lauf] Kamera fehlt: ' + name)
            continue
        sz.camera = kams[name]
        bel = szene['belichtung_nach_kamera']()
        if belichtung is not None:
            sz.view_settings.exposure = belichtung
            bel = belichtung
        sz.render.filepath = os.path.join(R, '%s-%s.png' % (vorsatz, name))
        t0 = time.time()
        bpy.ops.render.render(write_still=True)
        dt = round(time.time() - t0, 1)
        raus.append((name, dt, round(bel, 2)))
        print('[lauf] %s-%s  %.1f s  Belichtung %.2f' % (vorsatz, name, dt, bel))
    print('[lauf] Geraet: %s' % info['geraet'])
    return raus


def blatt(dateien, ziel, spalten=2):
    """Kontaktbogen aus mehreren Bildern -- damit man vergleicht statt
    nacheinander zu schauen. Ein Unterschied, den man nur im Gedaechtnis
    vergleicht, ist kein gemessener Unterschied."""
    bilder = []
    for f in dateien:
        p = f if os.path.isabs(f) else os.path.join(R, f)
        if os.path.isfile(p):
            bilder.append(bpy.data.images.load(p))
    if not bilder:
        return None
    bw, bh = bilder[0].size
    zeilen = (len(bilder) + spalten - 1) // spalten
    gw, gh = bw * spalten, bh * zeilen
    aus = bpy.data.images.new('blatt', gw, gh, alpha=False)
    puffer = [0.0] * (gw * gh * 4)
    for k, im in enumerate(bilder):
        sx = (k % spalten) * bw
        sy = (zeilen - 1 - k // spalten) * bh
        px = list(im.pixels)
        for y in range(bh):
            z = ((sy + y) * gw + sx) * 4
            q = (y * bw) * 4
            puffer[z:z + bw * 4] = px[q:q + bw * 4]
    aus.pixels = puffer
    aus.filepath_raw = ziel if os.path.isabs(ziel) else os.path.join(R, ziel)
    aus.file_format = 'PNG'
    aus.save()
    print('[lauf] Bogen: %s (%d Bilder)' % (aus.filepath_raw, len(bilder)))
    return aus.filepath_raw


def main():
    auftrag, w = argumente()
    print('[lauf] Auftrag: %s %s' % (auftrag, w))
    if auftrag == 'blatt':
        blatt(str(w.get('dateien', '')).split(';'),
              str(w.get('ziel', 'bogen.png')), int(w.get('spalten', 2)))
        print('[lauf] FERTIG')
        return
    d, kams, szene = bauen(halme=w.get('halme', 90000),
                           gras=w.get('gras', 1), echt=w.get('echt', 1),
                           moebel_aussen=w.get('aussen', 1),
                           wolken=w.get('wolken', 1),
                           dunst=w.get('dunst', 1.0))

    if auftrag == 'sonne':
        kamera = w.get('kamera', 'garten')
        liste = [float(x) for x in
                 str(w.get('azimute', '191;215;249;292;325')).split(';')]
        for az in liste:
            sonne_setzen(az, hoehe=w.get('sonnenhoehe', 27.0))
            rechnen(kams, szene, [kamera], 'sonne%03d' % az,
                    int(w.get('proben', 72)), int(w.get('breite', 960)),
                    int(w.get('hoehe_px', 540)), rauschgrenze=0.02)
    elif auftrag == 'diagnose':
        # Warum diese vier: Die Messung am 17.09.2026 ergab, dass besonnte
        # und beschattete Flaechen sich nur um 0,03 Anzeigewert
        # unterscheiden. Entweder traegt die Sonne kaum bei, oder die
        # Belichtung schiebt alles in die Schulter der Tonwertkurve.
        # Diese vier Bilder trennen die beiden Moeglichkeiten.
        az = w.get('azimut', 325)
        for name, st, bel in (('d-hell', 4.9, -0.65),
                              ('d-knapp', 4.9, -2.20),
                              ('d-ohnesonne', 0.02, -2.20),
                              ('d-starkesonne', 14.0, -2.20)):
            sonne_setzen(az, hoehe=27.0, staerke=st)
            rechnen(kams, szene, [w.get('kamera', 'garten')], name,
                    int(w.get('proben', 72)), 960, 540,
                    rauschgrenze=0.02, belichtung=bel)
    elif auftrag == 'leiter':
        # Belichtungsleiter fuer EINE Kamera. Sie ersetzt das Augenmass:
        # Der Sollwert ist nicht "sieht gut aus", sondern eine gemessene
        # Zahl -- besonnte weisse Wand um 0,86, Himmel darueber, damit der
        # Himmel nicht dunkler ist als die Wand (in der Wirklichkeit ist
        # er es nie).
        for kam in str(w.get('kamera', 'garten')).split(';'):
            for bel in [float(x) for x in
                        str(w.get('stufen', '-2.0;-2.6;-3.1')).split(';')]:
                marke = ('%s%s' % ('m' if bel < 0 else 'p',
                                   abs(bel))).replace('.', '')
                rechnen(kams, szene, [kam], 'l-%s-%s' % (kam, marke),
                        int(w.get('proben', 90)), int(w.get('breite', 1280)),
                        int(w.get('hoehe_px', 720)), rauschgrenze=0.015,
                        belichtung=bel)
    elif auftrag == 'grasexport':
        # Die Halmpositionen fuer Unreal. Nicht neu gewuerfelt, sondern
        # DIESELBEN wie in Cycles: Unreal hat im Kommandlet keine Physik,
        # ein Strahl von oben trifft dort nichts, und das Gelaende aus
        # mathutils.noise laesst sich drueben nicht nachrechnen. Also
        # liefert Blender die Liste -- das ist ohnehin die Reihenfolge,
        # in der hier gearbeitet wird: erst Blender, dann Unreal.
        gras_ausgeben(w)
    elif auftrag == 'laubexport':
        # Nadelbuendel auf die Zypressen. Dieselbe Technik wie beim Gras
        # und aus demselben Grund: Ein glatter Kegel ist im Bild sofort
        # als Rechnerbild zu erkennen, weil eine Zypresse keine glatte
        # Silhouette hat, sondern eine ausgefranste.
        laub_ausgeben(w)
    elif auftrag == 'probe':
        if 'azimut' in w:
            sonne_setzen(w['azimut'], hoehe=w.get('sonnenhoehe', 27.0),
                         staerke=w.get('staerke'))
        rechnen(kams, szene, [w.get('kamera', 'garten')],
                str(w.get('vorsatz', 'probe')),
                int(w.get('proben', 96)), int(w.get('breite', 1280)),
                int(w.get('hoehe_px', 720)), rauschgrenze=0.015,
                belichtung=w.get('belichtung'))
    elif auftrag == 'bilder':
        if 'azimut' in w:
            sonne_setzen(w['azimut'], hoehe=w.get('sonnenhoehe', 27.0),
                         staerke=w.get('staerke'))
        # Semikolon, nicht Komma: Kommas trennen bereits die Wertepaare.
        namen = str(w.get('namen', 'garten;ankunft;terrasse;wohnen;essen;'
                                   'kueche;master')).split(';')
        # DIE RAUSCHGRENZE IST DER EIGENTLICHE REGLER, nicht die Probenzahl.
        # Mit adaptivem Sampling hoert Cycles auf, sobald eine Kachel unter
        # der Grenze liegt -- eine hoehere Probenzahl aendert dann gar
        # nichts. Gemessen am 19.09.2026: Der Wohnraum bei 480 Proben und
        # 0,0065 sah aus wie eine Aquarellzeichnung, weil der Entrauscher
        # den Rest erfinden musste. Innenraeume brauchen 0,0025.
        rechnen(kams, szene, namen, str(w.get('vorsatz', 'hyp')),
                int(w.get('proben', 520)), int(w.get('breite', 1920)),
                int(w.get('hoehe_px', 1080)),
                rauschgrenze=float(w.get('rauschen', 0.0065)))
    print('[lauf] FERTIG')


try:
    main()
except Exception:
    print('[lauf] FEHLER')
    traceback.print_exc()
