<?php
/**
 * Student Potentials - Wali Kelas
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin', 'guru_mapel']);

define('PAGE_TITLE', 'Potensi Siswa');

$db = Database::getInstance();
$classId = $_SESSION['class_id'] ?? 0;
$action = get('action', 'list');

if (isPost() && Security::validateCSRF()) {
    $studentId = (int) post('student_id');
    $field = Security::clean(post('field'));
    $description = Security::clean(post('description'));
    $evidence = Security::clean(post('evidence'));
    $recommendation = Security::clean(post('recommendation'));

    $errors = [];
    if (!$studentId) $errors[] = 'Pilih siswa.';
    if (empty($description)) $errors[] = 'Deskripsi harus diisi.';

    if (empty($errors)) {
        $db->insert('potentials', [
            'student_id' => $studentId,
            'field' => $field,
            'description' => $description,
            'evidence' => $evidence ?: null,
            'recommendation' => $recommendation ?: null,
            'recorded_by' => Auth::getUserId()
        ]);
        Auth::logActivity('create_potential', 'potentials', "Potensi siswa: {$field}");
        setFlash('success', 'Potensi siswa berhasil dicatat.');
        redirect('modules/wali_kelas/potentials.php');
    } else {
        setFlash('error', implode('<br>', $errors));
    }
}

include __DIR__ . '/../../templates/header.php';

if ($action === 'add'):
    $students = $db->fetchAll("SELECT s.id, s.full_name, s.nis, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.is_active = 1 ORDER BY c.grade_level, s.full_name");
?>
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800">Catat Potensi Siswa</h3>
            <a href="<?= BASE_URL ?>modules/wali_kelas/potentials.php" class="text-sm text-gray-500"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Siswa *</label>
                <select name="student_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['nis'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Bidang Potensi *</label>
                <select name="field" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    <?php foreach (['akademik','seni','olahraga','literasi','keagamaan','kepemimpinan','sosial','teknologi','lainnya'] as $f): ?>
                    <option value="<?= $f ?>"><?= ucfirst($f) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Potensi *</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required placeholder="Jelaskan potensi yang diamati"></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Bukti Pengamatan</label>
                <textarea name="evidence" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Contoh: Sering menang lomba cerdas cermat, aktif di ekskul"></textarea>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Rekomendasi Pengembangan</label>
                <textarea name="recommendation" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Saran pengembangan untuk siswa"></textarea>
            </div>
            <button type="submit" class="w-full px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium"><i class="fas fa-lightbulb"></i> Simpan Potensi</button>
        </form>
    </div>
</div>
<?php else:
    $potentials = $db->fetchAll("SELECT p.*, s.full_name, s.nis, c.class_name, c.grade_level, u.full_name as recorder
        FROM potentials p JOIN students s ON p.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id LEFT JOIN users u ON p.recorded_by = u.id
        " . ($classId ? "WHERE s.class_id = {$classId}" : "") . " ORDER BY p.created_at DESC");
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-800">Pemetaan Potensi Siswa</h3>
        <a href="?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm"><i class="fas fa-plus"></i> Catat Potensi</a>
    </div>
    <div class="divide-y divide-gray-100">
        <?php foreach ($potentials as $p): ?>
        <div class="p-4 hover:bg-gray-50">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center"><i class="fas fa-lightbulb text-purple-600"></i></div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($p['full_name']) ?></p>
                        <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs"><?= ucfirst($p['field']) ?></span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($p['description']) ?></p>
                    <?php if ($p['recommendation']): ?><p class="text-xs text-purple-600 mt-1"><i class="fas fa-arrow-right"></i> <?= htmlspecialchars($p['recommendation']) ?></p><?php endif; ?>
                    <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($p['recorder']) ?> | <?= formatDate($p['created_at'], 'short') ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($potentials)): ?><div class="p-8 text-center text-gray-500">Belum ada data potensi.</div><?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
