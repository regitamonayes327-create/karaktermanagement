<?php
/**
 * Behavior Categories Management
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Kategori Perilaku');

$db = Database::getInstance();
$action = get('action', 'list');
$id = (int) get('id', 0);

if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'add' || $formAction === 'edit') {
        $categoryName = Security::clean(post('category_name'));
        $type = Security::clean(post('type'));
        $points = (int) post('points');
        $severity = Security::clean(post('severity'));
        $description = Security::clean(post('description'));

        $errors = [];
        if (empty($categoryName)) $errors[] = 'Nama kategori harus diisi.';
        if (!in_array($type, ['keteladanan','pelanggaran'])) $errors[] = 'Tipe tidak valid.';

        // Auto-adjust points sign
        if ($type === 'pelanggaran' && $points > 0) $points = -$points;
        if ($type === 'keteladanan' && $points < 0) $points = abs($points);

        if (empty($errors)) {
            $data = [
                'category_name' => $categoryName,
                'type' => $type,
                'points' => $points,
                'severity' => $severity,
                'description' => $description ?: null
            ];

            if ($formAction === 'add') {
                $db->insert('behavior_categories', $data);
                setFlash('success', 'Kategori berhasil ditambahkan.');
            } else {
                $db->update('behavior_categories', $data, 'id = ?', [$id]);
                setFlash('success', 'Kategori berhasil diperbarui.');
            }
            redirect('modules/admin/behavior_categories.php');
        } else {
            setFlash('error', implode('<br>', $errors));
        }
    } elseif ($formAction === 'delete') {
        $catId = (int) post('cat_id');
        $db->update('behavior_categories', ['is_active' => 0], 'id = ?', [$catId]);
        setFlash('success', 'Kategori berhasil dinonaktifkan.');
        redirect('modules/admin/behavior_categories.php');
    }
}

include __DIR__ . '/../../templates/header.php';

if ($action === 'add' || ($action === 'edit' && $id > 0)):
    $cat = $action === 'edit' ? $db->fetch("SELECT * FROM behavior_categories WHERE id = ?", [$id]) : null;
?>

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><?= $action === 'add' ? 'Tambah Kategori' : 'Edit Kategori' ?></h3>
            <a href="<?= BASE_URL ?>modules/admin/behavior_categories.php" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="<?= $action ?>">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori *</label>
                <input type="text" name="category_name" value="<?= htmlspecialchars($cat['category_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe *</label>
                    <select name="type" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="keteladanan" <?= ($cat['type'] ?? '') === 'keteladanan' ? 'selected' : '' ?>>Keteladanan (+)</option>
                        <option value="pelanggaran" <?= ($cat['type'] ?? '') === 'pelanggaran' ? 'selected' : '' ?>>Pelanggaran (-)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poin *</label>
                    <input type="number" name="points" value="<?= abs($cat['points'] ?? 5) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required min="1">
                    <p class="text-xs text-gray-500 mt-1">Tanda +/- otomatis sesuai tipe</p>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tingkat</label>
                <select name="severity" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="ringan" <?= ($cat['severity'] ?? '') === 'ringan' ? 'selected' : '' ?>>Ringan</option>
                    <option value="sedang" <?= ($cat['severity'] ?? '') === 'sedang' ? 'selected' : '' ?>>Sedang</option>
                    <option value="berat" <?= ($cat['severity'] ?? '') === 'berat' ? 'selected' : '' ?>>Berat</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= htmlspecialchars($cat['description'] ?? '') ?></textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"><i class="fas fa-save"></i> Simpan</button>
                <a href="<?= BASE_URL ?>modules/admin/behavior_categories.php" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php else:
    $typeFilter = get('type');
    $where = "is_active = 1";
    $params = [];
    if (!empty($typeFilter)) {
        $where .= " AND type = ?";
        $params[] = $typeFilter;
    }
    $categories = $db->fetchAll("SELECT * FROM behavior_categories WHERE {$where} ORDER BY type, severity DESC, category_name", $params);
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Kategori Perilaku & Poin</h3>
            <p class="text-sm text-gray-500"><?= count($categories) ?> kategori aktif</p>
        </div>
        <a href="?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"><i class="fas fa-plus"></i> Tambah</a>
    </div>

    <div class="p-4 border-b border-gray-50 bg-gray-50/50 flex gap-2">
        <a href="?" class="px-3 py-1.5 rounded-lg text-sm <?= empty($typeFilter) ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Semua</a>
        <a href="?type=keteladanan" class="px-3 py-1.5 rounded-lg text-sm <?= $typeFilter === 'keteladanan' ? 'bg-green-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Keteladanan</a>
        <a href="?type=pelanggaran" class="px-3 py-1.5 rounded-lg text-sm <?= $typeFilter === 'pelanggaran' ? 'bg-red-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Pelanggaran</a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Kategori</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Tipe</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">Poin</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Tingkat</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($categories as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($c['category_name']) ?></p>
                        <?php if ($c['description']): ?>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars(truncate($c['description'], 60)) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3"><?= statusBadge($c['type'], 'behavior') ?></td>
                    <td class="px-6 py-3 text-center font-bold <?= $c['points'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $c['points'] > 0 ? '+' : '' ?><?= $c['points'] ?></td>
                    <td class="px-6 py-3"><?= statusBadge($c['severity'], 'severity') ?></td>
                    <td class="px-6 py-3 text-center">
                        <a href="?action=edit&id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="inline" onsubmit="return confirmDelete()">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                            <button class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
