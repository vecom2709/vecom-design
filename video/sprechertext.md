# Sprechertext zum Erklärvideo (66 Sekunden)

Zum Selbsteinsprechen oder für eine Sprecherin. Die Zeiten passen zu den Szenen
im Video; ruhig sprechen, lieber etwas langsamer als schneller.

| ab | Text |
|---|---|
| 0:00 | Wie deine Website entsteht — in vier Schritten. |
| 0:05 | Die meisten Websites erklären, was jemand macht. Aber kaum eine sagt, warum man dort kaufen soll. Genau das ist der Unterschied zwischen einer Seite, die es gibt, und einer, die Kunden bringt. |
| 0:12 | Schritt eins: Wir reden. Eine Stunde, am Telefon oder bei dir. Was verkaufst du, an wen — und was passiert heute stattdessen? Daraus entsteht dein Angebot. Kostenlos, und für uns verbindlich. |
| 0:20 | Schritt zwei: Du siehst den ersten Bildschirm. Farbe, Schrift, Aufbau. Freigegeben wird, solange Ändern noch nichts kostet — nicht erst am Ende. |
| 0:28 | Schritt drei: Wir bauen, du schaust zu. Abschnitt für Abschnitt, jeder wirklich fertig, bevor der nächste beginnt. Über einen privaten Link siehst du die Seite wachsen. |
| 0:36 | Schritt vier: Online. Und danach nicht allein. Domain, E-Mail, Rechtstexte. Backups und kleine Änderungen übernehmen wir — du kümmerst dich um dein Geschäft. |
| 0:44 | Der Code gehört dir. Kein Cookie-Banner. Ein Ansprechpartner. |
| 0:52 | Ab 499 Euro einmalig. Angebot und Erstgespräch sind kostenlos. |
| 0:58 | vecom-design.it — schreib mir. |

## Ton hinzufügen

Sprachaufnahme als `stimme.m4a` neben das Video legen, dann:

    ffmpeg -i video/erklaervideo.mp4 -i stimme.m4a \
           -c:v copy -c:a aac -shortest video/erklaervideo-ton.mp4

Mit Musik zusätzlich (Musik leiser mischen):

    ffmpeg -i video/erklaervideo.mp4 -i stimme.m4a -i musik.mp3 \
      -filter_complex "[2:a]volume=0.12[m];[1:a][m]amix=inputs=2:duration=first[a]" \
      -map 0:v -map "[a]" -c:v copy -c:a aac video/erklaervideo-ton.mp4

Musik nur mit Lizenz für gewerbliche Nutzung verwenden.
