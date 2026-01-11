<?php
include 'db_connect.php';

$notification_id = $_POST['notification_id'];

$sql = "UPDATE notification 
        SET status = 'Read' 
        WHERE Notification_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false]);
}
