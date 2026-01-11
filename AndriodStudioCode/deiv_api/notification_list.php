<?php
error_reporting(0); // This stops warnings from breaking your JSON
include 'db_connect.php'; // Changed from db.php
header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'unread';

if ($type == 'unread') {
    // Make sure your table is named 'notification' and column is 'User_id'
    $sql = "SELECT * FROM notification WHERE User_id = ? AND status = 'Unread' ORDER BY date DESC";
} else {
    $sql = "SELECT * FROM notification WHERE User_id = ? AND status = 'Read' ORDER BY date DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    "success" => true,
    "notifications" => $data
]);
?>