<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['orang_tua']);
define('PAGE_TITLE', 'Presensi Anak');
$db = Database::getInstance();
$parentId = $_SESSION['parent_id'] ?? 0;
$children = $db->fetchAll("SELECT s.id, s.full_name FROM parent_student ps JOIN students s ON ps.student_id = s.id WHERE ps.parent_id = ?", [$parentId]);
$childId = (int) get('child', $children[0]['id'] ?? 0);
$monthFilter = get('month', date('Y-m'));
$startDate = $monthFilter . '-01';
$endDate = date('Y-m-t', strtotime($startDate));
$attendances = $db->fetchAll("SELECT * FROM attendances WHERE student_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC", [$childId, $startDate, $endDate]);
include __DIR__ . '/../../templates/header.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex gap-3 items-end">
        <div class="flex-1"><label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label><input type="month" name="month" value="<?= $monthFilter ?>" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm"></div>
        <?php if (count($children) > 1): ?>
        <div class="flex-1"><label class="block text-sm font-medium text-gray-700 mb-1">Anak</label><select name="child" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm"><?php foreach($children as $c): ?><option value="<?= $c['id'] ?>" <?= $childId==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['full_name']) ?></option><?php endforeach; ?></select></div>
        <?php else: ?><input type="hidden" name="child" value="<?= $childId ?>"><?php endif; ?>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm"><i class="fas fa-filter"></i></button>
    </form>
</div>
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th><th class="px-4 py-3 text-center font-medium text-gray-600">Jam</th><th class="px-4 py-3 text-center font-medium text-gray-600">Status</th></tr></thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($attendances as $a): ?>
            <tr><td class="px-4 py-3"><?= formatDate($a['date'], 'full') ?></td><td class="px-4 py-3 text-center"><?= $a['scan_time'] ? formatTime($a['scan_time']) : '-' ?></td><td class="px-4 py-3 text-center"><?= statusBadge($a['status'], 'attendance') ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($attendances)): ?><tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">Belum ada data presensi.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
