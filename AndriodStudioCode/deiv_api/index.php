<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

try {
    switch($action) {
        case 'upload_evidence':
            handleEvidenceUpload();
            break;
            
        case 'get_evidence':
            getEvidenceList();
            break;
            
        case 'get_evidence_by_user':
            $userId = $_GET['user_id'] ?? 0;
            getEvidenceByUser($userId);
            break;
            
        case 'delete_evidence':
            $evidenceId = $_GET['id'] ?? 0;
            deleteEvidence($evidenceId);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal server error: " . $e->getMessage()]);
}

function handleEvidenceUpload() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        return;
    }

    if (!isset($_FILES['evidence_file']) || !isset($_POST['user_id'])) {
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        return;
    }

    $user_id = intval($_POST['user_id']);
    $file = $_FILES['evidence_file'];

    // Allowed file types
    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'mp4', 'mov', 'avi'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        echo json_encode(["status" => "error", "message" => "Unsupported file format"]);
        return;
    }

    // Validate size (<100MB)
    if ($file['size'] > 100 * 1024 * 1024) {
        echo json_encode(["status" => "error", "message" => "File too large (max 100MB)"]);
        return;
    }

    global $conn;
    
    // Generate SHA-256 hash
    $hash_value = hash_file("sha256", $file["tmp_name"]);

    // Check if evidence already exists
    $check = mysqli_query($conn, "SELECT * FROM evidence WHERE hash_value='$hash_value'");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(["status" => "exists", "message" => "Evidence already registered"]);
        return;
    }

    // Create upload directory
    $upload_dir = "uploads/evidence/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $new_filename = uniqid("evidence_") . "." . $ext;
    $file_path = $upload_dir . $new_filename;

    // Move uploaded file
    if (move_uploaded_file($file["tmp_name"], $file_path)) {
        $date = date("Y-m-d H:i:s");
        $original_name = mysqli_real_escape_string($conn, $file['name']);
        
        $query = "INSERT INTO evidence(file_name, original_name, file_path, upload_date, status, hash_value, user_id) 
                  VALUES('$new_filename', '$original_name', '$file_path', '$date', 'Pending', '$hash_value', $user_id)";
        
        if (mysqli_query($conn, $query)) {
            $evidence_id = mysqli_insert_id($conn);
            echo json_encode([
                "status" => "success",
                "message" => "Evidence uploaded successfully",
                "evidence_id" => $evidence_id,
                "file_name" => $new_filename,
                "original_name" => $original_name,
                "file_path" => $file_path,
                "hash_value" => $hash_value,
                "upload_date" => $date,
                "user_id" => $user_id
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database insertion failed: " . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to move uploaded file"]);
    }
}

function getEvidenceList() {
    global $conn;
    
    $result = mysqli_query($conn, "
        SELECT e.*, u.username 
        FROM evidence e 
        LEFT JOIN users u ON e.user_id = u.id 
        ORDER BY e.upload_date DESC
    ");
    
    $evidence = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $evidence[] = $row;
    }
    
    echo json_encode(["status" => "success", "data" => $evidence]);
}

function getEvidenceByUser($userId) {
    global $conn;
    
    $userId = intval($userId);
    $result = mysqli_query($conn, "
        SELECT * FROM evidence 
        WHERE user_id = $userId 
        ORDER BY upload_date DESC
    ");
    
    $evidence = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $evidence[] = $row;
    }
    
    echo json_encode(["status" => "success", "data" => $evidence]);
}

function deleteEvidence($evidenceId) {
    global $conn;
    
    $evidenceId = intval($evidenceId);
    
    // First get file path
    $result = mysqli_query($conn, "SELECT file_path FROM evidence WHERE id = $evidenceId");
    if ($row = mysqli_fetch_assoc($result)) {
        // Delete physical file
        if (file_exists($row['file_path'])) {
            unlink($row['file_path']);
        }
    }
    
    // Delete from database
    mysqli_query($conn, "DELETE FROM evidence WHERE id = $evidenceId");
    
    if (mysqli_affected_rows($conn) > 0) {
        echo json_encode(["status" => "success", "message" => "Evidence deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete evidence"]);
    }
}
?>