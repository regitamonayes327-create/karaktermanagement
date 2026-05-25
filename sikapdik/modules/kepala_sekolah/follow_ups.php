<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['kepala_sekolah']);
// Redirect to shared follow_ups which already supports kepala_sekolah
header('Location: ' . BASE_URL . 'modules/wali_kelas/follow_ups.php');
exit;
