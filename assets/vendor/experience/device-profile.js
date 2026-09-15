/**
 * Geraeteerkennung. Laeuft einmal beim Start im Browser.
 *
 * Es werden ausschliesslich Leistungsmerkmale erhoben, nichts Personenbezogenes,
 * nichts wird gespeichert oder versendet. Das Ergebnis ist eine VERMUTUNG —
 * bestaetigt oder widerlegt wird sie von der Messung in benchmark.ts.
 */
import { geraeteScore } from './decision-engine.js';
import { grafikZuSchwach, istSoftwareRasterizer } from './gpu-kennung.js';
/**
 * Prueft WebGPU echt, nicht nur die Existenz der Schnittstelle: Es gibt
 * Browser, in denen `navigator.gpu` vorhanden ist, die Adapteranforderung
 * danach aber scheitert.
 */
async function pruefeWebGPU() {
    try {
        const gpu = navigator.gpu;
        if (!gpu)
            return false;
        const adapter = await gpu.requestAdapter({ powerPreference: 'high-performance' });
        if (!adapter)
            return false;
        const device = await adapter.requestDevice();
        device.destroy();
        return true;
    }
    catch {
        return false;
    }
}
function pruefeWebGL2() {
    const leer = { verfuegbar: false, renderer: null, maxTextureSize: 0 };
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl2', { failIfMajorPerformanceCaveat: false });
        if (!gl)
            return leer;
        let renderer = null;
        // Viele Browser geben die Kennung aus Datenschutzgruenden nicht heraus.
        // Die Einstufung darf deshalb nicht allein darauf beruhen.
        const info = gl.getExtension('WEBGL_debug_renderer_info');
        if (info)
            renderer = gl.getParameter(info.UNMASKED_RENDERER_WEBGL);
        const maxTextureSize = gl.getParameter(gl.MAX_TEXTURE_SIZE);
        // Kontext sofort freigeben — Browser begrenzen die Anzahl auf wenige.
        gl.getExtension('WEBGL_lose_context')?.loseContext();
        return { verfuegbar: true, renderer, maxTextureSize };
    }
    catch {
        return leer;
    }
}
function istMobil() {
    const uaData = navigator.userAgentData;
    if (typeof uaData?.mobile === 'boolean')
        return uaData.mobile;
    return /android|iphone|ipad|ipod|mobile|tablet/i.test(navigator.userAgent);
}
export async function ermittleDeviceProfile() {
    const webgl = pruefeWebGL2();
    const webgpu = await pruefeWebGPU();
    const basis = {
        webgpu,
        webgl2: webgl.verfuegbar,
        renderer: webgl.renderer,
        // Nicht nur Software-Rasterizer: auch eine integrierte Grafik von 2013
        // traegt keine Echtzeitbuehne. Beide bekommen vorgerendertes Material,
        // und das sieht dort besser aus als ruckelndes 3D.
        softwareRenderer: istSoftwareRasterizer(webgl.renderer) || grafikZuSchwach(webgl.renderer),
        maxTextureSize: webgl.maxTextureSize,
        cores: navigator.hardwareConcurrency || 2,
        memoryGB: navigator.deviceMemory ?? 0,
        mobile: istMobil(),
        touch: matchMedia('(pointer: coarse)').matches,
        devicePixelRatio: window.devicePixelRatio || 1,
        viewport: { width: window.innerWidth, height: window.innerHeight },
        reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
    };
    return { ...basis, score: geraeteScore(basis) };
}
//# sourceMappingURL=device-profile.js.map