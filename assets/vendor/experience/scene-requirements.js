/**
 * Anforderungen typischer Szenen.
 *
 * Diese Liste ist bewusst klein und benennt Faelle, keine Projekte: Der Kern
 * der Plattform darf nichts ueber vecom-design.it, einen Buchhandel oder eine
 * Immobilienseite wissen. Ein Projekt legt eigene Anforderungen an und reicht
 * sie herein.
 */
/** Bewegung im Kopfbereich einer normalen Seite. Nie Cloud — das waere Unfug. */
export const SZENE_HERO = {
    id: 'hero',
    erlaubteStrategien: ['LOCAL_WEBGPU', 'LOCAL_WEBGL2', 'SAFE_MEDIA'],
    minStufe: 'SAFE',
    unrealTauglich: false,
    komplexitaet: 2,
};
/** Ein Produkt zum Drehen, Zoomen, Konfigurieren. Oertlich gut machbar. */
export const SZENE_PRODUKT = {
    id: 'produkt',
    erlaubteStrategien: ['LOCAL_WEBGPU', 'LOCAL_WEBGL2', 'SAFE_MEDIA'],
    minStufe: 'LOW',
    unrealTauglich: false,
    komplexitaet: 3,
};
/** Begehbarer Raum mit hochwertigen Materialien. Hier lohnt die Cloud. */
export const SZENE_SHOWROOM = {
    id: 'showroom',
    erlaubteStrategien: ['CLOUD_UNREAL', 'LOCAL_WEBGPU', 'LOCAL_WEBGL2', 'SAFE_MEDIA'],
    minStufe: 'LOW',
    unrealTauglich: true,
    komplexitaet: 5,
};
/** Virtuelle Besichtigung, Architektur, Fahrzeug. Wie Showroom. */
export const SZENE_TOUR = {
    id: 'tour',
    erlaubteStrategien: ['CLOUD_UNREAL', 'LOCAL_WEBGPU', 'SAFE_MEDIA'],
    minStufe: 'MEDIUM',
    unrealTauglich: true,
    komplexitaet: 5,
};
export const STANDARD_SZENEN = {
    hero: SZENE_HERO,
    produkt: SZENE_PRODUKT,
    showroom: SZENE_SHOWROOM,
    tour: SZENE_TOUR,
};
//# sourceMappingURL=scene-requirements.js.map