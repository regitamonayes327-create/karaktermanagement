<?php
/**
 * Main Layout Header with Sidebar
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'SIKAPDIK');

$currentRole = Auth::getRole();
$currentUser = Auth::getFullName();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Menu structure per role
$menus = [
    'admin' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'modules/admin/dashboard.php', 'id' => 'dashboard'],
        ['icon' => 'fas fa-users', 'label' => 'Data Pengguna', 'url' => 'modules/admin/users.php', 'id' => 'users'],
        ['icon' => 'fas fa-chalkboard-teacher', 'label' => 'Data Guru', 'url' => 'modules/admin/teachers.php', 'id' => 'teachers'],
        ['icon' => 'fas fa-school', 'label' => 'Data Kelas', 'url' => 'modules/admin/classes.php', 'id' => 'classes'],
        ['icon' => 'fas fa-user-graduate', 'label' => 'Data Siswa', 'url' => 'modules/admin/students.php', 'id' => 'students'],
        ['icon' => 'fas fa-user-friends', 'label' => 'Data Orang Tua', 'url' => 'modules/admin/parents.php', 'id' => 'parents'],
        ['icon' => 'fas fa-qrcode', 'label' => 'Generate QR Code', 'url' => 'modules/admin/qrcode.php', 'id' => 'qrcode'],
        ['icon' => 'fas fa-list-alt', 'label' => 'Kategori Perilaku', 'url' => 'modules/admin/behavior_categories.php', 'id' => 'behavior_categories'],
        ['icon' => 'fas fa-calendar-check', 'label' => 'Presensi', 'url' => 'modules/admin/attendance.php', 'id' => 'attendance'],
        ['icon' => 'fas fa-level-up-alt', 'label' => 'Kenaikan Kelas', 'url' => 'modules/admin/class_promotion.php', 'id' => 'class_promotion'],
        ['icon' => 'fas fa-file-alt', 'label' => 'Laporan', 'url' => 'modules/admin/reports.php', 'id' => 'reports'],
        ['icon' => 'fas fa-history', 'label' => 'Audit Log', 'url' => 'modules/admin/audit_log.php', 'id' => 'audit_log'],
        ['icon' => 'fas fa-cog', 'label' => 'Pengaturan', 'url' => 'modules/admin/settings.php', 'id' => 'settings'],
        ['icon' => 'fas fa-sync-alt', 'label' => 'Manajemen Data', 'url' => 'modules/admin/year_reset.php', 'id' => 'year_reset'],
    ],
    'kepala_sekolah' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'modules/kepala_sekolah/dashboard.php', 'id' => 'dashboard'],
        ['icon' => 'fas fa-chart-bar', 'label' => 'Analitik Presensi', 'url' => 'modules/kepala_sekolah/attendance_analytics.php', 'id' => 'attendance_analytics'],
        ['icon' => 'fas fa-chart-line', 'label' => 'Analitik Perilaku', 'url' => 'modules/kepala_sekolah/behavior_analytics.php', 'id' => 'behavior_analytics'],
        ['icon' => 'fas fa-hands-helping', 'label' => 'Tindak Lanjut', 'url' => 'modules/kepala_sekolah/follow_ups.php', 'id' => 'follow_ups'],
        ['icon' => 'fas fa-exclamation-triangle', 'label' => 'Perlu Perhatian', 'url' => 'modules/kepala_sekolah/attention.php', 'id' => 'attention'],
        ['icon' => 'fas fa-trophy', 'label' => 'Prestasi', 'url' => 'modules/kepala_sekolah/achievements.php', 'id' => 'achievements'],
        ['icon' => 'fas fa-star', 'label' => 'Potensi Siswa', 'url' => 'modules/kepala_sekolah/potentials.php', 'id' => 'potentials'],
        ['icon' => 'fas fa-file-alt', 'label' => 'Laporan', 'url' => 'modules/kepala_sekolah/reports.php', 'id' => 'reports'],
        ['icon' => 'fas fa-file-signature', 'label' => 'Rapor Karakter', 'url' => 'modules/kepala_sekolah/character_report.php', 'id' => 'character_report'],
    ],
    'wali_kelas' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'modules/wali_kelas/dashboard.php', 'id' => 'dashboard'],
        ['icon' => 'fas fa-qrcode', 'label' => 'Scan QR Presensi', 'url' => 'modules/wali_kelas/scan_qr.php', 'id' => 'scan_qr'],
        ['icon' => 'fas fa-clipboard-list', 'label' => 'Presensi Manual', 'url' => 'modules/wali_kelas/manual_attendance.php', 'id' => 'manual_attendance'],
        ['icon' => 'fas fa-star', 'label' => 'Input Perilaku', 'url' => 'modules/wali_kelas/input_behavior.php', 'id' => 'input_behavior'],
        ['icon' => 'fas fa-clipboard-check', 'label' => 'Catatan Guru Mapel', 'url' => 'modules/wali_kelas/teacher_notes.php', 'id' => 'teacher_notes'],
        ['icon' => 'fas fa-hands-helping', 'label' => 'Tindak Lanjut', 'url' => 'modules/wali_kelas/follow_ups.php', 'id' => 'follow_ups'],
        ['icon' => 'fas fa-trophy', 'label' => 'Prestasi', 'url' => 'modules/wali_kelas/achievements.php', 'id' => 'achievements'],
        ['icon' => 'fas fa-lightbulb', 'label' => 'Potensi', 'url' => 'modules/wali_kelas/potentials.php', 'id' => 'potentials'],
        ['icon' => 'fas fa-id-card', 'label' => 'Profil Siswa', 'url' => 'modules/wali_kelas/student_profile.php', 'id' => 'student_profile'],
        ['icon' => 'fas fa-file-alt', 'label' => 'Laporan Kelas', 'url' => 'modules/wali_kelas/reports.php', 'id' => 'reports'],
        ['icon' => 'fas fa-file-signature', 'label' => 'Rapor Karakter', 'url' => 'modules/wali_kelas/character_report.php', 'id' => 'character_report'],
    ],
    'guru_mapel' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'modules/guru_mapel/dashboard.php', 'id' => 'dashboard'],
        ['icon' => 'fas fa-qrcode', 'label' => 'Scan QR Presensi', 'url' => 'modules/wali_kelas/scan_qr.php', 'id' => 'scan_qr'],
        ['icon' => 'fas fa-clipboard-list', 'label' => 'Presensi Manual', 'url' => 'modules/wali_kelas/manual_attendance.php', 'id' => 'manual_attendance'],
        ['icon' => 'fas fa-school', 'label' => 'Kelas yang Diajar', 'url' => 'modules/guru_mapel/my_classes.php', 'id' => 'my_classes'],
        ['icon' => 'fas fa-thumbs-up', 'label' => 'Input Keteladanan', 'url' => 'modules/guru_mapel/input_positive.php', 'id' => 'input_positive'],
        ['icon' => 'fas fa-exclamation-circle', 'label' => 'Input Pelanggaran', 'url' => 'modules/guru_mapel/input_negative.php', 'id' => 'input_negative'],
        ['icon' => 'fas fa-history', 'label' => 'Riwayat Input', 'url' => 'modules/guru_mapel/my_records.php', 'id' => 'my_records'],
        ['icon' => 'fas fa-trophy', 'label' => 'Prestasi/Potensi', 'url' => 'modules/guru_mapel/achievements.php', 'id' => 'achievements'],
    ],
    'orang_tua' => [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'modules/orang_tua/dashboard.php', 'id' => 'dashboard'],
        ['icon' => 'fas fa-calendar-check', 'label' => 'Presensi Anak', 'url' => 'modules/orang_tua/attendance.php', 'id' => 'attendance'],
        ['icon' => 'fas fa-star', 'label' => 'Perilaku Positif', 'url' => 'modules/orang_tua/positive.php', 'id' => 'positive'],
        ['icon' => 'fas fa-trophy', 'label' => 'Prestasi', 'url' => 'modules/orang_tua/achievements.php', 'id' => 'achievements'],
        ['icon' => 'fas fa-info-circle', 'label' => 'Catatan Pembinaan', 'url' => 'modules/orang_tua/follow_ups.php', 'id' => 'follow_ups'],
        ['icon' => 'fas fa-bell', 'label' => 'Notifikasi', 'url' => 'modules/orang_tua/notifications.php', 'id' => 'notifications'],
    ],
];

$roleMenus = $menus[$currentRole] ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= PAGE_TITLE ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .sidebar-active { background: rgba(59, 130, 246, 0.1); border-right: 3px solid #3b82f6; color: #3b82f6; }
        .sidebar-item:hover { background: rgba(59, 130, 246, 0.05); }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); } }
    </style>
</head>
<body class="bg-gray-50 font-sans">
<div class="flex h-screen overflow-hidden">


    <!-- Sidebar -->
    <aside class="sidebar fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transition-transform duration-300 md:relative md:translate-x-0 flex flex-col" id="sidebar">
        <!-- Logo -->
        <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-100">
            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-graduation-cap text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-800"><?= APP_NAME ?></h1>
                <p class="text-xs text-gray-500"><?= SCHOOL_NAME ?></p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-4 px-3">
            <ul class="space-y-1">
                <?php foreach ($roleMenus as $menu): ?>
                <li>
                    <a href="<?= BASE_URL . $menu['url'] ?>" 
                       class="sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-600 transition-all <?= $currentPage === $menu['id'] ? 'sidebar-active' : '' ?>">
                        <i class="<?= $menu['icon'] ?> w-5 text-center"></i>
                        <span><?= $menu['label'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- User info bottom -->
        <div class="border-t border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-blue-600 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-700 truncate"><?= htmlspecialchars($currentUser) ?></p>
                    <p class="text-xs text-gray-500"><?= getRoleName($currentRole) ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm border-b border-gray-100 px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-700">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h2 class="text-lg font-semibold text-gray-800"><?= PAGE_TITLE ?></h2>
            </div>
            <div class="flex items-center gap-4">
                <!-- Notifications -->
                <a href="<?= BASE_URL ?>modules/<?= $currentRole ?>/notifications.php" class="relative text-gray-500 hover:text-gray-700">
                    <i class="fas fa-bell text-lg"></i>
                    <?php
                    $unreadCount = Database::getInstance()->count('notifications', 'user_id = ? AND is_read = 0', [Auth::getUserId()]);
                    if ($unreadCount > 0):
                    ?>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-white text-[10px] flex items-center justify-center"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <!-- Profile dropdown -->
                <div class="relative" id="profileDropdown">
                    <button onclick="document.getElementById('profileMenu').classList.toggle('hidden')" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-800">
                        <span class="hidden sm:inline"><?= htmlspecialchars($currentUser) ?></span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <div id="profileMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-2 z-50">
                        <a href="<?= BASE_URL ?>modules/auth/profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-user-circle w-4"></i> Profil Saya
                        </a>
                        <a href="<?= BASE_URL ?>modules/auth/change_password.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-key w-4"></i> Ubah Password
                        </a>
                        <hr class="my-1">
                        <a href="<?= BASE_URL ?>modules/auth/logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fas fa-sign-out-alt w-4"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-6">
            <?php
            $flash = getFlash();
            if ($flash):
            $flashColors = ['success' => 'green', 'error' => 'red', 'warning' => 'yellow', 'info' => 'blue'];
            $color = $flashColors[$flash['type']] ?? 'blue';
            ?>
            <div class="mb-4 p-4 rounded-lg bg-<?= $color ?>-50 border border-<?= $color ?>-200 text-<?= $color ?>-800 flex items-center gap-3" id="flashMessage">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'times-circle' : 'info-circle') ?>"></i>
                <span><?= $flash['message'] ?></span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-<?= $color ?>-600 hover:text-<?= $color ?>-800">&times;</button>
            </div>
            <?php endif; ?>
