<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireLogin();
define('PAGE_TITLE', 'Notifikasi');
$db = Database::getInstance();
// Mark all as read
$db->update('notifications', ['is_read' => 1], 'user_id = ? AND is_read = 0', [Auth::getUserId()]);
$notifications = $db->fetchAll("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50", [Auth::getUserId()]);
include __DIR__ . '/../../templates/header.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-800">Notifikasi</h3>
        <p class="text-sm text-gray-500"><?= count($notifications) ?> notifikasi</p>
    </div>
    <div class="divide-y divide-gray-100">
        <?php foreach ($notifications as $n):
            $typeColors = ['info' => 'blue', 'warning' => 'yellow', 'success' => 'green', 'danger' => 'red'];
            $color = $typeColors[$n['type']] ?? 'blue';
            $typeIcons = ['info' => 'fa-info-circle', 'warning' => 'fa-exclamation-triangle', 'success' => 'fa-check-circle', 'danger' => 'fa-times-circle'];
            $icon = $typeIcons[$n['type']] ?? 'fa-bell';
        ?>
        <div class="p-4 hover:bg-gray-50 <?= !$n['is_read'] ? 'bg-blue-50/30' : '' ?>">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 bg-<?= $color ?>-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $icon ?> text-<?= $color ?>-600 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($n['title']) ?></p>
                    <p class="text-sm text-gray-600 mt-0.5"><?= htmlspecialchars($n['message']) ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= formatDate($n['created_at'], 'datetime') ?></p>
                </div>
                <?php if ($n['link']): ?>
                <a href="<?= BASE_URL . $n['link'] ?>" class="text-xs text-blue-600 hover:text-blue-800 flex-shrink-0">Lihat</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($notifications)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-bell-slash text-gray-300 text-3xl mb-2"></i>
            <p>Belum ada notifikasi.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
