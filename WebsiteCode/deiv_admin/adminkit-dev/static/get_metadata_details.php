<?php
session_start();
// Correct path to config from static folder
$config_path = dirname(__DIR__) . '/config/db.php';
include $config_path;

$meta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($meta_id > 0) {
    $sql = "
        SELECT 
            m.*,
            e.file_name,
            e.hash_value,
            c.case_name
        FROM metadata m
        LEFT JOIN evidence e ON m.Evidence_id = e.Evidence_id
        LEFT JOIN case_table c ON e.Case_id = c.Case_id
        WHERE m.Meta_id = :id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $meta_id]);
    $meta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($meta) {
        echo json_encode(['success' => true, 'meta' => $meta]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Metadata not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
?>