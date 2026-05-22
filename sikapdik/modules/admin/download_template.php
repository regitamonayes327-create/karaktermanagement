<?php
/**
 * Download Student Import Template (CSV)
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_siswa_sikapdik.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Write BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write header row
fputcsv($output, [
    'nis',
    'nisn',
    'nama_lengkap',
    'jenis_kelamin',
    'tempat_lahir',
    'tanggal_lahir',
    'alamat'
]);

// Write sample data rows (untuk panduan user)
$sampleData = [
    ['001', '0098765432', 'Ahmad Fauzan', 'L', 'Lumajang', '15/03/2016', 'Desa Jatigunung RT01/RW02'],
    ['002', '0098765433', 'Siti Aisyah', 'P', 'Lumajang', '22/07/2016', 'Desa Jatigunung RT03/RW01'],
    ['003', '0098765434', 'Budi Santoso', 'L', 'Malang', '08/11/2015', 'Desa Sumbersari RT02/RW03'],
    ['004', '0098765435', 'Putri Rahayu', 'P', 'Lumajang', '30/01/2016', 'Desa Jatigunung RT04/RW02'],
    ['005', '', 'Rizky Aditya', 'L', '', '', 'Desa Jatigunung'],
];

foreach ($sampleData as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
