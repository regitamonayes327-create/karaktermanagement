<?php
/**
 * Achievements - Parent View
 * Shows child's achievements with level categories
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['orang_tua']);

define('PAGE_TITLE', 'Prestasi Anak');

$db = Database::getInstance();
$parentId = $_SESSION['parent_id'] ?? 0;

// Tahun ajaran aktif
$tahunAjaran = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'active_academic_year'") ?: '-';
$semester = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'active_semester'") ?: '1';

$children = $db->fetchAll("SELECT s.id, s.full_name, c.class_name, c.grade_level FROM parent_student ps JOIN students s ON ps.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id WHERE ps.parent_id = ?", [$parentId]);
$childId = (int) get('child', $children[0]['id'] ?? 0);
$selectedChild = null;
foreach ($children as $c) { if ($c['id'] == $childId) { $selectedChild = $c; break; } }
if (!$selectedChild && !empty($children)) { $selectedChild = $children[0]; $childId = $selectedChild['id']; }

$achievements = $db->fetchAll("SELECT * FROM achievements WHERE student_id = ? AND show_to_parent = 1 ORDER BY achievement_date DESC", [$childId]);
include __DIR__ . '/../../templates/header.php';
?>

<?php if ($selectedChild): ?>
<div class="bg-gradient-to-r from-yellow-400 to-orange-500 rounded-xl p-6 mb-6 text-white">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-xl font-bold"><?= strtoupper(substr($selectedChild['full_name'], 0, 1)) ?></div>
        <div>
            <h3 class="text-lg font-semibold"><?= htmlspecialchars($selectedChild['full_name']) ?></h3>
            <p class="text-sm text-yellow-100">Kelas <?= $selectedChild['grade_level'] ?> - <?= $selectedChild['class_name'] ?></p>
            <p class="text-xs text-yellow-200 mt-0.5">Tahun Ajaran: <?= $tahunAjaran ?> | Semester <?= $semester ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (count($children) > 1): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <div class="flex items-center gap-3 flex-wrap">
        <span class="text-sm text-gray-600">Pilih Anak:</span>
        <?php foreach ($children as $c): ?>
        <a href="?child=<?= $c['id'] ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $childId == $c['id'] ? 'bg-yellow-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>"><?= htmlspecialchars($c['full_name']) ?></a>
// Get children
$children = $db->fetchAll("SELECT s.id, s.full_name, s.nis, c.class_name, c.grade_level 
    FROM parent_student ps JOIN students s ON ps.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id
    WHERE ps.parent_id = ? AND s.is_active = 1", [$parentId]);

$childId = (int) get('child', $children[0]['id'] ?? 0);
$levelFilter = get('level');

// Level labels & colors
$levelLabels = [
    'sekolah' => 'Tingkat Sekolah',
    'desa' => 'Tingkat Desa/Kelurahan',
    'kecamatan' => 'Tingkat Kecamatan',
    'kabupaten' => 'Tingkat Kabupaten',
    'nasional' => 'Tingkat Nasional',
    'internasional' => 'Tingkat Internasional',
];
$levelColors = [
    'sekolah' => 'bg-blue-100 text-blue-800',
    'desa' => 'bg-teal-100 text-teal-800',
    'kecamatan' => 'bg-purple-100 text-purple-800',
    'kabupaten' => 'bg-orange-100 text-orange-800',
    'nasional' => 'bg-red-100 text-red-800',
    'internasional' => 'bg-yellow-100 text-yellow-800',
];
$levelIcons = [
    'sekolah' => 'fa-school',
    'desa' => 'fa-home',
    'kecamatan' => 'fa-map-marker-alt',
    'kabupaten' => 'fa-city',
    'nasional' => 'fa-flag',
    'internasional' => 'fa-globe',
];

// Get achievements
$where = "a.student_id = ? AND a.show_to_parent = 1";
$params = [$childId];
if (!empty($levelFilter) && in_array($levelFilter, array_keys($levelLabels))) {
    $where .= " AND a.level = ?";
    $params[] = $levelFilter;
}

$achievements = $db->fetchAll("SELECT a.*, ac.category_name as ach_cat_name 
    FROM achievements a 
    LEFT JOIN achievement_categories ac ON a.achievement_category_id = ac.id
    WHERE {$where} 
    ORDER BY a.achievement_date DESC", $params);

// Stats per level for this child
$levelStats = $db->fetchAll("SELECT level, COUNT(*) as total, SUM(points) as total_points 
    FROM achievements WHERE student_id = ? AND show_to_parent = 1 GROUP BY level", [$childId]);
$stats = [];
foreach ($levelStats as $ls) {
    $stats[$ls['level']] = $ls;
}
$totalPoints = array_sum(array_column($levelStats, 'total_points'));
$totalAchievements = array_sum(array_column($levelStats, 'total'));

// Get current child info
$child = null;
foreach ($children as $c) {
    if ($c['id'] == $childId) { $child = $c; break; }
}

include __DIR__ . '/../../templates/header.php';
?>

<!-- Child Selector (if multiple children) -->
<?php if (count($children) > 1): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <div class="flex items-center gap-3 flex-wrap">
        <span class="text-sm text-gray-600"><i class="fas fa-user-graduate"></i> Pilih Anak:</span>
        <?php foreach ($children as $c): ?>
        <a href="?child=<?= $c['id'] ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $childId == $c['id'] ? 'bg-yellow-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            <?= htmlspecialchars($c['full_name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4"><i class="fas fa-trophy text-yellow-500"></i> Prestasi Anak</h3>
    <div class="space-y-3">
<?php if ($child): ?>

<!-- Summary Header -->
<div class="bg-gradient-to-r from-yellow-500 to-orange-500 rounded-xl p-6 mb-6 text-white">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h3 class="text-lg font-semibold">Prestasi <?= htmlspecialchars($child['full_name']) ?></h3>
            <p class="text-sm text-yellow-100">Kelas <?= $child['grade_level'] ?> - <?= $child['class_name'] ?></p>
        </div>
        <div class="flex items-center gap-6">
            <div class="text-center">
                <p class="text-2xl font-bold"><?= $totalAchievements ?></p>
                <p class="text-xs text-yellow-100">Total Prestasi</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold">+<?= $totalPoints ?></p>
                <p class="text-xs text-yellow-100">Total Poin</p>
            </div>
        </div>
    </div>
</div>

<!-- Level Stats -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <?php foreach ($levelLabels as $lvl => $lbl): ?>
    <a href="?child=<?= $childId ?>&level=<?= $lvl ?>" class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center hover:shadow-md transition <?= $levelFilter === $lvl ? 'ring-2 ring-yellow-400' : '' ?>">
        <div class="w-8 h-8 mx-auto mb-1 rounded-full flex items-center justify-center <?= $levelColors[$lvl] ?>">
            <i class="fas <?= $levelIcons[$lvl] ?> text-xs"></i>
        </div>
        <p class="text-lg font-bold text-gray-800"><?= $stats[$lvl]['total'] ?? 0 ?></p>
        <p class="text-[10px] text-gray-500 leading-tight"><?= str_replace('Tingkat ', '', $lbl) ?></p>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="flex gap-2 flex-wrap mb-4">
    <a href="?child=<?= $childId ?>" class="px-3 py-1.5 rounded-lg text-sm <?= empty($levelFilter) ? 'bg-yellow-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Semua</a>
    <?php foreach ($levelLabels as $lvl => $lbl): ?>
    <a href="?child=<?= $childId ?>&level=<?= $lvl ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $levelFilter === $lvl ? 'bg-yellow-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>"><?= str_replace('Tingkat ', '', $lbl) ?></a>
    <?php endforeach; ?>
</div>

<!-- Achievement List -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="divide-y divide-gray-100">
        <?php foreach ($achievements as $a): ?>
        <div class="p-4 hover:bg-gray-50">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 <?= $levelColors[$a['level']] ?? 'bg-gray-100' ?>">
                    <i class="fas <?= $levelIcons[$a['level']] ?? 'fa-trophy' ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($a['title']) ?></p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $levelColors[$a['level']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= $levelLabels[$a['level']] ?? ucfirst($a['level']) ?>
                        </span>
                    </div>
                    <?php if ($a['description']): ?>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($a['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                        <span><i class="fas fa-folder"></i> <?= ucfirst(str_replace('_', ' ', $a['category'])) ?></span>
                        <span><i class="fas fa-calendar"></i> <?= formatDate($a['achievement_date'], 'long') ?></span>
                        <?php if ($a['ach_cat_name']): ?>
                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($a['ach_cat_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($a['points']): ?>
                <div class="text-right flex-shrink-0">
                    <span class="text-lg font-bold text-yellow-600">+<?= $a['points'] ?></span>
                    <p class="text-[10px] text-gray-400">poin</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($achievements)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-trophy text-gray-300 text-4xl mb-3"></i>
            <p class="font-medium">Belum ada prestasi<?= $levelFilter ? ' untuk tingkat ini' : '' ?>.</p>
            <p class="text-sm mt-1">Prestasi anak Anda akan ditampilkan di sini.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<div class="text-center py-12 text-gray-500">
    <i class="fas fa-user-graduate text-4xl text-gray-300 mb-4"></i>
    <p>Belum ada data anak yang terhubung. Silakan hubungi sekolah.</p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
