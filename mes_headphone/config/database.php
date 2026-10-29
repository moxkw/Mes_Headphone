<?php
// FILE: config/database.php
// Kết nối MySQL bằng PDO (thông tin mặc định của XAMPP)
$host   = 'localhost';
$dbname = 'mes_headphone';
$dbuser = 'root';
$dbpass = '';          // XAMPP mặc định không có mật khẩu

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // báo lỗi bằng exception
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // mỗi dòng là mảng kết hợp
    ]);
} catch (PDOException $e) {
    die('Không kết nối được database: ' . $e->getMessage() .
        '<br>Hãy kiểm tra MySQL đã chạy và đã import file database/mes_headphone.sql chưa.');
}
