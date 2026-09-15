/**
 * Was fuer eine Grafik steckt da drin?
 *
 * Kerne und Speicher sagen nichts ueber die Grafik — und die entscheidet bei
 * Echtzeit-3D alles. Dieses Wissen stammt nicht aus einem Datenblatt, sondern
 * aus einem Ausfall auf einer echten Maschine: Ein i7-4600U meldet vier
 * Threads und landete damit auf einer mittleren Stufe, mit Bloom und erhoehter
 * Pixeldichte. Die Grafik darin ist eine Intel HD 4400 von 2013. Am 14.09.2026
 * blieb die Seite auf genau diesem Geraet so lange haengen, dass der Browser
 * ueber eine halbe Minute kein Skript mehr ausfuehren konnte.
 *
 * Uebernommen aus assets/js/world/quality.js von vecom-design.it — dort teuer
 * bezahlt, hier fuer jedes weitere Projekt nutzbar.
 */
/** Software-Rasterizer rechnen auf der CPU. Jedes Bild kostet Zehntelsekunden. */
const SOFTWARE = /swiftshader|llvmpipe|software|basic render|microsoft basic|mesa offscreen/i;
/**
 * Welche Zahl "alt" bedeutet, haengt davon ab, wie viele Stellen sie hat.
 * Intel hat die Zaehlweise mittendrin gewechselt: Die alten Generationen
 * tragen vier Stellen (HD 4400, 2013), die neueren drei (UHD 620, 630). Eine
 * einzige Schwelle kann das nicht treffen — ein Vergleich von 4400 gegen 600
 * haelt eine Grafik von 2013 fuer modern.
 *
 *   vier Stellen, bis 6000   -> alt   (HD 2000 … HD 6000, 2011–2015)
 *   drei Stellen, bis 630    -> alt   (HD 510 … UHD 630, 2015–2020)
 *   Iris, Xe, Arc            -> reicht
 *
 * Keine Zahl hinter "Graphics" heisst aelter als jede Zahl.
 */
export function grafikZuSchwach(kennung) {
    if (!kennung)
        return false; // keine Auskunft -> nicht raten
    if (SOFTWARE.test(kennung))
        return true;
    if (/\b(iris|arc)\b|\bxe\b/i.test(kennung))
        return false;
    const m = kennung.match(/\b(?:hd|uhd)\s*graphics(?:\s+(\d{3,4}))?/i);
    if (!m)
        return false; // keine integrierte Intel-Grafik
    const zahl = m[1];
    if (!zahl)
        return true; // "HD Graphics" ohne Zahl
    const n = Number(zahl);
    return zahl.length === 4 ? n <= 6000 : n <= 630;
}
/** Erkennt einen Software-Rasterizer an der Kennung. */
export function istSoftwareRasterizer(kennung) {
    return kennung ? SOFTWARE.test(kennung) : false;
}
//# sourceMappingURL=gpu-kennung.js.map