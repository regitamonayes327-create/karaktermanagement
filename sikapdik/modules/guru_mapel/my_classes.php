<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['guru_mapel']);
define('PAGE_TITLE', 'Kelas yang Diajar');
$db = Database::getInstance();
$teacherId = $_SESSION['teacher_id'] ?? 0;
$myClasses = $db->fetchAll("SELECT c.*, tca.subject, t.full_name as homeroom_name,
    (SELECT COUNT(*) FROM students WHERE class_id = c.id AND is_active = 1) as student_count
    FROM teacher_class_assignments tca JOIN classes c ON tca.class_id = c.id 
    LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id
    WHERE tca.teacher_id = ? AND c.is_active = 1 ORDER BY c.grade_level, c.class_name", [$teacherId]);
include __DIR__ . '/../../templates/header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($myClasses as $c): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center"><i class="fas fa-school text-blue-600 text-xl"></i></div>
            <div>
                <h4 class="font-semibold text-gray-800">Kelas <?= $c['grade_level'] ?> - <?= $c['class_name'] ?></h4>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($c['subject'] ?? 'Mapel') ?></p>
            </div>
        </div>
        <div class="text-sm text-gray-600 space-y-1">
            <p><i class="fas fa-user-tie text-gray-400 w-5"></i> Wali Kelas: <?= htmlspecialchars($c['homeroom_name'] ?? '-') ?></p>
            <p><i class="fas fa-users text-gray-400 w-5"></i> <?= $c['student_count'] ?> siswa</p>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($myClasses)): ?>
    <div class="col-span-full text-center py-12 text-gray-500">Belum ada penugasan kelas. Hubungi admin/operator.</div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
