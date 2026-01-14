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

        if(isset($_POST['submit'])) {
            $username = trim($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $email = trim($_POST['email']);
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $role = $_POST['role'];
            $organization = trim($_POST['organization']);

            if(empty($username) || empty($password) || empty($email) || empty($role)) {
                $message = "Please fill in all required fields.";
            } else {
                try {
                    // Insert user into DB
                    $stmt = $pdo->prepare("INSERT INTO user (username, password, email, first_name, last_name, role, status, organization) 
                                        VALUES (:username, :password, :email, :first_name, :last_name, :role, 'Pending', :organization)");
                    $stmt->execute([
                        ':username' => $username,
                        ':password' => $password,
                        ':email' => $email,
                        ':first_name' => $first_name,
                        ':last_name' => $last_name,
                        ':role' => $role,
                        ':organization' => $organization
                    ]);

                    // Get last inserted User_id
                    $lastId = $pdo->lastInsertId();

                    // Generate display ID
                    $displayID = generateDisplayID($role, $lastId);

                    $message = "User added successfully (Display ID: $displayID) and pending approval.";
                } catch(PDOException $e) {
                    $message = "Database error: " . $e->getMessage();
                }
            }
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Add User</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        </head>
        <body class="bg-gray-100">

        <div class="flex">

            <!-- ====== SIDEBAR ====== -->
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

            <!-- ====== MAIN CONTENT ====== -->
        <main class="flex-1 p-8">
            <h1 class="text-3xl font-bold mb-6">Add User</h1>

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
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="">-- Select Role --</option>
                        <option value="Law agencies">Law agencies</option>
                        <option value="Digital Forensic Investigator">Digital Forensic Investigator</option>
                        <option value="Legal Professionals">Legal Professionals</option>
                        <option value="Institution">Institution</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Organization</label>
                    <input type="text" name="organization" class="form-control">
                </div>
                <button type="submit" name="submit" class="btn btn-primary">Add User</button>
            </form>
        </main>

        </div>
        </body>
        </html>
