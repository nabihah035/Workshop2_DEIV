<?php
session_start();

if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: index.php");
    exit;
}

require_once dirname(dirname(__DIR__)) . '/config/db.php';

// Fetch pending users for notifications
$pending_users = $pdo->query("SELECT User_id, username, email, created_at FROM user WHERE status='Pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count($pending_users);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username']);
    $email        = trim($_POST['email']);
    $password     = $_POST['password'];
    $first_name   = trim($_POST['first_name']);
    $last_name    = trim($_POST['last_name']);
    $role         = $_POST['role'];
    $status       = $_POST['status'];
    $organization = trim($_POST['organization']);

    if ($username && $password && $role && $status) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Start transaction
            $pdo->beginTransaction();

            // Check if username already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Username already exists");
            }

            // Check if email already exists
            if ($email) {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE email = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("Email already exists");
                }
            }

            // Insert user into MySQL
            $stmt = $pdo->prepare("
                INSERT INTO user
                (username, password, email, first_name, last_name, role, status, organization)
                VALUES
                (:username, :password, :email, :first_name, :last_name, :role, :status, :organization)
            ");

            $stmt->execute([
                ':username' => $username,
                ':password' => $hashedPassword,
                ':email' => $email,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':role' => $role,
                ':status' => $status,
                ':organization' => $organization
            ]);
            
            // Get the MySQL user ID
            $mysql_user_id = $pdo->lastInsertId();

            // // Add audit trail
            // $user_id = $_SESSION['User_id'];
            // $action = "Add User: $username";
            // $ip_address = $_SERVER['REMOTE_ADDR'];
            
            // // Check audit_trail table structure
            // $columns = $pdo->query("SHOW COLUMNS FROM audit_trail")->fetchAll(PDO::FETCH_COLUMN);
            
            // if (in_array('description', $columns)) {
            //     $audit_stmt = $pdo->prepare("
            //         INSERT INTO audit_trail (User_id, action, description, ip_address)
            //         VALUES (:user_id, :action, :description, :ip_address)
            //     ");
                
            //     $audit_stmt->execute([
            //         ':user_id' => $user_id,
            //         ':action' => 'Add User',
            //         ':description' => "Added new user: $username ($role)",
            //         ':ip_address' => $ip_address
            //     ]);
            // } else {
            //     $audit_stmt = $pdo->prepare("
            //         INSERT INTO audit_trail (User_id, action, ip_address)
            //         VALUES (:user_id, :action, :ip_address)
            //     ");
                
            //     $audit_stmt->execute([
            //         ':user_id' => $user_id,
            //         ':action' => "Added new user: $username ($role)",
            //         ':ip_address' => $ip_address
            //     ]);
            // }

            $pdo->commit();

            // Prepare data for JavaScript Firebase sync
            $firebase_data = [
                'mysql_id' => $mysql_user_id,
                'username' => $username,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => $role,
                'status' => strtolower($status),
                'organization' => $organization,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="align-middle me-2" data-feather="check-circle"></i>
                <strong>Success!</strong> User successfully added to MySQL database.
                <div id="firebase-sync-message" class="mt-2"></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';

            // Store Firebase data in session for JavaScript
            $_SESSION['new_user_firebase_data'] = $firebase_data;
            $_SESSION['new_user_mysql_id'] = $mysql_user_id;

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="align-middle me-2" data-feather="alert-circle"></i>
                <strong>Error!</strong> ' . htmlspecialchars($e->getMessage()) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Responsive Admin &amp; Dashboard Template based on Bootstrap 5">
    <meta name="author" content="AdminKit">
    <meta name="keywords" content="adminkit, bootstrap, bootstrap 5, admin, dashboard, template, responsive, css, sass, html, theme, front-end, ui kit, web">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" href="img/icons/icon-48x48.png" />
    <link rel="canonical" href="https://demo-basic.adminkit.io/" />

    <title>Add User | DEIV ADMIN</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-firestore-compat.js"></script>

    <style>
        .form-card {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem 2rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 6px;
        }
        
        /* Enhanced spacing */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: width 0.3s, background-color 0.3s;
        }
        
        /* Firebase sync indicator */
        .firebase-sync {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background-color: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 1rem;
            border-left: 4px solid #4285f4;
        }
        
        .firebase-sync i {
            color: #4285f4;
            margin-right: 10px;
        }
        
        .firebase-sync span {
            font-size: 0.9rem;
            color: #5f6368;
        }
        
        /* Loading spinner */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <nav id="sidebar" class="sidebar js-sidebar">
            <div class="sidebar-content js-simplebar">
                <a class="sidebar-brand" href="index.php">
                    <span class="align-middle">DEIV ADMIN</span>
                </a>

                <ul class="sidebar-nav">
                    <li class="sidebar-header">
                        Navigation
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="index.php">
                            <i class="align-middle material-icons">home</i>
                            <span class="align-middle">Dashboard</span>
                        </a>
                    </li>

                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="user_management.php">
                            <i class="align-middle material-icons">people</i>
                            <span class="align-middle">User Management</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="case_list.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Case Files</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="evidence_list.php">
                            <i class="align-middle material-icons">inventory_2</i>
                            <span class="align-middle">Evidence Records</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="metadata_list.php">
                            <i class="align-middle material-icons">list_alt</i>
                            <span class="align-middle">Evidence Metadata</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="case_list.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Case Files</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="audit_logs.php">
                            <i class="align-middle material-icons">history</i>
                            <span class="align-middle">Audit Logs</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="logout.php">
                            <i class="align-middle material-icons">logout</i>
                            <span class="align-middle text-danger">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="main">
            <nav class="navbar navbar-expand navbar-light navbar-bg">
                <a class="sidebar-toggle js-sidebar-toggle">
                    <i class="hamburger align-self-center"></i>
                </a>

                <div class="navbar-collapse collapse">
                    <ul class="navbar-nav navbar-align">
                        
                    </ul>
                </div>
            </nav>

            <main class="content">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0"><strong>Add New User</strong></h1>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card form-card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0 text-white">
                                        <i class="align-middle me-2" data-feather="user-plus"></i>
                                        User Information
                                    </h5>
                                </div>
                                
                                <div class="card-body">
                                    <!-- <div class="firebase-sync">
                                        <i class="align-middle" data-feather="database"></i>
                                        <span>This user will be added to MySQL database and automatically synced with Firebase for real-time access.</span>
                                    </div> -->
                                    
                                    <?= $message ?>
                                    
                                    <form method="POST" class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required-field">Username</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="align-middle" data-feather="user"></i>
                                                    </span>
                                                    <input type="text" 
                                                           name="username" 
                                                           class="form-control" 
                                                           required
                                                           placeholder="Enter username"
                                                           onblur="checkUsernameAvailability(this.value)">
                                                    <div class="invalid-feedback" id="username-feedback">
                                                        Please enter a username.
                                                    </div>
                                                </div>
                                                <small class="text-muted" id="username-availability"></small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Email Address</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">@</span>
                                                    <input type="email" 
                                                           name="email" 
                                                           class="form-control" 
                                                           placeholder="Enter email address"
                                                           onblur="checkEmailAvailability(this.value)">
                                                    <div class="invalid-feedback" id="email-feedback">
                                                        Please enter a valid email address.
                                                    </div>
                                                </div>
                                                <small class="text-muted" id="email-availability"></small>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label required-field">Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="align-middle" data-feather="lock"></i>
                                                </span>
                                                <input type="password" 
                                                       name="password" 
                                                       id="password"
                                                       class="form-control" 
                                                       required
                                                       placeholder="Enter password"
                                                       onkeyup="checkPasswordStrength(this.value)">
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                    <i class="align-middle" data-feather="eye"></i>
                                                </button>
                                                <div class="invalid-feedback">
                                                    Please enter a password.
                                                </div>
                                            </div>
                                            <div class="password-strength mt-2">
                                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                            </div>
                                            <small class="text-muted">Password must be at least 8 characters long</small>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">First Name</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="align-middle" data-feather="user"></i>
                                                    </span>
                                                    <input type="text" 
                                                           name="first_name" 
                                                           class="form-control" 
                                                           placeholder="Enter first name">
                                                </div>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Last Name</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="align-middle" data-feather="user"></i>
                                                    </span>
                                                    <input type="text" 
                                                           name="last_name" 
                                                           class="form-control" 
                                                           placeholder="Enter last name">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Organization</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="align-middle" data-feather="briefcase"></i>
                                                </span>
                                                <input type="text" 
                                                       name="organization" 
                                                       class="form-control" 
                                                       placeholder="Enter organization name">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required-field">Role</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="align-middle" data-feather="shield"></i>
                                                    </span>
                                                    <select name="role" class="form-select" required>
                                                        <option value="">Select a role</option>
                                                        <option value="Admin">Admin</option>
                                                        <option value="Law agencies">Law Agencies</option>
                                                        <option value="Digital Forensic Investigator">Digital Forensic Investigator</option>
                                                        <option value="Legal Professionals">Legal Professionals</option>
                                                        <option value="Institution">Institution</option>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Please select a role.
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required-field">Status</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="align-middle" data-feather="activity"></i>
                                                    </span>
                                                    <select name="status" class="form-select" required>
                                                        <option value="Active">Active</option>
                                                        <option value="Inactive">Inactive</option>
                                                        <option value="Pending" selected>Pending</option>
                                                        <option value="Rejected">Rejected</option>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Please select a status.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                            <a href="user_management.php" class="btn btn-secondary">
                                                <i class="align-middle me-1" data-feather="arrow-left"></i>
                                                Back to User Management
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="align-middle me-1" data-feather="user-plus"></i>
                                                Add User
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="js/app.js"></script>
    
    <script>
        // Firebase Configuration (same as in user_management.php)
        const firebaseConfig = {
            apiKey: "AIzaSyDpjUo6GfJEnxTwKBjnfzpkQruSzWvov-I", // PASTE YOUR FULL KEY HERE
            authDomain: "deiv-ac114.firebaseapp.com",
            projectId: "deiv-ac114",
            storageBucket: "deiv-ac114.firebasestorage.app",
            messagingSenderId: "846119732034",
            appId: "1:846119732034:web:213dfed9358f6d38773edb",
            measurementId: "G-V591C9RQJ4"
        };

        // Initialize Firebase
        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }
        const db = firebase.firestore();

        // Function to sync user to Firebase
        function syncUserToFirebase(userData, mysqlId) {
            return new Promise((resolve, reject) => {
                // Generate a Firebase document ID (you can use MySQL ID or generate new)
                const firebaseId = 'user_' + mysqlId + '_' + Date.now();
                
                db.collection("users").doc(firebaseId).set({
                    mysql_id: mysqlId,
                    username: userData.username,
                    email: userData.email || '',
                    first_name: userData.first_name || '',
                    last_name: userData.last_name || '',
                    role: userData.role,
                    status: userData.status.toLowerCase(),
                    organization: userData.organization || '',
                    created_at: userData.created_at,
                    updated_at: new Date().toISOString()
                })
                .then(() => {
                    console.log("User synced to Firebase successfully");
                    resolve(true);
                })
                .catch((error) => {
                    console.error("Error syncing to Firebase:", error);
                    reject(error);
                });
            });
        }

        // Check if we need to sync with Firebase after form submission
        <?php if (isset($_SESSION['new_user_firebase_data'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const userData = <?= json_encode($_SESSION['new_user_firebase_data']) ?>;
            const mysqlId = <?= $_SESSION['new_user_mysql_id'] ?? 'null' ?>;
            
            if (userData && mysqlId) {
                const syncMessage = document.getElementById('firebase-sync-message');
                if (syncMessage) {
                    syncMessage.innerHTML = `
                        <div class="d-flex align-items-center">
                            <span class="me-2">Syncing with Firebase...</span>
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    `;
                    
                    syncUserToFirebase(userData, mysqlId)
                        .then(() => {
                            syncMessage.innerHTML = `
                                <div class="text-success">
                                    <i class="align-middle me-1" data-feather="check-circle"></i>
                                    Successfully synced with Firebase!
                                </div>
                            `;
                            feather.replace();
                        })
                        .catch((error) => {
                            syncMessage.innerHTML = `
                                <div class="text-warning">
                                    <i class="align-middle me-1" data-feather="alert-triangle"></i>
                                    User added to MySQL but Firebase sync failed. Error: ${error.message}
                                </div>
                            `;
                            feather.replace();
                        });
                    
                    // Clear session data
                    <?php 
                    unset($_SESSION['new_user_firebase_data']);
                    unset($_SESSION['new_user_mysql_id']);
                    ?>
                }
            }
        });
        <?php endif; ?>

        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
        
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.setAttribute('data-feather', 'eye-off');
            } else {
                passwordInput.type = 'password';
                icon.setAttribute('data-feather', 'eye');
            }
            feather.replace();
        });
        
        // Password strength indicator
        function checkPasswordStrength(password) {
            const bar = document.getElementById('passwordStrengthBar');
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            bar.style.width = strength + '%';
            
            if (strength < 50) {
                bar.style.backgroundColor = '#dc3545';
            } else if (strength < 75) {
                bar.style.backgroundColor = '#ffc107';
            } else {
                bar.style.backgroundColor = '#28a745';
            }
        }
        
        // Check username availability
        function checkUsernameAvailability(username) {
            if (username.length < 3) return;
            
            const feedback = document.getElementById('username-availability');
            feedback.innerHTML = '<span class="text-muted">Checking availability...</span>';
            
            fetch('check_username.php?username=' + encodeURIComponent(username))
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        feedback.innerHTML = '<span class="text-danger">Username already taken</span>';
                        document.querySelector('input[name="username"]').classList.add('is-invalid');
                    } else {
                        feedback.innerHTML = '<span class="text-success">Username available</span>';
                        document.querySelector('input[name="username"]').classList.remove('is-invalid');
                    }
                })
                .catch(() => {
                    feedback.innerHTML = '';
                });
        }
        
        // Check email availability
        function checkEmailAvailability(email) {
            if (!email || email.length < 5) return;
            
            const feedback = document.getElementById('email-availability');
            feedback.innerHTML = '<span class="text-muted">Checking availability...</span>';
            
            fetch('check_email.php?email=' + encodeURIComponent(email))
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        feedback.innerHTML = '<span class="text-danger">Email already registered</span>';
                        document.querySelector('input[name="email"]').classList.add('is-invalid');
                    } else {
                        feedback.innerHTML = '<span class="text-success">Email available</span>';
                        document.querySelector('input[name="email"]').classList.remove('is-invalid');
                    }
                })
                .catch(() => {
                    feedback.innerHTML = '';
                });
        }
        
        // Initialize feather icons
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
        });
    </script>

</body>
</html>