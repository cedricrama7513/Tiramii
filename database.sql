-- TIRA'MII — structure MySQL (utf8mb4) pour Hostinger / phpMyAdmin
-- Import : phpMyAdmin > Importer > choisir ce fichier

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS stock_reservations;
DROP TABLE IF EXISTS stock_levels;
DROP TABLE IF EXISTS products;

CREATE TABLE products (
  id VARCHAR(32) NOT NULL,
  name VARCHAR(255) NOT NULL,
  price_eur DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  description TEXT NOT NULL,
  badge_class VARCHAR(64) NOT NULL DEFAULT 'badge-new',
  badge_text VARCHAR(128) NOT NULL DEFAULT '',
  img_key VARCHAR(32) NOT NULL DEFAULT 'oreo',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_levels (
  product_id VARCHAR(32) NOT NULL,
  quantity INT NOT NULL DEFAULT 0 COMMENT '999 = stock illimité',
  PRIMARY KEY (product_id),
  CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_reservations (
  session_id VARCHAR(64) NOT NULL,
  items_json TEXT NOT NULL COMMENT 'map product_id => qty (JSON)',
  expires_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (session_id),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL DEFAULT '',
  phone VARCHAR(32) NOT NULL,
  address_line VARCHAR(255) NOT NULL,
  zip VARCHAR(12) NOT NULL DEFAULT '',
  city VARCHAR(80) NOT NULL DEFAULT '',
  delivery_time VARCHAR(32) NOT NULL DEFAULT '',
  note TEXT NULL,
  payment_method VARCHAR(32) NOT NULL,
  total_eur DECIMAL(8,2) NOT NULL,
  created_at DATETIME NOT NULL,
  validated_at DATETIME NULL DEFAULT NULL COMMENT 'Rempli depuis admin quand la commande est traitée',
  PRIMARY KEY (id),
  KEY idx_created (created_at),
  KEY idx_validated (validated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  product_id VARCHAR(32) NOT NULL,
  product_label VARCHAR(255) NOT NULL,
  quantity INT NOT NULL,
  unit_price_eur DECIMAL(6,2) NOT NULL,
  box_label VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_order (order_id),
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Données initiales (produits + stock)
INSERT INTO products (id, name, price_eur, description, badge_class, badge_text, img_key, sort_order) VALUES
('oreo', 'Saveur Oreo', 5.00, 'Crème mascarpone vanille, éclats d''Oreo croquants & chocolat noir.', 'badge-hot', '⭐ Bestseller', 'oreo', 1),
('spec', 'Saveur Spéculoos', 5.00, 'Mascarpone léger, poudre de spéculoos & biscuits Lotus.', 'badge-new', 'Classique', 'spec', 2),
('daim', 'Saveur Daim', 5.00, 'Crème mascarpone, éclats de Daim & noisettes torréfiées.', 'badge-new', 'Coup de cœur', 'daim', 3),
('kn', 'Kinder Bueno Nutella', 5.00, 'Nutella coulant, barres Kinder Bueno & mascarpone chocolat.', 'badge-hot', '🔥 Favori', 'kn', 4),
('kw', 'Kinder Bueno White', 6.00, 'Crème vanille intense, Kinder White fondant. (+1€ supplément)', 'badge-sup', '+1€ supplément', 'kw', 5),
('box1', 'Box gourmande', 10.00, 'Bueno, Oreo, Speculos, KitKat.', 'badge-hot', '📦 Box', 'oreo', 6),
('box_supreme', 'Box suprême', 10.00, 'M&M''s, Raffaello, Daim, Kinder Bueno White.', 'badge-hot', '✨ Suprême', 'kw', 7);

-- Quantité 999 = illimité (aucune décrémentation à la commande). Valeurs < 999 = stock géré.
INSERT INTO stock_levels (product_id, quantity) VALUES
('oreo', 50),
('spec', 50),
('daim', 50),
('kn', 50),
('kw', 50),
('box1', 20),
('box_supreme', 20);

-- Déjà en prod avec 999 (illimité) partout ? Pour que le stock baisse à chaque commande, passez à des quantités < 999, ex. :
-- UPDATE stock_levels SET quantity = 50 WHERE quantity = 999 AND product_id IN ('oreo','spec','daim','kn','kw');

-- Exemple de commande (facultatif — commentez si vous voulez une base vide)
INSERT INTO orders (first_name, last_name, phone, address_line, zip, city, delivery_time, note, payment_method, total_eur, created_at)
VALUES ('Test', 'Client', '0612345678', '1 rue de Test', '75013', 'Paris', '22h00', 'Interphone', 'cash', 15.00, NOW());

INSERT INTO order_items (order_id, product_id, product_label, quantity, unit_price_eur, box_label)
VALUES (1, 'oreo', 'Saveur Oreo', 2, 5.00, NULL), (1, 'kw', 'Kinder Bueno White', 1, 6.00, NULL);

-- Bases déjà en production : ajouter la Box suprême + mettre à jour la gourmande (une fois) :
-- UPDATE products SET description = 'Bueno, Oreo, Speculos, KitKat.', img_key = 'oreo' WHERE id = 'box1';
-- INSERT INTO products (id, name, price_eur, description, badge_class, badge_text, img_key, sort_order) VALUES ('box_supreme', 'Box suprême', 10.00, 'M&M''s, Raffaello, Daim, Kinder Bueno White.', 'badge-hot', '✨ Suprême', 'kw', 7);
-- INSERT INTO stock_levels (product_id, quantity) VALUES ('box_supreme', 20);

-- Bases déjà en production : ajouter la validation commande (une seule fois, si la colonne n’existe pas)
-- ALTER TABLE orders ADD COLUMN validated_at DATETIME NULL DEFAULT NULL COMMENT 'Rempli depuis admin quand la commande est traitée' AFTER created_at;
-- CREATE INDEX idx_validated ON orders (validated_at);
