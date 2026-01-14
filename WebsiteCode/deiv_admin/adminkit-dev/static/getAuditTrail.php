<?php
header("Content-Type: application/json");
require "db_connect.php";

date_default_timezone_set("Asia/Kuala_Lumpur");

$sql = "SELECT id, user_id, action, timestamp, ip_address
        FROM audit_trail
        ORDER BY timestamp DESC";

$result = mysqli_query($conn, $sql);

$logs = [];

while ($row = mysqli_fetch_assoc($result)) {
    $logs[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $logs
]);