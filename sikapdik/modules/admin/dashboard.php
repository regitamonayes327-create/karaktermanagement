<?php
/**
 * Admin Dashboard
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Dashboard Admin');

$db = Database::getInstance();

// Stats
$totalStudents = $db->count('students', 'is_active = 1');
$totalTeachers = $db->count('teachers', 'is_active = 1');
$totalClasses = $db->count('classes', 'is_active = 1');
$totalUsers = $db->count('users', 'is_active = 1');

// Today's attendance
$today = date('Y-m-d');
$todayPresent = $db->count('attendances', "date = ? AND status IN ('hadir','terlambat')", [$today]);
$todayLate = $db->count('attendances', "date = ? AND status = 'terlambat'", [$today]);
$todayAbsent = $db->count('attendances', "date = ? AND status = 'alpa'", [$today]);

// This month behavior
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthPositive = $db->count('behavior_records', "type = 'keteladanan' AND incident_date BETWEEN ? AND ?", [$monthStart, $monthEnd]);
$monthNegative = $db->count('behavior_records', "type = 'pelanggaran' AND incident_date BETWEEN ? AND ?", [$monthStart, $monthEnd]);

// Pending follow-ups
$pendingFollowUps = $db->count('follow_ups', "status = 'belum_diproses'");

// Recent activities
$recentLogs = $db->fetchAll("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10");

include __DIR__ . '/../../templates/header.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Siswa</p>
                <p class="text-2xl font-bold text-gray-800"><?= formatNumber($totalStudents) ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Guru</p>
                <p class="text-2xl font-bold text-gray-800"><?= formatNumber($totalTeachers) ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-chalkboard-teacher text-green-600 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Kelas</p>
                <p class="text-2xl font-bold text-gray-800"><?= formatNumber($totalClasses) ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-school text-purple-600 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Pengguna Aktif</p>
                <p class="text-2xl font-bold text-gray-800"><?= formatNumber($totalUsers) ?></p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-yellow-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Attendance & Behavior Summary -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Today Attendance -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-calendar-check text-blue-600"></i> Presensi Hari Ini
        </h3>
        <div class="grid grid-cols-3 gap-4">
            <div class="text-center p-3 bg-green-50 rounded-lg">
                <p class="text-2xl font-bold text-green-600"><?= $todayPresent ?></p>
                <p class="text-xs text-gray-500">Hadir</p>
            </div>
            <div class="text-center p-3 bg-yellow-50 rounded-lg">
                <p class="text-2xl font-bold text-yellow-600"><?= $todayLate ?></p>
                <p class="text-xs text-gray-500">Terlambat</p>
            </div>
            <div class="text-center p-3 bg-red-50 rounded-lg">
                <p class="text-2xl font-bold text-red-600"><?= $todayAbsent ?></p>
                <p class="text-xs text-gray-500">Alpa</p>
            </div>
        </div>
    </div>

    <!-- This Month Behavior -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-green-600"></i> Perilaku Bulan Ini
        </h3>
        <div class="grid grid-cols-3 gap-4">
            <div class="text-center p-3 bg-green-50 rounded-lg">
                <p class="text-2xl font-bold text-green-600"><?= $monthPositive ?></p>
                <p class="text-xs text-gray-500">Keteladanan</p>
            </div>
            <div class="text-center p-3 bg-red-50 rounded-lg">
                <p class="text-2xl font-bold text-red-600"><?= $monthNegative ?></p>
                <p class="text-xs text-gray-500">Pelanggaran</p>
            </div>
            <div class="text-center p-3 bg-orange-50 rounded-lg">
                <p class="text-2xl font-bold text-orange-600"><?= $pendingFollowUps ?></p>
                <p class="text-xs text-gray-500">Perlu Tindak Lanjut</p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Aksi Cepat</h3>
        <div class="space-y-2">
            <a href="<?= BASE_URL ?>modules/admin/students.php?action=add" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition text-sm text-gray-700">
                <i class="fas fa-user-plus text-blue-600 w-5"></i> Tambah Siswa Baru
            </a>
            <a href="<?= BASE_URL ?>modules/admin/qrcode.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition text-sm text-gray-700">
                <i class="fas fa-qrcode text-purple-600 w-5"></i> Generate QR Code
            </a>
            <a href="<?= BASE_URL ?>modules/admin/users.php?action=add" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition text-sm text-gray-700">
                <i class="fas fa-user-shield text-green-600 w-5"></i> Tambah Pengguna
            </a>
            <a href="<?= BASE_URL ?>modules/admin/reports.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition text-sm text-gray-700">
                <i class="fas fa-file-alt text-orange-600 w-5"></i> Lihat Laporan
            </a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Aktivitas Terbaru</h3>
        <div class="space-y-3 max-h-64 overflow-y-auto">
            <?php if (empty($recentLogs)): ?>
            <p class="text-sm text-gray-500 text-center py-4">Belum ada aktivitas tercatat.</p>
            <?php else: ?>
            <?php foreach ($recentLogs as $log): ?>
            <div class="flex items-start gap-3 p-2 rounded hover:bg-gray-50">
                <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fas fa-circle text-blue-400 text-[6px]"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($log['description'] ?? $log['action']) ?></p>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($log['full_name'] ?? 'System') ?> &bull; <?= formatDate($log['created_at'], 'datetime') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
