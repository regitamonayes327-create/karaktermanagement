<?php
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['wali_kelas', 'admin', 'guru_mapel']);
define('PAGE_TITLE', 'Scan QR Presensi');
$db = Database::getInstance();
$lateThreshold = $db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'late_threshold_time'") ?: '07:00:00';
include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-2xl mx-auto">
    <?php if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on'): ?>
    <div class="mb-4 p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">
        <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian:</strong> Scanner QR memerlukan HTTPS. Jika kamera tidak muncul, pastikan mengakses via https://
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="text-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-qrcode text-blue-600"></i> Scanner Presensi QR Code</h3>
            <p class="text-sm text-gray-500">Batas terlambat: <?= date('H:i', strtotime($lateThreshold)) ?> WIB</p>
        </div>

        <div id="qr-reader" style="width:100%;max-width:500px;margin:0 auto;"></div>
        
        <div id="scanResult" class="mt-4 hidden"></div>

        <!-- Manual input fallback -->
        <details class="mt-4 border-t border-gray-100 pt-4">
            <summary class="text-sm text-gray-500 cursor-pointer hover:text-gray-700">Kamera tidak berfungsi? Input token manual</summary>
            <div class="mt-3 flex gap-2">
                <input type="text" id="manualToken" placeholder="Paste token QR siswa di sini" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <button onclick="processToken(document.getElementById('manualToken').value)" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Proses</button>
            </div>
        </details>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-list text-green-600"></i> Presensi Hari Ini</h3>
            <span id="todayCount" class="text-xs bg-blue-100 text-blue-800 px-2.5 py-1 rounded-full font-medium">0</span>
        </div>
        <div id="attendanceList" class="space-y-2 max-h-72 overflow-y-auto">
            <p class="text-sm text-gray-400 text-center py-4">Memuat...</p>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const PROCESS_URL = '<?= BASE_URL ?>modules/wali_kelas/process_scan.php';
let lastCode = '';
let lastTime = 0;
let scannerPaused = false;

function onScanSuccess(decodedText, decodedResult) {
    if (scannerPaused) return;
    const now = Date.now();
    if (decodedText === lastCode && (now - lastTime) < 4000) return;
    lastCode = decodedText;
    lastTime = now;
    scannerPaused = true;
    processToken(decodedText);
}

function processToken(token) {
    if (!token || !token.trim()) return;
    token = token.trim();
    
    showResult('loading', 'Memproses...');
    
    fetch(PROCESS_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({qr_token: token})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showResult('success', `
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center"><i class="fas fa-check text-green-600 text-xl"></i></div>
                    <div class="text-left">
                        <p class="font-bold text-gray-800">${data.student_name}</p>
                        <p class="text-sm text-gray-600">${data.class_name} | ${data.time} WIB</p>
                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-bold ${data.status==='hadir'?'bg-green-100 text-green-800':'bg-yellow-100 text-yellow-800'}">${data.status_label}</span>
                    </div>
                </div>
            `);
            playSound(true);
            loadTodayAttendance();
        } else {
            showResult('error', `<div class="flex items-center gap-3"><div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center"><i class="fas fa-times text-red-600 text-xl"></i></div><div class="text-left"><p class="font-bold text-gray-800">Gagal</p><p class="text-sm text-gray-600">${data.message}</p></div></div>`);
            playSound(false);
        }
    })
    .catch(err => {
        showResult('error', `<p class="text-red-600">Error: ${err.message}</p>`);
    })
    .finally(() => {
        setTimeout(() => { scannerPaused = false; }, 3000);
    });
}

function showResult(type, html) {
    const el = document.getElementById('scanResult');
    el.classList.remove('hidden');
    const colors = {loading:'bg-blue-50 border-blue-200', success:'bg-green-50 border-green-200', error:'bg-red-50 border-red-200'};
    el.className = `mt-4 p-4 rounded-xl border ${colors[type] || ''}`;
    el.innerHTML = html;
    if (type !== 'loading') setTimeout(() => el.classList.add('hidden'), 5000);
}

function playSound(success) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.connect(g); g.connect(ctx.destination);
        o.frequency.value = success ? 880 : 220;
        o.type = success ? 'sine' : 'square';
        g.gain.value = 0.3;
        o.start(); o.stop(ctx.currentTime + 0.2);
    } catch(e) {}
}

function loadTodayAttendance() {
    fetch('<?= BASE_URL ?>modules/wali_kelas/today_attendance.php')
    .then(r => r.json())
    .then(data => {
        document.getElementById('todayCount').textContent = data.length;
        const list = document.getElementById('attendanceList');
        if (!data.length) { list.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">Belum ada presensi.</p>'; return; }
        list.innerHTML = data.map((a,i) => `<div class="flex items-center justify-between p-2 rounded-lg bg-gray-50"><div class="flex items-center gap-2"><span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600">${i+1}</span><div><p class="text-sm font-medium text-gray-800">${a.full_name}</p><p class="text-xs text-gray-400">${a.nis} | ${a.scan_time}</p></div></div><span class="px-2 py-0.5 rounded-full text-xs font-medium ${a.status==='hadir'?'bg-green-100 text-green-800':'bg-yellow-100 text-yellow-800'}">${a.status_label}</span></div>`).join('');
    }).catch(()=>{});
}

// Initialize scanner
document.addEventListener('DOMContentLoaded', function() {
    loadTodayAttendance();
    
    try {
        const scanner = new Html5QrcodeScanner("qr-reader", {
            fps: 10,
            qrbox: {width: 250, height: 250},
            rememberLastUsedCamera: true,
            showTorchButtonIfSupported: true
        }, false);
        scanner.render(onScanSuccess, function(){});
    } catch(e) {
        document.getElementById('qr-reader').innerHTML = '<div class="p-6 text-center bg-red-50 rounded-xl"><i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i><p class="text-sm text-red-700">Scanner tidak dapat dimuat: ' + e.message + '</p><p class="text-xs text-red-500 mt-1">Gunakan input manual di bawah.</p></div>';
    }
});
</script>

<style>
#qr-reader { border: none !important; }
#qr-reader video { border-radius: 12px !important; }
#qr-reader__dashboard_section_csr button { background:#2563eb!important;color:white!important;border:none!important;padding:8px 16px!important;border-radius:8px!important;font-size:13px!important;cursor:pointer!important;margin:4px!important; }
#qr-reader__dashboard_section_csr select { border:1px solid #e5e7eb!important;border-radius:8px!important;padding:6px 10px!important;font-size:13px!important; }
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
