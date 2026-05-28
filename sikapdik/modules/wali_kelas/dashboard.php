<?php
/**
 * Homeroom Teacher Dashboard
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas']);

define('PAGE_TITLE', 'Dashboard Wali Kelas');

$db = Database::getInstance();
$classId = $_SESSION['class_id'] ?? 0;
$className = $_SESSION['class_name'] ?? '';
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// Class stats
$totalStudents = $db->count('students', 'class_id = ? AND is_active = 1', [$classId]);
$todayPresent = $db->count('attendances', "class_id = ? AND date = ? AND status IN ('hadir','terlambat')", [$classId, $today]);
$todayLate = $db->count('attendances', "class_id = ? AND date = ? AND status = 'terlambat'", [$classId, $today]);
$notYetPresent = $totalStudents - $db->count('attendances', "class_id = ? AND date = ?", [$classId, $today]);

// This week behavior
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekBehavior = $db->fetchAll("SELECT br.*, s.full_name, bc.category_name 
    FROM behavior_records br JOIN students s ON br.student_id = s.id JOIN behavior_categories bc ON br.category_id = bc.id
    WHERE s.class_id = ? AND br.incident_date >= ? AND br.validation_status = 'approved'
    ORDER BY br.created_at DESC LIMIT 10", [$classId, $weekStart]);

// Pending teacher notes
$pendingNotes = $db->count('behavior_records', "recorder_role = 'guru_mapel' AND validation_status = 'pending' AND student_id IN (SELECT id FROM students WHERE class_id = ?)", [$classId]);


// Students needing follow-up
$needsFollowUp = $db->fetchAll("SELECT s.full_name, s.nis, COUNT(br.id) as violation_count 
    FROM behavior_records br JOIN students s ON br.student_id = s.id 
    WHERE s.class_id = ? AND br.type = 'pelanggaran' AND br.validation_status = 'approved' AND br.incident_date >= ?
    GROUP BY s.id HAVING violation_count >= 3 ORDER BY violation_count DESC LIMIT 5", [$classId, $weekStart]);

// --- Chart Data: Top 5 students by keteladanan points ---
$topStudents = $db->fetchAll("SELECT s.full_name, SUM(br.points) as total FROM behavior_records br JOIN students s ON br.student_id = s.id WHERE s.class_id = ? AND br.type='keteladanan' AND br.validation_status='approved' AND br.incident_date >= ? GROUP BY s.id ORDER BY total DESC LIMIT 5", [$classId, $monthStart]);

// --- Chart Data: Attendance distribution this month ---
$attDist = $db->fetch("SELECT 
    SUM(CASE WHEN status='hadir' THEN 1 ELSE 0 END) as hadir,
    SUM(CASE WHEN status='terlambat' THEN 1 ELSE 0 END) as terlambat,
    SUM(CASE WHEN status='sakit' THEN 1 ELSE 0 END) as sakit,
    SUM(CASE WHEN status='izin' THEN 1 ELSE 0 END) as izin,
    SUM(CASE WHEN status='alpa' THEN 1 ELSE 0 END) as alpa
    FROM attendances WHERE class_id = ? AND date BETWEEN ? AND ?", [$classId, $monthStart, date('Y-m-t')]);

include __DIR__ . '/../../templates/header.php';
?>

<!-- Quick Info -->
<div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl p-6 mb-6 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold">Kelas <?= htmlspecialchars($className) ?></h3>
            <p class="text-sm text-blue-100"><?= formatDate($today, 'full') ?></p>
        </div>
        <div class="flex gap-4">
            <a href="<?= BASE_URL ?>modules/wali_kelas/scan_qr.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition"><i class="fas fa-qrcode"></i> Scan QR</a>
            <a href="<?= BASE_URL ?>modules/wali_kelas/input_behavior.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition"><i class="fas fa-star"></i> Input Perilaku</a>
        </div>
    </div>
</div>


<!-- Stats -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-green-600"><?= $todayPresent ?></p>
        <p class="text-xs text-gray-500">Hadir Hari Ini</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-yellow-600"><?= $todayLate ?></p>
        <p class="text-xs text-gray-500">Terlambat</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-red-600"><?= $notYetPresent ?></p>
        <p class="text-xs text-gray-500">Belum Presensi</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-purple-600"><?= $pendingNotes ?></p>
        <p class="text-xs text-gray-500">Catatan Guru Mapel</p>
    </div>
</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Top 5 Siswa Keteladanan</h3>
        <canvas id="topStudentsChart" height="200"></canvas>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Distribusi Presensi Bulan Ini</h3>
        <canvas id="attDistChart" height="200"></canvas>
    </div>
</div>
<script>
new Chart(document.getElementById('topStudentsChart'), {
    type: 'bar',
    data: {
        labels: [<?= implode(',', array_map(fn($s) => "'".addslashes($s['full_name'])."'", $topStudents)) ?>],
        datasets: [{label:'Poin Keteladanan', data:[<?= implode(',', array_column($topStudents, 'total')) ?>], backgroundColor:'#10b981'}]
    },
    options: {indexAxis:'y', responsive:true, plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true}}}
});

new Chart(document.getElementById('attDistChart'), {
    type: 'doughnut',
    data: {
        labels: ['Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa'],
        datasets: [{data:[<?= (int)($attDist['hadir'] ?? 0) ?>, <?= (int)($attDist['terlambat'] ?? 0) ?>, <?= (int)($attDist['sakit'] ?? 0) ?>, <?= (int)($attDist['izin'] ?? 0) ?>, <?= (int)($attDist['alpa'] ?? 0) ?>], backgroundColor:['#10b981','#f59e0b','#3b82f6','#8b5cf6','#ef4444']}]
    },
    options: {responsive:true, plugins:{legend:{position:'bottom'}}}
});
</script>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- This Week Behavior -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Catatan Perilaku Minggu Ini</h3>
        <div class="space-y-2 max-h-64 overflow-y-auto">
            <?php if (empty($weekBehavior)): ?>
            <p class="text-sm text-gray-500 text-center py-4">Belum ada catatan minggu ini.</p>
            <?php else: ?>
            <?php foreach ($weekBehavior as $b): ?>
            <div class="flex items-center justify-between p-2 rounded <?= $b['type'] === 'keteladanan' ? 'bg-green-50' : 'bg-red-50' ?>">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($b['full_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($b['category_name']) ?></p>
                </div>
                <span class="text-sm font-bold <?= $b['points'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $b['points'] > 0 ? '+' : '' ?><?= $b['points'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>


    <!-- Needs Follow-up -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle text-orange-500"></i> Siswa Perlu Perhatian
        </h3>
        <div class="space-y-2">
            <?php if (empty($needsFollowUp)): ?>
            <p class="text-sm text-gray-500 text-center py-4">Tidak ada siswa yang perlu perhatian khusus.</p>
            <?php else: ?>
            <?php foreach ($needsFollowUp as $s): ?>
            <div class="flex items-center justify-between p-3 rounded-lg bg-orange-50 border border-orange-100">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= $s['nis'] ?></p>
                </div>
                <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold"><?= $s['violation_count'] ?> pelanggaran</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="<?= BASE_URL ?>modules/wali_kelas/follow_ups.php?action=add" class="mt-4 inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-plus"></i> Buat Tindak Lanjut</a>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
