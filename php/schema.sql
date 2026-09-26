-- Shree Jagannath Temple admin schema (MySQL 8 / PHP 8).
-- Database sjt_temple_blr is created by the installer before this file runs.

CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(100) NOT NULL,
    role            ENUM('Admin', 'Treasurer', 'Staff') NOT NULL DEFAULT 'Staff',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE inventory_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category        VARCHAR(50) NOT NULL,
    name            VARCHAR(150) NOT NULL,
    description     TEXT,
    quantity        INT NOT NULL DEFAULT 0,
    unit_cost       DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit            VARCHAR(30) DEFAULT 'pcs',
    item_condition  VARCHAR(30) NOT NULL DEFAULT 'Good',
    location        VARCHAR(100),
    source          VARCHAR(30) NOT NULL DEFAULT 'Purchased',
    donation_id     INT NULL,
    added_date      DATE NOT NULL,
    added_by        INT,
    notes           TEXT,
    FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE food_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    unit                VARCHAR(30) NOT NULL DEFAULT 'kg',
    current_stock       DECIMAL(10,2) NOT NULL DEFAULT 0,
    minimum_threshold   DECIMAL(10,2) DEFAULT 0,
    last_updated        DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE food_usage_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    food_item_id    INT NOT NULL,
    txn_type        VARCHAR(30) NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL,
    purpose         VARCHAR(200),
    txn_date        DATE NOT NULL,
    logged_by       INT,
    FOREIGN KEY (food_item_id) REFERENCES food_items(id) ON DELETE CASCADE,
    FOREIGN KEY (logged_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE inventory_movements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    item_id         INT NOT NULL,
    movement_type   VARCHAR(30) NOT NULL,
    quantity        INT NOT NULL,
    note            VARCHAR(255) NULL,
    movement_date   DATE NOT NULL,
    logged_by       INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (logged_by) REFERENCES users(id),
    KEY idx_inventory_movement_item (item_id),
    KEY idx_inventory_movement_date (movement_date)
) ENGINE=InnoDB;

CREATE TABLE stock_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    store_name      ENUM('food','inventory') NOT NULL,
    item_id         INT NOT NULL,
    item_name       VARCHAR(150) NOT NULL,
    movement_type   VARCHAR(30) NOT NULL,
    quantity        DECIMAL(12,2) NOT NULL,
    note            VARCHAR(255) NULL,
    movement_date   DATE NOT NULL,
    prepared_by     INT NOT NULL,
    applied         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prepared_by) REFERENCES users(id),
    KEY idx_stock_request_item (store_name, item_id)
) ENGINE=InnoDB;

CREATE TABLE purchases (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    item_id         INT NULL,
    item_name       VARCHAR(150) NOT NULL,
    category        VARCHAR(50) NOT NULL,
    unit            VARCHAR(30) NOT NULL DEFAULT 'pcs',
    quantity        INT NOT NULL,
    unit_cost       DECIMAL(12,2) NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    location        VARCHAR(100) NULL,
    paid_to         VARCHAR(150) NULL,
    purchase_date   DATE NOT NULL,
    payment_mode    VARCHAR(30) NOT NULL,
    cheque_number   VARCHAR(30) NULL,
    cheque_date     DATE NULL,
    cheque_cleared  TINYINT(1) NOT NULL DEFAULT 0,
    upi_reference   VARCHAR(64) NULL,
    expense_id      INT NULL,
    prepared_by     INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (prepared_by) REFERENCES users(id),
    KEY idx_purchase_date (purchase_date)
) ENGINE=InnoDB;

CREATE TABLE vastra_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    deity_name      VARCHAR(100) NOT NULL,
    item_name       VARCHAR(150) NOT NULL,
    color           VARCHAR(50),
    quantity        INT NOT NULL DEFAULT 1,
    source          VARCHAR(30) NULL DEFAULT 'Purchased',
    donation_id     INT NULL,
    date_added      DATE NOT NULL,
    status          VARCHAR(30) NULL DEFAULT 'In Store',
    notes           TEXT
) ENGINE=InnoDB;

CREATE TABLE donors (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20),
    email           VARCHAR(120),
    address         TEXT,
    pan_number      VARCHAR(20),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE donations (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    donor_id                INT NOT NULL,
    donation_type           VARCHAR(30) NOT NULL,
    amount                  DECIMAL(12,2) DEFAULT NULL,
    linked_food_id          INT NULL,
    linked_vastra_id        INT NULL,
    linked_inventory_id     INT NULL,
    purpose                 VARCHAR(200),
    donation_date           DATE NOT NULL,
    payment_mode            VARCHAR(30) NOT NULL,
    receipt_number          VARCHAR(30) UNIQUE,
    receipt_generated       TINYINT(1) DEFAULT 0,
    receipt_cancelled       TINYINT(1) NOT NULL DEFAULT 0,
    receipt_share_token     VARCHAR(64) NULL,
    cheque_number           VARCHAR(30) NULL,
    cheque_date             DATE NULL,
    cheque_cleared          TINYINT(1) NOT NULL DEFAULT 0,
    upi_reference           VARCHAR(64) NULL,
    reconciled_bank_txn_id  INT NULL,
    pledge_id               INT NULL,
    notes                   TEXT,
    created_by              INT,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY uq_receipt_share_token (receipt_share_token)
) ENGINE=InnoDB;

CREATE TABLE pledges (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    donor_id        INT NOT NULL,
    purpose         VARCHAR(200) NOT NULL,
    pledged_amount  DECIMAL(12,2) NOT NULL,
    pledge_date     DATE NOT NULL,
    note            VARCHAR(255) NULL,
    created_by      INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    KEY idx_pledge_donor (donor_id),
    KEY idx_pledge_date (pledge_date)
) ENGINE=InnoDB;

ALTER TABLE donations ADD CONSTRAINT fk_donation_pledge FOREIGN KEY (pledge_id) REFERENCES pledges(id);

CREATE TABLE expenses (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    category                VARCHAR(80) NOT NULL,
    description             VARCHAR(255),
    amount                  DECIMAL(12,2) NOT NULL,
    paid_to                 VARCHAR(150),
    expense_date            DATE NOT NULL,
    payment_mode            VARCHAR(30) NOT NULL,
    voucher_number          VARCHAR(30) NULL UNIQUE,
    cheque_number           VARCHAR(30) NULL,
    cheque_date             DATE NULL,
    cheque_cleared          TINYINT(1) NOT NULL DEFAULT 0,
    upi_reference           VARCHAR(64) NULL,
    bill_filename           VARCHAR(255) NULL,
    reconciled_bank_txn_id  INT NULL,
    receipt_ref             VARCHAR(100),
    added_by                INT,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB;

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

CREATE TABLE receipts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    donation_id     INT NOT NULL,
    receipt_number  VARCHAR(30) NOT NULL UNIQUE,
    generated_date  DATETIME DEFAULT CURRENT_TIMESTAMP,
    generated_by    INT,
    FOREIGN KEY (donation_id) REFERENCES donations(id),
    FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE subscribers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    mobile          VARCHAR(15) NOT NULL UNIQUE,
    email           VARCHAR(120),
    plan_name       VARCHAR(100) NOT NULL DEFAULT 'Monthly Seva',
    plan_amount     DECIMAL(10,2) NOT NULL,
    frequency       VARCHAR(20) NOT NULL DEFAULT 'Monthly',
    status          ENUM('Active','Paused','Cancelled') NOT NULL DEFAULT 'Active',
    start_date      DATE NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE subscription_invoices (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id           INT NOT NULL,
    invoice_number          VARCHAR(30) NOT NULL UNIQUE,
    amount                  DECIMAL(10,2) NOT NULL,
    period_label            VARCHAR(30),
    due_date                DATE NOT NULL,
    status                  ENUM('Pending','Sent','Paid','Overdue','Failed') NOT NULL DEFAULT 'Pending',
    payment_token           VARCHAR(64) NOT NULL UNIQUE,
    notification_sent       TINYINT(1) DEFAULT 0,
    notification_sent_at    DATETIME NULL,
    paid_date               DATETIME NULL,
    paid_via                VARCHAR(30) NULL,
    payment_reference       VARCHAR(100) NULL,
    linked_donation_id      INT NULL,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id),
    FOREIGN KEY (linked_donation_id) REFERENCES donations(id)
) ENGINE=InnoDB;

CREATE TABLE opening_balances (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    financial_year  CHAR(9) NOT NULL,
    cash_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
    bank_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
    note            VARCHAR(255) NULL,
    pending_cash    DECIMAL(12,2) NULL,
    pending_bank    DECIMAL(12,2) NULL,
    pending_note    VARCHAR(255) NULL,
    set_by          INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_opening_year (financial_year),
    FOREIGN KEY (set_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE contra_entries (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    entry_date      DATE NOT NULL,
    direction       ENUM('Deposit','Withdraw') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    note            VARCHAR(255) NULL,
    entered_by      INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entered_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE food_coupon_batches (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    coupon_name     VARCHAR(100) NOT NULL,
    cost            DECIMAL(10,2) NOT NULL,
    start_sl_no     INT NOT NULL,
    end_sl_no       INT NOT NULL,
    quantity        INT NOT NULL,
    total_value     DECIMAL(12,2) NOT NULL,
    created_date    DATE NOT NULL,
    created_by      INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_subscribers_status ON subscribers(status);
CREATE INDEX idx_invoices_status ON subscription_invoices(status);
CREATE INDEX idx_invoices_token ON subscription_invoices(payment_token);
CREATE INDEX idx_donations_date ON donations(donation_date);
CREATE INDEX idx_donations_donor ON donations(donor_id);
CREATE INDEX idx_expenses_date ON expenses(expense_date);
CREATE INDEX idx_bank_txn_date ON bank_transactions(txn_date);
CREATE INDEX idx_bank_txn_status ON bank_transactions(reconciled_status);
CREATE INDEX idx_food_usage_date ON food_usage_log(txn_date);
CREATE INDEX idx_contra_date ON contra_entries(entry_date);

CREATE TABLE corrections (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    subject_type      ENUM('donation','expense') NOT NULL,
    subject_id        INT NOT NULL,
    original_amount   DECIMAL(12,2) NOT NULL,
    corrected_amount  DECIMAL(12,2) NOT NULL,
    reason            VARCHAR(500) NOT NULL,
    entry_date        DATE NOT NULL,
    prepared_by       INT NOT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prepared_by) REFERENCES users(id),
    KEY idx_correction_subject (subject_type, subject_id),
    KEY idx_correction_date (entry_date)
) ENGINE=InnoDB;

CREATE TABLE receipt_cancellations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    donation_id   INT NOT NULL,
    reason        VARCHAR(500) NOT NULL,
    prepared_by   INT NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES donations(id),
    FOREIGN KEY (prepared_by) REFERENCES users(id),
    KEY idx_receipt_cancel_donation (donation_id)
) ENGINE=InnoDB;

CREATE TABLE approvals (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    subject_type    ENUM('expense','contra','opening','purchase','correction','receipt','stock','coupon') NOT NULL,
    subject_id      INT NOT NULL,
    status          ENUM('Draft','Waiting','Approved','Sent back','Rejected') NOT NULL DEFAULT 'Draft',
    amount          DECIMAL(14,2) NOT NULL DEFAULT 0,
    prepared_by     INT NOT NULL,
    decided_by      INT NULL,
    decision_note   VARCHAR(500) NULL,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_approval_subject (subject_type, subject_id),
    FOREIGN KEY (prepared_by) REFERENCES users(id),
    FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE app_settings (
    setting_key    VARCHAR(64) PRIMARY KEY,
    setting_value  TEXT NULL
) ENGINE=InnoDB;
