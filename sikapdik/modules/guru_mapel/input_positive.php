<?php
/**
 * Input Positive Behavior (Guru Mapel)
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['guru_mapel']);

define('PAGE_TITLE', 'Input Keteladanan');

$db = Database::getInstance();
$teacherId = $_SESSION['teacher_id'] ?? 0;

// Get validation mode
$validationMode = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'validation_mode'") ?: '0';

if (isPost() && Security::validateCSRF()) {
    $studentId = (int) post('student_id');
    $categoryId = (int) post('category_id');
    $description = Security::clean(post('description'));
    $incidentDate = Security::clean(post('incident_date')) ?: date('Y-m-d');
    $showToParent = (int) post('show_to_parent', 0);

    $errors = [];
    if (!$studentId) $errors[] = 'Pilih siswa.';
    if (!$categoryId) $errors[] = 'Pilih kategori.';

    if (empty($errors)) {
        $category = $db->fetch("SELECT * FROM behavior_categories WHERE id = ? AND type = 'keteladanan'", [$categoryId]);
        
        if ($category) {
            $db->insert('behavior_records', [
                'student_id' => $studentId,
                'category_id' => $categoryId,
                'type' => 'keteladanan',
                'points' => $category['points'],
                'description' => $description ?: null,
                'incident_date' => $incidentDate,
                'recorded_by' => Auth::getUserId(),
                'recorder_role' => 'guru_mapel',
                'validation_status' => $validationMode === '1' ? 'pending' : 'approved',
                'show_to_parent' => $showToParent
            ]);

            $student = $db->fetch("SELECT full_name FROM students WHERE id = ?", [$studentId]);
            Auth::logActivity('input_positive', 'behavior', "Guru mapel input keteladanan: {$student['full_name']} - {$category['category_name']}");
            
            $msg = "Keteladanan berhasil dicatat. +{$category['points']} poin.";
            if ($validationMode === '1') $msg .= ' (Menunggu validasi wali kelas)';
            setFlash('success', $msg);
            redirect('modules/guru_mapel/input_positive.php');
        }
    } else {
        setFlash('error', implode('<br>', $errors));
    }
}

// Get classes taught by this teacher
$myClasses = $db->fetchAll("SELECT DISTINCT c.id, c.class_name, c.grade_level 
    FROM teacher_class_assignments tca 
    JOIN classes c ON tca.class_id = c.id 
    WHERE tca.teacher_id = ? AND c.is_active = 1 
    ORDER BY c.grade_level, c.class_name", [$teacherId]);

// Get all active students (grouped by class)
$students = $db->fetchAll("SELECT s.id, s.full_name, s.nis, c.class_name, c.grade_level, c.id as class_id
    FROM students s LEFT JOIN classes c ON s.class_id = c.id 
    WHERE s.is_active = 1 ORDER BY c.grade_level, c.class_name, s.full_name");

$categories = $db->fetchAll("SELECT * FROM behavior_categories WHERE type = 'keteladanan' AND is_active = 1 ORDER BY category_name");

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2">
            <i class="fas fa-thumbs-up text-green-500"></i> Input Keteladanan Siswa
        </h3>
        <p class="text-sm text-gray-500 mb-6">Catat perilaku positif siswa yang Anda amati.</p>

        <?php if ($validationMode === '1'): ?>
        <div class="mb-4 p-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 text-sm">
            <i class="fas fa-info-circle"></i> Mode validasi aktif. Catatan akan diperiksa wali kelas sebelum masuk rekap.
        </div>
        <?php endif; ?>

        <form method="POST">
            <?= Security::csrfField() ?>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Siswa *</label>
                <select name="student_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    <option value="">-- Pilih Siswa --</option>
                    <?php 
                    $currentClass = '';
                    foreach ($students as $s): 
                        $classLabel = 'Kelas ' . $s['grade_level'] . '-' . $s['class_name'];
                        if ($classLabel !== $currentClass):
                            if ($currentClass !== '') echo '</optgroup>';
                            $currentClass = $classLabel;
                            echo '<optgroup label="' . $classLabel . '">';
                        endif;
                    ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['nis'] ?>)</option>
                    <?php endforeach; ?>
                    <?php if ($currentClass !== '') echo '</optgroup>'; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori Keteladanan *</label>
                <select name="category_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?> (+<?= $c['points'] ?> poin)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kejadian</label>
                <input type="date" name="incident_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Deskripsi singkat (opsional)"></textarea>
            </div>

            <div class="mb-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="show_to_parent" value="1" class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">Tampilkan ke orang tua</span>
                </label>
            </div>

            <button type="submit" class="w-full px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium flex items-center justify-center gap-2">
                <i class="fas fa-save"></i> Simpan Keteladanan
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
