CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT,
  email TEXT UNIQUE,
  password_hash TEXT,
  status TEXT DEFAULT 'active',
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kind TEXT,
  parent_id INTEGER,
  name TEXT,
  slug TEXT UNIQUE,
  status TEXT DEFAULT 'active',
  brand_name TEXT,
  logo_path TEXT,
  domain TEXT,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS memberships (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  account_id INTEGER,
  role TEXT,
  created_at TEXT,
  UNIQUE(user_id,account_id)
);

CREATE TABLE IF NOT EXISTS plans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kind TEXT,
  name TEXT,
  call_quota INTEGER DEFAULT 0,
  site_limit INTEGER DEFAULT 1,
  agent_limit INTEGER DEFAULT 1,
  staff_limit INTEGER DEFAULT 3,
  reseller_customers INTEGER DEFAULT 0,
  price_minor INTEGER DEFAULT 0,
  overage_minor INTEGER DEFAULT 0,
  status TEXT DEFAULT 'active',
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS subscriptions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  plan_id INTEGER,
  status TEXT,
  starts_at TEXT,
  ends_at TEXT
);

CREATE TABLE IF NOT EXISTS sites (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  name TEXT,
  domain TEXT,
  status TEXT DEFAULT 'active',
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS agents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  site_id INTEGER,
  name TEXT,
  voice TEXT,
  instructions TEXT,
  knowledge TEXT,
  flow_json TEXT,
  status TEXT DEFAULT 'draft',
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS api_keys (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  site_id INTEGER,
  name TEXT,
  prefix TEXT,
  token_hash TEXT UNIQUE,
  scopes TEXT,
  status TEXT DEFAULT 'active',
  last_used_at TEXT,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS integrations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  name TEXT,
  provider TEXT,
  token_hash TEXT UNIQUE,
  token_prefix TEXT,
  status TEXT DEFAULT 'active',
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  external_order_id TEXT,
  integration_id INTEGER,
  plan_id INTEGER,
  customer_name TEXT,
  customer_email TEXT,
  amount_minor INTEGER,
  status TEXT,
  payment_reference TEXT,
  created_at TEXT,
  UNIQUE(integration_id,external_order_id)
);

CREATE TABLE IF NOT EXISTS events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  integration_id INTEGER,
  external_id TEXT,
  event_type TEXT,
  payload TEXT,
  status TEXT,
  created_at TEXT,
  UNIQUE(integration_id,external_id)
);

CREATE TABLE IF NOT EXISTS calls (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  site_id INTEGER,
  phone TEXT,
  name TEXT,
  duration_seconds INTEGER,
  status TEXT,
  is_demo INTEGER DEFAULT 1,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS campaigns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  site_id INTEGER,
  name TEXT,
  status TEXT DEFAULT 'draft',
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS contacts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  site_id INTEGER,
  name TEXT,
  phone TEXT,
  dnc INTEGER DEFAULT 0,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS devices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  site_id INTEGER,
  name TEXT,
  phone TEXT,
  status TEXT DEFAULT 'offline',
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS usage_ledger (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  kind TEXT,
  units INTEGER,
  reference TEXT UNIQUE,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS audit_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_user_id INTEGER,
  account_id INTEGER,
  action TEXT,
  detail TEXT,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS support_grants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_user_id INTEGER,
  target_account_id INTEGER,
  reason TEXT,
  expires_at TEXT,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS voices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT UNIQUE,
  name TEXT,
  provider TEXT,
  tier TEXT,
  ready INTEGER DEFAULT 0,
  status TEXT DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS voice_grants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  voice_id INTEGER,
  UNIQUE(account_id,voice_id)
);

CREATE TABLE IF NOT EXISTS domains (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  hostname TEXT UNIQUE,
  verification_token TEXT,
  status TEXT DEFAULT 'pending',
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER,
  integration_id INTEGER,
  kind TEXT,
  payload TEXT,
  status TEXT DEFAULT 'pending',
  attempts INTEGER DEFAULT 0,
  created_at TEXT
);
