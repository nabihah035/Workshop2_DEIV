<?php
session_start();

// ================== DB CONNECTION ==================
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
include $config_path;

// ===== HANDLE DELETE REQUEST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_metadata'])) {
    $meta_id = intval($_POST['meta_id']);
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get metadata details before deleting for confirmation and audit log
        $check_stmt = $pdo->prepare("
            SELECT m.*, e.Evidence_id, e.file_name, c.Case_id, c.case_name 
            FROM metadata m
            LEFT JOIN evidence e ON m.Evidence_id = e.Evidence_id
            LEFT JOIN case_table c ON e.Case_id = c.Case_id
            WHERE m.Meta_id = :meta_id
        ");
        $check_stmt->bindValue(':meta_id', $meta_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $metadata = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($metadata) {
            // Delete the metadata record
            $delete_stmt = $pdo->prepare("DELETE FROM metadata WHERE Meta_id = :meta_id");
            $delete_stmt->bindValue(':meta_id', $meta_id, PDO::PARAM_INT);
            
            if ($delete_stmt->execute()) {
                // Insert audit log for deletion - using only existing columns
                $audit_stmt = $pdo->prepare("
                    INSERT INTO audit_trail 
                    (action, ip_address, User_id, Case_id, Evidence_id) 
                    VALUES 
                    (:action, :ip_address, :user_id, :case_id, :evidence_id)
                ");
                
                // Bind parameters for audit log - using only existing columns
                $audit_stmt->bindValue(':action', 'Delete', PDO::PARAM_STR);
                $audit_stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
                $audit_stmt->bindValue(':user_id', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
                $audit_stmt->bindValue(':case_id', $metadata['Case_id'] ?? null, PDO::PARAM_INT);
                $audit_stmt->bindValue(':evidence_id', $metadata['Evidence_id'] ?? null, PDO::PARAM_INT);
                
                $audit_stmt->execute();
                
                // Commit transaction
                $pdo->commit();
                
                $_SESSION['success_message'] = "Metadata '{$metadata['meta_key']}' deleted successfully! Audit log recorded.";
            } else {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Failed to delete metadata.";
            }
        } else {
            $_SESSION['error_message'] = "Metadata not found.";
        }
    } catch (PDOException $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header("Location: metadata_list.php");
    exit();
}

// ================== NOTIFICATIONS ==================
$pending_users = $pdo->query("
    SELECT User_id, username, email, created_at 
    FROM user 
    WHERE status='Pending' 
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count($pending_users);

// ================== PAGINATION ==================
$items_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// ================== ENHANCED SEARCH & FILTER ==================
$search = trim($_GET['search'] ?? '');
$evidence_filter = intval($_GET['evidence_id'] ?? 0);
$case_filter = intval($_GET['case_id'] ?? 0);

$where_clauses = [];
$params = [];

if ($search !== '') {
    // Enhanced search to include metadata key/value, evidence file names, and case names
    $where_clauses[] = "(m.meta_key LIKE :search 
                        OR m.meta_value LIKE :search 
                        OR e.file_name LIKE :search 
                        OR c.case_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($evidence_filter > 0) {
    $where_clauses[] = "m.Evidence_id = :evidence_id";
    $params[':evidence_id'] = $evidence_filter;
}

if ($case_filter > 0) {
    $where_clauses[] = "c.Case_id = :case_id";
    $params[':case_id'] = $case_filter;
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// ================== COUNT ==================
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM metadata m
    LEFT JOIN evidence e ON m.Evidence_id = e.Evidence_id
    LEFT JOIN case_table c ON e.Case_id = c.Case_id
    $where_sql
");
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// ================== FETCH METADATA ==================
$sql = "
    SELECT m.*, e.file_name, e.hash_value, c.case_name
    FROM metadata m
    LEFT JOIN evidence e ON m.Evidence_id = e.Evidence_id
    LEFT JOIN case_table c ON e.Case_id = c.Case_id
    $where_sql
    ORDER BY m.Meta_id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$metadata_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================== ENHANCED FILTER DROPDOWNS ==================
// Evidence list
$evidence_query = "SELECT Evidence_id, file_name FROM evidence";
$evidence_params = [];
if ($search !== '') {
    $evidence_query .= " WHERE file_name LIKE :search";
    $evidence_params[':search'] = "%$search%";
}
$evidence_query .= " ORDER BY file_name";
$evidence_stmt = $pdo->prepare($evidence_query);
$evidence_stmt->execute($evidence_params);
$evidence_list = $evidence_stmt->fetchAll(PDO::FETCH_ASSOC);

// Case list
$case_query = "SELECT Case_id, case_name FROM case_table";
$case_params = [];
if ($search !== '') {
    $case_query .= " WHERE case_name LIKE :search";
    $case_params[':search'] = "%$search%";
}
$case_query .= " ORDER BY case_name";
$case_stmt = $pdo->prepare($case_query);
$case_stmt->execute($case_params);
$case_list = $case_stmt->fetchAll(PDO::FETCH_ASSOC);

// ================== HELPERS ==================
function truncateText($text, $length = 80) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

function formatMetaValue($value) {
    if ($value === null) return '<span class="text-muted">NULL</span>';
    $safe = htmlspecialchars($value);
    return strlen($safe) > 100
        ? '<span title="'.$safe.'">'.truncateText($safe).'</span>'
        : $safe;
}

// Highlight search terms in text
function highlightSearch($text, $search) {
    if ($search === '') return htmlspecialchars($text);
    $highlighted = preg_replace('/(' . preg_quote($search, '/') . ')/i', 
        '<span class="bg-warning text-dark">$1</span>', 
        htmlspecialchars($text));
    return $highlighted ?: htmlspecialchars($text);
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

    <title>Evidence Metadata | DEIV Admin</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
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
        
        /* Search highlight */
        .search-highlight {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 500;
        }
        
        /* Filter badge */
        .filter-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .date-filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        /* Make row clickable but keep delete button functional */
        .metadata-row {
            cursor: pointer;
        }

        .metadata-row:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Sidebar -->
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
                    <li class="sidebar-item active">
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
                       
                    </ul>
                </div>
            </nav>

            <main class="content">
                <div class="container-fluid">

                    <h1 class="h3 mb-3"><strong>Evidence</strong> Metadata</h1>

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

                    <!-- ================== ENHANCED FILTER ================== -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <!-- Search -->
                                <div class="col-md-3">
                                    <label class="date-filter-label">
                                        <i class="bi bi-search"></i> Search
                                    </label>
                                    <input type="text" name="search" placeholder="Search metadata, evidence, or cases" class="form-control" value="<?= htmlspecialchars($search) ?>">
                                </div>

                                <!-- Evidence Filter -->
                                <div class="col-md-2">
                                    <label class="date-filter-label">
                                        <i class="bi bi-file-earmark"></i> Evidence
                                    </label>
                                    <select name="evidence_id" class="form-select">
                                        <option value="0">All Evidence</option>
                                        <?php foreach ($evidence_list as $e): ?>
                                            <option value="<?= $e['Evidence_id'] ?>" <?= $evidence_filter==$e['Evidence_id']?'selected':'' ?>>
                                                <?= htmlspecialchars(truncateText($e['file_name'],30)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Case Filter -->
                                <div class="col-md-2">
                                    <label class="date-filter-label">
                                        <i class="bi bi-folder"></i> Case
                                    </label>
                                    <select name="case_id" class="form-select">
                                        <option value="0">All Cases</option>
                                        <?php foreach ($case_list as $c): ?>
                                            <option value="<?= $c['Case_id'] ?>" <?= $case_filter==$c['Case_id']?'selected':'' ?>>
                                                <?= htmlspecialchars(truncateText($c['case_name'],30)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Filter Button -->
                                <div class="col-md-2">
                                    <label class="date-filter-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-funnel"></i> Filter
                                    </button>
                                </div>
                                
                                <!-- Clear Button -->
                                <div class="col-md-2">
                                    <label class="date-filter-label">&nbsp;</label>
                                    <a href="metadata_list.php" class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </a>
                                </div>
                            </form>
                            
                            <!-- Results Count -->
                            <div class="mt-3">
                                <small class="text-muted">
                                    Showing <?= $total_items ?> result<?= $total_items != 1 ? 's' : '' ?>
                                    <?php if($search || $evidence_filter || $case_filter): ?>
                                        <?php if($search): ?> matching "<?= htmlspecialchars($search) ?>"<?php endif; ?>
                                        <?php if($evidence_filter): ?> • Filtered by Evidence<?php endif; ?>
                                        <?php if($case_filter): ?> • Filtered by Case<?php endif; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- ================== TABLE ================== -->
                    <div class="card">
                        <div class="card-body table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Meta Key</th>
                                        <th>Value</th>
                                        <th>Evidence</th>
                                        <th>Case</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($metadata_items): foreach ($metadata_items as $m): ?>
                                    <tr class="metadata-row">
                                        <td onclick="showMetadataDetails(<?= $m['Meta_id'] ?>)">
                                            <code><?= highlightSearch(truncateText($m['meta_key'],25), $search) ?></code>
                                        </td>
                                        <td onclick="showMetadataDetails(<?= $m['Meta_id'] ?>)">
                                            <?= $search ? highlightSearch(truncateText($m['meta_value'], 100), $search) : formatMetaValue($m['meta_value']) ?>
                                        </td>
                                        <td onclick="showMetadataDetails(<?= $m['Meta_id'] ?>)">
                                            <?= $search ? highlightSearch($m['file_name'] ?? '—', $search) : htmlspecialchars($m['file_name'] ?? '—') ?>
                                        </td>
                                        <td onclick="showMetadataDetails(<?= $m['Meta_id'] ?>)">
                                            <?php if ($m['case_name']): ?>
                                                <span class="badge bg-info">
                                                    <?= $search ? highlightSearch($m['case_name'], $search) : htmlspecialchars($m['case_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="event.stopPropagation(); deleteMetadata(<?= $m['Meta_id'] ?>, '<?= htmlspecialchars($m['meta_key'], ENT_QUOTES) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No metadata found
                                            <?php if($search || $evidence_filter || $case_filter): ?>
                                                with the current filters.
                                                <br>
                                                <a href="metadata_list.php" class="btn btn-sm btn-outline-primary mt-2">Clear all filters</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- ================== PAGINATION ================== -->
                            <?php if ($total_pages > 1): ?>
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <?php for ($i=1;$i<=$total_pages;$i++): ?>
                                    <li class="page-item <?= $i==$current_page?'active':'' ?>">
                                        <a class="page-link"
                                           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&evidence_id=<?= $evidence_filter ?>&case_id=<?= $case_filter ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Hidden Form for Delete -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_metadata" value="1">
        <input type="hidden" name="meta_id" id="delete_meta_id">
    </form>

    <script src="js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    function showMetadataDetails(id) {
        fetch('get_metadata_details.php?id=' + id)
            .then(res => res.json())
            .then(d => {
                if (!d.success) return;
                Swal.fire({
                    title: 'Metadata Details',
                    html: `<pre>${JSON.stringify(d.meta, null, 2)}</pre>`,
                    width: 600
                });
            })
            .catch(error => {
                console.error('Error fetching metadata details:', error);
            });
    }
    
    function deleteMetadata(id, metaKey) {
        Swal.fire({
            title: 'Delete Metadata?',
            html: `Are you sure you want to delete this metadata?<br><br><strong>${metaKey}</strong><br><br>This action will be recorded in the audit log and cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-trash"></i> Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Set the metadata ID and submit the form
                document.getElementById('delete_meta_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        });
    }
    
    // Quick filter removal
    document.addEventListener('DOMContentLoaded', function() {
        // Add click handler for filter removal
        document.querySelectorAll('.filter-badge a').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.stopPropagation();
                window.location.href = this.href;
            });
        });
    });
    </script>

</body>
</html>