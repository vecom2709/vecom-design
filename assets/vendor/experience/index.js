/**
 * @vecom/experience-core
 *
 * Der Kern der VECOM Adaptive Experience Platform. Er beantwortet zwei Fragen
 * und sonst keine: WO wird gerendert und WIE GUT.
 *
 * Bewusst frei von React, Three.js und Unreal — diese haengen als Adapter
 * darunter. Der Kern laeuft genauso unter einer gewachsenen Vanilla-Seite wie
 * unter einer neuen Next.js-Anwendung.
 */
export { STRATEGIE_KETTE } from './types.js';
export { QUALITY_TIERS, STUFEN_REIHENFOLGE, stufeTiefer, stufeHoeher, stufeMin, stufeMax, stufeIstMindestens, } from './quality-tiers.js';
export { entscheide, naechsterVersuch, geraeteScore, netzScore, NETZ_SCHWELLE_CLOUD, GERAET_SCHWELLE_STARK, } from './decision-engine.js';
export { PerformanceGovernor, STANDARD_PARAMETER, SPARSCHRITTE, } from './performance-governor.js';
export { grafikZuSchwach, istSoftwareRasterizer } from './gpu-kennung.js';
export { ermittleDeviceProfile } from './device-profile.js';
export { ermittleNetworkProfile } from './network-profile.js';
export { messeLeistung } from './benchmark.js';
export { AdaptiveExperienceManager } from './experience-manager.js';
export { SZENE_HERO, SZENE_PRODUKT, SZENE_SHOWROOM, SZENE_TOUR, STANDARD_SZENEN, } from './scene-requirements.js';
//# sourceMappingURL=index.js.map