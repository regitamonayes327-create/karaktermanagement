<?php
/**
 * Parent/Guardian Management
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Data Orang Tua');

$db = Database::getInstance();
$action = get('action', 'list');
$id = (int) get('id', 0);

if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'add' || $formAction === 'edit') {
        $fullName = Security::clean(post('full_name'));
        $phone = Security::clean(post('phone'));
        $email = Security::clean(post('email'));
        $address = Security::clean(post('address'));
        $occupation = Security::clean(post('occupation'));
        $relationship = Security::clean(post('relationship'));
        $studentIds = $_POST['student_ids'] ?? [];

        $errors = [];
        if (empty($fullName)) $errors[] = 'Nama harus diisi.';

        if (empty($errors)) {
            $data = [
                'full_name' => $fullName,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'address' => $address ?: null,
                'occupation' => $occupation ?: null,
                'relationship' => $relationship
            ];

            if ($formAction === 'add') {
                // Create user account for parent
                $username = 'ortu_' . strtolower(str_replace(' ', '', substr($fullName, 0, 10))) . rand(100, 999);
                $password = Security::hashPassword('Parent@123');
                $userId = $db->insert('users', [
                    'username' => $username,
                    'password' => $password,
                    'full_name' => $fullName,
                    'role' => 'orang_tua',
                    'phone' => $phone
                ]);
                $data['user_id'] = $userId;
                $parentId = $db->insert('parents', $data);
                
                // Link students
                foreach ($studentIds as $sid) {
                    $db->insert('parent_student', ['parent_id' => $parentId, 'student_id' => (int)$sid]);
                }
                
                Auth::logActivity('create_parent', 'parents', "Menambah orang tua: {$fullName} (akun: {$username})");
                setFlash('success', "Orang tua berhasil ditambahkan. Username: <strong>{$username}</strong>, Password: <strong>Parent@123</strong>");
            } else {
                $db->update('parents', $data, 'id = ?', [$id]);
                // Update student links
                $db->delete('parent_student', 'parent_id = ?', [$id]);
                foreach ($studentIds as $sid) {
                    $db->insert('parent_student', ['parent_id' => $id, 'student_id' => (int)$sid]);
                }
                Auth::logActivity('update_parent', 'parents', "Memperbarui orang tua: {$fullName}");
                setFlash('success', 'Data orang tua berhasil diperbarui.');
            }
            redirect('modules/admin/parents.php');
        } else {
            setFlash('error', implode('<br>', $errors));
        }
    } elseif ($formAction === 'delete') {
        $parentId = (int) post('parent_id');
        $db->update('parents', ['is_active' => 0], 'id = ?', [$parentId]);
        setFlash('success', 'Data orang tua berhasil dinonaktifkan.');
        redirect('modules/admin/parents.php');
    }
}

include __DIR__ . '/../../templates/header.php';

if ($action === 'add' || ($action === 'edit' && $id > 0)):
    $parent = $action === 'edit' ? $db->fetch("SELECT * FROM parents WHERE id = ?", [$id]) : null;
    $linkedStudents = $action === 'edit' ? $db->fetchAll("SELECT student_id FROM parent_student WHERE parent_id = ?", [$id]) : [];
    $linkedIds = array_column($linkedStudents, 'student_id');
    $allStudents = $db->fetchAll("SELECT s.id, s.full_name, s.nis, c.class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.is_active = 1 ORDER BY s.full_name");
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><?= $action === 'add' ? 'Tambah Orang Tua' : 'Edit Orang Tua' ?></h3>
            <a href="<?= BASE_URL ?>modules/admin/parents.php" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="<?= $action ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($parent['full_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hubungan *</label>
                    <select name="relationship" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="ayah" <?= ($parent['relationship'] ?? '') === 'ayah' ? 'selected' : '' ?>>Ayah</option>
                        <option value="ibu" <?= ($parent['relationship'] ?? '') === 'ibu' ? 'selected' : '' ?>>Ibu</option>
                        <option value="wali" <?= ($parent['relationship'] ?? '') === 'wali' ? 'selected' : '' ?>>Wali</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. HP/WhatsApp</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($parent['phone'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pekerjaan</label>
                    <input type="text" name="occupation" value="<?= htmlspecialchars($parent['occupation'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Anak (Siswa)</label>
                <select name="student_ids[]" multiple class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" size="5">
                    <?php foreach ($allStudents as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= in_array($s['id'], $linkedIds) ? 'selected' : '' ?>><?= htmlspecialchars($s['full_name']) ?> (<?= $s['nis'] ?>) - <?= $s['class_name'] ?? 'Belum ada kelas' ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Tahan Ctrl untuk memilih lebih dari satu</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"><i class="fas fa-save"></i> Simpan</button>
                <a href="<?= BASE_URL ?>modules/admin/parents.php" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php else:
    $search = get('search');
    $page = max(1, (int) get('page', 1));
    $where = "p.is_active = 1";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (p.full_name LIKE ? OR p.phone LIKE ?)";
        $params = ["%{$search}%", "%{$search}%"];
    }
    $total = $db->fetchColumn("SELECT COUNT(*) FROM parents p WHERE {$where}", $params);
    $pagination = paginate($total, $page, 15);
    $parents = $db->fetchAll("SELECT p.*, GROUP_CONCAT(s.full_name SEPARATOR ', ') as children 
        FROM parents p LEFT JOIN parent_student ps ON p.id = ps.parent_id LEFT JOIN students s ON ps.student_id = s.id 
        WHERE {$where} GROUP BY p.id ORDER BY p.full_name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Daftar Orang Tua/Wali</h3>
            <p class="text-sm text-gray-500"><?= formatNumber($total) ?> data</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>modules/admin/import_parents.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm"><i class="fas fa-file-import"></i> Import Excel</a>
            <a href="?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"><i class="fas fa-plus"></i> Tambah</a>
        </div>
    </div>

    <div class="p-4 border-b border-gray-50 bg-gray-50/50">
        <form method="GET" class="flex gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama/HP..." class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm">
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Nama</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Hubungan</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">No. HP</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Anak</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($parents as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($p['full_name']) ?></td>
                    <td class="px-6 py-3"><?= ucfirst($p['relationship']) ?></td>
                    <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($p['phone'] ?? '-') ?></td>
                    <td class="px-6 py-3 text-gray-600 text-xs"><?= htmlspecialchars($p['children'] ?? '-') ?></td>
                    <td class="px-6 py-3 text-center">
                        <a href="?action=edit&id=<?= $p['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="inline" onsubmit="return confirmDelete()">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="parent_id" value="<?= $p['id'] ?>">
                            <button class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($parents)): ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada data orang tua.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-4"><?= renderPagination($pagination, '?search=' . urlencode($search)) ?></div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
