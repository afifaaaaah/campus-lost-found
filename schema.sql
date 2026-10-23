-- =========================================================================
-- CAMPUS LOST & FOUND — FULL SCHEMA (use this for a FRESH install / Docker)
-- Already have the database from earlier? Run schema-update.sql instead.
-- =========================================================================

CREATE DATABASE IF NOT EXISTS campus_lost_found;
USE campus_lost_found;

-- USERS: one row per registered person. `password` holds a bcrypt HASH
-- produced by PHP's password_hash() — never the real password.
CREATE TABLE IF NOT EXISTS users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    phone       VARCHAR(20)   NULL,
    role        ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- ITEMS: lost AND found reports share one table; item_type tells them apart.
-- status lifecycle: active -> matched -> claimed -> resolved (or closed)
CREATE TABLE IF NOT EXISTS items (
    item_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT           NOT NULL,
    item_type    ENUM('lost', 'found') NOT NULL,
    item_name    VARCHAR(150)  NOT NULL,
    category     VARCHAR(50)   NOT NULL,
    description  TEXT          NOT NULL,
    location     VARCHAR(150)  NOT NULL,
    item_date    DATE          NOT NULL,
    image_path   VARCHAR(255)  NULL,
    status       ENUM('active', 'matched', 'claimed', 'resolved', 'closed')
                 NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at  TIMESTAMP     NULL DEFAULT NULL,   -- when it ended; drives the 10-day cleanup
    CONSTRAINT fk_items_user
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- CLAIMS: "I believe this found item is mine", reviewed by an admin.
CREATE TABLE IF NOT EXISTS claims (
    claim_id      INT AUTO_INCREMENT PRIMARY KEY,
    item_id       INT           NOT NULL,
    claimant_id   INT           NOT NULL,
    claim_reason  TEXT          NOT NULL,
    claim_status  ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    claim_date    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   TIMESTAMP     NULL DEFAULT NULL,  -- when the admin decided
    admin_note    VARCHAR(255)  NULL,               -- optional message to the claimant
    CONSTRAINT fk_claims_item
        FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    CONSTRAINT fk_claims_claimant
        FOREIGN KEY (claimant_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE = InnoDB;

SHOW TABLES;
