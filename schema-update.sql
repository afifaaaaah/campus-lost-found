-- =========================================================================
-- SCHEMA UPDATE — run this ONCE on the database you already created.
-- Adds two columns to `claims` so admins can record WHEN they decided and
-- leave a note for the claimant. Nothing else in the database changes.
-- =========================================================================

USE campus_lost_found;

ALTER TABLE claims
    ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER claim_date,
    ADD COLUMN admin_note  VARCHAR(255) NULL AFTER reviewed_at;

-- -------------------------------------------------------------------------
-- MAKE YOUR FIRST ADMIN
-- 1. Register a normal account on the website first.
-- 2. Then change the email below to yours and run this one line:
-- -------------------------------------------------------------------------
-- UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
