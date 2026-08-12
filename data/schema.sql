-- סכימת מסד הנתונים למערכת נדלניס טים.
-- סדר יצירה מכבד מפתחות זרים: settings -> agents -> users -> properties -> partners -> leads -> testimonials.
-- אימות (auth) מרוכז כולו בטבלת users (role admin/agent) — לא ב-settings/agents.

CREATE TABLE IF NOT EXISTS settings (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  agency_name VARCHAR(255) NOT NULL DEFAULT '',
  tagline VARCHAR(255) NOT NULL DEFAULT '',
  phone VARCHAR(50) NOT NULL DEFAULT '',
  whatsapp VARCHAR(50) NOT NULL DEFAULT '',
  email VARCHAR(255) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  facebook VARCHAR(255) NOT NULL DEFAULT '',
  instagram VARCHAR(255) NOT NULL DEFAULT '',
  hero_title VARCHAR(255) NOT NULL DEFAULT '',
  hero_sub TEXT,
  about_text TEXT,
  stat_years INT NOT NULL DEFAULT 0,
  stat_deals INT NOT NULL DEFAULT 0,
  stat_clients INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings
  (id, agency_name, tagline, phone, whatsapp, email, address, hero_title, hero_sub, about_text, stat_years, stat_deals, stat_clients)
VALUES
  (1, 'נדלניס טים', 'תיווך • שיווק • השקעות נדל״ן', '052-529-9482', '972525299482', 'info@nadlanisteam.co.il', 'נתניה',
   'מכירים כל רחוב בנתניה',
   'צוות נדלניס טים מלווה אתכם בקנייה, במכירה ובהשקעה בנדל״ן — באמינות, במקצועיות ובלב פתוח.',
   'נדלניס טים פועל בנתניה עם היכרות מעמיקה עם השכונות, המחירים והאנשים.\nכל עסקה מתחילה בהקשבה: מה חשוב לכם, מה התקציב, ולאן אתם רוצים להגיע.\nמשם, אנחנו כבר דואגים לשאר.',
   12, 340, 200);

CREATE TABLE IF NOT EXISTS agents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  role VARCHAR(100) NOT NULL DEFAULT '',
  phone VARCHAR(50) NOT NULL DEFAULT '',
  whatsapp VARCHAR(50) NOT NULL DEFAULT '',
  email VARCHAR(255) NOT NULL DEFAULT '',
  photo VARCHAR(255) NOT NULL DEFAULT '',
  bio TEXT,
  areas JSON NOT NULL,
  languages JSON NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 10,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','agent') NOT NULL,
  agent_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_username (username),
  UNIQUE KEY uniq_users_agent (agent_id),
  CONSTRAINT fk_users_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS properties (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  deal ENUM('sale','rent') NOT NULL DEFAULT 'sale',
  type VARCHAR(50) NOT NULL DEFAULT '',
  status ENUM('available','under_contract','sold','draft') NOT NULL DEFAULT 'available',
  city VARCHAR(100) NOT NULL DEFAULT '',
  neighborhood VARCHAR(100) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  lat DECIMAL(10,6) NULL,
  lng DECIMAL(10,6) NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  rooms DECIMAL(4,1) NOT NULL DEFAULT 0,
  size DECIMAL(8,1) NOT NULL DEFAULT 0,
  plot_size DECIMAL(8,1) NOT NULL DEFAULT 0,
  floor INT NOT NULL DEFAULT 0,
  total_floors INT NOT NULL DEFAULT 0,
  parking INT NOT NULL DEFAULT 0,
  balcony TINYINT(1) NOT NULL DEFAULT 0,
  elevator TINYINT(1) NOT NULL DEFAULT 0,
  mamad TINYINT(1) NOT NULL DEFAULT 0,
  has_storage TINYINT(1) NOT NULL DEFAULT 0,
  renovated TINYINT(1) NOT NULL DEFAULT 0,
  is_accessible TINYINT(1) NOT NULL DEFAULT 0,
  furnished TINYINT(1) NOT NULL DEFAULT 0,
  entry_date VARCHAR(50) NOT NULL DEFAULT '',
  description TEXT,
  images JSON NOT NULL,
  agent_id INT UNSIGNED NOT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_properties_agent (agent_id),
  CONSTRAINT fk_properties_agent FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT '',
  business_type VARCHAR(255) NOT NULL DEFAULT '',
  regions JSON NOT NULL,
  description_short TEXT,
  description_full TEXT,
  services JSON NOT NULL,
  phone VARCHAR(50) NOT NULL DEFAULT '',
  whatsapp VARCHAR(50) NOT NULL DEFAULT '',
  email VARCHAR(255) NOT NULL DEFAULT '',
  website VARCHAR(255) NOT NULL DEFAULT '',
  logo VARCHAR(255) NOT NULL DEFAULT '',
  gallery JSON NOT NULL,
  years_experience INT NULL,
  rating DECIMAL(2,1) NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 10,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NOT NULL DEFAULT '',
  email VARCHAR(255) NOT NULL DEFAULT '',
  message TEXT,
  property_id INT UNSIGNED NULL,
  agent_id INT UNSIGNED NULL,
  partner_id INT UNSIGNED NULL,
  service VARCHAR(255) NOT NULL DEFAULT '',
  source ENUM('property','agent','partner','contact','home') NOT NULL DEFAULT 'contact',
  created_at DATETIME NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_leads_property (property_id),
  KEY idx_leads_agent (agent_id),
  KEY idx_leads_partner (partner_id),
  CONSTRAINT fk_leads_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
  CONSTRAINT fk_leads_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL,
  CONSTRAINT fk_leads_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS testimonials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL DEFAULT '',
  text TEXT NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
