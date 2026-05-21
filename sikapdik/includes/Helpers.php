<?php
/**
 * Helper Functions
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */

/**
 * Redirect to URL
 */
function redirect($path) {
    $url = BASE_URL . ltrim($path, '/');
    header("Location: {$url}");
    exit;
}

/**
 * Redirect back
 */
function redirectBack() {
    $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
    header("Location: {$referer}");
    exit;
}

/**
 * Set flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Format date to Indonesian
 */
function formatDate($date, $format = 'long') {
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    $months = ['', 'Januari','Februari','Maret','April','Mei','Juni',
               'Juli','Agustus','September','Oktober','November','Desember'];
    $days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

    switch ($format) {
        case 'long':
            return date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
        case 'full':
            return $days[(int)date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
        case 'short':
            return date('d/m/Y', $timestamp);
        case 'datetime':
            return date('d', $timestamp) . ' ' . $months[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp) . ' ' . date('H:i', $timestamp);
        default:
            return date($format, $timestamp);
    }
}


/**
 * Format time
 */
function formatTime($time) {
    if (empty($time)) return '-';
    return date('H:i', strtotime($time));
}

/**
 * Get asset URL
 */
function asset($path) {
    return BASE_URL . 'assets/' . ltrim($path, '/');
}

/**
 * Get module URL
 */
function moduleUrl($path) {
    return BASE_URL . 'modules/' . ltrim($path, '/');
}

/**
 * Truncate text
 */
function truncate($text, $length = 100) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

/**
 * Get status badge HTML
 */
function statusBadge($status, $type = 'attendance') {
    $badges = [
        'attendance' => [
            'hadir' => ['bg-green-100 text-green-800', 'Hadir'],
            'terlambat' => ['bg-yellow-100 text-yellow-800', 'Terlambat'],
            'sakit' => ['bg-blue-100 text-blue-800', 'Sakit'],
            'izin' => ['bg-purple-100 text-purple-800', 'Izin'],
            'alpa' => ['bg-red-100 text-red-800', 'Alpa'],
        ],
        'behavior' => [
            'keteladanan' => ['bg-green-100 text-green-800', 'Keteladanan'],
            'pelanggaran' => ['bg-red-100 text-red-800', 'Perlu Pembinaan'],
        ],
        'validation' => [
            'pending' => ['bg-yellow-100 text-yellow-800', 'Menunggu Validasi'],
            'approved' => ['bg-green-100 text-green-800', 'Disetujui'],
            'rejected' => ['bg-red-100 text-red-800', 'Ditolak'],
        ],
        'followup' => [
            'belum_diproses' => ['bg-red-100 text-red-800', 'Belum Diproses'],
            'dalam_pemantauan' => ['bg-yellow-100 text-yellow-800', 'Dalam Pemantauan'],
            'selesai' => ['bg-green-100 text-green-800', 'Selesai'],
        ],
        'severity' => [
            'ringan' => ['bg-blue-100 text-blue-800', 'Ringan'],
            'sedang' => ['bg-yellow-100 text-yellow-800', 'Sedang'],
            'berat' => ['bg-red-100 text-red-800', 'Berat'],
        ]
    ];

    $badge = $badges[$type][$status] ?? ['bg-gray-100 text-gray-800', ucfirst($status)];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $badge[0] . '">' . $badge[1] . '</span>';
}

/**
 * Pagination helper
 */
function paginate($totalItems, $currentPage, $perPage = 10) {
    $totalPages = ceil($totalItems / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total_items' => $totalItems,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<nav class="flex items-center justify-between mt-4">';
    $html .= '<div class="text-sm text-gray-700">Menampilkan ' . (($pagination['current_page'] - 1) * $pagination['per_page'] + 1) . ' - ' . min($pagination['current_page'] * $pagination['per_page'], $pagination['total_items']) . ' dari ' . $pagination['total_items'] . ' data</div>';
    $html .= '<div class="flex gap-1">';

    if ($pagination['has_prev']) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($pagination['current_page'] - 1) . '" class="px-3 py-1 rounded bg-white border border-gray-300 text-sm hover:bg-gray-50">&laquo;</a>';
    }

    for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++) {
        $active = $i == $pagination['current_page'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50';
        $html .= '<a href="' . $baseUrl . '&page=' . $i . '" class="px-3 py-1 rounded border text-sm ' . $active . '">' . $i . '</a>';
    }

    if ($pagination['has_next']) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($pagination['current_page'] + 1) . '" class="px-3 py-1 rounded bg-white border border-gray-300 text-sm hover:bg-gray-50">&raquo;</a>';
    }

    $html .= '</div></nav>';
    return $html;
}

/**
 * Get role display name
 */
function getRoleName($role) {
    $roles = [
        'admin' => 'Admin/Operator',
        'kepala_sekolah' => 'Kepala Sekolah',
        'wali_kelas' => 'Wali Kelas',
        'guru_mapel' => 'Guru Mapel',
        'orang_tua' => 'Orang Tua/Wali'
    ];
    return $roles[$role] ?? ucfirst($role);
}

/**
 * Number format Indonesian
 */
function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

/**
 * Check if request is POST
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Get POST data safely
 */
function post($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/**
 * Get GET data safely
 */
function get($key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}
