<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

// Example: login with username/password from POST (replace with your real login logic)
$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Username and password required"]);
    exit;
}

// Check user in database
$stmt = $conn->prepare("SELECT User_id, password, status FROM `user` WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}

$user = $result->fetch_assoc();

// For now, plain password comparison (replace with password_hash in production!)
if ($password !== $user['password']) {
    echo json_encode(["status" => "error", "message" => "Incorrect password"]);
    exit;
}

// Save user ID in session
$_SESSION['user_id'] = $user['User_id'];

echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "user_id" => $user['User_id']
]);
?>
