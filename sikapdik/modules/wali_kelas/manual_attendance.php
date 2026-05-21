<?php
/**
 * Manual Attendance
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin']);

define('PAGE_TITLE', 'Presensi Manual');

$db = Database::getInstance();

$dateFilter = get('date', date('Y-m-d'));

// Get class for homeroom teacher
if (Auth::getRole() === 'wali_kelas' && isset($_SESSION['class_id'])) {
    $classId = $_SESSION['class_id'];
} else {
    $classId = (int) get('class_id', 0);
}

$classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");

// Handle form submission
if (isPost() && Security::validateCSRF()) {
    $date = Security::clean(post('attendance_date'));
    $attendanceData = $_POST['attendance'] ?? [];
    $notes = $_POST['notes'] ?? [];
    $targetClassId = (int) post('target_class_id');

    if (!empty($date) && !empty($attendanceData) && $targetClassId > 0) {
        $count = 0;
        foreach ($attendanceData as $studentId => $status) {
            $studentId = (int) $studentId;
            if (!in_array($status, ['hadir','terlambat','sakit','izin','alpa'])) continue;

            // Check existing
            $existing = $db->fetch("SELECT id FROM attendances WHERE student_id = ? AND date = ?", [$studentId, $date]);
            
            $record = [
                'student_id' => $studentId,
                'class_id' => $targetClassId,
                'date' => $date,
                'scan_time' => date('H:i:s'),
                'status' => $status,
                'method' => 'manual',
                'notes' => Security::clean($notes[$studentId] ?? ''),
                'recorded_by' => Auth::getUserId()
            ];

            if ($existing) {
                $db->update('attendances', $record, 'id = ?', [$existing['id']]);
            } else {
                $db->insert('attendances', $record);
            }
            $count++;
        }

        Auth::logActivity('manual_attendance', 'attendance', "Presensi manual {$count} siswa tanggal {$date}");
        setFlash('success', "Presensi berhasil disimpan untuk {$count} siswa.");
        redirect("modules/wali_kelas/manual_attendance.php?date={$date}&class_id={$targetClassId}");
    }
}

// Get students
$students = [];
if ($classId > 0) {
    $students = $db->fetchAll("SELECT s.*, 
        (SELECT status FROM attendances WHERE student_id = s.id AND date = ?) as today_status,
        (SELECT notes FROM attendances WHERE student_id = s.id AND date = ?) as today_notes
        FROM students s WHERE s.class_id = ? AND s.is_active = 1 ORDER BY s.full_name", 
        [$dateFilter, $dateFilter, $classId]);
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
            <input type="date" name="date" value="<?= $dateFilter ?>" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
        </div>
        <?php if (Auth::getRole() === 'admin'): ?>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Kelas</label>
            <select name="class_id" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Pilih Kelas</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= $c['class_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm"><i class="fas fa-filter"></i> Tampilkan</button>
    </form>
</div>

<?php if ($classId > 0 && !empty($students)): ?>
<form method="POST">
    <?= Security::csrfField() ?>
    <input type="hidden" name="attendance_date" value="<?= $dateFilter ?>">
    <input type="hidden" name="target_class_id" value="<?= $classId ?>">

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Presensi <?= formatDate($dateFilter, 'full') ?></h3>
            <div class="flex gap-2">
                <button type="button" onclick="setAll('hadir')" class="px-3 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200">Semua Hadir</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 w-8">#</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Nama Siswa</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Catatan</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($students as $i => $s): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-gray-500"><?= $i + 1 ?></td>
                        <td class="px-4 py-2">
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p>
                            <p class="text-xs text-gray-400"><?= $s['nis'] ?> | <?= $s['gender'] === 'L' ? 'L' : 'P' ?></p>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex justify-center gap-1 flex-wrap">
                                <?php 
                                $statuses = ['hadir'=>'H','terlambat'=>'T','sakit'=>'S','izin'=>'I','alpa'=>'A'];
                                $colors = ['hadir'=>'green','terlambat'=>'yellow','sakit'=>'blue','izin'=>'purple','alpa'=>'red'];
                                foreach ($statuses as $val => $label): 
                                $checked = ($s['today_status'] ?? '') === $val ? 'checked' : ($val === 'hadir' && empty($s['today_status']) ? 'checked' : '');
                                ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="attendance[<?= $s['id'] ?>]" value="<?= $val ?>" <?= $checked ?> class="hidden peer attendance-radio" data-student="<?= $s['id'] ?>">
                                    <span class="inline-block w-8 h-8 rounded-lg text-center leading-8 text-xs font-bold border-2 peer-checked:bg-<?= $colors[$val] ?>-500 peer-checked:text-white peer-checked:border-<?= $colors[$val] ?>-500 border-gray-200 text-gray-400 hover:border-<?= $colors[$val] ?>-300"><?= $label ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <input type="text" name="notes[<?= $s['id'] ?>]" value="<?= htmlspecialchars($s['today_notes'] ?? '') ?>" class="w-full px-2 py-1 border border-gray-200 rounded text-xs" placeholder="Catatan...">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100">
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fas fa-save"></i> Simpan Presensi
            </button>
        </div>
    </div>
</form>

<script>
function setAll(status) {
    document.querySelectorAll(`input[value="${status}"]`).forEach(radio => {
        radio.checked = true;
    });
}
</script>

<?php elseif ($classId > 0): ?>
<div class="text-center py-8 text-gray-500">Tidak ada siswa di kelas ini.</div>
<?php else: ?>
<div class="text-center py-8 text-gray-500">Pilih kelas dan tanggal untuk memulai presensi manual.</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
