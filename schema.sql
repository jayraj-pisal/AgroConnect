 -- ═══════════════════════════════════════════
--  AGROCONNECT DATABASE SCHEMA
--  MySQL 8.0+  |  UTF-8 MB4
-- ═══════════════════════════════════════════
CREATE DATABASE IF NOT EXISTS agroconnect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE agroconnect;

-- ── USERS ──────────────────────────────────
CREATE TABLE users (
  id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120)    NOT NULL,
  email         VARCHAR(180)    NOT NULL UNIQUE,
  phone         VARCHAR(20),
  password_hash VARCHAR(255)    NOT NULL,
  role          ENUM('farmer','buyer','admin') NOT NULL DEFAULT 'buyer',
  location      VARCHAR(200),
  village       VARCHAR(100),
  district      VARCHAR(100),
  state         VARCHAR(100),
  profile_image VARCHAR(500),
  bio           TEXT,
  is_verified   TINYINT(1)      NOT NULL DEFAULT 0,
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  last_login    DATETIME,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role  (role)
);

-- ── CATEGORIES ─────────────────────────────
CREATE TABLE categories (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)   NOT NULL,
  slug        VARCHAR(80)   NOT NULL UNIQUE,
  type        ENUM('produce','equipment') NOT NULL,
  icon        VARCHAR(10),
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, slug, type, icon) VALUES
  ('Vegetables',  'vegetable', 'produce',   '🥦'),
  ('Fruits',      'fruit',     'produce',   '🍎'),
  ('Cereals',     'cereal',    'produce',   '🌾'),
  ('Pulses',      'pulse',     'produce',   '🫘'),
  ('Spices',      'spice',     'produce',   '🌶️'),
  ('Dairy',       'dairy',     'produce',   '🥛'),
  ('Tractors',    'tractor',   'equipment', '🚜'),
  ('Irrigation',  'irrigation','equipment', '💧'),
  ('Hand Tools',  'tools',     'equipment', '🔧'),
  ('Harvesters',  'harvester', 'equipment', '⚙️'),
  ('Seeds',       'seeds',     'equipment', '🌱');

-- ── PRODUCTS ───────────────────────────────
CREATE TABLE products (
  id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED   NOT NULL,
  category_id   INT UNSIGNED   NOT NULL,
  name          VARCHAR(200)   NOT NULL,
  description   TEXT,
  price_per_kg  DECIMAL(10,2)  NOT NULL,
  quantity_kg   DECIMAL(10,2)  NOT NULL DEFAULT 0,
  location      VARCHAR(200),
  village       VARCHAR(100),
  district      VARCHAR(100),
  state         VARCHAR(100),
  images        JSON,              -- array of image URLs
  is_organic    TINYINT(1)     NOT NULL DEFAULT 0,
  harvest_date  DATE,
  expiry_date   DATE,
  status        ENUM('pending','approved','rejected','sold') NOT NULL DEFAULT 'pending',
  views         INT UNSIGNED   NOT NULL DEFAULT 0,
  created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  INDEX idx_category (category_id),
  INDEX idx_status   (status),
  INDEX idx_location (state, district),
  FULLTEXT idx_search (name, description)
);

-- ── EQUIPMENT ADS ──────────────────────────
CREATE TABLE equipment_ads (
  id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED   NOT NULL,
  category_id   INT UNSIGNED   NOT NULL,
  title         VARCHAR(200)   NOT NULL,
  description   TEXT,
  price         DECIMAL(12,2)  NOT NULL,
  is_negotiable TINYINT(1)     NOT NULL DEFAULT 1,
  condition_val ENUM('new','like_new','good','fair','poor') NOT NULL DEFAULT 'good',
  year_of_mfg   YEAR,
  hours_used    INT UNSIGNED,
  brand         VARCHAR(100),
  model_number  VARCHAR(100),
  location      VARCHAR(200),
  contact_phone VARCHAR(20),
  images        JSON,
  status        ENUM('pending','approved','rejected','sold') NOT NULL DEFAULT 'pending',
  views         INT UNSIGNED   NOT NULL DEFAULT 0,
  created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  INDEX idx_category  (category_id),
  INDEX idx_status    (status),
  FULLTEXT idx_search (title, description)
);

-- ── ORDERS ─────────────────────────────────
CREATE TABLE orders (
  id              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  buyer_id        INT UNSIGNED   NOT NULL,
  seller_id       INT UNSIGNED   NOT NULL,
  product_id      INT UNSIGNED   NOT NULL,
  quantity_kg     DECIMAL(10,2)  NOT NULL,
  price_per_kg    DECIMAL(10,2)  NOT NULL,
  total_amount    DECIMAL(12,2)  NOT NULL,
  delivery_address TEXT,
  delivery_date   DATE,
  payment_method  ENUM('cod','upi','bank_transfer','online') NOT NULL DEFAULT 'cod',
  payment_status  ENUM('pending','paid','failed','refunded')  NOT NULL DEFAULT 'pending',
  order_status    ENUM('placed','confirmed','packed','shipped','delivered','cancelled') NOT NULL DEFAULT 'placed',
  notes           TEXT,
  created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (buyer_id)   REFERENCES users(id)    ON DELETE RESTRICT,
  FOREIGN KEY (seller_id)  REFERENCES users(id)    ON DELETE RESTRICT,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  INDEX idx_buyer    (buyer_id),
  INDEX idx_seller   (seller_id),
  INDEX idx_status   (order_status)
);

-- ── MESSAGES ───────────────────────────────
CREATE TABLE messages (
  id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  sender_id   INT UNSIGNED   NOT NULL,
  receiver_id INT UNSIGNED   NOT NULL,
  product_id  INT UNSIGNED,              -- optional: context product
  message     TEXT           NOT NULL,
  is_read     TINYINT(1)     NOT NULL DEFAULT 0,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id)   REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE SET NULL,
  INDEX idx_sender   (sender_id),
  INDEX idx_receiver (receiver_id),
  INDEX idx_conv     (sender_id, receiver_id)
);

-- ── MARKET RATES ───────────────────────────
CREATE TABLE market_rates (
  id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  crop_name   VARCHAR(100)   NOT NULL,
  category_id INT UNSIGNED,
  mandi_name  VARCHAR(150)   NOT NULL,
  state       VARCHAR(100),
  price_unit  VARCHAR(30)    NOT NULL DEFAULT '₹/quintal',
  price       DECIMAL(10,2)  NOT NULL,
  min_price   DECIMAL(10,2),
  max_price   DECIMAL(10,2),
  rate_date   DATE           NOT NULL,
  source      VARCHAR(100),
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_crop (crop_name),
  INDEX idx_date (rate_date),
  INDEX idx_mandi (mandi_name)
);

-- ── REVIEWS ────────────────────────────────
CREATE TABLE reviews (
  id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  reviewer_id INT UNSIGNED   NOT NULL,
  seller_id   INT UNSIGNED   NOT NULL,
  order_id    INT UNSIGNED   NOT NULL,
  rating      TINYINT        NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment     TEXT,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reviewer_id) REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (seller_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (order_id)    REFERENCES orders(id)  ON DELETE CASCADE,
  UNIQUE KEY  uq_review     (reviewer_id, order_id)
);

-- ── CART ───────────────────────────────────
CREATE TABLE cart_items (
  id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED   NOT NULL,
  product_id  INT UNSIGNED   NOT NULL,
  quantity_kg DECIMAL(10,2)  NOT NULL DEFAULT 1,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_cart (user_id, product_id)
);

-- ── NOTIFICATIONS ──────────────────────────
CREATE TABLE notifications (
  id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED   NOT NULL,
  type        VARCHAR(50)    NOT NULL,  -- 'order','message','rate_alert'
  title       VARCHAR(200)   NOT NULL,
  body        TEXT,
  is_read     TINYINT(1)     NOT NULL DEFAULT 0,
  ref_id      INT UNSIGNED,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_read (is_read)
);

-- ── PRICE ALERTS ───────────────────────────
CREATE TABLE price_alerts (
  id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED   NOT NULL,
  crop_name   VARCHAR(100)   NOT NULL,
  target_price DECIMAL(10,2) NOT NULL,
  direction   ENUM('above','below') NOT NULL,
  is_active   TINYINT(1)     NOT NULL DEFAULT 1,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ═══════════════════════════════════════════
--  SAMPLE DATA
-- ═══════════════════════════════════════════
INSERT INTO users (name, email, phone, password_hash, role, location, state, is_verified, is_active) VALUES
  ('Ramesh Patil',   'farmer@demo.com',  '+919876543210', '$2y$12$xe8eI7w.oxLx.0w4COlMBO0kY8Aefp1JrRMIK.azBRhjh6ZpcJy7q', 'farmer', 'Nashik, Maharashtra',  'Maharashtra', 1, 1),
  ('Suresh Kumar',   'buyer@demo.com',   '+918765432109', '$2y$12$xe8eI7w.oxLx.0w4COlMBO0kY8Aefp1JrRMIK.azBRhjh6ZpcJy7q', 'buyer',  'Pune, Maharashtra',    'Maharashtra', 1, 1),
  ('Admin User',     'admin@demo.com',   '+917654321098', '$2y$12$xe8eI7w.oxLx.0w4COlMBO0kY8Aefp1JrRMIK.azBRhjh6ZpcJy7q', 'admin',  'Mumbai, Maharashtra',  'Maharashtra', 1, 1),
  ('Gurpreet Singh', 'gurpreet@demo.com','+916543210987', '$2y$12$xe8eI7w.oxLx.0w4COlMBO0kY8Aefp1JrRMIK.azBRhjh6ZpcJy7q', 'farmer', 'Ludhiana, Punjab',    'Punjab',      1, 1),
  ('Priya Desai',    'priya@demo.com',   '+915432109876', '$2y$12$xe8eI7w.oxLx.0w4COlMBO0kY8Aefp1JrRMIK.azBRhjh6ZpcJy7q', 'farmer', 'Pune, Maharashtra',   'Maharashtra', 1, 1);

INSERT INTO products (user_id, category_id, name, description, price_per_kg, quantity_kg, location, state, is_organic, status) VALUES
  (1, 1, 'Fresh Tomatoes',   'Farm-fresh red tomatoes, harvested this morning.',       35.00,  500, 'Nashik',     'Maharashtra', 1, 'approved'),
  (4, 3, 'Basmati Rice',     'Premium long-grain Basmati, aged 2 years.',              95.00, 1000, 'Ludhiana',   'Punjab',      0, 'approved'),
  (5, 1, 'Baby Spinach',     'Tender baby spinach, pesticide-free.',                  60.00,  150, 'Pune',       'Maharashtra', 1, 'approved'),
  (1, 5, 'Turmeric (Sangli)','High-curcumin Sangli turmeric, 3-4% curcumin.',        150.00,  100, 'Sangli',     'Maharashtra', 1, 'approved'),
  (1, 2, 'Pomegranate',      'Sweet arils, beautiful red pomegranates.',              120.00,  400, 'Solapur',    'Maharashtra', 1, 'approved');

INSERT INTO market_rates (crop_name, mandi_name, state, price_unit, price, min_price, max_price, rate_date) VALUES
  ('Tomato',    'Nashik APMC',     'Maharashtra', '₹/kg',      38.00,  30.00,  45.00, CURDATE()),
  ('Onion',     'Lasalgaon APMC',  'Maharashtra', '₹/kg',      22.00,  18.00,  28.00, CURDATE()),
  ('Wheat',     'Pune Market',     'Maharashtra', '₹/quintal', 2350.0, 2200.0, 2500.0,CURDATE()),
  ('Soybean',   'Latur APMC',      'Maharashtra', '₹/quintal', 4400.0, 4200.0, 4600.0,CURDATE()),
  ('Cotton',    'Aurangabad APMC', 'Maharashtra', '₹/quintal', 6800.0, 6500.0, 7000.0,CURDATE()),
  ('Basmati',   'Delhi APMC',      'Delhi',       '₹/quintal', 5200.0, 4800.0, 5500.0,CURDATE()),
  ('Potato',    'Pune Market',     'Maharashtra', '₹/kg',      18.00,  14.00,  22.00, CURDATE()),
  ('Turmeric',  'Sangli APMC',     'Maharashtra', '₹/kg',      155.0,  140.0,  170.0, CURDATE());
