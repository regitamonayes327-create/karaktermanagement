<?php
/**
 * Achievements - Wali Kelas
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin', 'guru_mapel', 'kepala_sekolah']);

define('PAGE_TITLE', 'Prestasi Siswa');

$db = Database::getInstance();
$classId = $_SESSION['class_id'] ?? 0;
$action = get('action', 'list');
$id = (int) get('id', 0);

if (isPost() && Security::validateCSRF()) {
    $studentId = (int) post('student_id');
    $title = Security::clean(post('title'));
    $category = Security::clean(post('category'));
    $level = Security::clean(post('level'));
    $achievementDate = Security::clean(post('achievement_date')) ?: date('Y-m-d');
    $description = Security::clean(post('description'));
    $points = (int) post('points', 0);
    $showToParent = (int) post('show_to_parent', 1);

    $errors = [];
    if (!$studentId) $errors[] = 'Pilih siswa.';
    if (empty($title)) $errors[] = 'Judul prestasi harus diisi.';

    if (empty($errors)) {
        $data = [
            'student_id' => $studentId,
            'title' => $title,
            'category' => $category,
            'level' => $level,
            'achievement_date' => $achievementDate,
            'description' => $description ?: null,
            'points' => $points,
            'show_to_parent' => $showToParent,
            'recorded_by' => Auth::getUserId()
        ];
        $db->insert('achievements', $data);
        Auth::logActivity('create_achievement', 'achievements', "Prestasi: {$title}");
        NotificationHelper::onAchievementRecorded($studentId, $title, $level, $points);
        setFlash('success', 'Prestasi berhasil dicatat.');
        redirect('modules/wali_kelas/achievements.php');
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
            <h3 class="text-lg font-semibold text-gray-800">Catat Prestasi Baru</h3>
            <a href="<?= BASE_URL ?>modules/wali_kelas/achievements.php" class="text-sm text-gray-500"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Siswa *</label>
                <select name="student_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent combo-search" required data-placeholder="Ketik nama siswa...">
                    <option value="">-- Pilih --</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['nis'] ?>) - Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Judul Prestasi *</label>
                <input type="text" name="title" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required placeholder="Contoh: Juara 1 Lomba Membaca">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                    <select name="category" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <?php foreach (['akademik','non_akademik','seni','olahraga','keagamaan','lainnya'] as $c): ?>
                        <option value="<?= $c ?>"><?= ucfirst(str_replace('_', ' ', $c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tingkat</label>
                    <select name="level" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <?php foreach (['kelas','sekolah','kecamatan','kabupaten','provinsi','nasional','internasional'] as $l): ?>
                        <option value="<?= $l ?>"><?= ucfirst($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poin</label>
                    <input type="number" name="points" value="10" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" min="0">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                <input type="date" name="achievement_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Keterangan tambahan"></textarea>
            </div>
            <div class="mb-6">
                <label class="flex items-center gap-2"><input type="checkbox" name="show_to_parent" value="1" checked class="rounded border-gray-300 text-blue-600"><span class="text-sm text-gray-700">Tampilkan ke orang tua</span></label>
            </div>
            <button type="submit" class="w-full px-6 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition font-medium"><i class="fas fa-trophy"></i> Simpan Prestasi</button>
        </form>
    </div>
</div>
<?php else:
    $achievements = $db->fetchAll("SELECT a.*, s.full_name, s.nis, c.class_name, c.grade_level 
        FROM achievements a JOIN students s ON a.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id 
        " . ($classId ? "WHERE s.class_id = {$classId}" : "") . " ORDER BY a.achievement_date DESC LIMIT 50");
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-800">Daftar Prestasi</h3>
        <a href="?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 text-sm"><i class="fas fa-plus"></i> Catat Prestasi</a>
    </div>
    <div class="divide-y divide-gray-100">
        <?php foreach ($achievements as $a): ?>
        <div class="p-4 hover:bg-gray-50 flex items-start gap-3">
            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center"><i class="fas fa-trophy text-yellow-600"></i></div>
            <div class="flex-1">
                <p class="font-medium text-gray-800"><?= htmlspecialchars($a['title']) ?></p>
                <p class="text-sm text-gray-600"><?= htmlspecialchars($a['full_name']) ?> - Kelas <?= $a['grade_level'] ?>-<?= $a['class_name'] ?></p>
                <p class="text-xs text-gray-400"><?= ucfirst(str_replace('_',' ',$a['category'])) ?> | <?= ucfirst($a['level']) ?> | <?= formatDate($a['achievement_date'], 'short') ?></p>
            </div>
            <?php if ($a['points']): ?><span class="text-sm font-bold text-green-600">+<?= $a['points'] ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($achievements)): ?><div class="p-8 text-center text-gray-500">Belum ada prestasi.</div><?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
