<?php
/**
 * Change Password Page
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireLogin();

define('PAGE_TITLE', 'Ubah Password');

if (isPost() && Security::validateCSRF()) {
    $currentPassword = post('current_password');
    $newPassword = post('new_password');
    $confirmPassword = post('confirm_password');

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        setFlash('error', 'Semua field harus diisi.');
    } elseif ($newPassword !== $confirmPassword) {
        setFlash('error', 'Konfirmasi password tidak cocok.');
    } elseif (strlen($newPassword) < 8) {
        setFlash('error', 'Password baru minimal 8 karakter.');
    } else {
        $result = Auth::changePassword(Auth::getUserId(), $currentPassword, $newPassword);
        if ($result['success']) {
            setFlash('success', $result['message']);
        } else {
            setFlash('error', $result['message']);
        }
    }
    redirect('modules/auth/change_password.php');
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
            <i class="fas fa-key text-blue-600"></i> Ubah Password
        </h3>

        <form method="POST">
            <?= Security::csrfField() ?>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Password Lama *</label>
                <input type="password" name="current_password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru *</label>
                <input type="password" name="new_password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required minlength="8">
                <p class="text-xs text-gray-500 mt-1">Minimal 8 karakter</p>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password Baru *</label>
                <input type="password" name="confirm_password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required minlength="8">
            </div>

            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fas fa-save"></i> Ubah Password
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
