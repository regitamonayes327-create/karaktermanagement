<?php
/**
 * Process QR Scan (AJAX endpoint)
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireLogin();

header('Content-Type: application/json');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$qrToken = $input['qr_token'] ?? '';

if (empty($qrToken)) {
    echo json_encode(['success' => false, 'message' => 'QR Code tidak valid.']);
    exit;
}

$db = Database::getInstance();

// Find student by QR token
$student = $db->fetch("SELECT s.*, c.class_name, c.grade_level, c.id as class_id 
    FROM students s LEFT JOIN classes c ON s.class_id = c.id 
    WHERE s.qr_token = ? AND s.is_active = 1", [$qrToken]);

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'QR Code tidak dikenali atau siswa tidak aktif.']);
    exit;
}

$today = date('Y-m-d');
$currentTime = date('H:i:s');

// Check if already attended today
$existing = $db->fetch("SELECT id FROM attendances WHERE student_id = ? AND date = ?", [$student['id'], $today]);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'Siswa sudah presensi hari ini: ' . htmlspecialchars($student['full_name'])]);
    exit;
}

// Determine status (hadir or terlambat)
$lateThreshold = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'late_threshold_time'") ?: '07:00:00';

// Check per-class threshold
$globalMode = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'late_threshold_global'") ?: '1';
if ($globalMode === '0' && $student['class_id']) {
    $classThreshold = $db->fetchColumn("SELECT late_threshold_time FROM classes WHERE id = ?", [$student['class_id']]);
    if ($classThreshold) $lateThreshold = $classThreshold;
}

$status = ($currentTime > $lateThreshold) ? 'terlambat' : 'hadir';

// Record attendance
$db->insert('attendances', [
    'student_id' => $student['id'],
    'class_id' => $student['class_id'],
    'date' => $today,
    'scan_time' => $currentTime,
    'status' => $status,
    'method' => 'qr_scan',
    'recorded_by' => Auth::getUserId()
]);

Auth::logActivity('scan_attendance', 'attendance', "Presensi QR: {$student['full_name']} - {$status}");

echo json_encode([
    'success' => true,
    'student_name' => $student['full_name'],
    'class_name' => 'Kelas ' . $student['grade_level'] . ' - ' . $student['class_name'],
    'time' => date('H:i', strtotime($currentTime)),
    'status' => $status,
    'status_label' => $status === 'hadir' ? 'Hadir' : 'Terlambat'
]);
