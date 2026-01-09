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

// Turn off error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

try {
    /* TOTAL CASES */
    $stmt1 = $conn->prepare("SELECT COUNT(*) AS total FROM `case` WHERE User_id = ?");
    $stmt1->bind_param("i", $user_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $totalCasesRow = $result1->fetch_assoc();
    $totalCases = $totalCasesRow ? $totalCasesRow['total'] : 0;

    /* TOTAL EVIDENCE */
    $stmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM evidence WHERE Case_id IN (SELECT Case_id FROM `case` WHERE User_id = ?)");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $totalEvidenceRow = $result2->fetch_assoc();
    $totalEvidence = $totalEvidenceRow ? $totalEvidenceRow['total'] : 0;

    /* RECENT CASES */
    $stmt3 = $conn->prepare("SELECT Case_id, case_name, status, created_at FROM `case` WHERE User_id = ? ORDER BY Case_id DESC LIMIT 5");
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
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
