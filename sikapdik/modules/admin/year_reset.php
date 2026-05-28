<?php
/**
 * Manajemen Data Semester
 * Membersihkan data lama agar tidak menumpuk saat pergantian semester/tahun ajaran
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Manajemen Data Semester');

$db = Database::getInstance();
$activeYear = $db->fetch("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");

// Stats
$totalAttendance = $db->count('attendances', '1=1');
$totalBehavior = $db->count('behavior_records', '1=1');
$totalFollowUps = $db->count('follow_ups', '1=1');
$totalNotifications = $db->count('notifications', '1=1');

// Handle reset actions
if (isPost() && Security::validateCSRF()) {
    $action = post('reset_action');
    $confirmText = post('confirm_text');
    
    // Require typing "HAPUS" for confirmation
    if ($confirmText !== 'HAPUS') {
        setFlash('error', 'Konfirmasi tidak valid. Ketik "HAPUS" untuk melanjutkan.');
        redirect('modules/admin/year_reset.php');
    }

    $beforeDate = Security::clean(post('before_date'));
    
    if (empty($beforeDate)) {
        setFlash('error', 'Tanggal batas harus diisi.');
        redirect('modules/admin/year_reset.php');
    }

    $deleted = 0;

    try {
        $db->beginTransaction();

        switch ($action) {
            case 'reset_attendance':
                // Hapus data presensi sebelum tanggal
                $deleted = $db->delete('attendances', 'date < ?', [$beforeDate]);
                Auth::logActivity('reset_attendance', 'data_management', "Hapus {$deleted} data presensi sebelum {$beforeDate}");
                setFlash('success', "Berhasil menghapus <strong>{$deleted}</strong> data presensi (sebelum " . formatDate($beforeDate, 'long') . ").");
                break;

            case 'reset_behavior':
                // Hapus data perilaku sebelum tanggal
                $deleted = $db->delete('behavior_records', 'incident_date < ?', [$beforeDate]);
                Auth::logActivity('reset_behavior', 'data_management', "Hapus {$deleted} data perilaku sebelum {$beforeDate}");
                setFlash('success', "Berhasil menghapus <strong>{$deleted}</strong> catatan perilaku (sebelum " . formatDate($beforeDate, 'long') . ").");
                break;

            case 'reset_followups':
                // Hapus tindak lanjut sebelum tanggal
                $deleted = $db->delete('follow_ups', 'follow_up_date < ?', [$beforeDate]);
                Auth::logActivity('reset_followups', 'data_management', "Hapus {$deleted} tindak lanjut sebelum {$beforeDate}");
                setFlash('success', "Berhasil menghapus <strong>{$deleted}</strong> data tindak lanjut (sebelum " . formatDate($beforeDate, 'long') . ").");
                break;

            case 'reset_notifications':
                // Hapus notifikasi yang sudah dibaca
                $deleted = $db->delete('notifications', 'created_at < ? AND is_read = 1', [$beforeDate]);
                Auth::logActivity('reset_notifications', 'data_management', "Hapus {$deleted} notifikasi sebelum {$beforeDate}");
                setFlash('success', "Berhasil menghapus <strong>{$deleted}</strong> notifikasi lama.");
                break;

            case 'reset_all':
                // Hapus SEMUA data operasional sebelum tanggal
                $d1 = $db->delete('attendances', 'date < ?', [$beforeDate]);
                $d2 = $db->delete('behavior_records', 'incident_date < ?', [$beforeDate]);
                $d3 = $db->delete('follow_ups', 'follow_up_date < ?', [$beforeDate]);
                $d4 = $db->delete('notifications', 'created_at < ?', [$beforeDate]);
                $deleted = $d1 + $d2 + $d3 + $d4;
                Auth::logActivity('reset_all', 'data_management', "Hapus seluruh data sebelum {$beforeDate}: presensi={$d1}, perilaku={$d2}, tindak_lanjut={$d3}, notifikasi={$d4}");
                setFlash('success', "Berhasil menghapus seluruh data sebelum " . formatDate($beforeDate, 'long') . ":<br>Presensi: {$d1} | Perilaku: {$d2} | Tindak Lanjut: {$d3} | Notifikasi: {$d4}");
                break;

            default:
                setFlash('error', 'Aksi tidak valid.');
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        setFlash('error', 'Gagal: ' . $e->getMessage());
    }

    redirect('modules/admin/year_reset.php');
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
        <p class="text-sm text-blue-800"><i class="fas fa-info-circle"></i> <strong>Fungsi:</strong> Membersihkan data semester/tahun ajaran yang lalu agar database tidak menumpuk. Data siswa, kelas, guru, dan orang tua <strong>TIDAK</strong> akan terhapus.</p>
        <?php if ($activeYear): ?>
        <p class="text-xs text-blue-600 mt-1">Tahun Ajaran Aktif: <strong><?= htmlspecialchars($activeYear['year_name']) ?> Semester <?= $activeYear['semester'] ?></strong> (<?= formatDate($activeYear['start_date'], 'short') ?> s/d <?= formatDate($activeYear['end_date'], 'short') ?>)</p>
        <?php endif; ?>
    </div>

    <!-- Current Data Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-blue-600"><?= number_format($totalAttendance) ?></p>
            <p class="text-xs text-gray-500">Data Presensi</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-green-600"><?= number_format($totalBehavior) ?></p>
            <p class="text-xs text-gray-500">Catatan Perilaku</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-purple-600"><?= number_format($totalFollowUps) ?></p>
            <p class="text-xs text-gray-500">Tindak Lanjut</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-gray-600"><?= number_format($totalNotifications) ?></p>
            <p class="text-xs text-gray-500">Notifikasi</p>
        </div>
    </div>

    <!-- Reset Options -->
    <div class="space-y-4">
        <!-- Reset Presensi -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <form method="POST" onsubmit="return confirmReset('DATA PRESENSI')">
                <?= Security::csrfField() ?>
                <input type="hidden" name="reset_action" value="reset_attendance">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-calendar-check text-blue-600"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800">Bersihkan Data Presensi</h4>
                        <p class="text-sm text-gray-500 mb-3">Hapus semua data absensi/kehadiran sebelum tanggal tertentu.</p>
                        <div class="flex flex-col sm:flex-row gap-3 items-end">
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Hapus data sebelum tanggal:</label>
                                <input type="date" name="before_date" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value="<?= $activeYear['start_date'] ?? '' ?>">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Ketik "HAPUS" untuk konfirmasi:</label>
                                <input type="text" name="confirm_text" required placeholder="HAPUS" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 whitespace-nowrap">
                                <i class="fas fa-trash-alt"></i> Bersihkan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Reset Perilaku -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <form method="POST" onsubmit="return confirmReset('CATATAN PERILAKU')">
                <?= Security::csrfField() ?>
                <input type="hidden" name="reset_action" value="reset_behavior">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-star text-green-600"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800">Bersihkan Catatan Perilaku</h4>
                        <p class="text-sm text-gray-500 mb-3">Hapus catatan keteladanan & pelanggaran sebelum tanggal tertentu.</p>
                        <div class="flex flex-col sm:flex-row gap-3 items-end">
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Hapus data sebelum tanggal:</label>
                                <input type="date" name="before_date" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value="<?= $activeYear['start_date'] ?? '' ?>">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Ketik "HAPUS" untuk konfirmasi:</label>
                                <input type="text" name="confirm_text" required placeholder="HAPUS" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 whitespace-nowrap">
                                <i class="fas fa-trash-alt"></i> Bersihkan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Reset Tindak Lanjut -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <form method="POST" onsubmit="return confirmReset('DATA TINDAK LANJUT')">
                <?= Security::csrfField() ?>
                <input type="hidden" name="reset_action" value="reset_followups">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-hands-helping text-purple-600"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800">Bersihkan Tindak Lanjut</h4>
                        <p class="text-sm text-gray-500 mb-3">Hapus data tindak lanjut pembinaan sebelum tanggal tertentu.</p>
                        <div class="flex flex-col sm:flex-row gap-3 items-end">
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Hapus data sebelum tanggal:</label>
                                <input type="date" name="before_date" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value="<?= $activeYear['start_date'] ?? '' ?>">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Ketik "HAPUS" untuk konfirmasi:</label>
                                <input type="text" name="confirm_text" required placeholder="HAPUS" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 whitespace-nowrap">
                                <i class="fas fa-trash-alt"></i> Bersihkan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Reset Notifikasi -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <form method="POST" onsubmit="return confirmReset('NOTIFIKASI LAMA')">
                <?= Security::csrfField() ?>
                <input type="hidden" name="reset_action" value="reset_notifications">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-bell text-gray-600"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800">Bersihkan Notifikasi Lama</h4>
                        <p class="text-sm text-gray-500 mb-3">Hapus notifikasi yang sudah dibaca sebelum tanggal tertentu.</p>
                        <div class="flex flex-col sm:flex-row gap-3 items-end">
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Hapus sebelum tanggal:</label>
                                <input type="date" name="before_date" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value="<?= $activeYear['start_date'] ?? '' ?>">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Ketik "HAPUS" untuk konfirmasi:</label>
                                <input type="text" name="confirm_text" required placeholder="HAPUS" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700 whitespace-nowrap">
                                <i class="fas fa-trash-alt"></i> Bersihkan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Reset ALL (Dangerous) -->
        <div class="bg-white rounded-xl shadow-sm border-2 border-red-200 p-6">
            <form method="POST" onsubmit="return confirmResetAll()">
                <?= Security::csrfField() ?>
                <input type="hidden" name="reset_action" value="reset_all">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-red-800">Bersihkan SELURUH Data Semester</h4>
                        <p class="text-sm text-red-600 mb-3">Hapus semua data presensi, perilaku, tindak lanjut, dan notifikasi sebelum tanggal tertentu sekaligus. <strong>Data siswa, kelas, guru, orang tua TIDAK terhapus.</strong></p>
                        <div class="flex flex-col sm:flex-row gap-3 items-end">
                            <div class="flex-1">
                                <label class="block text-xs text-red-600 mb-1">Hapus SELURUH data sebelum:</label>
                                <input type="date" name="before_date" required class="w-full px-3 py-2 border border-red-200 rounded-lg text-sm" value="<?= $activeYear['start_date'] ?? '' ?>">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-red-600 mb-1">Ketik "HAPUS" untuk konfirmasi:</label>
                                <input type="text" name="confirm_text" required placeholder="HAPUS" class="w-full px-3 py-2 border border-red-200 rounded-lg text-sm">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 whitespace-nowrap">
                                <i class="fas fa-trash-alt"></i> Bersihkan Semua
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tips -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mt-6">
        <h4 class="font-semibold text-yellow-800 mb-2"><i class="fas fa-lightbulb"></i> Tips Penggunaan</h4>
        <ul class="text-sm text-yellow-700 space-y-1">
            <li><strong>Pergantian Semester:</strong> Bersihkan data sebelum tanggal mulai semester baru.</li>
            <li><strong>Pergantian Tahun Ajaran:</strong> Bersihkan seluruh data semester sebelumnya setelah rapor karakter dicetak.</li>
            <li><strong>Tanggal default:</strong> Otomatis terisi tanggal mulai tahun ajaran aktif (data sebelum itu akan dihapus).</li>
            <li><strong>Keamanan:</strong> Harus ketik "HAPUS" + konfirmasi JavaScript untuk mencegah penghapusan tidak sengaja.</li>
            <li><strong>Yang TIDAK terhapus:</strong> Data siswa, kelas, guru, orang tua, akun pengguna, kategori perilaku, pengaturan.</li>
        </ul>
    </div>
</div>

<script>
function confirmReset(dataType) {
    return confirm('PERHATIAN!\n\nAnda akan menghapus ' + dataType + ' secara permanen.\n\nData yang sudah dihapus TIDAK DAPAT dikembalikan.\n\nPastikan Anda sudah mencetak Rapor Karakter sebelum menghapus data.\n\nLanjutkan?');
}
function confirmResetAll() {
    return confirm('⚠️ PERINGATAN KERAS ⚠️\n\nAnda akan menghapus SELURUH data:\n- Presensi\n- Catatan Perilaku\n- Tindak Lanjut\n- Notifikasi\n\nsebelum tanggal yang ditentukan.\n\nData yang dihapus TIDAK DAPAT dikembalikan!\n\nPastikan Rapor Karakter sudah dicetak.\n\nYakin ingin melanjutkan?');
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
