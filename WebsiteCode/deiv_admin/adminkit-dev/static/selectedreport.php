<?php
session_start();

// ==== DB CONNECT ====
// Try multiple possible locations for db.php
$config_paths = [
    __DIR__ . '/config/db.php',
    __DIR__ . '/../config/db.php',
    dirname(dirname(__DIR__)) . '/config/db.php',
    'C:/xampp/htdocs/deiv_admin/config/db.php'  // Absolute path if needed
];

$db_connected = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected) {
    // If db.php doesn't exist, create a direct connection here
    $host = 'localhost';
    $dbname = 'deiv'; // Change to your database name
    $username = 'root'; // Change to your database username
    $password = ''; // Change to your database password
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// ================== NOTIFICATIONS ==================
$pending_users = $pdo->query("
    SELECT User_id, username, email, created_at 
    FROM user 
    WHERE status='Pending' 
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count($pending_users);

// Get the case id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("No case selected.");
}
$case_id = intval($_GET['id']);

// Check if table name is 'case' or 'case_table' (adjust based on your actual table name)
$table_name = 'case_table'; // Change to 'case' if that's your actual table name

// Fetch data
$sql = "
    SELECT 
        c.Case_id, c.case_name, c.description, c.status,
        u.first_name, u.last_name, u.email
    FROM `$table_name` c
    LEFT JOIN `user` u ON c.User_id = u.User_id
    WHERE c.Case_id = :id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $case_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    die("Case not found.");
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

    <title>Report Options | DEIV Admin</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* Blue Color Palette */
        :root {
            --primary-blue: #2c3e50;
            --secondary-blue: #3498db;
            --light-blue: #5dade2;
            --lighter-blue: #85c1e9;
            --lightest-blue: #d6eaf8;
            --dark-blue: #1a252f;
            --accent-blue: #2980b9;
            --info-blue: #21618c;
        }

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
            background-color: var(--lightest-blue);
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            color: var(--dark-blue);
        }
        
        /* Card hover effects */
        .clickable-card {
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .clickable-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.2);
        }
        
        .card-active {
            border: 2px solid var(--secondary-blue);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
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
            background-color: var(--secondary-blue);
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

        /* Report options specific styles */
        .report-option-card {
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            height: 100%;
        }
        
        .report-option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.25);
            text-decoration: none;
        }
        
        .report-option-card .card-body {
            padding: 2rem;
        }
        
        .case-info-alert {
            border-left: 4px solid var(--secondary-blue);
            background: linear-gradient(135deg, var(--lightest-blue) 0%, #ebf5fb 100%);
            border: 1px solid var(--lighter-blue);
        }
        
        .fa-3x {
            font-size: 3em;
        }
        
        /* Gradient header card - Blue Theme */
        .gradient-header-card {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 6px rgba(44, 62, 80, 0.2);
        }
        
        .gradient-header-card h2,
        .gradient-header-card p {
            color: white;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .gradient-header-card h2 i {
            color: rgba(255, 255, 255, 0.9);
        }
        
        /* Enhanced case info card */
        .case-info-alert h5 {
            color: var(--dark-blue);
            font-weight: 600;
        }
        
        /* Button enhancements - Blue Theme */
        .btn-back {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
            color: white;
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--accent-blue) 100%);
        }

        /* Blue-themed card borders */
        .border-primary {
            border-color: var(--secondary-blue) !important;
            border-width: 2px !important;
        }

        .border-primary:hover {
            border-color: var(--accent-blue) !important;
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.2);
        }

        .border-success {
            border-color: var(--light-blue) !important;
            border-width: 2px !important;
        }

        .border-success:hover {
            border-color: var(--secondary-blue) !important;
            box-shadow: 0 5px 20px rgba(93, 173, 226, 0.2);
        }

        /* Blue-themed text colors */
        .text-primary {
            color: var(--secondary-blue) !important;
        }

        .text-success {
            color: var(--accent-blue) !important;
        }

        /* Blue-themed buttons */
        .btn-outline-primary {
            color: var(--secondary-blue);
            border-color: var(--secondary-blue);
        }

        .btn-outline-primary:hover {
            background-color: var(--secondary-blue);
            border-color: var(--secondary-blue);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--light-blue) 0%, var(--secondary-blue) 100%);
            border: none;
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--accent-blue) 100%);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        /* Icon colors */
        .fa-eye {
            color: var(--secondary-blue) !important;
        }

        .fa-file-pdf {
            color: var(--accent-blue) !important;
        }

        /* Alert styling */
        .alert-light {
            background-color: var(--lightest-blue);
            border-color: var(--lighter-blue);
            color: var(--dark-blue);
        }

        .alert-light .fa-info-circle {
            color: var(--secondary-blue) !important;
        }

        /* Card footer */
        .card-footer.bg-transparent {
            background-color: rgba(214, 234, 248, 0.3) !important;
        }

        /* Status badges - Blue theme */
        .badge.bg-success {
            background: linear-gradient(135deg, var(--light-blue) 0%, var(--secondary-blue) 100%) !important;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%) !important;
        }

        .badge.bg-info {
            background: linear-gradient(135deg, var(--lighter-blue) 0%, var(--light-blue) 100%) !important;
        }

        .badge.bg-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%) !important;
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
                    <li class="sidebar-item active">
                        <a class="sidebar-link" href="reportlist.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Report management</span>
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
                    <div class="mb-3">
                        <a href="reportlist.php" class="btn btn-secondary btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Cases
                        </a>
                    </div>

                    <div class="card">
                        <div class="card-header gradient-header-card">
                            <h2 class="mb-0"><i class="fas fa-file-alt"></i> Select Output Format</h2>
                            <p class="mb-0">Choose how you would like to receive your report</p>
                        </div>
                        
                        <div class="card-body">
                            <!-- Selected Case Info -->
                            <div class="alert case-info-alert">
                                <h5 class="alert-heading"><i class="fas fa-folder-open"></i> Selected Case</h5>
                                <hr style="border-color: var(--lighter-blue);">
                                <h4 style="color: var(--dark-blue);"><?= htmlspecialchars($case['case_name']) ?></h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong style="color: var(--primary-blue);">Case ID:</strong> <?= htmlspecialchars($case['Case_id']) ?></p>
                                        <p><strong style="color: var(--primary-blue);">Status:</strong> 
                                            <span class="badge bg-<?= 
                                                $case['status'] == 'Complete' ? 'success' : 
                                                ($case['status'] == 'Pending' ? 'warning' : 
                                                ($case['status'] == 'In Progress' ? 'info' : 'secondary')) 
                                            ?>">
                                                <?= htmlspecialchars($case['status']) ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong style="color: var(--primary-blue);">Assigned to:</strong> <?= htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) ?></p>
                                        <p><strong style="color: var(--primary-blue);">Email:</strong> <?= htmlspecialchars($case['email']) ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($case['description'])): ?>
                                    <p><strong style="color: var(--primary-blue);">Description:</strong> <?= htmlspecialchars($case['description']) ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Output Options -->
                            <div class="row mt-4">
                                <div class="col-md-6 mb-3">
                                    <a href="fullreport.php?id=<?= $case['Case_id'] ?>" class="report-option-card">
                                        <div class="card h-100 border-primary">
                                            <div class="card-body text-center">
                                                <div class="mb-3">
                                                    <i class="fas fa-eye fa-3x"></i>
                                                </div>
                                                <h4 class="card-title text-primary">View Online</h4>
                                                <p class="card-text text-muted">Display report directly in your browser</p>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <button class="btn btn-outline-primary w-100">View Report</button>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <a href="downloadreport.php?id=<?= $case['Case_id'] ?>" class="report-option-card">
                                        <div class="card h-100 border-success">
                                            <div class="card-body text-center">
                                                <div class="mb-3">
                                                    <i class="fas fa-file-pdf fa-3x"></i>
                                                </div>
                                                <h4 class="card-title text-success">Download as PDF</h4>
                                                <p class="card-text text-muted">Save report as a PDF file to your device</p>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <button class="btn btn-success w-100"><i class="fas fa-download"></i> Download PDF</button>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>

                            <div class="alert alert-light mt-4">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> Your report will be compiled with all relevant data from the selected case including evidence, metadata, and audit logs.
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>