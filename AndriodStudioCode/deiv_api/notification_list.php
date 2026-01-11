<?php
include 'db.php';

$user_id = $_GET['user_id'];
$type = $_GET['type']; // read or unread

if ($type == 'unread') {
    $sql = "SELECT * FROM notification 
            WHERE User_id = ? AND status = 'Unread'
            ORDER BY date DESC";
} else {
    $sql = "SELECT * FROM notification 
            WHERE User_id = ? AND status = 'Read'
            ORDER BY date DESC";
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
