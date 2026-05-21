<?php
/**
 * Get Today's Attendance (AJAX endpoint)
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$today = date('Y-m-d');

// Get attendance for homeroom teacher's class or all for admin
$where = "a.date = ?";
$params = [$today];

if (Auth::getRole() === 'wali_kelas' && isset($_SESSION['class_id'])) {
    $where .= " AND a.class_id = ?";
    $params[] = $_SESSION['class_id'];
}

$attendances = $db->fetchAll("SELECT a.*, s.full_name, s.nis 
    FROM attendances a JOIN students s ON a.student_id = s.id 
    WHERE {$where} ORDER BY a.created_at DESC", $params);

$result = [];
foreach ($attendances as $a) {
    $result[] = [
        'full_name' => $a['full_name'],
        'nis' => $a['nis'],
        'scan_time' => date('H:i', strtotime($a['scan_time'])),
        'status' => $a['status'],
        'status_label' => $a['status'] === 'hadir' ? 'Hadir' : ($a['status'] === 'terlambat' ? 'Terlambat' : ucfirst($a['status']))
    ];
}

echo json_encode($result);
