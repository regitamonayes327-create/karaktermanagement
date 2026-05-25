<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['kepala_sekolah']);
define('PAGE_TITLE', 'Siswa Perlu Perhatian');
$db = Database::getInstance();
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$attentionStudents = $db->fetchAll("SELECT s.full_name, s.nis, c.class_name, c.grade_level,
    COALESCE(SUM(CASE WHEN br.type = 'pelanggaran' THEN ABS(br.points) ELSE 0 END), 0) as neg_points,
    COALESCE(SUM(CASE WHEN br.type = 'keteladanan' THEN br.points ELSE 0 END), 0) as pos_points,
    COUNT(CASE WHEN br.type = 'pelanggaran' THEN 1 END) as violation_count
    FROM students s LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN behavior_records br ON s.id = br.student_id AND br.validation_status = 'approved' AND br.incident_date BETWEEN ? AND ?
    WHERE s.is_active = 1 GROUP BY s.id HAVING neg_points > 0 ORDER BY neg_points DESC", [$monthStart, $monthEnd]);

include __DIR__ . '/../../templates/header.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Siswa dengan Catatan Pelanggaran Bulan Ini</h3>
    <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left font-medium text-gray-600">#</th><th class="px-4 py-3 text-left font-medium text-gray-600">Siswa</th><th class="px-4 py-3 text-left font-medium text-gray-600">Kelas</th><th class="px-4 py-3 text-center font-medium text-red-600">Pelanggaran</th><th class="px-4 py-3 text-center font-medium text-red-600">Poin -</th><th class="px-4 py-3 text-center font-medium text-green-600">Poin +</th></tr></thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($attentionStudents as $i => $s): ?>
            <tr class="<?= $s['neg_points'] >= 15 ? 'bg-red-50' : '' ?>"><td class="px-4 py-2 text-gray-400"><?= $i+1 ?></td><td class="px-4 py-2"><p class="font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p><p class="text-xs text-gray-400"><?= $s['nis'] ?></p></td><td class="px-4 py-2 text-xs">Kelas <?= $s['grade_level'] ?>-<?= $s['class_name'] ?></td><td class="px-4 py-2 text-center font-bold text-red-600"><?= $s['violation_count'] ?>x</td><td class="px-4 py-2 text-center font-bold text-red-600">-<?= $s['neg_points'] ?></td><td class="px-4 py-2 text-center text-green-600">+<?= $s['pos_points'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($attentionStudents)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Tidak ada siswa yang memerlukan perhatian khusus bulan ini.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
