/**
 * Qualitaetsstufen — die einzige Stelle im Projekt, an der Leistungsparameter
 * definiert werden.
 *
 * Grundsatz: Alle Stufen tragen dieselbe Markenwirkung, Komposition, Farbwelt
 * und Designsprache. Unterschiedlich ist nur, WIE das Bild zustande kommt.
 * Diese Werte niemals ueber das Projekt verteilen — sonst weiss spaeter
 * niemand mehr, warum eine Szene auf einem Geraet anders aussieht.
 */
export const STUFEN_REIHENFOLGE = ['SAFE', 'LOW', 'MEDIUM', 'HIGH', 'ULTRA'];
export const QUALITY_TIERS = {
    ULTRA: {
        resolutionScale: 1.1,
        maxPixelRatio: 2,
        particleBudget: 1.0,
        shadowMapSize: 2048,
        maxTextureSize: 4096,
        lodDistanceScale: 1.4,
        shaderVariant: 'ultra',
        lighting: 'dynamic',
        postProcessing: { bloom: true, depthOfField: true, ambientOcclusion: true, volumetrics: true },
        animationQuality: 'full',
        activeZones: 4,
        antialias: true,
        cloudFps: 60,
        cloudBitrateKbps: 20000,
    },
    HIGH: {
        resolutionScale: 1.0,
        maxPixelRatio: 2,
        particleBudget: 0.6,
        shadowMapSize: 1024,
        maxTextureSize: 2048,
        lodDistanceScale: 1.0,
        shaderVariant: 'high',
        lighting: 'dynamic',
        postProcessing: { bloom: true, depthOfField: false, ambientOcclusion: true, volumetrics: false },
        animationQuality: 'main',
        activeZones: 3,
        antialias: true,
        cloudFps: 60,
        cloudBitrateKbps: 12000,
    },
    MEDIUM: {
        resolutionScale: 0.85,
        maxPixelRatio: 1.5,
        particleBudget: 0.3,
        shadowMapSize: 512,
        maxTextureSize: 1024,
        lodDistanceScale: 0.75,
        shaderVariant: 'medium',
        lighting: 'hybrid',
        postProcessing: { bloom: true, depthOfField: false, ambientOcclusion: false, volumetrics: false },
        animationQuality: 'reduced',
        activeZones: 2,
        antialias: false,
        cloudFps: 30,
        cloudBitrateKbps: 6000,
    },
    LOW: {
        resolutionScale: 0.6,
        maxPixelRatio: 1,
        particleBudget: 0.1,
        shadowMapSize: 0,
        maxTextureSize: 512,
        lodDistanceScale: 0.5,
        shaderVariant: 'low',
        lighting: 'baked',
        postProcessing: { bloom: false, depthOfField: false, ambientOcclusion: false, volumetrics: false },
        animationQuality: 'minimal',
        activeZones: 1,
        antialias: false,
        cloudFps: 30,
        cloudBitrateKbps: 3000,
    },
    // Safe Mode ist keine Notversion, sondern eine gestaltete Variante aus
    // vorgerendertem Material: Blender- und Unreal-Cinematics, Cinemagraphs,
    // CSS-Bewegung. Er muss wie ULTRA wirken, nur ohne Echtzeitberechnung.
    SAFE: {
        resolutionScale: 0,
        maxPixelRatio: 1,
        particleBudget: 0,
        shadowMapSize: 0,
        maxTextureSize: 0,
        lodDistanceScale: 0,
        shaderVariant: 'none',
        lighting: 'prerendered',
        postProcessing: { bloom: false, depthOfField: false, ambientOcclusion: false, volumetrics: false },
        animationQuality: 'video',
        activeZones: 0,
        antialias: false,
        cloudFps: 0,
        cloudBitrateKbps: 0,
    },
};
export function stufeTiefer(tier) {
    const i = STUFEN_REIHENFOLGE.indexOf(tier);
    return STUFEN_REIHENFOLGE[Math.max(0, i - 1)] ?? 'SAFE';
}
export function stufeHoeher(tier) {
    const i = STUFEN_REIHENFOLGE.indexOf(tier);
    return STUFEN_REIHENFOLGE[Math.min(STUFEN_REIHENFOLGE.length - 1, i + 1)] ?? 'ULTRA';
}
/** Kleinere der beiden Stufen. Fuer Deckelungen. */
export function stufeMin(a, b) {
    return STUFEN_REIHENFOLGE.indexOf(a) <= STUFEN_REIHENFOLGE.indexOf(b) ? a : b;
}
/** Groessere der beiden Stufen. Fuer Mindestanforderungen. */
export function stufeMax(a, b) {
    return STUFEN_REIHENFOLGE.indexOf(a) >= STUFEN_REIHENFOLGE.indexOf(b) ? a : b;
}
export function stufeIstMindestens(tier, minimum) {
    return STUFEN_REIHENFOLGE.indexOf(tier) >= STUFEN_REIHENFOLGE.indexOf(minimum);
}
//# sourceMappingURL=quality-tiers.js.map