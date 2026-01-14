<?php
session_start();

// ==== DB CONNECT ====
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

// ===== NOTIFICATION COUNT =====
$stmt_pending = $pdo->query("SELECT COUNT(*) FROM notification WHERE status='Pending'");
$pending_count = $stmt_pending->fetchColumn() ?? 0;

// Get pending users for notification dropdown

// ===== INPUTS =====
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// ===== BASE QUERY =====
$sql = "
    SELECT 
        c.Case_id, 
        c.case_name, 
        c.status,
        c.created_at,
        u.first_name, 
        u.last_name
    FROM `case_table` c
    LEFT JOIN `user` u ON c.User_id = u.User_id
    WHERE 1 = 1
";

$params = [];

// ===== SEARCH FILTER =====
if (!empty($search)) {
    $sql .= " AND (
        c.case_name LIKE :caseName
        OR c.Case_id LIKE :caseId
        OR CONCAT(u.first_name, ' ', u.last_name) LIKE :assignedUser
    )";
    
    $params[':caseName'] = "%$search%";
    $params[':caseId'] = "%$search%";
    $params[':assignedUser'] = "%$search%";
}

// ===== STATUS FILTER =====
if ($status !== 'all') {
    $sql .= " AND c.status = :status";
    $params[':status'] = $status;
}

// ===== DATE RANGE FILTER =====
if (!empty($date_from)) {
    $sql .= " AND DATE(c.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(c.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$sql .= " ORDER BY c.Case_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($cases);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Report Generation</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
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
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        /* Hamburger Menu Styles */
        .sidebar-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .sidebar-toggle:hover {
            background-color: rgba(0,0,0,0.05);
        }
        
        .hamburger {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 24px;
            height: 18px;
        }
        
        .hamburger span {
            display: block;
            height: 2px;
            width: 100%;
            background-color: #333;
            border-radius: 2px;
            transition: all 0.3s;
        }
        
        .sidebar.collapsed + .main .hamburger span:first-child {
            transform: rotate(45deg) translate(5px, 5px);
        }
        
        .sidebar.collapsed + .main .hamburger span:nth-child(2) {
            opacity: 0;
        }
        
        .sidebar.collapsed + .main .hamburger span:last-child {
            transform: rotate(-45deg) translate(7px, -6px);
        }
        
        /* Sidebar collapsed state */
        .sidebar {
            transition: margin-left 0.3s ease;
        }
        
        .sidebar.collapsed {
            margin-left: -250px;
        }
        
        .sidebar.collapsed ~ .main {
            margin-left: 0;
            width: 100%;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main {
                width: 100%;
                margin-left: 0;
            }
            
            .sidebar.show ~ .main {
                margin-left: 250px;
            }
            
            .overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0,0,0,0.5);
                z-index: 1040;
                display: none;
            }
            
            .sidebar.show ~ .overlay {
                display: block;
            }
        }
        
        /* Existing styles */
        .chart-xs {
            height: 200px;
            position: relative;
        }
        
        .role-distribution-table td:first-child {
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
        
        /* Report cards */
        .case-card {
            transition: all 0.3s ease;
            border: 1px solid #dee2e6;
        }
        
        .case-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        /* Date filter styles */
        .date-filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .clear-filters-btn {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .case-date {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        /* Case card content */
        .case-card-content {
            height: 150px;
            display: flex;
            flex-direction: column;
        }
        
        .case-id-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .case-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .case-assigned {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .case-status {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="overlay"></div>
        
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
                    <!-- <li class="sidebar-item">
                        <a class="sidebar-link" href="case_list.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Case Files</span>
                        </a>
                    </li> -->

                     <!-- Report Files -->
                    <li class="sidebar-item active">
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
                       
                    </ul>
                </div>
            </nav>


            <main class="content">
                <div class="container-fluid p-0">
                   <h1 class="h3 mb-3"><strong>Report</strong> Generation</h1>
                    <p>Select a case to generate a report</p>

                    <!-- ================= FILTERS ================= -->
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="date-filter-label">
                                        <i class="bi bi-search"></i> Search
                                    </label>
                                    <input type="text" name="search" class="form-control"
                                        placeholder="Search Case (ID, Name, User)"
                                        value="<?= htmlspecialchars($search) ?>">
                                </div>

                                <div class="col-md-2">
                                    <label class="date-filter-label">
                                        <i class="bi bi-funnel"></i> Status
                                    </label>
                                    <select name="status" class="form-select">
                                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                                        <option value="Complete" <?= $status === 'Complete' ? 'selected' : '' ?>>Complete</option>
                                        <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="Closed" <?= $status === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="date-filter-label">
                                        <i class="bi bi-calendar-event"></i> From Date
                                    </label>
                                    <input type="date" name="date_from" class="form-control"
                                        value="<?= htmlspecialchars($date_from) ?>">
                                </div>

                                <div class="col-md-2">
                                    <label class="date-filter-label">
                                        <i class="bi bi-calendar-event"></i> To Date
                                    </label>
                                    <input type="date" name="date_to" class="form-control"
                                        value="<?= htmlspecialchars($date_to) ?>">
                                </div>

                                <div class="col-md-3">
                                    <label class="date-filter-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-fill">
                                            <i class="bi bi-funnel"></i> Filter
                                        </button>
                                        <a href="reportlist.php" class="btn btn-outline-secondary clear-filters-btn">
                                            <i class="bi bi-x-circle"></i> Clear
                                        </a>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="mt-3">
                                <p class="mb-1">Showing <b><?= $total ?></b> result(s)</p>
                                <?php if (!empty($date_from) || !empty($date_to)): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar-range"></i>
                                        <?php if (!empty($date_from) && !empty($date_to)): ?>
                                            Filtered: <?= date('M d, Y', strtotime($date_from)) ?> to <?= date('M d, Y', strtotime($date_to)) ?>
                                        <?php elseif (!empty($date_from)): ?>
                                            From: <?= date('M d, Y', strtotime($date_from)) ?>
                                        <?php else: ?>
                                            Until: <?= date('M d, Y', strtotime($date_to)) ?>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ================= CASE LIST ================= -->
                    <div class="row mt-4">
                        <?php if (count($cases) > 0): ?>
                            <?php foreach ($cases as $case): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <a href="selectedreport.php?id=<?= $case['Case_id'] ?>" style="text-decoration:none;">
                                        <div class="card case-card h-100">
                                            <div class="card-body case-card-content">
                                                <div class="case-id-name">
                                                    <i class="bi bi-folder"></i>
                                                    <?= htmlspecialchars($case['Case_id']) ?> - <?= htmlspecialchars($case['case_name']) ?>
                                                </div>
                                                
                                                <div class="flex-grow-1"></div>
                                                
                                                <div class="case-info-row">
                                                    <div class="case-assigned">
                                                        <i class="bi bi-person"></i>
                                                        <?= htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) ?>
                                                    </div>
                                                    <?php 
                                                        $status_class = 'bg-secondary';
                                                        switch($case['status']) {
                                                            case 'Complete': $status_class = 'bg-success'; break;
                                                            case 'Pending': $status_class = 'bg-warning'; break;
                                                            case 'In Progress': $status_class = 'bg-info'; break;
                                                            case 'Closed': $status_class = 'bg-secondary'; break;
                                                        }
                                                    ?>
                                                    <span class="badge <?= $status_class ?> case-status"><?= htmlspecialchars($case['status']) ?></span>
                                                </div>
                                                
                                                <div class="case-date">
                                                    <i class="bi bi-calendar3"></i>
                                                    Created: <?= date('M d, Y H:i', strtotime($case['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No cases found matching your criteria.
                                    <?php if (!empty($date_from) || !empty($date_to)): ?>
                                        <br>Try adjusting your date range or <a href="reportlist.php">clear all filters</a>.
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript Files -->
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Feather Icons -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    
    <!-- Simplebar (for custom scrollbars) -->
    <script src="https://cdn.jsdelivr.net/npm/simplebar@latest/dist/simplebar.min.js"></script>
    
    <!-- Chart.js (if needed) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize Feather Icons
            feather.replace();
            
            // Sidebar Toggle Functionality
            const sidebarToggle = document.querySelector('.js-sidebar-toggle');
            const sidebar = document.querySelector('.js-sidebar');
            const overlay = document.querySelector('.overlay');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Toggle collapsed class
                    sidebar.classList.toggle('collapsed');
                    
                    // On mobile, toggle 'show' class for overlay
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('show');
                        if (overlay) {
                            overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
                        }
                    }
                });
            }
            
            // Close sidebar when clicking overlay on mobile
            if (overlay) {
                overlay.addEventListener('click', function() {
                    if (window.innerWidth <= 768 && sidebar) {
                        sidebar.classList.remove('show');
                        overlay.style.display = 'none';
                    }
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 && sidebar && overlay) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggle = sidebarToggle.contains(event.target);
                    
                    if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                        overlay.style.display = 'none';
                    }
                }
            });
            
            // Adjust sidebar on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar && overlay) {
                    sidebar.classList.remove('show');
                    overlay.style.display = 'none';
                }
            });

            // Date validation - ensure 'To Date' is not before 'From Date'
            const dateFrom = document.querySelector('input[name="date_from"]');
            const dateTo = document.querySelector('input[name="date_to"]');
            
            if (dateFrom && dateTo) {
                dateFrom.addEventListener('change', function() {
                    if (dateTo.value && dateFrom.value > dateTo.value) {
                        dateTo.value = dateFrom.value;
                    }
                    dateTo.setAttribute('min', dateFrom.value);
                });
                
                dateTo.addEventListener('change', function() {
                    if (dateFrom.value && dateTo.value < dateFrom.value) {
                        dateFrom.value = dateTo.value;
                    }
                });
            }
        });
    </script>
</body>
</html>