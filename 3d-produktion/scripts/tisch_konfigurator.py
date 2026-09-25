# -*- coding: utf-8 -*-
"""Der Schalter: alle 72 Varianten durchsteuern, pruefen, katalogisieren.

WARUM PRUEFEN UND NICHT NUR SCHALTEN

Ein Konfigurator, der 72 Kombinationen anbietet, verspricht 72 Mal, dass es
funktioniert. Gebaut und angesehen hat man davon typischerweise drei. Die
Kombination, die bricht, findet dann der Kunde -- live, auf der Seite. Also
wird hier jede einzelne gebaut und nachgemessen, bevor irgendetwas
ausgeliefert wird.

Geprueft wird gegen Zahlen, die aus dem Gebrauch kommen, nicht aus dem Modell:

    Oberkante            0,750 m  +-2 mm    Esstischhoehe
    Beinfreiheit         >= 0,640 m         Knie unter der Zarge
    Gedecke je Laengs.   >= 2                60 cm je Gedeck
    Ueberstand am Bein   >= 0,080 m         damit ein Stuhl darunter passt
    Kippsicherheit       Standflaeche >= 55 % der Plattenlaenge

WAS AM ENDE HERAUSKOMMT

    tisch-katalog.json   ein Satz je Variante, mit Artikelnummer, Massen,
                         Materialien und dem Pruefergebnis. Das ist der
                         Datensatz, aus dem die Webseite ihre Auswahl baut --
                         und die Artikelnummer ist das, was am Ende in der
                         Anfrage steht statt "was kostet das?".
"""
import bpy, json, os, sys
from mathutils import Vector

P = os.path.dirname(os.path.abspath(__file__))
if P not in sys.path:
    sys.path.append(P)
import tisch_varianten as tv

HOELZER = ['Eiche', 'Esche', 'Nussbaum', 'Raeuchereiche']
METALLE = ['Schwarzstahl', 'Edelstahl', 'Messing']
GESTELLE = ['wange', 'vierbein']
MASSE = ['klein', 'mittel', 'gross']

KUERZEL = {
    'Eiche': 'EI', 'Esche': 'ES', 'Nussbaum': 'NU', 'Raeuchereiche': 'RE',
    'Schwarzstahl': 'S', 'Edelstahl': 'E', 'Messing': 'M',
    'wange': 'W', 'vierbein': 'V',
}

GRENZEN = dict(
    oberkante=(0.748, 0.752),
    beinfreiheit_min=0.640,
    gedeck_breite=0.60,
    ueberstand_min=0.080,
    standflaeche_anteil=0.55,
)


def alle():
    """Alle 72 Kombinationen, in fester Reihenfolge."""
    aus = []
    for g in GESTELLE:
        for la in MASSE:
            for h in HOELZER:
                for me in METALLE:
                    aus.append(dict(gestell=g, laenge=la, holz=h, metall=me))
    return aus


def artikelnummer(v):
    L = int(round(tv.LAENGEN[v['laenge']] * 100))
    return 'VD-T-%s%d-%s%s' % (KUERZEL[v['gestell']], L,
                               KUERZEL[v['holz']], KUERZEL[v['metall']])


def _huelle(o):
    e = [o.matrix_world @ Vector(k) for k in o.bound_box]
    return (min(p.x for p in e), max(p.x for p in e),
            min(p.y for p in e), max(p.y for p in e),
            min(p.z for p in e), max(p.z for p in e))


def pruefen(v, szene=None):
    """Baut die Variante und misst nach. Gibt Messwerte und Befunde zurueck."""
    szene = szene or bpy.context.scene
    teile = tv.bauen(laenge=v['laenge'], gestell=v['gestell'],
                     holz=v['holz'], metall=v['metall'], szene=szene)
    bpy.context.view_layer.update()

    platte = next(o for o in teile if o.name.startswith('Platte'))
    px0, px1, py0, py1, pz0, pz1 = _huelle(platte)
    L = px1 - px0

    traeger = [o for o in teile if not o.name.startswith('Platte')]
    tx = [_huelle(o) for o in traeger]
    stand_x0 = min(t[0] for t in tx)
    stand_x1 = max(t[1] for t in tx)

    # Beinfreiheit: tiefstes Bauteil, das UNTER der Platte quer liegt
    unter = [t for t in tx if t[5] > 0.30 and (t[5] - t[4]) < 0.12]
    beinfreiheit = min((t[4] for t in unter), default=pz0)

    ueberstand = min(abs(px0 - stand_x0), abs(px1 - stand_x1))
    standanteil = (stand_x1 - stand_x0) / L
    gedecke = int((L - 0.10) // GRENZEN['gedeck_breite'])

    befunde = []
    if not (GRENZEN['oberkante'][0] <= pz1 <= GRENZEN['oberkante'][1]):
        befunde.append('Oberkante %.4f m ausserhalb 0,748-0,752' % pz1)
    if beinfreiheit < GRENZEN['beinfreiheit_min']:
        befunde.append('Beinfreiheit %.4f m unter 0,640' % beinfreiheit)
    if ueberstand < GRENZEN['ueberstand_min']:
        befunde.append('Ueberstand %.4f m unter 0,080 -- Stuhl passt nicht darunter' % ueberstand)
    if standanteil < GRENZEN['standflaeche_anteil']:
        befunde.append('Standflaeche %.1f %% der Laenge -- Kippgefahr' % (standanteil * 100))
    if gedecke < 2:
        befunde.append('nur %d Gedecke je Laengsseite' % gedecke)

    return dict(
        artikel=artikelnummer(v), **v,
        laenge_m=round(L, 4), breite_m=round(py1 - py0, 4),
        oberkante_m=round(pz1, 4),
        beinfreiheit_m=round(beinfreiheit, 4),
        ueberstand_m=round(ueberstand, 4),
        standflaeche_anteil=round(standanteil, 4),
        gedecke_je_seite=gedecke,
        teile=len(teile),
        befunde=befunde,
        haelt=not befunde,
    )


def alle_pruefen(szene=None):
    aus = [pruefen(v, szene) for v in alle()]
    schlecht = [a for a in aus if not a['haelt']]
    print('[KONFIG] %d Varianten geprueft, %d mit Befund' % (len(aus), len(schlecht)))
    for a in schlecht:
        print('[KONFIG] %s: %s' % (a['artikel'], '; '.join(a['befunde'])))
    return aus


def katalog_schreiben(saetze, pfad=None):
    pfad = pfad or os.path.join(os.path.dirname(P), 'web-export', 'tisch-katalog.json')
    os.makedirs(os.path.dirname(pfad), exist_ok=True)
    daten = {
        'modell': 'VECOM Esstisch',
        'stand': '2026-09-16',
        'achsen': {'holz': HOELZER, 'metall': METALLE,
                   'gestell': GESTELLE, 'laenge': MASSE},
        'laengen_m': tv.LAENGEN,
        'grenzen': GRENZEN,
        'varianten': saetze,
    }
    with open(pfad, 'w', encoding='utf-8') as f:
        json.dump(daten, f, ensure_ascii=False, indent=1)
    return pfad
