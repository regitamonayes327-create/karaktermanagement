<?php
/**
 * Audit Log Viewer
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Audit Log');

$db = Database::getInstance();

$search = get('search');
$moduleFilter = get('module');
$page = max(1, (int) get('page', 1));

$where = "1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND (al.description LIKE ? OR al.action LIKE ? OR u.full_name LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}
if (!empty($moduleFilter)) {
    $where .= " AND al.module = ?";
    $params[] = $moduleFilter;
}

$total = $db->fetchColumn("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE {$where}", $params);
$pagination = paginate($total, $page, 20);
$logs = $db->fetchAll("SELECT al.*, u.full_name, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE {$where} ORDER BY al.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);
$modules = $db->fetchAll("SELECT DISTINCT module FROM audit_logs ORDER BY module");

include __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-800">Log Aktivitas Sistem</h3>
        <p class="text-sm text-gray-500"><?= formatNumber($total) ?> aktivitas tercatat</p>
    </div>

    <div class="p-4 border-b border-gray-50 bg-gray-50/50">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari aktivitas..." class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm">
            <select name="module" class="px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Semua Modul</option>
                <?php foreach ($modules as $m): ?>
                <option value="<?= $m['module'] ?>" <?= $moduleFilter === $m['module'] ? 'selected' : '' ?>><?= ucfirst($m['module']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Waktu</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Pengguna</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Modul</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Aksi</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Keterangan</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">IP</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($logs as $log): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap"><?= formatDate($log['created_at'], 'datetime') ?></td>
                    <td class="px-4 py-3 font-medium text-gray-700"><?= htmlspecialchars($log['full_name'] ?? 'System') ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-gray-100 rounded text-xs"><?= $log['module'] ?></span></td>
                    <td class="px-4 py-3 text-gray-600"><?= $log['action'] ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars(truncate($log['description'] ?? '-', 60)) ?></td>
                    <td class="px-4 py-3 text-gray-400 text-xs"><?= $log['ip_address'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada aktivitas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-4"><?= renderPagination($pagination, '?search=' . urlencode($search) . '&module=' . urlencode($moduleFilter)) ?></div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
