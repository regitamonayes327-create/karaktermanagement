<?php
/**
 * Principal Dashboard
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['kepala_sekolah']);

define('PAGE_TITLE', 'Dashboard Kepala Sekolah');

$db = Database::getInstance();
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Stats
$totalStudents = $db->count('students', 'is_active = 1');
$totalClasses = $db->count('classes', 'is_active = 1');

// Today attendance
$todayTotal = $db->count('attendances', "date = ?", [$today]);
$todayPresent = $db->count('attendances', "date = ? AND status IN ('hadir','terlambat')", [$today]);
$todayLate = $db->count('attendances', "date = ? AND status = 'terlambat'", [$today]);
$todayAbsent = $db->count('attendances', "date = ? AND status = 'alpa'", [$today]);
$attendancePercentage = $totalStudents > 0 ? round(($todayPresent / $totalStudents) * 100) : 0;

// This month behavior
$monthPositive = $db->count('behavior_records', "type = 'keteladanan' AND validation_status = 'approved' AND incident_date BETWEEN ? AND ?", [$monthStart, $monthEnd]);
$monthNegative = $db->count('behavior_records', "type = 'pelanggaran' AND validation_status = 'approved' AND incident_date BETWEEN ? AND ?", [$monthStart, $monthEnd]);

// Pending follow-ups
$pendingFollowUps = $db->count('follow_ups', "status IN ('belum_diproses','dalam_pemantauan')");

// Students needing attention (high negative points)
$attentionStudents = $db->fetchAll("SELECT s.id, s.full_name, s.nis, c.class_name, c.grade_level,
    COALESCE(SUM(CASE WHEN br.type = 'pelanggaran' THEN ABS(br.points) ELSE 0 END), 0) as neg_points,
    COALESCE(SUM(CASE WHEN br.type = 'keteladanan' THEN br.points ELSE 0 END), 0) as pos_points
    FROM students s LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN behavior_records br ON s.id = br.student_id AND br.validation_status = 'approved' AND br.incident_date BETWEEN ? AND ?
    WHERE s.is_active = 1 GROUP BY s.id HAVING neg_points > 10 ORDER BY neg_points DESC LIMIT 10", [$monthStart, $monthEnd]);

// Top achievers
$topStudents = $db->fetchAll("SELECT s.full_name, c.class_name, c.grade_level,
    SUM(br.points) as total_points
    FROM behavior_records br JOIN students s ON br.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id
    WHERE br.type = 'keteladanan' AND br.validation_status = 'approved' AND br.incident_date BETWEEN ? AND ?
    GROUP BY s.id ORDER BY total_points DESC LIMIT 5", [$monthStart, $monthEnd]);

// Class comparison
$classStats = $db->fetchAll("SELECT c.class_name, c.grade_level,
    (SELECT COUNT(*) FROM attendances a JOIN students st ON a.student_id = st.id WHERE st.class_id = c.id AND a.date = ? AND a.status = 'terlambat') as late_count,
    (SELECT COALESCE(SUM(CASE WHEN br2.type = 'keteladanan' THEN br2.points ELSE 0 END), 0) FROM behavior_records br2 JOIN students st2 ON br2.student_id = st2.id WHERE st2.class_id = c.id AND br2.incident_date BETWEEN ? AND ? AND br2.validation_status = 'approved') as positive_points
    FROM classes c WHERE c.is_active = 1 ORDER BY c.grade_level, c.class_name", [$today, $monthStart, $monthEnd]);

include __DIR__ . '/../../templates/header.php';
?>

<!-- Overview Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Kehadiran Hari Ini</p>
                <p class="text-2xl font-bold text-gray-800"><?= $attendancePercentage ?>%</p>
                <p class="text-xs text-gray-400"><?= $todayPresent ?>/<?= $totalStudents ?> siswa</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-chart-pie text-green-600 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Terlambat Hari Ini</p>
                <p class="text-2xl font-bold text-yellow-600"><?= $todayLate ?></p>
                <p class="text-xs text-gray-400">siswa</p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-clock text-yellow-600 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Keteladanan Bulan Ini</p>
                <p class="text-2xl font-bold text-green-600"><?= $monthPositive ?></p>
                <p class="text-xs text-gray-400">catatan positif</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-star text-green-600 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Tindak Lanjut Aktif</p>
                <p class="text-2xl font-bold text-orange-600"><?= $pendingFollowUps ?></p>
                <p class="text-xs text-gray-400">kasus</p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-hands-helping text-orange-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Students Needing Attention -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle text-red-500"></i> Siswa Perlu Perhatian
        </h3>
        <div class="space-y-2 max-h-64 overflow-y-auto">
            <?php if (empty($attentionStudents)): ?>
            <p class="text-sm text-gray-500 text-center py-4">Tidak ada siswa yang memerlukan perhatian khusus bulan ini.</p>
            <?php else: ?>
            <?php foreach ($attentionStudents as $s): ?>
            <div class="flex items-center justify-between p-2 rounded-lg bg-red-50">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p>
                    <p class="text-xs text-gray-500">Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-bold text-red-600">-<?= $s['neg_points'] ?></p>
                    <p class="text-xs text-green-600">+<?= $s['pos_points'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Achievers -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-trophy text-yellow-500"></i> Siswa Teladan Bulan Ini
        </h3>
        <div class="space-y-2">
            <?php if (empty($topStudents)): ?>
            <p class="text-sm text-gray-500 text-center py-4">Belum ada data bulan ini.</p>
            <?php else: ?>
            <?php foreach ($topStudents as $i => $s): ?>
            <div class="flex items-center gap-3 p-2 rounded-lg bg-green-50">
                <div class="w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center text-white font-bold text-sm"><?= $i + 1 ?></div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p>
                    <p class="text-xs text-gray-500">Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></p>
                </div>
                <span class="text-sm font-bold text-green-600">+<?= $s['total_points'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Class Comparison -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-chart-bar text-blue-500"></i> Perbandingan Antar Kelas
    </h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Kelas</th>
                <th class="px-4 py-2 text-center font-medium text-gray-600">Terlambat Hari Ini</th>
                <th class="px-4 py-2 text-center font-medium text-gray-600">Poin Positif Bulan Ini</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($classStats as $cs): ?>
                <tr>
                    <td class="px-4 py-2 font-medium">Kelas <?= $cs['grade_level'] ?> - <?= $cs['class_name'] ?></td>
                    <td class="px-4 py-2 text-center <?= $cs['late_count'] > 0 ? 'text-yellow-600 font-bold' : 'text-gray-400' ?>"><?= $cs['late_count'] ?></td>
                    <td class="px-4 py-2 text-center text-green-600 font-bold"><?= $cs['positive_points'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
