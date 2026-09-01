-- ===========================================================================
-- 008_widerruf.sql — Nachweis der Zustimmung beim Direktkauf.
--
-- Wer online eine Dienstleistung bei einem Verbraucher verkauft, muss zwei
-- Dinge belegen koennen: dass AGB und Datenschutz angenommen wurden, und dass
-- der Kunde den sofortigen Beginn ausdruecklich verlangt und dabei bestaetigt
-- hat, das Widerrufsrecht nach vollstaendiger Leistung zu verlieren
-- (Codice del Consumo, Art. 51 Abs. 8 und Art. 59). Ohne diesen Nachweis
-- laeuft die Frist von 14 Tagen weiter, auch wenn laengst gearbeitet wird.
--
-- Der Wortlaut wird mitgespeichert, nicht nur ein Haken: Was heute auf der
-- Seite steht, kann morgen anders lauten. Belegbar ist nur, was der Kunde
-- tatsaechlich vor sich hatte.
-- ===========================================================================

ALTER TABLE orders
  ADD COLUMN agb_ok_am        DATETIME NULL AFTER notes,
  ADD COLUMN widerruf_ok_am   DATETIME NULL AFTER agb_ok_am,
  ADD COLUMN zustimmung_text  TEXT     NULL AFTER widerruf_ok_am,
  ADD COLUMN zustimmung_lang  VARCHAR(5) NULL AFTER zustimmung_text;
