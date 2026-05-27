<?php
/**
 * Class Promotion & Graduation System
 * SIKAPDIK - Kenaikan Kelas & Kelulusan
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Kenaikan Kelas & Kelulusan');

$db = Database::getInstance();
$action = get('action', 'select');
$classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");
$tahunAjaran = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'active_academic_year'") ?: date('Y') . '/' . (date('Y')+1);

// Process promotion
if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'process_promotion') {
        $studentIds = $_POST['student_ids'] ?? [];
        $promotionType = Security::clean(post('promotion_type'));
        $fromClassId = (int) post('from_class_id');
        $toClassId = (int) post('to_class_id') ?: null;
        $notes = Security::clean(post('notes'));
        
        if (empty($studentIds)) {
            setFlash('error', 'Pilih minimal 1 siswa.');
        } elseif (!in_array($promotionType, ['naik_kelas','tinggal_kelas','lulus','pindah_sekolah','dikeluarkan'])) {
            setFlash('error', 'Jenis kenaikan tidak valid.');
        } elseif ($promotionType === 'naik_kelas' && !$toClassId) {
            setFlash('error', 'Pilih kelas tujuan untuk naik kelas.');
        } else {
            $count = 0;
            $db->beginTransaction();
            try {
                foreach ($studentIds as $sid) {
                    $sid = (int) $sid;
                    
                    // Record history
                    $db->insert('class_promotions', [
                        'student_id' => $sid,
                        'from_class_id' => $fromClassId ?: null,
                        'to_class_id' => $toClassId,
                        'academic_year' => $tahunAjaran,
                        'promotion_type' => $promotionType,
                        'notes' => $notes ?: null,
                        'promoted_by' => Auth::getUserId()
                    ]);
                    
                    // Update student
                    switch ($promotionType) {
                        case 'naik_kelas':
                            $db->update('students', ['class_id' => $toClassId], 'id = ?', [$sid]);
                            break;
                        case 'tinggal_kelas':
                            // Stay in same class, no change
                            break;
                        case 'lulus':
                            $db->update('students', ['status' => 'lulus', 'is_active' => 0, 'class_id' => null], 'id = ?', [$sid]);
                            break;
                        case 'pindah_sekolah':
                            $db->update('students', ['status' => 'pindah', 'is_active' => 0], 'id = ?', [$sid]);
                            break;
                        case 'dikeluarkan':
                            $db->update('students', ['status' => 'dikeluarkan', 'is_active' => 0], 'id = ?', [$sid]);
                            break;
                    }
                    $count++;
                }
                $db->commit();
                
                $typeLabels = ['naik_kelas'=>'Naik Kelas','tinggal_kelas'=>'Tinggal Kelas','lulus'=>'Lulus','pindah_sekolah'=>'Pindah Sekolah','dikeluarkan'=>'Dikeluarkan'];
                Auth::logActivity('class_promotion', 'students', "Proses {$typeLabels[$promotionType]}: {$count} siswa");
                setFlash('success', "Berhasil memproses {$count} siswa ({$typeLabels[$promotionType]}).");
            } catch (Exception $e) {
                $db->rollback();
                setFlash('error', 'Gagal: ' . $e->getMessage());
            }
            redirect('modules/admin/class_promotion.php');
        }
    }
}

include __DIR__ . '/../../templates/header.php';

// Get selected class students
$selectedClassId = (int) get('class_id', 0);
$students = [];
if ($selectedClassId) {
    $students = $db->fetchAll("SELECT s.*, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.class_id = ? AND s.is_active = 1 ORDER BY s.full_name", [$selectedClassId]);
}

// Get promotion history
$recentPromotions = $db->fetchAll("SELECT cp.*, s.full_name, s.nis, fc.class_name as from_class, fc.grade_level as from_grade, tc.class_name as to_class, tc.grade_level as to_grade
    FROM class_promotions cp 
    JOIN students s ON cp.student_id = s.id
    LEFT JOIN classes fc ON cp.from_class_id = fc.id
    LEFT JOIN classes tc ON cp.to_class_id = tc.id
    ORDER BY cp.promoted_at DESC LIMIT 20");
?>

<div class="max-w-4xl mx-auto">
    <!-- Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
        <p class="text-sm text-blue-800"><i class="fas fa-info-circle"></i> <strong>Tahun Ajaran Aktif:</strong> <?= htmlspecialchars($tahunAjaran) ?>. Proses kenaikan kelas/kelulusan akan tercatat pada tahun ajaran ini.</p>
    </div>

    <!-- Step 1: Select Class -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-graduation-cap text-blue-600"></i> Kenaikan Kelas & Kelulusan
        </h3>
        
        <form method="GET" class="flex gap-3 items-end mb-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Kelas Asal</label>
                <select name="class_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm" onchange="this.form.submit()">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClassId == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= htmlspecialchars($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($selectedClassId && !empty($students)): ?>
        <!-- Step 2 & 3: Process -->
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="process_promotion">
            <input type="hidden" name="from_class_id" value="<?= $selectedClassId ?>">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 p-4 bg-gray-50 rounded-lg">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Proses *</label>
                    <select name="promotion_type" id="promotionType" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" required onchange="toggleTargetClass(this.value)">
                        <option value="">-- Pilih --</option>
                        <option value="naik_kelas">Naik Kelas</option>
                        <option value="tinggal_kelas">Tinggal Kelas</option>
                        <option value="lulus">Lulus / Alumni</option>
                        <option value="pindah_sekolah">Pindah Sekolah</option>
                        <option value="dikeluarkan">Dikeluarkan</option>
                    </select>
                </div>
                <div id="targetClassDiv">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kelas Tujuan</label>
                    <select name="to_class_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <option value="">-- Pilih Kelas Tujuan --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>">Kelas <?= $c['grade_level'] ?> - <?= htmlspecialchars($c['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <input type="text" name="notes" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="Opsional">
                </div>
            </div>

            <!-- Student List -->
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-gray-700"><?= count($students) ?> siswa di kelas ini:</p>
                    <label class="flex items-center gap-2 text-sm text-blue-600 cursor-pointer">
                        <input type="checkbox" id="selectAll" onclick="toggleAll(this.checked)" class="rounded border-gray-300 text-blue-600">
                        Pilih Semua
                    </label>
                </div>
                <div class="border border-gray-200 rounded-lg max-h-72 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0"><tr>
                            <th class="px-3 py-2 text-center w-10"><input type="checkbox" onclick="toggleAll(this.checked)" class="rounded"></th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Nama</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">NIS</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">JK</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($students as $s): ?>
                            <tr class="hover:bg-blue-50">
                                <td class="px-3 py-2 text-center"><input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="student-cb rounded border-gray-300 text-blue-600"></td>
                                <td class="px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></td>
                                <td class="px-3 py-2 text-gray-600"><?= $s['nis'] ?></td>
                                <td class="px-3 py-2"><?= $s['gender'] === 'L' ? 'L' : 'P' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2 font-medium" onclick="return confirm('Proses kenaikan kelas/kelulusan untuk siswa terpilih?')">
                <i class="fas fa-check-circle"></i> Proses Kenaikan/Kelulusan
            </button>
        </form>

        <?php elseif ($selectedClassId): ?>
        <p class="text-center py-6 text-gray-500">Tidak ada siswa aktif di kelas ini.</p>
        <?php endif; ?>
    </div>

    <!-- Recent History -->
    <?php if (!empty($recentPromotions)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Riwayat Kenaikan/Kelulusan Terbaru</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50"><tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Siswa</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Dari</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Ke</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Jenis</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Tahun Ajaran</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Tanggal</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($recentPromotions as $p):
                        $typeColors = ['naik_kelas'=>'bg-green-100 text-green-800','tinggal_kelas'=>'bg-yellow-100 text-yellow-800','lulus'=>'bg-blue-100 text-blue-800','pindah_sekolah'=>'bg-purple-100 text-purple-800','dikeluarkan'=>'bg-red-100 text-red-800'];
                        $typeLabels = ['naik_kelas'=>'Naik Kelas','tinggal_kelas'=>'Tinggal','lulus'=>'Lulus','pindah_sekolah'=>'Pindah','dikeluarkan'=>'Dikeluarkan'];
                    ?>
                    <tr>
                        <td class="px-3 py-2 font-medium"><?= htmlspecialchars($p['full_name']) ?> <span class="text-gray-400">(<?= $p['nis'] ?>)</span></td>
                        <td class="px-3 py-2"><?= $p['from_class'] ? 'Kelas '.$p['from_grade'].'-'.$p['from_class'] : '-' ?></td>
                        <td class="px-3 py-2"><?= $p['to_class'] ? 'Kelas '.$p['to_grade'].'-'.$p['to_class'] : '-' ?></td>
                        <td class="px-3 py-2"><span class="px-1.5 py-0.5 rounded text-[10px] font-medium <?= $typeColors[$p['promotion_type']] ?? 'bg-gray-100' ?>"><?= $typeLabels[$p['promotion_type']] ?? $p['promotion_type'] ?></span></td>
                        <td class="px-3 py-2 text-gray-500"><?= $p['academic_year'] ?></td>
                        <td class="px-3 py-2 text-gray-400"><?= formatDate($p['promoted_at'], 'short') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.student-cb').forEach(cb => cb.checked = checked);
}
function toggleTargetClass(type) {
    const div = document.getElementById('targetClassDiv');
    div.style.display = (type === 'naik_kelas') ? '' : 'none';
}
// Init
toggleTargetClass(document.getElementById('promotionType')?.value || '');
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
