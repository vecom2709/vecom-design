"""geo.bin (VDGEO2) aus DB-IP City Lite bauen (03.10.2026, Uwe: Ja zur Stadt).
Bereiche nach (Land, Region, Stadt) zusammenfassen; Region und Stadt nur fuer
STADT_LAENDER (Zielmaerkte), sonst nur das Land. Eintraege: v4 12 Byte
(Start 4, Land 2, Region 2, Stadt 4), v6 16 Byte (Start 8 = erste 64 Bit, ...)."""
import csv, gzip, ipaddress, struct, sys
STADT_LAENDER = {'IT', 'DE', 'AT', 'CH'}
quelle, ziel = sys.argv[1], sys.argv[2]
regionen, staedte = [''], ['']
ri, si = {'': 0}, {'': 0}
def idx(tab, m, s):
    s = s[:120]
    while len(s.encode('utf-8')) > 255: s = s[:-1]
    if s not in m: m[s] = len(tab); tab.append(s)
    return m[s]
v4, v6 = [], []
with gzip.open(quelle, 'rt', encoding='utf-8', newline='') as f:
    for z in csv.reader(f):
        a, b, _, land = z[0], z[1], z[2], z[3]
        reg = z[4] if land in STADT_LAENDER else ''
        st = z[5] if land in STADT_LAENDER else ''
        satz = (land if len(land) == 2 else 'ZZ', idx(regionen, ri, reg), idx(staedte, si, st))
        ip = ipaddress.ip_address(a)
        if ip.version == 4:
            start = int(ip)
            if v4 and v4[-1][1] == satz: continue
            v4.append((start, satz))
        else:
            start = int(ip) >> 64
            if v6 and v6[-1][1] == satz: continue
            if v6 and v6[-1][0] == start: v6[-1] = (start, satz); continue
            v6.append((start, satz))
with open(ziel, 'wb') as o:
    o.write(b'VDGEO2' + struct.pack('>IIII', len(v4), len(v6), len(regionen), len(staedte)))
    for tab in (regionen, staedte):
        for s in tab:
            e = s.encode('utf-8'); o.write(bytes([len(e)]) + e)
    for start, (l, r, s) in v4: o.write(struct.pack('>I', start) + l.encode() + struct.pack('>HI', r, s))
    for start, (l, r, s) in v6: o.write(struct.pack('>Q', start) + l.encode() + struct.pack('>HI', r, s))
print('v4', len(v4), 'v6', len(v6), 'regionen', len(regionen), 'staedte', len(staedte))
