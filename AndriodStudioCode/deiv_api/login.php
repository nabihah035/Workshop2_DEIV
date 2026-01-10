<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Username and password required"]);
    exit;
}

// Check user in database
$stmt = $conn->prepare("SELECT User_id, password, status, first_name, last_name, role FROM `user` WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}

$user = $result->fetch_assoc();

// FIXED: Use password_verify for hashed passwords
if (!password_verify($password, $user['password'])) {
    echo json_encode(["status" => "error", "message" => "Incorrect password"]);
    exit;
}

// Check user status - Note: Your database has 'Active' (capital A)
if ($user['status'] === 'Inactive') {
    echo json_encode([
        "status" => "inactive", 
        "message" => "Account pending approval",
        "user_id" => $user['User_id']
    ]);
    exit;
}

// Save user ID in session for PHP session-based APIs
$_SESSION['user_id'] = $user['User_id'];
$_SESSION['username'] = $username;
$_SESSION['role'] = $user['role'];

echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "user_id" => $user['User_id'],
    "name" => $user['first_name'] . ' ' . $user['last_name'],
    "role" => $user['role']
]);