/**
 * Netzbewertung. Entscheidet mit darueber, ob ein Bildstrom aus dem
 * Rechenzentrum ueberhaupt in Frage kommt.
 *
 * Fuer Pixel Streaming zaehlt Latenz mehr als Bandbreite: Eine Leitung mit
 * 40 Mbit/s und 180 ms Rundlauf fuehlt sich schlechter an als eine mit
 * 15 Mbit/s und 30 ms, weil jede Mausbewegung wartet. Deshalb wird die
 * Rundlaufzeit gemessen und nicht nur die Angabe des Browsers geglaubt.
 */
import { netzScore } from './decision-engine.js';
/** Kurze WebRTC-Sondierung: entsteht ueberhaupt ein Kandidat? */
async function pruefeWebRTC(zeitlimitMs) {
    if (typeof RTCPeerConnection === 'undefined')
        return false;
    return new Promise((fertig) => {
        let pc = null;
        let erledigt = false;
        const schliessen = (ergebnis) => {
            if (erledigt)
                return;
            erledigt = true;
            try {
                pc?.close();
            }
            catch {
                /* egal */
            }
            fertig(ergebnis);
        };
        const uhr = setTimeout(() => schliessen(false), zeitlimitMs);
        try {
            pc = new RTCPeerConnection({ iceServers: [] });
            pc.createDataChannel('probe');
            pc.onicecandidate = (e) => {
                if (e.candidate) {
                    clearTimeout(uhr);
                    schliessen(true);
                }
            };
            pc.createOffer()
                .then((o) => pc.setLocalDescription(o))
                .catch(() => {
                clearTimeout(uhr);
                schliessen(false);
            });
        }
        catch {
            clearTimeout(uhr);
            schliessen(false);
        }
    });
}
async function messeLatenz(url, proben, zeitlimitMs) {
    const werte = [];
    for (let i = 0; i < proben; i++) {
        const start = performance.now();
        try {
            const abbruch = new AbortController();
            const uhr = setTimeout(() => abbruch.abort(), zeitlimitMs);
            // cache: 'no-store' und ein wechselnder Parameter, sonst misst man den
            // Zwischenspeicher des Browsers statt der Leitung.
            await fetch(`${url}${url.includes('?') ? '&' : '?'}p=${Date.now()}-${i}`, {
                method: 'HEAD',
                cache: 'no-store',
                signal: abbruch.signal,
            });
            clearTimeout(uhr);
            werte.push(performance.now() - start);
        }
        catch {
            /* eine fehlgeschlagene Probe zaehlt nicht, der Rest bleibt gueltig */
        }
    }
    if (werte.length === 0)
        return null;
    werte.sort((a, b) => a - b);
    return werte[Math.floor(werte.length / 2)] ?? null;
}
export async function ermittleNetworkProfile(o = {}) {
    const verbindung = navigator.connection;
    const zeitlimit = o.zeitlimitMs ?? 1500;
    const [webrtc, gemesseneLatenz] = await Promise.all([
        pruefeWebRTC(zeitlimit),
        o.latenzUrl ? messeLatenz(o.latenzUrl, o.proben ?? 3, zeitlimit) : Promise.resolve(null),
    ]);
    const basis = {
        effectiveType: verbindung?.effectiveType ?? null,
        downlinkMbps: verbindung?.downlink ?? null,
        // Gemessen schlaegt gemeldet.
        rttMs: gemesseneLatenz ?? verbindung?.rtt ?? null,
        saveData: verbindung?.saveData ?? false,
        webrtc,
    };
    return { ...basis, score: netzScore(basis) };
}
//# sourceMappingURL=network-profile.js.map