<?php
session_start();
include "db_connect.php";
header("Content-Type: application/json");

// Get user_id and status filter from GET parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

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
    /* BUILD SQL QUERY WITH FILTER */
    // In the SELECT query, add verified evidence count
    $sql = "
        SELECT 
            c.Case_id, 
            c.case_name, 
            c.description, 
            c.status, 
            c.created_at,
            COUNT(e.evidence_id) as evidence_count,
            SUM(CASE WHEN e.status = 'Verified' THEN 1 ELSE 0 END) as verified_count
        FROM `case_table` c
        LEFT JOIN `evidence` e ON c.Case_id = e.Case_id
        WHERE c.User_id = ?
    ";
        
    $params = array($user_id);
    $param_types = "i";
    
    // Add status filter if not "All"
    if ($status_filter !== 'All') {
        $sql .= " AND c.status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }
    
    $sql .= " GROUP BY c.Case_id ORDER BY c.Case_id DESC";
    
    /* GET TOTAL CASE COUNT WITH FILTER */
    $countSql = "SELECT COUNT(*) as total_cases FROM `case_table` WHERE User_id = ?";
    $countParams = array($user_id);
    $countParamTypes = "i";
    
    if ($status_filter !== 'All') {
        $countSql .= " AND status = ?";
        $countParams[] = $status_filter;
        $countParamTypes .= "s";
    }
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($countParamTypes, ...$countParams);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = 0;
    if ($countRow = $countResult->fetch_assoc()) {
        $totalCount = $countRow['total_cases'];
    }
    $countStmt->close();

    /* GET FILTERED CASES */
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $cases = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format created_at date
            $date = new DateTime($row['created_at']);
            $row['created_at'] = $date->format('m/d/Y');
            
            // Set status color
            $statusColor = "#777777"; // default gray
            switch ($row['status']) {
                case 'In Progress':
                    $statusColor = "#f9a825"; // yellow/orange
                    break;
                case 'Complete':
                    $statusColor = "#2e7d32"; // green
                    break;
                case 'Closed':
                    $statusColor = "#c62828"; // red
                    break;
                case 'Pending':
                    $statusColor = "#ef6c00"; // orange
                    break;
            }
            
            $row['status_color'] = $statusColor;
            $cases[] = $row;
        }
    }

    /* FINAL JSON */
    echo json_encode([
        "status" => "success",
        "user_id" => $user_id,
        "current_filter" => $status_filter,
        "total_cases" => $totalCount,
        "cases" => $cases
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
