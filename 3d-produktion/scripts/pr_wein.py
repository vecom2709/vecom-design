"""pr_wein.py -- Wein & Naturprodukte: Bordeaux-Flasche mit Etikett zum
Waehlen, dazu eine Holzkiste mit Tuer, in der eine zweite Flasche steht.

Masse einer 0,75-l-Bordolese (Herstellerzeichnungen, gerundet): Hoehe
300 mm, Koerper 76 mm, Wand 3,5 mm, Boden 5 mm mit Stichboden (Punt),
Muendung mit Bague 33 mm. Fuellhoehe bis in den Hals (242 mm).

Varianten (KHR_materials_variants): Nero d'Avola, Grillo, Olio extra
vergine -- Glasfarbe, Inhalt, Kapsel und Etikett wechseln zusammen.
Im Web ist der Inhalt ohne Transmission (three.js bricht nicht durch zwei
transparente Koerper hindurch -- der Wein waere hinter dem Glas
verschwunden), dafuer dunkel und glatt wie gefuellte Flaschen im Foto.
"""
import math
import numpy as np
import bpy
from mathutils import Vector
import pr_basis as B


def catmull(pts, n=6):
    p = np.asarray(pts, float); out = []
    for i in range(len(p) - 1):
        p0 = p[max(i - 1, 0)]; p1 = p[i]; p2 = p[i + 1]; p3 = p[min(i + 2, len(p) - 1)]
        for t in np.linspace(0, 1, n, endpoint=False):
            t2, t3 = t * t, t * t * t
            out.append(0.5 * ((2 * p1) + (-p0 + p2) * t + (2 * p0 - 5 * p1 + 4 * p2 - p3) * t2 + (-p0 + 3 * p1 - 3 * p2 + p3) * t3))
    out.append(p[-1])
    return np.array(out)


AUSSEN = [(0.0, 0.018), (0.010, 0.0168), (0.019, 0.0125), (0.026, 0.006), (0.0305, 0.0012), (0.0330, 0.0),
          (0.0362, 0.0018), (0.0377, 0.0070), (0.0380, 0.0160), (0.0380, 0.1000), (0.0380, 0.2000), (0.0374, 0.2090),
          (0.0340, 0.2220), (0.0262, 0.2360), (0.0192, 0.2460), (0.0158, 0.2560), (0.0149, 0.2700), (0.0147, 0.2840),
          (0.0160, 0.2852), (0.0166, 0.2880), (0.0166, 0.2960), (0.0158, 0.2996), (0.0120, 0.3000), (0.0096, 0.2992)]
INNEN = [(0.0092, 0.2960), (0.0092, 0.2800), (0.0094, 0.2680), (0.0104, 0.2550), (0.0140, 0.2450), (0.0214, 0.2350),
         (0.0300, 0.2220), (0.0343, 0.2080), (0.0345, 0.2000), (0.0345, 0.1000), (0.0345, 0.0240), (0.0330, 0.0210),
         (0.0280, 0.0170), (0.0200, 0.0205), (0.0100, 0.0228), (0.0, 0.0235)]
FUELL = 0.242


def profil_bei(pr, z):
    """Radius des (inneren) Profils in Hoehe z (Profil laeuft von oben nach unten)."""
    pr = np.asarray(pr)
    for (r0, z0), (r1, z1) in zip(pr[:-1], pr[1:]):
        if min(z0, z1) <= z <= max(z0, z1) and abs(z1 - z0) > 1e-9:
            return r0 + (r1 - r0) * (z - z0) / (z1 - z0)
    return pr[0][0]


def bauen(web=False):
    n = 96 if web else 192
    sc = bpy.context.scene
    # ---------------------------------------------------------------- Stoffe
    VAR = ['Rosso', 'Bianco', 'Olio']        # Dateinamen der Poster; Anzeige im Web
    glas = [B.stoff('Flaschenglas Rosso', (0.30, 0.46, 0.26), 0.0, trans=1.0, ior=1.52),
            B.stoff('Flaschenglas Bianco', (0.78, 0.90, 0.72), 0.0, trans=1.0, ior=1.52),
            B.stoff('Flaschenglas Olio', (0.20, 0.32, 0.14), 0.0, trans=1.0, ior=1.52)]
    if web:
        inhalt = [B.stoff('Inhalt Rosso', (0.10, 0.004, 0.012), 0.04, spec=0.5),
                  B.stoff('Inhalt Bianco', (0.62, 0.52, 0.18), 0.04, spec=0.5),
                  B.stoff('Inhalt Olio', (0.30, 0.26, 0.02), 0.04, spec=0.5)]
    else:
        inhalt = [B.stoff('Inhalt Rosso', (0.30, 0.006, 0.025), 0.0, trans=1.0, ior=1.345),   # Rotwein ist in der Flasche fast undurchsichtig
                  B.stoff('Inhalt Bianco', (0.98, 0.90, 0.55), 0.0, trans=1.0, ior=1.335),
                  B.stoff('Inhalt Olio', (0.80, 0.70, 0.12), 0.0, trans=1.0, ior=1.47)]
    kapsel = [B.stoff('Kapsel Rosso', (0.32, 0.02, 0.05), 0.32, metall=1.0),
              B.stoff('Kapsel Bianco', (0.85, 0.66, 0.32), 0.28, metall=1.0),
              B.stoff('Kapsel Olio', (0.18, 0.28, 0.08), 0.34, metall=1.0)]
    etik = [B.stoff(f'Etikett {k}', (1, 1, 1), 0.7, farbkarte=f'etikett-{k.lower()}-farbe', mr=f'etikett-{k.lower()}-mr',
                    normal=f'etikett-{k.lower()}-normal', normal_staerke=0.6) for k in ('Rosso', 'Bianco', 'Olio')]
    kork = B.stoff('Kork', (1, 1, 1), 0.85, farbkarte='kork-farbe')
    # Poren nur angedeutet: mit Normalstaerke 0,6 fingen sie das Deckenlicht
    # und standen als helle Striche wie Birkenrinde im Poster.
    holz = B.stoff('Holz Kiste', (1, 1, 1), 0.52, farbkarte='holz-kiste-farbe', normal='innen-walnuss-normal',
                   normal_staerke=0.2, kachel=0.40)
    filz = B.stoff('Filz Kiste', (0.060, 0.010, 0.015), 1.0, spec=0.3)   # Bordeaux-Filz; mit Sheen wurde er rosa-weiss
    messing = B.stoff('Messing', (0.86, 0.68, 0.38), 0.32, metall=1.0)

    # ---------------------------------------------------------------- Flasche
    def flasche(name, ort):
        prof = np.vstack([catmull(AUSSEN, 5), catmull(INNEN, 5)])
        teile = []
        g = B.drehkoerper(f'{name}_glas', prof, n, [glas[0]])
        teile.append(g)
        # Inhalt: inneres Profil unterhalb der Fuellhoehe, 0,3 mm eingerueckt, Meniskus oben
        innen = catmull(INNEN, 5)[::-1]                      # von unten nach oben
        unten = [(max(r - 0.0003, 0.0), z + 0.0003) for r, z in innen if z < FUELL]
        r_f = profil_bei(INNEN, FUELL) - 0.0003
        oben = [(r_f, FUELL + 0.0012), (r_f - 0.0012, FUELL + 0.0003), (r_f * 0.5, FUELL), (0.0, FUELL)]
        B.drehkoerper(f'{name}_inhalt', unten + oben, n, [inhalt[0]])
        teile.append(bpy.data.objects[f'{name}_inhalt'])
        # Kapsel: aeusseres Profil ab 252 mm, 0,5 mm Abstand, Deckel
        aus = [(r + 0.0005, z) for r, z in catmull(AUSSEN, 5) if z >= 0.252 and r > 0.011]
        kp = [(aus[0][0] + 0.0004, aus[0][1] - 0.0006)] + aus + [(0.0122, 0.3006), (0.0, 0.3006)]
        k = B.drehkoerper(f'{name}_kapsel', kp, n, [kapsel[0]])
        # Kork im Hals (sichtbar, wenn die Kapsel abgenommen ist)
        kr = [(0.0, 0.2555), (0.0086, 0.2555), (0.0091, 0.2575), (0.0091, 0.2985), (0.0086, 0.2997), (0.0, 0.2997)]
        ko = B.drehkoerper(f'{name}_kork', kr, 48, [kork])
        # Etikett vorn (Richtung -Y, zur Kamera), 110 x 95 mm
        breite = 0.110 / 0.0383
        # zur Studiokamera gedreht (winkel -24 Grad -> Richtung -66 Grad)
        mitte_w = math.radians(-66)
        e = B.mantel(f'{name}_etikett', 0.0383, 0.070, 0.165, mitte_w - breite / 2, mitte_w + breite / 2, 64, etik[0])
        for o in (g, teile[1], k, ko, e):
            o.location = ort
        return g, teile[1], k, ko, e

    fl = flasche('flasche', (0.0, 0.0, 0.0))
    # ---------------------------------------------------------------- Kiste
    # Geschenkkiste fuer 1, 2 oder 3 Flaschen (Wunsch B1). Die rechte Kante
    # bleibt stehen, breitere Kisten wachsen nach links. Die Einzelkiste
    # heisst weiter kiste_* (Zerlegen-Regeln, Poster); 2 und 3 sind nur fuers
    # Web (Eigenschaft nur_web -- das Fotostudio blendet sie aus).
    def kiste(pre, anzahl, nur_web):
        T, H, W = 0.108, 0.345, 0.009
        A = anzahl * 0.090 + 0.018
        kx, ky = -0.111 - A / 2, 0.035
        objs = [
            B.kasten(f'{pre}boden', A, T, W, holz, 0.0015, (kx, ky, 0), True),
            B.kasten(f'{pre}deckel', A, T, W, holz, 0.0015, (kx, ky, H - W), True),
            B.kasten(f'{pre}links', W, T, H - 2 * W, holz, 0.001, (kx - A / 2 + W / 2, ky, W), True),
            B.kasten(f'{pre}rechts', W, T, H - 2 * W, holz, 0.001, (kx + A / 2 - W / 2, ky, W), True),
            B.kasten(f'{pre}rueck', A - 2 * W, W, H - 2 * W, holz, 0.001, (kx, ky + T / 2 - W / 2, W), True),
        ]
        iw = A - 2 * W
        objs += [B.kasten(f'{pre}filz_boden', iw, T - 2 * W, 0.001, filz, 0.0, (kx, ky, W)),
                 B.kasten(f'{pre}filz_rueck', iw, 0.001, H - 2 * W, filz, 0.0, (kx, ky + T / 2 - W - 0.0005, W)),
                 B.kasten(f'{pre}filz_links', 0.001, T - 2 * W, H - 2 * W, filz, 0.0, (kx - iw / 2 + 0.0005, ky, W)),
                 B.kasten(f'{pre}filz_rechts', 0.001, T - 2 * W, H - 2 * W, filz, 0.0, (kx + iw / 2 - 0.0005, ky, W))]
        # Trennstege zwischen den Flaschen
        for k in range(1, anzahl):
            objs.append(B.kasten(f'{pre}steg_{k}', 0.006, T - 2 * W, H - 2 * W, holz, 0.001, (kx - iw / 2 + k * 0.090, ky, W), True))
        # Tuer vorn, Scharnier links vorn (Messingband) -- dreht nach aussen
        tuer = B.kasten(f'{pre}tuer', iw - 0.001, W, H - 2 * W - 0.001, holz, 0.001, (kx, ky - T / 2 + W / 2, W + 0.0005), True)
        tuer_filz = B.kasten(f'{pre}tuer_filz', iw - 0.004, 0.001, H - 2 * W - 0.006, filz, 0.0, (kx, ky - T / 2 + W + 0.0005, W + 0.003))
        angel_ort = (kx - iw / 2, ky - T / 2, 0)
        for zz in (0.06, H - 0.06):
            b = B.drehkoerper(f'{pre}band_{int(zz * 1000)}', [(0.0, zz - 0.02), (0.0028, zz - 0.02), (0.0028, zz + 0.02), (0.0, zz + 0.02)], 16, [messing])
            b.location = (kx - iw / 2, ky - T / 2 - 0.0005, 0); objs.append(b)
        riegel = B.kasten(f'{pre}riegel', 0.012, 0.004, 0.028, messing, 0.001, (kx + iw / 2 - 0.012, ky - T / 2 - 0.002, H / 2 - 0.014))
        ang = B.angel(f'{pre}tuer_angel', angel_ort, (0, 0, 1), 105.0, [tuer, tuer_filz, riegel])
        objs += [tuer, tuer_filz, riegel, ang]
        # Flaschen in der Kiste (gleiche Netze, eigene Objekte)
        for k in range(anzahl):
            for o in fl:
                k2 = o.copy(); k2.name = o.name.replace('flasche', f'{pre}flasche_{k}' if anzahl > 1 else 'kiste_flasche'); sc.collection.objects.link(k2)
                k2.location = (kx - iw / 2 + 0.045 + k * 0.090, ky + 0.004, W + 0.001); objs.append(k2)
        if nur_web:
            for o in objs:
                o['nur_web'] = 1
        return objs

    kiste('kiste_', 1, False)
    kiste('kiste2_', 2, True)
    kiste('kiste3_', 3, True)

    # ---------------------------------------------------------------- Weinglas
    # Bordeaux-Glas (Hoehe 225 mm, Kelch 100 mm, Wand 1,2 mm) rechts vor der
    # Flasche. Der Wein darin ist nur fuers Web: Beim Einschenken steigt er,
    # im Foto steht das Glas leer (Wunsch B1: "das Glas fuellt sich").
    G_AUSSEN = [(0.0, 0.0), (0.040, 0.0), (0.0412, 0.0018), (0.0385, 0.0042), (0.020, 0.0072), (0.0058, 0.0125), (0.0045, 0.030),
                (0.0045, 0.086), (0.0075, 0.0975), (0.0240, 0.1045), (0.0400, 0.1195), (0.0490, 0.1395), (0.0502, 0.1550),
                (0.0482, 0.1750), (0.0432, 0.2000), (0.0385, 0.2250)]
    G_INNEN = [(0.0373, 0.2250), (0.0420, 0.2000), (0.0470, 0.1750), (0.0490, 0.1550), (0.0478, 0.1400), (0.0388, 0.1210),
               (0.0232, 0.1068), (0.0078, 0.1016), (0.0, 0.1010)]
    gl_stoff = B.stoff('Weinglas', (1.0, 1.0, 1.0), 0.0, trans=1.0, ior=1.5)
    GX, GY = 0.165, -0.030
    wglas = B.drehkoerper('weinglas', np.vstack([catmull(G_AUSSEN, 4), catmull(G_INNEN, 4)]), n, [gl_stoff])
    wglas.location = (GX, GY, 0)
    VOLL = 0.135
    wp = [(0.0, 0.1013), (0.0075, 0.1019), (0.0229, 0.1071), (0.0385, 0.1213), (0.0451, VOLL), (0.0, VOLL)]
    # Eigene Stoffe fuer den Wein im Glas: Im Web wird er an einer Ebene
    # abgeschnitten (Fuellstand) -- mit dem Stoff der Flasche waere der Wein
    # in der Flasche mit abgeschnitten worden.
    glaswein = [B.stoff('Glaswein Rosso', (0.16, 0.006, 0.02), 0.04, spec=0.5),
                B.stoff('Glaswein Bianco', (0.70, 0.58, 0.22), 0.04, spec=0.5),
                B.stoff('Glaswein Olio', (0.36, 0.30, 0.03), 0.04, spec=0.5)]
    ww = B.drehkoerper('weinglas_wein', wp, n, [glaswein[0]])
    ww.location = (GX, GY, 0); ww['nur_web'] = 1

    # Einschenken: Die Flasche hebt sich und kippt ueber das Glas. Drehpunkt in
    # halber Flaschenhoehe, 112 Grad nach +X, dazu 16 cm hoch und 2 cm vor --
    # so bleibt der Flaschenboden ueber der Kiste (Rechnung 24.09.2026) und die
    # Muendung steht gut 3 cm ueber dem Glasrand.
    # Kapsel und Korken nicht mitnehmen: Sie sind in Stufe 2 schon abgenommen
    B.angel('flasche_angel', (0.0, 0.0, 0.150), (0, -1, 0), 112.0, [fl[0], fl[1], fl[4]])
    zuordnung = {glas[0]: glas, inhalt[0]: inhalt, kapsel[0]: kapsel, etik[0]: etik, glaswein[0]: glaswein}
    B.exportieren('wein', web, VAR, zuordnung,
                  'Vecom Design, eigener Entwurf; Bordolese 0,75 l nach Normmassen, Etiketten fuer die Demo gestaltet')
    if not web:
        B.speichern('wein')
