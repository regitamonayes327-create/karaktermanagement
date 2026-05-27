<?php
/**
 * Prestasi Anak - Orang Tua
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['orang_tua']);

if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'Prestasi Anak');

$db = Database::getInstance();
$parentId = $_SESSION['parent_id'] ?? 0;

// Tahun ajaran aktif
$tahunAjaran = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'active_academic_year'") ?: '-';
$semester = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'active_semester'") ?: '1';

// Get children
$children = [];
if ($parentId) {
    $children = $db->fetchAll("SELECT s.id, s.full_name, s.nis, c.class_name, c.grade_level FROM parent_student ps JOIN students s ON ps.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id WHERE ps.parent_id = ? AND s.is_active = 1", [$parentId]);
}

$childId = 0;
$selectedChild = null;

if (!empty($children)) {
    $childId = (int) get('child', $children[0]['id']);
    foreach ($children as $c) {
        if ($c['id'] == $childId) { $selectedChild = $c; break; }
    }
    if (!$selectedChild) { $selectedChild = $children[0]; $childId = $selectedChild['id']; }
}

// Get achievements
$achievements = [];
if ($childId) {
    $achievements = $db->fetchAll("SELECT * FROM achievements WHERE student_id = ? AND show_to_parent = 1 ORDER BY achievement_date DESC", [$childId]);
}

include __DIR__ . '/../../templates/header.php';
?>

<?php if ($selectedChild): ?>
<div class="bg-gradient-to-r from-yellow-400 to-orange-500 rounded-xl p-6 mb-6 text-white">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-xl font-bold"><?= strtoupper(substr($selectedChild['full_name'], 0, 1)) ?></div>
        <div>
            <h3 class="text-lg font-semibold"><?= htmlspecialchars($selectedChild['full_name']) ?></h3>
            <p class="text-sm text-yellow-100">Kelas <?= $selectedChild['grade_level'] ?? '-' ?> - <?= $selectedChild['class_name'] ?? '-' ?></p>
            <p class="text-xs text-yellow-200 mt-0.5">Tahun Ajaran: <?= htmlspecialchars($tahunAjaran) ?> | Semester <?= htmlspecialchars($semester) ?></p>
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
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4"><i class="fas fa-trophy text-yellow-500"></i> Prestasi Anak</h3>
    <div class="space-y-3">
        <?php foreach ($achievements as $a): ?>
        <div class="p-4 rounded-lg bg-yellow-50 border border-yellow-100">
            <p class="font-medium text-gray-800"><?= htmlspecialchars($a['title']) ?></p>
            <p class="text-sm text-gray-600"><?= ucfirst(str_replace('_', ' ', $a['category'])) ?> - Tingkat <?= ucfirst($a['level']) ?></p>
            <?php if (!empty($a['description'])): ?><p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($a['description']) ?></p><?php endif; ?>
            <div class="flex items-center justify-between mt-2">
                <p class="text-xs text-gray-400"><?= formatDate($a['achievement_date'], 'long') ?></p>
                <?php if (!empty($a['points'])): ?><span class="text-xs font-bold text-yellow-600">+<?= $a['points'] ?> poin</span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($achievements)): ?>
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-trophy text-gray-300 text-3xl mb-2"></i>
            <p>Belum ada prestasi yang tercatat.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$selectedChild): ?>
<div class="text-center py-12 text-gray-500">
    <i class="fas fa-user-graduate text-gray-300 text-4xl mb-4"></i>
    <p>Belum ada data anak yang terhubung dengan akun Anda.</p>
    <p class="text-sm mt-1">Silakan hubungi sekolah untuk menghubungkan akun.</p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
