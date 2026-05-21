<?php
/**
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 * SD Negeri 04 Jatigunung
 * 
 * Main Entry Point
 */

require_once __DIR__ . '/config/app.php';

// If user is logged in, redirect to dashboard
if (Auth::isLoggedIn()) {
    $role = Auth::getRole();
    switch ($role) {
        case 'admin':
            redirect('modules/admin/dashboard.php');
            break;
        case 'kepala_sekolah':
            redirect('modules/kepala_sekolah/dashboard.php');
            break;
        case 'wali_kelas':
            redirect('modules/wali_kelas/dashboard.php');
            break;
        case 'guru_mapel':
            redirect('modules/guru_mapel/dashboard.php');
            break;
        case 'orang_tua':
            redirect('modules/orang_tua/dashboard.php');
            break;
        default:
            redirect('modules/auth/login.php');
    }
} else {
    redirect('modules/auth/login.php');
}
