<?php
/**
 * Student Profile (Integrated) - Wali Kelas
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin', 'kepala_sekolah']);

$db = Database::getInstance();
$studentId = (int) get('id', 0);

if (!$studentId) {
    // Show student list
    define('PAGE_TITLE', 'Profil Siswa');
    $classId = $_SESSION['class_id'] ?? 0;
    
    if (Auth::getRole() === 'admin' || Auth::getRole() === 'kepala_sekolah') {
        $students = $db->fetchAll("SELECT s.*, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.is_active = 1 ORDER BY c.grade_level, c.class_name, s.full_name");
    } else {
        $students = $db->fetchAll("SELECT s.*, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.class_id = ? AND s.is_active = 1 ORDER BY s.full_name", [$classId]);
    }

    include __DIR__ . '/../../templates/header.php';
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Pilih Siswa</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <?php foreach ($students as $s): ?>
            <a href="?id=<?= $s['id'] ?>" class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-blue-200 hover:bg-blue-50 transition">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-blue-600 text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= $s['nis'] ?> | Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

// Load student data
$student = $db->fetch("SELECT s.*, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [$studentId]);
if (!$student) { setFlash('error', 'Siswa tidak ditemukan.'); redirect('modules/wali_kelas/student_profile.php'); }

define('PAGE_TITLE', 'Profil: ' . $student['full_name']);

$tab = get('tab', 'overview');

// Stats
$totalPositive = $db->fetchColumn("SELECT COALESCE(SUM(points), 0) FROM behavior_records WHERE student_id = ? AND type = 'keteladanan' AND validation_status = 'approved'", [$studentId]);
$totalNegative = $db->fetchColumn("SELECT COALESCE(SUM(ABS(points)), 0) FROM behavior_records WHERE student_id = ? AND type = 'pelanggaran' AND validation_status = 'approved'", [$studentId]);
$attendanceCount = $db->count('attendances', "student_id = ? AND status IN ('hadir','terlambat')", [$studentId]);
$lateCount = $db->count('attendances', "student_id = ? AND status = 'terlambat'", [$studentId]);
$achievementCount = $db->count('achievements', 'student_id = ?', [$studentId]);

include __DIR__ . '/../../templates/header.php';
?>

<!-- Student Header -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center text-white text-2xl font-bold">
            <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
        </div>
        <div class="flex-1">
            <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($student['full_name']) ?></h2>
            <p class="text-sm text-gray-500">NIS: <?= $student['nis'] ?> <?= $student['nisn'] ? '| NISN: ' . $student['nisn'] : '' ?> | Kelas <?= $student['grade_level'] ?>-<?= $student['class_name'] ?> | <?= $student['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></p>
        </div>
        <div class="grid grid-cols-4 gap-3 text-center">
            <div class="p-2">
                <p class="text-lg font-bold text-green-600">+<?= $totalPositive ?></p>
                <p class="text-[10px] text-gray-500">Keteladanan</p>
            </div>
            <div class="p-2">
                <p class="text-lg font-bold text-red-600">-<?= $totalNegative ?></p>
                <p class="text-[10px] text-gray-500">Pembinaan</p>
            </div>
            <div class="p-2">
                <p class="text-lg font-bold text-blue-600"><?= $attendanceCount ?></p>
                <p class="text-[10px] text-gray-500">Hadir</p>
            </div>
            <div class="p-2">
                <p class="text-lg font-bold text-purple-600"><?= $achievementCount ?></p>
                <p class="text-[10px] text-gray-500">Prestasi</p>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="flex gap-1 mb-6 overflow-x-auto">
    <?php 
    $tabs = ['overview'=>'Ringkasan','attendance'=>'Presensi','behavior'=>'Perilaku','followup'=>'Tindak Lanjut','achievement'=>'Prestasi','potential'=>'Potensi'];
    foreach ($tabs as $key => $label): ?>
    <a href="?id=<?= $studentId ?>&tab=<?= $key ?>" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap <?= $tab === $key ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<!-- Overview - Point Summary & Recent -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Perilaku Terbaru</h3>
        <?php $recentBehavior = $db->fetchAll("SELECT br.*, bc.category_name FROM behavior_records br JOIN behavior_categories bc ON br.category_id = bc.id WHERE br.student_id = ? AND br.validation_status = 'approved' ORDER BY br.incident_date DESC LIMIT 8", [$studentId]); ?>
        <div class="space-y-2">
            <?php foreach ($recentBehavior as $r): ?>
            <div class="flex items-center justify-between p-2 rounded <?= $r['type'] === 'keteladanan' ? 'bg-green-50' : 'bg-red-50' ?>">
                <div>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($r['category_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= formatDate($r['incident_date'], 'short') ?></p>
                </div>
                <span class="text-sm font-bold <?= $r['points'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $r['points'] > 0 ? '+' : '' ?><?= $r['points'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentBehavior)): ?><p class="text-sm text-gray-500 text-center py-4">Belum ada catatan.</p><?php endif; ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Presensi 30 Hari Terakhir</h3>
        <?php 
        $last30 = $db->fetchAll("SELECT status, COUNT(*) as total FROM attendances WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY status", [$studentId]);
        $attStats = array_column($last30, 'total', 'status');
        ?>
        <div class="grid grid-cols-2 gap-3">
            <div class="p-3 bg-green-50 rounded-lg text-center"><p class="text-xl font-bold text-green-600"><?= $attStats['hadir'] ?? 0 ?></p><p class="text-xs text-gray-500">Hadir</p></div>
            <div class="p-3 bg-yellow-50 rounded-lg text-center"><p class="text-xl font-bold text-yellow-600"><?= $attStats['terlambat'] ?? 0 ?></p><p class="text-xs text-gray-500">Terlambat</p></div>
            <div class="p-3 bg-blue-50 rounded-lg text-center"><p class="text-xl font-bold text-blue-600"><?= $attStats['sakit'] ?? 0 ?></p><p class="text-xs text-gray-500">Sakit</p></div>
            <div class="p-3 bg-red-50 rounded-lg text-center"><p class="text-xl font-bold text-red-600"><?= ($attStats['alpa'] ?? 0) + ($attStats['izin'] ?? 0) ?></p><p class="text-xs text-gray-500">Izin/Alpa</p></div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'behavior'): ?>
<?php $behaviors = $db->fetchAll("SELECT br.*, bc.category_name, u.full_name as recorder FROM behavior_records br JOIN behavior_categories bc ON br.category_id = bc.id LEFT JOIN users u ON br.recorded_by = u.id WHERE br.student_id = ? AND br.validation_status = 'approved' ORDER BY br.incident_date DESC", [$studentId]); ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Riwayat Perilaku</h3>
    <div class="space-y-3">
        <?php foreach ($behaviors as $b): ?>
        <div class="flex items-start gap-3 p-3 rounded-lg border <?= $b['type'] === 'keteladanan' ? 'border-green-100 bg-green-50/50' : 'border-red-100 bg-red-50/50' ?>">
            <div class="w-8 h-8 rounded-full flex items-center justify-center <?= $b['type'] === 'keteladanan' ? 'bg-green-100' : 'bg-red-100' ?>">
                <i class="fas <?= $b['type'] === 'keteladanan' ? 'fa-thumbs-up text-green-600' : 'fa-exclamation text-red-600' ?> text-xs"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($b['category_name']) ?></p>
                <?php if ($b['description']): ?><p class="text-xs text-gray-500"><?= htmlspecialchars($b['description']) ?></p><?php endif; ?>
                <p class="text-xs text-gray-400 mt-1"><?= formatDate($b['incident_date'], 'short') ?> | <?= htmlspecialchars($b['recorder']) ?></p>
            </div>
            <span class="text-sm font-bold <?= $b['points'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $b['points'] > 0 ? '+' : '' ?><?= $b['points'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($behaviors)): ?><p class="text-center py-8 text-gray-500">Belum ada catatan perilaku.</p><?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'attendance'): ?>
<?php $attendances = $db->fetchAll("SELECT * FROM attendances WHERE student_id = ? ORDER BY date DESC LIMIT 60", [$studentId]); ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Riwayat Presensi</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Tanggal</th>
                <th class="px-4 py-2 text-center font-medium text-gray-600">Jam</th>
                <th class="px-4 py-2 text-center font-medium text-gray-600">Status</th>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Catatan</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($attendances as $a): ?>
                <tr><td class="px-4 py-2"><?= formatDate($a['date'], 'short') ?></td><td class="px-4 py-2 text-center"><?= $a['scan_time'] ? formatTime($a['scan_time']) : '-' ?></td><td class="px-4 py-2 text-center"><?= statusBadge($a['status'], 'attendance') ?></td><td class="px-4 py-2 text-gray-500 text-xs"><?= htmlspecialchars($a['notes'] ?? '-') ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'followup'): ?>
<?php $followUps = $db->fetchAll("SELECT f.*, u.full_name as creator FROM follow_ups f LEFT JOIN users u ON f.created_by = u.id WHERE f.student_id = ? ORDER BY f.follow_up_date DESC", [$studentId]); ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Riwayat Tindak Lanjut</h3>
    <div class="space-y-3">
        <?php foreach ($followUps as $f): ?>
        <div class="p-4 border border-gray-100 rounded-lg">
            <div class="flex items-center gap-2 mb-2"><?= statusBadge($f['status'], 'followup') ?><span class="text-xs text-gray-400"><?= formatDate($f['follow_up_date'], 'short') ?></span></div>
            <p class="text-sm font-medium text-gray-800"><?= ucfirst(str_replace('_', ' ', $f['follow_up_type'])) ?></p>
            <p class="text-sm text-gray-600"><?= htmlspecialchars($f['description']) ?></p>
            <?php if ($f['result']): ?><p class="text-xs text-green-600 mt-2"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($f['result']) ?></p><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($followUps)): ?><p class="text-center py-8 text-gray-500">Belum ada tindak lanjut.</p><?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'achievement'): ?>
<?php $achievements = $db->fetchAll("SELECT * FROM achievements WHERE student_id = ? ORDER BY achievement_date DESC", [$studentId]); ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Prestasi Siswa</h3>
    <div class="space-y-3">
        <?php foreach ($achievements as $a): ?>
        <div class="p-4 border border-yellow-100 bg-yellow-50/50 rounded-lg">
            <p class="font-medium text-gray-800"><i class="fas fa-trophy text-yellow-500"></i> <?= htmlspecialchars($a['title']) ?></p>
            <p class="text-sm text-gray-600"><?= ucfirst(str_replace('_', ' ', $a['category'])) ?> - Tingkat <?= ucfirst($a['level']) ?></p>
            <p class="text-xs text-gray-400"><?= formatDate($a['achievement_date'], 'long') ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($achievements)): ?><p class="text-center py-8 text-gray-500">Belum ada prestasi.</p><?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'potential'): ?>
<?php $potentials = $db->fetchAll("SELECT p.*, u.full_name as recorder FROM potentials p LEFT JOIN users u ON p.recorded_by = u.id WHERE p.student_id = ? ORDER BY p.created_at DESC", [$studentId]); ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Potensi Siswa</h3>
    <div class="space-y-3">
        <?php foreach ($potentials as $p): ?>
        <div class="p-4 border border-purple-100 bg-purple-50/50 rounded-lg">
            <p class="font-medium text-gray-800"><i class="fas fa-lightbulb text-purple-500"></i> <?= ucfirst(str_replace('_', ' ', $p['field'])) ?></p>
            <p class="text-sm text-gray-600"><?= htmlspecialchars($p['description']) ?></p>
            <?php if ($p['recommendation']): ?><p class="text-xs text-purple-600 mt-1"><i class="fas fa-arrow-right"></i> <?= htmlspecialchars($p['recommendation']) ?></p><?php endif; ?>
            <p class="text-xs text-gray-400 mt-1">Dicatat oleh: <?= htmlspecialchars($p['recorder']) ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($potentials)): ?><p class="text-center py-8 text-gray-500">Belum ada data potensi.</p><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
