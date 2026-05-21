<?php
/**
 * Attendance Management (Admin)
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Presensi');

$db = Database::getInstance();

$dateFilter = get('date', date('Y-m-d'));
$classFilter = get('class_id');

$classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");

$where = "a.date = ?";
$params = [$dateFilter];

if (!empty($classFilter)) {
    $where .= " AND a.class_id = ?";
    $params[] = $classFilter;
}

$attendances = $db->fetchAll("SELECT a.*, s.full_name, s.nis, c.class_name, c.grade_level 
    FROM attendances a 
    JOIN students s ON a.student_id = s.id 
    JOIN classes c ON a.class_id = c.id 
    WHERE {$where} 
    ORDER BY c.grade_level, c.class_name, s.full_name", $params);

// Stats for selected date
$stats = [
    'hadir' => 0, 'terlambat' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0
];
foreach ($attendances as $a) {
    $stats[$a['status']]++;
}

include __DIR__ . '/../../templates/header.php';
?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
            <input type="date" name="date" value="<?= $dateFilter ?>" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Kelas</label>
            <select name="class_id" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Kelas</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= htmlspecialchars($c['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"><i class="fas fa-filter"></i> Filter</button>
    </form>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <div class="bg-green-50 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-green-600"><?= $stats['hadir'] ?></p>
        <p class="text-xs text-gray-600">Hadir</p>
    </div>
    <div class="bg-yellow-50 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-yellow-600"><?= $stats['terlambat'] ?></p>
        <p class="text-xs text-gray-600">Terlambat</p>
    </div>
    <div class="bg-blue-50 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-blue-600"><?= $stats['sakit'] ?></p>
        <p class="text-xs text-gray-600">Sakit</p>
    </div>
    <div class="bg-purple-50 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-purple-600"><?= $stats['izin'] ?></p>
        <p class="text-xs text-gray-600">Izin</p>
    </div>
    <div class="bg-red-50 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-red-600"><?= $stats['alpa'] ?></p>
        <p class="text-xs text-gray-600">Alpa</p>
    </div>
</div>

<!-- Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Data Presensi - <?= formatDate($dateFilter, 'full') ?></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Siswa</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Jam</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Status</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Metode</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Catatan</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($attendances as $a): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($a['full_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= $a['nis'] ?></p>
                    </td>
                    <td class="px-4 py-3">Kelas <?= $a['grade_level'] ?> - <?= htmlspecialchars($a['class_name']) ?></td>
                    <td class="px-4 py-3 text-center"><?= $a['scan_time'] ? formatTime($a['scan_time']) : '-' ?></td>
                    <td class="px-4 py-3 text-center"><?= statusBadge($a['status'], 'attendance') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= $a['method'] === 'qr_scan' ? '<i class="fas fa-qrcode text-purple-500"></i> QR' : '<i class="fas fa-edit text-blue-500"></i> Manual' ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($a['notes'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($attendances)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada data presensi untuk tanggal ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
