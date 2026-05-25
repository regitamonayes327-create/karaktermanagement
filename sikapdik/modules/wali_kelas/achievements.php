<?php
/**
 * Achievements - Wali Kelas / Guru Mapel / Admin
 * Integrated with achievement_categories
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin', 'guru_mapel']);

define('PAGE_TITLE', 'Prestasi Siswa');

$db = Database::getInstance();
$classId = $_SESSION['class_id'] ?? 0;
$action = get('action', 'list');
$id = (int) get('id', 0);

// Level labels
$levelLabels = [
    'sekolah' => 'Tingkat Sekolah',
    'desa' => 'Tingkat Desa/Kelurahan',
    'kecamatan' => 'Tingkat Kecamatan',
    'kabupaten' => 'Tingkat Kabupaten',
    'nasional' => 'Tingkat Nasional',
    'internasional' => 'Tingkat Internasional',
];
$levelColors = [
    'sekolah' => 'bg-blue-100 text-blue-800',
    'desa' => 'bg-teal-100 text-teal-800',
    'kecamatan' => 'bg-purple-100 text-purple-800',
    'kabupaten' => 'bg-orange-100 text-orange-800',
    'nasional' => 'bg-red-100 text-red-800',
    'internasional' => 'bg-yellow-100 text-yellow-800',
];

// Handle form submission
if (isPost() && Security::validateCSRF()) {
    $studentId = (int) post('student_id');
    $achievementCategoryId = (int) post('achievement_category_id') ?: null;
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
    if (!in_array($level, array_keys($levelLabels))) $errors[] = 'Tingkat prestasi tidak valid.';

    // If achievement_category selected, auto-fill points from category
    if ($achievementCategoryId) {
        $achCat = $db->fetch("SELECT points, level FROM achievement_categories WHERE id = ?", [$achievementCategoryId]);
        if ($achCat) {
            $points = $points ?: $achCat['points'];
            $level = $achCat['level'];
        }
    }

    if (empty($errors)) {
        $data = [
            'student_id' => $studentId,
            'achievement_category_id' => $achievementCategoryId,
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
        $student = $db->fetch("SELECT full_name FROM students WHERE id = ?", [$studentId]);
        Auth::logActivity('create_achievement', 'achievements', "Prestasi: {$title} - {$student['full_name']} ({$levelLabels[$level]})");
        setFlash('success', 'Prestasi berhasil dicatat.');
        redirect('modules/wali_kelas/achievements.php');
    } else {
        setFlash('error', implode('<br>', $errors));
    }
}

include __DIR__ . '/../../templates/header.php';

if ($action === 'add'):
    // Get students
    if (Auth::getRole() === 'wali_kelas' && $classId) {
        $students = $db->fetchAll("SELECT s.id, s.full_name, s.nis, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.class_id = ? AND s.is_active = 1 ORDER BY s.full_name", [$classId]);
    } else {
        $students = $db->fetchAll("SELECT s.id, s.full_name, s.nis, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.is_active = 1 ORDER BY c.grade_level, s.full_name");
    }

    // Get achievement categories grouped by level
    $achCategories = $db->fetchAll("SELECT * FROM achievement_categories WHERE is_active = 1 ORDER BY FIELD(level, 'sekolah','desa','kecamatan','kabupaten','nasional','internasional'), category_name");
?>
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-trophy text-yellow-500"></i> Catat Prestasi Baru</h3>
            <a href="<?= BASE_URL ?>modules/wali_kelas/achievements.php" class="text-sm text-gray-500"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>

            <!-- Siswa -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Siswa *</label>
                <select name="student_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['nis'] ?>) - Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Kategori Prestasi (from achievement_categories) -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori Prestasi</label>
                <select name="achievement_category_id" id="achCatSelect" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" onchange="onCategoryChange(this)">
                    <option value="" data-points="0" data-level="">-- Pilih Kategori (opsional) --</option>
                    <?php
                    $currentLevel = '';
                    foreach ($achCategories as $ac):
                        if ($ac['level'] !== $currentLevel):
                            if ($currentLevel !== '') echo '</optgroup>';
                            $currentLevel = $ac['level'];
                            echo '<optgroup label="' . ($levelLabels[$currentLevel] ?? ucfirst($currentLevel)) . '">';
                        endif;
                    ?>
                    <option value="<?= $ac['id'] ?>" data-points="<?= $ac['points'] ?>" data-level="<?= $ac['level'] ?>"><?= htmlspecialchars($ac['category_name']) ?> (+<?= $ac['points'] ?> poin)</option>
                    <?php endforeach; ?>
                    <?php if ($currentLevel !== '') echo '</optgroup>'; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">Pilih dari daftar atau isi manual di bawah</p>
            </div>

            <!-- Judul Prestasi -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Judul Prestasi *</label>
                <input type="text" name="title" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required placeholder="Contoh: Juara 1 Lomba Membaca Puisi">
            </div>

            <!-- Bidang & Tingkat & Poin -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bidang</label>
                    <select name="category" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                        <?php foreach (['akademik','non_akademik','seni','olahraga','keagamaan','lainnya'] as $c): ?>
                        <option value="<?= $c ?>"><?= ucfirst(str_replace('_', ' ', $c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tingkat *</label>
                    <select name="level" id="levelSelect" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
                        <?php foreach ($levelLabels as $val => $lbl): ?>
                        <option value="<?= $val ?>"><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poin</label>
                    <input type="number" name="points" id="pointsInput" value="10" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" min="0">
                </div>
            </div>

            <!-- Tanggal -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Prestasi</label>
                <input type="date" name="achievement_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
            </div>

            <!-- Deskripsi -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent" placeholder="Keterangan tambahan (opsional)"></textarea>
            </div>

            <!-- Show to parent -->
            <div class="mb-6">
                <label class="flex items-center gap-2"><input type="checkbox" name="show_to_parent" value="1" checked class="rounded border-gray-300 text-yellow-600"><span class="text-sm text-gray-700">Tampilkan ke orang tua</span></label>
            </div>

            <button type="submit" class="w-full px-6 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition font-medium"><i class="fas fa-trophy"></i> Simpan Prestasi</button>
        </form>
    </div>
</div>

<script>
function onCategoryChange(select) {
    var option = select.options[select.selectedIndex];
    var points = option.getAttribute('data-points');
    var level = option.getAttribute('data-level');
    
    if (points && parseInt(points) > 0) {
        document.getElementById('pointsInput').value = points;
    }
    if (level) {
        document.getElementById('levelSelect').value = level;
    }
}
</script>

<?php else:
    // LIST VIEW
    $levelFilter = get('level');
    $where = "1=1";
    $params = [];

    // Filter by class for wali_kelas
    if (Auth::getRole() === 'wali_kelas' && $classId) {
        $where .= " AND s.class_id = ?";
        $params[] = $classId;
    }
    if (!empty($levelFilter) && in_array($levelFilter, array_keys($levelLabels))) {
        $where .= " AND a.level = ?";
        $params[] = $levelFilter;
    }

    $achievements = $db->fetchAll("SELECT a.*, s.full_name, s.nis, c.class_name, c.grade_level, ac.category_name as ach_cat_name
        FROM achievements a 
        JOIN students s ON a.student_id = s.id 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN achievement_categories ac ON a.achievement_category_id = ac.id
        WHERE {$where} 
        ORDER BY a.achievement_date DESC LIMIT 100", $params);

    // Stats per level
    $statsWhere = (Auth::getRole() === 'wali_kelas' && $classId) ? "AND s.class_id = {$classId}" : "";
    $levelStats = $db->fetchAll("SELECT a.level, COUNT(*) as total, SUM(a.points) as total_points 
        FROM achievements a JOIN students s ON a.student_id = s.id 
        WHERE 1=1 {$statsWhere} 
        GROUP BY a.level");
    $stats = [];
    foreach ($levelStats as $ls) {
        $stats[$ls['level']] = $ls;
    }
?>

<!-- Level Stats Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <?php foreach ($levelLabels as $lvl => $lbl): ?>
    <a href="?level=<?= $lvl ?>" class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center hover:shadow-md transition <?= $levelFilter === $lvl ? 'ring-2 ring-yellow-400' : '' ?>">
        <p class="text-xl font-bold text-gray-800"><?= $stats[$lvl]['total'] ?? 0 ?></p>
        <p class="text-[10px] text-gray-500 leading-tight mt-1"><?= $lbl ?></p>
        <?php if (!empty($stats[$lvl]['total_points'])): ?>
        <p class="text-xs text-yellow-600 font-medium mt-0.5">+<?= $stats[$lvl]['total_points'] ?> poin</p>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Daftar Prestasi Siswa</h3>
            <p class="text-sm text-gray-500"><?= count($achievements) ?> prestasi tercatat</p>
        </div>
        <a href="?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 text-sm font-medium"><i class="fas fa-plus"></i> Catat Prestasi</a>
    </div>

    <!-- Level Filter -->
    <div class="p-4 border-b border-gray-50 bg-gray-50/50 flex gap-2 flex-wrap">
        <a href="?" class="px-3 py-1.5 rounded-lg text-sm <?= empty($levelFilter) ? 'bg-yellow-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">Semua</a>
        <?php foreach ($levelLabels as $lvl => $lbl): ?>
        <a href="?level=<?= $lvl ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $levelFilter === $lvl ? 'bg-yellow-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
    </div>

    <!-- List -->
    <div class="divide-y divide-gray-100">
        <?php foreach ($achievements as $a): ?>
        <div class="p-4 hover:bg-gray-50 flex items-start gap-3">
            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-trophy text-yellow-600"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($a['title']) ?></p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?= $levelColors[$a['level']] ?? 'bg-gray-100 text-gray-800' ?>">
                        <?= $levelLabels[$a['level']] ?? ucfirst($a['level']) ?>
                    </span>
                </div>
                <p class="text-sm text-gray-600"><?= htmlspecialchars($a['full_name']) ?> - Kelas <?= $a['grade_level'] ?>-<?= $a['class_name'] ?></p>
                <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                    <span><i class="fas fa-folder"></i> <?= ucfirst(str_replace('_',' ',$a['category'])) ?></span>
                    <span><i class="fas fa-calendar"></i> <?= formatDate($a['achievement_date'], 'short') ?></span>
                    <?php if ($a['ach_cat_name']): ?>
                    <span><i class="fas fa-tag"></i> <?= htmlspecialchars($a['ach_cat_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($a['points']): ?>
            <span class="text-sm font-bold text-yellow-600 flex-shrink-0">+<?= $a['points'] ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($achievements)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-trophy text-gray-300 text-3xl mb-2"></i>
            <p>Belum ada prestasi tercatat<?= $levelFilter ? ' untuk tingkat ini' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
