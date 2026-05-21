<?php
/**
 * Subject Teacher Dashboard
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['guru_mapel']);

define('PAGE_TITLE', 'Dashboard Guru Mapel');

$db = Database::getInstance();
$teacherId = $_SESSION['teacher_id'] ?? 0;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// My stats this month
$myPositive = $db->count('behavior_records', "recorded_by = ? AND type = 'keteladanan' AND incident_date >= ?", [Auth::getUserId(), $monthStart]);
$myNegative = $db->count('behavior_records', "recorded_by = ? AND type = 'pelanggaran' AND incident_date >= ?", [Auth::getUserId(), $monthStart]);
$myPending = $db->count('behavior_records', "recorded_by = ? AND validation_status = 'pending'", [Auth::getUserId()]);
$myTotal = $db->count('behavior_records', "recorded_by = ?", [Auth::getUserId()]);

// My classes
$myClasses = $db->fetchAll("SELECT c.id, c.class_name, c.grade_level, tca.subject,
    (SELECT COUNT(*) FROM students WHERE class_id = c.id AND is_active = 1) as student_count
    FROM teacher_class_assignments tca JOIN classes c ON tca.class_id = c.id 
    WHERE tca.teacher_id = ? AND c.is_active = 1 ORDER BY c.grade_level, c.class_name", [$teacherId]);

// Recent records
$recentRecords = $db->fetchAll("SELECT br.*, s.full_name, bc.category_name 
    FROM behavior_records br JOIN students s ON br.student_id = s.id JOIN behavior_categories bc ON br.category_id = bc.id
    WHERE br.recorded_by = ? ORDER BY br.created_at DESC LIMIT 8", [Auth::getUserId()]);

include __DIR__ . '/../../templates/header.php';
?>

<!-- Quick Actions -->
<div class="bg-gradient-to-r from-green-600 to-teal-600 rounded-xl p-6 mb-6 text-white">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-lg font-semibold">Selamat Datang, <?= htmlspecialchars(Auth::getFullName()) ?></h3>
            <p class="text-sm text-green-100"><?= formatDate($today, 'full') ?></p>
        </div>
        <div class="flex gap-3">
            <a href="<?= BASE_URL ?>modules/guru_mapel/input_positive.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition"><i class="fas fa-thumbs-up"></i> Keteladanan</a>
            <a href="<?= BASE_URL ?>modules/guru_mapel/input_negative.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition"><i class="fas fa-exclamation-circle"></i> Pelanggaran</a>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-green-600"><?= $myPositive ?></p>
        <p class="text-xs text-gray-500">Keteladanan Bulan Ini</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-red-600"><?= $myNegative ?></p>
        <p class="text-xs text-gray-500">Pelanggaran Bulan Ini</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-yellow-600"><?= $myPending ?></p>
        <p class="text-xs text-gray-500">Menunggu Validasi</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-blue-600"><?= $myTotal ?></p>
        <p class="text-xs text-gray-500">Total Catatan</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- My Classes -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-school text-blue-500"></i> Kelas yang Diajar</h3>
        <div class="space-y-2">
            <?php if (empty($myClasses)): ?>
            <p class="text-sm text-gray-500 text-center py-4">Belum ada penugasan kelas. Hubungi admin.</p>
            <?php else: ?>
            <?php foreach ($myClasses as $c): ?>
            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                <div>
                    <p class="text-sm font-medium text-gray-800">Kelas <?= $c['grade_level'] ?> - <?= $c['class_name'] ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($c['subject'] ?? 'Mapel') ?></p>
                </div>
                <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs"><?= $c['student_count'] ?> siswa</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Records -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-history text-purple-500"></i> Catatan Terbaru</h3>
        <div class="space-y-2 max-h-64 overflow-y-auto">
            <?php if (empty($recentRecords)): ?>
            <p class="text-sm text-gray-500 text-center py-4">Belum ada catatan.</p>
            <?php else: ?>
            <?php foreach ($recentRecords as $r): ?>
            <div class="flex items-center justify-between p-2 rounded <?= $r['type'] === 'keteladanan' ? 'bg-green-50' : 'bg-red-50' ?>">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($r['full_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($r['category_name']) ?></p>
                </div>
                <div class="text-right">
                    <span class="text-xs font-bold <?= $r['points'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $r['points'] > 0 ? '+' : '' ?><?= $r['points'] ?></span>
                    <p class="text-[10px] text-gray-400"><?= statusBadge($r['validation_status'], 'validation') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
