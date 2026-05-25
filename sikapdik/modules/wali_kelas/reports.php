<?php
/**
 * Reports - Wali Kelas
 * Redirects to shared reports module
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas']);
include __DIR__ . '/../shared/reports.php';
