

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS login_attempts, activity_log, expenses, sale_items, sales, products, users, branches;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE branches (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL UNIQUE,
  address    VARCHAR(255) NULL,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  username      VARCHAR(40)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','vendor') NOT NULL,
  branch_id     INT UNSIGNED NULL,           -- NULL for admin, required for vendor
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  last_login    DATETIME     NULL,
  last_seen     DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

CREATE TABLE products (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  branch_id           INT UNSIGNED NOT NULL,
  name                VARCHAR(120) NOT NULL,
  sku                 VARCHAR(40)  NULL,
  price               DECIMAL(10,2) NOT NULL,
  stock               INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 5,
  active              TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_branch_product (branch_id, name),
  CONSTRAINT fk_products_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

CREATE TABLE sales (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  branch_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  total      DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sales_branch_time (branch_id, created_at),
  KEY idx_sales_time (created_at),
  CONSTRAINT fk_sales_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_sales_user   FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE sale_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sale_id      INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  product_name VARCHAR(120) NOT NULL,   -- snapshot, so history survives renames
  qty          INT NOT NULL,
  unit_price   DECIMAL(10,2) NOT NULL,
  line_total   DECIMAL(12,2) NOT NULL,
  KEY idx_items_sale (sale_id),
  KEY idx_items_product (product_id),
  CONSTRAINT fk_items_sale    FOREIGN KEY (sale_id)    REFERENCES sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE expenses (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  branch_id   INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  category    VARCHAR(50)  NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  amount      DECIMAL(12,2) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_exp_branch_time (branch_id, created_at),
  KEY idx_exp_time (created_at),
  CONSTRAINT fk_exp_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_exp_user   FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE activity_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  branch_id  INT UNSIGNED NULL,
  action     VARCHAR(30)  NOT NULL,     -- login, sale, expense, restock, ...
  detail     VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_time (created_at)
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(60) NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempt_user (username, created_at),
  KEY idx_attempt_ip (ip, created_at)
) ENGINE=InnoDB;

INSERT INTO users (name, username, password_hash, role)
VALUES ('Store Owner', 'admin', '$2y$10$Bvv9jBvHEuqcN.HTAEUGue7oiohot0GjxTMMXo.R69VoQQUjohLr.', 'admin');
