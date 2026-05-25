<?php
/**
 * AJAX: Get behavior records for a student (for follow-up linking)
 * Returns JSON array of pelanggaran records
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    echo json_encode([]);
    exit;
}

$studentId = (int) ($_GET['student_id'] ?? 0);
if (!$studentId) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance();

// Get pelanggaran records for this student (most recent first)
$records = $db->fetchAll(
    "SELECT br.id, br.incident_date, br.points, br.description, br.type, bc.category_name, bc.severity
     FROM behavior_records br 
     JOIN behavior_categories bc ON br.category_id = bc.id 
     WHERE br.student_id = ? AND br.type = 'pelanggaran' AND br.validation_status = 'approved'
     ORDER BY br.incident_date DESC 
     LIMIT 30",
    [$studentId]
);

$result = [];
foreach ($records as $r) {
    $result[] = [
        'id' => $r['id'],
        'date' => date('d/m/Y', strtotime($r['incident_date'])),
        'category' => $r['category_name'],
        'severity' => ucfirst($r['severity']),
        'points' => $r['points'],
        'description' => $r['description'] ? mb_substr($r['description'], 0, 80) : '',
        'label' => date('d/m/Y', strtotime($r['incident_date'])) . ' - ' . $r['category_name'] . ' (' . $r['points'] . ' poin)'
    ];
}

echo json_encode($result);
