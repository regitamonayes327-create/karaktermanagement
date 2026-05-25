<?php
/**
 * Reports - Orang Tua
 * Redirects to shared reports module
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['orang_tua']);
include __DIR__ . '/../shared/reports.php';
