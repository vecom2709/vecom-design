-- Zahlungen bekommen eine Art, weil bei Webdesign in zwei Schritten gezahlt
-- wird: 50 % bei Auftrag, 50 % bei Übergabe. Dazu die Felder, die ein
-- Zahlungsanbieter braucht — bewusst anbieterneutral gehalten.

ALTER TABLE payments
  ADD COLUMN art        VARCHAR(20)  NOT NULL DEFAULT 'gesamt' AFTER order_id,
  ADD COLUMN bezeichnung VARCHAR(120) NULL     AFTER art,
  ADD COLUMN link_url   VARCHAR(500) NULL      AFTER provider_ref,
  ADD COLUMN link_bis   DATETIME     NULL      AFTER link_url,
  ADD COLUMN faellig_am DATE         NULL      AFTER paid_at,
  ADD KEY ix_payments_art (art);

ALTER TABLE orders
  ADD COLUMN anzahlung_prozent TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER monthly_cents;

-- Die Integrationen sind ab jetzt echte Zeilen, damit die Seite ihren Zustand
-- anzeigen kann. Zugangsdaten stehen NIE hier, sondern in config.local.php.
INSERT INTO integrations (ikey, name, category, status) VALUES
  ('stripe', 'Stripe', 'zahlung', 'nicht_verbunden'),
  ('brevo',  'Brevo (E-Mail)', 'email', 'nicht_verbunden')
ON DUPLICATE KEY UPDATE name = VALUES(name);
