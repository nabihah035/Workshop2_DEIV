<?php
/**
 * ======================================================================
 * CASE REPORT GENERATOR
 * Digital Evidence Management System
 * ======================================================================
 * 
 * Description: Generates comprehensive PDF reports for digital evidence cases
 * Version: 1.0
 * Author: Digital Evidence Management System
 * Last Modified: <?= date('Y-m-d') ?>
 * 
 * ======================================================================
 */

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
// DOMPDF LIBRARY INITIALIZATION
// ----------------------------------------------------------------------
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

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
    $stmt = $pdo->prepare("
        SELECT 
            n.message,
            n.status,
            n.date AS notif_date,
            u.first_name,
            u.last_name
        FROM notification n
        LEFT JOIN user u ON u.User_id = n.User_id
        WHERE n.Evidence_id IN (
            SELECT Evidence_id FROM evidence WHERE Case_id = :id
        )
        ORDER BY n.date DESC
    ");
    $stmt->execute([':id' => $caseId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    error_log("Database error in report generator: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    die("System Error: Unable to generate report. Please contact system administrator.");
}

// ----------------------------------------------------------------------
// PDF DOCUMENT GENERATION
// ----------------------------------------------------------------------
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="generator" content="Digital Evidence Management System v1.0">
    <title>Digital Evidence Case Report - <?= htmlspecialchars($case['case_name']) ?></title>
    <style>
        /* ======================================================================
           DOCUMENT STYLESHEET
           Digital Evidence Management System - Report Template
           ====================================================================== */
        
        /* Document Base */
        body { 
            font-family: "DejaVu Sans", "Liberation Sans", Arial, sans-serif; 
            font-size: 11pt;
            line-height: 1.5;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        
        /* Header Section */
        .document-header {
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .document-title {
            font-size: 22pt;
            font-weight: bold;
            color: #2c3e50;
            margin: 0 0 5px 0;
        }
        
        .document-subtitle {
            font-size: 12pt;
            color: #7f8c8d;
            margin: 0 0 15px 0;
        }
        /* Company / System Logo */
.report-logo {
    position: absolute;
    top: 25px;
    right: 25px;
}

.report-logo img {
    max-height: 60px;
    width: auto;
}

        /* Report Metadata */
        .report-metadata {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .metadata-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .metadata-item {
            margin-bottom: 5px;
        }
        
        .metadata-label {
            font-weight: bold;
            color: #2c3e50;
            display: inline-block;
            width: 120px;
        }
        
        /* Section Styling */
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .section-header {
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 16pt;
            color: #2c3e50;
        }
        
        /* Information Cards */
        .info-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .info-card-title {
            font-weight: bold;
            color: #2c3e50;
            margin: 0 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        /* Table Styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10pt;
        }
        
        .data-table th {
            background-color: #2c3e50;
            color: #ffffff;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1a252f;
        }
        
        .data-table td {
            padding: 10px;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .data-table tr:hover {
            background-color: #e8f4fc;
        }
        
        /* Status Indicators */
        .status-indicator {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: bold;
            margin-right: 5px;
        }
        
        .status-active { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-closed { background-color: #f8d7da; color: #721c24; }
        
        /* Typography */
        .text-muted {
            color: #6c757d;
            font-size: 9pt;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        /* Evidence Metadata */
        .metadata-list {
            list-style-type: none;
            padding: 0;
            margin: 10px 0 0 0;
        }
        
        .metadata-item {
            padding: 3px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .metadata-key {
            font-weight: bold;
            color: #2c3e50;
            display: inline-block;
            width: 150px;
        }
        
        /* Footer */
        .document-footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            font-size: 9pt;
            color: #6c757d;
        }
        
        /* Code and Technical Data */
        .technical-data {
            font-family: "Courier New", monospace;
            font-size: 9pt;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
            word-break: break-all;
        }
        
        /* Role Badges */
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            background-color: #e9ecef;
            border-radius: 10px;
            font-size: 8pt;
            color: #495057;
            margin-left: 8px;
            font-weight: bold;
        }
        
        /* Utility Classes */
        .page-break {
            page-break-before: always;
        }
        
        .no-data {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>
<body>
  

    <!-- ======================================================================
         DOCUMENT HEADER
         ====================================================================== -->
    <div class="document-header">
        <h1 class="document-title">Digital Evidence Case Report</h1>
        <p class="document-subtitle">Digital Evidence Management System</p>
    </div>
    
    <!-- ======================================================================
         REPORT METADATA
         ====================================================================== -->
    <div class="report-metadata">
        <div class="metadata-grid">
            <div class="metadata-item">
                <span class="metadata-label">Report ID:</span>
                <strong><?= htmlspecialchars($reportId) ?></strong>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Generated:</span>
                <?= date('d F Y, H:i:s') ?>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Case Reference:</span>
                #<?= str_pad($case['Case_id'], 6, '0', STR_PAD_LEFT) ?>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Document Classification:</span>
                INTERNAL USE ONLY
            </div>
        </div>
    </div>
    
    <!-- ======================================================================
         SECTION 1: CASE INFORMATION
         ====================================================================== -->
    <div class="section">
        <h2 class="section-header">1. Case Details</h2>
        
        <div class="info-card">
            <h3 class="info-card-title">Case Information</h3>
            <div class="metadata-grid">
                <div class="metadata-item">
                    <span class="metadata-label">Case ID:</span>
                    <?= htmlspecialchars($case['Case_id']) ?>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Case Name:</span>
                    <?= htmlspecialchars($case['case_name']) ?>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Status:</span>
                    <span class="status-indicator status-<?= strtolower($case['case_status']) ?>">
                        <?= htmlspecialchars($case['case_status']) ?>
                    </span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Created Date:</span>
                    <?= date('d F Y, H:i:s', strtotime($case['created_at'])) ?>
                </div>
            </div>
            
            <div style="margin-top: 15px;">
                <div class="metadata-label">Description:</div>
                <div style="padding: 10px; background-color: #f8f9fa; border-radius: 4px; margin-top: 5px;">
                    <?= nl2br(htmlspecialchars($case['description'])) ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ======================================================================
         SECTION 2: INVESTIGATOR INFORMATION
         ====================================================================== -->
    <div class="section">
        <h2 class="section-header">2. Assigned Investigator</h2>
        
        <div class="info-card">
            <h3 class="info-card-title">Investigator Details</h3>
            <div class="metadata-grid">
                <div class="metadata-item">
                    <span class="metadata-label">Investigator:</span>
                    <?= htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) ?>
                    <span class="role-badge"><?= htmlspecialchars($case['role']) ?></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">Email Address:</span>
                    <?= htmlspecialchars($case['email']) ?>
                </div>
                <?php if (!empty($case['organization'])): ?>
                <div class="metadata-item">
                    <span class="metadata-label">Organization:</span>
                    <?= htmlspecialchars($case['organization']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ======================================================================
         SECTION 3: EVIDENCE INVENTORY
         ====================================================================== -->
    <div class="section">
        <h2 class="section-header">3. Evidence Inventory</h2>
        <p class="text-muted">Total Items: <?= count($evidenceList) ?></p>
        
        <?php if (empty($evidenceList)): ?>
            <div class="no-data">No evidence has been uploaded for this case.</div>
        <?php else: ?>
            <?php foreach ($evidenceList as $index => $evidence): ?>
            <div class="info-card">
                <h3 class="info-card-title">
                    Evidence Item <?= $index + 1 ?>: <?= htmlspecialchars($evidence['file_name']) ?>
                </h3>
                
                <div class="metadata-grid">
                    <div class="metadata-item">
                        <span class="metadata-label">Evidence ID:</span>
                        <?= htmlspecialchars($evidence['Evidence_id']) ?>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Status:</span>
                        <span class="status-indicator status-<?= strtolower($evidence['status']) ?>">
                            <?= htmlspecialchars($evidence['status']) ?>
                        </span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Upload Date:</span>
                        <?= date('d F Y, H:i:s', strtotime($evidence['upload_date'])) ?>
                    </div>
                </div>
                
                <div style="margin-top: 10px;">
                    <div class="metadata-label">Hash Value:</div>
                    <div class="technical-data">
                        <?= htmlspecialchars($evidence['hash_value']) ?>
                    </div>
                </div>
                
                <?php if (!empty($metadata[$evidence['Evidence_id']])): ?>
                <div style="margin-top: 15px;">
                    <div class="metadata-label">Associated Metadata:</div>
                    <ul class="metadata-list">
                        <?php foreach ($metadata[$evidence['Evidence_id']] as $meta): ?>
                        <li class="metadata-item">
                            <span class="metadata-key"><?= htmlspecialchars($meta['meta_key']) ?>:</span>
                            <?= htmlspecialchars($meta['meta_value']) ?>
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
        <h2 class="section-header">4. Audit Trail</h2>
        <p class="text-muted">
            <em>Includes all recorded actions related to this case, evidence items, and assigned investigator.</em>
        </p>
        
        <?php if (empty($auditLogs)): ?>
            <div class="no-data">No audit trail records available for this case.</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="20%">Timestamp</th>
                        <th width="25%">Action Performed</th>
                        <th width="25%">User</th>
                        <th width="15%">Role</th>
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
                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
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
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Digital Evidence Management System</strong><br>
                Official Case Report Document
            </div>
            <div class="text-right">
                Page 1 of 1<br>
                Generated: <?= date('d F Y, H:i:s') ?>
            </div>
        </div>
        <div style="text-align: center; margin-top: 10px; color: #adb5bd;">
            <em>This document is computer-generated and requires no signature.</em>
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// ----------------------------------------------------------------------
// PDF RENDERING AND OUTPUT
// ----------------------------------------------------------------------
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('isPhpEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output the generated PDF
$dompdf->stream(
    $reportId . ".pdf", 
    [
        "Attachment" => true,
        "compress" => true
    ]
);

exit;
?>