<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: ../index.php");
    exit;
}

// FIXED LINE: Changed the path to point to your config folder
require_once __DIR__ . '/../../config/db.php';

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid user ID";
    header("Location: user_management.php");
    exit;
}

$user_id = $_GET['id'];

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE User_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "User not found";
        header("Location: user_management.php");
        exit;
    }
    
    // Prevent editing admin users if not admin
    if ($user['role'] === 'Admin') {
        $_SESSION['error'] = "Cannot edit admin users";
        header("Location: user_management.php");
        exit;
    }
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';
    $organization = trim($_POST['organization'] ?? '');
    
    // Validate required fields
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($role)) {
        $errors[] = "Role is required";
    }
    
    if (empty($status)) {
        $errors[] = "Status is required";
    }
    
    // Check if username already exists (excluding current user)
    if (!empty($username)) {
        $check_stmt = $pdo->prepare("SELECT User_id FROM user WHERE username = ? AND User_id != ?");
        $check_stmt->execute([$username, $user_id]);
        if ($check_stmt->rowCount() > 0) {
            $errors[] = "Username already exists";
        }
    }
    
    // Check if email already exists (excluding current user)
    if (!empty($email)) {
        $check_stmt = $pdo->prepare("SELECT User_id FROM user WHERE email = ? AND User_id != ?");
        $check_stmt->execute([$email, $user_id]);
        if ($check_stmt->rowCount() > 0) {
            $errors[] = "Email already exists";
        }
    }
    
    // If no errors, update user
    if (empty($errors)) {
        try {
            $update_sql = "UPDATE user SET 
                          username = ?, 
                          email = ?, 
                          first_name = ?, 
                          last_name = ?, 
                          role = ?, 
                          status = ?, 
                          organization = ? 
                          WHERE User_id = ?";
            
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([
                $username,
                $email,
                $first_name,
                $last_name,
                $role,
                $status,
                $organization,
                $user_id
            ]);
            
            // Log the activity (you can implement an audit log system here)
            
            $_SESSION['success'] = "User updated successfully";
            header("Location: user_management.php");
            exit;
            
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
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
    <meta name="description" content="DEIV - Digital Evidence Integrity Verification System">
    <meta name="author" content="DEIV">
    <meta name="keywords" content="digital evidence, forensic, investigation, security">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" href="img/icons/icon-48x48.png" />
    <link rel="canonical" href="https://demo-basic.adminkit.io/" />

    <title>Edit User | DEIV Admin</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* ===== FIX SIDEBAR LIKE INDEX.PHP ===== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow: hidden;
            z-index: 1030;
        }

        .sidebar-content {
            height: 100%;
            overflow-y: auto;
        }

        .main {
            margin-left: 260px;
        }

        @media (max-width: 991.98px) {
            .main {
                margin-left: 0;
            }
        }
        
        /* Status Badges */
        .badge-status {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-active { background-color: #d1fae5; color: #065f46; }
        .badge-inactive { background-color: #f3f4f6; color: #374151; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-rejected { background-color: #fee2e2; color: #991b1b; }
        
        /* Form Styles */
        .form-card {
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .form-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .required-field::after {
            content: " *";
            color: #dc2626;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .back-btn:hover {
            color: #374151;
        }
        
        .back-btn .material-icons {
            font-size: 1rem;
            margin-right: 0.5rem;
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

                    <!-- Dashboard -->
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="index.php">
                            <i class="align-middle material-icons">home</i>
                            <span class="align-middle">Dashboard</span>
                        </a>
                    </li>

                    <!-- User Management -->
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="user_management.php">
                            <i class="align-middle material-icons">people</i>
                            <span class="align-middle">User Management</span>
                        </a>
                    </li>

                    <!-- Evidence Records -->
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="evidence_list.php">
                            <i class="align-middle material-icons">inventory_2</i>
                            <span class="align-middle">Evidence Records</span>
                        </a>
                    </li>

                    <!-- Evidence Metadata -->
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="metadata_list.php">
                            <i class="align-middle material-icons">list_alt</i>
                            <span class="align-middle">Evidence Metadata</span>
                        </a>
                    </li>

                    <!-- Case Files -->
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="case_list.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Case Files</span>
                        </a>
                    </li>

                    <!-- Audit Logs -->
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="audit_logs.php">
                            <i class="align-middle material-icons">history</i>
                            <span class="align-middle">Audit Logs</span>
                        </a>
                    </li>

                    <!-- Logout -->
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

               
            </nav>

            <main class="content">
                <div class="container-fluid p-0">
                    <!-- Back Button -->
                    <div class="mb-4">
                        <a href="user_management.php" class="back-btn">
                            <i class="material-icons">arrow_back</i>
                            Back to User Management
                        </a>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="material-icons align-middle">error</i>
                            <strong>Error!</strong>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="material-icons align-middle">error</i>
                            <?= $_SESSION['error'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="material-icons align-middle me-2">edit</i>
                                        Edit User
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-card">
                                        <div class="form-header">
                                            <h6 class="text-muted mb-0">Update user information below</h6>
                                        </div>
                                        
                                        <form method="POST" action="" novalidate>
                                            <div class="row">
                                                <!-- Username -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="username" class="form-label required-field">Username</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="material-icons">person</i>
                                                        </span>
                                                        <input 
                                                            type="text" 
                                                            class="form-control" 
                                                            id="username" 
                                                            name="username" 
                                                            value="<?= htmlspecialchars($user['username'] ?? '') ?>" 
                                                            required
                                                            placeholder="Enter username"
                                                        >
                                                    </div>
                                                    <div class="form-text">Unique username for login</div>
                                                </div>

                                                <!-- Email -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="email" class="form-label required-field">Email Address</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="material-icons">email</i>
                                                        </span>
                                                        <input 
                                                            type="email" 
                                                            class="form-control" 
                                                            id="email" 
                                                            name="email" 
                                                            value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                                            required
                                                            placeholder="Enter email address"
                                                        >
                                                    </div>
                                                    <div class="form-text">User's primary email</div>
                                                </div>

                                                <!-- First Name -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="first_name" class="form-label required-field">First Name</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="material-icons">badge</i>
                                                        </span>
                                                        <input 
                                                            type="text" 
                                                            class="form-control" 
                                                            id="first_name" 
                                                            name="first_name" 
                                                            value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" 
                                                            required
                                                            placeholder="Enter first name"
                                                        >
                                                    </div>
                                                </div>

                                                <!-- Last Name -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="last_name" class="form-label">Last Name</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="material-icons">badge</i>
                                                        </span>
                                                        <input 
                                                            type="text" 
                                                            class="form-control" 
                                                            id="last_name" 
                                                            name="last_name" 
                                                            value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" 
                                                            placeholder="Enter last name"
                                                        >
                                                    </div>
                                                </div>

                                                <!-- Role -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="role" class="form-label required-field">Role</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="material-icons">work</i>
                                                        </span>
                                                        <select class="form-select" id="role" name="role" required>
                                                            <option value="">Select Role</option>
                                                            <option value="Law agencies" <?= ($user['role'] ?? '') == 'Law agencies' ? 'selected' : '' ?>>Law agencies</option>
                                                            <option value="Digital Forensic Investigator" <?= ($user['role'] ?? '') == 'Digital Forensic Investigator' ? 'selected' : '' ?>>Digital Forensic Investigator</option>
                                                            <option value="Legal Professionals" <?= ($user['role'] ?? '') == 'Legal Professionals' ? 'selected' : '' ?>>Legal Professionals</option>
                                                            <option value="Institution" <?= ($user['role'] ?? '') == 'Institution' ? 'selected' : '' ?>>Institution</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Status -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="status" class="form-label required-field">Status</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="material-icons">check_circle</i>
                                                        </span>
                                                        <select class="form-select" id="status" name="status" required>
                                                            <option value="">Select Status</option>
                                                            <option value="Active" <?= ($user['status'] ?? '') == 'Active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="Inactive" <?= ($user['status'] ?? '') == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                                            <option value="Pending" <?= ($user['status'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                            <option value="Rejected" <?= ($user['status'] ?? '') == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Organization -->
                                                <div class="col-12 mb-3">
                                                    <label for="organization" class="form-label">Organization</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="material-icons">business</i>
                                                        </span>
                                                        <input 
                                                            type="text" 
                                                            class="form-control" 
                                                            id="organization" 
                                                            name="organization" 
                                                            value="<?= htmlspecialchars($user['organization'] ?? '') ?>" 
                                                            placeholder="Enter organization name"
                                                        >
                                                    </div>
                                                    <div class="form-text">Organization/Company name (optional)</div>
                                                </div>

                                                <!-- Created Date (Readonly) -->
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Created Date</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="material-icons">event</i>
                                                        </span>
                                                        <input 
                                                            type="text" 
                                                            class="form-control" 
                                                            value="<?= htmlspecialchars($user['created_at'] ?? 'N/A') ?>" 
                                                            readonly
                                                        >
                                                    </div>
                                                </div>

                                                <!-- User ID (Readonly) -->
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">User ID</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">
                                                            <i class="material-icons">fingerprint</i>
                                                        </span>
                                                        <input 
                                                            type="text" 
                                                            class="form-control" 
                                                            value="<?= htmlspecialchars($user['User_id'] ?? '') ?>" 
                                                            readonly
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-4 pt-3 border-top">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="text-muted small">
                                                            <i class="material-icons align-middle" style="font-size: 0.875rem;">info</i>
                                                            All required fields are marked with *
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <a href="user_management.php" class="btn btn-outline-secondary me-2">
                                                            <i class="material-icons align-middle me-1">cancel</i>
                                                            Cancel
                                                        </a>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="material-icons align-middle me-1">save</i>
                                                            Update User
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

           
        </div>
    </div>

    <script src="js/app.js"></script>
    <!-- REQUIRED FOR MODAL -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                field.classList.remove('is-invalid');
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
        
        // Add visual feedback for required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        });
    });
    </script>

</body>
</html>