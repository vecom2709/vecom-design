# TikTok / Reels / Shorts — drei Clips

720 × 1280 (9:16), ohne Ton, je 14–17 Sekunden. Passt ohne Zuschnitt für
TikTok, Instagram Reels, YouTube Shorts und Facebook Reels.

| Datei | Aufhänger | Länge |
|---|---|---|
| `clip1-fehler.mp4` | „Deine Website erklärt, was du machst" | 14 s |
| `clip2-preis.mp4` | „Was kostet eine Website?" | 14 s |
| `clip3-ablauf.mp4` | „In 2 Wochen online" | 17 s |

## Bildunterschriften zum Kopieren

**Clip 1**
Die meisten Websites erklären, was jemand macht. Fast keine sagt, warum man
dort kaufen soll. Genau daran scheitern die meisten Seiten von Selbstständigen.
Was steht bei dir oben auf der Startseite? 👇
#webdesign #website #selbststaendig #handwerk #kleinunternehmen #onlinemarketing

**Clip 2**
„Kommt drauf an" ist keine Antwort. Bei mir stehen die Preise öffentlich auf
der Seite: ab 499 € einmalig, Festpreis vor Beginn, der Code gehört dir.
Fragen? Schreib mir.
#website #preise #webdesign #transparenz #selbststaendigkeit #gruenden

**Clip 3**
Von der ersten Nachricht bis online: zwei Wochen. Tag 1 reden wir, Tag 3 siehst
du den ersten Entwurf, Woche 2 ist deine Seite live. Kein Baukasten, kein
Abo-Zwang, ein Ansprechpartner.
#webdesign #website #ablauf #kmu #handwerk #gastronomie

## Veröffentlichen

Automatisch posten kann ich nicht — dafür bräuchte ich Zugang zu deinem
TikTok-Konto, und den solltest du keiner Automatik geben. Der praktikable Weg:

1. In TikTok Studio (tiktokstudio.com) hochladen und **terminieren** —
   bis zu zehn Tage im Voraus. Damit planst du einmal pro Woche und bist fertig.
2. Dieselbe Datei bei Instagram als Reel und bei YouTube als Short hochladen.
   Kein Zuschnitt nötig, das Format passt.
3. Musik direkt in der App darüberlegen: Die Clips sind stumm, damit du einen
   aktuellen Sound aus der TikTok-Bibliothek wählen kannst. Das ist besser für
   die Reichweite als eine mitgelieferte Tonspur — und lizenzrechtlich sauber.

**Rhythmus:** Ein Clip pro Woche, immer derselbe Wochentag. Drei Clips reichen
für den ersten Monat; danach neue aus derselben Vorlage.

## Neue Clips bauen

Texte stehen in `tiktok.html`, jeder Clip ist ein Block mit `data-spot`.
Aufnehmen:

    node tools/record-tiktok.mjs 1
    ffmpeg -i video/*.webm -c:v libx264 -preset veryfast -crf 22 \
           -pix_fmt yuv420p -movflags +faststart -r 30 video/clip1-fehler.mp4

Für italienische oder englische Fassungen die Texte im Block austauschen und
unter eigenem Namen speichern.
