<?php
/**
 * My Records (Guru Mapel - riwayat input)
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['guru_mapel']);

define('PAGE_TITLE', 'Riwayat Input');

$db = Database::getInstance();
$typeFilter = get('type');
$page = max(1, (int) get('page', 1));

$where = "br.recorded_by = ?";
$params = [Auth::getUserId()];

if (!empty($typeFilter)) {
    $where .= " AND br.type = ?";
    $params[] = $typeFilter;
}

$total = $db->fetchColumn("SELECT COUNT(*) FROM behavior_records br WHERE {$where}", $params);
$pagination = paginate($total, $page, 15);

$records = $db->fetchAll("SELECT br.*, s.full_name as student_name, s.nis, 
    bc.category_name, c.class_name, c.grade_level
    FROM behavior_records br 
    JOIN students s ON br.student_id = s.id 
    JOIN behavior_categories bc ON br.category_id = bc.id 
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE {$where} 
    ORDER BY br.created_at DESC 
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);

include __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-800">Riwayat Input Saya</h3>
        <p class="text-sm text-gray-500"><?= formatNumber($total) ?> catatan</p>
    </div>

    <div class="p-4 border-b border-gray-50 bg-gray-50/50 flex gap-2">
        <a href="?" class="px-3 py-1.5 rounded-lg text-sm <?= empty($typeFilter) ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600' ?>">Semua</a>
        <a href="?type=keteladanan" class="px-3 py-1.5 rounded-lg text-sm <?= $typeFilter === 'keteladanan' ? 'bg-green-600 text-white' : 'bg-white border text-gray-600' ?>">Keteladanan</a>
        <a href="?type=pelanggaran" class="px-3 py-1.5 rounded-lg text-sm <?= $typeFilter === 'pelanggaran' ? 'bg-red-600 text-white' : 'bg-white border text-gray-600' ?>">Pelanggaran</a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Siswa</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kategori</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Poin</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($records as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500"><?= formatDate($r['incident_date'], 'short') ?></td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($r['student_name']) ?></p>
                        <p class="text-xs text-gray-400">Kelas <?= $r['grade_level'] ?>-<?= $r['class_name'] ?></p>
                    </td>
                    <td class="px-4 py-3">
                        <?= htmlspecialchars($r['category_name']) ?>
                        <?php if ($r['description']): ?>
                        <p class="text-xs text-gray-400"><?= htmlspecialchars(truncate($r['description'], 40)) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center font-bold <?= $r['points'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $r['points'] > 0 ? '+' : '' ?><?= $r['points'] ?></td>
                    <td class="px-4 py-3 text-center"><?= statusBadge($r['validation_status'], 'validation') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada catatan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-4"><?= renderPagination($pagination, '?type=' . urlencode($typeFilter)) ?></div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
