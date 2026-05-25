<?php
/**
 * QR Code Scanner for Attendance
 * SIKAPDIK - Using html5-qrcode v2.3.8 (reliable)
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin']);

define('PAGE_TITLE', 'Scan QR Presensi');

$db = Database::getInstance();
$lateThreshold = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'late_threshold_time'") ?: '07:00:00';
$csrfToken = Security::generateCSRFToken();

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-2xl mx-auto">
    <!-- Scanner Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="text-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center justify-center gap-2">
                <i class="fas fa-qrcode text-blue-600"></i> Scanner Presensi QR Code
            </h3>
            <p class="text-sm text-gray-500 mt-1">Arahkan kamera ke QR Code siswa</p>
            <p class="text-xs text-gray-400">Batas terlambat: <?= date('H:i', strtotime($lateThreshold)) ?> WIB</p>
        </div>

        <!-- Camera View -->
        <div class="relative mb-4">
            <div id="qr-reader" class="w-full max-w-sm mx-auto rounded-xl overflow-hidden border-2 border-gray-200"></div>
        </div>

        <!-- Controls -->
        <div class="flex justify-center gap-3 mb-4">
            <button onclick="startScanner()" id="btnStart" class="px-5 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition flex items-center gap-2">
                <i class="fas fa-camera"></i> Mulai Scan
            </button>
            <button onclick="stopScanner()" id="btnStop" class="px-5 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition flex items-center gap-2 hidden">
                <i class="fas fa-stop"></i> Berhenti
            </button>
        </div>

        <p id="scanStatus" class="text-center text-sm text-gray-400">Klik "Mulai Scan" untuk mengaktifkan kamera</p>
    </div>

    <!-- Result Popup (hidden by default) -->
    <div id="resultPopup" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
        <div class="absolute inset-0 bg-black/50" onclick="closePopup()"></div>
        <div id="resultCard" class="relative bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full transform scale-95 opacity-0 transition-all duration-300">
            <!-- Content filled by JS -->
        </div>
    </div>

    <!-- Today's Attendance List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-list text-green-600"></i> Presensi Hari Ini
            </h3>
            <span id="todayCount" class="text-xs bg-blue-100 text-blue-800 px-2.5 py-1 rounded-full font-medium">0 siswa</span>
        </div>
        <div id="attendanceList" class="space-y-2 max-h-72 overflow-y-auto">
            <p class="text-sm text-gray-400 text-center py-4">Memuat...</p>
        </div>
    </div>
</div>

<!-- html5-qrcode library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
let html5QrCode = null;
let isScanning = false;
let lastScannedCode = '';
let lastScanTime = 0;
const SCAN_COOLDOWN = 3000; // 3 seconds between same scan
const PROCESS_URL = '<?= BASE_URL ?>modules/wali_kelas/process_scan.php';
const CSRF_TOKEN = '<?= $csrfToken ?>';


function startScanner() {
    if (isScanning) return;

    html5QrCode = new Html5Qrcode("qr-reader");
    
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };

    html5QrCode.start(
        { facingMode: "environment" },
        config,
        onScanSuccess,
        onScanError
    ).then(() => {
        isScanning = true;
        document.getElementById('btnStart').classList.add('hidden');
        document.getElementById('btnStop').classList.remove('hidden');
        document.getElementById('scanStatus').textContent = 'Kamera aktif. Arahkan ke QR Code siswa...';
        document.getElementById('scanStatus').className = 'text-center text-sm text-green-600 font-medium';
    }).catch(err => {
        showPopup('error', 'Gagal Mengakses Kamera', 
            'Pastikan browser memiliki izin akses kamera.<br><small class="text-gray-500">' + err + '</small>', 
            null);
    });
}

function stopScanner() {
    if (html5QrCode && isScanning) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            isScanning = false;
            document.getElementById('btnStart').classList.remove('hidden');
            document.getElementById('btnStop').classList.add('hidden');
            document.getElementById('scanStatus').textContent = 'Scanner dihentikan.';
            document.getElementById('scanStatus').className = 'text-center text-sm text-gray-400';
        }).catch(err => console.error('Stop error:', err));
    }
}

function onScanSuccess(decodedText, decodedResult) {
    // Prevent duplicate scans
    const now = Date.now();
    if (decodedText === lastScannedCode && (now - lastScanTime) < SCAN_COOLDOWN) {
        return;
    }
    lastScannedCode = decodedText;
    lastScanTime = now;

    // Visual feedback
    document.getElementById('scanStatus').textContent = 'Memproses QR Code...';
    document.getElementById('scanStatus').className = 'text-center text-sm text-blue-600 font-medium animate-pulse';

    // Send to server
    processQRCode(decodedText);
}

function onScanError(errorMessage) {
    // Silently ignore - no QR in frame is normal
}

function processQRCode(qrToken) {
    fetch(PROCESS_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN
        },
        body: JSON.stringify({ qr_token: qrToken })
    })
    .then(response => {
        if (!response.ok) throw new Error('Server error: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showPopup('success', 'Presensi Berhasil!', null, data);
            loadTodayAttendance();
            // Play success beep
            playBeep(true);
        } else {
            showPopup('error', 'Presensi Gagal', data.message, null);
            playBeep(false);
        }
        // Reset status
        setTimeout(() => {
            if (isScanning) {
                document.getElementById('scanStatus').textContent = 'Kamera aktif. Arahkan ke QR Code siswa...';
                document.getElementById('scanStatus').className = 'text-center text-sm text-green-600 font-medium';
            }
        }, 2000);
    })
    .catch(err => {
        showPopup('error', 'Gagal Memproses', 'Koneksi ke server gagal. Silakan coba lagi.<br><small>' + err.message + '</small>', null);
    });
}


function showPopup(type, title, message, data) {
    const popup = document.getElementById('resultPopup');
    const card = document.getElementById('resultCard');
    
    let html = '';
    
    if (type === 'success' && data) {
        const statusColor = data.status === 'hadir' ? 'green' : 'yellow';
        html = `
            <div class="text-center">
                <div class="w-20 h-20 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-500 text-4xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">${title}</h3>
                <div class="bg-gray-50 rounded-xl p-4 mb-4 text-left space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Nama</span>
                        <span class="text-sm font-semibold text-gray-800">${data.student_name}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Kelas</span>
                        <span class="text-sm font-medium text-gray-700">${data.class_name}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Jam Scan</span>
                        <span class="text-sm font-medium text-gray-700">${data.time} WIB</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Status</span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-${statusColor}-100 text-${statusColor}-800">${data.status_label}</span>
                    </div>
                </div>
                <button onclick="closePopup()" class="w-full py-3 bg-green-600 text-white rounded-xl font-medium hover:bg-green-700 transition">
                    <i class="fas fa-check"></i> OK, Lanjut Scan
                </button>
            </div>
        `;
    } else {
        html = `
            <div class="text-center">
                <div class="w-20 h-20 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-times-circle text-red-500 text-4xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">${title}</h3>
                <p class="text-sm text-gray-600 mb-6">${message || 'Terjadi kesalahan yang tidak diketahui.'}</p>
                <button onclick="closePopup()" class="w-full py-3 bg-gray-600 text-white rounded-xl font-medium hover:bg-gray-700 transition">
                    <i class="fas fa-redo"></i> Coba Lagi
                </button>
            </div>
        `;
    }
    
    card.innerHTML = html;
    popup.classList.remove('hidden');
    
    // Animate in
    setTimeout(() => {
        card.classList.remove('scale-95', 'opacity-0');
        card.classList.add('scale-100', 'opacity-100');
    }, 10);

    // Auto close success after 4 seconds
    if (type === 'success') {
        setTimeout(() => closePopup(), 4000);
    }
}

function closePopup() {
    const popup = document.getElementById('resultPopup');
    const card = document.getElementById('resultCard');
    card.classList.remove('scale-100', 'opacity-100');
    card.classList.add('scale-95', 'opacity-0');
    setTimeout(() => popup.classList.add('hidden'), 200);
}

function playBeep(success) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = success ? 800 : 300;
        osc.type = success ? 'sine' : 'square';
        gain.gain.value = 0.3;
        osc.start();
        osc.stop(ctx.currentTime + (success ? 0.15 : 0.3));
    } catch(e) {}
}

function loadTodayAttendance() {
    fetch('<?= BASE_URL ?>modules/wali_kelas/today_attendance.php')
    .then(r => r.json())
    .then(data => {
        document.getElementById('todayCount').textContent = data.length + ' siswa';
        const list = document.getElementById('attendanceList');
        
        if (data.length === 0) {
            list.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">Belum ada presensi hari ini.</p>';
            return;
        }
        
        list.innerHTML = data.map((a, i) => `
            <div class="flex items-center justify-between p-2.5 rounded-lg bg-gray-50 border border-gray-100">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600">${i + 1}</span>
                    <div>
                        <p class="text-sm font-medium text-gray-800">${a.full_name}</p>
                        <p class="text-xs text-gray-400">${a.nis} | ${a.scan_time}</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-medium ${a.status === 'hadir' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">${a.status_label}</span>
            </div>
        `).join('');
    })
    .catch(() => {});
}

// Load attendance on page ready
document.addEventListener('DOMContentLoaded', loadTodayAttendance);
</script>

<style>
#qr-reader video { border-radius: 12px; }
#qr-reader { min-height: 300px; }
#resultPopup .animate-in { animation: popIn 0.3s ease; }
@keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
