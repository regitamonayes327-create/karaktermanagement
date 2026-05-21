<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['orang_tua']);
define('PAGE_TITLE', 'Perilaku Positif');
$db = Database::getInstance();
$parentId = $_SESSION['parent_id'] ?? 0;
$children = $db->fetchAll("SELECT s.id, s.full_name FROM parent_student ps JOIN students s ON ps.student_id = s.id WHERE ps.parent_id = ?", [$parentId]);
$childId = (int) get('child', $children[0]['id'] ?? 0);
$parentViewMode = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'parent_view_mode'") ?: 'selected';
$showCondition = $parentViewMode === 'all' ? "1=1" : "br.show_to_parent = 1";
$records = $db->fetchAll("SELECT br.*, bc.category_name FROM behavior_records br JOIN behavior_categories bc ON br.category_id = bc.id WHERE br.student_id = ? AND br.type = 'keteladanan' AND br.validation_status = 'approved' AND {$showCondition} ORDER BY br.incident_date DESC", [$childId]);
include __DIR__ . '/../../templates/header.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Catatan Perilaku Positif (Keteladanan)</h3>
    <div class="space-y-3">
        <?php foreach ($records as $r): ?>
        <div class="flex items-center justify-between p-3 rounded-lg bg-green-50 border border-green-100">
            <div><p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($r['category_name']) ?></p><?php if($r['description']): ?><p class="text-xs text-gray-500"><?= htmlspecialchars($r['description']) ?></p><?php endif; ?><p class="text-xs text-gray-400"><?= formatDate($r['incident_date'], 'long') ?></p></div>
            <span class="text-lg font-bold text-green-600">+<?= $r['points'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($records)): ?><p class="text-center py-8 text-gray-500">Belum ada catatan keteladanan.</p><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
