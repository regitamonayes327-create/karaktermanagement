<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['orang_tua']);
define('PAGE_TITLE', 'Prestasi Anak');
$db = Database::getInstance();
$parentId = $_SESSION['parent_id'] ?? 0;
$children = $db->fetchAll("SELECT s.id, s.full_name FROM parent_student ps JOIN students s ON ps.student_id = s.id WHERE ps.parent_id = ?", [$parentId]);
$childId = (int) get('child', $children[0]['id'] ?? 0);
$achievements = $db->fetchAll("SELECT * FROM achievements WHERE student_id = ? AND show_to_parent = 1 ORDER BY achievement_date DESC", [$childId]);
include __DIR__ . '/../../templates/header.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4"><i class="fas fa-trophy text-yellow-500"></i> Prestasi Anak</h3>
    <div class="space-y-3">
        <?php foreach ($achievements as $a): ?>
        <div class="p-4 rounded-lg bg-yellow-50 border border-yellow-100">
            <p class="font-medium text-gray-800"><?= htmlspecialchars($a['title']) ?></p>
            <p class="text-sm text-gray-600"><?= ucfirst(str_replace('_',' ',$a['category'])) ?> - Tingkat <?= ucfirst($a['level']) ?></p>
            <?php if($a['description']): ?><p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($a['description']) ?></p><?php endif; ?>
            <p class="text-xs text-gray-400 mt-1"><?= formatDate($a['achievement_date'], 'long') ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($achievements)): ?><p class="text-center py-8 text-gray-500">Belum ada prestasi.</p><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
