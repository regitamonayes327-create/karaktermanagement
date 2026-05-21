<?php
/**
 * Reports Module (Admin)
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Laporan');

$db = Database::getInstance();
$reportType = get('type', 'attendance');
$classFilter = get('class_id');
$monthFilter = get('month', date('Y-m'));
$classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");

include __DIR__ . '/../../templates/header.php';
?>

<!-- Report Type Selection -->
<div class="flex flex-wrap gap-2 mb-6">
    <?php 
    $reportTypes = ['attendance' => 'Presensi', 'behavior' => 'Perilaku', 'followup' => 'Tindak Lanjut', 'achievement' => 'Prestasi'];
    foreach ($reportTypes as $key => $label): ?>
    <a href="?type=<?= $key ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?= $reportType === $key ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
        <input type="hidden" name="type" value="<?= $reportType ?>">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
            <input type="month" name="month" value="<?= $monthFilter ?>" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Kelas</label>
            <select name="class_id" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Semua</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= $c['class_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm"><i class="fas fa-filter"></i> Tampilkan</button>
        <button type="button" onclick="window.print()" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm"><i class="fas fa-print"></i> Cetak</button>
    </form>
</div>

<?php
$startDate = $monthFilter . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

if ($reportType === 'attendance'):
    $where = "a.date BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    if (!empty($classFilter)) { $where .= " AND a.class_id = ?"; $params[] = $classFilter; }
    
    $summary = $db->fetchAll("SELECT a.status, COUNT(*) as total FROM attendances a WHERE {$where} GROUP BY a.status", $params);
    $byClass = $db->fetchAll("SELECT c.class_name, c.grade_level, 
        SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN a.status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
        SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
        SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) as izin,
        SUM(CASE WHEN a.status = 'alpa' THEN 1 ELSE 0 END) as alpa,
        COUNT(*) as total
        FROM attendances a JOIN classes c ON a.class_id = c.id WHERE {$where} GROUP BY c.id ORDER BY c.grade_level, c.class_name", $params);
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Rekap Presensi - <?= formatDate($startDate, 'long') ?> s/d <?= formatDate($endDate, 'long') ?></h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Hadir</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Terlambat</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Sakit</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Izin</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Alpa</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Total</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($byClass as $row): ?>
                <tr>
                    <td class="px-4 py-3 font-medium">Kelas <?= $row['grade_level'] ?> - <?= $row['class_name'] ?></td>
                    <td class="px-4 py-3 text-center text-green-600 font-medium"><?= $row['hadir'] ?></td>
                    <td class="px-4 py-3 text-center text-yellow-600"><?= $row['terlambat'] ?></td>
                    <td class="px-4 py-3 text-center text-blue-600"><?= $row['sakit'] ?></td>
                    <td class="px-4 py-3 text-center text-purple-600"><?= $row['izin'] ?></td>
                    <td class="px-4 py-3 text-center text-red-600"><?= $row['alpa'] ?></td>
                    <td class="px-4 py-3 text-center font-bold"><?= $row['total'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($byClass)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'behavior'):
    $where = "br.incident_date BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    if (!empty($classFilter)) { $where .= " AND s.class_id = ?"; $params[] = $classFilter; }
    
    $behaviorReport = $db->fetchAll("SELECT s.full_name, s.nis, c.class_name, c.grade_level,
        SUM(CASE WHEN br.type = 'keteladanan' THEN br.points ELSE 0 END) as positive_points,
        SUM(CASE WHEN br.type = 'pelanggaran' THEN ABS(br.points) ELSE 0 END) as negative_points,
        COUNT(CASE WHEN br.type = 'keteladanan' THEN 1 END) as positive_count,
        COUNT(CASE WHEN br.type = 'pelanggaran' THEN 1 END) as negative_count
        FROM behavior_records br JOIN students s ON br.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id
        WHERE {$where} GROUP BY s.id ORDER BY negative_points DESC, positive_points DESC LIMIT 50", $params);
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Rekap Perilaku Siswa</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Siswa</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Keteladanan</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Pelanggaran</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Poin +</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Poin -</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($behaviorReport as $row): ?>
                <tr>
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($row['full_name']) ?></td>
                    <td class="px-4 py-3"><?= $row['class_name'] ? 'Kelas '.$row['grade_level'].' - '.$row['class_name'] : '-' ?></td>
                    <td class="px-4 py-3 text-center text-green-600"><?= $row['positive_count'] ?></td>
                    <td class="px-4 py-3 text-center text-red-600"><?= $row['negative_count'] ?></td>
                    <td class="px-4 py-3 text-center font-bold text-green-600">+<?= $row['positive_points'] ?></td>
                    <td class="px-4 py-3 text-center font-bold text-red-600">-<?= $row['negative_points'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($behaviorReport)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'followup'):
    $followUps = $db->fetchAll("SELECT f.*, s.full_name, c.class_name, u.full_name as creator_name
        FROM follow_ups f JOIN students s ON f.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id LEFT JOIN users u ON f.created_by = u.id
        WHERE f.follow_up_date BETWEEN ? AND ? ORDER BY f.follow_up_date DESC", [$startDate, $endDate]);
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Rekap Tindak Lanjut Pembinaan</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Siswa</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Jenis</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Keterangan</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($followUps as $f): ?>
                <tr>
                    <td class="px-4 py-3"><?= formatDate($f['follow_up_date'], 'short') ?></td>
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($f['full_name']) ?></td>
                    <td class="px-4 py-3"><?= ucfirst(str_replace('_', ' ', $f['follow_up_type'])) ?></td>
                    <td class="px-4 py-3 text-gray-600 text-xs"><?= htmlspecialchars(truncate($f['description'], 80)) ?></td>
                    <td class="px-4 py-3 text-center"><?= statusBadge($f['status'], 'followup') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($followUps)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else:
    $achievements = $db->fetchAll("SELECT a.*, s.full_name, c.class_name 
        FROM achievements a JOIN students s ON a.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id 
        WHERE a.achievement_date BETWEEN ? AND ? ORDER BY a.achievement_date DESC", [$startDate, $endDate]);
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Rekap Prestasi Siswa</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Siswa</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Prestasi</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kategori</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tingkat</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($achievements as $a): ?>
                <tr>
                    <td class="px-4 py-3"><?= formatDate($a['achievement_date'], 'short') ?></td>
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($a['full_name']) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($a['title']) ?></td>
                    <td class="px-4 py-3"><?= ucfirst(str_replace('_', ' ', $a['category'])) ?></td>
                    <td class="px-4 py-3"><?= ucfirst($a['level']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($achievements)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
