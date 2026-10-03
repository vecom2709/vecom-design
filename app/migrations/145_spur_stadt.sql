-- ===========================================================================
-- 145_spur_stadt.sql — Stadt zum Besuch (03.10.2026, Uwe: Ja zum Download von
-- DB-IP City Lite). Nur der Ortsname aus der lokalen Tabelle (Geo.php), nie
-- die IP; nur für die Zielmärkte IT, DE, AT, CH. Die Stadt ist die des
-- Netzknotens — bei Mobilfunk oft die nächste größere Stadt.
-- Nur hinzufügen. Rückweg: Spalte entfernen.
-- ===========================================================================

ALTER TABLE spur_besuche ADD COLUMN stadt VARCHAR(80) NULL;
