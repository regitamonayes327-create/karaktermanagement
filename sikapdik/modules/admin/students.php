<?php
/**
 * Student Management
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Data Siswa');

$db = Database::getInstance();
$action = get('action', 'list');
$id = (int) get('id', 0);

if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'add' || $formAction === 'edit') {
        $nis = Security::clean(post('nis'));
        $nisn = Security::clean(post('nisn'));
        $fullName = Security::clean(post('full_name'));
        $gender = Security::clean(post('gender'));
        $birthPlace = Security::clean(post('birth_place'));
        $birthDate = Security::clean(post('birth_date'));
        $address = Security::clean(post('address'));
        $classId = (int) post('class_id') ?: null;

        $errors = [];
        if (empty($nis)) $errors[] = 'NIS harus diisi.';
        if (empty($fullName)) $errors[] = 'Nama lengkap harus diisi.';
        if (!in_array($gender, ['L','P'])) $errors[] = 'Jenis kelamin harus dipilih.';
        
        $existing = $db->fetch("SELECT id FROM students WHERE nis = ? AND id != ?", [$nis, $id]);
        if ($existing) $errors[] = 'NIS sudah digunakan.';

        if (empty($errors)) {
            $data = [
                'nis' => $nis,
                'nisn' => $nisn ?: null,
                'full_name' => $fullName,
                'gender' => $gender,
                'birth_place' => $birthPlace ?: null,
                'birth_date' => $birthDate ?: null,
                'address' => $address ?: null,
                'class_id' => $classId
            ];

            if ($formAction === 'add') {
                $data['qr_token'] = Security::generateToken(32);
                $data['qr_generated_at'] = date('Y-m-d H:i:s');
                $db->insert('students', $data);
                Auth::logActivity('create_student', 'students', "Menambah siswa: {$fullName}");
                setFlash('success', 'Siswa berhasil ditambahkan.');
            } else {
                $db->update('students', $data, 'id = ?', [$id]);
                Auth::logActivity('update_student', 'students', "Memperbarui siswa: {$fullName}");
                setFlash('success', 'Siswa berhasil diperbarui.');
            }
            redirect('modules/admin/students.php');
        } else {
            setFlash('error', implode('<br>', $errors));
        }
    } elseif ($formAction === 'delete') {
        $studentId = (int) post('student_id');
        $db->update('students', ['is_active' => 0], 'id = ?', [$studentId]);
        Auth::logActivity('deactivate_student', 'students', "Menonaktifkan siswa ID: {$studentId}");
        setFlash('success', 'Siswa berhasil dinonaktifkan.');
        redirect('modules/admin/students.php');
    }
}

include __DIR__ . '/../../templates/header.php';

if ($action === 'add' || ($action === 'edit' && $id > 0)):
    $student = $action === 'edit' ? $db->fetch("SELECT * FROM students WHERE id = ?", [$id]) : null;
    $classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800"><?= $action === 'add' ? 'Tambah Siswa' : 'Edit Siswa' ?></h3>
            <a href="<?= BASE_URL ?>modules/admin/students.php" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="<?= $action ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIS *</label>
                    <input type="text" name="nis" value="<?= htmlspecialchars($student['nis'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NISN</label>
                    <input type="text" name="nisn" value="<?= htmlspecialchars($student['nisn'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($student['full_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin *</label>
                    <select name="gender" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="">-- Pilih --</option>
                        <option value="L" <?= ($student['gender'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= ($student['gender'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kelas</label>
                    <select name="class_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($student['class_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= htmlspecialchars($c['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tempat Lahir</label>
                    <input type="text" name="birth_place" value="<?= htmlspecialchars($student['birth_place'] ?? '') ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Lahir</label>
                    <input type="date" name="birth_date" value="<?= $student['birth_date'] ?? '' ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                <textarea name="address" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"><i class="fas fa-save"></i> Simpan</button>
                <a href="<?= BASE_URL ?>modules/admin/students.php" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php else:
    $search = get('search');
    $classFilter = get('class_filter');
    $page = max(1, (int) get('page', 1));
    
    $where = "s.is_active = 1";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (s.full_name LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)";
        $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
    }
    if (!empty($classFilter)) {
        $where .= " AND s.class_id = ?";
        $params[] = $classFilter;
    }

    $total = $db->fetchColumn("SELECT COUNT(*) FROM students s WHERE {$where}", $params);
    $pagination = paginate($total, $page, 15);
    $students = $db->fetchAll("SELECT s.*, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE {$where} ORDER BY c.grade_level, c.class_name, s.full_name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params);
    $classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Daftar Siswa</h3>
            <p class="text-sm text-gray-500"><?= formatNumber($total) ?> siswa aktif</p>
        </div>
        <a href="?action=add" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"><i class="fas fa-plus"></i> Tambah Siswa</a>
    </div>

    <div class="p-4 border-b border-gray-50 bg-gray-50/50">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama/NIS/NISN..." class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm">
            <select name="class_filter" class="px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Semua Kelas</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= htmlspecialchars($c['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700"><i class="fas fa-search"></i> Cari</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Siswa</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">NIS/NISN</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">Kelas</th>
                <th class="px-6 py-3 text-left font-medium text-gray-600">JK</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">QR</th>
                <th class="px-6 py-3 text-center font-medium text-gray-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($students as $s): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></td>
                    <td class="px-6 py-3 text-gray-600">
                        <span class="block"><?= htmlspecialchars($s['nis']) ?></span>
                        <span class="text-xs text-gray-400"><?= htmlspecialchars($s['nisn'] ?? '-') ?></span>
                    </td>
                    <td class="px-6 py-3"><?= $s['class_name'] ? 'Kelas ' . $s['grade_level'] . ' - ' . htmlspecialchars($s['class_name']) : '-' ?></td>
                    <td class="px-6 py-3"><?= $s['gender'] === 'L' ? 'L' : 'P' ?></td>
                    <td class="px-6 py-3 text-center">
                        <?php if ($s['qr_token']): ?>
                        <span class="text-green-600"><i class="fas fa-check-circle"></i></span>
                        <?php else: ?>
                        <span class="text-gray-400"><i class="fas fa-times-circle"></i></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <a href="?action=edit&id=<?= $s['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="inline" onsubmit="return confirmDelete()">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                            <button class="text-red-600 hover:text-red-800" title="Nonaktifkan"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($students)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Belum ada data siswa.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-4"><?= renderPagination($pagination, '?search=' . urlencode($search) . '&class_filter=' . urlencode($classFilter)) ?></div>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
