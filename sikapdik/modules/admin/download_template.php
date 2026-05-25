<?php
/**
 * Download Student Import Template (XLSX or CSV)
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */
require_once __DIR__ . '/../../config/app.php';
Auth::requireRole(['admin']);

$format = isset($_GET['format']) ? $_GET['format'] : 'xlsx';

// ============================================
// CSV FORMAT (fallback, always works)
// ============================================
if ($format === 'csv' || !class_exists('ZipArchive')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Template_Import_Siswa_SIKAPDIK.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header
    fputcsv($output, ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'alamat']);

    // Sample data
    fputcsv($output, ['001', '0098765432', 'Ahmad Fauzan', 'L', 'Lumajang', '15/03/2016', 'Desa Jatigunung RT01/RW02']);
    fputcsv($output, ['002', '0098765433', 'Siti Aisyah', 'P', 'Lumajang', '22/07/2016', 'Desa Jatigunung RT03/RW01']);
    fputcsv($output, ['003', '0098765434', 'Budi Santoso', 'L', 'Malang', '08/11/2015', 'Desa Sumbersari RT02/RW03']);
    fputcsv($output, ['004', '0098765435', 'Putri Rahayu', 'P', 'Lumajang', '30/01/2016', 'Desa Jatigunung RT04/RW02']);
    fputcsv($output, ['005', '', 'Rizky Aditya', 'L', '', '', 'Desa Jatigunung']);

    fclose($output);
    exit;
}

// ============================================
// XLSX FORMAT (professional, requires ZipArchive)
// ============================================
require_once __DIR__ . '/../../includes/SimpleXLSXWriter.php';

$xlsx = new SimpleXLSXWriter();

// Set column widths
$xlsx->setColWidths([6, 14, 16, 30, 16, 16, 20, 40]);

// Row 1: Title
$xlsx->writeRow(
    ['', 'TEMPLATE IMPORT DATA SISWA', '', '', '', '', '', ''],
    [0, 7, 7, 7, 7, 7, 7, 7]
);
$xlsx->addMerge(1, 1, 7, 1);

// Row 2: Subtitle
$xlsx->writeRow(
    ['', 'SIKAPDIK - ' . SCHOOL_NAME, '', '', '', '', '', ''],
    [0, 6, 6, 6, 6, 6, 6, 6]
);
$xlsx->addMerge(1, 2, 7, 2);

// Row 3: Header columns
$xlsx->writeRow(
    ['No', 'NIS *', 'NISN', 'NAMA LENGKAP *', 'JENIS KELAMIN *', 'TEMPAT LAHIR', 'TANGGAL LAHIR', 'ALAMAT'],
    [1, 1, 1, 1, 1, 1, 1, 1]
);

// Row 4: Sub-header (format hint)
$xlsx->writeRow(
    ['', '(Wajib, Unik)', '(Opsional)', '(Wajib)', '(L / P)', '(Opsional)', '(DD/MM/YYYY)', '(Opsional)'],
    [2, 2, 2, 2, 2, 2, 2, 2]
);

// Row 5-9: Sample data
$sampleData = [
    [1, '001', '0098765432', 'Ahmad Fauzan', 'L', 'Lumajang', '15/03/2016', 'Desa Jatigunung RT01/RW02'],
    [2, '002', '0098765433', 'Siti Aisyah', 'P', 'Lumajang', '22/07/2016', 'Desa Jatigunung RT03/RW01'],
    [3, '003', '0098765434', 'Budi Santoso', 'L', 'Malang', '08/11/2015', 'Desa Sumbersari RT02/RW03'],
    [4, '004', '0098765435', 'Putri Rahayu', 'P', 'Lumajang', '30/01/2016', 'Desa Jatigunung RT04/RW02'],
    [5, '005', '', 'Rizky Aditya', 'L', '', '', 'Desa Jatigunung'],
];

foreach ($sampleData as $row) {
    $xlsx->writeRow($row, [5, 5, 5, 5, 5, 5, 5, 5]);
}

// Row 10: Empty separator
$xlsx->writeRow(['', '', '', '', '', '', '', ''], [0, 0, 0, 0, 0, 0, 0, 0]);

// Row 11: Instructions header
$xlsx->writeRow(
    ['', 'PETUNJUK PENGISIAN:', '', '', '', '', '', ''],
    [0, 4, 0, 0, 0, 0, 0, 0]
);
$xlsx->addMerge(1, 11, 7, 11);

// Rows 12-17: Instructions
$instructions = [
    ['', '1.', 'Hapus contoh data di atas, lalu isi dengan data siswa sebenarnya.', '', '', '', '', ''],
    ['', '2.', 'Kolom bertanda * (NIS, NAMA LENGKAP, JENIS KELAMIN) wajib diisi.', '', '', '', '', ''],
    ['', '3.', 'Jenis Kelamin diisi: L (Laki-laki) atau P (Perempuan).', '', '', '', '', ''],
    ['', '4.', 'Format Tanggal Lahir: DD/MM/YYYY (contoh: 15/03/2016).', '', '', '', '', ''],
    ['', '5.', 'NIS harus unik. NIS yang sudah ada akan otomatis dilewati.', '', '', '', '', ''],
    ['', '6.', 'Simpan tetap format .xlsx, lalu upload di Import Data Siswa.', '', '', '', '', ''],
];

foreach ($instructions as $inst) {
    $xlsx->writeRow($inst, [0, 6, 6, 6, 6, 6, 6, 6]);
}

// Output
$xlsx->output('Template_Import_Siswa_SIKAPDIK.xlsx');
