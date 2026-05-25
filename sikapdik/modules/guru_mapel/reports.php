<?php
/**
 * Reports - Guru Mapel
 * Redirects to shared reports module
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['guru_mapel']);
include __DIR__ . '/../shared/reports.php';
