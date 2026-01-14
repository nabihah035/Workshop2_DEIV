<?php
error_reporting(0);
include 'db_connect.php'; // Changed from db.php
header('Content-Type: application/json');

$notification_id = isset($_POST['notification_id']) ? $_POST['notification_id'] : 0;

if ($notification_id > 0) {
    $sql = "UPDATE notification SET status = 'Read' WHERE Notification_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notification_id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Database update failed"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid notification ID"]);
}
?>