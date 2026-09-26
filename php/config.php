<?php
/**
 * Local AMPPS configuration.
 * Credentials match the platform defaults in prayerApp/gp/includes/config.php:
 * host localhost, user root, password mysql.
 * This app uses its own database so it does not share tables with gp_data.
 */
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = 'mysql';
const DB_NAME = 'jt_blr';
const DB_CHARSET = 'utf8mb4';

const APP_NAME = 'Shree Jagannath Temple';
const APP_PLACE = 'Sarjapura, Bengaluru';
const MAX_ADMIN_USERS = 10;
const TREASURER_APPROVAL_LIMIT = 10000.0;
const STOCK_WRITE_OFF_LIMIT = 5.0;
const SESSION_NAME = 'jt_blr_session';
const TIMEZONE = 'Asia/Kolkata';

const PLAN_PRESETS = [
    'Monthly Annadaan Seva',
    'Monthly Mahaprasad Seva',
    'Monthly Deepa Seva',
    'Quarterly Vastra Seva',
    'Yearly Nitya Seva',
];

const INVENTORY_CATEGORIES = [
    'Hardware',
    'Electronics',
    'Furniture',
    'Puja Items',
    'Kitchen Equipment',
    'Decoration',
    'Other',
];

const EXPENSE_CATEGORIES = [
    'Food Supplies',
    'Maintenance',
    'Utilities',
    'Salaries',
    'Festival',
    'Decoration',
    'Other',
];
