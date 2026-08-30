-- Je Paket entscheidet sich, ob es auf der Website direkt gebucht werden kann.
-- Standard ist aus: Ein Paket erscheint erst dann mit Kaufknopf, wenn es hier
-- ausdruecklich eingeschaltet wird.

ALTER TABLE packages
  ADD COLUMN direktkauf TINYINT(1) NOT NULL DEFAULT 0 AFTER oeffentlich;

-- Der Kaufknopf zeigt sich nur im Livemodus. Zum Ausprobieren laesst er sich
-- hiermit voruebergehend auch im Testmodus einblenden.
INSERT INTO settings (skey, svalue) VALUES ('direktkauf_test', '0')
ON DUPLICATE KEY UPDATE skey = skey;
