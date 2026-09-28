"""Mischung Sprecher + Musik fuer die Werbefilme (28.09.2026).

Aufruf: python3 mischen.py <auftrag.json> <stimmordner> <musik.mp3> <musik_treffer_s> <film_treffer_s> <laenge_s> <ziel.wav> [einblenden_s]

- Jeder Satz wird an Stille beschnitten und an seine Zeit gelegt. Laeuft ein Satz in den
  naechsten, rutscht der naechste nach hinten (Luft 0.22 s, nach Komma/Doppelpunkt 0.10 s).
- Musik wird so geschnitten, dass ihr grosser Schlag (musik_treffer_s) genau auf den Moment
  im Film faellt, in dem das Logo erscheint (film_treffer_s).
- Die Musik weicht der Stimme (Sidechain), Stimme bleibt deutlich lauter.
- Ergebnis: 48 kHz Stereo, ca. -14 LUFS, True Peak <= -1 dBTP.
Gibt eine Zeittabelle und Pegelmessung aus (Stimme gegen Musik waehrend gesprochen wird).
"""
import json, sys, os, subprocess, glob, tempfile
import numpy as np

SR = 48000
auftrag, stimmen, musik, m_hit, f_hit, laenge, ziel = sys.argv[1:8]
einblenden = float(sys.argv[8]) if len(sys.argv) > 8 else 4.0
m_hit, f_hit, laenge = float(m_hit), float(f_hit), float(laenge)
a = json.load(open(auftrag, encoding='utf-8'))
tmp = tempfile.mkdtemp()


def lade(pfad, sr=SR):
    raw = subprocess.run(['ffmpeg', '-v', 'error', '-i', pfad, '-ac', '1', '-ar', str(sr), '-f', 'f32le', '-'],
                         capture_output=True, check=True).stdout
    return np.frombuffer(raw, np.float32).copy()


def beschneiden(x, schwelle_db=-42.0, rand=0.03):
    w = int(0.01 * SR)
    r = np.array([np.sqrt(np.mean(x[i:i + w] ** 2)) + 1e-9 for i in range(0, len(x) - w, w)])
    an = np.where(20 * np.log10(r) > schwelle_db)[0]
    if len(an) == 0:
        return x
    s = max(0, an[0] * w - int(rand * SR)); e = min(len(x), (an[-1] + 1) * w + int(rand * SR))
    return x[s:e]


def lufs(pfad):
    o = subprocess.run(['ffmpeg', '-nostats', '-i', pfad, '-af', 'ebur128=peak=true', '-f', 'null', '-'],
                       capture_output=True, text=True).stderr
    i = [l for l in o.splitlines() if l.strip().startswith('I:')][-1].split()[1]
    tp = [l for l in o.splitlines() if l.strip().startswith('Peak:')]
    return float(i), (tp[-1].split()[1] if tp else '?')


def schreibe(pfad, x, ch=1):
    x = np.asarray(x, np.float32)
    subprocess.run(['ffmpeg', '-v', 'error', '-y', '-f', 'f32le', '-ar', str(SR), '-ac', str(ch), '-i', '-', pfad],
                   input=x.tobytes(), check=True)


# ---------------------------------------------------------------- Stimme legen
dateien = sorted(glob.glob(os.path.join(stimmen, a['name'] + '-[0-9][0-9].*')))
assert len(dateien) == len(a['saetze']), (len(dateien), len(a['saetze']))
clips = [beschneiden(lade(f)) for f in dateien]
# Anker: Satz (1-basiert), der genau auf seiner Zeit liegen MUSS (z. B. „Vecom Design." zum Logo).
# Ohne Angabe: der vorletzte Satz. Alles davor wird bei Bedarf leicht gestrafft (max. 9 %).
anker = int(a.get('anker', len(clips) - 1)) - 1


def dehnen(x, f):
    if abs(f - 1.0) < 0.005:
        return x
    p_in, p_out = os.path.join(tmp, 'd_in.wav'), os.path.join(tmp, 'd_out.wav')
    schreibe(p_in, x)
    subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', p_in, '-af', 'atempo=%.4f' % f, p_out], check=True)
    return lade(p_out)


def legen(clips, luft_s, luft_k):
    t_ist, ende = [], 0.0
    for i, x in enumerate(clips):
        luft = luft_k if i and a['saetze'][i - 1].rstrip()[-1:] in ',:' else luft_s
        t = max(float(a['zeiten'][i]), ende + (luft if i else 0.0))
        if i == anker:
            t = max(t, float(a['zeiten'][i]))
        t_ist.append(t); ende = t + len(x) / SR
    return t_ist


tempo = 1.0
for versuch in range(12):
    luft_s, luft_k = (0.22, 0.10) if versuch == 0 else (0.16, 0.08)
    t_ist = legen(clips, luft_s, luft_k)
    zu_spaet = t_ist[anker] - float(a['zeiten'][anker])
    if zu_spaet <= 0.05 or tempo >= 1.09:
        break
    tempo = min(1.09, tempo + 0.015)
    clips = [dehnen(beschneiden(lade(f)), tempo) if i < anker else c for i, (f, c) in enumerate(zip(dateien, clips))]
if tempo > 1.0:
    print('Stimme vor dem Anker gestrafft: Tempo %.3f' % tempo)
spur = np.zeros(int(laenge * SR), np.float32)
tabelle, bereiche = [], []
for i, (x, t) in enumerate(zip(clips, t_ist)):
    s_ = int(t * SR); e_ = min(len(spur), s_ + len(x))
    spur[s_:e_] += x[:e_ - s_]
    bereiche.append((t, t + len(x) / SR))
    t0 = float(a['zeiten'][i])
    tabelle.append((i + 1, t0, round(t, 2), round(len(x) / SR, 2), round(t + len(x) / SR, 2), round(t - t0, 2), a['saetze'][i]))
    if t + len(x) / SR > laenge - 0.3:
        print('WARNUNG: Satz', i + 1, 'endet zu spaet', round(t + len(x) / SR, 2))
    if i and t < bereiche[i - 1][1]:
        print('WARNUNG: Satz', i + 1, 'ueberlappt')
for z in tabelle:
    print('%2d  plan %5.2f  ist %5.2f  dauer %4.2f  ende %5.2f  versatz %+5.2f  %s' % z)
roh = os.path.join(tmp, 'stimme_roh.wav'); schreibe(roh, spur)
# Stimme: Tiefen unter 70 Hz weg, leichte Praesenz, gleichmaessig (Kompressor), -16 LUFS
stimme = os.path.join(tmp, 'stimme.wav')
subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', roh, '-af',
                'highpass=f=70,equalizer=f=180:t=q:w=1.0:g=1.5,equalizer=f=3200:t=q:w=1.2:g=2.0,'
                'acompressor=threshold=0.08:ratio=3:attack=8:release=160:makeup=1.5,'
                'loudnorm=I=-16:TP=-2:LRA=7,aresample=48000', '-ac', '2', stimme], check=True)

# ---------------------------------------------------------------- Musik schneiden
start = m_hit - f_hit
m = lade(musik)
if start >= 0:
    m = m[int(start * SR):]
else:
    m = np.concatenate([np.zeros(int(-start * SR), np.float32), m])
m = m[:int(laenge * SR)]
if len(m) < int(laenge * SR):
    m = np.concatenate([m, np.zeros(int(laenge * SR) - len(m), np.float32)])
n_ein = int(einblenden * SR); m[:n_ein] *= (np.linspace(0, 1, n_ein) ** 2)
n_aus = int(0.8 * SR); m[-n_aus:] *= np.linspace(1, 0, n_aus)
mroh = os.path.join(tmp, 'musik_roh.wav'); schreibe(mroh, m)
mus = os.path.join(tmp, 'musik.wav')
subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', mroh, '-af', 'loudnorm=I=-20:TP=-2,aresample=48000', '-ac', '2', mus], check=True)

# ---------------------------------------------------------------- Ducking + Summe
summe = os.path.join(tmp, 'summe.wav')
fk = ('[1:a]asplit=2[sc][v];[0:a][sc]sidechaincompress=threshold=0.015:ratio=7:attack=20:release=500:makeup=1[md];'
      '[md][v]amix=inputs=2:normalize=0:weights=1 1[mix];[mix]loudnorm=I=-14:TP=-1.0:LRA=9,aresample=48000[out]')
subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', mus, '-i', stimme, '-filter_complex', fk, '-map', '[out]',
                '-ar', '48000', '-ac', '2', '-c:a', 'pcm_s24le', ziel], check=True)
# geduckte Musik einzeln fuer die Messung
md = os.path.join(tmp, 'musik_geduckt.wav')
subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', mus, '-i', stimme, '-filter_complex',
                '[0:a][1:a]sidechaincompress=threshold=0.015:ratio=7:attack=20:release=500:makeup=1[o]', '-map', '[o]', md], check=True)

# ---------------------------------------------------------------- Messung
vs, ms = lade(stimme), lade(md)
def rms_db(x):
    return 20 * np.log10(np.sqrt(np.mean(x ** 2)) + 1e-9)
diffs = []
for s, e in bereiche:
    a_, b_ = int(s * SR), int(e * SR)
    diffs.append(rms_db(vs[a_:b_]) - rms_db(ms[a_:b_]))
print('Stimme ueber Musik je Satz:', ' '.join('%.0f' % d for d in diffs))
print('Stimme ueber Musik waehrend Sprache: min %.1f dB, median %.1f dB' % (min(diffs), float(np.median(diffs))))
i, tp = lufs(ziel)
print('Summe: %.1f LUFS, True Peak %s dBTP, Laenge %.2f s' % (i, tp, len(lade(ziel)) / SR))
