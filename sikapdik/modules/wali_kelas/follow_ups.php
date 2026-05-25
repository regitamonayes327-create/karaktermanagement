<?php
/**
 * Follow-up / Tindak Lanjut Pembinaan - Wali Kelas
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin']);

define('PAGE_TITLE', 'Tindak Lanjut');

$db = Database::getInstance();
$classId = $_SESSION['class_id'] ?? 0;
$action = get('action', 'list');
$id = (int) get('id', 0);

if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'add' || $formAction === 'edit') {
        $studentId = (int) post('student_id');
        $behaviorRecordId = (int) post('behavior_record_id') ?: null;
        $followUpType = Security::clean(post('follow_up_type'));
        $description = Security::clean(post('description'));
        $result = Security::clean(post('result'));
        $followUpDate = Security::clean(post('follow_up_date')) ?: date('Y-m-d');
        $status = Security::clean(post('status'));
        $parentInvolved = (int) post('parent_involved', 0);
        $showToParent = (int) post('show_to_parent', 0);

        $errors = [];
        if (!$studentId) $errors[] = 'Pilih siswa.';
        if (empty($followUpType)) $errors[] = 'Pilih jenis tindak lanjut.';
        if (empty($description)) $errors[] = 'Deskripsi harus diisi.';

        if (empty($errors)) {
            $data = [
                'student_id' => $studentId,
                'behavior_record_id' => $behaviorRecordId,
                'follow_up_type' => $followUpType,
                'description' => $description,
                'result' => $result ?: null,
                'follow_up_date' => $followUpDate,
                'status' => $status ?: 'belum_diproses',
                'parent_involved' => $parentInvolved,
                'show_to_parent' => $showToParent,
                'created_by' => Auth::getUserId()
            ];

            if ($formAction === 'add') {
                $db->insert('follow_ups', $data);
                $student = $db->fetch("SELECT full_name FROM students WHERE id = ?", [$studentId]);
                Auth::logActivity('create_followup', 'follow_ups', "Tindak lanjut: {$student['full_name']} - {$followUpType}");
                NotificationHelper::onFollowUpCreated($studentId, $followUpType, Auth::getFullName(), $showToParent);
                setFlash('success', 'Tindak lanjut berhasil disimpan.');
            } else {
                unset($data['created_by']);
                $db->update('follow_ups', $data, 'id = ?', [$id]);
                Auth::logActivity('update_followup', 'follow_ups', "Update tindak lanjut ID: {$id}");
                setFlash('success', 'Tindak lanjut berhasil diperbarui.');
            }
            redirect('modules/wali_kelas/follow_ups.php');
        } else {
            setFlash('error', implode('<br>', $errors));
        }
    }
}

include __DIR__ . '/../../templates/header.php';

if ($action === 'add' || ($action === 'edit' && $id > 0)):
    $followUp = $action === 'edit' ? $db->fetch("SELECT * FROM follow_ups WHERE id = ?", [$id]) : null;
    $students = $db->fetchAll("SELECT id, full_name, nis FROM students WHERE class_id = ? AND is_active = 1 ORDER BY full_name", [$classId]);
    
    // Get behavior records for linking (for edit mode or if student already selected)
    $behaviorRecords = [];
    $selectedStudentId = $followUp['student_id'] ?? (int) get('student_id', 0);
    if ($selectedStudentId) {
        $behaviorRecords = $db->fetchAll("SELECT br.id, br.incident_date, bc.category_name, br.type, br.points, br.description, bc.severity
            FROM behavior_records br JOIN behavior_categories bc ON br.category_id = bc.id 
            WHERE br.student_id = ? AND br.type = 'pelanggaran' AND br.validation_status = 'approved'
            ORDER BY br.incident_date DESC LIMIT 30", [$selectedStudentId]);
    }
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><?= $action === 'add' ? 'Buat Tindak Lanjut' : 'Edit Tindak Lanjut' ?></h3>
            <a href="<?= BASE_URL ?>modules/wali_kelas/follow_ups.php" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="<?= $action ?>">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Siswa *</label>
                <select name="student_id" id="studentSelect" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required onchange="loadBehaviorRecords(this.value)">
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($followUp['student_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['full_name']) ?> (<?= $s['nis'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Behavior Records for Selected Student -->
            <div class="mb-4" id="behaviorRecordsSection" style="<?= empty($behaviorRecords) ? 'display:none' : '' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i> Catatan Pelanggaran Terkait
                </label>
                <div id="behaviorRecordsList" class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg mb-2">
                    <?php if (!empty($behaviorRecords)): ?>
                    <?php foreach ($behaviorRecords as $br): ?>
                    <label class="flex items-start gap-3 p-3 hover:bg-red-50 border-b border-gray-100 last:border-0 cursor-pointer">
                        <input type="radio" name="behavior_record_id" value="<?= $br['id'] ?>" <?= ($followUp['behavior_record_id'] ?? '') == $br['id'] ? 'checked' : '' ?> class="mt-1 text-red-600 focus:ring-red-500">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium text-red-600"><?= formatDate($br['incident_date'], 'short') ?></span>
                                <span class="px-1.5 py-0.5 bg-red-100 text-red-700 rounded text-[10px] font-medium"><?= $br['points'] ?> poin</span>
                                <span class="px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px]"><?= ucfirst($br['severity']) ?></span>
                            </div>
                            <p class="text-sm text-gray-800 font-medium"><?= htmlspecialchars($br['category_name']) ?></p>
                            <?php if ($br['description']): ?>
                            <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($br['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <label class="flex items-center gap-2 p-2 text-sm text-gray-500 cursor-pointer">
                    <input type="radio" name="behavior_record_id" value="" <?= empty($followUp['behavior_record_id'] ?? '') ? 'checked' : '' ?> class="text-gray-400">
                    <span>Tidak terkait catatan spesifik</span>
                </label>
                <p id="noBehaviorMsg" class="text-xs text-gray-400 italic hidden p-2">Siswa ini belum memiliki catatan pelanggaran.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Tindak Lanjut *</label>
                    <select name="follow_up_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach (['teguran_lisan'=>'Teguran Lisan','nasihat'=>'Nasihat','mediasi'=>'Mediasi','komunikasi_ortu'=>'Komunikasi Orang Tua','pembinaan_kepsek'=>'Pembinaan Kepala Sekolah','skorsing'=>'Skorsing','lainnya'=>'Lainnya'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($followUp['follow_up_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                    <input type="date" name="follow_up_date" value="<?= $followUp['follow_up_date'] ?? date('Y-m-d') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Pembinaan *</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required><?= htmlspecialchars($followUp['description'] ?? '') ?></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Hasil Pembinaan</label>
                <textarea name="result" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Hasil/respon siswa setelah pembinaan"><?= htmlspecialchars($followUp['result'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="belum_diproses" <?= ($followUp['status'] ?? '') === 'belum_diproses' ? 'selected' : '' ?>>Belum Diproses</option>
                        <option value="dalam_pemantauan" <?= ($followUp['status'] ?? '') === 'dalam_pemantauan' ? 'selected' : '' ?>>Dalam Pemantauan</option>
                        <option value="selesai" <?= ($followUp['status'] ?? '') === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-4 mb-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="parent_involved" value="1" <?= ($followUp['parent_involved'] ?? 0) ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">Melibatkan orang tua</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="show_to_parent" value="1" <?= ($followUp['show_to_parent'] ?? 0) ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">Tampilkan ke orang tua</span>
                </label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"><i class="fas fa-save"></i> Simpan</button>
                <a href="<?= BASE_URL ?>modules/wali_kelas/follow_ups.php" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
function loadBehaviorRecords(studentId) {
    const section = document.getElementById('behaviorRecordsSection');
    const list = document.getElementById('behaviorRecordsList');
    const noMsg = document.getElementById('noBehaviorMsg');

    if (!studentId) {
        section.style.display = 'none';
        return;
    }

    section.style.display = '';
    list.innerHTML = '<div class="p-4 text-center text-sm text-gray-400"><i class="fas fa-spinner fa-spin"></i> Memuat catatan pelanggaran...</div>';
    noMsg.classList.add('hidden');

    fetch('<?= BASE_URL ?>modules/wali_kelas/get_behavior_records.php?student_id=' + studentId)
    .then(r => r.json())
    .then(data => {
        if (!data.length) {
            list.innerHTML = '';
            noMsg.classList.remove('hidden');
            return;
        }
        noMsg.classList.add('hidden');
        list.innerHTML = data.map(r => `
            <label class="flex items-start gap-3 p-3 hover:bg-red-50 border-b border-gray-100 last:border-0 cursor-pointer">
                <input type="radio" name="behavior_record_id" value="${r.id}" class="mt-1 text-red-600 focus:ring-red-500">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-red-600">${r.date}</span>
                        <span class="px-1.5 py-0.5 bg-red-100 text-red-700 rounded text-[10px] font-medium">${r.points} poin</span>
                        <span class="px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px]">${r.severity}</span>
                    </div>
                    <p class="text-sm text-gray-800 font-medium">${r.category}</p>
                    ${r.description ? '<p class="text-xs text-gray-500 truncate">' + r.description + '</p>' : ''}
                </div>
            </label>
        `).join('') + `
            <label class="flex items-center gap-2 p-3 text-sm text-gray-500 cursor-pointer border-t border-gray-100">
                <input type="radio" name="behavior_record_id" value="" checked class="text-gray-400">
                <span>Tidak terkait catatan spesifik</span>
            </label>
        `;
    })
    .catch(() => {
        list.innerHTML = '<p class="p-3 text-xs text-red-500">Gagal memuat data.</p>';
    });
}

// Auto-load if student already selected (edit mode)
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('studentSelect');
    if (sel && sel.value) loadBehaviorRecords(sel.value);
});
</script>

<?php else:
    $statusFilter = get('status');
    $where = "s.class_id = ?";
    $params = [$classId];
    if (!empty($statusFilter)) { $where .= " AND f.status = ?"; $params[] = $statusFilter; }

    $followUps = $db->fetchAll("SELECT f.*, s.full_name, s.nis FROM follow_ups f JOIN students s ON f.student_id = s.id WHERE {$where} ORDER BY f.created_at DESC", $params);
    
    $counts = [
        'all' => count($followUps),
        'belum_diproses' => $db->fetchColumn("SELECT COUNT(*) FROM follow_ups f JOIN students s ON f.student_id = s.id WHERE s.class_id = ? AND f.status = 'belum_diproses'", [$classId]),
        'dalam_pemantauan' => $db->fetchColumn("SELECT COUNT(*) FROM follow_ups f JOIN students s ON f.student_id = s.id WHERE s.class_id = ? AND f.status = 'dalam_pemantauan'", [$classId]),
    ];
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Tindak Lanjut Pembinaan</h3>
            <p class="text-sm text-gray-500"><?= $counts['belum_diproses'] ?> belum diproses, <?= $counts['dalam_pemantauan'] ?> dalam pemantauan</p>
        </div>
        <a href="?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"><i class="fas fa-plus"></i> Buat Tindak Lanjut</a>
    </div>

    <div class="p-4 border-b border-gray-50 bg-gray-50/50 flex gap-2 flex-wrap">
        <a href="?" class="px-3 py-1.5 rounded-lg text-sm <?= empty($statusFilter) ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600' ?>">Semua</a>
        <a href="?status=belum_diproses" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'belum_diproses' ? 'bg-red-600 text-white' : 'bg-white border text-gray-600' ?>">Belum Diproses</a>
        <a href="?status=dalam_pemantauan" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'dalam_pemantauan' ? 'bg-yellow-600 text-white' : 'bg-white border text-gray-600' ?>">Dalam Pemantauan</a>
        <a href="?status=selesai" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'selesai' ? 'bg-green-600 text-white' : 'bg-white border text-gray-600' ?>">Selesai</a>
    </div>

    <div class="divide-y divide-gray-100">
        <?php if (empty($followUps)): ?>
        <div class="p-8 text-center text-gray-500">Belum ada tindak lanjut.</div>
        <?php else: ?>
        <?php foreach ($followUps as $f): ?>
        <div class="p-4 hover:bg-gray-50">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <?= statusBadge($f['status'], 'followup') ?>
                        <span class="text-xs text-gray-400"><?= formatDate($f['follow_up_date'], 'short') ?></span>
                    </div>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($f['full_name']) ?> <span class="text-xs text-gray-400">(<?= $f['nis'] ?>)</span></p>
                    <p class="text-sm text-gray-600 mt-1"><span class="font-medium"><?= ucfirst(str_replace('_', ' ', $f['follow_up_type'])) ?>:</span> <?= htmlspecialchars(truncate($f['description'], 100)) ?></p>
                    <?php if ($f['result']): ?>
                    <p class="text-xs text-green-600 mt-1"><i class="fas fa-check"></i> <?= htmlspecialchars(truncate($f['result'], 60)) ?></p>
                    <?php endif; ?>
                    <?php if ($f['parent_involved']): ?>
                    <span class="inline-flex items-center text-xs text-purple-600 mt-1"><i class="fas fa-user-friends mr-1"></i> Orang tua terlibat</span>
                    <?php endif; ?>
                </div>
                <a href="?action=edit&id=<?= $f['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm"><i class="fas fa-edit"></i></a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
