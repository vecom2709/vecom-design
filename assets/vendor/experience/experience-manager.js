/**
 * Der AdaptiveExperienceManager — die einzige Stelle, die weiss, was gerade
 * gilt.
 *
 * Er kennt weder React noch Three.js noch Unreal. Er ermittelt, entscheidet,
 * regelt nach und meldet. Wer die Werte umsetzt, ist seine Sache. Genau
 * deshalb laesst sich dieselbe Plattform unter einer Next.js-Seite, unter
 * einer gewachsenen Vanilla-Seite oder in einem Shop betreiben.
 */
import { entscheide, naechsterVersuch } from './decision-engine.js';
import { PerformanceGovernor } from './performance-governor.js';
import { ermittleDeviceProfile } from './device-profile.js';
import { ermittleNetworkProfile } from './network-profile.js';
import { messeLeistung } from './benchmark.js';
const SPEICHER_SCHLUESSEL = 'vecom-experience-einstellungen';
const CLOUD_AUS = { eingerichtet: false, freiePlaetze: false, latenzMs: null };
const NUTZER_VORGABE = {
    stufe: 'AUTO',
    datenSparen: false,
    bewegungReduzieren: false,
    cloudAblehnen: false,
};
export class AdaptiveExperienceManager {
    o;
    geraet = null;
    netz = null;
    messung = null;
    cloud = CLOUD_AUS;
    nutzer = { ...NUTZER_VORGABE };
    gescheitert = [];
    cloudAngefordert = false;
    entscheidung = null;
    regler = null;
    zuhoerer = new Set();
    constructor(optionen) {
        this.o = optionen;
        this.nutzer = { ...NUTZER_VORGABE, ...this.ladeNutzerWahl() };
    }
    /**
     * Ermitteln und erstmalig entscheiden.
     *
     * Reihenfolge ist Absicht: Geraet und Netz laufen nebeneinander, die Messung
     * erst danach und nur, wenn sie ueberhaupt etwas aendern kann. Auf einem
     * Geraet ohne WebGL2 ist jede Messung verschwendete Zeit.
     */
    async initialisieren() {
        const [geraet, netz] = await Promise.all([
            ermittleDeviceProfile(),
            ermittleNetworkProfile(this.o.latenzUrl !== undefined ? { latenzUrl: this.o.latenzUrl } : {}),
        ]);
        this.geraet = geraet;
        this.netz = netz;
        if (this.o.cloudPruefung) {
            try {
                this.cloud = await this.o.cloudPruefung();
            }
            catch {
                this.cloud = CLOUD_AUS;
            }
        }
        const messenLohnt = !this.o.ohneMessung && (geraet.webgl2 || geraet.webgpu) && !geraet.softwareRenderer;
        this.messung = messenLohnt ? await messeLeistung() : null;
        this.entscheidung = entscheide(this.eingang());
        this.regler = new PerformanceGovernor(this.entscheidung.stufe);
        this.melde();
        return this.entscheidung;
    }
    eingang() {
        if (!this.geraet || !this.netz)
            throw new Error('initialisieren() wurde noch nicht aufgerufen');
        return {
            geraet: this.geraet,
            netz: this.netz,
            messung: this.messung,
            szene: this.o.szene,
            cloud: this.cloud,
            nutzer: this.nutzer,
            gescheitert: this.gescheitert,
            cloudAngefordert: this.cloudAngefordert,
        };
    }
    get zustand() {
        if (!this.entscheidung || !this.regler)
            throw new Error('initialisieren() fehlt');
        return {
            entscheidung: this.entscheidung,
            einstellungen: this.regler.einstellungen,
            geraet: this.geraet,
            netz: this.netz,
            messung: this.messung,
            nutzer: this.nutzer,
        };
    }
    abonnieren(fn) {
        this.zuhoerer.add(fn);
        if (this.entscheidung && this.regler)
            fn(this.zustand);
        return () => {
            this.zuhoerer.delete(fn);
        };
    }
    /** Einmal je Bild aus dem Renderloop. Meldet nur bei echter Aenderung. */
    bildGemeldet(deltaMs) {
        if (!this.regler)
            return { art: 'unveraendert' };
        const ereignis = this.regler.bildGemeldet(deltaMs);
        if (ereignis.art !== 'unveraendert' && this.entscheidung) {
            this.entscheidung = { ...this.entscheidung, stufe: this.regler.zustand.stufe };
            this.melde();
        }
        return ereignis;
    }
    /**
     * Die gewaehlte Strategie ist zusammengebrochen: WebRTC abgerissen, GPU-Platz
     * verloren, Shader nicht uebersetzt, GLB nicht geladen. Der Besucher sieht
     * davon nichts — er bekommt die naechste Fassung.
     */
    meldeStrategieFehler() {
        if (!this.entscheidung)
            throw new Error('initialisieren() fehlt');
        const alt = this.entscheidung.strategie;
        this.gescheitert.push(alt);
        this.entscheidung = naechsterVersuch(this.eingang(), alt);
        this.regler = new PerformanceGovernor(this.entscheidung.stufe);
        this.melde();
        return this.entscheidung;
    }
    /** Wahl aus dem Menue. 'AUTO' gibt die Regelung wieder frei. */
    setzeNutzerWahl(aenderung) {
        this.nutzer = { ...this.nutzer, ...aenderung };
        this.speichereNutzerWahl();
        this.entscheidung = entscheide(this.eingang());
        if (this.regler)
            this.regler.setzeManuell(this.nutzer.stufe, this.entscheidung.stufe);
        else
            this.regler = new PerformanceGovernor(this.entscheidung.stufe);
        this.melde();
        return this.entscheidung;
    }
    /**
     * Der Besucher hat die grosse Fassung angefordert. Erst ab hier darf eine
     * GPU im Rechenzentrum belegt werden, wenn das Geraet die Szene auch selbst
     * traegt. Vorher zeigt die Seite Poster, Cinematic oder die oertliche Szene.
     */
    async starteGrosseFassung() {
        this.cloudAngefordert = true;
        return this.pruefeCloudErneut();
    }
    /** Cloud-Lage neu bewerten, etwa nach einem Klick auf "Experience starten". */
    async pruefeCloudErneut() {
        if (this.o.cloudPruefung) {
            try {
                this.cloud = await this.o.cloudPruefung();
            }
            catch {
                this.cloud = CLOUD_AUS;
            }
        }
        this.entscheidung = entscheide(this.eingang());
        this.melde();
        return this.entscheidung;
    }
    ladeNutzerWahl() {
        try {
            const roh = localStorage.getItem(SPEICHER_SCHLUESSEL);
            return roh ? JSON.parse(roh) : {};
        }
        catch {
            return {};
        }
    }
    speichereNutzerWahl() {
        try {
            localStorage.setItem(SPEICHER_SCHLUESSEL, JSON.stringify(this.nutzer));
        }
        catch {
            /* privater Modus, gesperrter Speicher — kein Grund, etwas abzubrechen */
        }
    }
    melde() {
        const z = this.zustand;
        for (const fn of this.zuhoerer)
            fn(z);
    }
}
//# sourceMappingURL=experience-manager.js.map