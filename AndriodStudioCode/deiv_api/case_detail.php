<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

$case_id = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;
if ($case_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid case ID"]);
    exit;
}

try {
    // Get case info
    $stmt = $conn->prepare("SELECT Case_id, case_name, description, status, User_id FROM `case_table` WHERE Case_id = ?");
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Case not found"]);
        exit;
    }

    $case = $result->fetch_assoc();
    
    // Get assigned user name
    $user_id = $case['User_id'];
    $user_stmt = $conn->prepare("SELECT first_name, last_name FROM user WHERE User_id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_name = "Unassigned";
    if ($user_result->num_rows > 0) {
        $user = $user_result->fetch_assoc();
        $user_name = $user['first_name'] . " " . $user['last_name'];
    }
    $case['assigned_to'] = $user_name;
    
    // Get total evidence count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM evidence WHERE Case_id = ?");
    $count_stmt->bind_param("i", $case_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $case['total_evidence'] = $count_row['total'];

    // Get evidence
    $stmt2 = $conn->prepare("SELECT Evidence_id, file_name, upload_date, status, hash_value FROM evidence WHERE Case_id = ? ORDER BY Evidence_id ASC");
    $stmt2->bind_param("i", $case_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    $evidence = [];

    $meta_stmt = $conn->prepare(
        "SELECT meta_key, meta_value FROM metadata WHERE Evidence_id = ?"
    );

    while ($row = $res2->fetch_assoc()) {

        // Fetch metadata for this evidence
        $meta_stmt->bind_param("i", $row['Evidence_id']);
        $meta_stmt->execute();
        $meta_result = $meta_stmt->get_result();

        $metadata = [];
        while ($m = $meta_result->fetch_assoc()) {
            $metadata[] = $m;
        }

        // Attach metadata to evidence object
        $row['metadata'] = $metadata;

        $evidence[] = $row;
    }


    echo json_encode([
        "status" => "success",
        "case" => $case,
        "evidence" => $evidence
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>