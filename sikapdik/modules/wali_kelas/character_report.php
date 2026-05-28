<?php
/**
 * Character Report (Rapor Karakter)
 * Generates printable character development report per student per semester
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin']);

define('PAGE_TITLE', 'Rapor Karakter');

$db = Database::getInstance();

// Get active academic year
$activeYear = $db->fetch("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
$semesterStart = $activeYear['start_date'] ?? date('Y-01-01');
$semesterEnd = $activeYear['end_date'] ?? date('Y-12-31');
$semesterName = $activeYear ? ($activeYear['year_name'] . ' - Semester ' . $activeYear['semester']) : 'Tahun Ajaran Aktif';

// Get class for homeroom teacher
if (Auth::getRole() === 'wali_kelas' && isset($_SESSION['class_id'])) {
    $classId = $_SESSION['class_id'];
} else {
    $classId = (int) get('class_id', 0);
}

$classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");
$studentId = (int) get('student_id', 0);
$showReport = get('show', '') === '1';

// Get students for selected class
$students = [];
if ($classId > 0) {
    $students = $db->fetchAll("SELECT id, full_name, nis, gender FROM students WHERE class_id = ? AND is_active = 1 ORDER BY full_name", [$classId]);
}

// Character Grade function
function getCharacterGrade($netPoints) {
    if ($netPoints >= 50) return ['A', 'Sangat Baik', 'text-green-700 bg-green-100'];
    if ($netPoints >= 20) return ['B', 'Baik', 'text-blue-700 bg-blue-100'];
    if ($netPoints >= 0) return ['C', 'Cukup', 'text-yellow-700 bg-yellow-100'];
    return ['D', 'Perlu Pembinaan Intensif', 'text-red-700 bg-red-100'];
}

// Generate report data if student is selected
$reportData = null;
if ($showReport && $studentId > 0) {
    $student = $db->fetch("SELECT s.*, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [$studentId]);
    
    if ($student) {
        // Attendance summary
        $attendanceSummary = $db->fetch(
            "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
                SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) as alpa
             FROM attendances WHERE student_id = ? AND date BETWEEN ? AND ?",
            [$studentId, $semesterStart, $semesterEnd]
        );

        // Points summary
        $pointsSummary = $db->fetch(
            "SELECT 
                COALESCE(SUM(CASE WHEN type = 'keteladanan' THEN points ELSE 0 END), 0) as total_positive,
                COALESCE(SUM(CASE WHEN type = 'pelanggaran' THEN ABS(points) ELSE 0 END), 0) as total_negative,
                COALESCE(SUM(points), 0) as net_points
             FROM behavior_records 
             WHERE student_id = ? AND incident_date BETWEEN ? AND ? AND validation_status = 'approved'",
            [$studentId, $semesterStart, $semesterEnd]
        );

        // Top 5 keteladanan categories
        $topPositive = $db->fetchAll(
            "SELECT bc.category_name, COUNT(*) as count, SUM(br.points) as total_points
             FROM behavior_records br
             JOIN behavior_categories bc ON br.category_id = bc.id
             WHERE br.student_id = ? AND br.type = 'keteladanan' AND br.incident_date BETWEEN ? AND ? AND br.validation_status = 'approved'
             GROUP BY bc.id, bc.category_name
             ORDER BY total_points DESC LIMIT 5",
            [$studentId, $semesterStart, $semesterEnd]
        );

        // Top 5 pelanggaran categories
        $topNegative = $db->fetchAll(
            "SELECT bc.category_name, COUNT(*) as count, SUM(ABS(br.points)) as total_points
             FROM behavior_records br
             JOIN behavior_categories bc ON br.category_id = bc.id
             WHERE br.student_id = ? AND br.type = 'pelanggaran' AND br.incident_date BETWEEN ? AND ? AND br.validation_status = 'approved'
             GROUP BY bc.id, bc.category_name
             ORDER BY total_points DESC LIMIT 5",
            [$studentId, $semesterStart, $semesterEnd]
        );

        // Follow-up history
        $followUps = $db->fetchAll(
            "SELECT br.description, br.incident_date, br.follow_up_status, br.follow_up_notes
             FROM behavior_records br
             WHERE br.student_id = ? AND br.incident_date BETWEEN ? AND ? AND br.follow_up_status IS NOT NULL AND br.follow_up_status != ''
             ORDER BY br.incident_date DESC LIMIT 10",
            [$studentId, $semesterStart, $semesterEnd]
        );

        // Achievements
        $achievements = $db->fetchAll(
            "SELECT title, category, achievement_date, description
             FROM achievements
             WHERE student_id = ? AND achievement_date BETWEEN ? AND ?
             ORDER BY achievement_date DESC LIMIT 10",
            [$studentId, $semesterStart, $semesterEnd]
        );

        // Development trend - compare with previous period
        $periodLength = (strtotime($semesterEnd) - strtotime($semesterStart));
        $prevStart = date('Y-m-d', strtotime($semesterStart) - $periodLength);
        $prevEnd = date('Y-m-d', strtotime($semesterStart) - 1);
        
        $prevPoints = $db->fetch(
            "SELECT COALESCE(SUM(points), 0) as net_points FROM behavior_records 
             WHERE student_id = ? AND incident_date BETWEEN ? AND ? AND validation_status = 'approved'",
            [$studentId, $prevStart, $prevEnd]
        );

        $currentNet = (int)($pointsSummary['net_points'] ?? 0);
        $previousNet = (int)($prevPoints['net_points'] ?? 0);
        
        if ($currentNet > $previousNet + 5) {
            $trend = ['Membaik', 'text-green-700', 'fas fa-arrow-up'];
        } elseif ($currentNet < $previousNet - 5) {
            $trend = ['Menurun', 'text-red-700', 'fas fa-arrow-down'];
        } else {
            $trend = ['Stabil', 'text-blue-700', 'fas fa-minus'];
        }

        $grade = getCharacterGrade($currentNet);

        $reportData = [
            'student' => $student,
            'attendance' => $attendanceSummary,
            'points' => $pointsSummary,
            'topPositive' => $topPositive,
            'topNegative' => $topNegative,
            'followUps' => $followUps,
            'achievements' => $achievements,
            'trend' => $trend,
            'grade' => $grade,
            'previousNet' => $previousNet,
        ];
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<!-- Filter Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6 no-print">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
        <input type="hidden" name="show" value="1">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Kelas</label>
            <select name="class_id" id="classSelect" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm" onchange="this.form.submit()">
                <option value="">Pilih Kelas</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= $c['class_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Siswa</label>
            <select name="student_id" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Pilih Siswa</option>
                <?php foreach ($students as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $studentId == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['full_name']) ?> (<?= $s['nis'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
            <i class="fas fa-file-signature"></i> Tampilkan Rapor
        </button>
    </form>
</div>

<?php if ($reportData): ?>
<!-- Print Button -->
<div class="mb-4 no-print">
    <button onclick="window.print()" class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2">
        <i class="fas fa-print"></i> Cetak Rapor Karakter
    </button>
</div>

<!-- Report Card -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 print-area" id="reportCard">
    <!-- School Header -->
    <div class="text-center border-b-2 border-gray-800 pb-4 mb-6">
        <h2 class="text-xl font-bold text-gray-800 uppercase"><?= SCHOOL_NAME ?></h2>
        <p class="text-sm text-gray-600">Sistem Informasi Pemantauan Perilaku Siswa (SIKAPDIK)</p>
        <h3 class="text-lg font-bold text-blue-700 mt-2">RAPOR KARAKTER SISWA</h3>
        <p class="text-sm text-gray-600"><?= htmlspecialchars($semesterName) ?></p>
    </div>

    <!-- Student Info -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div>
            <table class="text-sm">
                <tr><td class="font-medium text-gray-600 pr-4">Nama Siswa</td><td>: <?= htmlspecialchars($reportData['student']['full_name']) ?></td></tr>
                <tr><td class="font-medium text-gray-600 pr-4">NIS</td><td>: <?= $reportData['student']['nis'] ?></td></tr>
                <tr><td class="font-medium text-gray-600 pr-4">Jenis Kelamin</td><td>: <?= $reportData['student']['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td></tr>
            </table>
        </div>
        <div>
            <table class="text-sm">
                <tr><td class="font-medium text-gray-600 pr-4">Kelas</td><td>: Kelas <?= $reportData['student']['grade_level'] ?> - <?= $reportData['student']['class_name'] ?></td></tr>
                <tr><td class="font-medium text-gray-600 pr-4">Periode</td><td>: <?= date('d/m/Y', strtotime($semesterStart)) ?> s.d. <?= date('d/m/Y', strtotime($semesterEnd)) ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Character Grade -->
    <div class="text-center mb-6 p-4 rounded-lg <?= $reportData['grade'][2] ?>">
        <p class="text-sm font-medium">Nilai Karakter</p>
        <p class="text-4xl font-bold"><?= $reportData['grade'][0] ?></p>
        <p class="text-sm font-medium"><?= $reportData['grade'][1] ?></p>
        <p class="text-xs mt-1">Poin Bersih: <?= $reportData['points']['net_points'] ?> poin</p>
    </div>

    <!-- Attendance Summary -->
    <div class="mb-6">
        <h4 class="font-bold text-gray-800 mb-2 border-b border-gray-200 pb-1"><i class="fas fa-calendar-check text-blue-600"></i> Ringkasan Kehadiran</h4>
        <div class="grid grid-cols-6 gap-2 text-center text-sm">
            <div class="p-2 rounded bg-gray-50">
                <p class="font-bold text-gray-800"><?= $reportData['attendance']['total_days'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Total Hari</p>
            </div>
            <div class="p-2 rounded bg-green-50">
                <p class="font-bold text-green-700"><?= $reportData['attendance']['hadir'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Hadir</p>
            </div>
            <div class="p-2 rounded bg-yellow-50">
                <p class="font-bold text-yellow-700"><?= $reportData['attendance']['terlambat'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Terlambat</p>
            </div>
            <div class="p-2 rounded bg-blue-50">
                <p class="font-bold text-blue-700"><?= $reportData['attendance']['sakit'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Sakit</p>
            </div>
            <div class="p-2 rounded bg-purple-50">
                <p class="font-bold text-purple-700"><?= $reportData['attendance']['izin'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Izin</p>
            </div>
            <div class="p-2 rounded bg-red-50">
                <p class="font-bold text-red-700"><?= $reportData['attendance']['alpa'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Alpa</p>
            </div>
        </div>
    </div>

    <!-- Points Summary -->
    <div class="mb-6">
        <h4 class="font-bold text-gray-800 mb-2 border-b border-gray-200 pb-1"><i class="fas fa-star text-yellow-500"></i> Ringkasan Poin Karakter</h4>
        <div class="grid grid-cols-3 gap-4 text-center text-sm">
            <div class="p-3 rounded-lg bg-green-50 border border-green-200">
                <p class="text-2xl font-bold text-green-700">+<?= $reportData['points']['total_positive'] ?></p>
                <p class="text-xs text-green-600">Poin Keteladanan</p>
            </div>
            <div class="p-3 rounded-lg bg-red-50 border border-red-200">
                <p class="text-2xl font-bold text-red-700">-<?= $reportData['points']['total_negative'] ?></p>
                <p class="text-xs text-red-600">Poin Pelanggaran</p>
            </div>
            <div class="p-3 rounded-lg bg-blue-50 border border-blue-200">
                <p class="text-2xl font-bold text-blue-700"><?= $reportData['points']['net_points'] ?></p>
                <p class="text-xs text-blue-600">Poin Bersih</p>
            </div>
        </div>
    </div>

    <!-- Top Positive Behaviors -->
    <?php if (!empty($reportData['topPositive'])): ?>
    <div class="mb-6">
        <h4 class="font-bold text-gray-800 mb-2 border-b border-gray-200 pb-1"><i class="fas fa-thumbs-up text-green-600"></i> Top 5 Keteladanan</h4>
        <table class="w-full text-sm">
            <thead class="bg-green-50"><tr>
                <th class="px-3 py-2 text-left text-green-800">Kategori</th>
                <th class="px-3 py-2 text-center text-green-800">Frekuensi</th>
                <th class="px-3 py-2 text-center text-green-800">Total Poin</th>
            </tr></thead>
            <tbody>
                <?php foreach ($reportData['topPositive'] as $item): ?>
                <tr class="border-b border-gray-100">
                    <td class="px-3 py-2"><?= htmlspecialchars($item['category_name']) ?></td>
                    <td class="px-3 py-2 text-center"><?= $item['count'] ?>x</td>
                    <td class="px-3 py-2 text-center text-green-700 font-medium">+<?= $item['total_points'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Top Negative Behaviors -->
    <?php if (!empty($reportData['topNegative'])): ?>
    <div class="mb-6">
        <h4 class="font-bold text-gray-800 mb-2 border-b border-gray-200 pb-1"><i class="fas fa-exclamation-triangle text-red-600"></i> Top 5 Pelanggaran</h4>
        <table class="w-full text-sm">
            <thead class="bg-red-50"><tr>
                <th class="px-3 py-2 text-left text-red-800">Kategori</th>
                <th class="px-3 py-2 text-center text-red-800">Frekuensi</th>
                <th class="px-3 py-2 text-center text-red-800">Total Poin</th>
            </tr></thead>
            <tbody>
                <?php foreach ($reportData['topNegative'] as $item): ?>
                <tr class="border-b border-gray-100">
                    <td class="px-3 py-2"><?= htmlspecialchars($item['category_name']) ?></td>
                    <td class="px-3 py-2 text-center"><?= $item['count'] ?>x</td>
                    <td class="px-3 py-2 text-center text-red-700 font-medium">-<?= $item['total_points'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Follow-up History -->
    <?php if (!empty($reportData['followUps'])): ?>
    <div class="mb-6">
        <h4 class="font-bold text-gray-800 mb-2 border-b border-gray-200 pb-1"><i class="fas fa-hands-helping text-purple-600"></i> Riwayat Tindak Lanjut</h4>
        <table class="w-full text-sm">
            <thead class="bg-purple-50"><tr>
                <th class="px-3 py-2 text-left text-purple-800">Tanggal</th>
                <th class="px-3 py-2 text-left text-purple-800">Keterangan</th>
                <th class="px-3 py-2 text-center text-purple-800">Status</th>
            </tr></thead>
            <tbody>
                <?php foreach ($reportData['followUps'] as $fu): ?>
                <tr class="border-b border-gray-100">
                    <td class="px-3 py-2"><?= date('d/m/Y', strtotime($fu['incident_date'])) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($fu['description']) ?></td>
                    <td class="px-3 py-2 text-center">
                        <span class="px-2 py-0.5 rounded text-xs <?= $fu['follow_up_status'] === 'resolved' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                            <?= ucfirst($fu['follow_up_status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Achievements -->
    <?php if (!empty($reportData['achievements'])): ?>
    <div class="mb-6">
        <h4 class="font-bold text-gray-800 mb-2 border-b border-gray-200 pb-1"><i class="fas fa-trophy text-yellow-500"></i> Prestasi</h4>
        <table class="w-full text-sm">
            <thead class="bg-yellow-50"><tr>
                <th class="px-3 py-2 text-left text-yellow-800">Tanggal</th>
                <th class="px-3 py-2 text-left text-yellow-800">Judul</th>
                <th class="px-3 py-2 text-left text-yellow-800">Kategori</th>
            </tr></thead>
            <tbody>
                <?php foreach ($reportData['achievements'] as $ach): ?>
                <tr class="border-b border-gray-100">
                    <td class="px-3 py-2"><?= date('d/m/Y', strtotime($ach['achievement_date'])) ?></td>
                    <td class="px-3 py-2 font-medium"><?= htmlspecialchars($ach['title']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($ach['category'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Development Trend -->
    <div class="mb-6">
        <h4 class="font-bold text-gray-800 mb-2 border-b border-gray-200 pb-1"><i class="fas fa-chart-line text-indigo-600"></i> Tren Perkembangan</h4>
        <div class="flex items-center gap-4 p-3 rounded-lg bg-gray-50">
            <div class="text-center">
                <p class="text-xs text-gray-500">Periode Sebelumnya</p>
                <p class="text-lg font-bold text-gray-700"><?= $reportData['previousNet'] ?> poin</p>
            </div>
            <div class="text-2xl <?= $reportData['trend'][1] ?>">
                <i class="<?= $reportData['trend'][2] ?>"></i>
            </div>
            <div class="text-center">
                <p class="text-xs text-gray-500">Periode Sekarang</p>
                <p class="text-lg font-bold text-gray-700"><?= $reportData['points']['net_points'] ?> poin</p>
            </div>
            <div class="ml-4">
                <span class="px-3 py-1 rounded-full text-sm font-medium <?= $reportData['trend'][1] ?>">
                    <i class="<?= $reportData['trend'][2] ?>"></i> <?= $reportData['trend'][0] ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Teacher Recommendation -->
    <div class="mb-6">
        <h4 class="font-bold text-gray-800 mb-2 border-b border-gray-200 pb-1"><i class="fas fa-comment-dots text-gray-600"></i> Catatan/Rekomendasi Wali Kelas</h4>
        <div class="p-3 rounded-lg bg-gray-50 min-h-[60px] text-sm text-gray-600">
            <?php
            $netPts = (int)$reportData['points']['net_points'];
            if ($netPts >= 50) {
                echo "Siswa menunjukkan karakter yang sangat baik. Pertahankan perilaku positif dan dapat menjadi teladan bagi teman-teman.";
            } elseif ($netPts >= 20) {
                echo "Siswa menunjukkan perkembangan karakter yang baik. Terus tingkatkan perilaku positif.";
            } elseif ($netPts >= 0) {
                echo "Siswa cukup baik namun masih perlu bimbingan untuk meningkatkan kedisiplinan dan perilaku positif.";
            } else {
                echo "Siswa memerlukan pembinaan intensif. Diperlukan kerjasama orang tua dan sekolah untuk peningkatan karakter.";
            }
            ?>
        </div>
    </div>

    <!-- Signature -->
    <div class="grid grid-cols-2 gap-8 mt-8 pt-4 border-t border-gray-200">
        <div class="text-center text-sm">
            <p class="text-gray-600">Orang Tua/Wali</p>
            <div class="h-16"></div>
            <p class="border-t border-gray-400 pt-1 inline-block px-8">(...................................)</p>
        </div>
        <div class="text-center text-sm">
            <p class="text-gray-600">Wali Kelas</p>
            <div class="h-16"></div>
            <p class="border-t border-gray-400 pt-1 inline-block px-8">(...................................)</p>
        </div>
    </div>

    <div class="text-center mt-6 text-xs text-gray-400">
        Dicetak pada: <?= date('d/m/Y H:i') ?> | SIKAPDIK - <?= SCHOOL_NAME ?>
    </div>
</div>

<?php elseif ($showReport && $studentId > 0): ?>
<div class="text-center py-8 text-gray-500">Data siswa tidak ditemukan.</div>
<?php elseif (!$showReport): ?>
<div class="text-center py-8 text-gray-500">
    <i class="fas fa-file-signature text-4xl text-gray-300 mb-3"></i>
    <p>Pilih kelas dan siswa, lalu klik "Tampilkan Rapor" untuk melihat rapor karakter.</p>
</div>
<?php endif; ?>

<style>
@media print {
    .no-print, header, aside, .sidebar, nav { display: none !important; }
    .print-area { 
        box-shadow: none !important; 
        border: none !important; 
        margin: 0 !important;
        padding: 20px !important;
    }
    body { background: white !important; }
    main { padding: 0 !important; overflow: visible !important; }
    .flex.h-screen { display: block !important; }
    .flex-1.flex.flex-col { display: block !important; }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
