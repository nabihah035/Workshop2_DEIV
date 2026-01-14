<?php
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['User_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the absolute path to config folder
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

// Check if case ID is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'No case ID provided']);
    exit();
}

$case_id = (int)$_POST['id'];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if case exists
    $check_stmt = $pdo->prepare("SELECT * FROM case_table WHERE Case_id = ?");
    $check_stmt->execute([$case_id]);
    $case = $check_stmt->fetch();
    
    if (!$case) {
        echo json_encode(['success' => false, 'message' => 'Case not found']);
        exit();
    }
    
    // First, delete related evidence records
    $delete_evidence_stmt = $pdo->prepare("DELETE FROM evidence WHERE Case_id = ?");
    $delete_evidence_stmt->execute([$case_id]);
    
    // Then delete the case
    $delete_case_stmt = $pdo->prepare("DELETE FROM case_table WHERE Case_id = ?");
    $delete_case_stmt->execute([$case_id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Case and related evidence deleted successfully'
    ]);
    
} catch (PDOException $e) {
    // Rollback on error
    $pdo->rollBack();
    
    // Log error for debugging
    error_log("Delete case error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to delete case: ' . $e->getMessage()
    ]);
}
?>