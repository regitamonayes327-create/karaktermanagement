<?php
/**
 * Security Class - CSRF, XSS, Input Validation
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */

class Security {

    /**
     * Initialize secure session
     */
    public static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

            session_name('SIKAPDIK_SESSION');
            session_start();

            // Regenerate session ID periodically
            if (!isset($_SESSION['_last_regeneration'])) {
                self::regenerateSession();
            } elseif (time() - $_SESSION['_last_regeneration'] > 300) {
                self::regenerateSession();
            }
        }
    }

    /**
     * Regenerate session ID safely
     */
    public static function regenerateSession() {
        session_regenerate_id(true);
        $_SESSION['_last_regeneration'] = time();
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Get CSRF hidden input field
     */
    public static function csrfField() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRF($token = null) {
        if ($token === null) {
            $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        
        if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
            return false;
        }

        $valid = hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
        
        // Regenerate token after validation
        if ($valid) {
            unset($_SESSION[CSRF_TOKEN_NAME]);
        }
        
        return $valid;
    }

    /**
     * Sanitize string input (XSS prevention)
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Clean input without HTML encoding (for database storage)
     */
    public static function clean($input) {
        if (is_array($input)) {
            return array_map([self::class, 'clean'], $input);
        }
        return strip_tags(trim($input));
    }

    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number (Indonesian format)
     */
    public static function validatePhone($phone) {
        return preg_match('/^(\+62|62|0)[0-9]{8,13}$/', preg_replace('/\s+/', '', $phone));
    }

    /**
     * Validate date
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generate random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                    return trim($ip);
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Rate limiting check
     */
    public static function isRateLimited($key, $maxAttempts = 5, $windowSeconds = 900) {
        $sessionKey = '_rate_' . $key;
        
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = ['attempts' => 0, 'first_attempt' => time()];
        }

        $data = &$_SESSION[$sessionKey];

        // Reset if window has passed
        if (time() - $data['first_attempt'] > $windowSeconds) {
            $data = ['attempts' => 0, 'first_attempt' => time()];
        }

        return $data['attempts'] >= $maxAttempts;
    }

    /**
     * Increment rate limit counter
     */
    public static function incrementRateLimit($key) {
        $sessionKey = '_rate_' . $key;
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = ['attempts' => 0, 'first_attempt' => time()];
        }
        $_SESSION[$sessionKey]['attempts']++;
    }

    /**
     * Reset rate limit
     */
    public static function resetRateLimit($key) {
        $sessionKey = '_rate_' . $key;
        unset($_SESSION[$sessionKey]);
    }

    /**
     * Validate file upload
     */
    public static function validateUpload($file, $allowedTypes = null, $maxSize = null) {
        if ($allowedTypes === null) $allowedTypes = ALLOWED_IMAGE_TYPES;
        if ($maxSize === null) $maxSize = UPLOAD_MAX_SIZE;

        $errors = [];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload gagal. Error code: ' . $file['error'];
            return $errors;
        }

        if ($file['size'] > $maxSize) {
            $errors[] = 'Ukuran file terlalu besar. Maksimal ' . ($maxSize / 1024 / 1024) . 'MB.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Tipe file tidak diizinkan.';
        }

        return $errors;
    }

    /**
     * Secure file upload
     */
    public static function uploadFile($file, $destination, $prefix = '') {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $prefix . self::generateToken(16) . '.' . $extension;
        $filepath = rtrim($destination, '/') . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $filename;
        }
        return false;
    }

    /**
     * Prevent clickjacking
     */
    public static function sendSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
