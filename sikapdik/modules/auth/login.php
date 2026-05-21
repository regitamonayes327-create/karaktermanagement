<?php
/**
 * Login Page
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */
require_once __DIR__ . '/../../config/app.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect('');
}

$error = '';

if (isPost()) {
    // Validate CSRF
    if (!Security::validateCSRF()) {
        $error = 'Sesi tidak valid. Silakan coba lagi.';
    } else {
        $username = Security::clean(post('username'));
        $password = post('password');

        if (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi.';
        } else {
            $result = Auth::login($username, $password);
            if ($result['success']) {
                // Redirect to intended page or dashboard
                $redirectTo = $_SESSION['redirect_after_login'] ?? '';
                unset($_SESSION['redirect_after_login']);
                
                if (!empty($redirectTo)) {
                    header("Location: " . $redirectTo);
                } else {
                    redirect('');
                }
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <i class="fas fa-graduation-cap text-white text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800"><?= APP_NAME ?></h1>
                <p class="text-sm text-gray-500 mt-1"><?= APP_FULL_NAME ?></p>
                <p class="text-xs text-gray-400 mt-1"><?= SCHOOL_NAME ?></p>
            </div>

            <!-- Error message -->
            <?php if ($error): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" autocomplete="off">
                <?= Security::csrfField() ?>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="username" 
                               value="<?= htmlspecialchars(post('username')) ?>"
                               class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm"
                               placeholder="Masukkan username" required autofocus>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="passwordInput"
                               class="w-full pl-10 pr-10 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm"
                               placeholder="Masukkan password" required>
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" 
                        class="w-full py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:ring-4 focus:ring-blue-200 transition duration-200 flex items-center justify-center gap-2">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Masuk</span>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-white/60 text-xs mt-6">
            &copy; <?= date('Y') ?> <?= SCHOOL_NAME ?> &mdash; <?= APP_NAME ?> v<?= APP_VERSION ?>
        </p>
    </div>

    <script>
    function togglePassword() {
        const input = document.getElementById('passwordInput');
        const icon = document.getElementById('toggleIcon');
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
