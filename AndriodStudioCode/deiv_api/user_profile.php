<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

// ✅ Get user_id from GET parameter (like HomeFragment)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid user ID"
    ]);
    exit;
}

// Turn off error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

try {
    // Fetch user data from database
    $stmt = $conn->prepare("
        SELECT 
            user_id,
            username,
            email,
            first_name,
            last_name,
            role,
            status,
            organization,
            DATE_FORMAT(created_at, '%M %d, %Y') as formatted_created_at,
            created_at
        FROM `user` 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => "error",
            "message" => "User not found"
        ]);
        exit;
    }

    $user = $result->fetch_assoc();

    // Prepare response data
    $response = [
        "status" => "success",
        "data" => [
            "user_id" => $user['user_id'],
            "username" => $user['username'],
            "email" => $user['email'],
            "full_name" => $user['first_name'] . ' ' . $user['last_name'],
            "first_name" => $user['first_name'],
            "last_name" => $user['last_name'],
            "role" => $user['role'],
            "status" => $user['status'],
            "organization" => $user['organization'],
            "created_at" => $user['formatted_created_at']
        ]
    ];

    echo json_encode($response);

    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>