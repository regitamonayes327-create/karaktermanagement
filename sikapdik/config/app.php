<?php
/**
 * Application Bootstrap
 * Load all required configurations and core files
 */

// Start output buffering
ob_start();

// Load configuration
require_once __DIR__ . '/database.php';

// Load core includes
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Helpers.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';

// Initialize session securely
Security::initSession();

// Initialize database connection
$db = Database::getInstance();
