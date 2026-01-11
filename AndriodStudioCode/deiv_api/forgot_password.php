<?php
include 'db_config.php'; // Your database connection file

$username = $_POST['username'];

// 1. Check if user exists
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // 2. Logic for password reset (e.g., reset to a default or send email)
    // For a simple workshop, we can reset it to "123456"
    $newPassword = password_hash("123456", PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $update->bind_param("ss", $newPassword, $username);
    
    if ($update->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Your password has been reset to: 123456"
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update password"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Username not found"]);
}