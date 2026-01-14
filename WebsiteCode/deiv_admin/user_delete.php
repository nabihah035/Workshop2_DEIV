<?php
session_start();

// Redirect to login if admin not logged in
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit;
}

require_once '../deiv_api/db.php';

// Check if id is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: user_list.php");
    exit;
}

$user_id = $_GET['id'];

// Fetch user data to display
try {
    $stmt = $pdo->prepare("SELECT username, first_name, last_name FROM user WHERE User_id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user) {
        header("Location: user_list.php");
        exit;
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle deletion after confirmation
if(isset($_POST['confirm'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM user WHERE User_id = :id");
        $stmt->execute([':id' => $user_id]);
        header("Location: user_list.php?msg=User+deleted+successfully");
        exit;
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete User</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">

<div class="bg-white p-6 rounded shadow-md w-96 text-center">
    <h2 class="text-xl font-bold mb-4 text-red-600">Confirm Delete</h2>
    <p class="mb-4">Are you sure you want to delete the user:</p>
    <p class="font-semibold mb-4"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['username']) ?>)</p>

    <?php if(isset($error)): ?>
        <div class="bg-red-100 text-red-700 p-2 mb-4 rounded"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" class="flex justify-around">
        <button type="submit" name="confirm" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded">Yes, Delete</button>
        <a href="user_list.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded">No, Cancel</a>
    </form>
</div>

</body>
</html>
