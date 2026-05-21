<?php
/**
 * QR Code Scanner for Attendance
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin']);

define('PAGE_TITLE', 'Scan QR Presensi');

$db = Database::getInstance();

// Get late threshold
$lateThreshold = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'late_threshold_time'") ?: '07:00:00';

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-2xl mx-auto">
    <!-- Scanner -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="text-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center justify-center gap-2">
                <i class="fas fa-qrcode text-blue-600"></i> Scanner Presensi QR Code
            </h3>
            <p class="text-sm text-gray-500">Arahkan kamera ke QR Code siswa</p>
            <p class="text-xs text-gray-400 mt-1">Batas terlambat: <?= formatTime($lateThreshold) ?> WIB</p>
        </div>

        <!-- Camera View -->
        <div class="relative mb-4">
            <div id="scanner-container" class="w-full aspect-square max-w-sm mx-auto bg-gray-900 rounded-xl overflow-hidden relative">
                <video id="preview" class="w-full h-full object-cover"></video>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-48 h-48 border-2 border-white/50 rounded-lg"></div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="flex justify-center gap-3 mb-4">
            <button onclick="startScanner()" id="btnStart" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 transition">
                <i class="fas fa-play"></i> Mulai Scan
            </button>
            <button onclick="stopScanner()" id="btnStop" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition hidden">
                <i class="fas fa-stop"></i> Stop
            </button>
        </div>

        <!-- Result -->
        <div id="scan-result" class="hidden">
            <div id="result-content" class="rounded-lg p-4 text-center"></div>
        </div>
    </div>

    <!-- Today's Scanned List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-list text-green-600"></i> Presensi Hari Ini
            <span id="today-count" class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full"></span>
        </h3>
        <div id="attendance-list" class="space-y-2 max-h-64 overflow-y-auto">
            <p class="text-sm text-gray-500 text-center py-4">Memuat data...</p>
        </div>
    </div>
</div>

<!-- QR Scanner Library -->
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrcodeScanner = null;
let isScanning = false;

function startScanner() {
    if (isScanning) return;
    
    const html5Qrcode = new Html5Qrcode("scanner-container");
    
    html5Qrcode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 200, height: 200 } },
        onScanSuccess,
        onScanFailure
    ).then(() => {
        isScanning = true;
        html5QrcodeScanner = html5Qrcode;
        document.getElementById('btnStart').classList.add('hidden');
        document.getElementById('btnStop').classList.remove('hidden');
    }).catch(err => {
        showResult('error', 'Gagal mengakses kamera: ' + err);
    });
}

function stopScanner() {
    if (html5QrcodeScanner && isScanning) {
        html5QrcodeScanner.stop().then(() => {
            isScanning = false;
            document.getElementById('btnStart').classList.remove('hidden');
            document.getElementById('btnStop').classList.add('hidden');
        });
    }
}

let lastScan = '';
let lastScanTime = 0;

function onScanSuccess(decodedText) {
    // Prevent duplicate scans within 3 seconds
    const now = Date.now();
    if (decodedText === lastScan && (now - lastScanTime) < 3000) return;
    lastScan = decodedText;
    lastScanTime = now;

    // Send to server
    processAttendance(decodedText);
}

function onScanFailure(error) {
    // Silence scan failures (no QR in frame)
}

function processAttendance(qrToken) {
    fetch('<?= BASE_URL ?>modules/wali_kelas/process_scan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= Security::generateCSRFToken() ?>' },
        body: JSON.stringify({ qr_token: qrToken })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showResult('success', `<i class="fas fa-check-circle text-2xl mb-2"></i><br>
                <strong>${data.student_name}</strong><br>
                <span class="text-sm">${data.class_name} | ${data.time}</span><br>
                <span class="mt-1 inline-block px-2 py-0.5 rounded text-xs font-medium ${data.status === 'hadir' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">${data.status_label}</span>`);
            loadTodayAttendance();
            // Play success sound
            new Audio('data:audio/wav;base64,UklGRl9vT19teleWQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ==').play().catch(()=>{});
        } else {
            showResult('error', `<i class="fas fa-times-circle text-2xl mb-2"></i><br>${data.message}`);
        }
    })
    .catch(err => {
        showResult('error', 'Gagal memproses: ' + err.message);
    });
}

function showResult(type, html) {
    const el = document.getElementById('scan-result');
    const content = document.getElementById('result-content');
    el.classList.remove('hidden');
    content.className = `rounded-lg p-4 text-center ${type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`;
    content.innerHTML = html;
    
    setTimeout(() => { el.classList.add('hidden'); }, 4000);
}

function loadTodayAttendance() {
    fetch('<?= BASE_URL ?>modules/wali_kelas/today_attendance.php')
    .then(res => res.json())
    .then(data => {
        const list = document.getElementById('attendance-list');
        document.getElementById('today-count').textContent = data.length + ' siswa';
        
        if (data.length === 0) {
            list.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">Belum ada presensi hari ini.</p>';
            return;
        }
        
        list.innerHTML = data.map(a => `
            <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                <div>
                    <p class="text-sm font-medium text-gray-800">${a.full_name}</p>
                    <p class="text-xs text-gray-500">${a.nis} | ${a.scan_time}</p>
                </div>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium ${a.status === 'hadir' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">${a.status_label}</span>
            </div>
        `).join('');
    });
}

// Load on page ready
document.addEventListener('DOMContentLoaded', loadTodayAttendance);
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
