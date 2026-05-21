<?php
/**
 * Class Management
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Data Kelas');

$db = Database::getInstance();
$action = get('action', 'list');
$id = (int) get('id', 0);

if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'add' || $formAction === 'edit') {
        $className = Security::clean(post('class_name'));
        $gradeLevel = (int) post('grade_level');
        $homeroomTeacherId = (int) post('homeroom_teacher_id') ?: null;
        $academicYearId = (int) post('academic_year_id') ?: null;

        $errors = [];
        if (empty($className)) $errors[] = 'Nama kelas harus diisi.';
        if ($gradeLevel < 1 || $gradeLevel > 6) $errors[] = 'Tingkat kelas tidak valid.';

        if (empty($errors)) {
            $data = [
                'class_name' => $className,
                'grade_level' => $gradeLevel,
                'homeroom_teacher_id' => $homeroomTeacherId,
                'academic_year_id' => $academicYearId
            ];

            if ($formAction === 'add') {
                $db->insert('classes', $data);
                Auth::logActivity('create_class', 'classes', "Menambah kelas: {$className}");
                setFlash('success', 'Kelas berhasil ditambahkan.');
            } else {
                $db->update('classes', $data, 'id = ?', [$id]);
                Auth::logActivity('update_class', 'classes', "Memperbarui kelas: {$className}");
                setFlash('success', 'Kelas berhasil diperbarui.');
            }
            redirect('modules/admin/classes.php');
        } else {
            setFlash('error', implode('<br>', $errors));
        }
    } elseif ($formAction === 'delete') {
        $classId = (int) post('class_id');
        $db->update('classes', ['is_active' => 0], 'id = ?', [$classId]);
        Auth::logActivity('deactivate_class', 'classes', "Menonaktifkan kelas ID: {$classId}");
        setFlash('success', 'Kelas berhasil dinonaktifkan.');
        redirect('modules/admin/classes.php');
    }
}

include __DIR__ . '/../../templates/header.php';

if ($action === 'add' || ($action === 'edit' && $id > 0)):
    $class = $action === 'edit' ? $db->fetch("SELECT * FROM classes WHERE id = ?", [$id]) : null;
    $teachers = $db->fetchAll("SELECT id, full_name FROM teachers WHERE is_homeroom = 1 AND is_active = 1 ORDER BY full_name");
    $academicYears = $db->fetchAll("SELECT * FROM academic_years ORDER BY start_date DESC");
?>

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><?= $action === 'add' ? 'Tambah Kelas' : 'Edit Kelas' ?></h3>
            <a href="<?= BASE_URL ?>modules/admin/classes.php" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="<?= $action ?>">

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kelas *</label>
                    <input type="text" name="class_name" value="<?= htmlspecialchars($class['class_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required placeholder="Contoh: 1A, 2B">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tingkat *</label>
                    <select name="grade_level" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <?php for($i=1; $i<=6; $i++): ?>
                        <option value="<?= $i ?>" <?= ($class['grade_level'] ?? '') == $i ? 'selected' : '' ?>>Kelas <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Wali Kelas</label>
                <select name="homeroom_teacher_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">-- Pilih Wali Kelas --</option>
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($class['homeroom_teacher_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Ajaran</label>
                <select name="academic_year_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">-- Pilih --</option>
                    <?php foreach ($academicYears as $ay): ?>
                    <option value="<?= $ay['id'] ?>" <?= ($class['academic_year_id'] ?? '') == $ay['id'] ? 'selected' : '' ?>><?= $ay['year_name'] ?> Semester <?= $ay['semester'] ?> <?= $ay['is_active'] ? '(Aktif)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"><i class="fas fa-save"></i> Simpan</button>
                <a href="<?= BASE_URL ?>modules/admin/classes.php" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php else:
    $classes = $db->fetchAll("SELECT c.*, t.full_name as teacher_name, ay.year_name, ay.semester,
        (SELECT COUNT(*) FROM students WHERE class_id = c.id AND is_active = 1) as student_count
        FROM classes c
        LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id
        LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
        WHERE c.is_active = 1 ORDER BY c.grade_level, c.class_name");
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Daftar Kelas</h3>
            <p class="text-sm text-gray-500"><?= count($classes) ?> kelas aktif</p>
        </div>
        <a href="?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"><i class="fas fa-plus"></i> Tambah Kelas</a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Kelas</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Tingkat</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Wali Kelas</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Tahun Ajaran</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">Siswa</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($classes as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($c['class_name']) ?></td>
                    <td class="px-6 py-3">Kelas <?= $c['grade_level'] ?></td>
                    <td class="px-6 py-3"><?= htmlspecialchars($c['teacher_name'] ?? '-') ?></td>
                    <td class="px-6 py-3 text-gray-500"><?= $c['year_name'] ? $c['year_name'] . ' Smt ' . $c['semester'] : '-' ?></td>
                    <td class="px-6 py-3 text-center"><span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full text-xs font-medium"><?= $c['student_count'] ?></span></td>
                    <td class="px-6 py-3 text-center">
                        <a href="?action=edit&id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="inline" onsubmit="return confirmDelete()">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                            <button class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($classes)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Belum ada data kelas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
