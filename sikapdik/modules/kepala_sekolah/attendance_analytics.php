<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['kepala_sekolah']);
define('PAGE_TITLE', 'Analitik Presensi');
$db = Database::getInstance();
$month = get('month', date('Y-m'));
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Per-class attendance stats
$classStats = $db->fetchAll("SELECT c.class_name, c.grade_level,
    SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
    SUM(CASE WHEN a.status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
    SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
    SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) as izin,
    SUM(CASE WHEN a.status = 'alpa' THEN 1 ELSE 0 END) as alpa,
    COUNT(*) as total
    FROM attendances a JOIN classes c ON a.class_id = c.id
    WHERE a.date BETWEEN ? AND ? GROUP BY c.id ORDER BY c.grade_level, c.class_name", [$startDate, $endDate]);

// Late students ranking
$lateStudents = $db->fetchAll("SELECT s.full_name, s.nis, c.class_name, c.grade_level, COUNT(*) as late_count
    FROM attendances a JOIN students s ON a.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id
    WHERE a.status = 'terlambat' AND a.date BETWEEN ? AND ?
    GROUP BY s.id ORDER BY late_count DESC LIMIT 15", [$startDate, $endDate]);

include __DIR__ . '/../../templates/header.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex gap-3 items-end">
        <div class="flex-1"><label class="block text-xs font-medium text-gray-600 mb-1">Bulan</label><input type="month" name="month" value="<?= $month ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"></div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm"><i class="fas fa-filter"></i> Filter</button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <h3 class="font-semibold text-gray-800 mb-4">Rekap Presensi Per Kelas - <?= formatDate($startDate, 'long') ?></h3>
    <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th><th class="px-4 py-3 text-center font-medium text-green-600">H</th><th class="px-4 py-3 text-center font-medium text-yellow-600">T</th><th class="px-4 py-3 text-center font-medium text-blue-600">S</th><th class="px-4 py-3 text-center font-medium text-purple-600">I</th><th class="px-4 py-3 text-center font-medium text-red-600">A</th><th class="px-4 py-3 text-center font-medium text-gray-600">Total</th></tr></thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($classStats as $c): ?>
            <tr><td class="px-4 py-2 font-medium">Kelas <?= $c['grade_level'] ?> - <?= $c['class_name'] ?></td><td class="px-4 py-2 text-center text-green-600"><?= $c['hadir'] ?></td><td class="px-4 py-2 text-center text-yellow-600"><?= $c['terlambat'] ?></td><td class="px-4 py-2 text-center text-blue-600"><?= $c['sakit'] ?></td><td class="px-4 py-2 text-center text-purple-600"><?= $c['izin'] ?></td><td class="px-4 py-2 text-center text-red-600"><?= $c['alpa'] ?></td><td class="px-4 py-2 text-center font-bold"><?= $c['total'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($classStats)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Tidak ada data.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Siswa Paling Sering Terlambat</h3>
    <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left font-medium text-gray-600">#</th><th class="px-4 py-2 text-left font-medium text-gray-600">Siswa</th><th class="px-4 py-2 text-left font-medium text-gray-600">Kelas</th><th class="px-4 py-2 text-center font-medium text-gray-600">Jumlah Terlambat</th></tr></thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($lateStudents as $i => $s): ?>
            <tr><td class="px-4 py-2 text-gray-400"><?= $i+1 ?></td><td class="px-4 py-2 font-medium"><?= htmlspecialchars($s['full_name']) ?></td><td class="px-4 py-2 text-gray-600">Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></td><td class="px-4 py-2 text-center font-bold text-yellow-600"><?= $s['late_count'] ?>x</td></tr>
            <?php endforeach; ?>
            <?php if (empty($lateStudents)): ?><tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Tidak ada data keterlambatan.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
