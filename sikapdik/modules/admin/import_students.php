<?php
/**
 * Import Students from CSV/Excel
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Import Data Siswa');

$db = Database::getInstance();
$classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");

$importResult = null;

// Handle file upload and import
if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'import' && !empty($_FILES['csv_file']['name'])) {
        $file = $_FILES['csv_file'];
        $classId = (int) post('import_class_id') ?: null;
        
        // Validate file
        $allowedTypes = ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['csv', 'txt'])) {
            setFlash('error', 'Format file harus CSV (.csv). Silakan download template terlebih dahulu.');
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            setFlash('error', 'Ukuran file maksimal 5MB.');
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            setFlash('error', 'Gagal mengupload file. Error code: ' . $file['error']);
        } else {
            // Process CSV
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                setFlash('error', 'Gagal membaca file.');
            } else {
                // Read header row
                $header = fgetcsv($handle, 0, ',');
                if ($header === false) {
                    setFlash('error', 'File kosong atau format tidak valid.');
                    fclose($handle);
                } else {
                    // Normalize header (lowercase, trim)
                    $header = array_map(function($h) {
                        return strtolower(trim(str_replace(["\xEF\xBB\xBF", '"'], '', $h)));
                    }, $header);
                    
                    // Map expected columns
                    $colMap = [
                        'nis' => array_search('nis', $header),
                        'nisn' => array_search('nisn', $header),
                        'full_name' => array_search('nama_lengkap', $header),
                        'gender' => array_search('jenis_kelamin', $header),
                        'birth_place' => array_search('tempat_lahir', $header),
                        'birth_date' => array_search('tanggal_lahir', $header),
                        'address' => array_search('alamat', $header),
                    ];
                    
                    // Validate required columns
                    if ($colMap['nis'] === false || $colMap['full_name'] === false || $colMap['gender'] === false) {
                        setFlash('error', 'Kolom wajib tidak ditemukan. Pastikan file memiliki kolom: nis, nama_lengkap, jenis_kelamin. Gunakan template yang tersedia.');
                        fclose($handle);
                    } else {
                        $imported = 0;
                        $skipped = 0;
                        $errors = [];
                        $rowNum = 1;
                        
                        $db->beginTransaction();
                        
                        try {
                            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                                $rowNum++;
                                
                                // Skip empty rows
                                if (empty(array_filter($row))) continue;
                                
                                // Get values
                                $nis = trim($row[$colMap['nis']] ?? '');
                                $fullName = trim($row[$colMap['full_name']] ?? '');
                                $genderRaw = strtoupper(trim($row[$colMap['gender']] ?? ''));
                                $nisn = ($colMap['nisn'] !== false) ? trim($row[$colMap['nisn']] ?? '') : '';
                                $birthPlace = ($colMap['birth_place'] !== false) ? trim($row[$colMap['birth_place']] ?? '') : '';
                                $birthDateRaw = ($colMap['birth_date'] !== false) ? trim($row[$colMap['birth_date']] ?? '') : '';
                                $address = ($colMap['address'] !== false) ? trim($row[$colMap['address']] ?? '') : '';
                                
                                // Validate required fields
                                if (empty($nis) || empty($fullName)) {
                                    $errors[] = "Baris {$rowNum}: NIS atau Nama kosong, dilewati.";
                                    $skipped++;
                                    continue;
                                }
                                
                                // Parse gender
                                $gender = '';
                                if (in_array($genderRaw, ['L', 'LAKI-LAKI', 'LAKI', 'M', 'MALE'])) {
                                    $gender = 'L';
                                } elseif (in_array($genderRaw, ['P', 'PEREMPUAN', 'F', 'FEMALE', 'W', 'WANITA'])) {
                                    $gender = 'P';
                                } else {
                                    $errors[] = "Baris {$rowNum}: Jenis kelamin tidak valid ({$genderRaw}), dilewati.";
                                    $skipped++;
                                    continue;
                                }
                                
                                // Parse birth date (support: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY)
                                $birthDate = null;
                                if (!empty($birthDateRaw)) {
                                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDateRaw)) {
                                        $birthDate = $birthDateRaw;
                                    } elseif (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $birthDateRaw, $m)) {
                                        $birthDate = "{$m[3]}-{$m[2]}-{$m[1]}";
                                    }
                                }
                                
                                // Check if NIS already exists
                                $existing = $db->fetch("SELECT id FROM students WHERE nis = ?", [$nis]);
                                if ($existing) {
                                    $errors[] = "Baris {$rowNum}: NIS {$nis} sudah ada, dilewati.";
                                    $skipped++;
                                    continue;
                                }
                                
                                // Insert student
                                $db->insert('students', [
                                    'nis' => $nis,
                                    'nisn' => $nisn ?: null,
                                    'full_name' => $fullName,
                                    'gender' => $gender,
                                    'birth_place' => $birthPlace ?: null,
                                    'birth_date' => $birthDate,
                                    'address' => $address ?: null,
                                    'class_id' => $classId,
                                    'qr_token' => Security::generateToken(32),
                                    'qr_generated_at' => date('Y-m-d H:i:s'),
                                    'is_active' => 1
                                ]);
                                $imported++;
                            }
                            
                            $db->commit();
                            fclose($handle);
                            
                            Auth::logActivity('import_students', 'students', "Import massal: {$imported} berhasil, {$skipped} dilewati");
                            
                            $importResult = [
                                'imported' => $imported,
                                'skipped' => $skipped,
                                'errors' => $errors,
                                'total_rows' => $rowNum - 1
                            ];
                            
                            if ($imported > 0) {
                                setFlash('success', "Import berhasil! {$imported} siswa ditambahkan" . ($skipped > 0 ? ", {$skipped} dilewati." : "."));
                            } else {
                                setFlash('error', "Tidak ada data yang berhasil diimport. Periksa format file Anda.");
                            }
                            
                        } catch (Exception $e) {
                            $db->rollback();
                            fclose($handle);
                            setFlash('error', 'Terjadi kesalahan saat import: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Import Data Siswa Massal</h3>
            <p class="text-sm text-gray-500">Upload file CSV untuk menambahkan banyak siswa sekaligus</p>
        </div>
        <a href="<?= BASE_URL ?>modules/admin/students.php" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <!-- Step 1: Download Template -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-file-download text-green-600 text-xl"></i>
            </div>
            <div class="flex-1">
                <h4 class="font-semibold text-gray-800 mb-1">Langkah 1: Download Template</h4>
                <p class="text-sm text-gray-600 mb-3">Download template CSV terlebih dahulu, isi data siswa sesuai format, lalu upload kembali.</p>
                <a href="<?= BASE_URL ?>modules/admin/download_template.php" 
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-medium shadow-sm">
                    <i class="fas fa-download"></i> Download Template CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Step 2: Upload File -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-start gap-4 mb-6">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-file-upload text-blue-600 text-xl"></i>
            </div>
            <div>
                <h4 class="font-semibold text-gray-800 mb-1">Langkah 2: Upload Data Siswa</h4>
                <p class="text-sm text-gray-600">Upload file CSV yang sudah diisi data siswa. Pastikan format sesuai template.</p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="importForm">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="import">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">File CSV *</label>
                    <div class="relative">
                        <input type="file" name="csv_file" accept=".csv,.txt" required id="csvFileInput"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Format: .csv | Maks: 5MB</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Masukkan ke Kelas (opsional)</label>
                    <select name="import_class_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                        <option value="">-- Tidak ditentukan --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>">Kelas <?= $c['grade_level'] ?> - <?= htmlspecialchars($c['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Semua siswa akan dimasukkan ke kelas ini</p>
                </div>
            </div>

            <!-- Preview area -->
            <div id="previewArea" class="hidden mb-4">
                <div class="border border-blue-200 rounded-lg bg-blue-50 p-4">
                    <h5 class="text-sm font-medium text-blue-800 mb-2"><i class="fas fa-eye"></i> Preview Data (5 baris pertama)</h5>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs" id="previewTable">
                            <thead class="bg-blue-100"><tr id="previewHeader"></tr></thead>
                            <tbody id="previewBody"></tbody>
                        </table>
                    </div>
                    <p class="text-xs text-blue-600 mt-2" id="previewCount"></p>
                </div>
            </div>

            <button type="submit" id="importBtn"
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2 font-medium shadow-sm">
                <i class="fas fa-upload"></i> Import Data Siswa
            </button>
        </form>
    </div>

    <!-- Import Result -->
    <?php if ($importResult): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-purple-600"></i> Hasil Import
        </h4>
        
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="text-center p-4 bg-green-50 rounded-lg border border-green-100">
                <p class="text-3xl font-bold text-green-600"><?= $importResult['imported'] ?></p>
                <p class="text-xs text-gray-600 mt-1">Berhasil Ditambahkan</p>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg border border-yellow-100">
                <p class="text-3xl font-bold text-yellow-600"><?= $importResult['skipped'] ?></p>
                <p class="text-xs text-gray-600 mt-1">Dilewati</p>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg border border-blue-100">
                <p class="text-3xl font-bold text-blue-600"><?= $importResult['total_rows'] ?></p>
                <p class="text-xs text-gray-600 mt-1">Total Baris</p>
            </div>
        </div>

        <?php if (!empty($importResult['errors'])): ?>
        <div class="mt-4">
            <h5 class="text-sm font-medium text-gray-700 mb-2">Detail Baris Dilewati:</h5>
            <div class="max-h-40 overflow-y-auto bg-gray-50 rounded-lg p-3 border">
                <?php foreach ($importResult['errors'] as $err): ?>
                <p class="text-xs text-gray-600 py-0.5"><i class="fas fa-exclamation-circle text-yellow-500"></i> <?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Guide / Info -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-info-circle text-blue-600"></i> Panduan Import
        </h4>
        
        <div class="space-y-3 text-sm text-gray-600">
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">1</span>
                <p>Download template CSV dan buka menggunakan <strong>Microsoft Excel</strong>, <strong>Google Sheets</strong>, atau <strong>LibreOffice Calc</strong>.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">2</span>
                <p>Isi data siswa sesuai kolom. <strong>Kolom wajib: NIS, Nama Lengkap, Jenis Kelamin</strong>. Kolom lain boleh kosong.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">3</span>
                <p>Jenis kelamin isi dengan <strong>L</strong> (Laki-laki) atau <strong>P</strong> (Perempuan).</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">4</span>
                <p>Format tanggal lahir: <strong>DD/MM/YYYY</strong> (contoh: 15/03/2016) atau <strong>YYYY-MM-DD</strong>.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">5</span>
                <p>Simpan file dalam format <strong>CSV (Comma Separated Values)</strong>, lalu upload di form di atas.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-yellow-100 rounded-full flex items-center justify-center text-xs font-bold text-yellow-600 flex-shrink-0 mt-0.5">!</span>
                <p class="text-yellow-700">Siswa dengan <strong>NIS yang sudah ada</strong> di database akan <strong>otomatis dilewati</strong> (tidak duplikat).</p>
            </div>
        </div>

        <!-- Format table -->
        <div class="mt-6 overflow-x-auto">
            <h5 class="text-sm font-medium text-gray-700 mb-2">Format Kolom Template:</h5>
            <table class="w-full text-xs border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Kolom</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Wajib</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Format</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Contoh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="px-3 py-2 font-medium">nis</td><td class="px-3 py-2"><span class="text-red-500 font-bold">Ya</span></td><td class="px-3 py-2">Angka/teks unik</td><td class="px-3 py-2">001</td></tr>
                    <tr><td class="px-3 py-2 font-medium">nisn</td><td class="px-3 py-2 text-gray-400">Tidak</td><td class="px-3 py-2">10 digit angka</td><td class="px-3 py-2">0012345678</td></tr>
                    <tr><td class="px-3 py-2 font-medium">nama_lengkap</td><td class="px-3 py-2"><span class="text-red-500 font-bold">Ya</span></td><td class="px-3 py-2">Teks</td><td class="px-3 py-2">Ahmad Fauzan</td></tr>
                    <tr><td class="px-3 py-2 font-medium">jenis_kelamin</td><td class="px-3 py-2"><span class="text-red-500 font-bold">Ya</span></td><td class="px-3 py-2">L atau P</td><td class="px-3 py-2">L</td></tr>
                    <tr><td class="px-3 py-2 font-medium">tempat_lahir</td><td class="px-3 py-2 text-gray-400">Tidak</td><td class="px-3 py-2">Teks</td><td class="px-3 py-2">Lumajang</td></tr>
                    <tr><td class="px-3 py-2 font-medium">tanggal_lahir</td><td class="px-3 py-2 text-gray-400">Tidak</td><td class="px-3 py-2">DD/MM/YYYY</td><td class="px-3 py-2">15/03/2016</td></tr>
                    <tr><td class="px-3 py-2 font-medium">alamat</td><td class="px-3 py-2 text-gray-400">Tidak</td><td class="px-3 py-2">Teks</td><td class="px-3 py-2">Desa Jatigunung RT01/RW02</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// CSV Preview
document.getElementById('csvFileInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(event) {
        const csv = event.target.result;
        const lines = csv.split('\n').filter(l => l.trim());
        
        if (lines.length < 2) {
            document.getElementById('previewArea').classList.add('hidden');
            return;
        }
        
        // Parse header
        const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
        let headerHtml = '';
        headers.forEach(h => { headerHtml += `<th class="px-2 py-1 text-left font-medium text-blue-700 whitespace-nowrap">${h}</th>`; });
        document.getElementById('previewHeader').innerHTML = headerHtml;
        
        // Parse first 5 data rows
        let bodyHtml = '';
        const maxRows = Math.min(lines.length - 1, 5);
        for (let i = 1; i <= maxRows; i++) {
            const cols = lines[i].split(',').map(c => c.trim().replace(/"/g, ''));
            bodyHtml += '<tr class="border-t border-blue-100">';
            cols.forEach(c => { bodyHtml += `<td class="px-2 py-1 text-gray-700 whitespace-nowrap">${c || '-'}</td>`; });
            bodyHtml += '</tr>';
        }
        document.getElementById('previewBody').innerHTML = bodyHtml;
        document.getElementById('previewCount').textContent = `Menampilkan ${maxRows} dari ${lines.length - 1} baris data`;
        document.getElementById('previewArea').classList.remove('hidden');
    };
    reader.readAsText(file);
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
