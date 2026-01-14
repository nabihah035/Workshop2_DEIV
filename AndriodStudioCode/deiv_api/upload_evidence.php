<?php
// ==========================================
// BULLETPROOF ERROR HANDLING
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 0); 
header("Content-Type: application/json");

// Catch Fatal Errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Server Crash: " . $error['message']]);
        exit;
    }
});

// ==========================================
// CONFIGURATION
// ==========================================
session_start();
include "db_connect.php"; 

$upload_dir = 'uploads/evidence/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

// URL of your Python AI Server
$PYTHON_AI_URL = "http://127.0.0.1:5000/predict";

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function run_ai_detection($filePath, $originalName, $aiUrl) {
    $cFile = new CURLFile($filePath, mime_content_type($filePath), $originalName);
    $data = ['file' => $cFile];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $aiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout for AI
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) { curl_close($ch); return 'error'; }
    curl_close($ch);

    if ($httpCode !== 200) return 'error';

    $json = json_decode($response, true);
    
    if (isset($json['prediction'])) {
        if (strpos($json['prediction'], 'Forged') !== false || strpos($json['prediction'], 'Deepfake') !== false) {
            return 'forged';
        }
        return 'authentic';
    }
    
    return 'error';
}

function get_file_metadata($filePath, $mimeType) {
    $metadata = [];
    $fileSize = filesize($filePath);
    $metadata['File Size'] = round($fileSize / (1024 * 1024), 2) . ' MB';
    if (strpos($mimeType, 'image/') === 0 && $imageSize = getimagesize($filePath)) {
        $metadata['Image Dimensions'] = $imageSize[0] . 'x' . $imageSize[1];
    }
    return $metadata;
}

// ==========================================
// MAIN LOGIC
// ==========================================

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request method");

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);
    if ($user_id <= 0) throw new Exception("User not authenticated.");

    $case_id = intval($_POST['case_id'] ?? 0);
    if ($case_id <= 0) throw new Exception("Missing Case ID");

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No file uploaded");
    }

    $file = $_FILES['file'];
    $tmp_path = $file['tmp_name'];
    $final_file_name = !empty($_POST['file_name']) ? $_POST['file_name'] : basename($file['name']);
    $file_mime_type = mime_content_type($tmp_path);

    // --- AI CHECK ---
    $ai_status = run_ai_detection($tmp_path, $file['name'], $PYTHON_AI_URL);

    // Initialize default status
    $db_status = 'Verified';
    $json_response_status = 'success';
    $json_message = "Upload Successful. AI verified this file is Authentic.";

    // --- LOGIC CHANGE: HANDLE FORGERY WITHOUT EXITING ---
    if ($ai_status === 'forged') {
        $db_status = 'Tampered';     // Set DB status to Tampered
        $json_response_status = 'tampered'; // Tell App to show Red Warning
        $json_message = 'SECURITY ALERT: The AI detected this file is FORGED. Saved as "Tampered".';
        // We do NOT exit here anymore. We continue to save.
    } 
    
    // --- DATABASE TRANSACTION ---
    $conn->begin_transaction();

    // 1. Get User
    $stmt = $conn->prepare("SELECT username FROM user WHERE User_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) throw new Exception("User not found");
    $stmt->close();

    // 2. Get Case
    $stmt = $conn->prepare("SELECT case_name FROM case_table WHERE Case_id = ?");
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows == 0) throw new Exception("Case not found");
    $case_name = $res->fetch_assoc()['case_name'];
    $stmt->close();

    // 3. Insert Evidence (Using dynamic $db_status)
    $hash = hash_file('sha256', $tmp_path);
    $date = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("INSERT INTO evidence (file_name, upload_date, status, hash_value, Case_id) VALUES (?, ?, ?, ?, ?)");
    // Bind the $db_status (Verified OR Tampered)
    $stmt->bind_param("ssssi", $final_file_name, $date, $db_status, $hash, $case_id);
    if (!$stmt->execute()) throw new Exception("Evidence Insert Failed: " . $stmt->error);
    $evidence_id = $stmt->insert_id;
    $stmt->close();

    // 4. Metadata
    $meta = get_file_metadata($tmp_path, $file_mime_type);
    $stmt = $conn->prepare("INSERT INTO metadata (meta_key, meta_value, Evidence_id) VALUES (?, ?, ?)");
    foreach ($meta as $k => $v) {
        $stmt->bind_param("ssi", $k, $v, $evidence_id);
        $stmt->execute();
    }
    $stmt->close();

    // 5. Move File
    if (!move_uploaded_file($tmp_path, $upload_dir . $evidence_id . '_' . $original_filename)) {
        throw new Exception("File move failed");
    }

    // 6. Audit Trail
    $action = "Upload";
    $ip = substr($_SERVER['REMOTE_ADDR'], 0, 45);
    $stmt = $conn->prepare("INSERT INTO audit_trail (action, date_time, ip_address, User_id, Evidence_id, Case_id) VALUES (?, NOW(), ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssiii", $action, $ip, $user_id, $evidence_id, $case_id);
        $stmt->execute();
        $stmt->close();
    }

    // 7. Notification
    $msg = "Uploaded evidence: '$final_file_name' ($db_status)";
    $stmt = $conn->prepare("INSERT INTO notification (message, status, date, User_id, Evidence_id) VALUES (?, 'Unread', CURDATE(), ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sii", $msg, $user_id, $evidence_id);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    
    // Return the response (success or tampered)
    echo json_encode([
        "status" => $json_response_status, 
        "message" => $json_message,
        "evidence_id" => $evidence_id,
        "ai_result" => $db_status
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
