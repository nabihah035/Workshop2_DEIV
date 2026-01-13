<?php
// API endpoint: approve_user.php
// Expects POST: email, token
header('Content-Type: text/plain; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'error: invalid_method';
    exit;
}

// include DB connection (uses $conn as mysqli)
include __DIR__ . '/db_connect.php';
require_once __DIR__ . '/firebase.php';

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$token = isset($_POST['token']) ? trim($_POST['token']) : '';

if (empty($email)) {
    echo 'error: missing_email';
    exit;
}

try {
    $conn->begin_transaction();

    // find user id
    $stmt = $conn->prepare("SELECT User_id, status FROM user WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        echo 'error: user_not_found';
        exit;
    }
    $row = $res->fetch_assoc();
    $user_id = (int)$row['User_id'];
    $old_status = $row['status'];
    $stmt->close();

    // update local status to Active
    $upd = $conn->prepare("UPDATE user SET status = 'Active' WHERE User_id = ? LIMIT 1");
    $upd->bind_param('i', $user_id);
    if (!$upd->execute()) {
        $upd->close();
        $conn->rollback();
        echo 'error: db_update_failed';
        exit;
    }
    $upd->close();

    // create local notification record
    $message = "Your account has been approved by the administrator.";
    $notif = $conn->prepare("INSERT INTO notification (message, status, date, User_id) VALUES (?, 'Unread', CURDATE(), ?)");
    $notif->bind_param('si', $message, $user_id);
    if (!$notif->execute()) {
        // log but continue
        error_log('Notification insert failed: ' . $conn->error);
    }
    $notif->close();

    $conn->commit();

    // Send FCM if token present
    $fcmResult = null;
    if (!empty($token)) {
        $firebase = new FirebaseNotification();
        $title = 'Account Approved';
        $body = 'Your DEIV account has been approved. You can now login to the app.';
        $data = ['type' => 'account_approved'];
        $fcmResult = $firebase->sendToDevice($token, $title, $body, $data);
    }

    // Return success (frontend checks for substring "success")
    echo 'success';
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn) $conn->rollback();
    error_log('approve_user error: ' . $e->getMessage());
    echo 'error: exception';
    exit;
}

?>
