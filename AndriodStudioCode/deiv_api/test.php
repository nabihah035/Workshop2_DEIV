<?php
// test.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

echo json_encode([
    "status" => "success",
    "message" => "DEIV API is working",
    "timestamp" => date("Y-m-d H:i:s"),
    "version" => "1.0"
]);
?>