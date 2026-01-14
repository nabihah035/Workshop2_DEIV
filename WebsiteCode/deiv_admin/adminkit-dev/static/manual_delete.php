<?php
// manual_delete.php
require_once dirname(dirname(__DIR__)) . '/config/db.php';

$user_id = 18; // From your example
$email = 'haland@gmail.com';

echo "<h2>Manual Delete Test</h2>";

// Check user exists
$stmt = $pdo->prepare("SELECT * FROM user WHERE User_id = ? OR email = ?");
$stmt->execute([$user_id, $email]);
$user = $stmt->fetch();

if ($user) {
    echo "User found:<pre>";
    print_r($user);
    echo "</pre>";
    
    // Delete the user
    $deleteStmt = $pdo->prepare("DELETE FROM user WHERE User_id = ?");
    $deleteStmt->execute([$user_id]);
    
    if ($deleteStmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ User deleted successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Delete failed - trying different field...</p>";
        
        // Try by id field
        $deleteStmt = $pdo->prepare("DELETE FROM user WHERE id = ?");
        $deleteStmt->execute([$user_id]);
        
        if ($deleteStmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ User deleted by 'id' field!</p>";
        } else {
            echo "<p style='color: red;'>❌ Delete failed completely</p>";
        }
    }
} else {
    echo "<p style='color: orange;'>⚠️ User not found</p>";
}

// Show all users to verify
echo "<hr><h3>Remaining Users</h3>";
$allUsers = $pdo->query("SELECT User_id, username, email, status FROM user")->fetchAll();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>User_id</th><th>Username</th><th>Email</th><th>Status</th></tr>";
foreach ($allUsers as $u) {
    echo "<tr>";
    echo "<td>{$u['User_id']}</td>";
    echo "<td>{$u['username']}</td>";
    echo "<td>{$u['email']}</td>";
    echo "<td>{$u['status']}</td>";
    echo "</tr>";
}
echo "</table>";
?>