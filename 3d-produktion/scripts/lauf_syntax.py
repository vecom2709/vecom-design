# -*- coding: utf-8 -*-
"""Headless: Python-Dateien nur uebersetzen (Syntaxpruefung), nichts ausfuehren.

Aufruf (Dateinamen ohne Pfad; gesucht wird in den Blender-Skripten und in
den Unreal-Skripten -- Pfade mit Leerzeichen kommen ueber -Werte nicht
heil an):
  werkzeug\\blender-lauf.ps1 -Skript lauf_syntax.py -Log syntax.log -Werte villa_aussen.py,v05_oberflaeche.py
"""
import os
import py_compile
import sys

ORTE = (os.path.dirname(os.path.abspath(__file__)),
        r'C:\Users\manue\Documents\Unreal Projects\VecomVilla\Content\Python')
argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
namen = []
for a in argv:
    namen += [t for t in a.split(',') if t]
for n in namen:
    pfad = next((os.path.join(o, n) for o in ORTE if os.path.exists(os.path.join(o, n))), None)
    if pfad is None:
        print('[syntax] FEHLT  %s' % n)
        continue
    try:
        py_compile.compile(pfad, doraise=True)
        print('[syntax] OK     %s' % pfad)
    except py_compile.PyCompileError as f:
        print('[syntax] FEHLER %s\n%s' % (pfad, f))
print('[syntax] FERTIG')
