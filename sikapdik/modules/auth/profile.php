<?php
/**
 * User Profile Page
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireLogin();

define('PAGE_TITLE', 'Profil Saya');

$db = Database::getInstance();
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [Auth::getUserId()]);

if (isPost() && Security::validateCSRF()) {
    $fullName = Security::clean(post('full_name'));
    $email = Security::clean(post('email'));
    $phone = Security::clean(post('phone'));

    $errors = [];
    if (empty($fullName)) $errors[] = 'Nama lengkap harus diisi.';
    if (!empty($email) && !Security::validateEmail($email)) $errors[] = 'Format email tidak valid.';

    if (empty($errors)) {
        $db->update('users', [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone
        ], 'id = ?', [Auth::getUserId()]);

        $_SESSION['full_name'] = $fullName;
        Auth::logActivity('update_profile', 'auth', 'User memperbarui profil');
        setFlash('success', 'Profil berhasil diperbarui.');
        redirect('modules/auth/profile.php');
    } else {
        setFlash('error', implode('<br>', $errors));
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
            <i class="fas fa-user-circle text-blue-600"></i> Informasi Profil
        </h3>

        <form method="POST">
            <?= Security::csrfField() ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" value="<?= htmlspecialchars($user['username']) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-gray-500" disabled>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <input type="text" value="<?= getRoleName($user['role']) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-gray-500" disabled>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="text-sm text-gray-500 mb-6">
                <p>Login terakhir: <?= $user['last_login'] ? formatDate($user['last_login'], 'datetime') : 'Belum pernah login' ?></p>
            </div>

            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
