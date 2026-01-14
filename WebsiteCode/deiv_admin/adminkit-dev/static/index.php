<?php
session_start();

// Get the absolute path to config folder
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

// Determine which data to show based on clicked card
$selected_type = isset($_GET['type']) ? $_GET['type'] : 'users'; // Default to users

// Get selected year from URL parameter, default to current year
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch pending users for notifications
$pending_users = $pdo->query("SELECT User_id, username, email, created_at FROM user WHERE status='Pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count($pending_users);

// ===== DASHBOARD NUMBERS =====
$total_users = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
$total_evidence = $pdo->query("SELECT COUNT(*) FROM evidence")->fetchColumn();
$total_cases = $pdo->query("SELECT COUNT(*) FROM case_table")->fetchColumn();
$audit_log = $pdo->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn();

// ===== USER DISTRIBUTION BY ROLE =====
$role_distribution = $pdo->query("
    SELECT 
        role,
        COUNT(*) as total 
    FROM user 
    WHERE role IS NOT NULL 
    AND role != ''
    GROUP BY role
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_users_by_role = array_sum(array_column($role_distribution, 'total'));

// ===== EVIDENCE DISTRIBUTION BY STATUS =====
$evidence_distribution = $pdo->query("
    SELECT 
        status,
        COUNT(*) as total 
    FROM evidence 
    WHERE status IS NOT NULL 
    GROUP BY status
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_evidence_by_status = array_sum(array_column($evidence_distribution, 'total'));

// ===== CASES DISTRIBUTION BY STATUS =====
$cases_distribution = $pdo->query("
    SELECT 
        status,
        COUNT(*) as total 
    FROM case_table 
    WHERE status IS NOT NULL 
    GROUP BY status
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_cases_by_status = array_sum(array_column($cases_distribution, 'total'));

// ===== AUDIT TRAIL DISTRIBUTION BY ACTION =====
$audit_distribution = $pdo->query("
    SELECT 
        action,
        COUNT(*) as total 
    FROM audit_trail 
    WHERE action IS NOT NULL 
    GROUP BY action
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_audit_by_action = array_sum(array_column($audit_distribution, 'total'));

// ===== GET DETAILED DATA BASED ON SELECTED TYPE =====
$detail_data = [];
$detail_columns = [];
$detail_table_title = "";
$detail_table_subtitle = "";

switch($selected_type) {
    case 'users':
        // Get user details with role and status
        $detail_data = $pdo->query("
            SELECT 
                u.User_id,
                u.username,
                u.email,
                u.first_name,
                u.last_name,
                u.role,
                u.status,
                DATE_FORMAT(u.created_at, '%d %b %Y %H:%i') as created_date,
                u.organization
            FROM user u
            ORDER BY u.created_at DESC
            LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $detail_columns = ['username', 'role', 'organization', 'status', 'created_date'];
        $detail_table_title = "Recent Users";
        $detail_table_subtitle = "Showing 15 most recent users";
        break;
        
    case 'evidence':
        // Get evidence details with case info
        $detail_data = $pdo->query("
            SELECT 
                e.Evidence_id,
                e.file_name,
                e.status,
                e.hash_value,
                DATE_FORMAT(e.upload_date, '%d %b %Y %H:%i') as upload_date,
                c.case_name,
                (SELECT u.username FROM audit_trail at 
                 LEFT JOIN user u ON at.User_id = u.User_id 
                 WHERE at.Evidence_id = e.Evidence_id 
                 AND at.action = 'Upload' 
                 ORDER BY at.date_time DESC LIMIT 1) as uploaded_by,
                (SELECT COUNT(*) FROM audit_trail a WHERE a.Evidence_id = e.Evidence_id) as audit_count
            FROM evidence e
            LEFT JOIN case_table c ON e.Case_id = c.Case_id
            ORDER BY e.upload_date DESC
            LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $detail_columns = ['file_name', 'status', 'case_name', 'uploaded_by', 'upload_date', 'audit_count'];
        $detail_table_title = "Recent Evidence";
        $detail_table_subtitle = "Showing 15 most recent evidence files";
        break;
        
    case 'cases':
        // Get case details with evidence count
        $detail_data = $pdo->query("
            SELECT 
                c.Case_id,
                c.case_name,
                c.description,
                c.status,
                DATE_FORMAT(c.created_at, '%d %b %Y %H:%i') as created_date,
                u.username as created_by,
                (SELECT COUNT(*) FROM evidence e WHERE e.Case_id = c.Case_id) as evidence_count,
                (SELECT COUNT(*) FROM audit_trail a WHERE a.Case_id = c.Case_id) as audit_count
            FROM case_table c
            LEFT JOIN user u ON c.User_id = u.User_id
            ORDER BY c.created_at DESC
            LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $detail_columns = ['case_name', 'status', 'created_by', 'created_date', 'evidence_count', 'audit_count'];
        $detail_table_title = "Recent Cases";
        $detail_table_subtitle = "Showing 15 most recent cases";
        break;
        
    case 'audit':
        // Get audit trail details
        $detail_data = $pdo->query("
            SELECT 
                at.*,
                u.username,
                u.first_name,
                u.last_name,
                u.role as user_role,
                DATE_FORMAT(at.date_time, '%d %b %Y %H:%i') as formatted_date,
                c.case_name,
                e.file_name
            FROM audit_trail at
            LEFT JOIN user u ON at.User_id = u.User_id
            LEFT JOIN case_table c ON at.Case_id = c.Case_id
            LEFT JOIN evidence e ON at.Evidence_id = e.Evidence_id
            ORDER BY at.date_time DESC
            LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $detail_columns = ['username', 'action', 'formatted_date', 'ip_address', 'case_name', 'file_name'];
        $detail_table_title = "Recent Audit Log";
        $detail_table_subtitle = "Showing 15 most recent audit logs";
        break;
}

// ===== GET AVAILABLE YEARS FOR DROPDOWN =====
$years_query = "
    SELECT DISTINCT YEAR(created_at) as year FROM user WHERE created_at IS NOT NULL
    UNION
    SELECT DISTINCT YEAR(upload_date) as year FROM evidence WHERE upload_date IS NOT NULL
    UNION
    SELECT DISTINCT YEAR(date_time) as year FROM audit_trail WHERE date_time IS NOT NULL
    UNION
    SELECT DISTINCT YEAR(created_at) as year FROM case_table WHERE created_at IS NOT NULL
    ORDER BY year DESC
";
$available_years = $pdo->query($years_query)->fetchAll(PDO::FETCH_COLUMN);

// Ensure current year is always available
if (!in_array(date('Y'), $available_years)) {
    array_unshift($available_years, date('Y'));
}

// ===== CHART DATA =====
$chart_labels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$chart_data = [];
$chart_title = "Recent Movement";
$chart_dataset_label = "Total";

switch($selected_type) {
    case 'evidence':
        // Fetch evidence data by month
        $evidence_by_month = $pdo->prepare("
            SELECT 
                MONTH(upload_date) as month,
                COUNT(*) as count
            FROM evidence
            WHERE YEAR(upload_date) = :year
            GROUP BY MONTH(upload_date)
            ORDER BY month
        ");
        $evidence_by_month->execute(['year' => $selected_year]);
        $evidence_by_month = $evidence_by_month->fetchAll(PDO::FETCH_ASSOC);
        
        $chart_data = array_fill(0, 12, 0);
        foreach($evidence_by_month as $row) {
            $chart_data[$row['month'] - 1] = $row['count'];
        }
        $chart_title = "Evidence Upload Trend";
        $chart_dataset_label = "Evidence";
        break;
        
    case 'cases':
        // Fetch cases data by month
        $cases_by_month = $pdo->prepare("
            SELECT 
                MONTH(created_at) as month,
                COUNT(*) as count
            FROM case_table
            WHERE YEAR(created_at) = :year
            GROUP BY MONTH(created_at)
            ORDER BY month
        ");
        $cases_by_month->execute(['year' => $selected_year]);
        $cases_by_month = $cases_by_month->fetchAll(PDO::FETCH_ASSOC);
        
        $chart_data = array_fill(0, 12, 0);
        foreach($cases_by_month as $row) {
            $chart_data[$row['month'] - 1] = $row['count'];
        }
        $chart_title = "Cases Created Trend";
        $chart_dataset_label = "Cases";
        break;
        
    case 'audit':
        // Fetch audit log data by month
        $audit_by_month = $pdo->prepare("
            SELECT 
                MONTH(date_time) as month,
                COUNT(*) as count
            FROM audit_trail
            WHERE YEAR(date_time) = :year
            GROUP BY MONTH(date_time)
            ORDER BY month
        ");
        $audit_by_month->execute(['year' => $selected_year]);
        $audit_by_month = $audit_by_month->fetchAll(PDO::FETCH_ASSOC);
        
        $chart_data = array_fill(0, 12, 0);
        foreach($audit_by_month as $row) {
            $chart_data[$row['month'] - 1] = $row['count'];
        }
        $chart_title = "Audit Log Activity";
        $chart_dataset_label = "Audit Logs";
        break;
        
    default: // 'users'
        // Fetch user registration data by month
        $users_by_month = $pdo->prepare("
            SELECT 
                MONTH(created_at) as month,
                COUNT(*) as count
            FROM user
            WHERE YEAR(created_at) = :year
            GROUP BY MONTH(created_at)
            ORDER BY month
        ");
        $users_by_month->execute(['year' => $selected_year]);
        $users_by_month = $users_by_month->fetchAll(PDO::FETCH_ASSOC);
        
        $chart_data = array_fill(0, 12, 0);
        foreach($users_by_month as $row) {
            $chart_data[$row['month'] - 1] = $row['count'];
        }
        $chart_title = "User Registration Trend";
        $chart_dataset_label = "Users";
        break;
}

// Get distribution data for selected type
$current_distribution = [];
$current_total = 0;
$current_title = "";

switch($selected_type) {
    case 'users':
        $current_distribution = $role_distribution;
        $current_total = $total_users_by_role;
        $current_title = "User Distribution by Role";
        break;
    case 'evidence':
        $current_distribution = $evidence_distribution;
        $current_total = $total_evidence_by_status;
        $current_title = "Evidence Distribution by Status";
        break;
    case 'cases':
        $current_distribution = $cases_distribution;
        $current_total = $total_cases_by_status;
        $current_title = "Case Distribution by Status";
        break;
    case 'audit':
        $current_distribution = $audit_distribution;
        $current_total = $total_audit_by_action;
        $current_title = "Audit Log Distribution by Action";
        break;
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

    <title>DEIV ADMIN</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        .chart-xs {
            height: 200px;
            position: relative;
        }
        
        .distribution-table td:first-child {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .audit-log-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            background-color: #f8f9fa;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
        }
        
        /* Card hover effects */
        .clickable-card {
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .clickable-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .card-active {
            border: 2px solid var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
        }
        
        .card-title i {
            margin-right: 5px;
        }
        
        .indicator-dot {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 10px;
            height: 10px;
            background-color: var(--bs-primary);
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .card-active .indicator-dot {
            opacity: 1;
        }
        
        /* Legend for chart */
        .chart-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            font-size: 12px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 5px;
        }
        
        /* Year filter styles */
        .year-filter-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .year-filter-select {
            padding: 5px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background-color: white;
            font-size: 14px;
            cursor: pointer;
        }
        
        .year-filter-select:focus {
            outline: none;
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
        }
        
        .detail-table {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .detail-table thead {
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }
        
        .status-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        .badge-verified { background-color: var(--bs-success); }
        .badge-pending { background-color: var(--bs-warning); }
        .badge-tampered { background-color: var(--bs-danger); }
        .badge-in-progress { background-color: var(--bs-info); }
        .badge-complete { background-color: var(--bs-success); }
        .badge-closed { background-color: var(--bs-secondary); }
        .badge-active { background-color: var(--bs-success); }
        .badge-inactive { background-color: var(--bs-secondary); }
        .badge-rejected { background-color: var(--bs-danger); }
        
        .table th {
            white-space: nowrap;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .text-truncate {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
                    <li class="sidebar-item active">
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
                            <a class="nav-icon dropdown-toggle" href="#" id="alertsDropdown" data-bs-toggle="dropdown">
                                <div class="position-relative">
                                    <i class="align-middle" data-feather="bell"></i>
                                    <span class="indicator"><?= $pending_count ?></span>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end py-0" aria-labelledby="alertsDropdown">
                                <div class="dropdown-menu-header">
                                    <?= $pending_count ?> Pending User<?= $pending_count > 1 ? 's' : '' ?>
                                </div>
                                <div class="list-group">
                                    <?php if($pending_count > 0): ?>
                                        <?php foreach($pending_users as $user): ?>
                                            <a href="user_management.php?user_id=<?= $user['User_id'] ?>" class="list-group-item">
                                                <div class="row g-0 align-items-center">
                                                    <div class="col-2">
                                                        <i class="text-warning" data-feather="user-plus"></i>
                                                    </div>
                                                    <div class="col-10">
                                                        <div class="text-dark"><?= htmlspecialchars($user['username']) ?></div>
                                                        <div class="text-muted small mt-1"><?= htmlspecialchars($user['email']) ?></div>
                                                        <div class="text-muted small mt-1"><?= date('d M Y H:i', strtotime($user['created_at'])) ?></div>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <a href="#" class="list-group-item">
                                            <div class="text-muted text-center">No pending users</div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="dropdown-menu-footer">
                                    <a href="user_management.php" class="text-muted">Go to User Management</a>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="content">
                <div class="container-fluid p-0">

                    <h1 class="h3 mb-3"><strong>Analytics</strong> Dashboard</h1>

                    <div class="row">
                        <div class="col-xl-6 col-xxl-5 d-flex">
                            <div class="w-100">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="card clickable-card <?= $selected_type == 'users' ? 'card-active' : '' ?>" 
                                             onclick="selectCard('users')"
                                             data-type="users">
                                            <div class="indicator-dot"></div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col mt-0">
                                                        <h5 class="card-title">
                                                            <i class="align-middle" data-feather="users"></i>
                                                            Total Users
                                                        </h5>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="stat text-primary">
                                                            <i class="align-middle" data-feather="users"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <h1 class="mt-1 mb-3"><?= $total_users ?></h1>
                                                <div class="mb-0">
                                                    <span class="text-success">+<?= $total_users ?> total</span>
                                                    <span class="text-muted">Registered users</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card clickable-card <?= $selected_type == 'evidence' ? 'card-active' : '' ?>"
                                             onclick="selectCard('evidence')"
                                             data-type="evidence">
                                            <div class="indicator-dot"></div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col mt-0">
                                                        <h5 class="card-title">
                                                            <i class="align-middle" data-feather="file"></i>
                                                            Total Evidence
                                                        </h5>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="stat text-primary">
                                                            <i class="align-middle" data-feather="file"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <h1 class="mt-1 mb-3"><?= $total_evidence ?></h1>
                                                <div class="mb-0">
                                                    <span class="text-success">+<?= $total_evidence ?> files</span>
                                                    <span class="text-muted">Uploaded evidence</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="card clickable-card <?= $selected_type == 'cases' ? 'card-active' : '' ?>"
                                             onclick="selectCard('cases')"
                                             data-type="cases">
                                            <div class="indicator-dot"></div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col mt-0">
                                                        <h5 class="card-title">
                                                            <i class="align-middle" data-feather="briefcase"></i>
                                                            Total Cases
                                                        </h5>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="stat text-primary">
                                                            <i class="align-middle" data-feather="briefcase"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <h1 class="mt-1 mb-3"><?= $total_cases ?></h1>
                                                <div class="mb-0">
                                                    <span class="text-success">+<?= $total_cases ?> cases</span>
                                                    <span class="text-muted">Active investigations</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card clickable-card <?= $selected_type == 'audit' ? 'card-active' : '' ?>"
                                             onclick="selectCard('audit')"
                                             data-type="audit">
                                            <div class="indicator-dot"></div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col mt-0">
                                                        <h5 class="card-title">
                                                            <i class="align-middle" data-feather="activity"></i>
                                                            Audit Log
                                                        </h5>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="stat text-primary">
                                                            <i class="align-middle" data-feather="activity"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <h1 class="mt-1 mb-3"><?= $audit_log ?></h1>
                                                <div class="mb-0">
                                                    <span class="text-success">+<?= $audit_log ?> logs</span>
                                                    <span class="text-muted">System activities</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6 col-xxl-7">
                            <div class="card flex-fill w-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0"><?= $chart_title ?></h5>
                                    <div class="year-filter-container">
                                        <label for="yearFilter" class="mb-0 me-2">Year:</label>
                                        <select id="yearFilter" class="year-filter-select" onchange="changeYear(this.value)">
                                            <?php foreach($available_years as $year): ?>
                                                <option value="<?= $year ?>" <?= $year == $selected_year ? 'selected' : '' ?>>
                                                    <?= $year ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="card-body py-3">
                                    <div class="chart chart-sm">
                                        <canvas id="chartjs-dashboard-line"></canvas>
                                    </div>
                                    <div class="chart-legend mt-3">
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: rgba(var(--bs-primary-rgb), 0.8);"></div>
                                            <span><?= $chart_dataset_label ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-lg-6 d-flex">
                            <div class="card flex-fill w-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><?= $current_title ?></h5>
                                </div>
                                <div class="card-body d-flex">
                                    <div class="align-self-center w-100">
                                        <div class="py-3">
                                            <div class="chart chart-xs">
                                                <canvas id="distribution-pie-chart"></canvas>
                                            </div>
                                        </div>
                                        
                                        <table class="table mb-0 distribution-table">
                                            <tbody>
                                                <?php if(count($current_distribution) > 0): ?>
                                                    <?php foreach($current_distribution as $item): ?>
                                                        <?php 
                                                        $percentage = $current_total > 0 
                                                            ? number_format(($item['total'] / $current_total) * 100, 1) 
                                                            : 0;
                                                        ?>
                                                        <tr>
                                                            <td title="<?= htmlspecialchars($item[$selected_type == 'audit' ? 'action' : ($selected_type == 'users' ? 'role' : 'status')]) ?>">
                                                                <?= htmlspecialchars($item[$selected_type == 'audit' ? 'action' : ($selected_type == 'users' ? 'role' : 'status')]) ?>
                                                            </td>
                                                            <td class="text-end"><?= $item['total'] ?></td>
                                                            <td class="text-end text-muted small">
                                                                <?= $percentage ?>%
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <tr class="table-light">
                                                        <td><strong>Total</strong></td>
                                                        <td class="text-end"><strong><?= $current_total ?></strong></td>
                                                        <td class="text-end"><strong>100%</strong></td>
                                                    </tr>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">
                                                            No distribution data available
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-lg-6 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><?= $detail_table_title ?></h5>
                                    <p class="card-subtitle text-muted mb-0"><?= $detail_table_subtitle ?></p>
                                </div>
                                <div class="card-body p-0">
                                    <div class="detail-table">
                                        <table class="table table-hover my-0">
                                            <thead>
                                                <tr>
                                                    <?php foreach($detail_columns as $col): ?>
                                                        <th>
                                                            <?= ucwords(str_replace('_', ' ', $col)) ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if(count($detail_data) > 0): ?>
                                                    <?php foreach($detail_data as $row): ?>
                                                        <tr>
                                                            <?php foreach($detail_columns as $col): ?>
                                                                <td>
                                                                    <?php 
                                                                    $value = $row[$col] ?? 'N/A';
                                                                    
                                                                    // Handle special cases
                                                                    if (strpos($col, 'status') !== false) {
                                                                        $status_class = '';
                                                                        $status_value = strtolower($value);
                                                                        switch($status_value) {
                                                                            case 'verified':
                                                                            case 'complete':
                                                                            case 'active':
                                                                                $status_class = 'badge-verified';
                                                                                break;
                                                                            case 'pending':
                                                                                $status_class = 'badge-pending';
                                                                                break;
                                                                            case 'tampered':
                                                                            case 'rejected':
                                                                                $status_class = 'badge-tampered';
                                                                                break;
                                                                            case 'in progress':
                                                                                $status_class = 'badge-in-progress';
                                                                                break;
                                                                            case 'closed':
                                                                            case 'inactive':
                                                                                $status_class = 'badge-closed';
                                                                                break;
                                                                            default:
                                                                                $status_class = 'bg-secondary';
                                                                        }
                                                                        echo '<span class="badge status-badge ' . $status_class . '">' . htmlspecialchars($value) . '</span>';
                                                                    } elseif (strpos($col, 'action') !== false) {
                                                                        $action_class = '';
                                                                        switch($value) {
                                                                            case 'Upload': $action_class = 'bg-primary'; break;
                                                                            case 'Verify': $action_class = 'bg-success'; break;
                                                                            case 'Delete': $action_class = 'bg-danger'; break;
                                                                            case 'View': $action_class = 'bg-info'; break;
                                                                            case 'Approve': $action_class = 'bg-success'; break;
                                                                            case 'Reject': $action_class = 'bg-danger'; break;
                                                                            default: $action_class = 'bg-secondary';
                                                                        }
                                                                        echo '<span class="badge audit-log-badge ' . $action_class . '">' . htmlspecialchars($value) . '</span>';
                                                                    } elseif ($col == 'ip_address') {
                                                                        echo '<span class="ip-address">' . htmlspecialchars($value) . '</span>';
                                                                    } elseif (in_array($col, ['case_name', 'file_name', 'description', 'username'])) {
                                                                        // Truncate long text
                                                                        echo '<span class="text-truncate" title="' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</span>';
                                                                    } else {
                                                                        echo htmlspecialchars($value);
                                                                    }
                                                                    ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="<?= count($detail_columns) ?>" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="align-middle mb-2" data-feather="inbox" style="width: 32px; height: 32px;"></i>
                                                                <p class="mb-0">No data found</p>
                                                                <small class="text-muted">Data will appear here</small>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-footer text-end py-2">
                                        <small class="text-muted me-2">
                                            Showing <?= min(15, count($detail_data)) ?> records
                                        </small>
                                        <?php 
                                        $page_link = '';
                                        switch($selected_type) {
                                            case 'users': $page_link = 'user_management.php'; break;
                                            case 'evidence': $page_link = 'evidence_list.php'; break;
                                            case 'cases': $page_link = 'case_list.php'; break;
                                            case 'audit': $page_link = 'audit_logs.php'; break;
                                        }
                                        ?>
                                        <?php if($page_link): ?>
                                            <a href="<?= $page_link ?>" class="btn btn-sm btn-outline-primary">
                                                View All
                                            </a>
                                        <?php endif; ?>
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
    
    <script>
        // Function to change year
        function changeYear(year) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentType = urlParams.get('type') || 'users';
            window.location.href = `index.php?type=${currentType}&year=${year}`;
        }
        
        // Function to select card and update view
        function selectCard(type) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentYear = urlParams.get('year') || '<?= date('Y') ?>';
            
            // Update URL without page reload (for bookmarking)
            const url = new URL(window.location);
            url.searchParams.set('type', type);
            url.searchParams.set('year', currentYear);
            window.history.pushState({}, '', url);
            
            // Remove active class from all cards
            document.querySelectorAll('.clickable-card').forEach(card => {
                card.classList.remove('card-active');
            });
            
            // Add active class to clicked card
            const clickedCard = document.querySelector(`[data-type="${type}"]`);
            if (clickedCard) {
                clickedCard.classList.add('card-active');
            }
            
            // Reload the page to show updated data
            window.location.href = `index.php?type=${type}&year=${currentYear}`;
        }
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            window.location.reload();
        });
    </script>
    
    <script>
        // Initialize pie chart for distribution
        document.addEventListener("DOMContentLoaded", function() {
            // Prepare data for the pie chart
            const distributionData = <?= json_encode($current_distribution) ?>;
            const selectedType = "<?= $selected_type ?>";
            
            if (distributionData.length > 0) {
                let labels, values;
                
                if (selectedType === 'users') {
                    labels = distributionData.map(d => d.role);
                } else if (selectedType === 'evidence' || selectedType === 'cases') {
                    labels = distributionData.map(d => d.status);
                } else if (selectedType === 'audit') {
                    labels = distributionData.map(d => d.action);
                }
                
                values = distributionData.map(d => parseInt(d.total));
                
                // Define color schemes for different types
                const colorSchemes = {
                    'users': [
                        window.theme.primary,
                        window.theme.success,
                        window.theme.warning,
                        window.theme.info,
                        window.theme.danger
                    ],
                    'evidence': [
                        window.theme.success, // Verified
                        window.theme.danger,  // Tampered
                        window.theme.warning  // Pending
                    ],
                    'cases': [
                        window.theme.info,     // In Progress
                        window.theme.success,  // Complete
                        window.theme.secondary, // Closed
                        window.theme.warning   // Pending
                    ],
                    'audit': [
                        window.theme.primary,  // Upload
                        window.theme.success,  // Verify
                        window.theme.danger,   // Delete
                        window.theme.warning,  // Reject
                        window.theme.info      // Approve
                    ]
                };
                
                // Get appropriate color scheme
                const colors = colorSchemes[selectedType] || colorSchemes.users;
                
                // Create background colors array
                const backgroundColors = values.map((_, index) => 
                    colors[index % colors.length] || '#6c757d'
                );
                
                // Create the pie chart
                new Chart(document.getElementById("distribution-pie-chart"), {
                    type: "pie",
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: backgroundColors,
                            borderColor: '#fff',
                            borderWidth: 2,
                            hoverBorderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
    
    <script>
        // Line chart for Recent Movement
        document.addEventListener("DOMContentLoaded", function() {
            const chartData = <?= json_encode($chart_data) ?>;
            const chartLabels = <?= json_encode($chart_labels) ?>;
            const chartDatasetLabel = "<?= $chart_dataset_label ?>";
            
            var ctx = document.getElementById("chartjs-dashboard-line").getContext("2d");
            var gradient = ctx.createLinearGradient(0, 0, 0, 225);
            gradient.addColorStop(0, "rgba(215, 227, 244, 1)");
            gradient.addColorStop(1, "rgba(215, 227, 244, 0)");
            
            // Line chart
            new Chart(document.getElementById("chartjs-dashboard-line"), {
                type: "line",
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: chartDatasetLabel,
                        fill: true,
                        backgroundColor: gradient,
                        borderColor: window.theme.primary,
                        borderWidth: 2,
                        pointBackgroundColor: window.theme.primary,
                        pointBorderColor: "#fff",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.1,
                        data: chartData
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltips: {
                            intersect: false,
                            mode: 'index'
                        }
                    },
                    hover: {
                        intersect: true
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6c757d'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                borderDash: [3, 3]
                            },
                            ticks: {
                                color: '#6c757d',
                                callback: function(value) {
                                    return Number.isInteger(value) ? value : '';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    
    <script>
        // Auto-refresh for pending users notification
        document.addEventListener("DOMContentLoaded", function() {
            function refreshPendingUsers() {
                fetch('fetch_pending_users.php')
                    .then(res => res.json())
                    .then(data => {
                        if(data && data.success) {
                            // Update the notification badge count
                            const indicator = document.querySelector('.indicator');
                            if (indicator) {
                                indicator.textContent = data.count;
                            }
                            
                            // Update the dropdown header
                            const dropdownHeader = document.querySelector('.dropdown-menu-header');
                            if (dropdownHeader) {
                                const userText = data.count === 1 ? 'Pending User' : 'Pending Users';
                                dropdownHeader.textContent = `${data.count} ${userText}`;
                            }
                            
                            // Update the dropdown content
                            const listGroup = document.querySelector('.list-group');
                            if (listGroup && data.users) {
                                if (data.count > 0) {
                                    // Build the list of pending users
                                    let html = '';
                                    data.users.forEach(user => {
                                        const date = new Date(user.created_at);
                                        const formattedDate = date.toLocaleDateString('en-GB', {
                                            day: '2-digit',
                                            month: 'short',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        });
                                        
                                        html += `
                                            <a href="user_management.php?user_id=${user.User_id}" class="list-group-item">
                                                <div class="row g-0 align-items-center">
                                                    <div class="col-2">
                                                        <i class="text-warning" data-feather="user-plus"></i>
                                                    </div>
                                                    <div class="col-10">
                                                        <div class="text-dark">${escapeHtml(user.username)}</div>
                                                        <div class="text-muted small mt-1">${escapeHtml(user.email)}</div>
                                                        <div class="text-muted small mt-1">${formattedDate}</div>
                                                    </div>
                                                </div>
                                            </a>
                                        `;
                                    });
                                    listGroup.innerHTML = html;
                                    
                                    // Re-initialize feather icons
                                    if (typeof feather !== 'undefined') {
                                        feather.replace();
                                    }
                                } else {
                                    // No pending users
                                    listGroup.innerHTML = `
                                        <a href="#" class="list-group-item">
                                            <div class="text-muted text-center">No pending users</div>
                                        </a>
                                    `;
                                }
                            }
                        }
                    })
                    .catch(error => console.error('Error refreshing notifications:', error));
            }
            
            // Helper function to escape HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Refresh every 30 seconds (30000 milliseconds)
            setInterval(refreshPendingUsers, 30000);
            
            // Also refresh when the page becomes visible again (user switches back to tab)
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    refreshPendingUsers();
                }
            });
        });
    </script>

</body>

</html>