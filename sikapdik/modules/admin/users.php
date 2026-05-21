<?php
/**
 * User Management
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Data Pengguna');

$db = Database::getInstance();
$action = get('action', 'list');
$id = (int) get('id', 0);

// Handle form submissions
if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'add' || $formAction === 'edit') {
        $username = Security::clean(post('username'));
        $fullName = Security::clean(post('full_name'));
        $email = Security::clean(post('email'));
        $phone = Security::clean(post('phone'));
        $role = Security::clean(post('role'));
        $isActive = (int) post('is_active', 1);
        $password = post('password');

        $errors = [];
        if (empty($username)) $errors[] = 'Username harus diisi.';
        if (empty($fullName)) $errors[] = 'Nama lengkap harus diisi.';
        if (!in_array($role, ['admin','kepala_sekolah','wali_kelas','guru_mapel','orang_tua'])) $errors[] = 'Role tidak valid.';
        
        // Check unique username
        $existing = $db->fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id]);
        if ($existing) $errors[] = 'Username sudah digunakan.';

        if ($formAction === 'add' && empty($password)) $errors[] = 'Password harus diisi untuk pengguna baru.';
        if (!empty($password) && strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';

        if (empty($errors)) {
            $data = [
                'username' => $username,
                'full_name' => $fullName,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'role' => $role,
                'is_active' => $isActive
            ];

            if (!empty($password)) {
                $data['password'] = Security::hashPassword($password);
            }

            if ($formAction === 'add') {
                $db->insert('users', $data);
                Auth::logActivity('create_user', 'users', "Menambah pengguna: {$username}");
                setFlash('success', 'Pengguna berhasil ditambahkan.');
            } else {
                $db->update('users', $data, 'id = ?', [$id]);
                Auth::logActivity('update_user', 'users', "Memperbarui pengguna: {$username}");
                setFlash('success', 'Pengguna berhasil diperbarui.');
            }
            redirect('modules/admin/users.php');
        } else {
            setFlash('error', implode('<br>', $errors));
        }
    } elseif ($formAction === 'delete') {
        $userId = (int) post('user_id');
        if ($userId !== Auth::getUserId()) {
            $db->update('users', ['is_active' => 0], 'id = ?', [$userId]);
            Auth::logActivity('deactivate_user', 'users', "Menonaktifkan pengguna ID: {$userId}");
            setFlash('success', 'Pengguna berhasil dinonaktifkan.');
        } else {
            setFlash('error', 'Tidak dapat menonaktifkan akun sendiri.');
        }
        redirect('modules/admin/users.php');
    } elseif ($formAction === 'reset_password') {
        $userId = (int) post('user_id');
        $newPass = post('new_password');
        if (strlen($newPass) >= 8) {
            Auth::resetPassword($userId, $newPass);
            setFlash('success', 'Password berhasil direset.');
        } else {
            setFlash('error', 'Password minimal 8 karakter.');
        }
        redirect('modules/admin/users.php');
    }
}

include __DIR__ . '/../../templates/header.php';

if ($action === 'add' || ($action === 'edit' && $id > 0)):
    $user = $action === 'edit' ? $db->fetch("SELECT * FROM users WHERE id = ?", [$id]) : null;
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><?= $action === 'add' ? 'Tambah Pengguna' : 'Edit Pengguna' ?></h3>
            <a href="<?= BASE_URL ?>modules/admin/users.php" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="<?= $action ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? post('username')) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? post('full_name')) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? post('email')) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? post('phone')) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                    <select name="role" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="">-- Pilih Role --</option>
                        <?php foreach(['admin'=>'Admin/Operator','kepala_sekolah'=>'Kepala Sekolah','wali_kelas'=>'Wali Kelas','guru_mapel'=>'Guru Mapel','orang_tua'=>'Orang Tua'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($user['role'] ?? post('role')) === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="is_active" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="1" <?= ($user['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= ($user['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Password <?= $action === 'add' ? '*' : '(kosongkan jika tidak diubah)' ?></label>
                <input type="password" name="password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" <?= $action === 'add' ? 'required' : '' ?> minlength="8">
                <p class="text-xs text-gray-500 mt-1">Minimal 8 karakter</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <a href="<?= BASE_URL ?>modules/admin/users.php" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php else: // List view
    $search = get('search');
    $roleFilter = get('role_filter');
    $page = max(1, (int) get('page', 1));
    $perPage = 15;

    $where = "1=1";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if (!empty($roleFilter)) {
        $where .= " AND role = ?";
        $params[] = $roleFilter;
    }

    $total = $db->count('users', $where, $params);
    $pagination = paginate($total, $page, $perPage);
    $users = $db->fetchAll("SELECT * FROM users WHERE {$where} ORDER BY created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <!-- Header -->
    <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Daftar Pengguna</h3>
            <p class="text-sm text-gray-500"><?= formatNumber($total) ?> pengguna terdaftar</p>
        </div>
        <a href="<?= BASE_URL ?>modules/admin/users.php?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
            <i class="fas fa-plus"></i> Tambah Pengguna
        </a>
    </div>

    <!-- Filters -->
    <div class="p-4 border-b border-gray-50 bg-gray-50/50">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari username/nama..." class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            <select name="role_filter" class="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Role</option>
                <?php foreach(['admin'=>'Admin','kepala_sekolah'=>'Kepala Sekolah','wali_kelas'=>'Wali Kelas','guru_mapel'=>'Guru Mapel','orang_tua'=>'Orang Tua'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $roleFilter === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700"><i class="fas fa-search"></i> Cari</button>
        </form>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-6 py-3 font-medium text-gray-600">Pengguna</th>
                    <th class="px-6 py-3 font-medium text-gray-600">Role</th>
                    <th class="px-6 py-3 font-medium text-gray-600">Status</th>
                    <th class="px-6 py-3 font-medium text-gray-600">Login Terakhir</th>
                    <th class="px-6 py-3 font-medium text-gray-600 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($u['full_name']) ?></p>
                        <p class="text-xs text-gray-500">@<?= htmlspecialchars($u['username']) ?></p>
                    </td>
                    <td class="px-6 py-3"><?= getRoleName($u['role']) ?></td>
                    <td class="px-6 py-3">
                        <?php if ($u['is_active']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-gray-500"><?= $u['last_login'] ? formatDate($u['last_login'], 'datetime') : '-' ?></td>
                    <td class="px-6 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="?action=edit&id=<?= $u['id'] ?>" class="text-blue-600 hover:text-blue-800" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if ($u['id'] !== Auth::getUserId()): ?>
                            <form method="POST" class="inline" onsubmit="return confirmDelete('Nonaktifkan pengguna ini?')">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800" title="Nonaktifkan"><i class="fas fa-ban"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada data pengguna.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="p-4">
        <?= renderPagination($pagination, '?search=' . urlencode($search) . '&role_filter=' . urlencode($roleFilter)) ?>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
