-- Optionnel : si l’admin PHP ne peut pas exécuter CREATE/ALTER (droits MySQL restreints).
-- À lancer une fois dans phpMyAdmin sur la même base que le site.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pro_accounts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  restaurant_name VARCHAR(255) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL DEFAULT '',
  phone VARCHAR(32) NOT NULL,
  address_line VARCHAR(255) NOT NULL,
  zip VARCHAR(12) NOT NULL,
  city VARCHAR(80) NOT NULL,
  status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pro_accounts_email (email),
  KEY idx_pro_accounts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la colonne existe déjà, ignorer l’erreur.
ALTER TABLE products ADD COLUMN price_pro_eur DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'NULL = même prix que particuliers' AFTER price_eur;

ALTER TABLE orders ADD COLUMN pro_account_id INT UNSIGNED NULL DEFAULT NULL AFTER id;
ALTER TABLE orders ADD KEY idx_orders_pro_account (pro_account_id);

CREATE TABLE IF NOT EXISTS pro_leads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurant_name VARCHAR(255) NOT NULL,
  contact_name VARCHAR(160) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  city VARCHAR(120) NOT NULL DEFAULT '',
  intent VARCHAR(80) NOT NULL DEFAULT '',
  message VARCHAR(4000) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_pro_leads_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
