<?php
error_reporting(0); // Prevents warnings from breaking JSON
session_start();
include "db_connect.php";
header("Content-Type: application/json");

$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Username and password required"]);
    exit;
}

// Table name is 'user' as confirmed
$stmt = $conn->prepare("SELECT User_id, password, status, first_name, last_name, role FROM `user` WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}

$user = $result->fetch_assoc();

// Check password - support both Hashed (for Nad123) and Plain Text (for Hawa)
$isCorrect = password_verify($password, $user['password']) || ($password === $user['password']);

if (!$isCorrect) {
    echo json_encode(["status" => "error", "message" => "Incorrect password"]);
    exit;
}

// Check status - Your DB has 'Active'
if ($user['status'] === 'Inactive') {
    echo json_encode(["status" => "inactive", "message" => "Account pending approval"]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "user_id" => (int)$user['User_id'],
    "name" => $user['first_name'] . ' ' . $user['last_name'],
    "role" => $user['role']
]);
?>