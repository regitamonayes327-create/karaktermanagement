<?php
/**
 * Tindak Lanjut Pembinaan - Integrated with Behavior Records
 * SIKAPDIK
 * 
 * Shows all pelanggaran records automatically.
 * Wali Kelas / Kepala Sekolah can take action or mark as "tidak perlu tindak lanjut".
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin', 'kepala_sekolah']);

define('PAGE_TITLE', 'Tindak Lanjut');

$db = Database::getInstance();
$classId = $_SESSION['class_id'] ?? 0;
$action = get('action', 'list');
$id = (int) get('id', 0);

// Handle actions
if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    // Mark follow-up as complete (selesai)
    if ($formAction === 'mark_complete') {
        $followUpId = (int) post('follow_up_id');
        if ($followUpId) {
            $db->update('follow_ups', ['status' => 'selesai'], 'id = ?', [$followUpId]);
            Auth::logActivity('mark_complete', 'follow_ups', "Selesaikan tindak lanjut: follow_up ID {$followUpId}");
            setFlash('success', 'Tindak lanjut ditandai selesai.');
        }
        redirect('modules/wali_kelas/follow_ups.php?status=ditindaklanjuti');
    }

    // Mark as "tidak perlu tindak lanjut"
    if ($formAction === 'mark_no_followup') {
        $recordId = (int) post('record_id');
        if ($recordId) {
            $db->update('behavior_records', ['follow_up_status' => 'tidak_perlu'], 'id = ?', [$recordId]);
            Auth::logActivity('mark_no_followup', 'follow_ups', "Tandai tidak perlu tindak lanjut: record ID {$recordId}");
            setFlash('success', 'Catatan ditandai tidak memerlukan tindak lanjut.');
        }
        redirect('modules/wali_kelas/follow_ups.php');
    }
    
    // Save follow-up action
    if ($formAction === 'add_followup') {
        $recordId = (int) post('behavior_record_id');
        $studentId = (int) post('student_id');
        $followUpType = Security::clean(post('follow_up_type'));
        $description = Security::clean(post('description'));
        $result = Security::clean(post('result'));
        $status = Security::clean(post('status')) ?: 'dalam_pemantauan';
        $parentInvolved = (int) post('parent_involved', 0);
        $showToParent = (int) post('show_to_parent', 0);

        $errors = [];
        if (!$studentId) $errors[] = 'Data siswa tidak valid.';
        if (empty($followUpType)) $errors[] = 'Pilih jenis tindak lanjut.';
        if (empty($description)) $errors[] = 'Deskripsi tindak lanjut harus diisi.';

        if (empty($errors)) {
            $db->insert('follow_ups', [
                'student_id' => $studentId,
                'behavior_record_id' => $recordId ?: null,
                'follow_up_type' => $followUpType,
                'description' => $description,
                'result' => $result ?: null,
                'follow_up_date' => date('Y-m-d'),
                'status' => $status,
                'parent_involved' => $parentInvolved,
                'show_to_parent' => $showToParent,
                'created_by' => Auth::getUserId()
            ]);

            // Mark behavior record as followed up
            if ($recordId) {
                $db->update('behavior_records', ['follow_up_status' => 'ditindaklanjuti'], 'id = ?', [$recordId]);
            }

            $student = $db->fetch("SELECT full_name FROM students WHERE id = ?", [$studentId]);
            Auth::logActivity('create_followup', 'follow_ups', "Tindak lanjut: {$student['full_name']} - {$followUpType}");
            
            if (class_exists('NotificationHelper')) {
                NotificationHelper::onFollowUpCreated($studentId, $followUpType, Auth::getFullName(), $showToParent);
            }

            setFlash('success', 'Tindak lanjut berhasil disimpan.');
        } else {
            setFlash('error', implode('<br>', $errors));
        }
        redirect('modules/wali_kelas/follow_ups.php');
    }
}

include __DIR__ . '/../../templates/header.php';

// ============================================================
// ACTION: Tindak Lanjuti (Form)
// ============================================================
if ($action === 'followup' && $id > 0):
    $record = $db->fetch("SELECT br.*, s.full_name, s.nis, s.nisn, s.gender, c.class_name, c.grade_level,
        bc.category_name, bc.severity, u.full_name as recorder_name
        FROM behavior_records br
        JOIN students s ON br.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        JOIN behavior_categories bc ON br.category_id = bc.id
        LEFT JOIN users u ON br.recorded_by = u.id
        WHERE br.id = ?", [$id]);
    
    if (!$record) { setFlash('error', 'Catatan tidak ditemukan.'); redirect('modules/wali_kelas/follow_ups.php'); }
?>

<div class="max-w-2xl mx-auto">
    <!-- Detail Pelanggaran -->
    <div class="bg-red-50 border border-red-200 rounded-xl p-5 mb-6">
        <h4 class="text-sm font-semibold text-red-800 mb-3 flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> Detail Pelanggaran yang Ditindaklanjuti
        </h4>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-red-600 font-medium">Nama:</span> <?= htmlspecialchars($record['full_name']) ?></div>
            <div><span class="text-red-600 font-medium">NIS:</span> <?= $record['nis'] ?> <?= $record['nisn'] ? '/ NISN: ' . $record['nisn'] : '' ?></div>
            <div><span class="text-red-600 font-medium">Kelas:</span> <?= $record['grade_level'] ?>-<?= $record['class_name'] ?></div>
            <div><span class="text-red-600 font-medium">JK:</span> <?= $record['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></div>
            <div><span class="text-red-600 font-medium">Kategori:</span> <?= htmlspecialchars($record['category_name']) ?> (<?= ucfirst($record['severity']) ?>)</div>
            <div><span class="text-red-600 font-medium">Poin:</span> <strong><?= $record['points'] ?></strong></div>
            <div><span class="text-red-600 font-medium">Tanggal:</span> <?= formatDate($record['incident_date'], 'full') ?></div>
            <div><span class="text-red-600 font-medium">Dicatat oleh:</span> <?= htmlspecialchars($record['recorder_name']) ?></div>
        </div>
        <?php if ($record['description']): ?>
        <p class="mt-3 text-sm text-red-700 bg-red-100 rounded-lg p-2"><i class="fas fa-quote-left text-xs"></i> <?= htmlspecialchars($record['description']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Form Tindak Lanjut -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
            <i class="fas fa-hands-helping text-blue-600"></i> Form Tindak Lanjut
        </h3>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="add_followup">
            <input type="hidden" name="behavior_record_id" value="<?= $record['id'] ?>">
            <input type="hidden" name="student_id" value="<?= $record['student_id'] ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Tindak Lanjut *</label>
                    <select name="follow_up_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="">-- Pilih --</option>
                        <option value="teguran_lisan">Teguran Lisan</option>
                        <option value="nasihat">Nasihat</option>
                        <option value="mediasi">Mediasi</option>
                        <option value="komunikasi_ortu">Komunikasi Orang Tua</option>
                        <option value="pembinaan_kepsek">Pembinaan Kepala Sekolah</option>
                        <option value="skorsing">Skorsing</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="dalam_pemantauan">Dalam Pemantauan</option>
                        <option value="selesai">Selesai</option>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Tindak Lanjut *</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required placeholder="Jelaskan tindakan yang dilakukan..."></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Hasil Pembinaan</label>
                <textarea name="result" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Respon/hasil dari siswa setelah tindak lanjut (opsional)"></textarea>
            </div>

            <div class="flex gap-4 mb-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="parent_involved" value="1" class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">Melibatkan orang tua</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="show_to_parent" value="1" class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">Tampilkan ke orang tua</span>
                </label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"><i class="fas fa-save"></i> Simpan Tindak Lanjut</button>
                <a href="<?= BASE_URL ?>modules/wali_kelas/follow_ups.php" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php else:
// ============================================================
// LIST VIEW: Show all pelanggaran records needing follow-up
// ============================================================

    $statusFilter = get('status', 'pending'); // pending, ditindaklanjuti, tidak_perlu

    // Build query based on role
    $roleWhere = '';
    $roleParams = [];
    if (Auth::getRole() === 'wali_kelas' && $classId) {
        $roleWhere = " AND s.class_id = ?";
        $roleParams[] = $classId;
    }

    // Filter
    $followUpWhere = '';
    if ($statusFilter === 'pending') {
        $followUpWhere = " AND (br.follow_up_status IS NULL OR br.follow_up_status = '')";
    } elseif ($statusFilter === 'ditindaklanjuti') {
        $followUpWhere = " AND br.follow_up_status = 'ditindaklanjuti'";
    } elseif ($statusFilter === 'tidak_perlu') {
        $followUpWhere = " AND br.follow_up_status = 'tidak_perlu'";
    }

    $records = $db->fetchAll("SELECT br.*, s.full_name, s.nis, s.nisn, s.gender, c.class_name, c.grade_level,
        bc.category_name, bc.severity, u.full_name as recorder_name,
        fu.id as follow_up_id, fu.status as follow_up_real_status
        FROM behavior_records br
        JOIN students s ON br.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        JOIN behavior_categories bc ON br.category_id = bc.id
        LEFT JOIN users u ON br.recorded_by = u.id
        LEFT JOIN follow_ups fu ON fu.behavior_record_id = br.id
        WHERE br.type = 'pelanggaran' AND br.validation_status = 'approved'
        {$roleWhere} {$followUpWhere}
        ORDER BY br.incident_date DESC, br.created_at DESC
        LIMIT 100", $roleParams);

    // Counts
    $countPending = $db->fetchColumn("SELECT COUNT(*) FROM behavior_records br JOIN students s ON br.student_id = s.id WHERE br.type = 'pelanggaran' AND br.validation_status = 'approved' AND (br.follow_up_status IS NULL OR br.follow_up_status = '') {$roleWhere}", $roleParams);
    $countDone = $db->fetchColumn("SELECT COUNT(*) FROM behavior_records br JOIN students s ON br.student_id = s.id WHERE br.type = 'pelanggaran' AND br.validation_status = 'approved' AND br.follow_up_status = 'ditindaklanjuti' {$roleWhere}", $roleParams);
    $countSkipped = $db->fetchColumn("SELECT COUNT(*) FROM behavior_records br JOIN students s ON br.student_id = s.id WHERE br.type = 'pelanggaran' AND br.validation_status = 'approved' AND br.follow_up_status = 'tidak_perlu' {$roleWhere}", $roleParams);
?>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-red-50 border border-red-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-red-600"><?= $countPending ?></p>
        <p class="text-xs text-gray-600">Perlu Tindak Lanjut</p>
    </div>
    <div class="bg-green-50 border border-green-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-green-600"><?= $countDone ?></p>
        <p class="text-xs text-gray-600">Sudah Ditindaklanjuti</p>
    </div>
    <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-gray-500"><?= $countSkipped ?></p>
        <p class="text-xs text-gray-600">Tidak Perlu</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-800">Tindak Lanjut Pelanggaran Siswa</h3>
        <p class="text-sm text-gray-500">Catatan pelanggaran otomatis muncul di sini untuk ditindaklanjuti</p>
    </div>

    <!-- Filter Tabs -->
    <div class="p-4 border-b border-gray-50 bg-gray-50/50 flex gap-2 flex-wrap">
        <a href="?status=pending" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'pending' ? 'bg-red-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">
            Perlu Tindak Lanjut <?php if($countPending): ?><span class="ml-1 bg-white/20 px-1.5 py-0.5 rounded text-[10px]"><?= $countPending ?></span><?php endif; ?>
        </a>
        <a href="?status=ditindaklanjuti" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'ditindaklanjuti' ? 'bg-green-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Sudah Ditindaklanjuti</a>
        <a href="?status=tidak_perlu" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'tidak_perlu' ? 'bg-gray-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Tidak Perlu</a>
    </div>

    <!-- Records Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Siswa</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">NIS/NISN</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Pelanggaran</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Poin</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Dicatat</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($records as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($r['full_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= $r['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></p>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600">
                        <span class="block"><?= $r['nis'] ?></span>
                        <?php if ($r['nisn']): ?><span class="text-gray-400"><?= $r['nisn'] ?></span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs">Kelas <?= $r['grade_level'] ?>-<?= $r['class_name'] ?></td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($r['category_name']) ?></p>
                        <span class="px-1.5 py-0.5 bg-<?= $r['severity'] === 'berat' ? 'red' : ($r['severity'] === 'sedang' ? 'yellow' : 'blue') ?>-100 text-<?= $r['severity'] === 'berat' ? 'red' : ($r['severity'] === 'sedang' ? 'yellow' : 'blue') ?>-700 rounded text-[10px] font-medium"><?= ucfirst($r['severity']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-center font-bold text-red-600"><?= $r['points'] ?></td>
                    <td class="px-4 py-3 text-xs text-gray-500"><?= formatDate($r['incident_date'], 'short') ?></td>
                    <td class="px-4 py-3 text-xs text-gray-500"><?= htmlspecialchars($r['recorder_name'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($statusFilter === 'pending'): ?>
                        <div class="flex items-center justify-center gap-1">
                            <a href="?action=followup&id=<?= $r['id'] ?>" class="px-2.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition" title="Tindak Lanjuti">
                                <i class="fas fa-gavel"></i> Tindak Lanjuti
                            </a>
                            <form method="POST" class="inline" onsubmit="return confirm('Tandai catatan ini tidak memerlukan tindak lanjut?')">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="form_action" value="mark_no_followup">
                                <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="px-2.5 py-1.5 bg-gray-200 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-300 transition" title="Tidak Perlu Tindak Lanjut">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                        <?php elseif ($statusFilter === 'ditindaklanjuti'): ?>
                        <?php if (isset($r['follow_up_real_status']) && $r['follow_up_real_status'] === 'dalam_pemantauan'): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Tandai tindak lanjut ini sebagai selesai?')">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="form_action" value="mark_complete">
                            <input type="hidden" name="follow_up_id" value="<?= $r['follow_up_id'] ?>">
                            <button type="submit" class="px-2.5 py-1.5 bg-green-600 text-white rounded-lg text-xs font-medium hover:bg-green-700 transition"><i class="fas fa-check-circle"></i> Selesaikan</button>
                        </form>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fas fa-check mr-1"></i> Selesai</span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Dilewati</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <?php if ($statusFilter === 'pending'): ?>
                    <i class="fas fa-check-circle text-green-400 text-2xl mb-2"></i><br>Semua catatan pelanggaran sudah ditindaklanjuti.
                    <?php else: ?>
                    Tidak ada data untuk filter ini.
                    <?php endif; ?>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
