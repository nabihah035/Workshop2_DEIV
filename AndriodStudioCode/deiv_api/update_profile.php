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
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? ''; // Optional password update

// Validate required fields
if (empty($first_name) || empty($username)) {
    echo json_encode(["status" => "error", "message" => "First name and username are required"]);
    exit;
}

// Check if username already exists (excluding current user)
$checkStmt = $conn->prepare("SELECT user_id FROM `user` WHERE username = ? AND user_id != ?");
$checkStmt->bind_param("si", $username, $user_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Username already exists"]);
    $checkStmt->close();
    exit;
}
$checkStmt->close();

// Prepare update query
if (!empty($password)) {
    // Update with password (hash the password)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE `user` SET first_name = ?, last_name = ?, username = ?, password = ? WHERE user_id = ?");
    $stmt->bind_param("ssssi", $first_name, $last_name, $username, $hashed_password, $user_id);
} else {
    // Update without password
    $stmt = $conn->prepare("UPDATE `user` SET first_name = ?, last_name = ?, username = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $first_name, $last_name, $username, $user_id);
}

// Execute update
if ($stmt->execute()) {
    // Also update session if the session user_id matches (for web compatibility)
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
        $_SESSION['username'] = $username;
    }
    
    echo json_encode([
        "status" => "success", 
        "message" => "Profile updated successfully",
        "updated_data" => [
            "full_name" => $first_name . ' ' . $last_name,
            "username" => $username
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update profile: " . $conn->error]);
}

$stmt->close();
$conn->close();
?>