<?php
include "db_connect.php";
header("Content-Type: application/json");

$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$new_pass = $_POST['new_password'] ?? '';

if (!$username || !$email || !$new_pass) {
    echo json_encode(["status" => "error", "message" => "Missing required data"]);
    exit;
}

// Verify user (Check if table is 'user' or 'users')
$stmt = $conn->prepare("SELECT * FROM `user` WHERE username = ? AND email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE `user` SET password = ? WHERE username = ?");
    $update->bind_param("ss", $hashed, $username);
    
    if ($update->execute()) {
        echo json_encode(["status" => "success", "message" => "Password updated!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Username or Email"]);
}