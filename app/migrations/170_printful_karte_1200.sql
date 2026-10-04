-- ===========================================================================
-- 170_printful_karte_1200.sql — Printful-Visitenkarte: Bilder in 1200 × 750 px
-- (05.10.2026). Printful meldet die Druckfläche selbst mit 1200 × 750 px
-- (1/4 Zoll Beschnitt je Seite); angenommen war 1125 × 675. Die Flächenprüfung
-- hat jeden Auftrag mit dem alten Bild verweigert — gedruckt wurde nichts.
--
-- Fotos, die Printful aus den alten Bildern gerechnet hat, zeigen die Karte
-- falsch beschnitten. Sie werden verworfen; der Cron rechnet die Vorlagenfotos
-- neu. Entwürfe mit altem Bild gelten als nicht druckbar (Printful::dateienDa
-- prüft die Größe) — der Partner gibt neu frei und bekommt dabei das neue Foto.
-- ===========================================================================

DELETE FROM wm_vorlagenfotos WHERE vorlage = 'visitenkarte';

DELETE f FROM wm_produktfotos f
  JOIN wm_entwuerfe e ON e.id = f.entwurf_id
  JOIN wm_produkte p ON p.id = e.produkt_id
 WHERE p.vorlage = 'visitenkarte';

UPDATE wm_entwuerfe e JOIN wm_produkte p ON p.id = e.produkt_id
   SET e.mockup = NULL, e.mockup_status = NULL, e.mockup_task = NULL
 WHERE p.vorlage = 'visitenkarte' AND e.mockup_status IS NOT NULL;
