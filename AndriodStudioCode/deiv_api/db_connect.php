<?php
$host = "localhost";
$user = "root"; 
$pass = ""; 
$db   = "deiv";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

mysqli_set_charset($conn, "utf8");
?>