<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูลสำหรับ XAMPP (MySQL/MariaDB ผ่าน phpMyAdmin)
// ค่าเริ่มต้นของ XAMPP คือ host=localhost, user=root, pass ว่าง
$DB_HOST = 'localhost';
$DB_NAME = 'babygole';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage() . ' — ตรวจสอบว่าเปิด MySQL ใน XAMPP และสร้างฐานข้อมูล babygole แล้ว (ดู database.sql)'
    ]));
}
