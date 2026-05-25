<?php
/**
 * Reports - Kepala Sekolah
 * Redirects to shared reports module
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['kepala_sekolah']);
include __DIR__ . '/../shared/reports.php';
