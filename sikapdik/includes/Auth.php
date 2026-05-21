<?php
/**
 * Authentication Class
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */

class Auth {

    /**
     * Attempt login
     */
    public static function login($username, $password) {
        $db = Database::getInstance();

        // Check rate limiting
        if (Security::isRateLimited('login_' . $username, MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
            return ['success' => false, 'message' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.'];
        }

        // Find user
        $user = $db->fetch(
            "SELECT * FROM users WHERE username = ? AND is_active = 1",
            [$username]
        );

        if (!$user) {
            Security::incrementRateLimit('login_' . $username);
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Akun terkunci. Silakan coba lagi nanti.'];
        }

        // Verify password
        if (!Security::verifyPassword($password, $user['password'])) {
            // Increment login attempts
            $attempts = $user['login_attempts'] + 1;
            $lockUntil = null;

            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
            }

            $db->update('users', [
                'login_attempts' => $attempts,
                'locked_until' => $lockUntil
            ], 'id = ?', [$user['id']]);

            Security::incrementRateLimit('login_' . $username);
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        // Login success - reset attempts and set session
        $db->update('users', [
            'login_attempts' => 0,
            'locked_until' => null,
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);

        // Regenerate session
        Security::regenerateSession();
        Security::resetRateLimit('login_' . $username);

        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // Get additional data based on role
        if ($user['role'] === 'wali_kelas' || $user['role'] === 'guru_mapel') {
            $teacher = $db->fetch("SELECT * FROM teachers WHERE user_id = ?", [$user['id']]);
            if ($teacher) {
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['is_homeroom'] = $teacher['is_homeroom'];
                
                if ($teacher['is_homeroom']) {
                    $class = $db->fetch("SELECT id, class_name FROM classes WHERE homeroom_teacher_id = ? AND is_active = 1", [$teacher['id']]);
                    if ($class) {
                        $_SESSION['class_id'] = $class['id'];
                        $_SESSION['class_name'] = $class['class_name'];
                    }
                }
            }
        } elseif ($user['role'] === 'orang_tua') {
            $parent = $db->fetch("SELECT * FROM parents WHERE user_id = ?", [$user['id']]);
            if ($parent) {
                $_SESSION['parent_id'] = $parent['id'];
            }
        }

        // Log activity
        self::logActivity('login', 'auth', 'User login berhasil');

        return ['success' => true, 'message' => 'Login berhasil.', 'role' => $user['role']];
    }

    /**
     * Logout user
     */
    public static function logout() {
        self::logActivity('logout', 'auth', 'User logout');
        
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
            self::logout();
            return false;
        }

        // Update activity time
        $_SESSION['login_time'] = time();
        return true;
    }

    /**
     * Get current user role
     */
    public static function getRole() {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user full name
     */
    public static function getFullName() {
        return $_SESSION['full_name'] ?? '';
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole($roles) {
        if (!self::isLoggedIn()) return false;
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array(self::getRole(), $roles);
    }

    /**
     * Require login - redirect to login page if not authenticated
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
            redirect('modules/auth/login.php');
            exit;
        }
    }

    /**
     * Require specific role(s)
     */
    public static function requireRole($roles) {
        self::requireLogin();
        
        if (!self::hasRole($roles)) {
            http_response_code(403);
            include __DIR__ . '/../templates/403.php';
            exit;
        }
    }

    /**
     * Change password
     */
    public static function changePassword($userId, $currentPassword, $newPassword) {
        $db = Database::getInstance();
        
        $user = $db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
        
        if (!$user || !Security::verifyPassword($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Password lama salah.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password baru minimal 8 karakter.'];
        }

        $hash = Security::hashPassword($newPassword);
        $db->update('users', [
            'password' => $hash,
            'password_changed_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$userId]);

        self::logActivity('change_password', 'auth', 'User mengganti password');
        
        return ['success' => true, 'message' => 'Password berhasil diubah.'];
    }

    /**
     * Reset password (by admin)
     */
    public static function resetPassword($userId, $newPassword) {
        $db = Database::getInstance();
        $hash = Security::hashPassword($newPassword);
        
        $db->update('users', [
            'password' => $hash,
            'login_attempts' => 0,
            'locked_until' => null,
            'password_changed_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$userId]);

        self::logActivity('reset_password', 'auth', 'Admin mereset password user ID: ' . $userId);
        
        return true;
    }

    /**
     * Log activity to audit log
     */
    public static function logActivity($action, $module, $description = '', $oldData = null, $newData = null) {
        try {
            $db = Database::getInstance();
            $db->insert('audit_logs', [
                'user_id' => self::getUserId(),
                'action' => $action,
                'module' => $module,
                'description' => $description,
                'ip_address' => Security::getClientIP(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'old_data' => $oldData ? json_encode($oldData) : null,
                'new_data' => $newData ? json_encode($newData) : null
            ]);
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }
}
