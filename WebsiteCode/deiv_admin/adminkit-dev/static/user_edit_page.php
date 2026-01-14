<?php
session_start();

// Minimal access check (uncomment/adjust as needed)
/*
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: index.php");
    exit;
}
*/

require_once dirname(dirname(__DIR__)) . '/config/db.php';

// Fetch pending users for notifications
$pending_users = $pdo->query("SELECT User_id, username, email, created_at FROM user WHERE status='Pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count($pending_users);

$email = $_GET['email'] ?? '';
$uid = $_GET['uid'] ?? '';

// Get user data from MySQL if email is provided
$mysql_user = null;
if (!empty($email)) {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->execute([$email]);
    $mysql_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mysql_user && empty($uid)) {
        $uid = $mysql_user['User_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=1">
    <meta name="description" content="DEIV - Digital Evidence Integrity Verification System">
    <meta name="author" content="DEIV">
    <meta name="keywords" content="digital evidence, forensic, investigation, security">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" href="img/icons/icon-48x48.png" />
    <link rel="canonical" href="https://demo-basic.adminkit.io/" />

    <title>Edit User | DEIV Admin</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.5rem 2rem;
            border-bottom: none;
        }
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            display: block;
        }
        .required:after {
            content: " *";
            color: #e53e3e;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #718096;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .section-title {
            color: #4a5568;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .info-box {
            background: #f7fafc;
            border: 2px dashed #cbd5e0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-box p {
            color: #4a5568;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .info-box .material-icons {
            color: #667eea;
            font-size: 1.25rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .password-hint {
            font-size: 0.875rem;
            color: #718096;
            margin-top: 0.25rem;
        }
        /* Data comparison table */
        .comparison-table {
            font-size: 0.9rem;
        }
        .comparison-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .data-match {
            background-color: #d1fae5 !important;
        }
        .data-mismatch {
            background-color: #fee2e2 !important;
        }
        /* Action buttons in table */
        .table-action-btn {
            padding: 2px 8px;
            font-size: 0.8rem;
        }
        /* Data source indicator */
        .data-source-indicator {
            font-size: 0.85rem;
            padding: 4px 10px;
            border-radius: 20px;
        }
        /* Navbar notification indicator */
        .indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #f56565;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
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

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="index.php">
                            <i class="align-middle material-icons">home</i>
                            <span class="align-middle">Dashboard</span>
                        </a>
                    </li>

                    <li class="sidebar-item active">
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

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="evidence_list.php">
                            <i class="align-middle material-icons">inventory_2</i>
                            <span class="align-middle">Evidence Records</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="metadata_list.php">
                            <i class="align-middle material-icons">list_alt</i>
                            <span class="align-middle">Evidence Metadata</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="case_list.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Case Files</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="audit_logs.php">
                            <i class="align-middle material-icons">history</i>
                            <span class="align-middle">Audit Logs</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a class="sidebar-link" href="logout.php">
                            <i class="material-icons align-middle">logout</i>
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
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h2 class="mb-1">Edit User</h2>
                                            <p class="mb-0 opacity-75">
                                                Update user information in both MySQL and Firebase
                                                <span id="data-source-indicator" class="badge bg-secondary data-source-indicator ms-2">
                                                    <i class="material-icons align-middle" style="font-size: 14px">hourglass_empty</i> Loading data...
                                                </span>
                                            </p>
                                        </div>
                                        <div>
                                            <a href="user_management.php" class="btn btn-outline-light">
                                                <i class="material-icons align-middle me-1">arrow_back</i> Back
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body" style="padding: 2.5rem;">
                                    
                                    <div class="info-box">
                                        <p>
                                            <i class="material-icons">info</i>
                                            This user will be updated in both MySQL database and Firebase for real-time access.
                                            <?php if($mysql_user): ?>
                                                <span class="ms-2 text-success">
                                                    <i class="material-icons align-middle">check_circle</i>
                                                    MySQL data loaded
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <div id="firebase-comparison" class="alert alert-info d-none">
                                        <!-- Firebase comparison will be inserted here by JavaScript -->
                                    </div>

                                    <div class="section-title">User Information</div>

                                    <form id="edit-user-form">
                                        <input type="hidden" id="edit-user-id" name="id" value="<?= htmlspecialchars($uid ?? '') ?>">
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label required">First Name</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light">
                                                            <i class="material-icons">badge</i>
                                                        </span>
                                                        <input id="edit-first-name" name="first_name" class="form-control" 
                                                               placeholder="Enter first name" 
                                                               value="<?= htmlspecialchars($mysql_user['first_name'] ?? '') ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Last Name</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light">
                                                            <i class="material-icons">badge</i>
                                                        </span>
                                                        <input id="edit-last-name" name="last_name" class="form-control" 
                                                               placeholder="Enter last name" 
                                                               value="<?= htmlspecialchars($mysql_user['last_name'] ?? '') ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label required">Email Address</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light">
                                                            <i class="material-icons">email</i>
                                                        </span>
                                                        <input id="edit-email" name="email" type="email" class="form-control" 
                                                               placeholder="Enter email address" required
                                                               value="<?= htmlspecialchars($mysql_user['email'] ?? $email) ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Organization</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light">
                                                            <i class="material-icons">business</i>
                                                        </span>
                                                        <input id="edit-organization" name="organization" class="form-control" 
                                                               placeholder="Enter organization name"
                                                               value="<?= htmlspecialchars($mysql_user['organization'] ?? '') ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Password</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light">
                                                            <i class="material-icons">lock</i>
                                                        </span>
                                                        <input id="edit-password" name="password" type="password" class="form-control" 
                                                               placeholder="Enter new password (optional)">
                                                    </div>
                                                    <div class="password-hint">Leave blank to keep current password</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label required">Role</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light">
                                                            <i class="material-icons">admin_panel_settings</i>
                                                        </span>
                                                        <select id="edit-role" name="role" class="form-select" required>
                                                            <option value="">Select a role</option>
                                                            <option value="Admin" <?= ($mysql_user['role'] ?? '') == 'Admin' ? 'selected' : '' ?>>Admin</option>
                                                            <option value="Law agencies" <?= ($mysql_user['role'] ?? '') == 'Law agencies' ? 'selected' : '' ?>>Law Agencies</option>
                                                            <option value="Digital Forensic Investigator" <?= ($mysql_user['role'] ?? '') == 'Digital Forensic Investigator' ? 'selected' : '' ?>>Digital Forensic Investigator</option>
                                                            <option value="Legal Professionals" <?= ($mysql_user['role'] ?? '') == 'Legal Professionals' ? 'selected' : '' ?>>Legal Professionals</option>
                                                            <option value="Institution" <?= ($mysql_user['role'] ?? '') == 'Institution' ? 'selected' : '' ?>>Institution</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label required">Status</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light">
                                                            <i class="material-icons">check_circle</i>
                                                        </span>
                                                        <select name="status" class="form-select" required>
                                                            <option value="Pending" <?= ($mysql_user['status'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                            <option value="Active" <?= ($mysql_user['status'] ?? '') == 'Active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="Inactive" <?= ($mysql_user['status'] ?? '') == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                                            <option value="Rejected" <?= ($mysql_user['status'] ?? '') == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Username</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light">
                                                            <i class="material-icons">person</i>
                                                        </span>
                                                        <input id="edit-username" name="username" class="form-control" 
                                                               placeholder="Enter username"
                                                               value="<?= htmlspecialchars($mysql_user['username'] ?? '') ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                            <div>
                                                <button type="button" id="sync-firebase-btn" class="btn btn-info" style="display: none;">
                                                    <i class="material-icons align-middle me-1">sync</i> Sync to Firebase
                                                </button>
                                                <button type="button" id="fill-from-firebase-btn" class="btn btn-warning" style="display: none;">
                                                    <i class="material-icons align-middle me-1">download</i> Fill from Firebase
                                                </button>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="user_management.php" class="btn btn-secondary">
                                                    <i class="material-icons align-middle me-1">arrow_back</i> Cancel
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="material-icons align-middle me-1">save</i> Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="js/app.js"></script>
    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-firestore-compat.js"></script>
    
    <script>
        // Firebase Configuration
        const firebaseConfig = {
            apiKey: "AIzaSyDpjUo6GfJEnxTwKBjnfzpkQruSzWvov-I",
            authDomain: "deiv-ac114.firebaseapp.com",
            projectId: "deiv-ac114",
            storageBucket: "deiv-ac114.firebasestorage.app",
            messagingSenderId: "846119732034",
            appId: "1:846119732034:web:213dfed9358f6d38773edb",
            measurementId: "G-V591C9RQJ4"
        };

        // Initialize Firebase
        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }
        const db = firebase.firestore();

        (function(){
            console.log('=== user_edit_page.js loaded ===');
            
            // Get URL parameters
            const params = new URLSearchParams(window.location.search);
            const emailParam = params.get('email') || '';
            const uidParam = params.get('uid') || '';
            
            console.log('URL params - email:', emailParam, 'uid:', uidParam);

            // Get form elements
            const form = document.getElementById('edit-user-form');
            const inputId = document.getElementById('edit-user-id');
            const inputF = document.getElementById('edit-first-name');
            const inputL = document.getElementById('edit-last-name');
            const inputE = document.getElementById('edit-email');
            const inputO = document.getElementById('edit-organization');
            const inputR = document.getElementById('edit-role');
            const inputStatus = document.querySelector('select[name="status"]');
            const inputPassword = document.getElementById('edit-password');
            const inputUsername = document.getElementById('edit-username');
            const syncBtn = document.getElementById('sync-firebase-btn');
            const fillBtn = document.getElementById('fill-from-firebase-btn');
            const comparisonDiv = document.getElementById('firebase-comparison');

            // Debug: Check if elements exist
            console.log('Form elements check:', {
                form: !!form,
                inputId: !!inputId,
                inputF: !!inputF,
                inputL: !!inputL,
                inputE: !!inputE,
                inputO: !!inputO,
                inputR: !!inputR,
                inputStatus: !!inputStatus,
                inputPassword: !!inputPassword,
                inputUsername: !!inputUsername
            });

            if (!form) {
                console.error('ERROR: Form element not found! Check if form has id="edit-user-form"');
                return;
            }

            // Clear password field
            if (inputPassword) inputPassword.value = '';

            // Prefill from URL params
            if (inputE && !inputE.value) inputE.value = decodeURIComponent(emailParam);
            if (inputId && !inputId.value) inputId.value = uidParam;

            // Function to update data source indicator
            function updateDataSourceIndicator(source, message) {
                const indicator = document.getElementById('data-source-indicator');
                if (!indicator) return;
                
                let badgeClass = 'bg-secondary';
                let icon = 'hourglass_empty';
                
                switch(source) {
                    case 'mysql':
                        badgeClass = 'bg-primary';
                        icon = 'storage';
                        break;
                    case 'firebase':
                        badgeClass = 'bg-warning';
                        icon = 'cloud';
                        break;
                    case 'both':
                        badgeClass = 'bg-success';
                        icon = 'sync';
                        break;
                    case 'error':
                        badgeClass = 'bg-danger';
                        icon = 'error';
                        break;
                    default:
                        badgeClass = 'bg-secondary';
                        icon = 'hourglass_empty';
                }
                
                indicator.className = `badge ${badgeClass} data-source-indicator ms-2`;
                indicator.innerHTML = `<i class="material-icons align-middle" style="font-size: 14px">${icon}</i> ${message}`;
            }

            // Function to copy Firebase value to form
            function copyToForm(fieldId, value) {
                const fieldMap = {
                    'first_name': 'edit-first-name',
                    'last_name': 'edit-last-name',
                    'email': 'edit-email',
                    'organization': 'edit-organization',
                    'role': 'edit-role',
                    'status': 'status',
                    'username': 'edit-username'
                };
                
                const input = document.getElementById(fieldMap[fieldId] || fieldId);
                if (input) {
                    // Special handling for status (select element)
                    if (fieldId === 'status') {
                        // Convert to proper case for select option
                        const statusValue = value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
                        input.value = statusValue;
                    } else {
                        input.value = value;
                    }
                    
                    console.log(`✅ Copied ${fieldId}: ${value} to form`);
                    
                    // Refresh comparison after a short delay
                    setTimeout(() => {
                        if (window.currentFirebaseData) {
                            showFirebaseComparison(window.currentFirebaseData);
                        }
                    }, 300);
                }
            }

            // Function to fill all form fields from Firebase
            function fillAllFromFirebase(firebaseData) {
                if (!firebaseData) {
                    alert('No Firebase data available');
                    return;
                }
                
                if (!confirm('Fill ALL form fields with Firebase data?\n\nThis will overwrite all current form values.')) {
                    return;
                }
                
                // Fill all fields
                if (inputF) inputF.value = firebaseData.first_name || '';
                if (inputL) inputL.value = firebaseData.last_name || '';
                if (inputE) inputE.value = firebaseData.email || decodeURIComponent(emailParam) || '';
                if (inputO) inputO.value = firebaseData.organization || '';
                if (inputR) inputR.value = firebaseData.role || '';
                if (inputStatus && firebaseData.status) {
                    const statusValue = firebaseData.status.charAt(0).toUpperCase() + firebaseData.status.slice(1).toLowerCase();
                    inputStatus.value = statusValue;
                }
                if (inputUsername) inputUsername.value = firebaseData.username || '';
                
                console.log('✅ Form filled with ALL Firebase data');
                
                // Refresh comparison
                if (window.currentFirebaseData) {
                    showFirebaseComparison(window.currentFirebaseData);
                }
            }

            // Function to sync to Firebase
            async function syncToFirebase(data) {
                const uid = inputId ? inputId.value : uidParam;
                if (!uid) {
                    alert('User ID is required to sync');
                    return;
                }
                
                if (!confirm('Sync form data to Firebase?\n\nThis will update Firebase with the current form values.')) {
                    return;
                }
                
                try {
                    syncBtn.innerHTML = '<i class="material-icons align-middle me-1">hourglass_empty</i> Syncing...';
                    syncBtn.disabled = true;
                    
                    // Update Firebase directly using client-side SDK
                    await db.collection("users").doc(uid).update({
                        first_name: data.first_name || '',
                        last_name: data.last_name || '',
                        email: data.email || '',
                        organization: data.organization || '',
                        role: data.role || '',
                        status: (data.status || '').toLowerCase(),
                        username: data.username || '',
                        mysql_synced: true,
                        mysql_synced_at: new Date().toISOString()
                    });
                    
                    alert('✅ Successfully synced to Firebase!');
                    syncBtn.innerHTML = '<i class="material-icons align-middle me-1">check_circle</i> Synced';
                    syncBtn.disabled = true;
                    syncBtn.classList.remove('btn-info');
                    syncBtn.classList.add('btn-success');
                    
                    // Refresh comparison after a delay
                    setTimeout(() => {
                        loadFirebaseData();
                    }, 1000);
                    
                } catch (error) {
                    console.error('Firebase sync error:', error);
                    
                    // If direct update fails, try using PHP API
                    if (error.code === 'permission-denied' || error.code === 'failed-precondition') {
                        alert('⚠️ Direct Firebase update not allowed. Using server sync instead...');
                        syncViaPHP(data, uid);
                    } else {
                        alert('❌ Failed to sync to Firebase: ' + error.message);
                        syncBtn.innerHTML = '<i class="material-icons align-middle me-1">sync</i> Sync to Firebase';
                        syncBtn.disabled = false;
                    }
                }
            }

            // Alternative sync using PHP API
            async function syncViaPHP(data, uid) {
                try {
                    // Prepare form data for PHP sync
                    const formData = new FormData();
                    formData.append('id', uid);
                    formData.append('first_name', data.first_name || '');
                    formData.append('last_name', data.last_name || '');
                    formData.append('email', data.email || '');
                    formData.append('username', data.username || '');
                    formData.append('organization', data.organization || '');
                    formData.append('role', data.role || '');
                    formData.append('status', data.status || '');
                    formData.append('sync_firebase', 'true');
                    
                    // Use update_user.php for sync
                    const response = await fetch('update_user.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Successfully synced to Firebase via server!');
                        syncBtn.innerHTML = '<i class="material-icons align-middle me-1">check_circle</i> Synced';
                        syncBtn.disabled = true;
                        syncBtn.classList.remove('btn-info');
                        syncBtn.classList.add('btn-success');
                        
                        // Refresh comparison
                        setTimeout(() => {
                            loadFirebaseData();
                        }, 1000);
                    } else {
                        throw new Error(result.message || 'Server sync failed');
                    }
                    
                } catch (error) {
                    console.error('PHP sync error:', error);
                    alert('❌ Failed to sync via server: ' + error.message);
                    syncBtn.innerHTML = '<i class="material-icons align-middle me-1">sync</i> Sync to Firebase';
                    syncBtn.disabled = false;
                }
            }

            // Function to show Firebase comparison
            function showFirebaseComparison(firebaseData) {
                if (!firebaseData || !comparisonDiv) return;
                
                // Store for later use
                window.currentFirebaseData = firebaseData;
                
                const mysqlData = {
                    first_name: inputF ? inputF.value : '',
                    last_name: inputL ? inputL.value : '',
                    email: inputE ? inputE.value : '',
                    organization: inputO ? inputO.value : '',
                    role: inputR ? inputR.value : '',
                    status: inputStatus ? inputStatus.value : '',
                    username: inputUsername ? inputUsername.value : ''
                };
                
                // Check if MySQL has data or is empty
                const mysqlHasData = Object.values(mysqlData).some(val => val && val.trim() !== '');
                
                let html = `
                    <h6><i class="material-icons align-middle me-2">compare_arrows</i> Data Comparison</h6>
                    <div class="small mt-2 mb-2">
                        <span class="${mysqlHasData ? 'text-primary' : 'text-warning'}">
                            <i class="material-icons align-middle" style="font-size: 16px">${mysqlHasData ? 'storage' : 'warning'}</i>
                            ${mysqlHasData ? 'MySQL data loaded' : 'MySQL: No data found'}
                        </span> | 
                        <span class="text-warning">
                            <i class="material-icons align-middle" style="font-size: 16px">cloud</i>
                            Firebase: Data loaded
                        </span>
                    </div>
                    <table class="table table-sm comparison-table">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>MySQL (Form)</th>
                                <th>Firebase (Current)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>`;
                
                // Check each field
                const fields = ['first_name', 'last_name', 'email', 'organization', 'role', 'status', 'username'];
                let hasDifferences = false;
                
                fields.forEach(field => {
                    const mysqlVal = mysqlData[field] || '(empty)';
                    const firebaseVal = firebaseData[field] || '(empty)';
                    const mysqlDisplay = mysqlVal === '(empty)' ? '<span class="text-muted">(empty)</span>' : mysqlVal;
                    const firebaseDisplay = firebaseVal === '(empty)' ? '<span class="text-muted">(empty)</span>' : firebaseVal;
                    
                    // Normalize values for comparison (case-insensitive, trim)
                    const mysqlNormalized = mysqlVal.toString().toLowerCase().trim();
                    const firebaseNormalized = firebaseVal.toString().toLowerCase().trim();
                    const isMatch = mysqlNormalized === firebaseNormalized;
                    
                    if (!isMatch) {
                        hasDifferences = true;
                    }
                    
                    html += `
                        <tr class="${isMatch ? 'data-match' : 'data-mismatch'}">
                            <td><strong>${field.replace('_', ' ')}</strong></td>
                            <td>${mysqlDisplay}</td>
                            <td>${firebaseDisplay}</td>
                            <td>
                                ${!isMatch && mysqlVal === '(empty)' && firebaseVal !== '(empty)' ? 
                                    `<button class="btn btn-sm btn-outline-success table-action-btn" onclick="copyToForm('${field}', '${firebaseVal.replace(/'/g, "\\'")}')">
                                        <i class="material-icons" style="font-size: 14px">arrow_downward</i>
                                    </button>` : 
                                    ''}
                            </td>
                        </tr>`;
                });
                
                html += `</tbody></table>`;
                
                // Add summary
                const differences = fields.filter(field => {
                    const mysqlVal = mysqlData[field] || '';
                    const firebaseVal = firebaseData[field] || '';
                    return mysqlVal.toString().toLowerCase().trim() !== firebaseVal.toString().toLowerCase().trim();
                }).length;
                
                html += `
                    <div class="alert ${differences > 0 ? 'alert-warning' : 'alert-success'} mt-3">
                        <i class="material-icons align-middle me-2">${differences > 0 ? 'warning' : 'check_circle'}</i>
                        ${differences} field${differences !== 1 ? 's' : ''} differ${differences !== 1 ? '' : 's'} between MySQL and Firebase
                    </div>
                    
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-warning btn-sm" onclick="fillAllFromFirebase(${JSON.stringify(firebaseData)})">
                            <i class="material-icons align-middle me-1">download</i> Fill ALL from Firebase
                        </button>
                        <button type="button" class="btn btn-info btn-sm" onclick="syncToFirebase(${JSON.stringify(mysqlData)})">
                            <i class="material-icons align-middle me-1">upload</i> Sync Form to Firebase
                        </button>
                    </div>`;
                
                comparisonDiv.innerHTML = html;
                comparisonDiv.classList.remove('d-none');
                
                // Update data source indicator
                updateDataSourceIndicator(mysqlHasData ? 'both' : 'firebase', 
                    mysqlHasData ? 'Both databases' : 'Firebase only');
                
                // Show sync button if there are differences
                if (hasDifferences && syncBtn) {
                    syncBtn.style.display = 'inline-flex';
                    syncBtn.onclick = () => syncToFirebase(mysqlData);
                }
                
                // Show fill from Firebase button
                if (fillBtn) {
                    fillBtn.style.display = 'inline-flex';
                    fillBtn.onclick = () => fillAllFromFirebase(firebaseData);
                }
            }

            // Function to load Firebase data
            async function loadFirebaseData() {
                const uid = inputId ? inputId.value : uidParam;
                if (!uid) {
                    console.log('⚠️ No user ID available for Firebase lookup');
                    updateDataSourceIndicator('error', 'No User ID');
                    return;
                }
                
                try {
                    console.log('🔥 Fetching Firebase data for user:', uid);
                    const doc = await db.collection("users").doc(uid).get();
                    
                    if (doc.exists) {
                        const firebaseData = doc.data();
                        console.log('🔥 Firebase data:', firebaseData);
                        
                        // Update data source indicator
                        updateDataSourceIndicator('firebase', 'Firebase data loaded');
                        
                        showFirebaseComparison(firebaseData);
                    } else {
                        console.warn('⚠️ No Firebase document found for user:', uid);
                        updateDataSourceIndicator('mysql', 'MySQL only (Firebase not found)');
                        
                        if (comparisonDiv) {
                            comparisonDiv.innerHTML = `
                                <div class="alert alert-warning">
                                    <i class="material-icons align-middle me-2">warning</i>
                                    User not found in Firebase.<br>
                                    <small>User ID: ${uid}</small><br>
                                    <small>This user exists only in MySQL. They will be created in Firebase when you save.</small>
                                </div>`;
                            comparisonDiv.classList.remove('d-none');
                        }
                    }
                } catch (error) {
                    console.error('❌ Error loading Firebase data:', error);
                    updateDataSourceIndicator('error', 'Firebase error');
                    
                    if (comparisonDiv) {
                        comparisonDiv.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="material-icons align-middle me-2">error</i>
                                Error loading Firebase data: ${error.message}<br>
                                <small>Check browser console for details.</small>
                            </div>`;
                        comparisonDiv.classList.remove('d-none');
                    }
                }
            }

            // Fetch user data from MySQL if email provided
            if (emailParam && inputE && inputF && inputL && inputO && inputR && inputStatus) {
                console.log('📧 Fetching MySQL user data for email:', emailParam);
                updateDataSourceIndicator('loading', 'Loading MySQL data...');
                
                fetch('get_user_by_email.php?email=' + encodeURIComponent(emailParam))
                    .then(r => {
                        console.log('Response status:', r.status);
                        if (!r.ok) {
                            throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                        }
                        return r.json();
                    })
                    .then(data => {
                        console.log('📊 MySQL response:', data);
                        
                        if (data && data.success && data.found && data.user) {
                            const u = data.user;
                            console.log('✅ MySQL user FOUND:', u);
                            updateDataSourceIndicator('mysql', 'MySQL data loaded');
                            
                            // Debug: Show all fields from MySQL
                            console.log('🔍 MySQL Fields:', {
                                id: u.id,
                                User_id: u.User_id,
                                user_id: u.user_id,
                                first_name: u.first_name,
                                last_name: u.last_name,
                                email: u.email,
                                organization: u.organization,
                                role: u.role,
                                status: u.status,
                                username: u.username
                            });
                            
                            // Populate form with MySQL data
                            if (inputId) {
                                // Try different ID field names
                                const userId = u.User_id || u.id || u.user_id || uidParam;
                                inputId.value = userId;
                                console.log('🆔 Set User ID to:', inputId.value);
                            }
                            if (inputF) {
                                inputF.value = u.first_name || '';
                                console.log('👤 Set First Name to:', inputF.value);
                            }
                            if (inputL) {
                                inputL.value = u.last_name || '';
                                console.log('👤 Set Last Name to:', inputL.value);
                            }
                            if (inputE) {
                                inputE.value = u.email || decodeURIComponent(emailParam) || '';
                                console.log('📧 Set Email to:', inputE.value);
                            }
                            if (inputO) {
                                inputO.value = u.organization || '';
                                console.log('🏢 Set Organization to:', inputO.value);
                            }
                            if (inputR) {
                                inputR.value = u.role || '';
                                console.log('👑 Set Role to:', inputR.value);
                            }
                            if (inputStatus) {
                                // Capitalize first letter for dropdown
                                const status = u.status || 'Pending';
                                inputStatus.value = status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
                                console.log('📊 Set Status to:', inputStatus.value);
                            }
                            if (inputUsername) {
                                inputUsername.value = u.username || '';
                                console.log('👤 Set Username to:', inputUsername.value);
                            }
                            
                            console.log('✅✅ Form SUCCESSFULLY populated with MySQL data');
                            
                            // Load Firebase data for comparison
                            setTimeout(loadFirebaseData, 500);
                            
                        } else if (data && !data.found) {
                            console.warn('⚠️ User NOT FOUND in MySQL:', data.message);
                            updateDataSourceIndicator('firebase', 'Firebase only (MySQL not found)');
                            
                            // Show warning in comparison div
                            if (comparisonDiv) {
                                comparisonDiv.innerHTML = `
                                    <div class="alert alert-warning">
                                        <i class="material-icons align-middle me-2">warning</i>
                                        User not found in MySQL database.<br>
                                        <small>Email: ${emailParam}</small><br>
                                        <small>This user exists only in Firebase. You can create it in MySQL by saving this form.</small>
                                    </div>`;
                                comparisonDiv.classList.remove('d-none');
                            }
                            
                            // Keep the email in form but show it's from Firebase
                            if (inputE) {
                                inputE.value = decodeURIComponent(emailParam);
                                console.log('📧 Set Email from URL param:', inputE.value);
                            }
                            if (inputId && uidParam) {
                                inputId.value = uidParam;
                                console.log('🆔 Set User ID from URL param:', inputId.value);
                            }
                            
                            // Load Firebase data (user exists in Firebase)
                            setTimeout(loadFirebaseData, 500);
                            
                        } else {
                            console.warn('⚠️ Invalid response format from get_user_by_email.php', data);
                            updateDataSourceIndicator('error', 'MySQL error');
                            // Load Firebase data anyway
                            setTimeout(loadFirebaseData, 500);
                        }
                    })
                    .catch(err => {
                        console.error('❌ MySQL fetch error:', err);
                        updateDataSourceIndicator('error', 'MySQL error');
                        
                        // Show error in comparison div
                        if (comparisonDiv) {
                            comparisonDiv.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="material-icons align-middle me-2">error</i>
                                    Error loading MySQL data: ${err.message}<br>
                                    <small>Check browser console for details.</small>
                                </div>`;
                            comparisonDiv.classList.remove('d-none');
                        }
                        // Still try to load Firebase data
                        setTimeout(loadFirebaseData, 500);
                    });
            } else {
                console.log('ℹ️ No email parameter provided, loading Firebase data only');
                updateDataSourceIndicator('loading', 'Loading Firebase data...');
                // Load Firebase data directly
                setTimeout(loadFirebaseData, 500);
            }

            // Form submission handler
            form.addEventListener('submit', function(e){
                e.preventDefault();
                console.log('=== Form submit event triggered ===');
                
                // Get current values
                const email = inputE ? inputE.value.trim() : '';
                const role = inputR ? inputR.value : '';
                const uid = inputId ? inputId.value : '';
                
                console.log('Validation check - Email:', email, 'Role:', role, 'UID:', uid);

                // Validate required fields
                if (!email) {
                    alert('Email Address is required');
                    if (inputE) inputE.focus();
                    return;
                }
                
                if (!role) {
                    alert('Role is required');
                    if (inputR) inputR.focus();
                    return;
                }

                // Validate email format
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    alert('Please enter a valid email address');
                    if (inputE) inputE.focus();
                    return;
                }

                // Prepare FormData
                const fd = new FormData(form);
                
                // Add Firebase sync flag
                fd.append('sync_firebase', 'true');
                
                // Debug: Show what's being sent
                console.log('FormData contents:');
                for (let pair of fd.entries()) {
                    console.log('  ' + pair[0] + ':', pair[1]);
                }

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="material-icons align-middle me-1">hourglass_empty</i> Saving...';
                submitBtn.disabled = true;
                
                if (syncBtn) {
                    syncBtn.disabled = true;
                }
                if (fillBtn) {
                    fillBtn.disabled = true;
                }

                // Send request
                console.log('Sending request to update_user.php...');
                fetch('update_user.php', {
                    method: 'POST',
                    body: fd
                })
                .then(response => {
                    console.log('Response status:', response.status, response.statusText);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response:', text);
                    
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed response:', data);
                        
                        if (data.success) {
                            console.log('✅ SUCCESS:', data.message);
                            
                            let successMsg = '✅ User updated successfully!';
                            if (data.firestore_updated) {
                                successMsg += ' (Firebase synced)';
                            } else if (data.firestore_error) {
                                successMsg += ' (MySQL updated, Firebase sync failed: ' + data.firestore_error + ')';
                            }
                            
                            alert(successMsg);
                            
                            // Redirect after 1 second
                            setTimeout(() => {
                                window.location.href = 'user_management.php';
                            }, 1000);
                        } else {
                            console.log('❌ ERROR:', data.message);
                            
                            // Provide more helpful error messages
                            let errorMsg = data.message || 'Unknown error';
                            if (errorMsg.includes('SQLSTATE') || errorMsg.includes('Column not found')) {
                                errorMsg = 'Database error: ' + errorMsg + '\n\nPlease check your database structure.';
                            }
                            
                            alert('❌ Update failed: ' + errorMsg);
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                            if (syncBtn) syncBtn.disabled = false;
                            if (fillBtn) fillBtn.disabled = false;
                        }
                    } catch (parseError) {
                        console.error('❌ JSON parse error:', parseError);
                        console.error('Raw text:', text);
                        
                        // Check if it's an HTML error page
                        if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                            alert('⚠️ Server returned an HTML error page. Check server logs.');
                        } else {
                            alert('⚠️ Invalid server response. Check console for details.');
                        }
                        
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        if (syncBtn) syncBtn.disabled = false;
                        if (fillBtn) fillBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('❌ Fetch error:', error);
                    alert('❌ Network error: ' + error.message);
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    if (syncBtn) syncBtn.disabled = false;
                    if (fillBtn) fillBtn.disabled = false;
                });
            });
            
            // Make functions globally available
            window.copyToForm = copyToForm;
            window.fillAllFromFirebase = fillAllFromFirebase;
            window.syncToFirebase = syncToFirebase;
            
            console.log('✅ Form event listener attached successfully');
        })();
    </script>
</body>
</html>