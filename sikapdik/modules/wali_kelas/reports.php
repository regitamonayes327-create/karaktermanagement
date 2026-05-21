<?php
/**
 * Class Reports - Wali Kelas
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas']);

define('PAGE_TITLE', 'Laporan Kelas');

$db = Database::getInstance();
$classId = $_SESSION['class_id'] ?? 0;
$className = $_SESSION['class_name'] ?? '';
$monthFilter = get('month', date('Y-m'));

$startDate = $monthFilter . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Attendance summary per student
$attendanceReport = $db->fetchAll("SELECT s.full_name, s.nis,
    SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
    SUM(CASE WHEN a.status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
    SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
    SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) as izin,
    SUM(CASE WHEN a.status = 'alpa' THEN 1 ELSE 0 END) as alpa
    FROM students s LEFT JOIN attendances a ON s.id = a.student_id AND a.date BETWEEN ? AND ?
    WHERE s.class_id = ? AND s.is_active = 1 GROUP BY s.id ORDER BY s.full_name", [$startDate, $endDate, $classId]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex items-end gap-3">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
            <input type="month" name="month" value="<?= $monthFilter ?>" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm"><i class="fas fa-filter"></i> Filter</button>
        <button type="button" onclick="window.print()" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm"><i class="fas fa-print"></i> Cetak</button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Rekap Presensi Kelas <?= htmlspecialchars($className) ?> - <?= formatDate($startDate, 'long') ?></h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-3 py-2 text-left font-medium text-gray-600">#</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Nama</th>
                <th class="px-3 py-2 text-center font-medium text-green-600">H</th>
                <th class="px-3 py-2 text-center font-medium text-yellow-600">T</th>
                <th class="px-3 py-2 text-center font-medium text-blue-600">S</th>
                <th class="px-3 py-2 text-center font-medium text-purple-600">I</th>
                <th class="px-3 py-2 text-center font-medium text-red-600">A</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($attendanceReport as $i => $r): ?>
                <tr>
                    <td class="px-3 py-2 text-gray-500"><?= $i + 1 ?></td>
                    <td class="px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($r['full_name']) ?></td>
                    <td class="px-3 py-2 text-center text-green-600"><?= $r['hadir'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center text-yellow-600"><?= $r['terlambat'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center text-blue-600"><?= $r['sakit'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center text-purple-600"><?= $r['izin'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center text-red-600"><?= $r['alpa'] ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
