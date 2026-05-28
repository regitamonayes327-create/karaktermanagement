<?php
/**
 * Process QR Scan (AJAX endpoint)
 * Returns JSON response for scan_qr.php
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';

header('Content-Type: application/json; charset=utf-8');

// Must be logged in
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Sesi login telah habis. Silakan login ulang.']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Request tidak valid.']);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
$qrToken = trim($input['qr_token'] ?? '');

if (empty($qrToken)) {
    echo json_encode(['success' => false, 'message' => 'QR Code kosong atau tidak terbaca.']);
    exit;
}

// Basic length check only - let database handle the actual matching
if (strlen($qrToken) < 5 || strlen($qrToken) > 255) {
    echo json_encode(['success' => false, 'message' => 'Format QR Code tidak valid. Panjang token tidak sesuai.']);
    exit;
}
// Allow any printable characters (QR codes can contain various chars)
if (preg_match('/[\x00-\x1f]/', $qrToken)) {
    echo json_encode(['success' => false, 'message' => 'Format QR Code tidak valid. Pastikan QR Code berasal dari SIKAPDIK.']);
    exit;
}

$db = Database::getInstance();

// Find student by QR token
$student = $db->fetch(
    "SELECT s.id, s.full_name, s.nis, s.qr_token, s.class_id, s.is_active,
            c.class_name, c.grade_level, c.id as cid
     FROM students s 
     LEFT JOIN classes c ON s.class_id = c.id 
     WHERE s.qr_token = ? LIMIT 1", 
    [$qrToken]
);

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'QR Code tidak dikenali. Siswa tidak ditemukan di database.']);
    exit;
}

if (!$student['is_active']) {
    echo json_encode(['success' => false, 'message' => 'Siswa "' . $student['full_name'] . '" tidak aktif.']);
    exit;
}

if (!$student['class_id']) {
    echo json_encode(['success' => false, 'message' => 'Siswa "' . $student['full_name'] . '" belum memiliki kelas.']);
    exit;
}

$today = date('Y-m-d');
$currentTime = date('H:i:s');

// Check if already attended today
$existing = $db->fetch(
    "SELECT id, status, scan_time FROM attendances WHERE student_id = ? AND date = ?", 
    [$student['id'], $today]
);

if ($existing) {
    $scanTime = date('H:i', strtotime($existing['scan_time']));
    $statusLabel = $existing['status'] === 'hadir' ? 'Hadir' : ucfirst($existing['status']);
    echo json_encode([
        'success' => false, 
        'message' => $student['full_name'] . ' sudah presensi hari ini (jam ' . $scanTime . ', status: ' . $statusLabel . ').'
    ]);
    exit;
}


// Determine late threshold
$lateThreshold = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'late_threshold_time'") ?: '07:00:00';

// Check per-class threshold if not global
$globalMode = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'late_threshold_global'") ?: '1';
if ($globalMode === '0' && $student['class_id']) {
    $classThreshold = $db->fetchColumn("SELECT late_threshold_time FROM classes WHERE id = ?", [$student['class_id']]);
    if ($classThreshold) {
        $lateThreshold = $classThreshold;
    }
}

// Determine status
$status = ($currentTime > $lateThreshold) ? 'terlambat' : 'hadir';

// Record attendance
try {
    $db->insert('attendances', [
        'student_id' => $student['id'],
        'class_id' => $student['class_id'],
        'date' => $today,
        'scan_time' => $currentTime,
        'status' => $status,
        'method' => 'qr_scan',
        'recorded_by' => Auth::getUserId()
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan presensi: ' . $e->getMessage()]);
    exit;
}

// Log
Auth::logActivity('scan_attendance', 'attendance', "Presensi QR: {$student['full_name']} - {$status}");

// AUTO POINTS: Record behavior based on attendance status
try {
    // Calculate late minutes
    $lateMinutes = 0;
    if ($status === 'terlambat') {
        $lateSeconds = strtotime($currentTime) - strtotime($lateThreshold);
        $lateMinutes = max(1, ceil($lateSeconds / 60));
    }

    // Find or create appropriate behavior category
    if ($status === 'terlambat') {
        // Find "Terlambat" pelanggaran category
        $catTerlambat = $db->fetch("SELECT id FROM behavior_categories WHERE type = 'pelanggaran' AND category_name LIKE '%erlambat%' AND is_active = 1 LIMIT 1");
        $categoryId = $catTerlambat['id'] ?? null;
        
        if ($categoryId) {
            $db->insert('behavior_records', [
                'student_id' => $student['id'],
                'category_id' => $categoryId,
                'type' => 'pelanggaran',
                'points' => -3,
                'description' => "Terlambat {$lateMinutes} menit (scan QR pukul " . date('H:i') . ")",
                'incident_date' => $today,
                'recorded_by' => Auth::getUserId(),
                'recorder_role' => Auth::getRole() === 'admin' ? 'admin' : 'wali_kelas',
                'validation_status' => 'approved',
                'show_to_parent' => 1
            ]);
        }
    } else {
        // Hadir tepat waktu = keteladanan +3
        $catDisiplin = $db->fetch("SELECT id FROM behavior_categories WHERE type = 'keteladanan' AND (category_name LIKE '%isiplin%' OR category_name LIKE '%epat waktu%') AND is_active = 1 LIMIT 1");
        $categoryId = $catDisiplin['id'] ?? null;
        
        if ($categoryId) {
            $db->insert('behavior_records', [
                'student_id' => $student['id'],
                'category_id' => $categoryId,
                'type' => 'keteladanan',
                'points' => 3,
                'description' => "Hadir tepat waktu (scan QR pukul " . date('H:i') . ")",
                'incident_date' => $today,
                'recorded_by' => Auth::getUserId(),
                'recorder_role' => Auth::getRole() === 'admin' ? 'admin' : 'wali_kelas',
                'validation_status' => 'approved',
                'show_to_parent' => 0
            ]);
        }
    }
} catch (Exception $e) {
    // Silent fail - attendance already recorded, point recording is bonus
    error_log("Auto-point error: " . $e->getMessage());
}

// Check for repeated lateness this week
if ($status === 'terlambat') {
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $lateCount = $db->count('attendances', "student_id = ? AND status = 'terlambat' AND date >= ?", [$student['id'], $weekStart]);
    if ($lateCount >= 3) {
        NotificationHelper::onRepeatedLateness($student['id'], $lateCount);
    }
}

// Success response
echo json_encode([
    'success' => true,
    'student_name' => $student['full_name'],
    'nis' => $student['nis'],
    'class_name' => 'Kelas ' . $student['grade_level'] . ' - ' . $student['class_name'],
    'time' => date('H:i', strtotime($currentTime)),
    'status' => $status,
    'status_label' => $status === 'hadir' ? 'Hadir' : 'Terlambat'
]);
