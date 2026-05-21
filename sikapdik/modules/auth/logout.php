<?php
/**
 * Logout Handler
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';

Auth::logout();
setFlash('success', 'Anda telah berhasil keluar.');
redirect('modules/auth/login.php');
