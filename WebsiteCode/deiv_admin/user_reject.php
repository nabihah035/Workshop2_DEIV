<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit;
}

require_once '../deiv_api/db.php';

if(isset($_GET['id'])){
    $id = $_GET['id'];
    $stmt = $pdo->prepare("UPDATE user SET status='Rejected' WHERE User_id=?");
    $stmt->execute([$id]);
}
header("Location: user_list.php");
exit;
