<?php
/**
 * Teacher-Class Assignments Management
 * SIKAPDIK - Penugasan Guru Mapel ke Kelas
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Penugasan Guru Mapel');

$db = Database::getInstance();

// Get active academic year
$activeAcademicYear = $db->fetch("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
$academicYearId = $activeAcademicYear['id'] ?? null;

// Handle form submissions
if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'add') {
        $teacherId = (int) post('teacher_id');
        $classId = (int) post('class_id');
        $subject = Security::clean(post('subject'));
        
        $errors = [];
        if (!$teacherId) $errors[] = 'Pilih guru.';
        if (!$classId) $errors[] = 'Pilih kelas.';
        if (empty($subject)) $errors[] = 'Mata pelajaran harus diisi.';
        
        // Check duplicate
        $existing = $db->fetch("SELECT id FROM teacher_class_assignments WHERE teacher_id = ? AND class_id = ? AND academic_year_id = ?", [$teacherId, $classId, $academicYearId]);
        if ($existing) $errors[] = 'Guru sudah ditugaskan ke kelas ini.';
        
        if (empty($errors)) {
            $db->insert('teacher_class_assignments', [
                'teacher_id' => $teacherId,
                'class_id' => $classId,
                'subject' => $subject,
                'academic_year_id' => $academicYearId
            ]);
            Auth::logActivity('add_assignment', 'teacher_assignments', "Penugasan guru ID:{$teacherId} ke kelas ID:{$classId} ({$subject})");
            setFlash('success', 'Penugasan berhasil ditambahkan.');
        } else {
            setFlash('error', implode('<br>', $errors));
        }
        redirect('modules/admin/teacher_assignments.php');
    } elseif ($formAction === 'delete') {
        $assignmentId = (int) post('assignment_id');
        $db->delete('teacher_class_assignments', 'id = ?', [$assignmentId]);
        Auth::logActivity('delete_assignment', 'teacher_assignments', "Hapus penugasan ID:{$assignmentId}");
        setFlash('success', 'Penugasan berhasil dihapus.');
        redirect('modules/admin/teacher_assignments.php');
    }
}

// Get data
$teachers = $db->fetchAll("SELECT t.id, t.full_name, t.subject as default_subject, u.role FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE t.is_active = 1 ORDER BY t.full_name");
$classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");

// Filter
$filterTeacher = get('teacher_id');
$filterClass = get('class_id');

$where = "1=1";
$params = [];
if (!empty($filterTeacher)) { $where .= " AND tca.teacher_id = ?"; $params[] = $filterTeacher; }
if (!empty($filterClass)) { $where .= " AND tca.class_id = ?"; $params[] = $filterClass; }

$assignments = $db->fetchAll("SELECT tca.*, t.full_name as teacher_name, t.subject as teacher_subject, c.class_name, c.grade_level, ay.year_name
    FROM teacher_class_assignments tca
    JOIN teachers t ON tca.teacher_id = t.id
    JOIN classes c ON tca.class_id = c.id
    LEFT JOIN academic_years ay ON tca.academic_year_id = ay.id
    WHERE {$where}
    ORDER BY c.grade_level, c.class_name, t.full_name", $params);

include __DIR__ . '/../../templates/header.php';
?>

<!-- Add Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-plus-circle text-blue-600"></i> Tambah Penugasan Guru ke Kelas
    </h3>
    <form method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <?= Security::csrfField() ?>
        <input type="hidden" name="form_action" value="add">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Guru *</label>
            <select name="teacher_id" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm combo-search" required data-placeholder="Cari guru...">
                <option value="">-- Pilih Guru --</option>
                <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?><?= $t['default_subject'] ? ' ('.$t['default_subject'].')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Kelas *</label>
            <select name="class_id" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm" required>
                <option value="">-- Pilih Kelas --</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>">Kelas <?= $c['grade_level'] ?> - <?= htmlspecialchars($c['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Mata Pelajaran *</label>
            <input type="text" name="subject" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm" placeholder="Contoh: Matematika" required>
        </div>
        <div>
            <button type="submit" class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">
                <i class="fas fa-plus"></i> Tambah
            </button>
        </div>
    </form>
</div>

<!-- Filter -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
        <div class="flex-1">
            <select name="teacher_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Semua Guru</option>
                <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $filterTeacher == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1">
            <select name="class_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Semua Kelas</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= $c['class_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm"><i class="fas fa-filter"></i> Filter</button>
        <a href="?" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm">Reset</a>
    </form>
</div>

<!-- Assignments Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Daftar Penugasan Guru ke Kelas</h3>
        <p class="text-sm text-gray-500"><?= count($assignments) ?> penugasan <?= $activeAcademicYear ? '(TA: ' . $activeAcademicYear['year_name'] . ')' : '' ?></p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Guru</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Mata Pelajaran</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tahun Ajaran</th>
                <th class="px-4 py-3 text-center font-medium text-gray-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($assignments as $a): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($a['teacher_name']) ?></td>
                    <td class="px-4 py-3">Kelas <?= $a['grade_level'] ?> - <?= htmlspecialchars($a['class_name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($a['subject'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= $a['year_name'] ?? '-' ?></td>
                    <td class="px-4 py-3 text-center">
                        <form method="POST" class="inline" onsubmit="return confirm('Hapus penugasan ini?')">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                            <button class="text-red-600 hover:text-red-800 text-xs"><i class="fas fa-trash"></i> Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($assignments)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">
                    Belum ada penugasan guru. Tambahkan penugasan di atas.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
