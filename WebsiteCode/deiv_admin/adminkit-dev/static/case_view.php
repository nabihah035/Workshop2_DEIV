<?php
// case_view.php
session_start();

// Check login
if (!isset($_SESSION['User_id'])) {
    header("Location: login.php");
    exit();
}

// DB config
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

// Validate Case ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: case_list.php");
    exit();
}

$case_id = (int) $_GET['id'];

// Fetch case details
$stmt = $pdo->prepare("
    SELECT c.*, u.username, u.first_name, u.last_name, u.role
    FROM case_table c
    LEFT JOIN user u ON c.User_id = u.User_id
    WHERE c.Case_id = ?
");
$stmt->execute([$case_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    header("Location: case_list.php");
    exit();
}

// Status badge class
$status_class = '';
switch ($case['status']) {
    case 'In Progress': $status_class = 'status-in-progress'; break;
    case 'Complete': $status_class = 'status-complete'; break;
    case 'Closed': $status_class = 'status-closed'; break;
    default: $status_class = 'status-pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>DEIV ADMIN - View Case</title>
    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-in-progress { background:rgba(13,110,253,.1); color:#0d6efd; }
        .status-complete { background:rgba(25,135,84,.1); color:#198754; }
        .status-closed { background:rgba(108,117,125,.1); color:#6c757d; }
        .status-pending { background:rgba(255,193,7,.1); color:#ffc107; }

        .label { font-weight:600; color:#495057; }
        .value { color:#6c757d; }
    </style>
</head>

<body>
<div class="wrapper">

    <!-- ===== SIDEBAR (SAME AS case_edit.php) ===== -->
    <nav id="sidebar" class="sidebar js-sidebar">
        <div class="sidebar-content js-simplebar">
            <a class="sidebar-brand" href="index.php">
                <span class="align-middle">DEIV ADMIN</span>
            </a>

            <ul class="sidebar-nav">
                <li class="sidebar-header">Navigation</li>

                <li class="sidebar-item">
                    <a class="sidebar-link" href="index.php">
                        <i class="align-middle material-icons">home</i>
                        Dashboard
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link" href="user_management.php">
                        <i class="align-middle material-icons">people</i>
                        User Management
                    </a>
                </li>

                <li class="sidebar-item active">
                    <a class="sidebar-link" href="case_list.php">
                        <i class="align-middle material-icons">folder</i>
                        Case Files
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link" href="evidence_list.php">
                        <i class="align-middle material-icons">inventory_2</i>
                        Evidence Records
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link" href="metadata_list.php">
                        <i class="align-middle material-icons">list_alt</i>
                        Evidence Metadata
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link" href="reportlist.php">
                        <i class="align-middle material-icons">folder</i>
                        Report Management
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link" href="audit_logs.php">
                        <i class="align-middle material-icons">history</i>
                        Audit Logs
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link" href="logout.php">
                        <i class="align-middle material-icons">logout</i>
                        <span class="text-danger">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- ===== MAIN CONTENT ===== -->
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

                <h3 class="mb-3">Case Details</h3>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Case #<?= $case_id ?></h5>
                    </div>

                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="label">Case Name</div>
                                <div class="value"><?= htmlspecialchars($case['case_name']) ?></div>
                            </div>

                            <div class="col-md-6">
                                <div class="label">Status</div>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= htmlspecialchars($case['status']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="label">Description</div>
                            <div class="value"><?= nl2br(htmlspecialchars($case['description'])) ?></div>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="label">Assigned To</div>
                                <div class="value">
                                    <?= $case['username']
                                        ? htmlspecialchars($case['username'].' ('.$case['first_name'].' '.$case['last_name'].')')
                                        : '<em>Unassigned</em>' ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="label">User Role</div>
                                <div class="value"><?= htmlspecialchars($case['role'] ?? '-') ?></div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="label">Created At</div>
                                <div class="value"><?= date('F j, Y, g:i a', strtotime($case['created_at'])) ?></div>
                            </div>

                            <div class="col-md-6">
                                <div class="label">Last Updated</div>
                                <div class="value"><?= date('F j, Y, g:i a', strtotime($case['updated_at'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer text-end">
                        <a href="case_edit.php?id=<?= $case_id ?>" class="btn btn-primary">
                            <i class="material-icons">edit</i> Edit Case
                        </a>
                        <a href="evidence_list.php?case_id=<?= $case_id ?>" class="btn btn-outline-info">
                            <i class="material-icons">folder</i> View Evidence
                        </a>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
