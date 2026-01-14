<?php
// case_edit.php
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['User_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get the absolute path to config folder
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: case_list.php");
    exit();
}

$case_id = (int)$_GET['id'];

// Fetch the case details
$stmt = $pdo->prepare("
    SELECT c.*, u.username, u.first_name, u.last_name 
    FROM case_table c 
    LEFT JOIN user u ON c.User_id = u.User_id 
    WHERE c.Case_id = ?
");
$stmt->execute([$case_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if case exists
if (!$case) {
    header("Location: case_list.php");
    exit();
}

// Fetch all active users for assignment dropdown
$users = $pdo->query("SELECT User_id, username, first_name, last_name, role FROM user WHERE status = 'Active' ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $case_name = trim($_POST['case_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $assigned_user_id = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
    
    // Validation
    if (empty($case_name)) {
        $errors[] = "Case name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($status) || !in_array($status, ['In Progress', 'Complete', 'Closed', 'Pending'])) {
        $errors[] = "Valid status is required";
    }
    
    // If no errors, update the case
    if (empty($errors)) {
        try {
            $update_stmt = $pdo->prepare("
                UPDATE case_table 
                SET case_name = ?, 
                    description = ?, 
                    status = ?, 
                    User_id = ?,
                    updated_at = NOW()
                WHERE Case_id = ?
            ");
            
            $update_stmt->execute([
                $case_name,
                $description,
                $status,
                $assigned_user_id,
                $case_id
            ]);
            
            // Log the action
            // $user_id = $_SESSION['User_id'];
            // $action = "Updated case #$case_id";
            // $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
            // $log_stmt->execute([$user_id, $action]);
            
            $success = true;
            
            // Fetch updated case data
            $stmt->execute([$case_id]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $errors[] = "Error updating case: " . $e->getMessage();
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

    <title>DEIV ADMIN - Edit Case</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-in-progress {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .status-complete {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .status-closed {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .case-info-card {
            border-left: 4px solid #0d6efd;
        }
        
        .case-info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .case-info-value {
            color: #6c757d;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        .required:after {
            content: " *";
            color: #dc3545;
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
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="user_management.php">
                            <i class="align-middle material-icons">people</i>
                            <span class="align-middle">User Management</span>
                        </a>
                    </li>

                     <!-- Case Files -->
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="case_list.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Case Files</span>
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

                    <!-- Report Files -->
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="reportlist.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Report Management</span>
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

                <div class="navbar-collapse collapse">
                    <ul class="navbar-nav navbar-align">
                        <li class="nav-item">
                            <a class="nav-link" href="case_list.php">
                                <i class="align-middle material-icons">arrow_back</i>
                                Back to Cases
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="content">
                <div class="container-fluid p-0">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="card-title mb-0">
                                                <i class="align-middle me-2" data-feather="edit"></i>
                                                Edit Case #<?= $case_id ?>
                                            </h5>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="case_list.php" class="btn btn-outline-secondary">
                                                <i class="align-middle me-1" data-feather="arrow-left"></i>
                                                Back to List
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <!-- Success Message -->
                                    <?php if($success): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="align-middle me-2" data-feather="check-circle"></i>
                                            Case updated successfully!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Error Messages -->
                                    <?php if(!empty($errors)): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="align-middle me-2" data-feather="alert-circle"></i>
                                            <strong>Please fix the following errors:</strong>
                                            <ul class="mb-0 mt-1">
                                                <?php foreach($errors as $error): ?>
                                                    <li><?= htmlspecialchars($error) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <!-- Case Information -->
                                        <div class="col-md-4">
                                            <div class="card case-info-card mb-4">
                                                <div class="card-header">
                                                    <h6 class="mb-0">Case Information</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <div class="case-info-label">Case ID</div>
                                                        <div class="case-info-value">#<?= $case_id ?></div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="case-info-label">Created At</div>
                                                        <div class="case-info-value"><?= date('F j, Y, g:i a', strtotime($case['created_at'])) ?></div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="case-info-label">Last Updated</div>
                                                        <div class="case-info-value"><?= date('F j, Y, g:i a', strtotime($case['updated_at'])) ?></div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="case-info-label">Current Status</div>
                                                        <div class="case-info-value">
                                                            <?php
                                                            $status_class = '';
                                                            $case_status = $case['status'] ?? 'Pending';
                                                            switch($case_status) {
                                                                case 'In Progress':
                                                                    $status_class = 'status-in-progress';
                                                                    break;
                                                                case 'Complete':
                                                                    $status_class = 'status-complete';
                                                                    break;
                                                                case 'Closed':
                                                                    $status_class = 'status-closed';
                                                                    break;
                                                                case 'Pending':
                                                                default:
                                                                    $status_class = 'status-pending';
                                                            }
                                                            ?>
                                                            <span class="status-badge <?= $status_class ?>">
                                                                <?= $case_status ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="case-info-label">Assigned To</div>
                                                        <div class="case-info-value">
                                                            <?php if($case['username']): ?>
                                                                <strong><?= htmlspecialchars($case['username']) ?></strong><br>
                                                                <small class="text-muted">
                                                                    <?= htmlspecialchars($case['first_name'] ?? '') ?> <?= htmlspecialchars($case['last_name'] ?? '') ?>
                                                                </small>
                                                            <?php else: ?>
                                                                <span class="text-muted">Unassigned</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Edit Form -->
                                        <div class="col-md-8">
                                            <form method="POST" action="">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h6 class="mb-0">Edit Case Details</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="mb-3">
                                                            <label for="case_name" class="form-label required">Case Name</label>
                                                            <input type="text" 
                                                                   class="form-control" 
                                                                   id="case_name" 
                                                                   name="case_name" 
                                                                   value="<?= htmlspecialchars($case['case_name'] ?? '') ?>"
                                                                   required
                                                                   maxlength="255">
                                                            <div class="form-text">Enter a descriptive name for this case</div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="description" class="form-label required">Description</label>
                                                            <textarea class="form-control" 
                                                                      id="description" 
                                                                      name="description" 
                                                                      rows="4"
                                                                      required><?= htmlspecialchars($case['description'] ?? '') ?></textarea>
                                                            <div class="form-text">Provide detailed information about this case</div>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label for="status" class="form-label required">Status</label>
                                                                <select class="form-select" id="status" name="status" required>
                                                                    <option value="Pending" <?= ($case['status'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                                    <option value="In Progress" <?= ($case['status'] ?? '') == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                                                    <option value="Complete" <?= ($case['status'] ?? '') == 'Complete' ? 'selected' : '' ?>>Complete</option>
                                                                    <option value="Closed" <?= ($case['status'] ?? '') == 'Closed' ? 'selected' : '' ?>>Closed</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="col-md-6 mb-3">
                                                                <label for="assigned_user_id" class="form-label">Assign To User</label>
                                                                <select class="form-select" id="assigned_user_id" name="assigned_user_id">
                                                                    <option value="">-- Unassigned --</option>
                                                                    <?php foreach($users as $user): ?>
                                                                        <option value="<?= $user['User_id'] ?>" 
                                                                                <?= ($case['User_id'] ?? '') == $user['User_id'] ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($user['username']) ?> 
                                                                            (<?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?>)
                                                                            - <?= htmlspecialchars($user['role']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <div class="form-text">Assign this case to a specific user</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card-footer">
                                                        <div class="d-flex justify-content-between">
                                                            <a href="case_list.php" class="btn btn-secondary">
                                                                <i class="align-middle me-1" data-feather="x"></i>
                                                                Cancel
                                                            </a>
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="align-middle me-1" data-feather="save"></i>
                                                                Save Changes
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <!-- Quick Actions -->
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0">Quick Actions</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <a href="evidence_list.php?case_id=<?= $case_id ?>" class="btn btn-outline-info">
                                                            <i class="align-middle me-1" data-feather="file-text"></i>
                                                            View Evidence
                                                        </a>
                                                        <a href="case_view.php?id=<?= $case_id ?>" class="btn btn-outline-secondary">
                                                            <i class="align-middle me-1" data-feather="eye"></i>
                                                            View Details
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                                                            <i class="align-middle me-1" data-feather="trash-2"></i>
                                                            Delete Case
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    
    <script>
        // Initialize feather icons
        feather.replace();
        
        function confirmDelete() {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will delete the case and all associated evidence!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'case_delete.php?id=<?= $case_id ?>';
                }
            });
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const caseName = document.getElementById('case_name').value.trim();
            const description = document.getElementById('description').value.trim();
            const status = document.getElementById('status').value;
            
            if (!caseName) {
                e.preventDefault();
                Swal.fire('Error', 'Please enter a case name', 'error');
                document.getElementById('case_name').focus();
                return false;
            }
            
            if (!description) {
                e.preventDefault();
                Swal.fire('Error', 'Please enter a description', 'error');
                document.getElementById('description').focus();
                return false;
            }
            
            if (!status) {
                e.preventDefault();
                Swal.fire('Error', 'Please select a status', 'error');
                document.getElementById('status').focus();
                return false;
            }
        });
    </script>

</body>

</html>