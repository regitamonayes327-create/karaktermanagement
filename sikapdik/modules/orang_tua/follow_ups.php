<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['orang_tua']);
define('PAGE_TITLE', 'Catatan Pembinaan');
$db = Database::getInstance();
$parentId = $_SESSION['parent_id'] ?? 0;
$children = $db->fetchAll("SELECT s.id, s.full_name FROM parent_student ps JOIN students s ON ps.student_id = s.id WHERE ps.parent_id = ?", [$parentId]);
$childId = (int) get('child', $children[0]['id'] ?? 0);
$followUps = $db->fetchAll("SELECT * FROM follow_ups WHERE student_id = ? AND show_to_parent = 1 ORDER BY follow_up_date DESC", [$childId]);
include __DIR__ . '/../../templates/header.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4"><i class="fas fa-info-circle text-blue-500"></i> Catatan Pembinaan</h3>
    <p class="text-sm text-gray-500 mb-4">Informasi tindak lanjut yang sekolah sampaikan kepada Anda.</p>
    <div class="space-y-3">
        <?php foreach ($followUps as $f): ?>
        <div class="p-4 rounded-lg bg-blue-50 border border-blue-100">
            <div class="flex items-center gap-2 mb-2"><?= statusBadge($f['status'], 'followup') ?><span class="text-xs text-gray-400"><?= formatDate($f['follow_up_date'], 'long') ?></span></div>
            <p class="text-sm font-medium text-gray-800"><?= ucfirst(str_replace('_', ' ', $f['follow_up_type'])) ?></p>
            <p class="text-sm text-gray-600"><?= htmlspecialchars($f['description']) ?></p>
            <?php if($f['result']): ?><p class="text-xs text-green-600 mt-2"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($f['result']) ?></p><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($followUps)): ?><p class="text-center py-8 text-gray-500">Tidak ada catatan pembinaan.</p><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
