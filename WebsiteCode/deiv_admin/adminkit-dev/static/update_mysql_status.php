<?php
require_once dirname(dirname(__DIR__)) . '/config/db.php';

$user_id = $_POST['user_id'] ?? '';
$email = $_POST['email'] ?? '';
$status = $_POST['status'] ?? '';

if (empty($email) || empty($status)) {
    echo 'Missing data';
    exit();
}

// Update or insert into MySQL
$stmt = $pdo->prepare("SELECT User_id FROM user WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $update = $pdo->prepare("UPDATE user SET status = ? WHERE email = ?");
    $update->execute([$status, $email]);
    echo "updated";
} else {
    $insert = $pdo->prepare("INSERT INTO user (email, status, username, created_at) VALUES (?, ?, ?, NOW())");
    $username = strstr($email, '@', true);
    $insert->execute([$email, $status, $username]);
    echo "inserted";
}
?>