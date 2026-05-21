<?php
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="text-center px-6">
        <div class="text-6xl text-red-500 mb-4"><i class="fas fa-ban"></i></div>
        <h1 class="text-4xl font-bold text-gray-800 mb-2">403</h1>
        <p class="text-xl text-gray-600 mb-4">Akses Ditolak</p>
        <p class="text-gray-500 mb-6">Anda tidak memiliki hak akses untuk halaman ini.</p>
        <a href="<?= BASE_URL ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-home"></i> Kembali ke Beranda
        </a>
    </div>
</body>
</html>
