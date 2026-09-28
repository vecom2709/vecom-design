"""Hoert jeden Satz mit Whisper ab und vergleicht mit dem Soll-Text."""
import sys, json, glob, os, re, difflib
from faster_whisper import WhisperModel
m = WhisperModel('medium', device='cpu', compute_type='int8')
def norm(s): return re.sub(r'[^\w ]', '', s.lower().replace('…',' ')).split()
for auftrag in sys.argv[2:]:
    a = json.load(open(auftrag, encoding='utf-8'))
    fs = sorted(glob.glob(os.path.join(sys.argv[1], a['name'] + '-[0-9][0-9].*')))
    for f, soll in zip(fs, a['saetze']):
        seg, _ = m.transcribe(f, language=a['sprache'], beam_size=5)
        ist = ' '.join(s.text.strip() for s in seg)
        q = difflib.SequenceMatcher(None, norm(soll), norm(ist)).ratio()
        print('%s %s %.2f | %s || %s' % ('OK ' if q > 0.85 else '?? ', os.path.basename(f), q, soll, ist), flush=True)
