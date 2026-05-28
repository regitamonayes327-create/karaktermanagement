<?php
/**
 * Academic Year Reset
 * Manage academic year transitions and semester switches
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Reset Tahun Ajaran');

$db = Database::getInstance();

// Get current active academic year
$activeYear = $db->fetch("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");

// Get all academic years for reference
$allYears = $db->fetchAll("SELECT * FROM academic_years ORDER BY start_date DESC LIMIT 10");

// Handle actions
if (isPost() && Security::validateCSRF()) {
    $action = post('action');

    if ($action === 'new_year') {
        // Mulai Tahun Ajaran Baru
        $yearName = Security::clean(post('year_name'));
        $startDate = Security::clean(post('start_date'));
        $endDate = Security::clean(post('end_date'));
        $semester = (int) post('semester', 1);

        if (empty($yearName) || empty($startDate) || empty($endDate)) {
            setFlash('error', 'Semua field harus diisi.');
            redirect('modules/admin/year_reset.php');
        }

        try {
            $db->beginTransaction();

            // Deactivate all academic years
            $db->query("UPDATE academic_years SET is_active = 0");

            // Create new academic year
            $db->insert('academic_years', [
                'year_name' => $yearName,
                'semester' => $semester,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => 1
            ]);

            // Reset follow_up_status on all behavior_records (mark as fresh for new year)
            $db->query("UPDATE behavior_records SET follow_up_status = NULL WHERE follow_up_status IS NOT NULL AND follow_up_status != 'resolved'");

            $db->commit();

            Auth::logActivity('year_reset', 'settings', "Mulai tahun ajaran baru: {$yearName} Semester {$semester}");
            setFlash('success', "Tahun ajaran baru \"{$yearName}\" berhasil dibuat dan diaktifkan. Semua status tindak lanjut telah direset.");
            redirect('modules/admin/year_reset.php');
        } catch (Exception $e) {
            $db->rollback();
            setFlash('error', 'Gagal membuat tahun ajaran baru: ' . $e->getMessage());
            redirect('modules/admin/year_reset.php');
        }
    }

    if ($action === 'new_semester') {
        // Mulai Semester Baru
        $semester = (int) post('new_semester');
        $startDate = Security::clean(post('semester_start_date'));
        $endDate = Security::clean(post('semester_end_date'));

        if (!$activeYear) {
            setFlash('error', 'Tidak ada tahun ajaran aktif. Buat tahun ajaran baru terlebih dahulu.');
            redirect('modules/admin/year_reset.php');
        }

        if (empty($startDate) || empty($endDate)) {
            setFlash('error', 'Tanggal mulai dan berakhir semester harus diisi.');
            redirect('modules/admin/year_reset.php');
        }

        try {
            $db->beginTransaction();

            // Update active year with new semester
            $db->update('academic_years', [
                'semester' => $semester,
                'start_date' => $startDate,
                'end_date' => $endDate
            ], 'id = ?', [$activeYear['id']]);

            // Optionally reset pending follow-ups
            if (post('reset_followups') === '1') {
                $db->query("UPDATE behavior_records SET follow_up_status = NULL WHERE follow_up_status IS NOT NULL AND follow_up_status != 'resolved'");
            }

            $db->commit();

            Auth::logActivity('semester_switch', 'settings', "Pindah ke Semester {$semester}");
            setFlash('success', "Berhasil beralih ke Semester {$semester}.");
            redirect('modules/admin/year_reset.php');
        } catch (Exception $e) {
            $db->rollback();
            setFlash('error', 'Gagal beralih semester: ' . $e->getMessage());
            redirect('modules/admin/year_reset.php');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<!-- Current Academic Year Info -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-info-circle text-blue-600"></i> Tahun Ajaran Aktif</h3>
    <?php if ($activeYear): ?>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-4 rounded-lg bg-blue-50 border border-blue-200 text-center">
            <p class="text-sm text-blue-600">Tahun Ajaran</p>
            <p class="text-xl font-bold text-blue-800"><?= htmlspecialchars($activeYear['year_name']) ?></p>
        </div>
        <div class="p-4 rounded-lg bg-green-50 border border-green-200 text-center">
            <p class="text-sm text-green-600">Semester</p>
            <p class="text-xl font-bold text-green-800"><?= $activeYear['semester'] ?></p>
        </div>
        <div class="p-4 rounded-lg bg-purple-50 border border-purple-200 text-center">
            <p class="text-sm text-purple-600">Tanggal Mulai</p>
            <p class="text-lg font-bold text-purple-800"><?= date('d/m/Y', strtotime($activeYear['start_date'])) ?></p>
        </div>
        <div class="p-4 rounded-lg bg-orange-50 border border-orange-200 text-center">
            <p class="text-sm text-orange-600">Tanggal Berakhir</p>
            <p class="text-lg font-bold text-orange-800"><?= date('d/m/Y', strtotime($activeYear['end_date'])) ?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="p-4 rounded-lg bg-yellow-50 border border-yellow-200 text-center">
        <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mb-2"></i>
        <p class="text-yellow-800 font-medium">Belum ada tahun ajaran aktif.</p>
        <p class="text-sm text-yellow-600">Silakan buat tahun ajaran baru di bawah.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Actions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- New Academic Year -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-plus-circle text-green-600"></i> Mulai Tahun Ajaran Baru</h3>
        <p class="text-sm text-gray-500 mb-4">Membuat tahun ajaran baru, menonaktifkan tahun ajaran sebelumnya, dan mereset status tindak lanjut.</p>
        
        <form method="POST" onsubmit="return confirmNewYear()">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="new_year">
            
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Tahun Ajaran</label>
                    <input type="text" name="year_name" placeholder="2024/2025" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                    <select name="semester" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                        <option value="1">Semester 1 (Ganjil)</option>
                        <option value="2">Semester 2 (Genap)</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                        <input type="date" name="start_date" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Berakhir</label>
                        <input type="date" name="end_date" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                    </div>
                </div>
            </div>

            <div class="mt-4 p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-sm text-yellow-800">
                <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian:</strong> Tindakan ini akan:
                <ul class="list-disc list-inside mt-1 text-xs">
                    <li>Menonaktifkan semua tahun ajaran sebelumnya</li>
                    <li>Membuat tahun ajaran baru sebagai aktif</li>
                    <li>Mereset status tindak lanjut yang belum resolved</li>
                    <li>Data lama TIDAK dihapus (tetap bisa diakses lewat filter tanggal)</li>
                </ul>
            </div>

            <button type="submit" class="mt-4 w-full px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                <i class="fas fa-play"></i> Mulai Tahun Ajaran Baru
            </button>
        </form>
    </div>

    <!-- Switch Semester -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-exchange-alt text-blue-600"></i> Mulai Semester Baru</h3>
        <p class="text-sm text-gray-500 mb-4">Beralih ke semester berikutnya dalam tahun ajaran yang sama. Update tanggal periode.</p>
        
        <form method="POST" onsubmit="return confirmNewSemester()">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="new_semester">
            
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester Baru</label>
                    <select name="new_semester" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                        <option value="1" <?= ($activeYear && $activeYear['semester'] == 2) ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                        <option value="2" <?= ($activeYear && $activeYear['semester'] == 1) ? 'selected' : '' ?>>Semester 2 (Genap)</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai Semester</label>
                        <input type="date" name="semester_start_date" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Berakhir Semester</label>
                        <input type="date" name="semester_end_date" required class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                    </div>
                </div>
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="reset_followups" value="1" class="rounded border-gray-300">
                        <span class="text-sm text-gray-700">Reset status tindak lanjut yang pending</span>
                    </label>
                </div>
            </div>

            <div class="mt-4 p-3 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-800">
                <i class="fas fa-info-circle"></i> <strong>Info:</strong> Tindakan ini akan:
                <ul class="list-disc list-inside mt-1 text-xs">
                    <li>Mengupdate semester dan tanggal pada tahun ajaran aktif</li>
                    <li>Laporan & dashboard otomatis menampilkan data periode baru</li>
                    <li>Data lama TIDAK dihapus</li>
                </ul>
            </div>

            <button type="submit" class="mt-4 w-full px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium" <?= !$activeYear ? 'disabled' : '' ?>>
                <i class="fas fa-sync-alt"></i> Mulai Semester Baru
            </button>
        </form>
    </div>
</div>

<!-- Academic Year History -->
<?php if (!empty($allYears)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-history text-gray-600"></i> Riwayat Tahun Ajaran</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tahun Ajaran</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Semester</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Tanggal Mulai</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Tanggal Berakhir</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($allYears as $year): ?>
                <tr class="hover:bg-gray-50 <?= $year['is_active'] ? 'bg-green-50' : '' ?>">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($year['year_name']) ?></td>
                    <td class="px-4 py-3 text-center"><?= $year['semester'] ?></td>
                    <td class="px-4 py-3 text-center"><?= date('d/m/Y', strtotime($year['start_date'])) ?></td>
                    <td class="px-4 py-3 text-center"><?= date('d/m/Y', strtotime($year['end_date'])) ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($year['is_active']): ?>
                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 font-medium">Aktif</span>
                        <?php else: ?>
                        <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">Tidak Aktif</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function confirmNewYear() {
    return confirm('PERHATIAN!\n\nAnda akan memulai Tahun Ajaran Baru.\n\nTindakan ini akan:\n- Menonaktifkan semua tahun ajaran sebelumnya\n- Mereset status tindak lanjut\n\nData lama TIDAK akan dihapus.\n\nLanjutkan?');
}

function confirmNewSemester() {
    return confirm('Anda akan beralih ke semester baru.\n\nLaporan dan dashboard akan otomatis menampilkan data periode baru.\n\nLanjutkan?');
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
