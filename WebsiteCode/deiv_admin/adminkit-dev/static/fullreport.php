<?php
// ----------------------------------------------------------------------
// SESSION INITIALIZATION
// ----------------------------------------------------------------------
session_start();

// ----------------------------------------------------------------------
// DATABASE CONNECTION
// ----------------------------------------------------------------------
$config_path = dirname(dirname(__DIR__)) . '/config/db.php';
require_once $config_path;

// ----------------------------------------------------------------------
// INPUT VALIDATION AND SANITIZATION
// ----------------------------------------------------------------------

// Validate Case ID parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    die("Error: Invalid Case ID provided. Please provide a valid numeric Case ID.");
}

$caseId = (int) $_GET['id'];

// ----------------------------------------------------------------------
// CASE EXISTENCE VERIFICATION
// ----------------------------------------------------------------------
$check = $pdo->prepare("SELECT COUNT(*) FROM case_table WHERE Case_id = :id");
$check->execute([':id' => $caseId]);

if ($check->fetchColumn() == 0) {
    header('HTTP/1.1 404 Not Found');
    die("Error: Case with ID {$caseId} not found in the database.");
}

// ----------------------------------------------------------------------
// NOTIFICATION COUNT FOR HEADER
// ----------------------------------------------------------------------
$stmt_pending = $pdo->query("SELECT COUNT(*) FROM notification WHERE status='Pending'");
$pending_count = $stmt_pending->fetchColumn();

// ----------------------------------------------------------------------
// REPORT IDENTIFICATION
// ----------------------------------------------------------------------
$reportId = "REP_" . str_pad($caseId, 6, '0', STR_PAD_LEFT) . "_" . date('Ymd_His');

// ----------------------------------------------------------------------
// DATA RETRIEVAL PROCESS
// ----------------------------------------------------------------------
try {
    // ------------------------------------------------------------------
    // 1. PRIMARY CASE INFORMATION
    // ------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT 
            c.Case_id,
            c.User_id,
            c.case_name,
            c.description,
            c.status AS case_status,
            c.created_at,
            u.first_name,
            u.last_name,
            u.email,
            u.role,
            u.organization
        FROM case_table c
        LEFT JOIN user u ON c.User_id = u.User_id
        WHERE c.Case_id = :id
    ");
    $stmt->execute([':id' => $caseId]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);

    // ------------------------------------------------------------------
    // 2. EVIDENCE COLLECTION
    // ------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT 
            Evidence_id,
            file_name,
            upload_date,
            status,
            hash_value
        FROM evidence
        WHERE Case_id = :id
        ORDER BY upload_date DESC
    ");
    $stmt->execute([':id' => $caseId]);
    $evidenceList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ------------------------------------------------------------------
    // 3. EVIDENCE METADATA EXTRACTION
    // ------------------------------------------------------------------
    $metadata = [];
    if (!empty($evidenceList)) {
        $ids = array_column($evidenceList, 'Evidence_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare("
            SELECT Evidence_id, meta_key, meta_value
            FROM metadata
            WHERE Evidence_id IN ($placeholders)
            ORDER BY Meta_id ASC
        ");
        $stmt->execute($ids);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $metadata[$m['Evidence_id']][] = $m;
        }
    }

    // ------------------------------------------------------------------
    // 4. NOTIFICATION RETRIEVAL
    // ------------------------------------------------------------------
    $stmt_check = $pdo->query("SHOW COLUMNS FROM notification");
    $notification_columns = $stmt_check->fetchAll(PDO::FETCH_COLUMN);

    $date_column =
        in_array('date', $notification_columns) ? 'date' :
        (in_array('created_at', $notification_columns) ? 'created_at' :
        (in_array('date_created', $notification_columns) ? 'date_created' :
        (in_array('timestamp', $notification_columns) ? 'timestamp' : null)));

    $sql_notification = "
        SELECT 
            n.message,
            n.status,
            " . ($date_column ? "n.$date_column AS notif_date" : "NOW() AS notif_date") . ",
            u.first_name,
            u.last_name
        FROM notification n
        LEFT JOIN user u ON u.User_id = n.User_id
        WHERE n.Evidence_id IN (
            SELECT Evidence_id FROM evidence WHERE Case_id = :id
        )
        ORDER BY notif_date DESC
    ";
    $stmt_note = $pdo->prepare($sql_notification);
    $stmt_note->execute([':id' => $caseId]);
    $notifications = $stmt_note->fetchAll(PDO::FETCH_ASSOC);

    // ------------------------------------------------------------------
    // 5. AUDIT TRAIL COMPILATION
    // ------------------------------------------------------------------
    $checkColumn = $pdo->query("SHOW COLUMNS FROM audit_trail LIKE 'Case_id'");
    $hasCaseId = $checkColumn->rowCount() > 0;
    
    $checkEvidenceColumn = $pdo->query("SHOW COLUMNS FROM audit_trail LIKE 'Evidence_id'");
    $hasEvidenceId = $checkEvidenceColumn->rowCount() > 0;
    
    $auditConditions = [];
    $auditParams = [':user_id' => $case['User_id']];
    
    // Include actions by the case owner
    $auditConditions[] = "a.User_id = :user_id";
    
    // Include actions on this specific case (if column exists)
    if ($hasCaseId) {
        $auditConditions[] = "a.Case_id = :case_id";
        $auditParams[':case_id'] = $caseId;
    }
    
    // Include actions on evidence from this case (if column exists)
    if ($hasEvidenceId && !empty($evidenceList)) {
        $evidenceIds = array_column($evidenceList, 'Evidence_id');
        $evidencePlaceholders = [];
        foreach ($evidenceIds as $idx => $eid) {
            $paramName = ":eid_$idx";
            $evidencePlaceholders[] = $paramName;
            $auditParams[$paramName] = $eid;
        }
        $auditConditions[] = "a.Evidence_id IN (" . implode(',', $evidencePlaceholders) . ")";
    }
    
    $whereClause = implode(' OR ', $auditConditions);
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            a.action,
            a.date_time,
            a.ip_address,
            u.first_name,
            u.last_name,
            u.role
        FROM audit_trail a
        LEFT JOIN user u ON a.User_id = u.User_id
        WHERE $whereClause
        ORDER BY a.date_time DESC
    ");
    $stmt->execute($auditParams);
    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error in report viewer: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    die("System Error: Unable to generate report. Please contact system administrator.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Evidence Case Report - <?= htmlspecialchars($case['case_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ======================================================================
           DOCUMENT STYLESHEET
           Digital Evidence Management System - Web Report Template
           ====================================================================== */
        
        /* Document Base */
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f5f7fa;
            color: #333333;
            line-height: 1.6;
        }
        
        /* Main Container */
        .report-container {
            max-width: 1200px;
            margin: 2rem auto;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        
        /* Header Section */
        .document-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            color: #2c3e50;
            padding: 2rem;
            border-radius: 8px 8px 0 0;
            border-bottom: 4px solid #3498db;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .header-content {
            flex: 1;
        }
        
        .header-logo {
            flex-shrink: 0;
        }
        
        .header-logo img {
            max-height: 80px;
            max-width: 200px;
            object-fit: contain;
        }
        
        .document-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
        }
        
        .document-title i {
            color: #3498db;
        }
        
        .document-subtitle {
            font-size: 1.1rem;
            opacity: 0.7;
            margin: 0;
            color: #495057;
        }
        
        /* Report Metadata */
        .report-metadata {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #3498db;
            padding: 1.5rem;
            margin: 1.5rem;
            border-radius: 4px;
        }
        
        .metadata-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .metadata-item {
            display: flex;
            flex-direction: column;
        }
        
        .metadata-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .metadata-value {
            font-size: 1rem;
            color: #495057;
        }
        
        /* Section Styling */
        .section {
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .section-header {
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: #2c3e50;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-icon {
            color: #3498db;
            font-size: 1.3rem;
        }
        
        /* Information Cards */
        .info-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: box-shadow 0.3s ease;
        }
        
        .info-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .info-card-title {
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 1rem 0;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #eee;
            font-size: 1.1rem;
        }
        
        /* Status Indicators */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            border-radius: 4px;
            font-size: 0.813rem;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
        }
        
        .status-active { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }
        .status-pending { 
            background-color: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeeba;
        }
        .status-closed { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
        }
        .status-complete {
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }
        .status-in.progress,
        .status-in_progress {
            background-color: #d1ecf1; 
            color: #0c5460; 
            border: 1px solid #bee5eb;
        }
        
        /* Role Badges */
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            margin: 1rem 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .data-table thead {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        
        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 1rem;
            border-top: 1px solid #dee2e6;
            vertical-align: top;
        }
        
        .data-table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .data-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .data-table tbody tr:hover {
            background-color: #e3f2fd;
        }
        
        /* Technical Data */
        .technical-data {
            font-family: "Monaco", "Menlo", "Courier New", monospace;
            font-size: 0.85rem;
            background-color: #f8f9fa;
            padding: 0.75rem;
            border-radius: 4px;
            border: 1px solid #e9ecef;
            word-break: break-all;
            color: #495057;
        }
        
        /* Metadata List */
        .metadata-list {
            list-style-type: none;
            padding: 0;
            margin: 1rem 0 0 0;
        }
        
        .metadata-list-item {
            padding: 0.5rem 0;
            border-bottom: 1px dashed #eee;
            display: flex;
            gap: 1rem;
        }
        
        .metadata-list-item:last-child {
            border-bottom: none;
        }
        
        .metadata-key {
            font-weight: 600;
            color: #2c3e50;
            min-width: 150px;
            flex-shrink: 0;
        }
        
        .metadata-value {
            color: #495057;
            flex-grow: 1;
        }
        
        /* Empty State */
        .no-data {
            text-align: center;
            padding: 3rem 2rem;
            background-color: #f8f9fa;
            border-radius: 6px;
            color: #6c757d;
            font-style: italic;
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            padding: 1.5rem;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
        }
        
        .btn-custom {
            padding: 0.65rem 1.5rem;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            color: white;
        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary-custom {
            background-color: #6c757d;
            border: none;
            color: white;
        }
        
        .btn-secondary-custom:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }
        
        /* Footer */
        .document-footer {
            margin-top: 2rem;
            padding: 1.5rem;
            border-top: 2px solid #dee2e6;
            background-color: #f8f9fa;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .report-container {
                margin: 1rem;
            }
            
            .document-header {
                flex-direction: column;
                text-align: center;
            }
            
            .header-logo {
                order: -1;
                margin-bottom: 1rem;
            }
            
            .metadata-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .data-table {
                font-size: 0.85rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.5rem;
            }
        }
        
        /* Print Styles */
        @media print {
            .action-buttons,
            .btn {
                display: none;
            }
            
            .report-container {
                box-shadow: none;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- ======================================================================
             DOCUMENT HEADER
             ====================================================================== -->
        <div class="document-header">
            <div class="header-content">
                <h1 class="document-title">
                    <i class="fas fa-file-alt"></i> Digital Evidence Case Report
                </h1>
                <p class="document-subtitle">Digital Evidence Management System</p>
            </div>
            <div class="header-logo">
                <!-- Replace 'your-logo.png' with your actual logo path -->
                <img src="/deiv_admin/adminkit-dev/src/img/icons/DEIV.png" alt="Organization Logo" onerror="this.style.display='none'">
            </div>
        </div>
        
        <!-- ======================================================================
             REPORT METADATA
             ====================================================================== -->
        <div class="report-metadata">
            <div class="metadata-grid">
                <div class="metadata-item">
                    <span class="metadata-label">Report ID</span>
                    <span class="metadata-value"><strong><?= htmlspecialchars($reportId) ?></strong></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Generated</span>
                    <span class="metadata-value"><?= date('d F Y, H:i:s') ?></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Case Reference</span>
                    <span class="metadata-value">#<?= str_pad($case['Case_id'], 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Classification</span>
                    <span class="metadata-value">INTERNAL USE ONLY</span>
                </div>
            </div>
        </div>
        
        <!-- ======================================================================
             SECTION 1: CASE INFORMATION
             ====================================================================== -->
        <div class="section">
            <h2 class="section-header">
                <i class="fas fa-folder-open section-icon"></i>
                1. Case Details
            </h2>
            
            <div class="info-card">
                <h3 class="info-card-title">Case Information</h3>
                <div class="metadata-grid">
                    <div class="metadata-item">
                        <span class="metadata-label">Case ID</span>
                        <span class="metadata-value"><?= htmlspecialchars($case['Case_id']) ?></span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Case Name</span>
                        <span class="metadata-value"><?= htmlspecialchars($case['case_name']) ?></span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Status</span>
                        <span class="metadata-value">
                            <span class="status-badge status-<?= strtolower($case['case_status']) ?>">
                                <?= htmlspecialchars($case['case_status']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Created Date</span>
                        <span class="metadata-value"><?= date('d F Y, H:i:s', strtotime($case['created_at'])) ?></span>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <div class="metadata-label" style="margin-bottom: 0.5rem;">Description</div>
                    <div style="padding: 1rem; background-color: #f8f9fa; border-radius: 4px; border-left: 3px solid #3498db;">
                        <?= nl2br(htmlspecialchars($case['description'])) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ======================================================================
             SECTION 2: INVESTIGATOR INFORMATION
             ====================================================================== -->
        <div class="section">
            <h2 class="section-header">
                <i class="fas fa-user-shield section-icon"></i>
                2. Assigned Investigator
            </h2>
            
            <div class="info-card">
                <h3 class="info-card-title">Investigator Details</h3>
                <div class="metadata-grid">
                    <div class="metadata-item">
                        <span class="metadata-label">Investigator</span>
                        <span class="metadata-value">
                            <?= htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) ?>
                            <span class="role-badge"><?= htmlspecialchars($case['role']) ?></span>
                        </span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Email Address</span>
                        <span class="metadata-value"><?= htmlspecialchars($case['email']) ?></span>
                    </div>
                    <?php if (!empty($case['organization'])): ?>
                    <div class="metadata-item">
                        <span class="metadata-label">Organization</span>
                        <span class="metadata-value"><?= htmlspecialchars($case['organization']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ======================================================================
             SECTION 3: EVIDENCE INVENTORY
             ====================================================================== -->
        <div class="section">
            <h2 class="section-header">
                <i class="fas fa-box section-icon"></i>
                3. Evidence Inventory
            </h2>
            <p class="text-muted mb-3">
                <i class="fas fa-info-circle"></i> Total Items: <strong><?= count($evidenceList) ?></strong>
            </p>
            
            <?php if (empty($evidenceList)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>No evidence has been uploaded for this case.</p>
                </div>
            <?php else: ?>
                <?php foreach ($evidenceList as $index => $evidence): ?>
                <div class="info-card">
                    <h3 class="info-card-title">
                        <i class="fas fa-file"></i> Evidence Item <?= $index + 1 ?>: <?= htmlspecialchars($evidence['file_name']) ?>
                    </h3>
                    
                    <div class="metadata-grid">
                        <div class="metadata-item">
                            <span class="metadata-label">Evidence ID</span>
                            <span class="metadata-value"><?= htmlspecialchars($evidence['Evidence_id']) ?></span>
                        </div>
                        <div class="metadata-item">
                            <span class="metadata-label">Status</span>
                            <span class="metadata-value">
                                <span class="status-badge status-<?= strtolower($evidence['status']) ?>">
                                    <?= htmlspecialchars($evidence['status']) ?>
                                </span>
                            </span>
                        </div>
                        <div class="metadata-item">
                            <span class="metadata-label">Upload Date</span>
                            <span class="metadata-value"><?= date('d F Y, H:i:s', strtotime($evidence['upload_date'])) ?></span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem;">
                        <div class="metadata-label" style="margin-bottom: 0.5rem;">Hash Value</div>
                        <div class="technical-data">
                            <?= htmlspecialchars($evidence['hash_value']) ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($metadata[$evidence['Evidence_id']])): ?>
                    <div style="margin-top: 1.5rem;">
                        <div class="metadata-label" style="margin-bottom: 0.75rem;">Associated Metadata</div>
                        <ul class="metadata-list">
                            <?php foreach ($metadata[$evidence['Evidence_id']] as $meta): ?>
                            <li class="metadata-list-item">
                                <span class="metadata-key"><?= htmlspecialchars($meta['meta_key']) ?></span>
                                <span class="metadata-value"><?= htmlspecialchars($meta['meta_value']) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- ======================================================================
             SECTION 5: AUDIT TRAIL
             ====================================================================== -->
        <div class="section">
            <h2 class="section-header">
                <i class="fas fa-history section-icon"></i>
                4. Audit Trail
            </h2>
            <p class="text-muted mb-3">
                <em><i class="fas fa-info-circle"></i> Includes all recorded actions related to this case, evidence items, and assigned investigator.</em>
            </p>
            
            <?php if (empty($auditLogs)): ?>
                <div class="no-data">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No audit trail records available for this case.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="18%">Timestamp</th>
                            <th width="30%">Action Performed</th>
                            <th width="25%">User</th>
                            <th width="12%">Role</th>
                            <th width="15%">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i:s', strtotime($log['date_time'])) ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td>
                                <?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?>
                            </td>
                            <td>
                                <span class="role-badge"><?= htmlspecialchars($log['role']) ?></span>
                            </td>
                            <td><code><?= htmlspecialchars($log['ip_address']) ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- ======================================================================
             DOCUMENT FOOTER
             ====================================================================== -->
        <div class="document-footer">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <strong><i class="fas fa-shield-alt"></i> Digital Evidence Management System</strong><br>
                    <span class="text-muted">Official Case Report Document</span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted">Generated: <?= date('d F Y, H:i:s') ?></span>
                </div>
            </div>
            <div class="text-center mt-3">
                <em class="text-muted">
                    <i class="fas fa-lock"></i> This document contains sensitive information and is for authorized personnel only.
                </em>
            </div>
        </div>
        
        <!-- ======================================================================
             ACTION BUTTONS
             ====================================================================== -->
        <div class="action-buttons">
            <a href="javascript:history.back()" class="btn btn-custom btn-secondary-custom">
                <i class="fas fa-arrow-left"></i> Back to Cases
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>