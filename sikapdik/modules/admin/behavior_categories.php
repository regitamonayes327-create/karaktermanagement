<?php
/**
 * Behavior & Achievement Categories Management
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Kategori Perilaku & Prestasi');

$db = Database::getInstance();
$action = get('action', 'list');
$id = (int) get('id', 0);
$tab = get('tab', 'perilaku'); // perilaku or prestasi

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================
if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    $formTab = post('form_tab', 'perilaku');

    // --- PERILAKU (behavior) ---
    if ($formTab === 'perilaku') {
        if ($formAction === 'add' || $formAction === 'edit') {
            $categoryName = Security::clean(post('category_name'));
            $type = Security::clean(post('type'));
            $points = (int) post('points');
            $severity = Security::clean(post('severity'));
            $description = Security::clean(post('description'));

            $errors = [];
            if (empty($categoryName)) $errors[] = 'Nama kategori harus diisi.';
            if (!in_array($type, ['keteladanan','pelanggaran'])) $errors[] = 'Tipe tidak valid.';

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
                    Auth::logActivity('create_behavior_cat', 'behavior_categories', "Tambah kategori perilaku: {$categoryName}");
                    setFlash('success', 'Kategori perilaku berhasil ditambahkan.');
                } else {
                    $db->update('behavior_categories', $data, 'id = ?', [$id]);
                    Auth::logActivity('update_behavior_cat', 'behavior_categories', "Edit kategori perilaku: {$categoryName}");
                    setFlash('success', 'Kategori perilaku berhasil diperbarui.');
                }
                redirect('modules/admin/behavior_categories.php?tab=perilaku');
            } else {
                setFlash('error', implode('<br>', $errors));
            }
        } elseif ($formAction === 'delete') {
            $catId = (int) post('cat_id');
            $db->update('behavior_categories', ['is_active' => 0], 'id = ?', [$catId]);
            Auth::logActivity('delete_behavior_cat', 'behavior_categories', "Nonaktifkan kategori perilaku ID: {$catId}");
            setFlash('success', 'Kategori perilaku berhasil dinonaktifkan.');
            redirect('modules/admin/behavior_categories.php?tab=perilaku');
        }
    }

    // --- PRESTASI (achievement) ---
    if ($formTab === 'prestasi') {
        if ($formAction === 'add' || $formAction === 'edit') {
            $categoryName = Security::clean(post('category_name'));
            $level = Security::clean(post('level'));
            $points = (int) post('points');
            $description = Security::clean(post('description'));

            $errors = [];
            if (empty($categoryName)) $errors[] = 'Nama kategori prestasi harus diisi.';
            if (!in_array($level, ['sekolah','desa','kecamatan','kabupaten','nasional','internasional'])) $errors[] = 'Tingkat tidak valid.';
            if ($points < 0) $points = abs($points);

            if (empty($errors)) {
                $data = [
                    'category_name' => $categoryName,
                    'level' => $level,
                    'points' => $points,
                    'description' => $description ?: null
                ];

                if ($formAction === 'add') {
                    $db->insert('achievement_categories', $data);
                    Auth::logActivity('create_achievement_cat', 'achievement_categories', "Tambah kategori prestasi: {$categoryName} ({$level})");
                    setFlash('success', 'Kategori prestasi berhasil ditambahkan.');
                } else {
                    $db->update('achievement_categories', $data, 'id = ?', [$id]);
                    Auth::logActivity('update_achievement_cat', 'achievement_categories', "Edit kategori prestasi: {$categoryName}");
                    setFlash('success', 'Kategori prestasi berhasil diperbarui.');
                }
                redirect('modules/admin/behavior_categories.php?tab=prestasi');
            } else {
                setFlash('error', implode('<br>', $errors));
            }
        } elseif ($formAction === 'delete') {
            $catId = (int) post('cat_id');
            $db->update('achievement_categories', ['is_active' => 0], 'id = ?', [$catId]);
            Auth::logActivity('delete_achievement_cat', 'achievement_categories', "Nonaktifkan kategori prestasi ID: {$catId}");
            setFlash('success', 'Kategori prestasi berhasil dinonaktifkan.');
            redirect('modules/admin/behavior_categories.php?tab=prestasi');
        }
    }
}

include __DIR__ . '/../../templates/header.php';

// ============================================
// TAB NAVIGATION
// ============================================
?>

<!-- Tab Navigation -->
<div class="flex gap-1 mb-6 border-b border-gray-200">
    <a href="?tab=perilaku" class="px-5 py-3 text-sm font-medium border-b-2 transition <?= $tab === 'perilaku' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
        <i class="fas fa-star mr-1"></i> Kategori Perilaku
    </a>
    <a href="?tab=prestasi" class="px-5 py-3 text-sm font-medium border-b-2 transition <?= $tab === 'prestasi' ? 'border-yellow-600 text-yellow-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
        <i class="fas fa-trophy mr-1"></i> Kategori Prestasi
    </a>
</div>

<?php
// ============================================
// TAB: PERILAKU
// ============================================
if ($tab === 'perilaku'):

    if ($action === 'add_perilaku' || ($action === 'edit_perilaku' && $id > 0)):
        $cat = $action === 'edit_perilaku' ? $db->fetch("SELECT * FROM behavior_categories WHERE id = ?", [$id]) : null;
?>

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><?= $action === 'add_perilaku' ? 'Tambah Kategori Perilaku' : 'Edit Kategori Perilaku' ?></h3>
            <a href="<?= BASE_URL ?>modules/admin/behavior_categories.php?tab=perilaku" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="<?= str_replace('_perilaku', '', $action) ?>">
            <input type="hidden" name="form_tab" value="perilaku">

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
                <label class="block text-sm font-medium text-gray-700 mb-1">Tingkat Keparahan</label>
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
                <a href="<?= BASE_URL ?>modules/admin/behavior_categories.php?tab=perilaku" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
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
        <a href="?tab=perilaku&action=add_perilaku" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"><i class="fas fa-plus"></i> Tambah Perilaku</a>
    </div>

    <div class="p-4 border-b border-gray-50 bg-gray-50/50 flex gap-2">
        <a href="?tab=perilaku" class="px-3 py-1.5 rounded-lg text-sm <?= empty($typeFilter) ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Semua</a>
        <a href="?tab=perilaku&type=keteladanan" class="px-3 py-1.5 rounded-lg text-sm <?= $typeFilter === 'keteladanan' ? 'bg-green-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Keteladanan</a>
        <a href="?tab=perilaku&type=pelanggaran" class="px-3 py-1.5 rounded-lg text-sm <?= $typeFilter === 'pelanggaran' ? 'bg-red-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Pelanggaran</a>
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
                        <a href="?tab=perilaku&action=edit_perilaku&id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="inline" onsubmit="return confirmDelete()">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="form_tab" value="perilaku">
                            <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                            <button class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada kategori perilaku.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php
// ============================================
// TAB: PRESTASI
// ============================================
elseif ($tab === 'prestasi'):

    if ($action === 'add_prestasi' || ($action === 'edit_prestasi' && $id > 0)):
        $cat = $action === 'edit_prestasi' ? $db->fetch("SELECT * FROM achievement_categories WHERE id = ?", [$id]) : null;

        $levelLabels = [
            'sekolah' => 'Tingkat Sekolah',
            'desa' => 'Tingkat Desa/Kelurahan',
            'kecamatan' => 'Tingkat Kecamatan',
            'kabupaten' => 'Tingkat Kabupaten',
            'nasional' => 'Tingkat Nasional',
            'internasional' => 'Tingkat Internasional',
        ];
?>

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><?= $action === 'add_prestasi' ? 'Tambah Kategori Prestasi' : 'Edit Kategori Prestasi' ?></h3>
            <a href="<?= BASE_URL ?>modules/admin/behavior_categories.php?tab=prestasi" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="<?= str_replace('_prestasi', '', $action) ?>">
            <input type="hidden" name="form_tab" value="prestasi">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori Prestasi *</label>
                <input type="text" name="category_name" value="<?= htmlspecialchars($cat['category_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required placeholder="Contoh: Juara Lomba Cerdas Cermat">
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tingkat Prestasi *</label>
                    <select name="level" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
                        <?php foreach ($levelLabels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($cat['level'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poin Prestasi *</label>
                    <input type="number" name="points" value="<?= $cat['points'] ?? 10 ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required min="1">
                    <p class="text-xs text-gray-500 mt-1">Semakin tinggi tingkat = poin lebih besar</p>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" placeholder="Keterangan tambahan (opsional)"><?= htmlspecialchars($cat['description'] ?? '') ?></textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition flex items-center gap-2"><i class="fas fa-save"></i> Simpan</button>
                <a href="<?= BASE_URL ?>modules/admin/behavior_categories.php?tab=prestasi" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php else:
    $levelFilter = get('level');
    $where = "is_active = 1";
    $params = [];
    if (!empty($levelFilter)) {
        $where .= " AND level = ?";
        $params[] = $levelFilter;
    }
    $achCategories = $db->fetchAll("SELECT * FROM achievement_categories WHERE {$where} ORDER BY FIELD(level, 'sekolah','desa','kecamatan','kabupaten','nasional','internasional'), category_name", $params);

    $levelLabels = [
        'sekolah' => 'Sekolah',
        'desa' => 'Desa/Kelurahan',
        'kecamatan' => 'Kecamatan',
        'kabupaten' => 'Kabupaten',
        'nasional' => 'Nasional',
        'internasional' => 'Internasional',
    ];
    $levelColors = [
        'sekolah' => 'bg-blue-100 text-blue-800',
        'desa' => 'bg-teal-100 text-teal-800',
        'kecamatan' => 'bg-purple-100 text-purple-800',
        'kabupaten' => 'bg-orange-100 text-orange-800',
        'nasional' => 'bg-red-100 text-red-800',
        'internasional' => 'bg-yellow-100 text-yellow-800',
    ];

    // Count per level
    $levelCounts = $db->fetchAll("SELECT level, COUNT(*) as total FROM achievement_categories WHERE is_active = 1 GROUP BY level");
    $counts = array_column($levelCounts, 'total', 'level');
?>

<!-- Level Summary Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <?php foreach ($levelLabels as $lvl => $lbl): ?>
    <a href="?tab=prestasi&level=<?= $lvl ?>" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition <?= $levelFilter === $lvl ? 'ring-2 ring-yellow-400' : '' ?>">
        <p class="text-2xl font-bold text-gray-800"><?= $counts[$lvl] ?? 0 ?></p>
        <p class="text-xs text-gray-500 mt-1"><?= $lbl ?></p>
    </a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Kategori Prestasi Siswa</h3>
            <p class="text-sm text-gray-500"><?= count($achCategories) ?> kategori aktif</p>
        </div>
        <a href="?tab=prestasi&action=add_prestasi" class="inline-flex items-center gap-2 px-4 py-2.5 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition text-sm"><i class="fas fa-plus"></i> Tambah Prestasi</a>
    </div>

    <!-- Level Filter -->
    <div class="p-4 border-b border-gray-50 bg-gray-50/50 flex gap-2 flex-wrap">
        <a href="?tab=prestasi" class="px-3 py-1.5 rounded-lg text-sm <?= empty($levelFilter) ? 'bg-yellow-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Semua</a>
        <?php foreach ($levelLabels as $lvl => $lbl): ?>
        <a href="?tab=prestasi&level=<?= $lvl ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $levelFilter === $lvl ? 'bg-yellow-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Kategori Prestasi</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Tingkat</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">Poin</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($achCategories as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($c['category_name']) ?></p>
                        <?php if ($c['description']): ?>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars(truncate($c['description'], 70)) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $levelColors[$c['level']] ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= $levelLabels[$c['level']] ?? ucfirst($c['level']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center font-bold text-yellow-600">+<?= $c['points'] ?></td>
                    <td class="px-6 py-3 text-center">
                        <a href="?tab=prestasi&action=edit_prestasi&id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="inline" onsubmit="return confirmDelete('Nonaktifkan kategori prestasi ini?')">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="form_tab" value="prestasi">
                            <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                            <button class="text-red-600 hover:text-red-800" title="Nonaktifkan"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($achCategories)): ?>
                <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">Belum ada kategori prestasi. Jalankan migration SQL atau tambah manual.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
