<?php
include "db_connect.php";
header("Content-Type: application/json");

// Get POST data
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$organization = isset($_POST['organization']) ? trim($_POST['organization']) : '';
$role = isset($_POST['role']) ? $_POST['role'] : 'Institution'; // Default role

// Validate input
if (empty($username) || empty($password) || empty($email) || empty($first_name) || empty($last_name) || empty($organization)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email format"]);
    exit;
}

// Validate role against enum values
$valid_roles = ['Law Agencies', 'Digital Forensic Investigator', 'Legal Professionals', 'Institution', 'Admin'];
if (!in_array($role, $valid_roles)) {
    echo json_encode(["status" => "error", "message" => "Invalid role selected"]);
    exit;
}

// Check if username already exists
$checkStmt = $conn->prepare("SELECT User_id FROM user WHERE username = ? OR email = ?");
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Username or email already exists"]);
    $checkStmt->close();
    exit;
}
$checkStmt->close();

// Hash the password before storing
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new user with 'Inactive' status by default (as per your database schema)
$insertStmt = $conn->prepare("INSERT INTO user (username, password, email, first_name, last_name, role, status, organization) VALUES (?, ?, ?, ?, ?, ?, 'Inactive', ?)");
$insertStmt->bind_param("sssssss", $username, $hashed_password, $email, $first_name, $last_name, $role, $organization);

if ($insertStmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Registration successful. Waiting for admin approval."]);
} else {
    // More detailed error message
    $error_message = "Registration failed";
    if ($conn->errno == 1062) { // Duplicate entry error
        $error_message = "Username or email already exists";
    }
    echo json_encode(["status" => "error", "message" => $error_message . ": " . $conn->error]);
}

$insertStmt->close();
$conn->close();
?>