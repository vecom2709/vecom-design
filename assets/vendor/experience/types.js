/**
 * Die Grundbegriffe der Plattform.
 *
 * Der wichtigste Gedanke steht gleich am Anfang: WO gerendert wird und WIE GUT
 * gerendert wird sind ZWEI Entscheidungen, nicht eine. Ein schwaches Telefon an
 * einer sehr guten Leitung kann CLOUD_UNREAL in ULTRA bekommen; ein starker
 * Rechner an einer schlechten Leitung bekommt LOCAL_WEBGPU, ebenfalls in ULTRA.
 * Wer beides in einen Wert presst, kann diese beiden Faelle nicht mehr
 * unterscheiden — und liefert dem Telefon eine Diashow.
 */
/**
 * Die Rueckfallkette. Jede Strategie kennt genau ihren Nachfolger; es gibt
 * keine Sackgasse, weil SAFE_MEDIA auf sich selbst zeigt.
 */
export const STRATEGIE_KETTE = {
    CLOUD_UNREAL: 'LOCAL_WEBGPU',
    LOCAL_WEBGPU: 'LOCAL_WEBGL2',
    LOCAL_WEBGL2: 'SAFE_MEDIA',
    SAFE_MEDIA: 'SAFE_MEDIA',
};
//# sourceMappingURL=types.js.map