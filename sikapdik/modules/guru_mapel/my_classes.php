<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['guru_mapel']);
define('PAGE_TITLE', 'Kelas yang Diajar');
$db = Database::getInstance();
$teacherId = $_SESSION['teacher_id'] ?? 0;

$myClasses = $db->fetchAll("SELECT c.id, c.class_name, c.grade_level, tca.subject, t.full_name as homeroom_name,
    (SELECT COUNT(*) FROM students WHERE class_id = c.id AND is_active = 1) as student_count
    FROM teacher_class_assignments tca 
    JOIN classes c ON tca.class_id = c.id 
    LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id
    WHERE tca.teacher_id = ? AND c.is_active = 1 
    ORDER BY c.grade_level, c.class_name", [$teacherId]);

include __DIR__ . '/../../templates/header.php';
?>

<?php if (empty($myClasses)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
    <i class="fas fa-school text-gray-300 text-5xl mb-4"></i>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">Belum Ada Penugasan Kelas</h3>
    <p class="text-sm text-gray-500 mb-4">Anda belum ditugaskan ke kelas manapun oleh admin.<br>Hubungi admin/operator sekolah untuk menambahkan penugasan mengajar Anda.</p>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 max-w-md mx-auto text-left">
        <p class="text-sm text-blue-800"><i class="fas fa-info-circle"></i> <strong>Info untuk Admin:</strong></p>
        <p class="text-xs text-blue-700 mt-1">Buka menu <strong>Penugasan Guru</strong> di panel Admin untuk menambahkan penugasan guru ke kelas.</p>
    </div>
</div>
<?php else: ?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($myClasses as $c): 
        // Get recent behavior records by this teacher for this class
        $recentCount = $db->count('behavior_records', "recorded_by = ? AND student_id IN (SELECT id FROM students WHERE class_id = ?)", [Auth::getUserId(), $c['id']]);
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                <span class="text-lg font-bold text-blue-600"><?= $c['grade_level'] ?></span>
            </div>
            <div>
                <h4 class="font-semibold text-gray-800">Kelas <?= $c['grade_level'] ?> - <?= htmlspecialchars($c['class_name']) ?></h4>
                <p class="text-xs text-blue-600 font-medium"><?= htmlspecialchars($c['subject'] ?? '-') ?></p>
            </div>
        </div>
        
        <div class="space-y-2 text-sm text-gray-600 mb-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-user-tie text-gray-400 w-4 text-center"></i>
                <span>Wali Kelas: <?= htmlspecialchars($c['homeroom_name'] ?? 'Belum ditentukan') ?></span>
            </div>
            <div class="flex items-center gap-2">
                <i class="fas fa-users text-gray-400 w-4 text-center"></i>
                <span><?= $c['student_count'] ?> siswa aktif</span>
            </div>
            <div class="flex items-center gap-2">
                <i class="fas fa-clipboard-list text-gray-400 w-4 text-center"></i>
                <span><?= $recentCount ?> catatan perilaku Anda</span>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>modules/guru_mapel/input_positive.php" class="flex-1 px-3 py-2 bg-green-50 text-green-700 rounded-lg text-xs font-medium text-center hover:bg-green-100 transition">
                <i class="fas fa-thumbs-up"></i> Keteladanan
            </a>
            <a href="<?= BASE_URL ?>modules/guru_mapel/input_negative.php" class="flex-1 px-3 py-2 bg-red-50 text-red-700 rounded-lg text-xs font-medium text-center hover:bg-red-100 transition">
                <i class="fas fa-exclamation-circle"></i> Pelanggaran
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
