/**
 * Die Entscheidungsmaschine.
 *
 * Sie bekommt alles, was ueber Geraet, Leitung, Messung, Szene, Cloud und
 * Besucherwunsch bekannt ist, und liefert genau zwei Werte: WO gerendert wird
 * und WIE GUT.
 *
 * Diese Datei ist bewusst rein: keine Browser-Schnittstellen, keine Zeit, kein
 * Zufall, keine Seiteneffekte. Nur so laesst sich die komplette Geraetematrix
 * pruefen, ohne acht Geraete auf dem Tisch zu haben.
 *
 * Die Regel, die es NICHT gibt: "mobil = niedrig". Ein aktuelles Telefon
 * rechnet mehr als ein Buerorechner von 2016, und ein Buerorechner mit
 * Software-Rasterizer rechnet gar nichts.
 */
import { STRATEGIE_KETTE } from './types.js';
import { stufeIstMindestens, stufeMin, stufeTiefer } from './quality-tiers.js';
/**
 * Punktwert des Geraets. Punktesystem statt Geraeteliste: Kennungen aendern
 * sich staendig, ein gewichtetes Merkmalsmodell altert deutlich besser.
 */
export function geraeteScore(p) {
    let score = 0;
    if (p.webgpu)
        score += 3;
    if (p.webgl2)
        score += 2;
    if (p.cores >= 8)
        score += 2;
    else if (p.cores >= 4)
        score += 1;
    if (p.memoryGB >= 8)
        score += 2;
    else if (p.memoryGB >= 4)
        score += 1;
    if (!p.mobile)
        score += 2;
    if (p.softwareRenderer)
        score -= 5;
    if (p.maxTextureSize > 0 && p.maxTextureSize < 4096)
        score -= 2;
    return score;
}
/**
 * Punktwert der Leitung. Fuer einen Bildstrom aus dem Rechenzentrum zaehlt
 * Latenz mehr als Bandbreite: 40 Mbit/s bei 180 ms Rundlauf fuehlen sich
 * schlechter an als 15 Mbit/s bei 30 ms, weil jede Mausbewegung wartet.
 */
export function netzScore(n) {
    let score = 0;
    if (n.effectiveType === '4g')
        score += 3;
    else if (n.effectiveType === '3g')
        score += 1;
    else if (n.effectiveType === '2g' || n.effectiveType === 'slow-2g')
        score -= 3;
    // Unbekannt (null) gibt 2 Punkte: auf dem Schreibtisch meldet kaum ein
    // Browser etwas, und dort ist die Leitung meist gut. Nicht bestrafen.
    else
        score += 2;
    if (n.downlinkMbps !== null) {
        if (n.downlinkMbps >= 25)
            score += 3;
        else if (n.downlinkMbps >= 10)
            score += 2;
        else if (n.downlinkMbps >= 5)
            score += 1;
        else
            score -= 2;
    }
    if (n.rttMs !== null) {
        if (n.rttMs <= 40)
            score += 3;
        else if (n.rttMs <= 80)
            score += 2;
        else if (n.rttMs <= 150)
            score += 0;
        else
            score -= 3;
    }
    if (!n.webrtc)
        score -= 4;
    if (n.saveData)
        score -= 5;
    return score;
}
/** Ab diesem Netzwert ist ein Bildstrom aus dem Rechenzentrum vertretbar. */
export const NETZ_SCHWELLE_CLOUD = 6;
/** Ab diesem Geraetewert lohnt oertliches Rendern in hoher Stufe. */
export const GERAET_SCHWELLE_STARK = 7;
/** Stufe aus Geraetewert, gedeckelt durch die Messung, falls vorhanden. */
function stufeAusGeraet(geraet, messung) {
    if (!geraet.webgl2 && !geraet.webgpu)
        return 'SAFE';
    if (geraet.softwareRenderer)
        return 'SAFE';
    let stufe;
    const s = geraet.score;
    if (s >= 9)
        stufe = 'ULTRA';
    else if (s >= GERAET_SCHWELLE_STARK)
        stufe = 'HIGH';
    else if (s >= 4)
        stufe = 'MEDIUM';
    else if (s >= 1)
        stufe = 'LOW';
    else
        stufe = 'SAFE';
    // Die Messung schlaegt die Merkmale. Merkmale sind eine Vermutung,
    // gemessene Bildzeiten sind eine Tatsache.
    if (messung && messung.gueltig) {
        let gemessen;
        if (messung.fps >= 58)
            gemessen = 'ULTRA';
        else if (messung.fps >= 50)
            gemessen = 'HIGH';
        else if (messung.fps >= 38)
            gemessen = 'MEDIUM';
        else if (messung.fps >= 25)
            gemessen = 'LOW';
        else
            gemessen = 'SAFE';
        stufe = stufeMin(stufe, gemessen);
    }
    return stufe;
}
/** Stufe, die ein Bildstrom aus dem Rechenzentrum tragen kann. */
function stufeAusNetz(netz) {
    if (netz.score >= 9)
        return 'ULTRA';
    if (netz.score >= NETZ_SCHWELLE_CLOUD)
        return 'HIGH';
    if (netz.score >= 3)
        return 'MEDIUM';
    return 'LOW';
}
function darfCloud(e) {
    if (e.nutzer.cloudAblehnen)
        return false;
    if (e.nutzer.datenSparen || e.netz.saveData)
        return false;
    if (!e.szene.unrealTauglich)
        return false;
    if (!e.szene.erlaubteStrategien.includes('CLOUD_UNREAL'))
        return false;
    if (!e.cloud.eingerichtet || !e.cloud.freiePlaetze)
        return false;
    if (!e.netz.webrtc)
        return false;
    if (e.netz.score < NETZ_SCHWELLE_CLOUD)
        return false;
    // Ueber 150 ms Rundlauf fuehlt sich jede Eingabe zaeh an. Dann lieber
    // oertlich in niedrigerer Stufe als fern in hoher.
    if (e.cloud.latenzMs !== null && e.cloud.latenzMs > 150)
        return false;
    return true;
}
/** Erste Strategie aus der Kette, die erlaubt, moeglich und nicht gescheitert ist. */
function waehleStrategie(e, oertlicheStufe) {
    const gescheitert = new Set(e.gescheitert ?? []);
    const erlaubt = (s) => e.szene.erlaubteStrategien.includes(s) && !gescheitert.has(s);
    const oertlichTaugt = !e.geraet.softwareRenderer && stufeIstMindestens(oertlicheStufe, e.szene.minStufe);
    if (darfCloud(e) && erlaubt('CLOUD_UNREAL')) {
        // Die Cloud ist teuer. Sie lohnt nur, wenn sie wirklich etwas besser macht:
        // entweder das Geraet schafft die Szene oertlich nicht, oder die Szene ist
        // so schwer, dass auch ein starkes Geraet dort verliert.
        const geraetSchwach = !oertlichTaugt || oertlicheStufe === 'LOW' || oertlicheStufe === 'SAFE';
        const szeneSchwer = e.szene.komplexitaet >= 5;
        if (geraetSchwach) {
            return { strategie: 'CLOUD_UNREAL', grund: 'Geraet traegt die Szene oertlich nicht, die Leitung traegt den Bildstrom' };
        }
        // Ein Geraet, das die Szene selbst in hoher Stufe schafft, mietet keine
        // GPU — ausser der Besucher hat die grosse Fassung ausdruecklich verlangt.
        if (szeneSchwer && e.netz.score >= 9 && e.cloudAngefordert === true) {
            return { strategie: 'CLOUD_UNREAL', grund: 'Grosse Fassung angefordert, sehr gute Leitung — ferngerendert ist das Bild besser' };
        }
    }
    if (oertlichTaugt && e.geraet.webgpu && erlaubt('LOCAL_WEBGPU')) {
        return { strategie: 'LOCAL_WEBGPU', grund: 'WebGPU vorhanden und Geraet traegt die Szene' };
    }
    if (oertlichTaugt && e.geraet.webgl2 && erlaubt('LOCAL_WEBGL2')) {
        return { strategie: 'LOCAL_WEBGL2', grund: 'WebGL2 vorhanden und Geraet traegt die Szene' };
    }
    // Letzter Versuch, bevor Safe greift: Cloud auch fuer Szenen, die zwar nicht
    // "unrealTauglich" sind, aber Cloud ausdruecklich erlauben.
    if (darfCloud(e) && erlaubt('CLOUD_UNREAL')) {
        return { strategie: 'CLOUD_UNREAL', grund: 'Oertlich nicht darstellbar, Cloud steht bereit' };
    }
    return { strategie: 'SAFE_MEDIA', grund: 'Kein tragfaehiger Echtzeitweg — vorgerendertes Material' };
}
/**
 * Die eine Entscheidung. Reihenfolge ist Absicht: erst was das Geraet kann,
 * dann wo gerendert wird, dann wie gut — und ganz zuletzt, was der Besucher
 * gesagt hat. Der Besucher gewinnt immer.
 */
export function entscheide(e) {
    const oertlicheStufe = stufeAusGeraet(e.geraet, e.messung);
    const { strategie, grund } = waehleStrategie(e, oertlicheStufe);
    let stufe;
    let begruendung;
    if (strategie === 'SAFE_MEDIA') {
        stufe = 'SAFE';
        begruendung = grund;
    }
    else if (strategie === 'CLOUD_UNREAL') {
        // Bei ferngerendertem Bild bestimmt die Leitung die Stufe, nicht die GPU
        // des Besuchers. Genau das ist der Sinn der Sache.
        stufe = stufeAusNetz(e.netz);
        begruendung = `${grund} (Leitung traegt ${stufe})`;
    }
    else {
        stufe = oertlicheStufe;
        begruendung = grund;
    }
    // Ansagen des Besuchers und des Systems. Sie deckeln, sie heben nie an.
    if (e.nutzer.datenSparen || e.netz.saveData) {
        stufe = stufeMin(stufe, 'MEDIUM');
        begruendung += '; Datensparen gedeckelt auf MEDIUM';
    }
    if (e.nutzer.bewegungReduzieren || e.geraet.reducedMotion) {
        stufe = stufeMin(stufe, 'MEDIUM');
        begruendung += '; reduzierte Bewegung gedeckelt auf MEDIUM';
    }
    if (e.nutzer.stufe !== 'AUTO') {
        stufe = e.nutzer.stufe;
        begruendung = `Vom Besucher fest auf ${stufe} gestellt`;
    }
    // Ein hochaufloesendes Telefon darf nicht allein wegen seiner Anzeige die
    // hoechste Stufe bekommen — die Fuellrate ist dort der Engpass, nicht die
    // Geometrie. Gilt nur oertlich; im Cloud-Strom rechnet das Telefon nichts.
    if (e.geraet.mobile &&
        (strategie === 'LOCAL_WEBGPU' || strategie === 'LOCAL_WEBGL2') &&
        stufe === 'ULTRA' &&
        e.nutzer.stufe === 'AUTO') {
        stufe = 'HIGH';
        begruendung += '; mobil oertlich hoechstens HIGH (Fuellrate)';
    }
    // Die Szene hat ein Mindestmass. Wird es unterschritten, ist vorgerendertes
    // Material ehrlicher als eine Szene, die ihre Wirkung verliert.
    if (strategie !== 'SAFE_MEDIA' && !stufeIstMindestens(stufe, e.szene.minStufe)) {
        return {
            strategie: 'SAFE_MEDIA',
            stufe: 'SAFE',
            begruendung: `Szene "${e.szene.id}" verlangt mindestens ${e.szene.minStufe}, erreichbar waere nur ${stufe}`,
            naechsteStrategie: 'SAFE_MEDIA',
        };
    }
    return {
        strategie,
        stufe,
        begruendung,
        naechsteStrategie: STRATEGIE_KETTE[strategie],
    };
}
/**
 * Naechster Versuch, wenn die gewaehlte Strategie zur Laufzeit scheitert —
 * WebRTC bricht ab, der GPU-Platz faellt weg, ein Shader uebersetzt nicht.
 * Der Besucher sieht davon nichts: kein Fehlerbild, nur eine andere Fassung.
 */
export function naechsterVersuch(e, gescheiteteStrategie) {
    const gescheitert = [...(e.gescheitert ?? []), gescheiteteStrategie];
    const neu = entscheide({ ...e, gescheitert });
    // Sicherheitsnetz gegen eine Entscheidung, die dieselbe Strategie erneut
    // waehlt: dann eine Stufe tiefer in der Kette weiter.
    if (neu.strategie === gescheiteteStrategie) {
        const ersatz = STRATEGIE_KETTE[gescheiteteStrategie];
        return {
            strategie: ersatz,
            stufe: ersatz === 'SAFE_MEDIA' ? 'SAFE' : stufeTiefer(neu.stufe),
            begruendung: `${gescheiteteStrategie} gescheitert, weiter mit ${ersatz}`,
            naechsteStrategie: STRATEGIE_KETTE[ersatz],
        };
    }
    return neu;
}
//# sourceMappingURL=decision-engine.js.map