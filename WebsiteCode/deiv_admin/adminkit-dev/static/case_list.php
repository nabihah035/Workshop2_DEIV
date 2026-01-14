<?php
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['User_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get the absolute path to config folder
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

// Fetch pending users for notifications
$pending_users = $pdo->query("SELECT User_id, username, email, created_at FROM user WHERE status='Pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count($pending_users);

// Handle search functionality
$search_query = '';
$where_clause = '';
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $where_clause = "WHERE (c.case_name LIKE :search OR c.description LIKE :search OR c.status LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%$search_query%";
}

// Handle status filter
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = $_GET['status'];
    if ($where_clause) {
        $where_clause .= " AND c.status = :status";
    } else {
        $where_clause = "WHERE c.status = :status";
    }
    $params[':status'] = $status_filter;
}

// Pagination setup
$limit = 15; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of cases for pagination
$count_query = "SELECT COUNT(*) as total FROM case_table c $where_clause";
$count_stmt = $pdo->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_cases = $count_stmt->fetchColumn();
$total_pages = ceil($total_cases / $limit);

// Fetch cases with user information
$query = "
    SELECT 
        c.*,
        u.username,
        u.first_name,
        u.last_name,
        u.role as user_role,
        u.status as user_status,
        (SELECT COUNT(*) FROM evidence WHERE Case_id = c.Case_id) as evidence_count
    FROM case_table c
    LEFT JOIN user u ON c.User_id = u.User_id
    $where_clause
    ORDER BY c.Case_id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);

// Bind search and status parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind pagination parameters
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique status values for filter dropdown
$statuses = $pdo->query("SELECT DISTINCT status FROM case_table WHERE status IS NOT NULL ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
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

    <title>DEIV ADMIN - Case Files</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
            text-decoration: none !important;
        }
        
        .status-badge:hover {
            opacity: 0.8;
            transform: translateY(-1px);
            text-decoration: none;
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
        
        .case-description {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .case-description:hover {
            overflow: visible;
            white-space: normal;
            position: absolute;
            background: white;
            border: 1px solid #dee2e6;
            padding: 0.5rem;
            border-radius: 0.25rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            max-width: 400px;
        }
        
        .evidence-count {
            background-color: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            margin-right: 0.25rem;
            transition: all 0.2s;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-2px);
        }
        
        .pagination .page-link {
            color: #0d6efd;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }
        
        .search-container {
            max-width: 500px;
        }
        
        .filter-dropdown {
            min-width: 180px;
        }
        
        .table th {
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            vertical-align: middle;
            border-top: 1px solid #dee2e6;
        }
        
        .table tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .user-info {
            max-width: 150px;
        }
        
        .user-info div {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .user-info .username {
            font-weight: 600;
            color: #495057;
        }
        
        .user-info .user-details {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .case-id {
            font-weight: 600;
            color: #0d6efd;
        }
        
        .case-name {
            font-weight: 600;
            color: #212529;
        }
        
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            color: #adb5bd;
        }
        
        .table-responsive {
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .btn-create-case {
            background-color: #0d6efd;
            border-color: #0d6efd;
            padding: 0.5rem 1rem;
        }
        
        .btn-create-case:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
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
                       
                        
                        <li class="nav-item dropdown">
                            <a class="nav-icon dropdown-toggle d-inline-block d-sm-none" href="#" data-bs-toggle="dropdown">
                                <i class="align-middle" data-feather="settings"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="content">
                <div class="container-fluid p-0">

                    <h1 class="h3 mb-3"><strong>Case</strong> Files</h1>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="card-title mb-0">All Cases</h5>
                                            <p class="text-muted mb-0">Total <?= $total_cases ?> cases found</p>
                                        </div>
                                       
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <!-- Search and Filter Bar -->
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <form method="GET" action="case_list.php" class="row g-2">
                                                <div class="col-md-6">
                                                    <div class="input-group">
                                                        <input type="text" 
                                                               class="form-control" 
                                                               name="search" 
                                                               placeholder="Search cases by name, description, or user..." 
                                                               value="<?= htmlspecialchars($search_query) ?>">
                                                        <button class="btn btn-outline-primary" type="submit">
                                                            <i class="align-middle" data-feather="search"></i>
                                                        </button>
                                                        <?php if($search_query || isset($status_filter)): ?>
                                                            <a href="case_list.php" class="btn btn-outline-secondary">
                                                                <i class="align-middle" data-feather="x"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <select class="form-select filter-dropdown" name="status" onchange="this.form.submit()">
                                                        <option value="">All Statuses</option>
                                                        <?php foreach($statuses as $status): ?>
                                                            <option value="<?= $status ?>" 
                                                                    <?= (isset($status_filter) && $status_filter == $status) ? 'selected' : '' ?>>
                                                                <?= $status ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Cases Table -->
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Case Name</th>
                                                    <th>Description</th>
                                                    <th>Status</th>
                                                    <th>Assigned User</th>
                                                    <th>Evidence Count</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if(count($cases) > 0): ?>
                                                    <?php foreach($cases as $case): ?>
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
                                                                $status_class = 'status-pending';
                                                                break;
                                                            default:
                                                                $status_class = 'status-pending';
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td class="case-id">#<?= $case['Case_id'] ?></td>
                                                            <td>
                                                                <div class="case-name"><?= htmlspecialchars($case['case_name'] ?? 'Unnamed Case') ?></div>
                                                            </td>
                                                            <td>
                                                                <div class="case-description" title="<?= htmlspecialchars($case['description'] ?? '') ?>">
                                                                    <?= htmlspecialchars($case['description'] ?? 'No description') ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <a href="case_edit.php?id=<?= $case['Case_id'] ?>" class="status-badge <?= $status_class ?>">
                                                                    <?= $case_status ?>
                                                                </a>
                                                            </td>
                                                            <td class="user-info">
                                                                <?php if($case['username']): ?>
                                                                    <div class="username"><?= htmlspecialchars($case['username']) ?></div>
                                                                    <div class="user-details">
                                                                        <?= htmlspecialchars($case['first_name'] ?? '') ?> <?= htmlspecialchars($case['last_name'] ?? '') ?>
                                                                    </div>
                                                                    <div class="user-details"><?= htmlspecialchars($case['user_role'] ?? '') ?></div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Unassigned</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="evidence-count">
                                                                    <?= $case['evidence_count'] ?? 0 ?> files
                                                                </span>
                                                            </td>
                                                            <td class="action-buttons">
                                                                <!-- View Evidence Button -->
                                                                <a href="evidence_list.php?case_id=<?= $case['Case_id'] ?>" 
                                                                   class="btn btn-sm btn-outline-info" 
                                                                   title="View Evidence"
                                                                   data-bs-toggle="tooltip">
                                                                    <i class="align-middle" data-feather="file-text"></i>
                                                                </a>
                                                                
                                                                <!-- View Details Button -->
                                                                <a href="case_view.php?id=<?= $case['Case_id'] ?>" 
                                                                   class="btn btn-sm btn-outline-secondary" 
                                                                   title="View Details"
                                                                   data-bs-toggle="tooltip">
                                                                    <i class="align-middle" data-feather="eye"></i>
                                                                </a>
                                                                
                                                                <!-- Edit Case Button -->
                                                                <a href="case_edit.php?id=<?= $case['Case_id'] ?>" 
                                                                   class="btn btn-sm btn-outline-primary" 
                                                                   title="Edit Case"
                                                                   data-bs-toggle="tooltip">
                                                                    <i class="align-middle" data-feather="edit"></i>
                                                                </a>
                                                                
                                                                <!-- Delete Button -->
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-outline-danger" 
                                                                        title="Delete Case"
                                                                        data-bs-toggle="tooltip"
                                                                        onclick="confirmDelete(<?= $case['Case_id'] ?>, '<?= addslashes($case['case_name'] ?? 'Unnamed Case') ?>')">
                                                                    <i class="align-middle" data-feather="trash-2"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-5">
                                                            <div class="empty-state">
                                                                <i class="align-middle empty-state-icon" data-feather="folder"></i>
                                                                <h5 class="mt-3">No cases found</h5>
                                                                <?php if($search_query || isset($status_filter)): ?>
                                                                    <p class="text-muted mb-4">Try adjusting your search or filter</p>
                                                                    <a href="case_list.php" class="btn btn-outline-primary">
                                                                        <i class="align-middle me-1" data-feather="x"></i>
                                                                        Clear filters
                                                                    </a>
                                                                <?php else: ?>
                                                                    <p class="text-muted mb-4">Start by creating your first case</p>
                                                                    <a href="case_create.php" class="btn btn-primary">
                                                                        <i class="align-middle me-1" data-feather="plus"></i>
                                                                        Create New Case
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if($total_pages > 1): ?>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-center mt-3">
                                                <!-- Previous Page -->
                                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" 
                                                       href="?page=<?= $page-1 ?><?= $search_query ? '&search='.urlencode($search_query) : '' ?><?= isset($status_filter) ? '&status='.urlencode($status_filter) : '' ?>"
                                                       aria-label="Previous">
                                                        <i class="align-middle" data-feather="chevron-left"></i>
                                                        <span class="visually-hidden">Previous</span>
                                                    </a>
                                                </li>
                                                
                                                <!-- First Page -->
                                                <?php if($page > 3): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" 
                                                           href="?page=1<?= $search_query ? '&search='.urlencode($search_query) : '' ?><?= isset($status_filter) ? '&status='.urlencode($status_filter) : '' ?>">
                                                            1
                                                        </a>
                                                    </li>
                                                    <?php if($page > 4): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">...</span>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <!-- Page Numbers around current page -->
                                                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                        <a class="page-link" 
                                                           href="?page=<?= $i ?><?= $search_query ? '&search='.urlencode($search_query) : '' ?><?= isset($status_filter) ? '&status='.urlencode($status_filter) : '' ?>">
                                                            <?= $i ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <!-- Last Page -->
                                                <?php if($page < $total_pages - 2): ?>
                                                    <?php if($page < $total_pages - 3): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">...</span>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li class="page-item">
                                                        <a class="page-link" 
                                                           href="?page=<?= $total_pages ?><?= $search_query ? '&search='.urlencode($search_query) : '' ?><?= isset($status_filter) ? '&status='.urlencode($status_filter) : '' ?>">
                                                            <?= $total_pages ?>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <!-- Next Page -->
                                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                    <a class="page-link" 
                                                       href="?page=<?= $page+1 ?><?= $search_query ? '&search='.urlencode($search_query) : '' ?><?= isset($status_filter) ? '&status='.urlencode($status_filter) : '' ?>"
                                                       aria-label="Next">
                                                        <i class="align-middle" data-feather="chevron-right"></i>
                                                        <span class="visually-hidden">Next</span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
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
        
        // Initialize tooltips
        document.addEventListener("DOMContentLoaded", function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        function confirmDelete(caseId, caseName) {
            Swal.fire({
                title: 'Delete Case',
                html: `Are you sure you want to delete <strong>${caseName}</strong>?<br><br>
                      <span class="text-danger">This will delete the case and all associated evidence!</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return new Promise((resolve, reject) => {
                        $.ajax({
                            url: 'case_delete.php',
                            type: 'POST',
                            data: { 
                                id: caseId,
                                csrf_token: '<?= $_SESSION["csrf_token"] ?>'
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    resolve(response);
                                } else {
                                    reject(new Error(response.message || 'Failed to delete case'));
                                }
                            },
                            error: function(xhr, status, error) {
                                reject(new Error('Server error: ' + error));
                            }
                        });
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'Case has been deleted successfully.',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.reload();
                    });
                }
            }).catch((error) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: error.message,
                    confirmButtonText: 'OK'
                });
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

        // Show case description on hover
        document.querySelectorAll('.case-description').forEach(item => {
            item.addEventListener('mouseenter', function() {
                if (this.scrollWidth > this.clientWidth) {
                    this.style.position = 'absolute';
                    this.style.background = 'white';
                    this.style.border = '1px solid #dee2e6';
                    this.style.padding = '0.5rem';
                    this.style.borderRadius = '0.25rem';
                    this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                    this.style.zIndex = '1000';
                    this.style.maxWidth = '400px';
                    this.style.whiteSpace = 'normal';
                    this.style.overflow = 'visible';
                }
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.position = '';
                this.style.background = '';
                this.style.border = '';
                this.style.padding = '';
                this.style.borderRadius = '';
                this.style.boxShadow = '';
                this.style.zIndex = '';
                this.style.maxWidth = '300px';
                this.style.whiteSpace = 'nowrap';
                this.style.overflow = 'hidden';
                this.style.textOverflow = 'ellipsis';
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
            
            // Esc to clear search
            if (e.key === 'Escape' && document.querySelector('input[name="search"]').value) {
                window.location.href = 'case_list.php';
            }
        });

        // Show current page info
        function updatePageInfo() {
            const start = (<?= $page ?> - 1) * <?= $limit ?> + 1;
            const end = Math.min(start + <?= $limit ?> - 1, <?= $total_cases ?>);
            const pageInfo = document.getElementById('pageInfo');
            
            if (pageInfo) {
                pageInfo.textContent = `Showing ${start} to ${end} of ${<?= $total_cases ?>} cases`;
            }
        }

        // Call on page load
        document.addEventListener('DOMContentLoaded', updatePageInfo);
    </script>

</body>

</html>