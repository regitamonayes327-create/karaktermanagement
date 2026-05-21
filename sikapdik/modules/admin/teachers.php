<?php
/**
 * Teacher Management
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Data Guru');

$db = Database::getInstance();
$action = get('action', 'list');
$id = (int) get('id', 0);

if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'add' || $formAction === 'edit') {
        $fullName = Security::clean(post('full_name'));
        $nip = Security::clean(post('nip'));
        $gender = Security::clean(post('gender'));
        $phone = Security::clean(post('phone'));
        $address = Security::clean(post('address'));
        $position = Security::clean(post('position'));
        $subject = Security::clean(post('subject'));
        $isHomeroom = (int) post('is_homeroom', 0);
        $userId = (int) post('user_id') ?: null;

        $errors = [];
        if (empty($fullName)) $errors[] = 'Nama lengkap harus diisi.';
        if (!in_array($gender, ['L','P'])) $errors[] = 'Jenis kelamin harus dipilih.';

        if (empty($errors)) {
            $data = [
                'full_name' => $fullName,
                'nip' => $nip ?: null,
                'gender' => $gender,
                'phone' => $phone ?: null,
                'address' => $address ?: null,
                'position' => $position ?: null,
                'subject' => $subject ?: null,
                'is_homeroom' => $isHomeroom,
                'user_id' => $userId
            ];

            if ($formAction === 'add') {
                $db->insert('teachers', $data);
                Auth::logActivity('create_teacher', 'teachers', "Menambah guru: {$fullName}");
                setFlash('success', 'Data guru berhasil ditambahkan.');
            } else {
                $db->update('teachers', $data, 'id = ?', [$id]);
                Auth::logActivity('update_teacher', 'teachers', "Memperbarui guru: {$fullName}");
                setFlash('success', 'Data guru berhasil diperbarui.');
            }
            redirect('modules/admin/teachers.php');
        } else {
            setFlash('error', implode('<br>', $errors));
        }
    } elseif ($formAction === 'delete') {
        $teacherId = (int) post('teacher_id');
        $db->update('teachers', ['is_active' => 0], 'id = ?', [$teacherId]);
        Auth::logActivity('deactivate_teacher', 'teachers', "Menonaktifkan guru ID: {$teacherId}");
        setFlash('success', 'Guru berhasil dinonaktifkan.');
        redirect('modules/admin/teachers.php');
    }
}

include __DIR__ . '/../../templates/header.php';

if ($action === 'add' || ($action === 'edit' && $id > 0)):
    $teacher = $action === 'edit' ? $db->fetch("SELECT * FROM teachers WHERE id = ?", [$id]) : null;
    $availableUsers = $db->fetchAll("SELECT id, username, full_name FROM users WHERE role IN ('wali_kelas','guru_mapel') AND is_active = 1 ORDER BY full_name");
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><?= $action === 'add' ? 'Tambah Guru' : 'Edit Guru' ?></h3>
            <a href="<?= BASE_URL ?>modules/admin/teachers.php" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="<?= $action ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($teacher['full_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIP</label>
                    <input type="text" name="nip" value="<?= htmlspecialchars($teacher['nip'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin *</label>
                    <select name="gender" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="">-- Pilih --</option>
                        <option value="L" <?= ($teacher['gender'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= ($teacher['gender'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
                    <input type="text" name="position" value="<?= htmlspecialchars($teacher['position'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Guru Kelas / Guru Mapel">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mata Pelajaran</label>
                    <input type="text" name="subject" value="<?= htmlspecialchars($teacher['subject'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Wali Kelas?</label>
                    <select name="is_homeroom" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="0" <?= ($teacher['is_homeroom'] ?? 0) == 0 ? 'selected' : '' ?>>Tidak</option>
                        <option value="1" <?= ($teacher['is_homeroom'] ?? 0) == 1 ? 'selected' : '' ?>>Ya</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Akun Pengguna (Opsional)</label>
                    <select name="user_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Tidak Terhubung --</option>
                        <?php foreach ($availableUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($teacher['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?> (@<?= $u['username'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                <textarea name="address" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= htmlspecialchars($teacher['address'] ?? '') ?></textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <a href="<?= BASE_URL ?>modules/admin/teachers.php" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php else:
    $search = get('search');
    $page = max(1, (int) get('page', 1));
    $where = "is_active = 1";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (full_name LIKE ? OR nip LIKE ? OR subject LIKE ?)";
        $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
    }
    $total = $db->count('teachers', $where, $params);
    $pagination = paginate($total, $page, 15);
    $teachers = $db->fetchAll("SELECT * FROM teachers WHERE {$where} ORDER BY full_name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Daftar Guru</h3>
            <p class="text-sm text-gray-500"><?= formatNumber($total) ?> guru aktif</p>
        </div>
        <a href="?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
            <i class="fas fa-plus"></i> Tambah Guru
        </a>
    </div>

    <div class="p-4 border-b border-gray-50 bg-gray-50/50">
        <form method="GET" class="flex gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama/NIP/mapel..." class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm">
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Nama Guru</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">NIP</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Mapel</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Wali Kelas</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($teachers as $t): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($t['full_name']) ?></p>
                        <p class="text-xs text-gray-500"><?= $t['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></p>
                    </td>
                    <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($t['nip'] ?? '-') ?></td>
                    <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($t['subject'] ?? '-') ?></td>
                    <td class="px-6 py-3"><?= $t['is_homeroom'] ? '<span class="text-green-600 font-medium">Ya</span>' : '-' ?></td>
                    <td class="px-6 py-3 text-center">
                        <a href="?action=edit&id=<?= $t['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="inline" onsubmit="return confirmDelete()">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                            <button class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($teachers)): ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada data guru.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-4"><?= renderPagination($pagination, '?search=' . urlencode($search)) ?></div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
