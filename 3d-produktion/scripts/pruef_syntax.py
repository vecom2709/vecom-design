# -*- coding: utf-8 -*-
"""Uebersetzt die Unreal-Skripte, ohne Unreal zu starten.

WARUM DAS EIN EIGENER SCHRITT IST
Ein Tippfehler in v05_oberflaeche.py kostet sonst einen kompletten
Kopflos-Lauf: Unreal startet, laedt das Projekt, faellt nach zwei Minuten
ueber eine fehlende Klammer und muss von vorne. Der Uebersetzer von
Blender ist derselbe CPython -- er findet denselben Fehler in einer
Sekunde. Die unreal-Bibliothek wird dabei nicht gebraucht: compile()
fuehrt nichts aus.
"""
import os
import sys

UE = r'C:\Users\manue\Documents\Unreal Projects\VecomVilla\Content\Python'
HIER = os.path.dirname(os.path.abspath(__file__))

fehler = 0
for ordner in (UE, HIER):
    for name in sorted(os.listdir(ordner)):
        if not name.endswith('.py'):
            continue
        pfad = os.path.join(ordner, name)
        try:
            compile(open(pfad, encoding='utf-8').read(), pfad, 'exec')
        except SyntaxError as f:
            fehler += 1
            print('[pruef] FEHLER %s Zeile %s: %s'
                  % (name, f.lineno, f.msg))
            if f.text:
                print('[pruef]        %s' % f.text.rstrip())
print('[pruef] %s' % ('%d Datei(en) mit Syntaxfehler' % fehler
                      if fehler else 'Alle Dateien uebersetzen sauber.'))
print('[pruef] FERTIG')
