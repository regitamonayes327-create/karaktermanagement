<?php
/**
 * Teacher Notes Validation (Wali Kelas validates Guru Mapel inputs)
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas']);

define('PAGE_TITLE', 'Catatan Guru Mapel');

$db = Database::getInstance();
$classId = $_SESSION['class_id'] ?? 0;

// Handle validation
if (isPost() && Security::validateCSRF()) {
    $recordId = (int) post('record_id');
    $validationAction = post('validation_action');
    $validationNotes = Security::clean(post('validation_notes'));

    if ($recordId && in_array($validationAction, ['approved', 'rejected'])) {
        $db->update('behavior_records', [
            'validation_status' => $validationAction,
            'validated_by' => Auth::getUserId(),
            'validation_notes' => $validationNotes ?: null,
            'validated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$recordId]);

        Auth::logActivity('validate_behavior', 'behavior', "Validasi catatan ID:{$recordId} - {$validationAction}");
        setFlash('success', 'Catatan berhasil ' . ($validationAction === 'approved' ? 'disetujui' : 'ditolak') . '.');
    }
    redirect('modules/wali_kelas/teacher_notes.php');
}

// Get pending records from guru mapel for students in this class
$statusFilter = get('status', 'pending');
$where = "br.recorder_role = 'guru_mapel' AND s.class_id = ?";
$params = [$classId];

if ($statusFilter !== 'all') {
    $where .= " AND br.validation_status = ?";
    $params[] = $statusFilter;
}

$records = $db->fetchAll("SELECT br.*, s.full_name as student_name, s.nis, 
    bc.category_name, u.full_name as recorder_name
    FROM behavior_records br 
    JOIN students s ON br.student_id = s.id 
    JOIN behavior_categories bc ON br.category_id = bc.id 
    LEFT JOIN users u ON br.recorded_by = u.id
    WHERE {$where} 
    ORDER BY br.created_at DESC", $params);

$pendingCount = $db->fetchColumn("SELECT COUNT(*) FROM behavior_records br JOIN students s ON br.student_id = s.id WHERE br.recorder_role = 'guru_mapel' AND s.class_id = ? AND br.validation_status = 'pending'", [$classId]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Catatan dari Guru Mapel</h3>
            <p class="text-sm text-gray-500">
                <?php if ($pendingCount > 0): ?>
                <span class="text-orange-600 font-medium"><?= $pendingCount ?> catatan menunggu validasi</span>
                <?php else: ?>
                Semua catatan sudah divalidasi
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Status Filter -->
    <div class="p-4 border-b border-gray-50 bg-gray-50/50 flex gap-2">
        <a href="?status=pending" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'pending' ? 'bg-yellow-600 text-white' : 'bg-white border text-gray-600' ?>">Menunggu <?php if($pendingCount): ?><span class="bg-white/20 px-1 rounded"><?= $pendingCount ?></span><?php endif; ?></a>
        <a href="?status=approved" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'approved' ? 'bg-green-600 text-white' : 'bg-white border text-gray-600' ?>">Disetujui</a>
        <a href="?status=rejected" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'rejected' ? 'bg-red-600 text-white' : 'bg-white border text-gray-600' ?>">Ditolak</a>
        <a href="?status=all" class="px-3 py-1.5 rounded-lg text-sm <?= $statusFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600' ?>">Semua</a>
    </div>

    <!-- Records -->
    <div class="divide-y divide-gray-100">
        <?php if (empty($records)): ?>
        <div class="p-8 text-center text-gray-500">Tidak ada catatan untuk filter ini.</div>
        <?php else: ?>
        <?php foreach ($records as $r): ?>
        <div class="p-4 hover:bg-gray-50">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <?= statusBadge($r['type'], 'behavior') ?>
                        <?= statusBadge($r['validation_status'], 'validation') ?>
                    </div>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($r['student_name']) ?> <span class="text-xs text-gray-400">(<?= $r['nis'] ?>)</span></p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($r['category_name']) ?> <span class="font-bold <?= $r['points'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">(<?= $r['points'] > 0 ? '+' : '' ?><?= $r['points'] ?>)</span></p>
                    <?php if ($r['description']): ?>
                    <p class="text-xs text-gray-500 mt-1"><i class="fas fa-comment text-gray-400"></i> <?= htmlspecialchars($r['description']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-400 mt-1">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($r['recorder_name']) ?> | 
                        <i class="fas fa-calendar"></i> <?= formatDate($r['incident_date'], 'short') ?>
                    </p>
                </div>

                <?php if ($r['validation_status'] === 'pending'): ?>
                <div class="flex gap-2 sm:flex-col">
                    <form method="POST" class="inline">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="validation_action" value="approved">
                        <input type="hidden" name="validation_notes" value="">
                        <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">
                            <i class="fas fa-check"></i> Setujui
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return promptReject(this)">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="validation_action" value="rejected">
                        <input type="hidden" name="validation_notes" value="" class="reject-notes">
                        <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700">
                            <i class="fas fa-times"></i> Tolak
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function promptReject(form) {
    const reason = prompt('Alasan penolakan (opsional):');
    if (reason === null) return false;
    form.querySelector('.reject-notes').value = reason;
    return true;
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
