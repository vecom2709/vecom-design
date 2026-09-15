/**
 * Der Leistungsregler.
 *
 * Er beobachtet die Bildzeiten und regelt nach. Wichtig ist dabei weniger das
 * Herunterschalten als das NICHT-Pendeln: Ein System, das zwischen zwei Stufen
 * hin und her springt, sieht schlechter aus als eines, das dauerhaft eine
 * Stufe tiefer laeuft. Deshalb gleitender Mittelwert, Sperrzeit nach jeder
 * Aenderung, Hysterese zwischen Herauf- und Herunterschwelle, und eine Stufe,
 * die zweimal zusammengebrochen ist, wird nicht mehr angeboten.
 *
 * Bevor eine ganze Qualitaetsstufe faellt, werden einzelne Posten abgeschaltet
 * — in der Reihenfolge ihres Verhaeltnisses von gewonnener Leistung zu
 * sichtbarem Verlust. Die Aufloesung zuerst, weil sie am meisten bringt und am
 * wenigsten auffaellt; die Texturen zuletzt, weil sie das Material tragen.
 *
 * Auch diese Datei ist rein: die Zeit kommt von aussen herein, damit sich
 * Stunden simulieren lassen, ohne zu warten.
 */
import { QUALITY_TIERS, stufeHoeher, stufeTiefer } from './quality-tiers.js';
export const STANDARD_PARAMETER = {
    einschwingenMs: 4000,
    fensterGroesse: 90,
    fpsNotfall: 30,
    fpsReduzieren: 40,
    fpsErhoehen: 55,
    stabilMs: 10_000,
    sperrzeitMs: 5_000,
    maxVersuche: 2,
};
/**
 * Die Sparleiter innerhalb einer Stufe. Index 0 heisst: nichts gespart.
 * Jeder weitere Schritt nimmt genau einen Posten zurueck.
 */
export const SPARSCHRITTE = [
    'nichts',
    'aufloesung',
    'partikel',
    'schatten',
    'reflexionen',
    'nachbearbeitung',
    'nebenanimationen',
    'texturen',
    'lod',
];
export class PerformanceGovernor {
    p;
    jetzt;
    stufe;
    sparIndex = 0;
    feinSkala = 1;
    framezeiten = [];
    startZeit;
    letzteAenderung = 0;
    gutSeit = 0;
    fehlversuche = new Map();
    manuell = false;
    constructor(startStufe, optionen) {
        this.p = { ...STANDARD_PARAMETER, ...optionen?.parameter };
        this.jetzt = optionen?.zeitquelle ?? (() => performance.now());
        this.stufe = startStufe;
        this.startZeit = this.jetzt();
    }
    get zustand() {
        return { stufe: this.stufe, sparIndex: this.sparIndex, feinSkala: this.feinSkala };
    }
    get aktuellerSparschritt() {
        return SPARSCHRITTE[Math.min(this.sparIndex, SPARSCHRITTE.length - 1)] ?? 'nichts';
    }
    /** Einstellungen der Stufe, verrechnet mit dem, was gerade gespart wird. */
    get einstellungen() {
        const basis = QUALITY_TIERS[this.stufe];
        const s = {
            ...basis,
            postProcessing: { ...basis.postProcessing },
            resolutionScale: basis.resolutionScale * this.feinSkala,
        };
        const i = this.sparIndex;
        if (i >= 2)
            s.particleBudget = basis.particleBudget * 0.35;
        if (i >= 3)
            s.shadowMapSize = 0;
        if (i >= 4)
            s.postProcessing.ambientOcclusion = false;
        if (i >= 5) {
            s.postProcessing.bloom = false;
            s.postProcessing.depthOfField = false;
            s.postProcessing.volumetrics = false;
        }
        if (i >= 6)
            s.animationQuality = 'minimal';
        if (i >= 7)
            s.maxTextureSize = Math.max(256, Math.floor(basis.maxTextureSize / 2));
        if (i >= 8)
            s.lodDistanceScale = basis.lodDistanceScale * 0.6;
        return s;
    }
    /** Feste Wahl des Besuchers. 'AUTO' gibt die Regelung wieder frei. */
    setzeManuell(stufe, automatischeStufe) {
        if (stufe === 'AUTO') {
            this.manuell = false;
            this.stufe = automatischeStufe;
        }
        else {
            this.manuell = true;
            this.stufe = stufe;
        }
        this.sparIndex = 0;
        this.feinSkala = 1;
        this.framezeiten.length = 0;
    }
    /**
     * Einmal pro Bild aus dem Renderloop. Dieser Pfad laeuft 60-mal je Sekunde,
     * deshalb ohne neue Objekte und ohne Sortieren im Normalfall.
     */
    bildGemeldet(deltaMs) {
        const jetzt = this.jetzt();
        if (jetzt - this.startZeit < this.p.einschwingenMs)
            return { art: 'unveraendert' };
        this.framezeiten.push(deltaMs);
        if (this.framezeiten.length > this.p.fensterGroesse)
            this.framezeiten.shift();
        if (this.framezeiten.length < this.p.fensterGroesse)
            return { art: 'unveraendert' };
        const m = this.messwerte();
        // Eine feste Wahl wird geachtet — ausser es wird unbedienbar.
        if (this.manuell && m.fps >= 20)
            return { art: 'unveraendert' };
        if (jetzt - this.letzteAenderung < this.p.sperrzeitMs)
            return { art: 'unveraendert' };
        if (m.fps < this.p.fpsNotfall)
            return this.herabstufen(jetzt);
        if (m.fps < this.p.fpsReduzieren)
            return this.sparen(jetzt);
        if (m.fps > this.p.fpsErhoehen) {
            if (this.gutSeit === 0) {
                this.gutSeit = jetzt;
                return { art: 'unveraendert' };
            }
            if (jetzt - this.gutSeit > this.p.stabilMs)
                return this.zurueckgeben(jetzt);
            return { art: 'unveraendert' };
        }
        this.gutSeit = 0;
        return { art: 'unveraendert' };
    }
    messwerte() {
        const n = this.framezeiten.length;
        if (n === 0)
            return { fps: 0, frameTimeMs: 0, frameTimeP95: 0, anzahl: 0 };
        let summe = 0;
        for (const t of this.framezeiten)
            summe += t;
        const mittel = summe / n;
        const sortiert = [...this.framezeiten].sort((a, b) => a - b);
        const p95 = sortiert[Math.min(n - 1, Math.floor(n * 0.95))] ?? mittel;
        return { fps: 1000 / mittel, frameTimeMs: mittel, frameTimeP95: p95, anzahl: n };
    }
    /** Meldung der Grenze: in dieser Stufe ist etwas zusammengebrochen. */
    meldeFehler() {
        this.fehlversuche.set(this.stufe, this.p.maxVersuche);
        return this.herabstufen(this.jetzt());
    }
    sparen(jetzt) {
        // Erst die Aufloesung feiner zuruecknehmen, dann die naechste Stellschraube.
        if (this.sparIndex === 0 || (this.sparIndex === 1 && this.feinSkala > 0.6)) {
            this.sparIndex = 1;
            this.feinSkala = Math.max(0.6, Math.round((this.feinSkala - 0.1) * 10) / 10);
            this.nachAenderung(jetzt);
            return { art: 'gespart', schritt: 'aufloesung', stufe: this.stufe };
        }
        if (this.sparIndex < SPARSCHRITTE.length - 1) {
            this.sparIndex += 1;
            this.nachAenderung(jetzt);
            return { art: 'gespart', schritt: this.aktuellerSparschritt, stufe: this.stufe };
        }
        // Alles gespart und immer noch zu langsam: jetzt faellt die Stufe.
        return this.herabstufen(jetzt);
    }
    zurueckgeben(jetzt) {
        if (this.sparIndex > 1) {
            const vorher = this.aktuellerSparschritt;
            this.sparIndex -= 1;
            this.nachAenderung(jetzt);
            return { art: 'zurueckgenommen', schritt: vorher, stufe: this.stufe };
        }
        if (this.sparIndex === 1 && this.feinSkala < 1) {
            this.feinSkala = Math.min(1, Math.round((this.feinSkala + 0.1) * 10) / 10);
            if (this.feinSkala >= 1)
                this.sparIndex = 0;
            this.nachAenderung(jetzt);
            return { art: 'zurueckgenommen', schritt: 'aufloesung', stufe: this.stufe };
        }
        const neu = stufeHoeher(this.stufe);
        if (neu === this.stufe)
            return { art: 'unveraendert' };
        if ((this.fehlversuche.get(neu) ?? 0) >= this.p.maxVersuche)
            return { art: 'unveraendert' };
        const von = this.stufe;
        this.stufe = neu;
        this.sparIndex = 0;
        this.feinSkala = 1;
        this.nachAenderung(jetzt);
        return { art: 'heraufgestuft', von, auf: neu };
    }
    herabstufen(jetzt) {
        const neu = stufeTiefer(this.stufe);
        if (neu === this.stufe)
            return { art: 'unveraendert' };
        this.fehlversuche.set(this.stufe, (this.fehlversuche.get(this.stufe) ?? 0) + 1);
        const von = this.stufe;
        this.stufe = neu;
        this.sparIndex = 0;
        this.feinSkala = 1;
        this.nachAenderung(jetzt);
        return { art: 'herabgestuft', von, auf: neu };
    }
    nachAenderung(jetzt) {
        this.letzteAenderung = jetzt;
        this.gutSeit = 0;
        this.framezeiten.length = 0;
    }
}
//# sourceMappingURL=performance-governor.js.map