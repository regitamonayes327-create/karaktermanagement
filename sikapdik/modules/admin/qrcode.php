<?php
/**
 * QR Code Generation
 * SIKAPDIK
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Generate QR Code');

$db = Database::getInstance();

// Handle QR regeneration
if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'generate_all') {
        $students = $db->fetchAll("SELECT id FROM students WHERE is_active = 1 AND (qr_token IS NULL OR qr_token = '')");
        $count = 0;
        foreach ($students as $s) {
            $token = Security::generateToken(32);
            $db->update('students', ['qr_token' => $token, 'qr_generated_at' => date('Y-m-d H:i:s')], 'id = ?', [$s['id']]);
            $count++;
        }
        Auth::logActivity('generate_qr_batch', 'qrcode', "Generate QR Code untuk {$count} siswa");
        setFlash('success', "QR Code berhasil digenerate untuk {$count} siswa.");
    } elseif ($formAction === 'regenerate') {
        $studentId = (int) post('student_id');
        $token = Security::generateToken(32);
        $db->update('students', ['qr_token' => $token, 'qr_generated_at' => date('Y-m-d H:i:s')], 'id = ?', [$studentId]);
        Auth::logActivity('regenerate_qr', 'qrcode', "Regenerate QR untuk siswa ID: {$studentId}");
        setFlash('success', 'QR Code berhasil digenerate ulang.');
    }
    redirect('modules/admin/qrcode.php');
}

$classFilter = get('class_id');
$classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");

$where = "s.is_active = 1";
$params = [];
if (!empty($classFilter)) {
    $where .= " AND s.class_id = ?";
    $params[] = $classFilter;
}
$students = $db->fetchAll("SELECT s.*, c.class_name, c.grade_level FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE {$where} ORDER BY c.grade_level, c.class_name, s.full_name", $params);

$noQrCount = $db->count('students', "is_active = 1 AND (qr_token IS NULL OR qr_token = '')");

include __DIR__ . '/../../templates/header.php';
?>

<!-- Actions -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">QR Code Siswa</h3>
            <p class="text-sm text-gray-500">
                <?php if ($noQrCount > 0): ?>
                <span class="text-orange-600 font-medium"><?= $noQrCount ?> siswa belum memiliki QR Code</span>
                <?php else: ?>
                Semua siswa sudah memiliki QR Code
                <?php endif; ?>
            </p>
        </div>
        <?php if ($noQrCount > 0): ?>
        <form method="POST" onsubmit="return confirm('Generate QR untuk semua siswa yang belum memiliki?')">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="generate_all">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                <i class="fas fa-magic"></i> Generate Semua
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Filter -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex gap-3 items-end">
        <div class="flex-1">
            <select name="class_id" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option value="">Semua Kelas</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>>Kelas <?= $c['grade_level'] ?> - <?= htmlspecialchars($c['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm"><i class="fas fa-filter"></i> Filter</button>
        <?php if (!empty($classFilter)): ?>
        <button type="button" onclick="printQRCards()" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm"><i class="fas fa-print"></i> Cetak Kartu QR</button>
        <?php endif; ?>
    </form>
</div>

<!-- QR Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="qrCards">
    <?php foreach ($students as $s): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center qr-card">
        <div class="mb-2">
            <?php if ($s['qr_token']): ?>
            <div class="w-32 h-32 mx-auto bg-white border-2 border-gray-200 rounded-lg flex items-center justify-center" id="qr-<?= $s['id'] ?>">
                <!-- QR Code rendered via JS -->
                <canvas id="canvas-<?= $s['id'] ?>"></canvas>
            </div>
            <?php else: ?>
            <div class="w-32 h-32 mx-auto bg-gray-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-qrcode text-gray-300 text-3xl"></i>
            </div>
            <?php endif; ?>
        </div>
        <p class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($s['full_name']) ?></p>
        <p class="text-xs text-gray-500"><?= $s['nis'] ?> | <?= $s['class_name'] ? 'Kelas ' . $s['grade_level'] . '-' . $s['class_name'] : '-' ?></p>
        
        <div class="mt-2">
            <form method="POST" class="inline">
                <?= Security::csrfField() ?>
                <input type="hidden" name="form_action" value="regenerate">
                <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                <button type="submit" class="text-xs text-blue-600 hover:text-blue-800" onclick="return confirm('Regenerate QR untuk siswa ini?')">
                    <i class="fas fa-sync-alt"></i> Regenerate
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($students)): ?>
<div class="text-center py-8 text-gray-500">Tidak ada siswa untuk ditampilkan.</div>
<?php endif; ?>

<!-- QR Code JS library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($students as $s): ?>
    <?php if ($s['qr_token']): ?>
    QRCode.toCanvas(document.getElementById('canvas-<?= $s['id'] ?>'), '<?= $s['qr_token'] ?>', {
        width: 120,
        margin: 1,
        color: { dark: '#1e40af', light: '#ffffff' }
    });
    <?php endif; ?>
    <?php endforeach; ?>
});

function printQRCards() {
    window.print();
}
</script>

<style>
@media print {
    .sidebar, header, nav, form, button, .no-print { display: none !important; }
    .qr-card { break-inside: avoid; border: 1px solid #ddd; }
    #qrCards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
