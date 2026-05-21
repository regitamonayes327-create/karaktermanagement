<?php
/**
 * SIKAPDIK Installation Script
 * Run this file ONCE after uploading to hosting
 * DELETE this file after successful installation!
 */

// Prevent re-installation
if (file_exists(__DIR__ . '/config/.installed')) {
    die('<h2 style="color:red;">Aplikasi sudah terinstall. Hapus file install.php untuk keamanan.</h2>');
}

$step = $_GET['step'] ?? '1';
$error = '';
$success = '';

// Step 2: Process installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '2') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/') . '/';
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? '';
    $schoolName = trim($_POST['school_name'] ?? '');

    // Validate
    if (empty($dbName) || empty($dbUser) || empty($adminPass) || strlen($adminPass) < 8) {
        $error = 'Semua field wajib diisi. Password admin minimal 8 karakter.';
        $step = '1';
    } else {
        try {
            // Test connection
            $dsn = "mysql:host={$dbHost};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            // Import SQL schema
            $sqlFile = __DIR__ . '/database/sikapdik.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("File database/sikapdik.sql tidak ditemukan.");
            }
            
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);

            // Update admin password with proper hash
            $adminHash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, username = ? WHERE role = 'admin' LIMIT 1");
            $stmt->execute([$adminHash, $adminUser]);

            // Update school name in settings
            if (!empty($schoolName)) {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'school_name'");
                $stmt->execute([$schoolName]);
            }

            // Update config/database.php
            $configContent = "<?php
/**
 * Database Configuration
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */

define('DB_HOST', '{$dbHost}');
define('DB_NAME', '{$dbName}');
define('DB_USER', '{$dbUser}');
define('DB_PASS', '{$dbPass}');
define('DB_CHARSET', 'utf8mb4');

// Base URL
define('BASE_URL', '{$baseUrl}');

// Application Info
define('APP_NAME', 'SIKAPDIK');
define('APP_FULL_NAME', 'Sistem Informasi Pemantauan Perilaku Siswa');
define('APP_VERSION', '1.0.0');
define('SCHOOL_NAME', '" . addslashes($schoolName ?: 'SD Negeri 04 Jatigunung') . "');

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// Upload settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('QRCODE_PATH', __DIR__ . '/../uploads/qrcodes/');

// Timezone
date_default_timezone_set('Asia/Jakarta');
";
            file_put_contents(__DIR__ . '/config/database.php', $configContent);

            // Create uploads directories
            @mkdir(__DIR__ . '/uploads', 0755, true);
            @mkdir(__DIR__ . '/uploads/qrcodes', 0755, true);
            @mkdir(__DIR__ . '/uploads/prestasi', 0755, true);

            // Create .installed flag
            file_put_contents(__DIR__ . '/config/.installed', date('Y-m-d H:i:s'));

            $success = 'Instalasi berhasil!';
            $step = '3';

        } catch (PDOException $e) {
            $error = 'Koneksi database gagal: ' . $e->getMessage();
            $step = '1';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            $step = '1';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi SIKAPDIK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-xl">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-graduation-cap text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Instalasi SIKAPDIK</h1>
            <p class="text-sm text-gray-500">Sistem Informasi Pemantauan Perilaku Siswa</p>
        </div>

        <!-- Progress -->
        <div class="flex items-center justify-center gap-2 mb-8">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?= $step >= '1' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' ?>">1</div>
            <div class="w-12 h-0.5 <?= $step >= '2' ? 'bg-blue-600' : 'bg-gray-200' ?>"></div>
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?= $step >= '2' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' ?>">2</div>
            <div class="w-12 h-0.5 <?= $step >= '3' ? 'bg-blue-600' : 'bg-gray-200' ?>"></div>
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?= $step >= '3' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-500' ?>">3</div>
        </div>

        <?php if ($error): ?>
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($step === '1' || $step === '2'): ?>
        <!-- Step 1: Configuration Form -->
        <form method="POST" action="?step=2">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Konfigurasi Database & Aplikasi</h3>

            <div class="space-y-4">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 mb-3"><i class="fas fa-database text-blue-500"></i> Database</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Host</label>
                            <input type="text" name="db_host" value="localhost" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Nama Database</label>
                            <input type="text" name="db_name" value="sdnjatig_sikapdikdb" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Username DB</label>
                            <input type="text" name="db_user" value="sdnjatig_sikapdikuser" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Password DB</label>
                            <input type="text" name="db_pass" value="I}W#.nDAMRcEK{FA" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 mb-3"><i class="fas fa-globe text-green-500"></i> Aplikasi</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Base URL (dengan / di akhir)</label>
                            <input type="url" name="base_url" value="https://sikapdik.sdn4jatigunung.sch.id/" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Nama Sekolah</label>
                            <input type="text" name="school_name" value="SD Negeri 04 Jatigunung" class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 mb-3"><i class="fas fa-user-shield text-purple-500"></i> Akun Admin</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Username Admin</label>
                            <input type="text" name="admin_user" value="admin" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Password Admin (min 8 karakter)</label>
                            <input type="password" name="admin_pass" class="w-full px-3 py-2 border rounded-lg text-sm" required minlength="8" placeholder="Min. 8 karakter">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full mt-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition">
                <i class="fas fa-rocket"></i> Mulai Instalasi
            </button>
        </form>

        <?php elseif ($step === '3'): ?>
        <!-- Step 3: Success -->
        <div class="text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-green-600 text-3xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Instalasi Berhasil!</h3>
            <p class="text-sm text-gray-600 mb-6">Aplikasi SIKAPDIK siap digunakan.</p>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 text-left">
                <h4 class="text-sm font-bold text-yellow-800 mb-2"><i class="fas fa-exclamation-triangle"></i> PENTING!</h4>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>1. <strong>HAPUS file install.php</strong> sekarang juga untuk keamanan!</li>
                    <li>2. Pastikan folder <code>uploads/</code> memiliki permission 755.</li>
                    <li>3. Login dengan username dan password admin yang Anda buat.</li>
                </ul>
            </div>

            <a href="modules/auth/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition">
                <i class="fas fa-sign-in-alt"></i> Login ke Aplikasi
            </a>
        </div>
        <?php endif; ?>
    </div>

    <p class="text-center text-gray-400 text-xs mt-4">SIKAPDIK v1.0.0 &copy; <?= date('Y') ?> SD Negeri 04 Jatigunung</p>
</div>
</body>
</html>
