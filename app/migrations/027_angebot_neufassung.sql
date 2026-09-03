-- ===========================================================================
-- 027_angebot_neufassung.sql — Ein gesendetes Angebot darf eine Neufassung
-- bekommen.
--
-- WAS BISHER FEHLTE
--
-- Ein verschicktes Angebot ist absichtlich festgeschrieben: Was beim Kunden
-- liegt, darf sich nicht hinter seinem Ruecken bewegen. Nur gab es danach
-- keinen Weg mehr. Sagt der Kunde "eine Seite mehr" oder "die Speisekarte
-- brauche ich doch nicht", stand alles still — aendern verboten, und ein
-- zweites Angebot legte die Verwaltung fuer denselben Bedarf nicht an.
--
-- WARUM NICHT EINFACH DAS ALTE AUFSCHLIESSEN
--
-- Weil der Kunde den Link hat. Ein Angebot, das sich unter seinem Klick
-- veraendert, ist keines. Stattdessen entsteht eine zweite Fassung als
-- Entwurf mit allen Posten der ersten, und die erste wird zurueckgezogen:
-- ihr Link zeigt weiter das alte Blatt, nimmt aber keine Zusage mehr an.
--
-- DIE DREI SPALTEN
--
-- vorgaenger_id und ersetzt_durch zeigen in beide Richtungen. Man will von
-- der neuen Fassung aus wissen, worueber verhandelt wurde, und von der alten
-- aus, wo es weitergeht. fassung ist die Zahl fuers Blatt — "Fassung 2" sagt
-- dem Kunden mehr als eine neue Nummer allein.
-- ===========================================================================

ALTER TABLE angebote
  ADD COLUMN vorgaenger_id INT UNSIGNED NULL AFTER bedarf_id,
  ADD COLUMN ersetzt_durch INT UNSIGNED NULL AFTER vorgaenger_id,
  ADD COLUMN fassung TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER ersetzt_durch;

ALTER TABLE angebote
  ADD KEY ix_angebote_vorgaenger (vorgaenger_id);
