<?php
session_start();

// Get the absolute path to config folder
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

// ===== HANDLE DELETE REQUEST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_evidence'])) {
    $evidence_id = intval($_POST['evidence_id']);
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // First, fetch evidence details for audit log
        $evidence_stmt = $pdo->prepare("
            SELECT e.*, c.case_name 
            FROM evidence e 
            LEFT JOIN case_table c ON e.Case_id = c.Case_id 
            WHERE e.Evidence_id = :evidence_id
        ");
        $evidence_stmt->bindValue(':evidence_id', $evidence_id, PDO::PARAM_INT);
        $evidence_stmt->execute();
        $evidence_data = $evidence_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evidence_data) {
            throw new Exception("Evidence not found");
        }
        
        // Delete the evidence record
        $delete_stmt = $pdo->prepare("DELETE FROM evidence WHERE Evidence_id = :evidence_id");
        $delete_stmt->bindValue(':evidence_id', $evidence_id, PDO::PARAM_INT);
        
        if ($delete_stmt->execute()) {
            // Insert audit log for deletion
            $audit_stmt = $pdo->prepare("
                INSERT INTO audit_trail 
                (action, ip_address, User_id, Case_id, Evidence_id) 
                VALUES 
                (:action, :ip_address, :user_id, :case_id, :evidence_id)
            ");
            
            // Bind parameters for audit log
            $audit_stmt->bindValue(':action', 'Delete', PDO::PARAM_STR);
            $audit_stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
            $audit_stmt->bindValue(':user_id', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
            $audit_stmt->bindValue(':case_id', $evidence_data['Case_id'] ?? null, PDO::PARAM_INT);
            $audit_stmt->bindValue(':evidence_id', $evidence_id, PDO::PARAM_INT);
            
            $audit_stmt->execute();
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "Evidence deleted successfully! Audit log recorded.";
        } else {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Failed to delete evidence.";
        }
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header("Location: evidence_list.php");
    exit();
}

// Fetch pending users for notifications
$pending_users = $pdo->query("SELECT User_id, username, email, created_at FROM user WHERE status='Pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count($pending_users);

// ===== PAGINATION =====
$items_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// ===== SEARCH AND FILTER =====
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$case_filter = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;

// Build WHERE clause for filters
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(e.file_name LIKE :search OR e.hash_value LIKE :search OR c.case_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $where_clauses[] = "e.status = :status";
    $params[':status'] = $status_filter;
}

if ($case_filter > 0) {
    $where_clauses[] = "e.Case_id = :case_id";
    $params[':case_id'] = $case_filter;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// ===== GET TOTAL COUNT =====
$count_sql = "SELECT COUNT(*) FROM evidence e LEFT JOIN case_table c ON e.Case_id = c.Case_id $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// ===== FETCH EVIDENCE DATA WITH PAGINATION =====
$sql = "
    SELECT 
        e.Evidence_id,
        e.file_name,
        e.upload_date,
        e.status,
        e.hash_value,
        c.Case_id,
        c.case_name,
        c.status AS case_status
    FROM evidence e
    LEFT JOIN case_table c ON e.Case_id = c.Case_id
    $where_sql
    ORDER BY e.upload_date DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

// Bind parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind pagination parameters
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$evidence_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== FETCH CASES FOR FILTER DROPDOWN =====
$cases = $pdo->query("SELECT Case_id, case_name FROM case_table ORDER BY case_name")->fetchAll(PDO::FETCH_ASSOC);

// ===== GET STATUS COUNTS =====
$status_counts = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM evidence
    GROUP BY status
    ORDER BY status
")->fetchAll(PDO::FETCH_ASSOC);

// Format file size (optional, can remove if file_size column not used)
function formatFileSize($bytes) {
    return $bytes . ' bytes';
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

    <title>Evidence Records - DEIV ADMIN</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
   <style>
    .evidence-table th { white-space: nowrap; }
    .file-name { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .status-badge { 
        font-size: 0.75rem; 
        padding: 0.35rem 0.65rem; 
        font-weight: 500; 
        color: #212529 !important; /* Force black text color */
    }
    .hash-tag { 
        font-family: 'Courier New', monospace; 
        font-size: 0.85rem; 
        background-color: #f8f9fa; 
        padding: 0.3rem 0.6rem; 
        border-radius: 4px; 
        max-width: 200px; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        display: inline-block;
        cursor: pointer;
    }
    .hash-tag:hover {
        background-color: #e9ecef;
    }
    .badge.pending { 
        background-color: #ffc107; 
        color: #212529 !important; 
    }
    .badge.verified { 
        background-color: #198754; 
        color: #212529 !important; 
    }
    .badge.tampered { 
        background-color: #dc3545; 
        color: #212529 !important; 
    }
    .filter-card .card-body {
        padding: 1rem;
    }
    .page-item.active .page-link {
        background-color: var(--bs-primary);
        border-color: var(--bs-primary);
    }
    .table-responsive {
        min-height: 400px;
    }
    .stats-card {
        transition: transform 0.2s;
    }
    .stats-card:hover {
        transform: translateY(-2px);
    }
    .action-buttons .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    .date-filter-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #6c757d;
        margin-bottom: 0.25rem;
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
                    <li class="sidebar-item active">
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
                        <!-- <li class="nav-item dropdown">
                            <a class="nav-icon dropdown-toggle" href="#" id="alertsDropdown" data-bs-toggle="dropdown">
                                <div class="position-relative">
                                    <i class="align-middle" data-feather="bell"></i>
                                    <span class="indicator"><?= $pending_count ?></span>
                                </div>
                            </a>
                        </li> -->
                    </ul>
                </div>
            </nav>

            <main class="content">
                <div class="container-fluid p-0">

                    <h1 class="h3 mb-3"><strong>Evidence</strong> Records</h1>

                    <!-- Success/Error Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle me-2"></i><?= $_SESSION['error_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="row">
                        <?php foreach($status_counts as $status): ?>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card stats-card border-left-<?= 
                                    $status['status'] == 'Verified' ? 'success' : 
                                    ($status['status'] == 'Pending' ? 'warning' : 'danger')
                                ?> shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-<?= 
                                                    $status['status'] == 'Verified' ? 'success' : 
                                                    ($status['status'] == 'Pending' ? 'warning' : 'danger')
                                                ?> text-uppercase mb-1">
                                                    <?= htmlspecialchars($status['status']) ?> Evidence
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $status['count'] ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-<?= 
                                                    $status['status'] == 'Verified' ? 'check-circle' : 
                                                    ($status['status'] == 'Pending' ? 'clock' : 'exclamation-triangle')
                                                ?> fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Total Evidence Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stats-card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Evidence
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_items ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Card -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card filter-card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Filter Evidence</h5>
                                </div>
                                <div class="card-body">
                                    <form method="GET" action="evidence_list.php" class="row g-3">
                                        <div class="col-md-4">
                                            <label class="date-filter-label">
                                                <i class="bi bi-search"></i> Search
                                            </label>
                                            <input type="text" name="search" class="form-control" placeholder="Search by file name, hash, or case..." value="<?= htmlspecialchars($search) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="date-filter-label">
                                                <i class="bi bi-funnel"></i> Status
                                            </label>
                                            <select name="status" class="form-select">
                                                <option value="">All Status</option>
                                                <option value="Pending" <?= $status_filter=='Pending'?'selected':'' ?>>Pending</option>
                                                <option value="Verified" <?= $status_filter=='Verified'?'selected':'' ?>>Verified</option>
                                                <option value="Tampered" <?= $status_filter=='Tampered'?'selected':'' ?>>Tampered</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="date-filter-label">
                                                <i class="bi bi-folder"></i> Case
                                            </label>
                                            <select name="case_id" class="form-select">
                                                <option value="0">All Cases</option>
                                                <?php foreach($cases as $case): ?>
                                                    <option value="<?= $case['Case_id'] ?>" <?= $case_filter==$case['Case_id']?'selected':'' ?>><?= htmlspecialchars($case['case_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="date-filter-label">&nbsp;</label>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="align-middle me-1" data-feather="filter"></i> Filter
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Evidence Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Evidence Records</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover evidence-table">
                                            <thead>
                                                <tr>
                                                    <th>Evidence ID</th>
                                                    <th>File Name</th>
                                                    <th>Case</th>
                                                    <th>Status</th>
                                                    <th>Hash Value</th>
                                                    <th>Upload Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if(count($evidence_items) > 0): ?>
                                                    <?php foreach($evidence_items as $e): ?>
                                                        <tr>
                                                            <td>#<?= htmlspecialchars($e['Evidence_id']) ?></td>
                                                            <td class="file-name" title="<?= htmlspecialchars($e['file_name']) ?>">
                                                                <?= htmlspecialchars($e['file_name']) ?>
                                                            </td>
                                                            <td>
                                                                <?php if($e['case_name']): ?>
                                                                    <span class="badge bg-info"><?= htmlspecialchars($e['case_name']) ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">No case</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge status-badge <?= strtolower($e['status']) ?>">
                                                                    <?= htmlspecialchars($e['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="hash-tag" title="<?= htmlspecialchars($e['hash_value']) ?>" onclick="copyHash('<?= htmlspecialchars($e['hash_value']) ?>')">
                                                                    <?= htmlspecialchars(substr($e['hash_value'],0,16)) ?>...
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="small text-muted"><?= date('d M Y', strtotime($e['upload_date'])) ?></div>
                                                                <div class="small"><?= date('H:i', strtotime($e['upload_date'])) ?></div>
                                                            </td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteEvidence(<?= $e['Evidence_id'] ?>, '<?= htmlspecialchars($e['file_name']) ?>')">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="align-middle mb-2" data-feather="file" style="width: 48px; height: 48px;"></i>
                                                                <h5 class="mt-2">No evidence found</h5>
                                                                <p class="mb-0">Try adjusting your filters or search terms</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if($total_pages > 1): ?>
                                        <nav aria-label="Page navigation" class="mt-4">
                                            <ul class="pagination justify-content-center">
                                                <!-- Previous Page -->
                                                <?php if($current_page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?page=<?= $current_page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&case_id=<?= $case_filter ?>">
                                                            Previous
                                                        </a>
                                                    </li>
                                                <?php endif; ?>

                                                <!-- Page Numbers -->
                                                <?php 
                                                $start_page = max(1, $current_page - 2);
                                                $end_page = min($total_pages, $current_page + 2);
                                                
                                                for($i = $start_page; $i <= $end_page; $i++): ?>
                                                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&case_id=<?= $case_filter ?>">
                                                            <?= $i ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>

                                                <!-- Next Page -->
                                                <?php if($current_page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?page=<?= $current_page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&case_id=<?= $case_filter ?>">
                                                            Next
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                        
                                        <div class="text-center text-muted small">
                                            Showing <?= count($evidence_items) ?> of <?= $total_items ?> records
                                            (Page <?= $current_page ?> of <?= $total_pages ?>)
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Hidden Form for Delete -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_evidence" value="1">
        <input type="hidden" name="evidence_id" id="delete_evidence_id">
    </form>

    <script src="js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        function copyHash(hash) {
            navigator.clipboard.writeText(hash).then(() => {
                // Show toast notification
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
                
                Toast.fire({
                    icon: 'success',
                    title: 'Hash copied to clipboard!'
                });
            }).catch(err => {
                console.error('Failed to copy hash:', err);
            });
        }
        
        function deleteEvidence(id, fileName) {
            Swal.fire({
                title: 'Delete Evidence?',
                html: `Are you sure you want to delete this evidence?<br><br><strong>${fileName}</strong><br><br>This action will be recorded in the audit log and cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash"></i> Yes, delete it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Set the evidence ID and submit the form
                    document.getElementById('delete_evidence_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }
        
        // Auto-refresh for pending users notification
        document.addEventListener("DOMContentLoaded", function() {
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
    </script>

</body>

</html>