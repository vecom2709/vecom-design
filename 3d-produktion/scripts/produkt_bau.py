"""produkt_bau.py -- Einstieg fuer die Produktdemos (Blender, Hintergrund).

  blender -b -P produkt_bau.py -- <was>[,web]
  was = wein | ... (je Demo ein Modul pr_<was>.py)
"""
import os, sys, importlib
HIER = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HIER)
argv = sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else []
argv = [t for a in argv for t in a.split(',') if t]
WAS = argv[0] if argv else 'wein'
WEB = 'web' in argv[1:]
import pr_basis as B
modul = importlib.import_module(f'pr_{WAS}')
B.leeren()
modul.bauen(web=WEB)
B.log('FERTIG', WAS, 'web' if WEB else 'voll')
