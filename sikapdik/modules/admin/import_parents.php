<?php
/**
 * Import Parents from Excel
 * SIKAPDIK - Update/Create parent accounts from Excel file
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

define('PAGE_TITLE', 'Import Data Orang Tua');

$db = Database::getInstance();
$importResult = null;

if (isPost() && Security::validateCSRF()) {
    if (!empty($_FILES['import_file']['name'])) {
        $file = $_FILES['import_file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($extension !== 'xlsx') {
            setFlash('error', 'Format file harus .xlsx');
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            setFlash('error', 'Ukuran file maksimal 5MB.');
        } else {
            if (!class_exists('ZipArchive')) {
                setFlash('error', 'Server tidak mendukung format .xlsx (ZipArchive tidak tersedia).');
            } else {
                require_once __DIR__ . '/../../includes/SimpleXLSXReader.php';
                $reader = new SimpleXLSXReader();
                
                if ($reader->open($file['tmp_name'])) {
                    $allRows = $reader->getRows();
                    
                    // Find header row
                    $headerRowIdx = -1;
                    foreach ($allRows as $idx => $row) {
                        $rowLower = array_map(function($v) { return strtolower(trim((string)$v)); }, $row);
                        if (in_array('nis', $rowLower) || in_array('nama siswa', $rowLower)) {
                            $headerRowIdx = $idx;
                            break;
                        }
                    }
                    
                    if ($headerRowIdx === -1) {
                        setFlash('error', 'Header tidak ditemukan. Pastikan file memiliki kolom NIS.');
                    } else {
                        $header = array_map(function($v) {
                            return strtolower(trim(str_replace(['*',' '], ['','_'], (string)$v)));
                        }, $allRows[$headerRowIdx]);
                        
                        // Map columns
                        $colNis = false; $colParentName = false; $colRelationship = false;
                        $colPhone = false; $colUsername = false; $colPassword = false;
                        
                        foreach ($header as $i => $h) {
                            if (in_array($h, ['nis'])) $colNis = $i;
                            if (in_array($h, ['nama_orang_tua', 'nama_ortu', 'orang_tua'])) $colParentName = $i;
                            if (in_array($h, ['hubungan', 'relationship'])) $colRelationship = $i;
                            if (in_array($h, ['no_hp', 'hp', 'telepon', 'phone', 'no_telepon'])) $colPhone = $i;
                            if (in_array($h, ['username', 'user'])) $colUsername = $i;
                            if (in_array($h, ['password', 'pass'])) $colPassword = $i;
                        }
                        
                        if ($colNis === false || $colParentName === false) {
                            setFlash('error', 'Kolom NIS dan Nama Orang Tua wajib ada di file.');
                        } else {
                            $created = 0;
                            $updated = 0;
                            $skipped = 0;
                            $errors = [];
                            
                            $db->beginTransaction();
                            try {
                                for ($i = $headerRowIdx + 1; $i < count($allRows); $i++) {
                                    $row = $allRows[$i];
                                    $nis = trim((string)($row[$colNis] ?? ''));
                                    $parentName = trim((string)($row[$colParentName] ?? ''));
                                    
                                    if (empty($nis) || empty($parentName)) continue;
                                    
                                    $relationship = ($colRelationship !== false) ? strtolower(trim((string)($row[$colRelationship] ?? 'ayah'))) : 'ayah';
                                    if (!in_array($relationship, ['ayah','ibu','wali'])) $relationship = 'ayah';
                                    
                                    $phone = ($colPhone !== false) ? trim((string)($row[$colPhone] ?? '')) : '';
                                    $username = ($colUsername !== false) ? trim((string)($row[$colUsername] ?? '')) : '';
                                    $password = ($colPassword !== false) ? trim((string)($row[$colPassword] ?? '')) : '';
                                    
                                    // Find student by NIS
                                    $student = $db->fetch("SELECT id, full_name FROM students WHERE nis = ?", [$nis]);
                                    if (!$student) {
                                        $errors[] = "Baris " . ($i+1) . ": NIS '{$nis}' tidak ditemukan.";
                                        $skipped++;
                                        continue;
                                    }
                                    
                                    // Check if student already has parent linked
                                    $existingLink = $db->fetch("SELECT ps.parent_id, p.user_id FROM parent_student ps JOIN parents p ON ps.parent_id = p.id WHERE ps.student_id = ?", [$student['id']]);
                                    
                                    if ($existingLink) {
                                        // UPDATE existing parent
                                        $parentId = $existingLink['parent_id'];
                                        $userId = $existingLink['user_id'];
                                        
                                        // Update parent record
                                        $updateData = ['full_name' => $parentName, 'relationship' => $relationship];
                                        if ($phone) $updateData['phone'] = $phone;
                                        $db->update('parents', $updateData, 'id = ?', [$parentId]);
                                        
                                        // Update user account
                                        if ($userId) {
                                            $userData = ['full_name' => $parentName];
                                            if ($username) $userData['username'] = $username;
                                            if ($password) $userData['password'] = Security::hashPassword($password);
                                            if ($phone) $userData['phone'] = $phone;
                                            $db->update('users', $userData, 'id = ?', [$userId]);
                                        }
                                        
                                        $updated++;
                                    } else {
                                        // CREATE new parent + user + link
                                        if (empty($username)) $username = 'ortu_' . $nis;
                                        if (empty($password)) $password = 'Ortu@' . $nis;
                                        
                                        // Check username exists
                                        $existUser = $db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
                                        if ($existUser) {
                                            $username = $username . '_' . rand(10,99);
                                        }
                                        
                                        $userId = $db->insert('users', [
                                            'username' => $username,
                                            'password' => Security::hashPassword($password),
                                            'full_name' => $parentName,
                                            'role' => 'orang_tua',
                                            'phone' => $phone ?: null,
                                            'is_active' => 1
                                        ]);
                                        
                                        $parentId = $db->insert('parents', [
                                            'user_id' => $userId,
                                            'full_name' => $parentName,
                                            'phone' => $phone ?: null,
                                            'relationship' => $relationship,
                                            'is_active' => 1
                                        ]);
                                        
                                        $db->insert('parent_student', ['parent_id' => $parentId, 'student_id' => $student['id']]);
                                        $created++;
                                    }
                                }
                                
                                $db->commit();
                                Auth::logActivity('import_parents', 'parents', "Import orang tua: {$created} dibuat, {$updated} diperbarui, {$skipped} dilewati");
                                
                                $importResult = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
                                
                                if ($created + $updated > 0) {
                                    setFlash('success', "Import berhasil! <strong>{$created}</strong> akun baru dibuat, <strong>{$updated}</strong> diperbarui" . ($skipped ? ", {$skipped} dilewati" : "") . ".");
                                } else {
                                    setFlash('error', 'Tidak ada data yang berhasil diimport.');
                                }
                            } catch (Exception $e) {
                                $db->rollback();
                                setFlash('error', 'Error: ' . $e->getMessage());
                            }
                        }
                    }
                } else {
                    setFlash('error', $reader->getError());
                }
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Import Data Orang Tua dari Excel</h3>
            <p class="text-sm text-gray-500">Update nama, username, password orang tua dari file Excel</p>
        </div>
        <a href="<?= BASE_URL ?>modules/admin/parents.php" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <!-- Upload Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-start gap-4 mb-6">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-file-excel text-blue-600 text-xl"></i>
            </div>
            <div>
                <h4 class="font-semibold text-gray-800 mb-1">Upload File Excel (.xlsx)</h4>
                <p class="text-sm text-gray-600">File harus memiliki kolom: <strong>NIS</strong>, <strong>Nama Orang Tua</strong>, Hubungan, No HP, Username, Password</p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <?= Security::csrfField() ?>
            <div class="mb-4">
                <input type="file" name="import_file" accept=".xlsx" required
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm file:mr-4 file:py-1.5 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
            </div>
            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2 font-medium">
                <i class="fas fa-upload"></i> Import & Update Data Orang Tua
            </button>
        </form>
    </div>

    <?php if ($importResult): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h4 class="font-semibold text-gray-800 mb-4"><i class="fas fa-chart-bar text-purple-600"></i> Hasil Import</h4>
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="text-center p-4 bg-green-50 rounded-xl border border-green-100">
                <p class="text-3xl font-bold text-green-600"><?= $importResult['created'] ?></p>
                <p class="text-xs text-gray-600">Akun Baru</p>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-xl border border-blue-100">
                <p class="text-3xl font-bold text-blue-600"><?= $importResult['updated'] ?></p>
                <p class="text-xs text-gray-600">Diperbarui</p>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-xl border border-yellow-100">
                <p class="text-3xl font-bold text-yellow-600"><?= $importResult['skipped'] ?></p>
                <p class="text-xs text-gray-600">Dilewati</p>
            </div>
        </div>
        <?php if (!empty($importResult['errors'])): ?>
        <details class="mt-3">
            <summary class="text-sm font-medium text-gray-700 cursor-pointer">Detail dilewati (<?= count($importResult['errors']) ?>)</summary>
            <div class="mt-2 max-h-40 overflow-y-auto bg-gray-50 rounded-lg p-3 border text-xs text-gray-600">
                <?php foreach ($importResult['errors'] as $err): ?><p class="py-0.5"><?= htmlspecialchars($err) ?></p><?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Guide -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h4 class="font-semibold text-gray-800 mb-3"><i class="fas fa-info-circle text-blue-600"></i> Panduan</h4>
        <div class="space-y-2 text-sm text-gray-600">
            <p><strong>1.</strong> Sistem mencocokkan data berdasarkan <strong>NIS siswa</strong>.</p>
            <p><strong>2.</strong> Jika siswa sudah punya orang tua → nama, HP, username, password akan <strong>diperbarui</strong>.</p>
            <p><strong>3.</strong> Jika siswa belum punya orang tua → akun baru <strong>dibuat otomatis</strong>.</p>
            <p><strong>4.</strong> Kolom wajib: <strong>NIS</strong> dan <strong>Nama Orang Tua</strong>. Kolom lain opsional.</p>
            <p><strong>5.</strong> Jika username/password kosong di Excel, sistem akan generate otomatis: <code>ortu_[NIS]</code> / <code>Ortu@[NIS]</code></p>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-xs border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100"><tr>
                    <th class="px-2 py-2 text-left font-semibold">Kolom</th>
                    <th class="px-2 py-2 text-center font-semibold">Wajib</th>
                    <th class="px-2 py-2 text-left font-semibold">Contoh</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <tr class="bg-red-50"><td class="px-2 py-1.5 font-medium">NIS</td><td class="px-2 py-1.5 text-center text-red-600 font-bold">Ya</td><td class="px-2 py-1.5">985</td></tr>
                    <tr class="bg-red-50"><td class="px-2 py-1.5 font-medium">Nama Orang Tua</td><td class="px-2 py-1.5 text-center text-red-600 font-bold">Ya</td><td class="px-2 py-1.5">HENO SUYEPRI</td></tr>
                    <tr><td class="px-2 py-1.5">Hubungan</td><td class="px-2 py-1.5 text-center text-gray-400">Tidak</td><td class="px-2 py-1.5">Ayah / Ibu / Wali</td></tr>
                    <tr><td class="px-2 py-1.5">No HP</td><td class="px-2 py-1.5 text-center text-gray-400">Tidak</td><td class="px-2 py-1.5">082262189171</td></tr>
                    <tr><td class="px-2 py-1.5">Username</td><td class="px-2 py-1.5 text-center text-gray-400">Tidak</td><td class="px-2 py-1.5">heno001</td></tr>
                    <tr><td class="px-2 py-1.5">Password</td><td class="px-2 py-1.5 text-center text-gray-400">Tidak</td><td class="px-2 py-1.5">heno001</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
