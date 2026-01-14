<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit;
}
require_once '../deiv_api/db.php';

$message = '';

/* ========================================================
   GENERATE DISPLAY ID BASED ON ROLE + User_id
   ======================================================== */
function generateDisplayID($role, $userId) {
    $prefixMap = [
        'Admin' => 'A',
        'Law agencies' => 'B',
        'Digital Forensic Investigator' => 'C',
        'Legal Professionals' => 'D',
        'Institution' => 'E'
    ];

    if (!isset($prefixMap[$role])) {
        return $userId;
    }

    return $prefixMap[$role] . str_pad($userId, 4, "0", STR_PAD_LEFT);
}

// Get user_id from GET
if(!isset($_GET['id'])) {
    header("Location: user_list.php");
    exit;
}

$userId = $_GET['id'];

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE User_id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user) {
        header("Location: user_list.php");
        exit;
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission
if(isset($_POST['submit'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role = $_POST['role'];
    $organization = trim($_POST['organization']);

    // Password update (optional)
    if(!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_sql = ", password = :password";
    } else {
        $password_sql = "";
    }

    try {
        $sql = "UPDATE user SET username = :username, email = :email, first_name = :first_name,
                last_name = :last_name, role = :role, organization = :organization $password_sql
                WHERE User_id = :id";
        $stmt = $pdo->prepare($sql);

        $params = [
            ':username' => $username,
            ':email' => $email,
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':role' => $role,
            ':organization' => $organization,
            ':id' => $userId
        ];

        if(!empty($_POST['password'])) {
            $params[':password'] = $password;
        }

        $stmt->execute($params);

        // Generate updated Display ID
        $displayID = generateDisplayID($role, $userId);
        $message = "User updated successfully. Display ID: $displayID";

        // Refresh user data
        $user['username'] = $username;
        $user['email'] = $email;
        $user['first_name'] = $first_name;
        $user['last_name'] = $last_name;
        $user['role'] = $role;
        $user['organization'] = $organization;

    } catch(PDOException $e) {
        $message = "Database error: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="bg-gray-100">

<div class="flex">

    <!-- SIDEBAR -->
    <aside class="w-72 bg-white shadow-lg h-screen p-6">
        <h2 class="text-xl font-bold mb-8">DEIV ADMIN</h2>

        <nav class="space-y-2">
            <a href="dashboard.php" class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">home</span> Dashboard
            </a>
            <a href="user_list.php" class="flex items-center p-3 bg-blue-600 text-white rounded-lg">
                <span class="material-icons mr-3">people</span> User Management
            </a>
            <a href="evidence_list.php" class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">inventory_2</span> Evidence Records
            </a>

            <a href="metadata_list.php" class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">list_alt</span> Evidence Metadata
            </a>

            <a href="case_list.php" class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">folder</span> Case Files
            </a>

            <a href="audit_logs.php" class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">history</span> Audit Logs
            </a>

            <a href="logout.php" class="flex items-center p-3 hover:bg-gray-100 rounded-lg text-red-600">
                <span class="material-icons mr-3">logout</span> Logout
            </a>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
<main class="flex-1 p-8">
    <h1 class="text-3xl font-bold mb-6">Edit User</h1>

    <!-- Back Button -->
    <a href="user_list.php" class="btn btn-secondary mb-4">
        <span class="material-icons align-middle">arrow_back</span> Back to User Management
    </a>

    <?php if($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

   

    <form method="POST" class="bg-white p-6 rounded shadow-md">
        <div class="mb-3">
            <label>Username *</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']); ?>" required>
        </div>
        <div class="mb-3">
            <label>Password (leave blank to keep current)</label>
            <input type="password" name="password" class="form-control">
        </div>
        <div class="mb-3">
            <label>Email *</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" required>
        </div>
        <div class="mb-3">
            <label>First Name</label>
            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']); ?>">
        </div>
        <div class="mb-3">
            <label>Last Name</label>
            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']); ?>">
        </div>
        <div class="mb-3">
            <label>Role *</label>
            <select name="role" class="form-control" required>
                <option value="">-- Select Role --</option>
                <option value="Law agencies" <?= $user['role']=='Law agencies'?'selected':'' ?>>Law agencies</option>
                <option value="Digital Forensic Investigator" <?= $user['role']=='Digital Forensic Investigator'?'selected':'' ?>>Digital Forensic Investigator</option>
                <option value="Legal Professionals" <?= $user['role']=='Legal Professionals'?'selected':'' ?>>Legal Professionals</option>
                <option value="Institution" <?= $user['role']=='Institution'?'selected':'' ?>>Institution</option>
            </select>
        </div>
        <div class="mb-3">
            <label>Organization</label>
            <input type="text" name="organization" class="form-control" value="<?= htmlspecialchars($user['organization']); ?>">
        </div>
        <button type="submit" name="submit" class="btn btn-primary">Update User</button>
    </form>
</main>

</div>
</body>
</html>
