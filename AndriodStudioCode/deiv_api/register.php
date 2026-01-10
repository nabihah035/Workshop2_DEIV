<?php
include "db_connect.php";
header("Content-Type: application/json");

// Get POST data
$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$email = isset($_POST['email']) ? $_POST['email'] : '';
$first_name = isset($_POST['first_name']) ? $_POST['first_name'] : '';
$last_name = isset($_POST['last_name']) ? $_POST['last_name'] : '';
$organization = isset($_POST['organization']) ? $_POST['organization'] : '';
$role = isset($_POST['role']) ? $_POST['role'] : 'Institution'; // Default role

// Validate input
if (empty($username) || empty($password) || empty($email) || empty($first_name) || empty($last_name)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// Check if username already exists
$checkStmt = $conn->prepare("SELECT User_id FROM `user` WHERE username = ? OR email = ?");
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Username or email already exists"]);
    exit;
}

// Hash the password before storing
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$insertStmt->bind_param("sssssss", $username, $hashed_password, $email, $first_name, $last_name, $organization, $role);

// Insert new user with 'Inactive' status by default
$insertStmt = $conn->prepare("INSERT INTO `user` (username, password, email, first_name, last_name, organization, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Inactive')");
$insertStmt->bind_param("sssssss", $username, $hashed_password, $email, $first_name, $last_name, $organization, $role);
if ($insertStmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Registration successful. Waiting for admin approval."]);
} else {
    echo json_encode(["status" => "error", "message" => "Registration failed: " . $conn->error]);
}
?>