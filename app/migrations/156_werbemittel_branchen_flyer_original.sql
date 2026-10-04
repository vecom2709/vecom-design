-- ===========================================================================
-- 156_werbemittel_branchen_flyer_original.sql — Branchen-Flyer A5 jetzt im
-- Stil der Original-Flyer (04.10.2026, Uwe: „im selben Stil wie die Original-
-- Flyer, alle 51 einzeln“). Statt 24 Branchen gibt es 51 Gestaltungen; der
-- Beschreibungstext des Produkts sagt das. Wiederholbar (reines UPDATE).
-- ===========================================================================

UPDATE wm_produkte
   SET text_it = 'Fronte: uno dei 51 volantini per settore, in italiano, tedesco o inglese, con il suo QR e il suo link. Retro: il suo nome, il suo contatto e il QR verso la sua pagina.',
       text_de = 'Vorderseite: einer der 51 Branchen-Flyer, auf Deutsch, Italienisch oder Englisch, mit Ihrem QR-Code und Ihrem Link. Rückseite: Ihr Name, Ihr Kontakt und der QR-Code zu Ihrer Seite.',
       text_en = 'Front: one of 51 industry flyers, in English, German or Italian, with your QR code and your link. Back: your name, your contact and the QR code to your page.'
 WHERE vorlage = 'flyer_branche';
