<?php
/**
 * Comprehensive Reports Module (Shared across roles)
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 * 
 * Access Control:
 * - Admin/Kepsek: all data, all classes
 * - Wali Kelas: only their class students
 * - Guru Mapel: only records they created, all classes
 * - Orang Tua: only their children
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireLogin();

$role = Auth::getRole();
$db = Database::getInstance();

// Determine page title based on role
$pageTitles = [
    'admin' => 'Laporan',
    'kepala_sekolah' => 'Laporan',
    'wali_kelas' => 'Laporan Kelas',
    'guru_mapel' => 'Laporan Catatan Saya',
    'orang_tua' => 'Laporan Anak'
];
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', $pageTitles[$role] ?? 'Laporan');

// Report type
$reportType = get('type', 'attendance');

// Filter parameters
$filterDate = get('date', date('Y-m-d'));
$filterMonth = get('month', date('Y-m'));
$filterYear = get('year', date('Y'));
$filterPeriod = get('period', 'bulanan'); // harian, mingguan, bulanan, semester
$filterClassId = get('class_id');
$filterCategory = get('category');
$filterLevel = get('level');

// Calculate date range based on period
$startDate = '';
$endDate = '';
switch ($filterPeriod) {
    case 'harian':
        $startDate = $filterDate;
        $endDate = $filterDate;
        break;
    case 'mingguan':
        $startDate = date('Y-m-d', strtotime('monday this week', strtotime($filterDate)));
        $endDate = date('Y-m-d', strtotime('sunday this week', strtotime($filterDate)));
        break;
    case 'bulanan':
        $startDate = $filterMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        break;
    case 'semester':

        $sem = get('semester', '1');
        if ($sem === '1') {
            $startDate = $filterYear . '-07-01';
            $endDate = $filterYear . '-12-31';
        } else {
            $startDate = $filterYear . '-01-01';
            $endDate = $filterYear . '-06-30';
        }
        break;
    case 'tahunan':
        $startDate = $filterYear . '-01-01';
        $endDate = $filterYear . '-12-31';
        break;
}

// Role-based WHERE conditions
$roleWhere = '';
$roleParams = [];

switch ($role) {
    case 'admin':
    case 'kepala_sekolah':
        if (!empty($filterClassId)) {
            $roleWhere = " AND s.class_id = ?";
            $roleParams[] = $filterClassId;
        }
        break;
    case 'wali_kelas':
        $classId = $_SESSION['class_id'] ?? 0;
        $roleWhere = " AND s.class_id = ?";
        $roleParams[] = $classId;
        break;
    case 'guru_mapel':
        // Only records they created (for behavior), all classes
        break;
    case 'orang_tua':
        $parentId = $_SESSION['parent_id'] ?? 0;
        $childIds = $db->fetchAll("SELECT student_id FROM parent_student WHERE parent_id = ?", [$parentId]);
        $childIdList = array_column($childIds, 'student_id');
        if (!empty($childIdList)) {
            $placeholders = implode(',', array_fill(0, count($childIdList), '?'));
            $roleWhere = " AND s.id IN ({$placeholders})";
            $roleParams = $childIdList;
        } else {
            $roleWhere = " AND s.id = 0"; // No children
        }
        break;
}

// Get classes for filter (admin/kepsek only)
$classes = [];
if (in_array($role, ['admin', 'kepala_sekolah'])) {
    $classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");
}

include __DIR__ . '/../../templates/header.php';
?>

<!-- Report Type Tabs -->
<div class="flex flex-wrap gap-2 mb-6">
    <a href="?type=attendance&period=<?= $filterPeriod ?>&month=<?= $filterMonth ?>" 
       class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $reportType === 'attendance' ? 'bg-blue-600 text-white shadow' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <i class="fas fa-calendar-check mr-1"></i> Laporan Absensi
    </a>
    <a href="?type=behavior&period=<?= $filterPeriod ?>&month=<?= $filterMonth ?>" 
       class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $reportType === 'behavior' ? 'bg-green-600 text-white shadow' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <i class="fas fa-star mr-1"></i> Laporan Perilaku
    </a>
    <a href="?type=achievement&period=bulanan&month=<?= $filterMonth ?>" 
       class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $reportType === 'achievement' ? 'bg-yellow-600 text-white shadow' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
        <i class="fas fa-trophy mr-1"></i> Laporan Prestasi
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end flex-wrap">
        <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>">
        

        <!-- Period Filter -->
        <div class="flex-1 min-w-[140px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Periode</label>
            <select name="period" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" onchange="togglePeriodFields(this.value)">
                <?php if ($reportType !== 'achievement'): ?>
                <option value="harian" <?= $filterPeriod === 'harian' ? 'selected' : '' ?>>Harian</option>
                <option value="mingguan" <?= $filterPeriod === 'mingguan' ? 'selected' : '' ?>>Mingguan</option>
                <?php endif; ?>
                <option value="bulanan" <?= $filterPeriod === 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
                <option value="semester" <?= $filterPeriod === 'semester' ? 'selected' : '' ?>>Per Semester</option>
                <?php if ($reportType === 'achievement'): ?>
                <option value="tahunan" <?= $filterPeriod === 'tahunan' ? 'selected' : '' ?>>Per Tahun</option>
                <?php endif; ?>
            </select>
        </div>

        <!-- Date (for harian/mingguan) -->
        <div class="flex-1 min-w-[140px]" id="field-date" style="<?= in_array($filterPeriod, ['harian','mingguan']) ? '' : 'display:none' ?>">
            <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
            <input type="date" name="date" value="<?= $filterDate ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
        </div>

        <!-- Month (for bulanan) -->
        <div class="flex-1 min-w-[140px]" id="field-month" style="<?= $filterPeriod === 'bulanan' ? '' : 'display:none' ?>">
            <label class="block text-xs font-medium text-gray-600 mb-1">Bulan</label>
            <input type="month" name="month" value="<?= $filterMonth ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
        </div>

        <!-- Year/Semester (for semester/tahunan) -->
        <div class="flex-1 min-w-[100px]" id="field-year" style="<?= in_array($filterPeriod, ['semester','tahunan']) ? '' : 'display:none' ?>">
            <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
            <input type="number" name="year" value="<?= $filterYear ?>" min="2020" max="2030" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
        </div>
        <div class="min-w-[100px]" id="field-semester" style="<?= $filterPeriod === 'semester' ? '' : 'display:none' ?>">
            <label class="block text-xs font-medium text-gray-600 mb-1">Semester</label>
            <select name="semester" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="1" <?= get('semester','1') === '1' ? 'selected' : '' ?>>Ganjil</option>
                <option value="2" <?= get('semester','1') === '2' ? 'selected' : '' ?>>Genap</option>
            </select>
        </div>

        <?php if (in_array($role, ['admin', 'kepala_sekolah'])): ?>
        <!-- Class filter -->
        <div class="flex-1 min-w-[140px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Kelas</label>
            <select name="class_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Semua Kelas</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterClassId == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= $c['class_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>


        <?php if ($reportType === 'behavior'): ?>
        <div class="flex-1 min-w-[140px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Kategori</label>
            <select name="category" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Semua</option>
                <option value="keteladanan" <?= $filterCategory === 'keteladanan' ? 'selected' : '' ?>>Keteladanan</option>
                <option value="pelanggaran" <?= $filterCategory === 'pelanggaran' ? 'selected' : '' ?>>Pelanggaran</option>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($reportType === 'achievement'): ?>
        <div class="flex-1 min-w-[140px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Tingkat</label>
            <select name="level" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Semua Tingkat</option>
                <?php foreach (['sekolah'=>'Sekolah','desa'=>'Desa','kecamatan'=>'Kecamatan','kabupaten'=>'Kabupaten','nasional'=>'Nasional','internasional'=>'Internasional'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $filterLevel === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">
            <i class="fas fa-filter"></i> Tampilkan
        </button>
        <button type="button" onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm font-medium hover:bg-gray-700 transition">
            <i class="fas fa-print"></i> Cetak
        </button>
    </form>
</div>

<!-- Period Label -->
<div class="mb-4 text-sm text-gray-500">
    <i class="fas fa-calendar"></i> Periode: <strong><?= formatDate($startDate, 'long') ?></strong> s/d <strong><?= formatDate($endDate, 'long') ?></strong>
</div>

<?php
// ============================================================
// REPORT: ATTENDANCE
// ============================================================
if ($reportType === 'attendance'):
    $where = "a.date BETWEEN ? AND ?" . $roleWhere;
    $params = array_merge([$startDate, $endDate], $roleParams);

    // Summary stats
    $stats = $db->fetchAll("SELECT a.status, COUNT(*) as total 
        FROM attendances a JOIN students s ON a.student_id = s.id 
        WHERE {$where} GROUP BY a.status", $params);
    $statMap = array_column($stats, 'total', 'status');
    $totalRecords = array_sum(array_column($stats, 'total'));

    // Per-student detail
    $detail = $db->fetchAll("SELECT s.full_name, s.nis, c.class_name, c.grade_level,
        SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN a.status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
        SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
        SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) as izin,
        SUM(CASE WHEN a.status = 'alpa' THEN 1 ELSE 0 END) as alpa,
        COUNT(*) as total
        FROM attendances a 
        JOIN students s ON a.student_id = s.id 
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE {$where}
        GROUP BY s.id ORDER BY c.grade_level, c.class_name, s.full_name", $params);
?>

<!-- Stats Cards -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <div class="bg-green-50 border border-green-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-green-600"><?= $statMap['hadir'] ?? 0 ?></p>
        <p class="text-xs text-gray-600">Hadir</p>
    </div>
    <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-yellow-600"><?= $statMap['terlambat'] ?? 0 ?></p>
        <p class="text-xs text-gray-600">Terlambat</p>
    </div>
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-blue-600"><?= $statMap['sakit'] ?? 0 ?></p>
        <p class="text-xs text-gray-600">Sakit</p>
    </div>
    <div class="bg-purple-50 border border-purple-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-purple-600"><?= $statMap['izin'] ?? 0 ?></p>
        <p class="text-xs text-gray-600">Izin</p>
    </div>
    <div class="bg-red-50 border border-red-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-red-600"><?= $statMap['alpa'] ?? 0 ?></p>
        <p class="text-xs text-gray-600">Alpa</p>
    </div>
</div>


<!-- Detail Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Detail Absensi Per Siswa</h3>
        <p class="text-xs text-gray-500"><?= count($detail) ?> siswa | <?= $totalRecords ?> total pencatatan</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">#</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Nama Siswa</th>
                <?php if (in_array($role, ['admin','kepala_sekolah'])): ?>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th>
                <?php endif; ?>
                <th class="px-4 py-3 text-center font-medium text-green-600">H</th>
                <th class="px-4 py-3 text-center font-medium text-yellow-600">T</th>
                <th class="px-4 py-3 text-center font-medium text-blue-600">S</th>
                <th class="px-4 py-3 text-center font-medium text-purple-600">I</th>
                <th class="px-4 py-3 text-center font-medium text-red-600">A</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Total</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($detail as $i => $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-gray-400"><?= $i + 1 ?></td>
                    <td class="px-4 py-2"><span class="font-medium text-gray-800"><?= htmlspecialchars($r['full_name']) ?></span><br><span class="text-xs text-gray-400"><?= $r['nis'] ?></span></td>
                    <?php if (in_array($role, ['admin','kepala_sekolah'])): ?>
                    <td class="px-4 py-2 text-gray-600 text-xs"><?= $r['class_name'] ? $r['grade_level'].'-'.$r['class_name'] : '-' ?></td>
                    <?php endif; ?>
                    <td class="px-4 py-2 text-center font-medium text-green-600"><?= $r['hadir'] ?: '-' ?></td>
                    <td class="px-4 py-2 text-center font-medium text-yellow-600"><?= $r['terlambat'] ?: '-' ?></td>
                    <td class="px-4 py-2 text-center font-medium text-blue-600"><?= $r['sakit'] ?: '-' ?></td>
                    <td class="px-4 py-2 text-center font-medium text-purple-600"><?= $r['izin'] ?: '-' ?></td>
                    <td class="px-4 py-2 text-center font-medium text-red-600"><?= $r['alpa'] ?: '-' ?></td>
                    <td class="px-4 py-2 text-center font-bold text-gray-700"><?= $r['total'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($detail)): ?>
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">Tidak ada data untuk periode ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// ============================================================
// REPORT: BEHAVIOR
// ============================================================
elseif ($reportType === 'behavior'):
    $where = "br.incident_date BETWEEN ? AND ?" . $roleWhere;
    $params = array_merge([$startDate, $endDate], $roleParams);
    
    // For guru_mapel, only their records
    if ($role === 'guru_mapel') {
        $where .= " AND br.recorded_by = ?";
        $params[] = Auth::getUserId();
    }
    
    // Category filter
    if (!empty($filterCategory)) {
        $where .= " AND br.type = ?";
        $params[] = $filterCategory;
    }

    // Summary
    $summary = $db->fetchAll("SELECT br.type, COUNT(*) as total, SUM(ABS(br.points)) as total_points
        FROM behavior_records br JOIN students s ON br.student_id = s.id
        WHERE {$where} AND br.validation_status = 'approved'
        GROUP BY br.type", $params);
    $sumMap = [];
    foreach ($summary as $sm) { $sumMap[$sm['type']] = $sm; }

    // Detail per student
    $detail = $db->fetchAll("SELECT s.full_name, s.nis, c.class_name, c.grade_level,
        SUM(CASE WHEN br.type = 'keteladanan' THEN 1 ELSE 0 END) as pos_count,
        SUM(CASE WHEN br.type = 'pelanggaran' THEN 1 ELSE 0 END) as neg_count,
        SUM(CASE WHEN br.type = 'keteladanan' THEN br.points ELSE 0 END) as pos_points,
        SUM(CASE WHEN br.type = 'pelanggaran' THEN ABS(br.points) ELSE 0 END) as neg_points
        FROM behavior_records br 
        JOIN students s ON br.student_id = s.id 
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE {$where} AND br.validation_status = 'approved'
        GROUP BY s.id ORDER BY neg_points DESC, pos_points DESC", $params);

    // Recent records list
    $records = $db->fetchAll("SELECT br.*, s.full_name, s.nis, bc.category_name, c.class_name, c.grade_level,
        u.full_name as recorder_name
        FROM behavior_records br 
        JOIN students s ON br.student_id = s.id 
        JOIN behavior_categories bc ON br.category_id = bc.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON br.recorded_by = u.id
        WHERE {$where} AND br.validation_status = 'approved'
        ORDER BY br.incident_date DESC, br.created_at DESC LIMIT 100", $params);
?>


<!-- Behavior Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-green-50 border border-green-100 rounded-xl p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Keteladanan</p>
                <p class="text-3xl font-bold text-green-600"><?= $sumMap['keteladanan']['total'] ?? 0 ?></p>
                <p class="text-xs text-green-500">+<?= $sumMap['keteladanan']['total_points'] ?? 0 ?> poin</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-thumbs-up text-green-600 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-red-50 border border-red-100 rounded-xl p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Pelanggaran</p>
                <p class="text-3xl font-bold text-red-600"><?= $sumMap['pelanggaran']['total'] ?? 0 ?></p>
                <p class="text-xs text-red-500">-<?= $sumMap['pelanggaran']['total_points'] ?? 0 ?> poin</p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Behavior Per-Student Summary Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Ringkasan Per Siswa</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">#</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Siswa</th>
                <?php if (in_array($role, ['admin','kepala_sekolah'])): ?>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th>
                <?php endif; ?>
                <th class="px-4 py-3 text-center font-medium text-green-600">Keteladanan</th>
                <th class="px-4 py-3 text-center font-medium text-red-600">Pelanggaran</th>
                <th class="px-4 py-3 text-center font-medium text-green-600">Poin +</th>
                <th class="px-4 py-3 text-center font-medium text-red-600">Poin -</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($detail as $i => $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-gray-400"><?= $i + 1 ?></td>
                    <td class="px-4 py-2 font-medium text-gray-800"><?= htmlspecialchars($r['full_name']) ?></td>
                    <?php if (in_array($role, ['admin','kepala_sekolah'])): ?>
                    <td class="px-4 py-2 text-xs text-gray-600"><?= $r['class_name'] ? $r['grade_level'].'-'.$r['class_name'] : '-' ?></td>
                    <?php endif; ?>
                    <td class="px-4 py-2 text-center text-green-600 font-medium"><?= $r['pos_count'] ?: '-' ?></td>
                    <td class="px-4 py-2 text-center text-red-600 font-medium"><?= $r['neg_count'] ?: '-' ?></td>
                    <td class="px-4 py-2 text-center font-bold text-green-600"><?= $r['pos_points'] ? '+' . $r['pos_points'] : '-' ?></td>
                    <td class="px-4 py-2 text-center font-bold text-red-600"><?= $r['neg_points'] ? '-' . $r['neg_points'] : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($detail)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Tidak ada data untuk periode ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detail Records -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Detail Catatan Perilaku</h3>
        <p class="text-xs text-gray-500"><?= count($records) ?> catatan</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50"><tr>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Tanggal</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Siswa</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Kategori</th>
                <th class="px-3 py-2 text-center font-medium text-gray-600">Tipe</th>
                <th class="px-3 py-2 text-center font-medium text-gray-600">Poin</th>
                <th class="px-3 py-2 text-left font-medium text-gray-600">Pencatat</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($records as $r): ?>
                <tr>
                    <td class="px-3 py-2 text-gray-500"><?= formatDate($r['incident_date'], 'short') ?></td>
                    <td class="px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($r['full_name']) ?></td>
                    <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars($r['category_name']) ?></td>
                    <td class="px-3 py-2 text-center"><?= statusBadge($r['type'], 'behavior') ?></td>
                    <td class="px-3 py-2 text-center font-bold <?= $r['points'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $r['points'] > 0 ? '+' : '' ?><?= $r['points'] ?></td>
                    <td class="px-3 py-2 text-gray-400"><?= htmlspecialchars($r['recorder_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">Tidak ada data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// ============================================================
// REPORT: ACHIEVEMENT
// ============================================================
elseif ($reportType === 'achievement'):
    $where = "a.achievement_date BETWEEN ? AND ?" . $roleWhere;
    $params = array_merge([$startDate, $endDate], $roleParams);

    if (!empty($filterLevel)) {
        $where .= " AND a.level = ?";
        $params[] = $filterLevel;
    }


    // Stats per level
    $levelStats = $db->fetchAll("SELECT a.level, COUNT(*) as total, SUM(a.points) as total_points
        FROM achievements a JOIN students s ON a.student_id = s.id
        WHERE {$where} GROUP BY a.level", $params);
    $lvlMap = [];
    foreach ($levelStats as $ls) { $lvlMap[$ls['level']] = $ls; }
    $totalAch = array_sum(array_column($levelStats, 'total'));
    $totalPts = array_sum(array_column($levelStats, 'total_points'));

    // Detail list
    $achievements = $db->fetchAll("SELECT a.*, s.full_name, s.nis, c.class_name, c.grade_level
        FROM achievements a JOIN students s ON a.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id
        WHERE {$where} ORDER BY a.achievement_date DESC", $params);

    $levelLabels = ['sekolah'=>'Sekolah','desa'=>'Desa/Kelurahan','kecamatan'=>'Kecamatan','kabupaten'=>'Kabupaten','nasional'=>'Nasional','internasional'=>'Internasional'];
    $levelColors = ['sekolah'=>'bg-blue-100 text-blue-800','desa'=>'bg-teal-100 text-teal-800','kecamatan'=>'bg-purple-100 text-purple-800','kabupaten'=>'bg-orange-100 text-orange-800','nasional'=>'bg-red-100 text-red-800','internasional'=>'bg-yellow-100 text-yellow-800'];
?>

<!-- Achievement Stats -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-yellow-600"><?= $totalAch ?></p>
        <p class="text-xs text-gray-600">Total Prestasi</p>
    </div>
    <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-yellow-600">+<?= $totalPts ?></p>
        <p class="text-xs text-gray-600">Total Poin</p>
    </div>
    <?php
    $highestLevel = '';
    $highestOrder = 0;
    $order = ['sekolah'=>1,'desa'=>2,'kecamatan'=>3,'kabupaten'=>4,'nasional'=>5,'internasional'=>6];
    foreach ($lvlMap as $lvl => $data) {
        if (($order[$lvl] ?? 0) > $highestOrder) { $highestOrder = $order[$lvl]; $highestLevel = $lvl; }
    }
    ?>
    <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4 text-center">
        <p class="text-lg font-bold text-gray-800"><?= $levelLabels[$highestLevel] ?? '-' ?></p>
        <p class="text-xs text-gray-600">Tingkat Tertinggi</p>
    </div>
    <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-gray-800"><?= count($achievements) > 0 ? count(array_unique(array_column($achievements, 'student_id'))) : 0 ?></p>
        <p class="text-xs text-gray-600">Siswa Berprestasi</p>
    </div>
</div>

<!-- Level Breakdown -->
<div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-6">
    <?php foreach ($levelLabels as $lvl => $lbl): ?>
    <div class="bg-white border border-gray-100 rounded-lg p-2 text-center">
        <p class="text-lg font-bold text-gray-700"><?= $lvlMap[$lvl]['total'] ?? 0 ?></p>
        <p class="text-[10px] text-gray-500"><?= $lbl ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Achievement Detail Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Detail Prestasi</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">#</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Siswa</th>
                <?php if (in_array($role, ['admin','kepala_sekolah'])): ?>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th>
                <?php endif; ?>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Prestasi</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Tingkat</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Poin</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($achievements as $i => $a): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-gray-400"><?= $i + 1 ?></td>
                    <td class="px-4 py-2 text-gray-500 text-xs"><?= formatDate($a['achievement_date'], 'short') ?></td>
                    <td class="px-4 py-2 font-medium text-gray-800"><?= htmlspecialchars($a['full_name']) ?></td>
                    <?php if (in_array($role, ['admin','kepala_sekolah'])): ?>
                    <td class="px-4 py-2 text-xs text-gray-600"><?= $a['class_name'] ? $a['grade_level'].'-'.$a['class_name'] : '-' ?></td>
                    <?php endif; ?>
                    <td class="px-4 py-2"><?= htmlspecialchars($a['title']) ?></td>
                    <td class="px-4 py-2 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $levelColors[$a['level']] ?? 'bg-gray-100' ?>"><?= $levelLabels[$a['level']] ?? ucfirst($a['level']) ?></span></td>
                    <td class="px-4 py-2 text-center font-bold text-yellow-600"><?= $a['points'] ? '+' . $a['points'] : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($achievements)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Tidak ada data prestasi untuk periode ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Period Toggle Script -->
<script>
function togglePeriodFields(period) {
    document.getElementById('field-date').style.display = (period === 'harian' || period === 'mingguan') ? '' : 'none';
    document.getElementById('field-month').style.display = (period === 'bulanan') ? '' : 'none';
    document.getElementById('field-year').style.display = (period === 'semester' || period === 'tahunan') ? '' : 'none';
    var semEl = document.getElementById('field-semester');
    if (semEl) semEl.style.display = (period === 'semester') ? '' : 'none';
}
</script>

<style>
@media print {
    .sidebar, aside, header, nav, form, button, a, .no-print { display: none !important; }
    body { background: white !important; }
    .flex.h-screen { display: block !important; }
    main { padding: 10px !important; }
    table { font-size: 11px !important; }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
