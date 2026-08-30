-- Fundament der Verwaltungsplattform von Vecom Design.
-- Alle Betraege als ganze Cent (integer) — nie als Fliesskomma.
-- Alle Status als VARCHAR mit Konstanten in PHP, damit spaetere Status
-- ohne Schemaaenderung ergaenzt werden koennen.

CREATE TABLE customers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(160) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  phone         VARCHAR(60)  NULL,
  company       VARCHAR(160) NULL,
  industry      VARCHAR(120) NULL,
  street        VARCHAR(160) NULL,
  zip           VARCHAR(20)  NULL,
  city          VARCHAR(120) NULL,
  country       VARCHAR(80)  NULL DEFAULT 'Italien',
  notes         TEXT         NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customers_email (email),
  KEY ix_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(160) NOT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'kunde',
  customer_id   INT UNSIGNED NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_role (role),
  CONSTRAINT fk_users_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE packages (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(80)  NOT NULL,
  name          VARCHAR(120) NOT NULL,
  description   TEXT         NULL,
  price_cents   INT UNSIGNED NOT NULL DEFAULT 0,
  monthly_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency      CHAR(3)      NOT NULL DEFAULT 'EUR',
  features      JSON         NULL,
  pages_count   VARCHAR(40)  NULL,
  delivery_days VARCHAR(40)  NULL,
  seo           VARCHAR(190) NULL,
  hosting       VARCHAR(190) NULL,
  extras        TEXT         NULL,
  image         VARCHAR(255) NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  popular       TINYINT(1)   NOT NULL DEFAULT 0,
  sort          SMALLINT     NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_packages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no      VARCHAR(24)  NOT NULL,
  customer_id   INT UNSIGNED NOT NULL,
  package_id    INT UNSIGNED NULL,
  package_name  VARCHAR(120) NOT NULL,
  price_cents   INT UNSIGNED NOT NULL DEFAULT 0,
  monthly_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency      CHAR(3)      NOT NULL DEFAULT 'EUR',
  status        VARCHAR(32)  NOT NULL DEFAULT 'neu',
  notes         TEXT         NULL,
  ordered_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_no (order_no),
  KEY ix_orders_status (status),
  KEY ix_orders_customer (customer_id),
  KEY ix_orders_ordered (ordered_at),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_package  FOREIGN KEY (package_id)  REFERENCES packages(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NULL,
  customer_id   INT UNSIGNED NOT NULL,
  package_id    INT UNSIGNED NULL,
  name          VARCHAR(190) NOT NULL,
  status        VARCHAR(32)  NOT NULL DEFAULT 'bestellung_eingegangen',
  progress      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  priority      VARCHAR(16)  NOT NULL DEFAULT 'normal',
  start_date    DATE         NULL,
  deadline      DATE         NULL,
  preview_url   VARCHAR(255) NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_projects_order (order_id),
  KEY ix_projects_status (status),
  KEY ix_projects_deadline (deadline),
  CONSTRAINT fk_projects_order    FOREIGN KEY (order_id)    REFERENCES orders(id)    ON DELETE SET NULL,
  CONSTRAINT fk_projects_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_projects_package  FOREIGN KEY (package_id)  REFERENCES packages(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  provider      VARCHAR(40)  NOT NULL DEFAULT 'manuell',
  provider_ref  VARCHAR(190) NULL,
  method        VARCHAR(40)  NULL,
  amount_cents  INT UNSIGNED NOT NULL DEFAULT 0,
  currency      CHAR(3)      NOT NULL DEFAULT 'EUR',
  status        VARCHAR(32)  NOT NULL DEFAULT 'ausstehend',
  paid_at       DATETIME     NULL,
  detail        JSON         NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_payments_status (status),
  KEY ix_payments_order (order_id),
  UNIQUE KEY uq_payments_provider_ref (provider, provider_ref),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no    VARCHAR(24)  NOT NULL,
  customer_id   INT UNSIGNED NOT NULL,
  order_id      INT UNSIGNED NULL,
  project_id    INT UNSIGNED NULL,
  net_cents     INT UNSIGNED NOT NULL DEFAULT 0,
  tax_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  tax_cents     INT UNSIGNED NOT NULL DEFAULT 0,
  total_cents   INT UNSIGNED NOT NULL DEFAULT 0,
  currency      CHAR(3)      NOT NULL DEFAULT 'EUR',
  status        VARCHAR(32)  NOT NULL DEFAULT 'entwurf',
  issued_at     DATE         NULL,
  due_at        DATE         NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_invoices_no (invoice_no),
  CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoices_order    FOREIGN KEY (order_id)    REFERENCES orders(id)    ON DELETE SET NULL,
  CONSTRAINT fk_invoices_project  FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE websites (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id    INT UNSIGNED NULL,
  customer_id   INT UNSIGNED NOT NULL,
  domain        VARCHAR(190) NOT NULL,
  url           VARCHAR(255) NOT NULL,
  status        VARCHAR(32)  NOT NULL DEFAULT 'nicht_veroeffentlicht',
  monitoring    TINYINT(1)   NOT NULL DEFAULT 0,
  published_at  DATETIME     NULL,
  ssl_expires_at DATE        NULL,
  last_ok_at    DATETIME     NULL,
  last_fail_at  DATETIME     NULL,
  last_status   SMALLINT     NULL,
  last_ms       INT          NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_websites_status (status),
  CONSTRAINT fk_websites_project  FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE SET NULL,
  CONSTRAINT fk_websites_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE website_checks (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  website_id    INT UNSIGNED NOT NULL,
  checked_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  http_status   SMALLINT     NULL,
  response_ms   INT          NULL,
  ssl_valid     TINYINT(1)   NULL,
  ssl_expires_at DATE        NULL,
  ok            TINYINT(1)   NOT NULL DEFAULT 0,
  error         VARCHAR(255) NULL,
  KEY ix_checks_site_time (website_id, checked_at),
  CONSTRAINT fk_checks_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id    INT UNSIGNED NOT NULL,
  title         VARCHAR(255) NOT NULL,
  done          TINYINT(1)   NOT NULL DEFAULT 0,
  due_date      DATE         NULL,
  sort          SMALLINT     NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_tasks_project (project_id),
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id    INT UNSIGNED NULL,
  customer_id   INT UNSIGNED NOT NULL,
  sender        VARCHAR(16)  NOT NULL DEFAULT 'kunde',
  user_id       INT UNSIGNED NULL,
  body          TEXT         NOT NULL,
  read_at       DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_messages_unread (read_at),
  KEY ix_messages_project (project_id),
  CONSTRAINT fk_messages_project  FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE CASCADE,
  CONSTRAINT fk_messages_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE files (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id   INT UNSIGNED NULL,
  project_id    INT UNSIGNED NULL,
  stored_name   VARCHAR(190) NOT NULL,
  orig_name     VARCHAR(255) NOT NULL,
  mime          VARCHAR(120) NULL,
  size_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by   VARCHAR(40)  NOT NULL DEFAULT 'admin',
  user_id       INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_files_stored (stored_name),
  KEY ix_files_project (project_id),
  CONSTRAINT fk_files_project  FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE CASCADE,
  CONSTRAINT fk_files_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questionnaires (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id    INT UNSIGNED NOT NULL,
  customer_id   INT UNSIGNED NOT NULL,
  status        VARCHAR(32)  NOT NULL DEFAULT 'offen',
  data          JSON         NULL,
  submitted_at  DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_quest_project (project_id),
  CONSTRAINT fk_quest_project  FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE CASCADE,
  CONSTRAINT fk_quest_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activities (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type          VARCHAR(48)  NOT NULL,
  title         VARCHAR(255) NOT NULL,
  customer_id   INT UNSIGNED NULL,
  order_id      INT UNSIGNED NULL,
  project_id    INT UNSIGNED NULL,
  actor         VARCHAR(80)  NOT NULL DEFAULT 'System',
  meta          JSON         NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_activities_time (created_at),
  KEY ix_activities_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type          VARCHAR(48)  NOT NULL,
  level         VARCHAR(16)  NOT NULL DEFAULT 'info',
  title         VARCHAR(255) NOT NULL,
  body          VARCHAR(500) NULL,
  link          VARCHAR(255) NULL,
  read_at       DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_notifications_unread (read_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integrations (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ikey          VARCHAR(60)  NOT NULL,
  name          VARCHAR(120) NOT NULL,
  category      VARCHAR(40)  NOT NULL DEFAULT 'sonstiges',
  status        VARCHAR(24)  NOT NULL DEFAULT 'nicht_verbunden',
  config        JSON         NULL,
  last_sync_at  DATETIME     NULL,
  last_error    VARCHAR(500) NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_integrations_key (ikey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_events (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider      VARCHAR(40)  NOT NULL,
  event_id      VARCHAR(190) NOT NULL,
  event_type    VARCHAR(80)  NULL,
  signature_ok  TINYINT(1)   NOT NULL DEFAULT 0,
  status        VARCHAR(24)  NOT NULL DEFAULT 'empfangen',
  payload       LONGTEXT     NULL,
  error         VARCHAR(500) NULL,
  received_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at  DATETIME     NULL,
  UNIQUE KEY uq_webhook_provider_event (provider, event_id),
  KEY ix_webhook_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NULL,
  actor         VARCHAR(80)  NOT NULL DEFAULT 'System',
  action        VARCHAR(60)  NOT NULL,
  entity        VARCHAR(40)  NOT NULL,
  entity_id     INT UNSIGNED NULL,
  before_json   JSON         NULL,
  after_json    JSON         NULL,
  ip            VARCHAR(45)  NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_audit_entity (entity, entity_id),
  KEY ix_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  skey          VARCHAR(80)  NOT NULL PRIMARY KEY,
  svalue        TEXT         NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
