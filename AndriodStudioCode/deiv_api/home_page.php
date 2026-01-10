<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

// Get user_id from GET parameter
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid user ID"
    ]);
    exit;
}

// Verify user exists
$checkUser = $conn->prepare("SELECT User_id FROM user WHERE User_id = ? AND status = 'Active'");
$checkUser->bind_param("i", $user_id);
$checkUser->execute();
$checkResult = $checkUser->get_result();

if ($checkResult->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "User not found or inactive"
    ]);
    exit;
}

try {
    /* TOTAL CASES */
    $stmt1 = $conn->prepare("SELECT COUNT(*) AS total FROM `case_table` WHERE User_id = ?");
    $stmt1->bind_param("i", $user_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $totalCasesRow = $result1->fetch_assoc();
    $totalCases = $totalCasesRow ? $totalCasesRow['total'] : 0;

    /* TOTAL EVIDENCE */
    $stmt2 = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM evidence e
        INNER JOIN case_table c ON e.Case_id = c.Case_id
        WHERE c.User_id = ?
    ");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $totalEvidenceRow = $result2->fetch_assoc();
    $totalEvidence = $totalEvidenceRow ? $totalEvidenceRow['total'] : 0;

    /* RECENT CASES */
    $stmt3 = $conn->prepare("
        SELECT Case_id, case_name, status, created_at 
        FROM `case_table` 
        WHERE User_id = ? 
        ORDER BY Case_id DESC 
        LIMIT 5
    ");
    $stmt3->bind_param("i", $user_id);
    $stmt3->execute();
    $result3 = $stmt3->get_result();

    $recentCases = [];
    if ($result3 && $result3->num_rows > 0) {
        while ($row = $result3->fetch_assoc()) {
            $statusColor = "#777777"; // default gray

            switch ($row['status']) {
                case 'In Progress':
                    $statusColor = "#f9a825";
                    break;
                case 'Complete':
                    $statusColor = "#2e7d32";
                    break;
                case 'Closed':
                    $statusColor = "#c62828";
                    break;
                case 'Pending':
                    $statusColor = "#ef6c00";
                    break;
            }

            $row['status_color'] = $statusColor;
            $recentCases[] = $row;
        }
    }

    /* FINAL JSON */
    echo json_encode([
        "status" => "success",
        "user_id" => $user_id,
        "total_cases" => (int)$totalCases,
        "total_evidence" => (int)$totalEvidence,
        "recent_cases" => $recentCases
    ]);

} catch (Exception $e) {
    // Return a more informative error
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage(),
        "debug_info" => [
            "user_id" => $user_id,
            "error_file" => $e->getFile(),
            "error_line" => $e->getLine()
        ]
    ]);
}

$conn->close();
?>