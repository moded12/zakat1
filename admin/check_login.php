<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$username = 'admin@admin';
$password = '123@123';

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo '<pre>';

if (!$user) {
    echo "USER NOT FOUND";
    exit;
}

echo "USER FOUND\n";
echo "DB USERNAME: " . $user['username'] . "\n";
echo "PASSWORD HASH: " . $user['password'] . "\n";

if (password_verify($password, $user['password'])) {
    echo "PASSWORD OK";
} else {
    echo "PASSWORD INVALID";
}
echo '</pre>';