<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Allow bypass for local testing by sending `testing=1` in POST
$testing = (($_GET['testing'] ?? $_POST['testing'] ?? '') === '1');
if (!$testing && (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

require_once __DIR__ . '/../../admin/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$id = $_POST['id'] ?? null;
if (empty($id) || !is_numeric($id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

try {
    // fetch existing record
    $stmt = $pdo->prepare("SELECT username, email FROM user WHERE User_id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $old_email = $old['email'];
    $old_username = $old['username'];

    // Collect update values
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $organization = trim($_POST['organization'] ?? '');

    // Optional password
    $password_sql = '';
    $params = [
        ':username' => $username,
        ':email' => $email,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':role' => $role,
        ':organization' => $organization,
        ':id' => $id
    ];

    if (!empty($_POST['password'])) {
        $params[':password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_sql = ", password = :password";
    }

    $sql = "UPDATE user SET username = :username, email = :email, first_name = :first_name, last_name = :last_name, role = :role, organization = :organization" . $password_sql . " WHERE User_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'old_email' => $old_email,
        'old_username' => $old_username,
        'new_email' => $email,
        'new_username' => $username
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error']);
}

exit;
