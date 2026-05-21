<?php
/**
 * Database Configuration
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 * SD Negeri 04 Jatigunung
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'sdnjatig_sikapdikdb');
define('DB_USER', 'sdnjatig_sikapdikuser');
define('DB_PASS', 'I}W#.nDAMRcEK{FA');
define('DB_CHARSET', 'utf8mb4');

// Base URL
define('BASE_URL', 'https://sikapdik.sdn4jatigunung.sch.id/');

// Application Info
define('APP_NAME', 'SIKAPDIK');
define('APP_FULL_NAME', 'Sistem Informasi Pemantauan Perilaku Siswa');
define('APP_VERSION', '1.0.0');
define('SCHOOL_NAME', 'SD Negeri 04 Jatigunung');

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Upload settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('QRCODE_PATH', __DIR__ . '/../uploads/qrcodes/');

// Timezone
date_default_timezone_set('Asia/Jakarta');
