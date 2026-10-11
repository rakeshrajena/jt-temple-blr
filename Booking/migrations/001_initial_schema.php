<?php
// Initial schema. Money is always stored as integer paise (₹1 = 100).
return [
    "CREATE TABLE IF NOT EXISTS admins (
        id {PK},
        username VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'staff',
        is_active {BOOL} NOT NULL DEFAULT 1,
        failed_logins INT NOT NULL DEFAULT 0,
        locked_until {DATETIME} NULL,
        last_login_at {DATETIME} NULL,
        created_at {DATETIME} NOT NULL,
        updated_at {DATETIME} NOT NULL
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS settings (
        skey VARCHAR(64) NOT NULL PRIMARY KEY,
        svalue {TEXT} NULL,
        updated_at {DATETIME} NOT NULL,
        updated_by VARCHAR(100) NULL
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS services (
        id {PK},
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(150) NOT NULL,
        description {TEXT} NULL,
        price_paise INT NOT NULL DEFAULT 0,
        requires_approval {BOOL} NOT NULL DEFAULT 0,
        requires_slot {BOOL} NOT NULL DEFAULT 1,
        collect_participants {BOOL} NOT NULL DEFAULT 0,
        max_participants INT NOT NULL DEFAULT 50,
        allow_food {BOOL} NOT NULL DEFAULT 1,
        is_food_only {BOOL} NOT NULL DEFAULT 0,
        is_combinable {BOOL} NOT NULL DEFAULT 1,
        daily_capacity INT NULL,
        lead_days INT NULL,
        fields_json {TEXT} NULL,
        is_active {BOOL} NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at {DATETIME} NOT NULL,
        updated_at {DATETIME} NOT NULL
    ){TABLE_OPTS}",

    // Named booking_coupons (not food_coupons) so this schema can live in the same MySQL
    // database as the temple ERP, which already uses food_coupons for serial coupon codes.
    "CREATE TABLE IF NOT EXISTS booking_coupons (
        id {PK},
        name VARCHAR(100) NOT NULL,
        description VARCHAR(255) NULL,
        price_paise INT NOT NULL,
        max_per_booking INT NOT NULL DEFAULT 200,
        is_active {BOOL} NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at {DATETIME} NOT NULL,
        updated_at {DATETIME} NOT NULL
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS time_slots (
        id {PK},
        service_id {FK} NOT NULL,
        label VARCHAR(100) NOT NULL,
        start_time CHAR(5) NOT NULL,
        capacity INT NULL,
        weekdays VARCHAR(7) NOT NULL DEFAULT '1234567',
        is_active {BOOL} NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        FOREIGN KEY (service_id) REFERENCES services(id)
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS blocked_dates (
        id {PK},
        service_id {FK} NULL,
        block_date {DATE} NOT NULL,
        reason VARCHAR(255) NULL,
        created_at {DATETIME} NOT NULL,
        created_by VARCHAR(100) NULL,
        FOREIGN KEY (service_id) REFERENCES services(id)
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS bookings (
        id {PK},
        booking_no VARCHAR(30) NOT NULL UNIQUE,
        invoice_no VARCHAR(40) NOT NULL UNIQUE,
        access_token CHAR(32) NOT NULL UNIQUE,
        idempotency_key VARCHAR(64) NULL UNIQUE,
        booking_type VARCHAR(20) NOT NULL,
        devotee_name VARCHAR(100) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        email VARCHAR(150) NULL,
        service_date {DATE} NOT NULL,
        instructions {TEXT} NULL,
        subtotal_paise INT NOT NULL,
        additional_label VARCHAR(100) NULL,
        additional_paise INT NOT NULL DEFAULT 0,
        total_paise INT NOT NULL,
        payment_status VARCHAR(30) NOT NULL,
        approval_status VARCHAR(30) NOT NULL,
        booking_status VARCHAR(30) NOT NULL,
        payment_ref VARCHAR(60) NULL,
        paid_at {DATETIME} NULL,
        created_ip VARCHAR(45) NULL,
        created_at {DATETIME} NOT NULL,
        updated_at {DATETIME} NOT NULL
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS booking_items (
        id {PK},
        booking_id {FK} NOT NULL,
        item_type VARCHAR(20) NOT NULL,
        service_id {FK} NULL,
        coupon_id {FK} NULL,
        slot_id {FK} NULL,
        slot_label VARCHAR(100) NULL,
        name VARCHAR(150) NOT NULL,
        unit_price_paise INT NOT NULL,
        quantity INT NOT NULL,
        amount_paise INT NOT NULL,
        participants INT NULL,
        details_json {TEXT} NULL,
        sort_order INT NOT NULL DEFAULT 0,
        FOREIGN KEY (booking_id) REFERENCES bookings(id)
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS payments (
        id {PK},
        booking_id {FK} NOT NULL,
        method VARCHAR(30) NOT NULL,
        reference VARCHAR(60) NULL,
        amount_paise INT NOT NULL,
        status VARCHAR(30) NOT NULL,
        payer_note VARCHAR(255) NULL,
        gateway_order_id VARCHAR(100) NULL,
        gateway_payment_id VARCHAR(100) NULL,
        submitted_at {DATETIME} NOT NULL,
        reviewed_at {DATETIME} NULL,
        reviewed_by VARCHAR(100) NULL,
        review_note VARCHAR(255) NULL,
        FOREIGN KEY (booking_id) REFERENCES bookings(id)
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS notifications (
        id {PK},
        booking_id {FK} NULL,
        channel VARCHAR(20) NOT NULL,
        audience VARCHAR(20) NOT NULL,
        recipient VARCHAR(30) NOT NULL,
        event VARCHAR(50) NOT NULL,
        template VARCHAR(100) NULL,
        params_json {TEXT} NULL,
        status VARCHAR(20) NOT NULL,
        provider_message_id VARCHAR(150) NULL,
        error {TEXT} NULL,
        attempts INT NOT NULL DEFAULT 0,
        next_attempt_at {DATETIME} NULL,
        created_at {DATETIME} NOT NULL,
        updated_at {DATETIME} NOT NULL,
        sent_at {DATETIME} NULL
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS audit_log (
        id {PK},
        admin_id {FK} NULL,
        actor VARCHAR(100) NOT NULL,
        entity VARCHAR(30) NOT NULL,
        entity_id VARCHAR(50) NULL,
        booking_id {FK} NULL,
        action VARCHAR(60) NOT NULL,
        details_json {TEXT} NULL,
        ip VARCHAR(45) NULL,
        created_at {DATETIME} NOT NULL
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS sequences (
        name VARCHAR(30) NOT NULL,
        period VARCHAR(10) NOT NULL,
        value INT NOT NULL DEFAULT 0,
        PRIMARY KEY (name, period)
    ){TABLE_OPTS}",

    "CREATE TABLE IF NOT EXISTS rate_limits (
        rkey VARCHAR(190) NOT NULL PRIMARY KEY,
        hits INT NOT NULL,
        window_start INT NOT NULL
    ){TABLE_OPTS}",

    "CREATE INDEX idx_bookings_mobile ON bookings (mobile)",
    "CREATE INDEX idx_bookings_service_date ON bookings (service_date)",
    "CREATE INDEX idx_bookings_created ON bookings (created_at)",
    "CREATE INDEX idx_bookings_status ON bookings (booking_status, payment_status)",
    "CREATE INDEX idx_items_booking ON booking_items (booking_id)",
    "CREATE INDEX idx_items_slot ON booking_items (slot_id)",
    "CREATE INDEX idx_items_service ON booking_items (service_id)",
    "CREATE INDEX idx_payments_booking ON payments (booking_id)",
    "CREATE INDEX idx_payments_reference ON payments (reference)",
    "CREATE INDEX idx_notifications_status ON notifications (status, next_attempt_at)",
    "CREATE INDEX idx_notifications_booking ON notifications (booking_id)",
    "CREATE INDEX idx_audit_booking ON audit_log (booking_id)",
    "CREATE INDEX idx_audit_created ON audit_log (created_at)",
    "CREATE INDEX idx_blocked_date ON blocked_dates (block_date)",
];
