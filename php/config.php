<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/env.php';

define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_PORT', env_value('DB_PORT', '3306'));
define('DB_NAME', env_first(['DB_NAME', 'MYSQL_DATABASE']));
define('DB_USER', env_first(['DB_USER', 'MYSQL_USER']));
define('DB_PASS', env_first(['DB_PASSWORD', 'DB_PASS', 'MYSQL_PASSWORD']));
define('DB_CHARSET', 'utf8mb4');

const APP_NAME = 'Shree Jagannath Temple';
const APP_PLACE = 'Sarjapura, Bengaluru';
const MAX_ADMIN_USERS = 10;
const TREASURER_APPROVAL_LIMIT = 10000.0;
const STOCK_WRITE_OFF_LIMIT = 5.0;
const SESSION_NAME = 'jt_blr_session';
const TIMEZONE = 'Asia/Kolkata';
