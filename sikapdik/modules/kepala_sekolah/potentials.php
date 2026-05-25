<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['kepala_sekolah']);
include __DIR__ . '/../wali_kelas/potentials.php';
