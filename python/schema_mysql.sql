-- ============================================================
-- Shree Jagannath Temple — Admin Management System
-- MySQL Production Schema
-- ============================================================
-- Run this on your MySQL server to create the database structure.
-- The demo app (app.py) runs on SQLite for local testing, but uses
-- the exact same table/column layout as this file, so switching
-- to MySQL in production is a connection-layer change only
-- (see README.md "Moving to MySQL in Production").
-- ============================================================

CREATE DATABASE IF NOT EXISTS temple_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE temple_admin;

-- ----------------------------------------------------------------
-- 1. Admin Users (limit enforced at application layer: max 10 active)
-- ----------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(100) NOT NULL,
    role            ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Staff',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 2. General Inventory — hardware, equipment, misc temple items
-- ----------------------------------------------------------------
CREATE TABLE inventory_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category        VARCHAR(50) NOT NULL,          -- e.g. Hardware, Electronics, Furniture, Puja Items
    name            VARCHAR(150) NOT NULL,
    description     TEXT,
    quantity        INT NOT NULL DEFAULT 0,
    unit            VARCHAR(30) DEFAULT 'pcs',
    item_condition  ENUM('New','Good','Fair','Needs Repair','Damaged') DEFAULT 'Good',
    location        VARCHAR(100),
    source          ENUM('Purchased','Donated') DEFAULT 'Purchased',
    donation_id     INT NULL,                       -- set if source = Donated, links to donations.id
    added_date      DATE NOT NULL,
    added_by        INT,
    notes           TEXT,
    FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 3. Raw Food Items — stock + usage/consumption tracking
-- ----------------------------------------------------------------
CREATE TABLE food_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    unit                VARCHAR(30) NOT NULL DEFAULT 'kg',   -- kg, litre, pcs, packet...
    current_stock       DECIMAL(10,2) NOT NULL DEFAULT 0,
    minimum_threshold   DECIMAL(10,2) DEFAULT 0,             -- low-stock alert level
    last_updated        DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE food_usage_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    food_item_id    INT NOT NULL,
    txn_type        ENUM('Added','Used') NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL,
    purpose         VARCHAR(200),                    -- e.g. "Daily Mahaprasad", "Ratha Yatra bhog"
    txn_date        DATE NOT NULL,
    logged_by       INT,
    FOREIGN KEY (food_item_id) REFERENCES food_items(id) ON DELETE CASCADE,
    FOREIGN KEY (logged_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 4. Deity Vastra / Cloths
-- ----------------------------------------------------------------
CREATE TABLE vastra_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    deity_name      VARCHAR(100) NOT NULL,           -- Jagannath, Balabhadra, Subhadra...
    item_name       VARCHAR(150) NOT NULL,
    color           VARCHAR(50),
    quantity        INT NOT NULL DEFAULT 1,
    source          ENUM('Purchased','Donated') DEFAULT 'Purchased',
    donation_id     INT NULL,
    date_added      DATE NOT NULL,
    status          ENUM('In Store','In Use','Retired') DEFAULT 'In Store',
    notes           TEXT
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 5. Donors
-- ----------------------------------------------------------------
CREATE TABLE donors (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20),
    email           VARCHAR(120),
    address         TEXT,
    pan_number      VARCHAR(20),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 6. Donations — cash and in-kind (food / vastra / inventory)
-- ----------------------------------------------------------------
CREATE TABLE donations (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    donor_id            INT NOT NULL,
    donation_type       ENUM('Cash','Food','Vastra','Inventory','Other') NOT NULL,
    amount              DECIMAL(12,2) DEFAULT NULL,      -- for Cash, or estimated value of in-kind
    linked_food_id      INT NULL,                        -- if Food, which food_items row it added to
    linked_vastra_id    INT NULL,                         -- if Vastra, which vastra_items row
    linked_inventory_id INT NULL,                          -- if Inventory, which inventory_items row
    purpose             VARCHAR(200),                     -- e.g. "General", "Ratha Yatra", "Annadaan"
    donation_date        DATE NOT NULL,
    payment_mode         ENUM('Cash','Bank Transfer','UPI','Cheque','In-Kind') NOT NULL,
    receipt_number        VARCHAR(30) UNIQUE,
    receipt_generated     TINYINT(1) DEFAULT 0,
    reconciled_bank_txn_id INT NULL,                       -- links to bank_transactions.id once matched
    notes                 TEXT,
    created_by             INT,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 7. Expenses
-- ----------------------------------------------------------------
CREATE TABLE expenses (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    category                VARCHAR(80) NOT NULL,        -- Maintenance, Food Supplies, Utilities, Salaries, Festival, Other
    description             VARCHAR(255),
    amount                  DECIMAL(12,2) NOT NULL,
    paid_to                 VARCHAR(150),
    expense_date            DATE NOT NULL,
    payment_mode            ENUM('Cash','Bank Transfer','UPI','Cheque') NOT NULL,
    reconciled_bank_txn_id  INT NULL,
    receipt_ref             VARCHAR(100),
    added_by                INT,
    created_at               DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 8. Bank Statement Upload batches + parsed transactions
-- ----------------------------------------------------------------
CREATE TABLE bank_statement_uploads (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    filename            VARCHAR(255),
    upload_date         DATETIME DEFAULT CURRENT_TIMESTAMP,
    uploaded_by         INT,
    total_transactions  INT DEFAULT 0,
    matched_count       INT DEFAULT 0,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE bank_transactions (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    upload_batch_id     INT NOT NULL,
    txn_date            DATE NOT NULL,
    description         VARCHAR(255),
    amount              DECIMAL(12,2) NOT NULL,
    txn_type            ENUM('Credit','Debit') NOT NULL,
    balance             DECIMAL(12,2),
    reconciled_status   ENUM('Matched','Unmatched','Manual') DEFAULT 'Unmatched',
    matched_donation_id INT NULL,
    matched_expense_id  INT NULL,
    FOREIGN KEY (upload_batch_id) REFERENCES bank_statement_uploads(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_donation_id) REFERENCES donations(id),
    FOREIGN KEY (matched_expense_id) REFERENCES expenses(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 9. Receipts (generated PDFs, admin-triggered)
-- ----------------------------------------------------------------
CREATE TABLE receipts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    donation_id     INT NOT NULL,
    receipt_number  VARCHAR(30) NOT NULL UNIQUE,
    generated_date  DATETIME DEFAULT CURRENT_TIMESTAMP,
    generated_by    INT,
    FOREIGN KEY (donation_id) REFERENCES donations(id),
    FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 10. Monthly Subscriptions — recurring seva/donation plans
-- ----------------------------------------------------------------
CREATE TABLE subscribers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    mobile          VARCHAR(15) NOT NULL UNIQUE,     -- where invoice links are sent
    email           VARCHAR(120),
    plan_name       VARCHAR(100) NOT NULL DEFAULT 'Monthly Seva',
    plan_amount     DECIMAL(10,2) NOT NULL,
    frequency       ENUM('Monthly','Quarterly','Yearly') NOT NULL DEFAULT 'Monthly',
    status          ENUM('Active','Paused','Cancelled') NOT NULL DEFAULT 'Active',
    start_date      DATE NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 11. Subscription Invoices — one per billing cycle, with a
--     shareable payment link token sent via SMS/WhatsApp
-- ----------------------------------------------------------------
CREATE TABLE subscription_invoices (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id           INT NOT NULL,
    invoice_number          VARCHAR(30) NOT NULL UNIQUE,
    amount                  DECIMAL(10,2) NOT NULL,
    period_label            VARCHAR(30),                -- e.g. "September 2026"
    due_date                DATE NOT NULL,
    status                  ENUM('Pending','Sent','Paid','Overdue','Failed') NOT NULL DEFAULT 'Pending',
    payment_token           VARCHAR(64) NOT NULL UNIQUE, -- forms the /pay/<token> public link
    notification_sent       TINYINT(1) DEFAULT 0,
    notification_sent_at    DATETIME NULL,
    paid_date               DATETIME NULL,
    paid_via                VARCHAR(30) NULL,            -- UPI, Card, Netbanking (from gateway callback)
    payment_reference       VARCHAR(100) NULL,           -- reference id from the real payment gateway
    linked_donation_id      INT NULL,                     -- once paid, mirrored into donations for reporting
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id),
    FOREIGN KEY (linked_donation_id) REFERENCES donations(id)
) ENGINE=InnoDB;

CREATE INDEX idx_subscribers_status ON subscribers(status);
CREATE INDEX idx_invoices_status ON subscription_invoices(status);
CREATE INDEX idx_invoices_token ON subscription_invoices(payment_token);

-- ----------------------------------------------------------------
-- Helpful indexes for reporting & reconciliation performance
-- ----------------------------------------------------------------
-- ----------------------------------------------------------------
-- 12. Food Coupon Batches — prasad/meal coupons, cost-tracked and printable
-- ----------------------------------------------------------------
CREATE TABLE food_coupon_batches (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    coupon_name     VARCHAR(100) NOT NULL,        -- e.g. "Lunch Mahaprasad"
    cost            DECIMAL(10,2) NOT NULL,       -- cost per coupon
    start_sl_no     INT NOT NULL,
    end_sl_no       INT NOT NULL,
    quantity        INT NOT NULL,
    total_value     DECIMAL(12,2) NOT NULL,       -- cost * quantity
    created_date    DATE NOT NULL,
    created_by      INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_donations_date ON donations(donation_date);
CREATE INDEX idx_donations_donor ON donations(donor_id);
CREATE INDEX idx_expenses_date ON expenses(expense_date);
CREATE INDEX idx_bank_txn_date ON bank_transactions(txn_date);
CREATE INDEX idx_bank_txn_status ON bank_transactions(reconciled_status);
CREATE INDEX idx_food_usage_date ON food_usage_log(txn_date);
