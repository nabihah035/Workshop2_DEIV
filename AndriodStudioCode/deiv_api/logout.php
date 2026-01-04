<?php
session_start();
header("Content-Type: application/json");

// Destroy PHP session
session_unset();
session_destroy();

echo json_encode([
    "status" => "success",
    "message" => "Logged out successfully"
]);
?>
