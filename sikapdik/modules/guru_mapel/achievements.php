<?php
/**
 * Redirect to wali_kelas achievements (shared module)
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['guru_mapel']);
include __DIR__ . '/../wali_kelas/achievements.php';
