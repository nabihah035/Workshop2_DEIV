<?php
session_start();

// Correct path to config from static folder
$config_path = dirname(__DIR__) . '/../config/db.php';
include $config_path;

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="metadata_export_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, ['Meta ID', 'Key', 'Value', 'Evidence ID', 'File Name', 'Case Name', 'Hash Value']);

// Build query with filters
$where_clauses = [];
$params = [];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $where_clauses[] = "(m.meta_key LIKE :search OR m.meta_value LIKE :search)";
    $params[':search'] = "%$search%";
}

if (isset($_GET['evidence_id']) && intval($_GET['evidence_id']) > 0) {
    $evidence_id = intval($_GET['evidence_id']);
    $where_clauses[] = "m.Evidence_id = :evidence_id";  // Fix: specify table
    $params[':evidence_id'] = $evidence_id;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch all metadata (no pagination for export)
$sql = "
    SELECT 
        m.*,
        e.file_name,
        e.hash_value,
        c.case_name
    FROM metadata m
    LEFT JOIN evidence e ON m.Evidence_id = e.Evidence_id
    LEFT JOIN case_table c ON e.Case_id = c.Case_id
    $where_sql
    ORDER BY m.Meta_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Write data rows
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['Meta_id'],
        $row['meta_key'],
        $row['meta_value'],
        $row['Evidence_id'],
        $row['file_name'],
        $row['case_name'],
        $row['hash_value']
    ]);
}

fclose($output);
?>