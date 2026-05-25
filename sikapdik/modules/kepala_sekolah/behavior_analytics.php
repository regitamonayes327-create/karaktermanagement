<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['kepala_sekolah']);
define('PAGE_TITLE', 'Analitik Perilaku');
$db = Database::getInstance();
$month = get('month', date('Y-m'));
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

$summary = $db->fetchAll("SELECT br.type, COUNT(*) as total, SUM(ABS(br.points)) as total_points FROM behavior_records br WHERE br.validation_status = 'approved' AND br.incident_date BETWEEN ? AND ? GROUP BY br.type", [$startDate, $endDate]);
$sumMap = []; foreach ($summary as $s) $sumMap[$s['type']] = $s;

$topPositive = $db->fetchAll("SELECT s.full_name, c.class_name, c.grade_level, SUM(br.points) as total FROM behavior_records br JOIN students s ON br.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id WHERE br.type = 'keteladanan' AND br.validation_status = 'approved' AND br.incident_date BETWEEN ? AND ? GROUP BY s.id ORDER BY total DESC LIMIT 10", [$startDate, $endDate]);

$topNegative = $db->fetchAll("SELECT s.full_name, c.class_name, c.grade_level, SUM(ABS(br.points)) as total FROM behavior_records br JOIN students s ON br.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id WHERE br.type = 'pelanggaran' AND br.validation_status = 'approved' AND br.incident_date BETWEEN ? AND ? GROUP BY s.id ORDER BY total DESC LIMIT 10", [$startDate, $endDate]);

include __DIR__ . '/../../templates/header.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex gap-3 items-end">
        <div class="flex-1"><label class="block text-xs font-medium text-gray-600 mb-1">Bulan</label><input type="month" name="month" value="<?= $month ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"></div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm"><i class="fas fa-filter"></i> Filter</button>
    </form>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-green-50 border border-green-100 rounded-xl p-5"><p class="text-sm text-gray-600">Total Keteladanan</p><p class="text-3xl font-bold text-green-600"><?= $sumMap['keteladanan']['total'] ?? 0 ?></p><p class="text-xs text-green-500">+<?= $sumMap['keteladanan']['total_points'] ?? 0 ?> poin</p></div>
    <div class="bg-red-50 border border-red-100 rounded-xl p-5"><p class="text-sm text-gray-600">Total Pelanggaran</p><p class="text-3xl font-bold text-red-600"><?= $sumMap['pelanggaran']['total'] ?? 0 ?></p><p class="text-xs text-red-500">-<?= $sumMap['pelanggaran']['total_points'] ?? 0 ?> poin</p></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-trophy text-green-500"></i> Top Keteladanan</h3>
        <div class="space-y-2"><?php foreach ($topPositive as $i => $s): ?>
        <div class="flex items-center justify-between p-2 bg-green-50 rounded-lg"><div class="flex items-center gap-2"><span class="w-6 h-6 bg-green-200 rounded-full flex items-center justify-center text-xs font-bold text-green-700"><?= $i+1 ?></span><div><p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p><p class="text-xs text-gray-500">Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></p></div></div><span class="font-bold text-green-600">+<?= $s['total'] ?></span></div>
        <?php endforeach; ?><?php if (empty($topPositive)): ?><p class="text-sm text-gray-500 text-center py-4">Belum ada data.</p><?php endif; ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-exclamation-triangle text-red-500"></i> Top Pelanggaran</h3>
        <div class="space-y-2"><?php foreach ($topNegative as $i => $s): ?>
        <div class="flex items-center justify-between p-2 bg-red-50 rounded-lg"><div class="flex items-center gap-2"><span class="w-6 h-6 bg-red-200 rounded-full flex items-center justify-center text-xs font-bold text-red-700"><?= $i+1 ?></span><div><p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p><p class="text-xs text-gray-500">Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></p></div></div><span class="font-bold text-red-600">-<?= $s['total'] ?></span></div>
        <?php endforeach; ?><?php if (empty($topNegative)): ?><p class="text-sm text-gray-500 text-center py-4">Belum ada data.</p><?php endif; ?></div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
