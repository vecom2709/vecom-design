-- Beispieldaten sollen die Verwaltung zeigen, solange noch keine echten
-- Kunden da sind — und danach restlos verschwinden, ohne dass jemand
-- ueberlegen muss, was geloescht werden darf.
--
-- Deshalb bekommt jede betroffene Tabelle ein Kennzeichen. Geloescht wird
-- ausschliesslich, was hier eine 1 stehen hat. Eine echte Zeile hat immer
-- eine 0 und kann von der Loeschung gar nicht erfasst werden.

ALTER TABLE customers     ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE orders        ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE projects      ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE payments      ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE invoices      ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE websites      ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE questionnaires ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tasks         ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE messages      ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE files         ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE activities    ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE notifications ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE mails         ADD COLUMN demo TINYINT(1) NOT NULL DEFAULT 0;

-- Ueber diese Schluessel wird gesucht und geloescht.
ALTER TABLE customers     ADD KEY ix_customers_demo (demo);
ALTER TABLE orders        ADD KEY ix_orders_demo (demo);
ALTER TABLE projects      ADD KEY ix_projects_demo (demo);
ALTER TABLE activities    ADD KEY ix_activities_demo (demo);
ALTER TABLE notifications ADD KEY ix_notifications_demo (demo);
