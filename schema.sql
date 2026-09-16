-- ============================================================================
-- Centenary Networks — Self-Service Password Reset (SSPR)
-- schema.sql
--
-- Run this entire script once in phpMyAdmin (SQL tab) against the empty
-- database you created in the Xneelo Control Panel. Safe to re-run: tables
-- are only created if they do not already exist.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ----------------------------------------------------------------------------
-- clients: one row per corporate client Centenary manages.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_name VARCHAR(150) NOT NULL,
  client_code VARCHAR(60) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clients_client_code (client_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- client_admins: the administrator accounts that can request a password
-- reset. email is globally unique so a login/reset lookup never has to guess
-- which client a given address belongs to.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS client_admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  reset_token VARCHAR(64) NULL DEFAULT NULL,
  token_expiry DATETIME NULL DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_client_admins_email (email),
  KEY idx_client_admins_reset_token (reset_token),
  KEY idx_client_admins_client_id (client_id),
  CONSTRAINT fk_client_admins_client
    FOREIGN KEY (client_id) REFERENCES clients (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Optional: a starter client so you can test the flow immediately. Use
-- generate-hash.php (included in this project) to produce a real bcrypt
-- hash for a test password, then INSERT the admin row yourself in
-- phpMyAdmin with that hash — never paste a hash you have not generated
-- and verified yourself, and delete generate-hash.php from the server once
-- you are done testing.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO clients (id, client_name, client_code) VALUES
  (1, 'Centenary Networks (Internal Test)', 'centenary-internal');

-- Example (run after generating a hash with generate-hash.php):
-- INSERT INTO client_admins (client_id, full_name, email, password_hash)
-- VALUES (1, 'Test Administrator', 'you@yourdomain.com', 'PASTE_BCRYPT_HASH_HERE');
