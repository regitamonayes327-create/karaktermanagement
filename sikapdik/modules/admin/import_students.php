<?php
/**
 * Import Students from XLSX/CSV
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/SimpleXLSXReader.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Import Data Siswa');

$db = Database::getInstance();
$classes = $db->fetchAll("SELECT id, class_name, grade_level FROM classes WHERE is_active = 1 ORDER BY grade_level, class_name");

$importResult = null;

// Handle file upload and import
if (isPost() && Security::validateCSRF()) {
    $formAction = post('form_action');
    
    if ($formAction === 'import' && !empty($_FILES['import_file']['name'])) {
        $file = $_FILES['import_file'];
        $classId = (int) post('import_class_id') ?: null;
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['xlsx', 'csv', 'txt'])) {
            setFlash('error', 'Format file harus .xlsx atau .csv. Silakan download template terlebih dahulu.');
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            setFlash('error', 'Ukuran file maksimal 5MB.');
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            setFlash('error', 'Gagal mengupload file. Silakan coba lagi.');
        } else {
            $rows = [];
            $header = [];
            $parseError = '';

            // Parse file based on extension
            if ($extension === 'xlsx') {
                $reader = new SimpleXLSXReader();
                if ($reader->open($file['tmp_name'])) {
                    $allRows = $reader->getRows();
                    
                    // Find the header row (look for row containing 'nis' or 'NIS')
                    $headerRowIdx = -1;
                    foreach ($allRows as $idx => $row) {
                        $rowLower = array_map(function($v) { return strtolower(trim(str_replace('*', '', $v))); }, $row);
                        if (in_array('nis', $rowLower) && (in_array('nama_lengkap', $rowLower) || in_array('nama lengkap', $rowLower))) {
                            $headerRowIdx = $idx;
                            break;
                        }
                    }
                    
                    if ($headerRowIdx === -1) {
                        $parseError = 'Header kolom tidak ditemukan. Pastikan ada baris dengan kolom: NIS, NAMA_LENGKAP, JENIS_KELAMIN.';
                    } else {
                        $header = array_map(function($v) {
                            return strtolower(trim(str_replace(['*', ' '], ['', '_'], $v)));
                        }, $allRows[$headerRowIdx]);
                        
                        // Get data rows after header (skip sub-header if exists)
                        $startRow = $headerRowIdx + 1;
                        // Check if next row is sub-header (contains hints like "Wajib" or "Opsional" or starts with "(")
                        if (isset($allRows[$startRow])) {
                            $firstCell = trim($allRows[$startRow][1] ?? '');
                            if (strpos($firstCell, '(') === 0 || strpos(strtolower($firstCell), 'wajib') !== false) {
                                $startRow++;
                            }
                        }
                        
                        for ($i = $startRow; $i < count($allRows); $i++) {
                            $rows[] = $allRows[$i];
                        }
                    }
                } else {
                    $parseError = $reader->getError();
                }
            } else {
                // CSV
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle === false) {
                    $parseError = 'Gagal membaca file.';
                } else {
                    $headerRaw = fgetcsv($handle, 0, ',');
                    if ($headerRaw === false) {
                        $parseError = 'File kosong atau format tidak valid.';
                    } else {
                        $header = array_map(function($h) {
                            return strtolower(trim(str_replace(["\xEF\xBB\xBF", '"', '*', ' '], ['', '', '', '_'], $h)));
                        }, $headerRaw);
                        while (($row = fgetcsv($handle, 0, ',')) !== false) {
                            $rows[] = $row;
                        }
                    }
                    fclose($handle);
                }
            }

            if (!empty($parseError)) {
                setFlash('error', $parseError);
            } elseif (empty($header)) {
                setFlash('error', 'File tidak memiliki header yang valid.');
            } else {
                // Map columns
                $colMap = [
                    'nis' => $this_findCol($header, ['nis']),
                    'nisn' => $this_findCol($header, ['nisn']),
                    'full_name' => $this_findCol($header, ['nama_lengkap', 'nama']),
                    'gender' => $this_findCol($header, ['jenis_kelamin', 'jk', 'gender']),
                    'birth_place' => $this_findCol($header, ['tempat_lahir', 'ttl']),
                    'birth_date' => $this_findCol($header, ['tanggal_lahir', 'tgl_lahir']),
                    'address' => $this_findCol($header, ['alamat', 'address']),
                ];

                if ($colMap['nis'] === false || $colMap['full_name'] === false || $colMap['gender'] === false) {
                    setFlash('error', 'Kolom wajib tidak ditemukan (NIS, NAMA_LENGKAP, JENIS_KELAMIN). Gunakan template yang tersedia.');
                } else {
                    $imported = 0;
                    $skipped = 0;
                    $errors = [];
                    $rowNum = 0;

                    $db->beginTransaction();
                    
                    try {
                        foreach ($rows as $row) {
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

                            // Skip rows that look like instructions or empty
                            if (empty($nis) || empty($fullName) || is_numeric($nis) === false && strlen($nis) > 20) {
                                // Allow alphanumeric NIS
                                if (empty($nis) || empty($fullName)) {
                                    if (!empty($nis) || !empty($fullName)) {
                                        $errors[] = "Baris {$rowNum}: NIS atau Nama kosong, dilewati.";
                                        $skipped++;
                                    }
                                    continue;
                                }
                            }

                            // Parse gender
                            $gender = '';
                            if (in_array($genderRaw, ['L', 'LAKI-LAKI', 'LAKI', 'M', 'MALE'])) {
                                $gender = 'L';
                            } elseif (in_array($genderRaw, ['P', 'PEREMPUAN', 'F', 'FEMALE', 'W', 'WANITA'])) {
                                $gender = 'P';
                            } else {
                                $errors[] = "Baris {$rowNum}: Jenis kelamin '{$genderRaw}' tidak valid (harus L/P), dilewati.";
                                $skipped++;
                                continue;
                            }

                            // Parse birth date
                            $birthDate = null;
                            if (!empty($birthDateRaw)) {
                                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDateRaw)) {
                                    $birthDate = $birthDateRaw;
                                } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $birthDateRaw, $m)) {
                                    $birthDate = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                                }
                            }

                            // Check duplicate NIS
                            $existing = $db->fetch("SELECT id FROM students WHERE nis = ?", [$nis]);
                            if ($existing) {
                                $errors[] = "Baris {$rowNum}: NIS '{$nis}' sudah ada di database, dilewati.";
                                $skipped++;
                                continue;
                            }

                            // Insert
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
                        Auth::logActivity('import_students', 'students', "Import massal: {$imported} berhasil, {$skipped} dilewati");
                        
                        $importResult = [
                            'imported' => $imported,
                            'skipped' => $skipped,
                            'errors' => $errors,
                            'total_rows' => $rowNum
                        ];

                        if ($imported > 0) {
                            setFlash('success', "Import berhasil! <strong>{$imported} siswa</strong> ditambahkan" . ($skipped > 0 ? ", {$skipped} dilewati." : "."));
                        } else {
                            setFlash('error', "Tidak ada data yang berhasil diimport. Periksa format file Anda.");
                        }
                    } catch (Exception $e) {
                        $db->rollback();
                        setFlash('error', 'Terjadi kesalahan saat import: ' . $e->getMessage());
                    }
                }
            }
        }
    }
}

/**
 * Find column index by possible names
 */
function this_findCol($header, $possibleNames) {
    foreach ($possibleNames as $name) {
        $idx = array_search($name, $header);
        if ($idx !== false) return $idx;
    }
    return false;
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Import Data Siswa Massal</h3>
            <p class="text-sm text-gray-500">Upload file Excel (.xlsx) untuk menambahkan banyak siswa sekaligus</p>
        </div>
        <a href="<?= BASE_URL ?>modules/admin/students.php" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <!-- Step 1: Download Template -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-file-excel text-green-600 text-xl"></i>
            </div>
            <div class="flex-1">
                <h4 class="font-semibold text-gray-800 mb-1">Langkah 1: Download Template Excel</h4>
                <p class="text-sm text-gray-600 mb-3">Download template .xlsx yang sudah rapi, isi data siswa sesuai format, lalu upload kembali.</p>
                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>modules/admin/download_template.php" 
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-medium shadow-sm">
                        <i class="fas fa-download"></i> Download Template (.xlsx)
                    </a>
                    <span class="text-xs text-gray-400">File Excel dengan format dan contoh data</span>
                </div>
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
                <p class="text-sm text-gray-600">Upload file .xlsx atau .csv yang sudah diisi data siswa.</p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="importForm">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_action" value="import">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">File Excel/CSV *</label>
                    <div class="relative">
                        <input type="file" name="import_file" accept=".xlsx,.csv,.txt" required id="importFileInput"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm file:mr-4 file:py-1.5 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Format: .xlsx (rekomendasi) atau .csv | Maks: 5MB</p>
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
            <div class="text-center p-4 bg-green-50 rounded-xl border border-green-100">
                <p class="text-3xl font-bold text-green-600"><?= $importResult['imported'] ?></p>
                <p class="text-xs text-gray-600 mt-1">Berhasil Ditambahkan</p>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-xl border border-yellow-100">
                <p class="text-3xl font-bold text-yellow-600"><?= $importResult['skipped'] ?></p>
                <p class="text-xs text-gray-600 mt-1">Dilewati</p>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-xl border border-blue-100">
                <p class="text-3xl font-bold text-blue-600"><?= $importResult['total_rows'] ?></p>
                <p class="text-xs text-gray-600 mt-1">Total Baris Diproses</p>
            </div>
        </div>

        <?php if (!empty($importResult['errors'])): ?>
        <div class="mt-4">
            <h5 class="text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-yellow-500"></i> Detail Baris Dilewati:
            </h5>
            <div class="max-h-40 overflow-y-auto bg-gray-50 rounded-lg p-3 border border-gray-200">
                <?php foreach ($importResult['errors'] as $err): ?>
                <p class="text-xs text-gray-600 py-0.5 border-b border-gray-100 last:border-0">
                    <i class="fas fa-minus-circle text-yellow-500"></i> <?= htmlspecialchars($err) ?>
                </p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-4 pt-4 border-t border-gray-100">
            <a href="<?= BASE_URL ?>modules/admin/students.php" class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium">
                <i class="fas fa-list"></i> Lihat Daftar Siswa
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Guide -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-info-circle text-blue-600"></i> Panduan Import
        </h4>
        
        <div class="space-y-3 text-sm text-gray-600">
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">1</span>
                <p>Download template Excel (.xlsx) dan buka menggunakan <strong>Microsoft Excel</strong>, <strong>Google Sheets</strong>, atau <strong>LibreOffice</strong>.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">2</span>
                <p><strong>Hapus contoh data</strong> di template, lalu isi dengan data siswa sebenarnya. Jangan ubah baris header.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">3</span>
                <p>Kolom wajib: <strong>NIS</strong>, <strong>NAMA LENGKAP</strong>, <strong>JENIS KELAMIN</strong> (L atau P). Kolom lain boleh kosong.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">4</span>
                <p>Format tanggal lahir: <strong>DD/MM/YYYY</strong> (contoh: 15/03/2016) atau <strong>YYYY-MM-DD</strong>.</p>
            </div>
            <div class="flex items-start gap-3">
                <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-600 flex-shrink-0 mt-0.5">5</span>
                <p>Simpan file tetap dalam format <strong>.xlsx</strong>, lalu upload di form di atas.</p>
            </div>
            <div class="flex items-start gap-3 bg-yellow-50 p-3 rounded-lg border border-yellow-100 -mx-3">
                <span class="w-6 h-6 bg-yellow-200 rounded-full flex items-center justify-center text-xs font-bold text-yellow-700 flex-shrink-0 mt-0.5">!</span>
                <p class="text-yellow-800">NIS yang sudah ada di database <strong>otomatis dilewati</strong> (tidak duplikat). QR Code otomatis di-generate untuk setiap siswa baru.</p>
            </div>
        </div>

        <!-- Column Format Reference -->
        <div class="mt-6 overflow-x-auto">
            <h5 class="text-sm font-medium text-gray-700 mb-2">Referensi Kolom:</h5>
            <table class="w-full text-xs border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 border-b">Kolom</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-700 border-b">Wajib</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 border-b">Keterangan</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 border-b">Contoh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr class="bg-red-50"><td class="px-3 py-2 font-medium">NIS</td><td class="px-3 py-2 text-center"><span class="text-red-600 font-bold">Ya</span></td><td class="px-3 py-2">Nomor unik siswa</td><td class="px-3 py-2 font-mono">001</td></tr>
                    <tr><td class="px-3 py-2 font-medium">NISN</td><td class="px-3 py-2 text-center text-gray-400">Tidak</td><td class="px-3 py-2">Nomor Induk Siswa Nasional</td><td class="px-3 py-2 font-mono">0098765432</td></tr>
                    <tr class="bg-red-50"><td class="px-3 py-2 font-medium">NAMA LENGKAP</td><td class="px-3 py-2 text-center"><span class="text-red-600 font-bold">Ya</span></td><td class="px-3 py-2">Nama lengkap siswa</td><td class="px-3 py-2">Ahmad Fauzan</td></tr>
                    <tr class="bg-red-50"><td class="px-3 py-2 font-medium">JENIS KELAMIN</td><td class="px-3 py-2 text-center"><span class="text-red-600 font-bold">Ya</span></td><td class="px-3 py-2">L (Laki-laki) atau P (Perempuan)</td><td class="px-3 py-2 font-mono">L</td></tr>
                    <tr><td class="px-3 py-2 font-medium">TEMPAT LAHIR</td><td class="px-3 py-2 text-center text-gray-400">Tidak</td><td class="px-3 py-2">Kota/Kabupaten tempat lahir</td><td class="px-3 py-2">Lumajang</td></tr>
                    <tr><td class="px-3 py-2 font-medium">TANGGAL LAHIR</td><td class="px-3 py-2 text-center text-gray-400">Tidak</td><td class="px-3 py-2">Format: DD/MM/YYYY</td><td class="px-3 py-2 font-mono">15/03/2016</td></tr>
                    <tr><td class="px-3 py-2 font-medium">ALAMAT</td><td class="px-3 py-2 text-center text-gray-400">Tidak</td><td class="px-3 py-2">Alamat lengkap siswa</td><td class="px-3 py-2">Desa Jatigunung RT01/RW02</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
