# -*- coding: utf-8 -*-
"""Den fertigen Ablauf-Film auf die Website bringen (Uwe, 26.09.2026:
"Wenn das Video fertig ist in 3 Sprachen, setze es live").

Aufruf (auf dem Rechner, auf dem gerendert wurde; braucht ffmpeg und node):
    python 3d-produktion/film-ablauf/einbauen.py <render-ordner> [--live]

<render-ordner> enthaelt je Sprache einen Unterordner mit der PNG-Folge:
    unreal_it/ unreal_de/ unreal_en/     (Unreal, Movie Render Queue)
    oder cycles_it/ cycles_de/ cycles_en/ (Rueckfallweg Blender)

Was passiert -- und was vorher geprueft wird:
  1. Jede Sprache muss VOLLSTAENDIG sein (2160 Bilder, keine Luecke). Ein
     halber Film geht nicht online; dann bricht das Skript ab, bevor es
     irgendetwas anfasst.
  2. ffmpeg: H.264, CRF 19, yuv420p, +faststart, ohne Tonspur -> video/ablauf-<spr>.mp4
     (dieselben Dateinamen wie bisher: Die Seite bindet sie schon ein).
  3. Plakatbild aus der ersten Station (Bild 120) -> assets/img/video-ablauf-<spr>.webp
  4. Die Texte unter dem Video: "2:48 · mit Ton" und "mit genau den
     Bildschirmen" stimmen fuer den neuen Film nicht mehr -> ersetzt.
  5. node build.mjs (erzeugt /de/, /en/ und die Versionsnummern neu).
  6. Mit --live: Commit und Push auf main -> der Deploy stellt es online.
"""
import glob, json, os, re, subprocess, sys

WURZEL = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
BILDER = 2160
PLAKAT_BILD = 120

TEXTE = {
    'it': {
        'alt': ['Tutto il percorso, dal primo messaggio al sito finito — con le schermate esatte che vedrà anche Lei. Alla fine sa a cosa va incontro.',
                'Tutto il percorso · 2:48 · con audio'],
        'neu': ['Tutto il percorso, dal primo messaggio al sito online: ogni passo e le scelte che ha — dominio, e-mail, assistenza. Alla fine sa a cosa va incontro.',
                'Tutto il percorso · 1:30'],
    },
    'de': {
        'alt': ['Der ganze Weg von der ersten Nachricht bis zur fertigen Seite — mit genau den Bildschirmen, die Sie dabei zu sehen bekommen. Danach wissen Sie, worauf Sie sich einlassen.',
                'Der ganze Ablauf · 2:54 · mit Ton'],
        'neu': ['Der ganze Weg von der ersten Nachricht bis zur Seite online: jeder Schritt und Ihre Wahlmöglichkeiten — Domain, E-Mail, Betreuung. Danach wissen Sie, worauf Sie sich einlassen.',
                'Der ganze Ablauf · 1:30'],
    },
    'en': {
        'alt': ['The whole path from your first message to the finished site — with the very screens you’ll see along the way. Afterwards you know what you’re getting into.',
                'The whole process · 2:33 · with sound'],
        'neu': ['The whole path from your first message to your site going live: every step and your options — domain, email, ongoing care. Afterwards you know what you’re getting into.',
                'The whole process · 1:30'],
    },
}


def folge(ordner):
    """Die PNG-Folge eines Ordners: Muster fuer ffmpeg, Startnummer, Anzahl."""
    dateien = sorted(glob.glob(os.path.join(ordner, '*.png')))
    if not dateien:
        raise SystemExit('Keine Bilder in %s' % ordner)
    m = re.match(r'^(.*?)(\d+)\.png$', os.path.basename(dateien[0]))
    if not m:
        raise SystemExit('Unerwartete Dateinamen in %s: %s' % (ordner, dateien[0]))
    vor, ziffern = m.group(1), m.group(2)
    nummern = sorted(int(re.match(r'^.*?(\d+)\.png$', os.path.basename(d)).group(1)) for d in dateien)
    luecken = [n for n in range(nummern[0], nummern[0] + len(nummern)) if n not in set(nummern)]
    if len(nummern) != BILDER or luecken:
        raise SystemExit('%s: %d von %d Bildern%s -- nicht vollstaendig, nichts geaendert.'
                         % (ordner, len(nummern), BILDER, ', Luecken z. B. bei %s' % luecken[:5] if luecken else ''))
    return os.path.join(ordner, '%s%%0%dd.png' % (vor, len(ziffern))), nummern[0]


def lauf(cmd, **kw):
    print('>', ' '.join(cmd) if isinstance(cmd, list) else cmd)
    subprocess.run(cmd, check=True, **kw)


def main():
    if len(sys.argv) < 2:
        raise SystemExit(__doc__)
    quelle = os.path.abspath(sys.argv[1])
    live = '--live' in sys.argv

    # 1. alles pruefen, bevor irgendetwas geaendert wird
    folgen = {}
    for spr in ('it', 'de', 'en'):
        kand = [os.path.join(quelle, p + spr) for p in ('unreal_', 'cycles_')]
        ordner = next((k for k in kand if os.path.isdir(k)), None)
        if not ordner:
            raise SystemExit('Kein Ordner fuer %s (%s) -- nichts geaendert.' % (spr, ' / '.join(kand)))
        folgen[spr] = folge(ordner)
    for spr, t in TEXTE.items():
        js = open(os.path.join(WURZEL, 'assets', 'js', 'i18n-%s.js' % spr), encoding='utf-8').read()
        for a in t['alt']:
            if a not in js and not any(n in js for n in t['neu']):
                raise SystemExit('Text in i18n-%s.js nicht gefunden (inzwischen geaendert?): %s' % (spr, a[:60]))

    # 2. + 3. Filme und Plakate
    for spr, (muster, start) in folgen.items():
        mp4 = os.path.join(WURZEL, 'video', 'ablauf-%s.mp4' % spr)
        lauf(['ffmpeg', '-y', '-loglevel', 'error', '-framerate', '24', '-start_number', str(start), '-i', muster,
              '-c:v', 'libx264', '-preset', 'slow', '-crf', '19', '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
              '-an', mp4])
        print('  %s: %.1f MB' % (os.path.basename(mp4), os.path.getsize(mp4) / 1e6))
        plakat = muster % (start + PLAKAT_BILD - 1)
        lauf(['ffmpeg', '-y', '-loglevel', 'error', '-i', plakat, '-vf', 'scale=960:540', '-c:v', 'libwebp',
              '-quality', '80', os.path.join(WURZEL, 'assets', 'img', 'video-ablauf-%s.webp' % spr)])

    # 4. Texte (Woerterbuecher und der italienische Standardtext in index.html)
    for spr, t in TEXTE.items():
        for datei in [os.path.join(WURZEL, 'assets', 'js', 'i18n-%s.js' % spr)] + ([os.path.join(WURZEL, 'index.html')] if spr == 'it' else []):
            s = open(datei, encoding='utf-8').read()
            for a, n in zip(t['alt'], t['neu']):
                s = s.replace(a, n)
            open(datei, 'w', encoding='utf-8').write(s)

    # 5. Seiten neu erzeugen
    lauf(['node', 'build.mjs'], cwd=WURZEL)

    # 6. online
    if live:
        lauf(['git', 'add', 'video/ablauf-it.mp4', 'video/ablauf-de.mp4', 'video/ablauf-en.mp4',
              'assets/img/video-ablauf-it.webp', 'assets/img/video-ablauf-de.webp', 'assets/img/video-ablauf-en.webp',
              'assets/js', 'index.html', 'de', 'en', '*.html'], cwd=WURZEL)
        lauf(['git', 'commit', '-m', 'Ablauf-Film: neue 3D-Fassung (Blender/Unreal) in drei Sprachen'], cwd=WURZEL)
        lauf(['git', 'push', 'origin', 'HEAD:main'], cwd=WURZEL)
        print('Gepusht -- in etwa vier Minuten live. Danach den Deploy pruefen (CLAUDE.md, Regel 4).')
    else:
        print('Fertig vorbereitet. Ansehen, dann mit --live veroeffentlichen.')


if __name__ == '__main__':
    main()
