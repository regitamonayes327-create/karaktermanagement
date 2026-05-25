<?php
/**
 * QR Code Scanner for Attendance
 * SIKAPDIK - Uses Html5QrcodeScanner (built-in UI, more reliable)
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin']);

define('PAGE_TITLE', 'Scan QR Presensi');

$db = Database::getInstance();
$lateThreshold = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'late_threshold_time'") ?: '07:00:00';

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

        <!-- Scanner will render here -->
        <div id="qr-reader" class="mx-auto" style="max-width:400px;"></div>
        
        <p id="scanStatus" class="text-center text-sm text-gray-400 mt-3">Scanner akan aktif otomatis. Izinkan akses kamera jika diminta.</p>
    </div>

    <!-- Result Popup -->
    <div id="resultPopup" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden" style="z-index:9999;">
        <div class="absolute inset-0 bg-black/60" onclick="closePopup()"></div>
        <div id="resultCard" class="relative bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full transition-all duration-300"></div>
    </div>

    <!-- Today's Attendance List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-list text-green-600"></i> Presensi Hari Ini
            </h3>
            <span id="todayCount" class="text-xs bg-blue-100 text-blue-800 px-2.5 py-1 rounded-full font-medium">0</span>
        </div>
        <div id="attendanceList" class="space-y-2 max-h-72 overflow-y-auto">
            <p class="text-sm text-gray-400 text-center py-4">Memuat...</p>
        </div>
    </div>
</div>

<!-- html5-qrcode v2.3.8 -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
const PROCESS_URL = '<?= BASE_URL ?>modules/wali_kelas/process_scan.php';
let lastScannedCode = '';
let lastScanTime = 0;
let scannerPaused = false;

// Use Html5QrcodeScanner - it has its own UI with start/stop button built-in
const scanner = new Html5QrcodeScanner("qr-reader", {
    fps: 10,
    qrbox: { width: 250, height: 250 },
    rememberLastUsedCamera: true,
    supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
}, false); // verbose = false

scanner.render(onScanSuccess, onScanFailure);

function onScanSuccess(decodedText, decodedResult) {
    if (scannerPaused) return;
    
    // Prevent duplicate scans within 3 seconds
    const now = Date.now();
    if (decodedText === lastScannedCode && (now - lastScanTime) < 3000) return;
    lastScannedCode = decodedText;
    lastScanTime = now;
    scannerPaused = true;

    document.getElementById('scanStatus').textContent = 'Memproses...';
    document.getElementById('scanStatus').className = 'text-center text-sm text-blue-600 font-medium mt-3';

    // Send to server
    fetch(PROCESS_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_token: decodedText })
    })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        if (data.success) {
            showPopup('success', data);
            loadTodayAttendance();
            playBeep(true);
        } else {
            showPopup('error', data);
            playBeep(false);
        }
    })
    .catch(err => {
        showPopup('error', { message: 'Koneksi gagal: ' + err.message });
    })
    .finally(() => {
        // Resume scanning after 3 seconds
        setTimeout(() => {
            scannerPaused = false;
            document.getElementById('scanStatus').textContent = 'Scanner aktif. Arahkan ke QR Code berikutnya...';
            document.getElementById('scanStatus').className = 'text-center text-sm text-green-600 font-medium mt-3';
        }, 3000);
    });
}

function onScanFailure(error) {
    // Normal - no QR in view, ignore silently
}

function showPopup(type, data) {
    const popup = document.getElementById('resultPopup');
    const card = document.getElementById('resultCard');
    let html = '';

    if (type === 'success') {
        const sColor = data.status === 'hadir' ? 'green' : 'yellow';
        html = `<div class="text-center">
            <div class="w-20 h-20 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-check-circle text-green-500 text-4xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-1">Presensi Berhasil!</h3>
            <div class="bg-gray-50 rounded-xl p-4 my-4 text-left space-y-2">
                <div class="flex justify-between"><span class="text-sm text-gray-500">Nama</span><span class="text-sm font-bold text-gray-800">${data.student_name}</span></div>
                <div class="flex justify-between"><span class="text-sm text-gray-500">NIS</span><span class="text-sm text-gray-700">${data.nis || '-'}</span></div>
                <div class="flex justify-between"><span class="text-sm text-gray-500">Kelas</span><span class="text-sm text-gray-700">${data.class_name}</span></div>
                <div class="flex justify-between"><span class="text-sm text-gray-500">Jam</span><span class="text-sm text-gray-700">${data.time} WIB</span></div>
                <div class="flex justify-between items-center"><span class="text-sm text-gray-500">Status</span><span class="px-3 py-1 rounded-full text-xs font-bold bg-${sColor}-100 text-${sColor}-800">${data.status_label}</span></div>
            </div>
            <button onclick="closePopup()" class="w-full py-3 bg-green-600 text-white rounded-xl font-medium hover:bg-green-700">OK</button>
        </div>`;
    } else {
        html = `<div class="text-center">
            <div class="w-20 h-20 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                <i class="fas fa-times-circle text-red-500 text-4xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Gagal!</h3>
            <p class="text-sm text-gray-600 mb-6">${data.message || 'Terjadi kesalahan.'}</p>
            <button onclick="closePopup()" class="w-full py-3 bg-gray-600 text-white rounded-xl font-medium hover:bg-gray-700">Tutup</button>
        </div>`;
    }

    card.innerHTML = html;
    popup.classList.remove('hidden');
    setTimeout(() => { if (type === 'success') closePopup(); }, 5000);
}

function closePopup() {
    document.getElementById('resultPopup').classList.add('hidden');
}

function playBeep(success) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.value = success ? 880 : 220;
        osc.type = success ? 'sine' : 'square';
        gain.gain.value = 0.3;
        osc.start(); osc.stop(ctx.currentTime + (success ? 0.15 : 0.4));
    } catch(e) {}
}

function loadTodayAttendance() {
    fetch('<?= BASE_URL ?>modules/wali_kelas/today_attendance.php')
    .then(r => r.json())
    .then(data => {
        document.getElementById('todayCount').textContent = data.length + ' siswa';
        const list = document.getElementById('attendanceList');
        if (!data.length) { list.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">Belum ada presensi.</p>'; return; }
        list.innerHTML = data.map((a, i) => `
            <div class="flex items-center justify-between p-2.5 rounded-lg bg-gray-50 border border-gray-100">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600">${i+1}</span>
                    <div><p class="text-sm font-medium text-gray-800">${a.full_name}</p><p class="text-xs text-gray-400">${a.nis} | ${a.scan_time}</p></div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-medium ${a.status==='hadir'?'bg-green-100 text-green-800':'bg-yellow-100 text-yellow-800'}">${a.status_label}</span>
            </div>`).join('');
    }).catch(() => {});
}

document.addEventListener('DOMContentLoaded', loadTodayAttendance);
</script>

<style>
#qr-reader { border: none !important; }
#qr-reader img[alt="Info icon"] { display: none !important; }
#qr-reader__dashboard_section_csr button { 
    background: #2563eb !important; color: white !important; border: none !important; 
    padding: 10px 20px !important; border-radius: 8px !important; font-weight: 500 !important;
    cursor: pointer !important; margin: 5px !important;
}
#qr-reader__dashboard_section_csr button:hover { background: #1d4ed8 !important; }
#qr-reader__dashboard_section_csr select { 
    border: 1px solid #e5e7eb !important; border-radius: 8px !important; padding: 8px 12px !important;
}
#qr-reader__scan_region video { border-radius: 12px !important; }
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
