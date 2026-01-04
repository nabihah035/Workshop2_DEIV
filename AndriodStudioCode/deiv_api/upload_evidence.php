<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$file_name = $_POST['file_name'] ?? '';
$upload_date = $_POST['upload_date'] ?? date('Y-m-d H:i:s');
$status = $_POST['status'] ?? 'Pending';
$hash_value = $_POST['hash_value'] ?? '';
$case_id = intval($_POST['case_id'] ?? 0);

if (empty($file_name) || $case_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO evidence (file_name, upload_date, status, hash_value, Case_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $file_name, $upload_date, $status, $hash_value, $case_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Evidence uploaded successfully",
            "evidence_id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error"]);
    }
    
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>