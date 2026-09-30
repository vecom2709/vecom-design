-- ===========================================================================
-- 113_sie.sql — Alles auf Sie (30.09.2026, Uwe: „alles auf Sie“).
--
-- Die Website, die Fragen des Baukastens, Manuela und der Telegram-Bot
-- siezen längst. Geduzt haben noch drei Leistungstexte, die in der
-- Datenbank stehen (Preisseite, Betreuungskarten). Die Startdateien sind
-- mitgeändert; hier werden bestehende Einrichtungen nachgezogen.
--
-- NUR, WAS NOCH IM ORIGINAL DASTEHT
--
-- Hat Uwe einen Text in der Verwaltung selbst umgeschrieben, bleibt er
-- unangetastet: Ersetzt wird nur der wörtliche alte Satz. Die drei
-- Paketsätze sind reines ASCII — REPLACE trifft sie deshalb auch dann, wenn
-- der JSON-Text die Umlaute als \u-Folge speichert.
-- ===========================================================================

UPDATE bausteine
   SET text_de = 'Ich schreibe die Texte jeder Seite, Sie lesen sie vor der Veröffentlichung gegen.'
 WHERE slug = 'texte'
   AND text_de = 'Ich schreibe die Texte jeder Seite, du liest sie vor der Veröffentlichung gegen.';

UPDATE bausteine
   SET text_de = 'Ihr Projekt geht in der Reihenfolge vor. Je größer es ist, desto mehr schiebt es.'
 WHERE slug = 'express'
   AND text_de = 'Dein Projekt geht in der Reihenfolge vor. Je größer es ist, desto mehr schiebt es.';

UPDATE packages
   SET texte = REPLACE(REPLACE(REPLACE(texte,
               'ich merke es vor dir', 'ich merke es vor Ihnen'),
               'du schreibst mir, ich antworte', 'Sie schreiben mir, ich antworte'),
               'deine Anliegen zuerst', 'Ihre Anliegen zuerst')
 WHERE texte LIKE '%ich merke es vor dir%'
    OR texte LIKE '%du schreibst mir, ich antworte%'
    OR texte LIKE '%deine Anliegen zuerst%';

-- Die ältere Spalte features trägt dieselben Sätze (Rückfall, wenn texte leer ist).
UPDATE packages
   SET features = REPLACE(REPLACE(REPLACE(features,
               'ich merke es vor dir', 'ich merke es vor Ihnen'),
               'du schreibst mir, ich antworte', 'Sie schreiben mir, ich antworte'),
               'deine Anliegen zuerst', 'Ihre Anliegen zuerst')
 WHERE features LIKE '%ich merke es vor dir%'
    OR features LIKE '%du schreibst mir, ich antworte%'
    OR features LIKE '%deine Anliegen zuerst%';
