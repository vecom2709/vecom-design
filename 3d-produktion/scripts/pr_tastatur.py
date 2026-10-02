"""pr_tastatur.py -- Tastenfeld des Laptops in pr_arbeiten.py (03.10.2026).

Reines Python ohne bpy: Blender baut daraus die Kappen, und
tools/arbeiten-tastatur.py zeichnet aus denselben Zahlen die Beschriftung.
Zwei Quellen für dieselbe Anordnung würden auseinanderlaufen; die erste
Fassung der Tasten stand nur im Blender-Skript.

Maße nach heutigen 14"-Geräten: 16,2 mm Kappe auf 19 mm Raster, volle
Funktionsreihe (80 %), Pfeiltasten als umgedrehtes T mit halber Höhe.
Markenlos: keine Herstellertaste, keine Logos.
"""

KAPPE = 0.0162
RASTER = 0.0190
SPALT = RASTER - KAPPE
# Tastaturwanne (Mitte, Breite, Höhe) in Laptop-Koordinaten (m)
WANNE = dict(cx=0.0, cy=0.040, b=0.281, h=0.116)

# (Beschriftung, Breite in Rastern); 'pfeil' = ↑ über ↓ in einer Taste
ZEILEN = [
    (0.80, [('esc', 1.5)] + [(f'F{i}', 1.0) for i in range(1, 13)] + [('⏻', 1.0)]),
    (1.0, [('`', 1)] + [(c, 1) for c in '1234567890'] + [('-', 1), ('=', 1), ('⌫', 1.5)]),
    (1.0, [('⇥', 1.5)] + [(c, 1) for c in 'QWERTYUIOP'] + [('[', 1), (']', 1), ('\\', 1)]),
    (1.0, [('⇪', 1.75)] + [(c, 1) for c in 'ASDFGHJKL'] + [(';', 1), ("'", 1), ('⏎', 1.75)]),
    (1.0, [('⇧', 2.25)] + [(c, 1) for c in 'ZXCVBNM'] + [(',', 1), ('.', 1), ('/', 1), ('⇧', 2.25)]),
    (1.0, [('fn', 1), ('ctrl', 1), ('alt', 1), ('opt', 1.25), ('', 5.0), ('opt', 1.25), ('alt', 1),
           ('◀', 1), ('pfeil', 1), ('▶', 1)]),
]


def tasten():
    """Liste der Tasten: dict(x, y, b, h, text, klein). x/y = Mitte in m."""
    aus = []
    gesamt_h = sum(KAPPE * h for h, _ in ZEILEN) + SPALT * (len(ZEILEN) - 1)
    y = WANNE['cy'] + gesamt_h / 2
    for hoehe, reihe in ZEILEN:
        hk = KAPPE * hoehe
        gesamt = sum(b for _, b in reihe) * RASTER - SPALT
        x = -gesamt / 2
        for text, bw in reihe:
            kb = bw * RASTER - SPALT
            mx, my = x + kb / 2, y - hk / 2
            if text == 'pfeil':
                hh = (hk - 0.0012) / 2
                aus.append(dict(x=mx, y=y - hh / 2, b=kb, h=hh, text='▲', klein=True))
                aus.append(dict(x=mx, y=y - hk + hh / 2, b=kb, h=hh, text='▼', klein=True))
            elif text in ('◀', '▶'):
                hh = (hk - 0.0012) / 2          # seitliche Pfeile ebenfalls halb hoch, unten bündig
                aus.append(dict(x=mx, y=y - hk + hh / 2, b=kb, h=hh, text=text, klein=True))
            else:
                aus.append(dict(x=mx, y=my, b=kb, h=hk, text=text, klein=len(text) > 1 or bw > 1.2))
            x += bw * RASTER
        y -= hk + SPALT
    return aus
