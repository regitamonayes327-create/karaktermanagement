<?php
/**
 * System Settings
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Pengaturan Sistem');

$db = Database::getInstance();

if (isPost() && Security::validateCSRF()) {
    $settings = [
        'school_name' => post('school_name'),
        'school_address' => post('school_address'),
        'school_phone' => post('school_phone'),
        'school_email' => post('school_email'),
        'active_academic_year' => post('active_academic_year'),
        'active_semester' => post('active_semester'),
        'late_threshold_time' => post('late_threshold_time'),
        'school_start_time' => post('school_start_time'),
        'late_threshold_global' => post('late_threshold_global', '1'),
        'validation_mode' => post('validation_mode', '0'),
        'parent_view_mode' => post('parent_view_mode', 'selected'),
    ];

    foreach ($settings as $key => $value) {
        $existing = $db->fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            $db->update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            $db->insert('settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'general']);
        }
    }

    // Handle logo upload
    if (!empty($_FILES['school_logo']['name'])) {
        $errors = Security::validateUpload($_FILES['school_logo']);
        if (empty($errors)) {
            $filename = Security::uploadFile($_FILES['school_logo'], UPLOAD_PATH, 'logo_');
            if ($filename) {
                $db->update('settings', ['setting_value' => $filename], "setting_key = 'school_logo'");
            }
        }
    }

    Auth::logActivity('update_settings', 'settings', 'Memperbarui pengaturan sistem');
    setFlash('success', 'Pengaturan berhasil disimpan.');
    redirect('modules/admin/settings.php');
}

// Load settings
$settingsData = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$s = [];
foreach ($settingsData as $row) {
    $s[$row['setting_key']] = $row['setting_value'];
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <form method="POST" enctype="multipart/form-data">
        <?= Security::csrfField() ?>

        <!-- School Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-school text-blue-600"></i> Informasi Sekolah
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Sekolah</label>
                    <input type="text" name="school_name" value="<?= htmlspecialchars($s['school_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Sekolah</label>
                    <input type="email" name="school_email" value="<?= htmlspecialchars($s['school_email'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                    <input type="text" name="school_phone" value="<?= htmlspecialchars($s['school_phone'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logo Sekolah</label>
                    <input type="file" name="school_logo" accept="image/*" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Sekolah</label>
                <textarea name="school_address" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= htmlspecialchars($s['school_address'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Academic Settings -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-calendar text-green-600"></i> Pengaturan Akademik
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Ajaran Aktif</label>
                    <input type="text" name="active_academic_year" value="<?= htmlspecialchars($s['active_academic_year'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="2024/2025">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester Aktif</label>
                    <select name="active_semester" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="1" <?= ($s['active_semester'] ?? '') === '1' ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                        <option value="2" <?= ($s['active_semester'] ?? '') === '2' ? 'selected' : '' ?>>Semester 2 (Genap)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Attendance Settings -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-clock text-yellow-600"></i> Pengaturan Presensi
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jam Masuk Sekolah</label>
                    <input type="time" name="school_start_time" value="<?= $s['school_start_time'] ?? '06:45' ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Batas Terlambat</label>
                    <input type="time" name="late_threshold_time" value="<?= $s['late_threshold_time'] ?? '07:00' ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mode Batas</label>
                    <select name="late_threshold_global" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="1" <?= ($s['late_threshold_global'] ?? '1') === '1' ? 'selected' : '' ?>>Global (sama semua)</option>
                        <option value="0" <?= ($s['late_threshold_global'] ?? '1') === '0' ? 'selected' : '' ?>>Per Kelas</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Behavior Settings -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-cogs text-purple-600"></i> Pengaturan Perilaku & Orang Tua
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mode Validasi Guru Mapel</label>
                    <select name="validation_mode" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="0" <?= ($s['validation_mode'] ?? '0') === '0' ? 'selected' : '' ?>>Nonaktif (langsung masuk)</option>
                        <option value="1" <?= ($s['validation_mode'] ?? '0') === '1' ? 'selected' : '' ?>>Aktif (perlu validasi wali kelas)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tampilan Orang Tua</label>
                    <select name="parent_view_mode" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="selected" <?= ($s['parent_view_mode'] ?? 'selected') === 'selected' ? 'selected' : '' ?>>Hanya yang dipilih guru</option>
                        <option value="all" <?= ($s['parent_view_mode'] ?? '') === 'all' ? 'selected' : '' ?>>Semua catatan</option>
                    </select>
                </div>
            </div>
        </div>

        <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
            <i class="fas fa-save"></i> Simpan Pengaturan
        </button>
    </form>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
