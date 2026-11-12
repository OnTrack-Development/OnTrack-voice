CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190),
  email VARCHAR(190) UNIQUE,
  password_hash TEXT,
  status VARCHAR(80) DEFAULT 'active',
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(190),
  parent_id BIGINT,
  name VARCHAR(190),
  slug VARCHAR(190) UNIQUE,
  status VARCHAR(80) DEFAULT 'active',
  brand_name TEXT,
  logo_path TEXT,
  domain VARCHAR(190),
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memberships (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT,
  account_id BIGINT,
  role VARCHAR(190),
  created_at VARCHAR(190),
  UNIQUE(user_id,account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(190),
  name VARCHAR(190),
  call_quota BIGINT DEFAULT 0,
  site_limit BIGINT DEFAULT 1,
  agent_limit BIGINT DEFAULT 1,
  staff_limit BIGINT DEFAULT 3,
  reseller_customers BIGINT DEFAULT 0,
  price_minor BIGINT DEFAULT 0,
  overage_minor BIGINT DEFAULT 0,
  status VARCHAR(80) DEFAULT 'active',
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  plan_id BIGINT,
  status VARCHAR(190),
  starts_at VARCHAR(190),
  ends_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  name VARCHAR(190),
  domain VARCHAR(190),
  status VARCHAR(80) DEFAULT 'active',
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  site_id BIGINT,
  name VARCHAR(190),
  voice TEXT,
  instructions TEXT,
  knowledge TEXT,
  flow_json TEXT,
  status VARCHAR(80) DEFAULT 'draft',
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  site_id BIGINT,
  name VARCHAR(190),
  prefix VARCHAR(190),
  token_hash VARCHAR(190) UNIQUE,
  scopes VARCHAR(190),
  status VARCHAR(80) DEFAULT 'active',
  last_used_at VARCHAR(190),
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  name VARCHAR(190),
  provider VARCHAR(190),
  token_hash VARCHAR(190) UNIQUE,
  token_prefix TEXT,
  status VARCHAR(80) DEFAULT 'active',
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  external_order_id VARCHAR(190),
  integration_id BIGINT,
  plan_id BIGINT,
  customer_name TEXT,
  customer_email TEXT,
  amount_minor BIGINT,
  status VARCHAR(190),
  payment_reference VARCHAR(190),
  created_at VARCHAR(190),
  UNIQUE(integration_id,external_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  integration_id BIGINT,
  external_id TEXT,
  event_type VARCHAR(190),
  payload TEXT,
  status VARCHAR(190),
  created_at VARCHAR(190),
  UNIQUE(integration_id,external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calls (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  site_id BIGINT,
  phone VARCHAR(190),
  name VARCHAR(190),
  duration_seconds BIGINT,
  status VARCHAR(190),
  is_demo BIGINT DEFAULT 1,
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  site_id BIGINT,
  name VARCHAR(190),
  status VARCHAR(80) DEFAULT 'draft',
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  site_id BIGINT,
  name VARCHAR(190),
  phone VARCHAR(190),
  dnc BIGINT DEFAULT 0,
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  site_id BIGINT,
  name VARCHAR(190),
  phone VARCHAR(190),
  status VARCHAR(80) DEFAULT 'offline',
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_ledger (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  kind VARCHAR(190),
  units BIGINT,
  reference VARCHAR(190) UNIQUE,
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT,
  account_id BIGINT,
  action TEXT,
  detail TEXT,
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT,
  target_account_id BIGINT,
  reason TEXT,
  expires_at VARCHAR(190),
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(190) UNIQUE,
  name VARCHAR(190),
  provider VARCHAR(190),
  tier VARCHAR(190),
  ready BIGINT DEFAULT 0,
  status VARCHAR(80) DEFAULT 'active',
  gender VARCHAR(20) DEFAULT 'neutral',
  dialect VARCHAR(80) DEFAULT '',
  source_ref_id VARCHAR(120) DEFAULT '',
  sample_path TEXT,
  sample_url TEXT,
  meta_json TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voice_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  voice_id BIGINT,
  UNIQUE(account_id,voice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS domains (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  hostname VARCHAR(190) UNIQUE,
  verification_token VARCHAR(190),
  status VARCHAR(80) DEFAULT 'pending',
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT,
  integration_id BIGINT,
  kind VARCHAR(190),
  payload TEXT,
  status VARCHAR(80) DEFAULT 'pending',
  attempts BIGINT DEFAULT 0,
  created_at VARCHAR(190)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
