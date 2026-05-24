<?php
/**
 * Download Student Import Template (Professional XLSX)
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/SimpleXLSXWriter.php';
Auth::requireRole(['admin']);

$xlsx = new SimpleXLSXWriter();

// Set column widths (professional spacing)
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

// Row 5-9: Sample data (with alternating style)
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

// Row 11-16: Instructions
$xlsx->writeRow(
    ['', 'PETUNJUK PENGISIAN:', '', '', '', '', '', ''],
    [0, 4, 0, 0, 0, 0, 0, 0]
);
$xlsx->addMerge(1, 11, 7, 11);

$instructions = [
    ['', '1.', 'Hapus contoh data di atas (baris 5-9), lalu isi dengan data siswa sebenarnya.', '', '', '', '', ''],
    ['', '2.', 'Kolom bertanda * (NIS, NAMA LENGKAP, JENIS KELAMIN) wajib diisi.', '', '', '', '', ''],
    ['', '3.', 'Jenis Kelamin diisi: L (Laki-laki) atau P (Perempuan).', '', '', '', '', ''],
    ['', '4.', 'Format Tanggal Lahir: DD/MM/YYYY (contoh: 15/03/2016) atau YYYY-MM-DD.', '', '', '', '', ''],
    ['', '5.', 'NIS harus unik. Siswa dengan NIS yang sudah ada di database akan otomatis dilewati.', '', '', '', '', ''],
    ['', '6.', 'Simpan file ini tetap dalam format .xlsx, lalu upload di menu Import Data Siswa.', '', '', '', '', ''],
];

foreach ($instructions as $inst) {
    $xlsx->writeRow($inst, [0, 6, 6, 6, 6, 6, 6, 6]);
}

// Output the file
$xlsx->output('Template_Import_Siswa_SIKAPDIK.xlsx');
