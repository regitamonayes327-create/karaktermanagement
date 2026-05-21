<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['orang_tua']);
define('PAGE_TITLE', 'Notifikasi');
$db = Database::getInstance();
// Mark all as read
$db->update('notifications', ['is_read' => 1], 'user_id = ? AND is_read = 0', [Auth::getUserId()]);
$notifications = $db->fetchAll("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50", [Auth::getUserId()]);
include __DIR__ . '/../../templates/header.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4"><i class="fas fa-bell text-blue-500"></i> Notifikasi</h3>
    <div class="space-y-3">
        <?php foreach ($notifications as $n): ?>
        <div class="p-4 rounded-lg border <?= $n['is_read'] ? 'border-gray-100 bg-white' : 'border-blue-200 bg-blue-50' ?>">
            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($n['title']) ?></p>
            <p class="text-sm text-gray-600"><?= htmlspecialchars($n['message']) ?></p>
            <p class="text-xs text-gray-400 mt-1"><?= formatDate($n['created_at'], 'datetime') ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($notifications)): ?><p class="text-center py-8 text-gray-500">Belum ada notifikasi.</p><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
