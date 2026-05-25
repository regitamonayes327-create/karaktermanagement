<?php
/**
 * Input Behavior (Keteladanan & Pelanggaran) - Wali Kelas
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin']);

define('PAGE_TITLE', 'Input Perilaku');

$db = Database::getInstance();
$action = get('action', 'form');

// Get class for wali kelas
$classId = $_SESSION['class_id'] ?? (int) get('class_id', 0);

if (isPost() && Security::validateCSRF()) {
    $studentId = (int) post('student_id');
    $categoryId = (int) post('category_id');
    $description = Security::clean(post('description'));
    $incidentDate = Security::clean(post('incident_date'));
    $showToParent = (int) post('show_to_parent', 0);

    $errors = [];
    if (!$studentId) $errors[] = 'Pilih siswa.';
    if (!$categoryId) $errors[] = 'Pilih kategori perilaku.';
    if (empty($incidentDate)) $incidentDate = date('Y-m-d');

    if (empty($errors)) {
        // Get category details
        $category = $db->fetch("SELECT * FROM behavior_categories WHERE id = ?", [$categoryId]);
        
        if ($category) {
            $db->insert('behavior_records', [
                'student_id' => $studentId,
                'category_id' => $categoryId,
                'type' => $category['type'],
                'points' => $category['points'],
                'description' => $description ?: null,
                'incident_date' => $incidentDate,
                'recorded_by' => Auth::getUserId(),
                'recorder_role' => 'wali_kelas',
                'validation_status' => 'approved', // Wali kelas langsung approved
                'show_to_parent' => $showToParent
            ]);

            $student = $db->fetch("SELECT full_name FROM students WHERE id = ?", [$studentId]);
            Auth::logActivity('input_behavior', 'behavior', "Input {$category['type']}: {$student['full_name']} - {$category['category_name']}");
            
            setFlash('success', "Catatan perilaku berhasil disimpan. ({$category['type']}: {$category['category_name']}, Poin: {$category['points']})");
            redirect('modules/wali_kelas/input_behavior.php');
        }
    } else {
        setFlash('error', implode('<br>', $errors));
    }
}

// Get students for class
$students = [];
if ($classId > 0) {
    $students = $db->fetchAll("SELECT id, full_name, nis FROM students WHERE class_id = ? AND is_active = 1 ORDER BY full_name", [$classId]);
}
$allStudents = $db->fetchAll("SELECT s.id, s.full_name, s.nis, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.is_active = 1 ORDER BY c.grade_level, c.class_name, s.full_name");

// Get categories
$positiveCategories = $db->fetchAll("SELECT * FROM behavior_categories WHERE type = 'keteladanan' AND is_active = 1 ORDER BY category_name");
$negativeCategories = $db->fetchAll("SELECT * FROM behavior_categories WHERE type = 'pelanggaran' AND is_active = 1 ORDER BY severity DESC, category_name");

// Recent records
$recentRecords = $db->fetchAll("SELECT br.*, s.full_name, bc.category_name 
    FROM behavior_records br 
    JOIN students s ON br.student_id = s.id 
    JOIN behavior_categories bc ON br.category_id = bc.id 
    WHERE br.recorded_by = ? 
    ORDER BY br.created_at DESC LIMIT 10", [Auth::getUserId()]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Input Form -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
                <i class="fas fa-star text-yellow-500"></i> Input Catatan Perilaku
            </h3>

            <form method="POST">
                <?= Security::csrfField() ?>

                <!-- Student Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Siswa *</label>
                    <select name="student_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required id="studentSelect">
                        <option value="">-- Pilih Siswa --</option>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['nis'] ?>)</option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($allStudents as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['nis'] ?>) - Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Behavior Type Tabs -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Perilaku *</label>
                    <div class="flex gap-2 mb-3">
                        <button type="button" onclick="showTab('positive')" id="tab-positive" class="px-4 py-2 rounded-lg text-sm font-medium bg-green-600 text-white transition">
                            <i class="fas fa-thumbs-up"></i> Keteladanan (+)
                        </button>
                        <button type="button" onclick="showTab('negative')" id="tab-negative" class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-200 text-gray-600 transition">
                            <i class="fas fa-exclamation-triangle"></i> Pelanggaran (-)
                        </button>
                    </div>

                    <!-- Positive Categories -->
                    <div id="panel-positive">
                        <select name="category_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent category-select" id="positive-select">
                            <option value="">-- Pilih Kategori Keteladanan --</option>
                            <?php foreach ($positiveCategories as $c): ?>
                            <option value="<?= $c['id'] ?>" data-points="+<?= $c['points'] ?>"><?= htmlspecialchars($c['category_name']) ?> (+<?= $c['points'] ?> poin)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Negative Categories -->
                    <div id="panel-negative" class="hidden">
                        <select class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent category-select" id="negative-select">
                            <option value="">-- Pilih Kategori Pelanggaran --</option>
                            <?php foreach ($negativeCategories as $c): ?>
                            <option value="<?= $c['id'] ?>" data-points="<?= $c['points'] ?>"><?= htmlspecialchars($c['category_name']) ?> (<?= $c['points'] ?> poin) - <?= ucfirst($c['severity']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Date -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kejadian</label>
                    <input type="date" name="incident_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Deskripsi singkat kejadian (opsional)"></textarea>
                </div>

                <!-- Show to parent -->
                <div class="mb-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="show_to_parent" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700">Tampilkan ke orang tua</span>
                    </label>
                </div>

                <button type="submit" class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2 font-medium">
                    <i class="fas fa-save"></i> Simpan Catatan Perilaku
                </button>
            </form>
        </div>
    </div>

    <!-- Recent Records Sidebar -->
    <div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">Catatan Terbaru</h3>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <?php if (empty($recentRecords)): ?>
                <p class="text-sm text-gray-500 text-center py-4">Belum ada catatan.</p>
                <?php else: ?>
                <?php foreach ($recentRecords as $r): ?>
                <div class="p-3 rounded-lg <?= $r['type'] === 'keteladanan' ? 'bg-green-50 border border-green-100' : 'bg-red-50 border border-red-100' ?>">
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($r['full_name']) ?></p>
                    <p class="text-xs text-gray-600"><?= htmlspecialchars($r['category_name']) ?></p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs font-bold <?= $r['points'] >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $r['points'] > 0 ? '+' : '' ?><?= $r['points'] ?> poin</span>
                        <span class="text-xs text-gray-400"><?= formatDate($r['incident_date'], 'short') ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(type) {
    const posPanel = document.getElementById('panel-positive');
    const negPanel = document.getElementById('panel-negative');
    const posTab = document.getElementById('tab-positive');
    const negTab = document.getElementById('tab-negative');
    const posSelect = document.getElementById('positive-select');
    const negSelect = document.getElementById('negative-select');

    if (type === 'positive') {
        posPanel.classList.remove('hidden');
        negPanel.classList.add('hidden');
        posTab.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-green-600 text-white transition';
        negTab.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-gray-200 text-gray-600 transition';
        posSelect.name = 'category_id';
        negSelect.removeAttribute('name');
    } else {
        negPanel.classList.remove('hidden');
        posPanel.classList.add('hidden');
        negTab.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-red-600 text-white transition';
        posTab.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-gray-200 text-gray-600 transition';
        negSelect.name = 'category_id';
        posSelect.removeAttribute('name');
    }
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
