<?php
/**
 * Parent Dashboard
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['orang_tua']);

define('PAGE_TITLE', 'Dashboard Orang Tua');

$db = Database::getInstance();
$parentId = $_SESSION['parent_id'] ?? 0;

// Tahun ajaran aktif
$tahunAjaran = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'active_academic_year'") ?: '-';
$semester = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'active_semester'") ?: '1';

// Get children
$children = $db->fetchAll("SELECT s.*, c.class_name, c.grade_level 
    FROM parent_student ps JOIN students s ON ps.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id
    WHERE ps.parent_id = ? AND s.is_active = 1", [$parentId]);

$selectedChild = (int) get('child', $children[0]['id'] ?? 0);
$child = null;
foreach ($children as $c) {
    if ($c['id'] == $selectedChild) { $child = $c; break; }
}
if (!$child && !empty($children)) { $child = $children[0]; $selectedChild = $child['id']; }

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Child data
$attendanceThisMonth = [];
$positiveRecords = [];
$pelanggaranRecords = [];
$achievements = [];
$followUps = [];
$notifications = [];

if ($child) {
    // Attendance this month
    $attStats = $db->fetchAll("SELECT status, COUNT(*) as total FROM attendances WHERE student_id = ? AND date BETWEEN ? AND ? GROUP BY status", [$selectedChild, $monthStart, $monthEnd]);
    $attendanceThisMonth = array_column($attStats, 'total', 'status');

    // Positive behavior (show_to_parent or all mode)
    $parentViewMode = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'parent_view_mode'") ?: 'selected';
    $showCondition = $parentViewMode === 'all' ? "1=1" : "br.show_to_parent = 1";
    
    $positiveRecords = $db->fetchAll("SELECT br.*, bc.category_name FROM behavior_records br 
        JOIN behavior_categories bc ON br.category_id = bc.id 
        WHERE br.student_id = ? AND br.type = 'keteladanan' AND br.validation_status = 'approved' AND {$showCondition}
        ORDER BY br.incident_date DESC LIMIT 10", [$selectedChild]);

    // Pelanggaran visible to parent
    $pelanggaranRecords = $db->fetchAll("SELECT br.*, bc.category_name FROM behavior_records br 
        JOIN behavior_categories bc ON br.category_id = bc.id 
        WHERE br.student_id = ? AND br.type = 'pelanggaran' AND br.validation_status = 'approved' AND br.show_to_parent = 1
        ORDER BY br.incident_date DESC LIMIT 10", [$selectedChild]);

    // Achievements
    $achievements = $db->fetchAll("SELECT * FROM achievements WHERE student_id = ? AND show_to_parent = 1 ORDER BY achievement_date DESC LIMIT 5", [$selectedChild]);

    // Follow-ups visible to parent
    $followUps = $db->fetchAll("SELECT * FROM follow_ups WHERE student_id = ? AND show_to_parent = 1 ORDER BY follow_up_date DESC LIMIT 5", [$selectedChild]);

    // Notifications
    $notifications = $db->fetchAll("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", [Auth::getUserId()]);
}

include __DIR__ . '/../../templates/header.php';
?>

<?php if (count($children) > 1): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <div class="flex items-center gap-3 flex-wrap">
        <span class="text-sm text-gray-600">Pilih Anak:</span>
        <?php foreach ($children as $c): ?>
        <a href="?child=<?= $c['id'] ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $selectedChild == $c['id'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>"><?= htmlspecialchars($c['full_name']) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($child): ?>
<!-- Child Info -->
<div class="bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl p-6 mb-6 text-white">
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center text-2xl font-bold"><?= strtoupper(substr($child['full_name'], 0, 1)) ?></div>
        <div>
            <h3 class="text-lg font-semibold"><?= htmlspecialchars($child['full_name']) ?></h3>
            <p class="text-sm text-blue-100">Kelas <?= $child['grade_level'] ?> - <?= $child['class_name'] ?> | NIS: <?= $child['nis'] ?></p>
            <p class="text-xs text-blue-200 mt-1">Tahun Ajaran: <?= $tahunAjaran ?> | Semester <?= $semester ?></p>
        </div>
    </div>
</div>

<!-- Attendance Summary -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center">
        <p class="text-xl font-bold text-green-600"><?= $attendanceThisMonth['hadir'] ?? 0 ?></p>
        <p class="text-xs text-gray-500">Hadir</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center">
        <p class="text-xl font-bold text-yellow-600"><?= $attendanceThisMonth['terlambat'] ?? 0 ?></p>
        <p class="text-xs text-gray-500">Terlambat</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center">
        <p class="text-xl font-bold text-blue-600"><?= $attendanceThisMonth['sakit'] ?? 0 ?></p>
        <p class="text-xs text-gray-500">Sakit</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center">
        <p class="text-xl font-bold text-purple-600"><?= $attendanceThisMonth['izin'] ?? 0 ?></p>
        <p class="text-xs text-gray-500">Izin</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center">
        <p class="text-xl font-bold text-red-600"><?= $attendanceThisMonth['alpa'] ?? 0 ?></p>
        <p class="text-xs text-gray-500">Alpa</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Positive Behavior -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-star text-green-500"></i> Perilaku Positif</h3>
        <div class="space-y-2">
            <?php if (empty($positiveRecords)): ?>
            <p class="text-sm text-gray-500 text-center py-4">Belum ada catatan keteladanan.</p>
            <?php else: ?>
            <?php foreach ($positiveRecords as $r): ?>
            <div class="flex items-center justify-between p-2 rounded-lg bg-green-50">
                <div>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($r['category_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= formatDate($r['incident_date'], 'short') ?></p>
                </div>
                <span class="text-sm font-bold text-green-600">+<?= $r['points'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pelanggaran Records (visible to parent) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-exclamation-triangle text-red-500"></i> Catatan Pelanggaran</h3>
        <div class="space-y-2">
            <?php if (empty($pelanggaranRecords)): ?>
            <p class="text-sm text-gray-500 text-center py-4">Tidak ada catatan pelanggaran.</p>
            <?php else: ?>
            <?php foreach ($pelanggaranRecords as $r): ?>
            <div class="flex items-center justify-between p-2 rounded-lg bg-red-50">
                <div>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($r['category_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= formatDate($r['incident_date'], 'short') ?></p>
                    <?php if ($r['description']): ?><p class="text-xs text-red-500 mt-0.5"><?= htmlspecialchars(mb_strimwidth($r['description'], 0, 60, '...')) ?></p><?php endif; ?>
                </div>
                <span class="text-sm font-bold text-red-600"><?= $r['points'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Achievements & Follow-ups -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-trophy text-yellow-500"></i> Prestasi</h3>
        <?php if (empty($achievements)): ?>
        <p class="text-sm text-gray-500 text-center py-2">Belum ada prestasi.</p>
        <?php else: ?>
        <?php foreach ($achievements as $a): ?>
        <div class="p-2 rounded bg-yellow-50 mb-2">
            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($a['title']) ?></p>
            <p class="text-xs text-gray-500"><?= ucfirst($a['level']) ?> | <?= formatDate($a['achievement_date'], 'short') ?></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($followUps)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-info-circle text-blue-500"></i> Catatan Pembinaan</h3>
        <?php foreach ($followUps as $f): ?>
        <div class="p-3 rounded-lg bg-blue-50 border border-blue-100 mb-2">
            <p class="text-sm text-gray-700"><?= htmlspecialchars($f['description']) ?></p>
            <p class="text-xs text-gray-400 mt-1"><?= formatDate($f['follow_up_date'], 'short') ?> | <?= statusBadge($f['status'], 'followup') ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="text-center py-12 text-gray-500">
    <i class="fas fa-user-graduate text-4xl text-gray-300 mb-4"></i>
    <p>Belum ada data anak yang terhubung. Silakan hubungi sekolah.</p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
