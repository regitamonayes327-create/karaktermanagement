<?php
/**
 * Forgot Password - Reset with Captcha Only
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */
require_once __DIR__ . '/../../config/app.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect('');
}

// Handle refresh/cancel via GET
if (isset($_GET['refresh'])) {
    $_SESSION['forgot_captcha'] = null;
    header("Location: " . BASE_URL . "modules/auth/forgot_password.php");
    exit;
}
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_username'], $_SESSION['reset_fullname'], $_SESSION['forgot_captcha']);
    header("Location: " . BASE_URL . "modules/auth/forgot_password.php");
    exit;
}

// Generate captcha
function generateCaptcha() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $captcha = '';
    for ($i = 0; $i < 6; $i++) {
        $captcha .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $captcha;
}

// Generate new captcha if not set or after submission
if (!isset($_SESSION['forgot_captcha']) || isset($_POST['reset_password'])) {
    $_SESSION['forgot_captcha'] = generateCaptcha();
}

$error = '';
$success = '';
$step = 'find_user'; // find_user -> reset_form

// Step 1: Find user by username
if (isPost() && isset($_POST['find_user'])) {
    if (!Security::validateCSRF()) {
        $error = 'Sesi tidak valid. Silakan coba lagi.';
    } else {
        $username = Security::clean(post('username'));
        
        if (empty($username)) {
            $error = 'Username harus diisi.';
        } else {
            $db = Database::getInstance();
            $user = $db->fetch("SELECT id, username, full_name, role FROM users WHERE username = ? AND is_active = 1", [$username]);
            
            if (!$user) {
                $error = 'Username tidak ditemukan atau akun tidak aktif.';
            } else {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_username'] = $user['username'];
                $_SESSION['reset_fullname'] = $user['full_name'];
                $_SESSION['forgot_captcha'] = generateCaptcha();
                $step = 'reset_form';
            }
        }
    }
}

// Step 2: Reset password with captcha
if (isPost() && isset($_POST['reset_password'])) {
    if (!Security::validateCSRF()) {
        $error = 'Sesi tidak valid. Silakan coba lagi.';
    } else {
        $captchaInput = trim(post('captcha'));
        $newPassword = post('new_password');
        $confirmPassword = post('confirm_password');
        $userId = $_SESSION['reset_user_id'] ?? 0;

        $errors = [];

        // Validate captcha
        if (empty($captchaInput) || $captchaInput !== ($_SESSION['forgot_captcha'] ?? '')) {
            $errors[] = 'Kode captcha salah. Perhatikan huruf besar dan kecil.';
        }

        if (empty($newPassword)) {
            $errors[] = 'Password baru harus diisi.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'Password baru minimal 8 karakter.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }

        if (!$userId) {
            $errors[] = 'Sesi reset tidak valid. Silakan ulangi.';
        }

        if (empty($errors)) {
            $db = Database::getInstance();
            $hash = Security::hashPassword($newPassword);
            
            $db->update('users', [
                'password' => $hash,
                'login_attempts' => 0,
                'locked_until' => null,
                'password_changed_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$userId]);

            // Log activity
            try {
                $db->insert('audit_logs', [
                    'user_id' => $userId,
                    'action' => 'forgot_password_reset',
                    'module' => 'auth',
                    'description' => 'Password direset melalui fitur lupa password',
                    'ip_address' => Security::getClientIP(),
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
                ]);
            } catch (Exception $e) {}

            // Clear session data
            unset($_SESSION['reset_user_id'], $_SESSION['reset_username'], $_SESSION['reset_fullname'], $_SESSION['forgot_captcha']);

            $success = 'Password berhasil diubah! Silakan login dengan password baru.';
            $step = 'find_user';
        } else {
            $error = implode('<br>', $errors);
            $step = 'reset_form';
            // Regenerate captcha on failure
            $_SESSION['forgot_captcha'] = generateCaptcha();
        }
    }
}

// Keep step as reset_form if user data exists in session
if ($step === 'find_user' && isset($_SESSION['reset_user_id']) && !$success) {
    $step = 'reset_form';
}

$captchaCode = $_SESSION['forgot_captcha'] ?? generateCaptcha();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <!-- Logo -->
            <div class="text-center mb-6">
                <div class="w-14 h-14 bg-orange-500 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-lg">
                    <i class="fas fa-key text-white text-xl"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-800">Lupa Password</h1>
                <p class="text-sm text-gray-500 mt-1"><?= APP_NAME ?> - <?= SCHOOL_NAME ?></p>
            </div>

            <!-- Success message -->
            <?php if ($success): ?>
            <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm flex items-start gap-2">
                <i class="fas fa-check-circle mt-0.5"></i>
                <div>
                    <p class="font-medium"><?= $success ?></p>
                    <a href="<?= BASE_URL ?>modules/auth/login.php" class="inline-flex items-center gap-1 mt-2 text-green-800 font-medium hover:underline">
                        <i class="fas fa-sign-in-alt"></i> Ke halaman login
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Error message -->
            <?php if ($error): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-2">
                <i class="fas fa-exclamation-circle mt-0.5"></i>
                <span><?= $error ?></span>
            </div>
            <?php endif; ?>

            <?php if ($step === 'find_user' && !$success): ?>
            <!-- Step 1: Find Username -->
            <form method="POST" autocomplete="off">
                <?= Security::csrfField() ?>
                
                <p class="text-sm text-gray-600 mb-4">Masukkan username Anda untuk mereset password.</p>

                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="username" 
                               value="<?= htmlspecialchars(post('username')) ?>"
                               class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm"
                               placeholder="Masukkan username Anda" required autofocus>
                    </div>
                </div>

                <button type="submit" name="find_user" value="1"
                        class="w-full py-3 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 focus:ring-4 focus:ring-orange-200 transition duration-200 flex items-center justify-center gap-2">
                    <i class="fas fa-search"></i>
                    <span>Cari Akun</span>
                </button>
            </form>

            <?php elseif ($step === 'reset_form'): ?>
            <!-- Step 2: Reset Password with Captcha -->
            <form method="POST" autocomplete="off">
                <?= Security::csrfField() ?>

                <!-- User info -->
                <div class="mb-4 p-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 text-sm">
                    <p class="font-medium"><i class="fas fa-user-check"></i> Akun ditemukan:</p>
                    <p class="mt-1"><?= htmlspecialchars($_SESSION['reset_fullname'] ?? '') ?> <span class="text-blue-500">(@<?= htmlspecialchars($_SESSION['reset_username'] ?? '') ?>)</span></p>
                </div>

                <!-- New Password -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="new_password" id="newPassInput"
                               class="w-full pl-10 pr-10 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm"
                               placeholder="Minimal 8 karakter" required minlength="8">
                        <button type="button" onclick="toggleNewPass()" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye" id="toggleNewIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password Baru</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="confirm_password"
                               class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm"
                               placeholder="Ulangi password baru" required minlength="8">
                    </div>
                </div>

                <!-- Captcha -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kode Verifikasi</label>
                    <div class="flex items-center gap-3 mb-2">
                        <!-- Captcha Display -->
                        <div class="flex-shrink-0 px-4 py-3 bg-gray-900 rounded-lg select-none relative overflow-hidden" id="captchaBox">
                            <!-- Background noise -->
                            <div class="absolute inset-0 opacity-20">
                                <svg width="100%" height="100%">
                                    <line x1="5" y1="5" x2="90%" y2="80%" stroke="#666" stroke-width="1"/>
                                    <line x1="10%" y1="90%" x2="95%" y2="10%" stroke="#888" stroke-width="1"/>
                                    <line x1="50%" y1="0" x2="30%" y2="100%" stroke="#555" stroke-width="1"/>
                                </svg>
                            </div>
                            <span class="relative text-xl font-mono font-bold tracking-[0.3em] text-green-400" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5); letter-spacing: 0.2em; font-style: italic;">
                                <?= htmlspecialchars($captchaCode) ?>
                            </span>
                        </div>
                        <!-- Refresh captcha -->
                        <a href="<?= BASE_URL ?>modules/auth/forgot_password.php?refresh=1" 
                           class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition" title="Ganti kode">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-shield-alt"></i>
                        </span>
                        <input type="text" name="captcha" 
                               class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm"
                               placeholder="Ketik kode di atas (perhatikan besar/kecil)" required autocomplete="off">
                    </div>
                    <p class="text-xs text-gray-400 mt-1"><i class="fas fa-info-circle"></i> Ketik persis sesuai huruf besar/kecil yang ditampilkan</p>
                </div>

                <button type="submit" name="reset_password" value="1"
                        class="w-full py-3 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 focus:ring-4 focus:ring-orange-200 transition duration-200 flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i>
                    <span>Reset Password</span>
                </button>

                <!-- Cancel / Back -->
                <a href="<?= BASE_URL ?>modules/auth/forgot_password.php?cancel=1" 
                   class="block mt-3 text-center text-sm text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left"></i> Ganti username lain
                </a>
            </form>
            <?php endif; ?>

            <!-- Back to login -->
            <?php if (!$success): ?>
            <div class="mt-6 pt-4 border-t border-gray-100 text-center">
                <a href="<?= BASE_URL ?>modules/auth/login.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-arrow-left"></i> Kembali ke Login
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <p class="text-center text-white/60 text-xs mt-6">
            &copy; <?= date('Y') ?> <?= SCHOOL_NAME ?> &mdash; <?= APP_NAME ?> v<?= APP_VERSION ?>
        </p>
    </div>

    <script>
    function toggleNewPass() {
        const input = document.getElementById('newPassInput');
        const icon = document.getElementById('toggleNewIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>
</html>

