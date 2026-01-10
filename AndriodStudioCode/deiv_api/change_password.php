<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

// ✅ Get user_id from POST parameter (from Android app)
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

// Get POST data
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

// Validate input
if (empty($current_password) || empty($new_password)) {
    echo json_encode(["status" => "error", "message" => "Current password and new password are required"]);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(["status" => "error", "message" => "New password must be at least 6 characters"]);
    exit;
}

// Get current password from database
$stmt = $conn->prepare("SELECT password FROM `user` WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    $stmt->close();
    exit;
}

$user = $result->fetch_assoc();
$stored_password = $user['password'];
$stmt->close();

// Verify current password
// Check if stored password is hashed or plain text
if (password_verify($current_password, $stored_password)) {
    // Password is hashed and matches
} else if ($current_password === $stored_password) {
    // Password is plain text and matches (legacy support)
} else {
    echo json_encode(["status" => "error", "message" => "Current password is incorrect"]);
    exit;
}

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Check if new password is same as current
if (password_verify($new_password, $stored_password)) {
    echo json_encode(["status" => "error", "message" => "New password must be different from current password"]);
    exit;
}

// Also check if new password matches the plain text stored password (for legacy cases)
if ($new_password === $stored_password) {
    echo json_encode(["status" => "error", "message" => "New password must be different from current password"]);
    exit;
}

// Update password with hashed version
$updateStmt = $conn->prepare("UPDATE `user` SET password = ? WHERE user_id = ?");
$updateStmt->bind_param("si", $hashed_password, $user_id);

if ($updateStmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Password changed successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to change password: " . $conn->error]);
}

$updateStmt->close();
$conn->close();
?>