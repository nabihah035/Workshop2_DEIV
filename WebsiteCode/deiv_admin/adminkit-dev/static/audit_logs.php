<?php
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['User_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get the absolute path to config folder
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

// Fetch pending users for notifications
$pending_users = $pdo->query("SELECT User_id, username, email, created_at FROM user WHERE status='Pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count($pending_users);

// Handle date range filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($date_from)) {
    $where_conditions[] = "DATE(at.date_time) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(at.date_time) <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($action_filter)) {
    $where_conditions[] = "at.action = :action";
    $params[':action'] = $action_filter;
}

if (!empty($user_filter)) {
    $where_conditions[] = "at.User_id = :user_id";
    $params[':user_id'] = $user_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';


// Pagination setup
$limit = 20; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1; // Ensure page is never less than 1
$offset = ($page - 1) * $limit;

// Get total number of audit logs for pagination
$count_query = "SELECT COUNT(*) as total FROM audit_trail at $where_clause";
$count_stmt = $pdo->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Ensure page doesn't exceed total pages
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch audit logs with user information - FIXED for MariaDB
$query = "
    SELECT 
        at.*,
        u.username,
        u.first_name,
        u.last_name,
        u.email,
        u.role as user_role,
        u.status as user_status
    FROM audit_trail at
    LEFT JOIN user u ON at.User_id = u.User_id
    $where_clause
    ORDER BY at.date_time DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);

// Bind filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind pagination parameters - ensure they are integers
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique action types for filter dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM audit_trail WHERE action IS NOT NULL ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Fetch users for filter dropdown
$users = $pdo->query("SELECT User_id, username, first_name, last_name FROM user ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for dashboard
$today_logs = $pdo->query("SELECT COUNT(*) FROM audit_trail WHERE DATE(date_time) = CURDATE()")->fetchColumn();
$week_logs = $pdo->query("SELECT COUNT(*) FROM audit_trail WHERE date_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$month_logs = $pdo->query("SELECT COUNT(*) FROM audit_trail WHERE date_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Get top actions
$top_actions = $pdo->query("
    SELECT action, COUNT(*) as count 
    FROM audit_trail 
    WHERE action IS NOT NULL 
    GROUP BY action 
    ORDER BY count DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get top users by activity
$top_users = $pdo->query("
    SELECT 
        u.username,
        u.first_name,
        u.last_name,
        COUNT(at.Audit_id) as activity_count
    FROM audit_trail at
    LEFT JOIN user u ON at.User_id = u.User_id
    WHERE at.User_id IS NOT NULL
    GROUP BY at.User_id
    ORDER BY activity_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
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

    <title>DEIV ADMIN - Audit Logs</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .action-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-upload {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        /* Add to your existing CSS in the <style> tag */
.action-verify {
    background-color: rgba(25, 135, 84, 0.2) !important; /* Brighter background */
    color: #198754 !important;
    border: 1px solid #198754;
    font-weight: 700;
    padding: 0.35rem 0.85rem;
}

/* Add a checkmark icon before "Verify" */
.action-verify::before {
    content: "✓ ";
    font-weight: bold;
    margin-right: 3px;
}

/* Or use this for a more distinct look */
.action-verify-v2 {
    background: linear-gradient(135deg, #198754 0%, #20c997 100%) !important;
    color: white !important;
    border: none;
    box-shadow: 0 2px 4px rgba(25, 135, 84, 0.3);
}
        
        .action-delete {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .action-view {
            background-color: rgba(13, 202, 240, 0.1);
            color: #0dcaf0;
        }
        
        .action-approve {
            background-color: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }
        
        .action-reject {
            background-color: rgba(253, 126, 20, 0.1);
            color: #fd7e14;
        }
        
        .action-create {
            background-color: rgba(32, 201, 151, 0.1);
            color: #20c997;
        }
        
        .action-update {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            background-color: #f8f9fa;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            color: #6c757d;
        }
        
        .user-info {
            max-width: 150px;
        }
        
        .user-info div {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .datetime-cell {
            min-width: 140px;
        }
        
        .stat-card {
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-today .stat-icon {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .stat-week .stat-icon {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .stat-month .stat-icon {
            background-color: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }
        
        .stat-total .stat-icon {
            background-color: rgba(253, 126, 20, 0.1);
            color: #fd7e14;
        }
        
        .filter-form .form-control,
        .filter-form .form-select {
            height: 38px;
        }
        
        .top-list-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .top-list-item:last-child {
            border-bottom: none;
        }
        
        .activity-count {
            background-color: #e9ecef;
            padding: 0.15rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #495057;
        }
        
        .log-details {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .table th {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
        }
        
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            background-color: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
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

                    <li class="sidebar-item">
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

                    <!-- Case Files -->
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="reportlist.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Report Management</span>
                        </a>
                    </li>

                    <!-- Audit Logs -->
                    <li class="sidebar-item active">
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

                    <h1 class="h3 mb-3"><strong>Audit</strong> Logs</h1>

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card stat-today">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Today</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat-icon">
                                                <i class="align-middle" data-feather="activity"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?= $today_logs ?></h1>
                                    <div class="mb-0">
                                        <span class="text-success">Today's activities</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card stat-week">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Last 7 Days</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat-icon">
                                                <i class="align-middle" data-feather="trending-up"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?= $week_logs ?></h1>
                                    <div class="mb-0">
                                        <span class="text-success">Weekly activities</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card stat-month">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Last 30 Days</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat-icon">
                                                <i class="align-middle" data-feather="calendar"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?= $month_logs ?></h1>
                                    <div class="mb-0">
                                        <span class="text-success">Monthly activities</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card stat-total">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col mt-0">
                                            <h5 class="card-title">Total Logs</h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="stat-icon">
                                                <i class="align-middle" data-feather="database"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <h1 class="mt-1 mb-3"><?= $total_logs ?></h1>
                                    <div class="mb-0">
                                        <span class="text-success">All-time activities</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Filter Panel and Statistics -->
                        <div class="col-xl-4 col-lg-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Filter Logs</h5>
                                </div>
                                <div class="card-body">
                                    <form method="GET" action="audit_logs.php" class="filter-form">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label">Date Range</label>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <input type="date" 
                                                               class="form-control" 
                                                               name="date_from" 
                                                               value="<?= htmlspecialchars($date_from) ?>"
                                                               placeholder="From Date">
                                                    </div>
                                                    <div class="col-6">
                                                        <input type="date" 
                                                               class="form-control" 
                                                               name="date_to" 
                                                               value="<?= htmlspecialchars($date_to) ?>"
                                                               placeholder="To Date">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label class="form-label">Action Type</label>
                                                <select class="form-select" name="action">
                                                    <option value="">All Actions</option>
                                                    <?php foreach($actions as $action): ?>
                                                        <option value="<?= $action ?>" 
                                                                <?= ($action_filter == $action) ? 'selected' : '' ?>>
                                                            <?= $action ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label class="form-label">User</label>
                                                <select class="form-select" name="user_id">
                                                    <option value="">All Users</option>
                                                    <?php foreach($users as $user): ?>
                                                        <option value="<?= $user['User_id'] ?>" 
                                                                <?= ($user_filter == $user['User_id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($user['username']) ?>
                                                            <?php if($user['first_name'] || $user['last_name']): ?>
                                                                (<?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?>)
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-12">
                                                <div class="d-grid gap-2 d-md-flex">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="align-middle me-1" data-feather="filter"></i>
                                                        Apply Filters
                                                    </button>
                                                    <?php if($date_from || $date_to || $action_filter || $user_filter): ?>
                                                        <a href="audit_logs.php" class="btn btn-outline-secondary">
                                                            <i class="align-middle me-1" data-feather="x"></i>
                                                            Clear
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                    
                                    <hr class="my-4">
                                    
                                    <!-- Top Actions -->
                                    <h6 class="card-subtitle mb-3">Top Actions</h6>
                                    <div class="list-group list-group-flush">
                                        <?php if(count($top_actions) > 0): ?>
                                            <?php foreach($top_actions as $action): ?>
                                                <div class="top-list-item d-flex justify-content-between align-items-center">
                                                    <span><?= $action['action'] ?></span>
                                                    <span class="activity-count"><?= $action['count'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-muted small">No action data available</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Top Users -->
                                    <h6 class="card-subtitle mb-3 mt-4">Most Active Users</h6>
                                    <div class="list-group list-group-flush">
                                        <?php if(count($top_users) > 0): ?>
                                            <?php foreach($top_users as $user): ?>
                                                <div class="top-list-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <div><?= htmlspecialchars($user['username']) ?></div>
                                                        <?php if($user['first_name'] || $user['last_name']): ?>
                                                            <div class="text-muted small">
                                                                <?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="activity-count"><?= $user['activity_count'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-muted small">No user activity data available</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Audit Logs Table -->
                        <div class="col-xl-8 col-lg-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="card-title mb-0">Audit Trail</h5>
                                            <p class="text-muted mb-0">Showing <?= count($audit_logs) ?> of <?= $total_logs ?> logs</p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <?php if($date_from || $date_to || $action_filter || $user_filter): ?>
                                                <div class="text-muted small">
                                                    Filters applied
                                                    <?php if($date_from || $date_to): ?>
                                                        • Date range: <?= $date_from ? $date_from : 'Any' ?> to <?= $date_to ? $date_to : 'Any' ?>
                                                    <?php endif; ?>
                                                    <?php if($action_filter): ?>
                                                        • Action: <?= $action_filter ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Action</th>
                                                    <th>User</th>
                                                    <th>Date & Time</th>
                                                    <th>IP Address</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if(count($audit_logs) > 0): ?>
                                                    <?php foreach($audit_logs as $log): ?>
                                                        <tr>
                                                            <td><strong>#<?= $log['Audit_id'] ?></strong></td>
                                                            <td>
                                                                <?php
                                                                $action_class = '';
                                                                $action = $log['action'] ?? 'Unknown';
                                                                switch($action) {
                                                                    case 'Upload':
                                                                        $action_class = 'action-upload';
                                                                        break;
                                                                    case 'Verify':
                                                                        $action_class = 'action-verify';
                                                                        break;
                                                                    case 'Delete':
                                                                        $action_class = 'action-delete';
                                                                        break;
                                                                    case 'View':
                                                                        $action_class = 'action-view';
                                                                        break;
                                                                    case 'Approve':
                                                                        $action_class = 'action-approve';
                                                                        break;
                                                                    case 'Reject':
                                                                        $action_class = 'action-reject';
                                                                        break;
                                                                    case 'Create':
                                                                        $action_class = 'action-create';
                                                                        break;
                                                                    case 'Update':
                                                                        $action_class = 'action-update';
                                                                        break;
                                                                    default:
                                                                        $action_class = 'action-view';
                                                                }
                                                                ?>
                                                                <span class="action-badge <?= $action_class ?>">
                                                                    <?= $action ?>
                                                                </span>
                                                            </td>
                                                            <td class="user-info">
                                                                <?php if($log['username']): ?>
                                                                    <div><strong><?= htmlspecialchars($log['username']) ?></strong></div>
                                                                    <?php if($log['first_name'] || $log['last_name']): ?>
                                                                        <div class="text-muted small">
                                                                            <?= htmlspecialchars($log['first_name'] ?? '') ?> <?= htmlspecialchars($log['last_name'] ?? '') ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <?php if($log['user_role']): ?>
                                                                        <div class="text-muted small">
                                                                            <?= htmlspecialchars($log['user_role']) ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">System</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="datetime-cell">
                                                                <div>
                                                                    <?php if($log['date_time']): ?>
                                                                        <div class="small">
                                                                            <?= date('d M Y', strtotime($log['date_time'])) ?>
                                                                        </div>
                                                                        <div class="text-muted smaller">
                                                                            <?= date('H:i:s', strtotime($log['date_time'])) ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">N/A</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="ip-address" title="IP Address">
                                                                    <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5">
                                                            <div class="empty-state">
                                                                <div class="empty-state-icon">
                                                                    <i class="align-middle" data-feather="activity"></i>
                                                                </div>
                                                                <h5>No audit logs found</h5>
                                                                <?php if($date_from || $date_to || $action_filter || $user_filter): ?>
                                                                    <p class="text-muted">Try adjusting your filters</p>
                                                                    <a href="audit_logs.php" class="btn btn-outline-primary">
                                                                        Clear all filters
                                                                    </a>
                                                                <?php else: ?>
                                                                    <p class="text-muted">System activities will appear here</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        // Initialize date pickers
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize flatpickr for date inputs
            flatpickr("input[type=date]", {
                dateFormat: "Y-m-d",
                allowInput: true
            });
            
            // Auto-refresh notifications
            setInterval(() => {
                fetch('fetch_pending_users.php')
                    .then(res => res.json())
                    .then(data => {
                        if(data && data.count !== undefined) {
                            document.querySelector('.indicator').textContent = data.count;
                        }
                    })
                    .catch(error => console.error('Error refreshing notifications:', error));
            }, 30000);
        });
        
        // Export function (if needed)
        function exportAuditLogs(format) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            
            window.location.href = `export_audit_logs.php?${params.toString()}`;
        }
    </script>

</body>

</html>