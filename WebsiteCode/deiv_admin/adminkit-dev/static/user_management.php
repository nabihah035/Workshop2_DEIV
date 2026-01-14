<?php
session_start();

// Check if user is logged in and is an admin
// (Uncomment this when you are ready to secure the page)
/*
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: index.php"); 
    exit;
}
*/

// Database connection for MySQL (for audit trail)
require_once dirname(dirname(__DIR__)) . '/config/db.php';

// Initialize filter variables
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$users = []; 

// Function to log verification to audit trail
function logVerificationToAudit($user_id, $case_id = null, $description = null) {
    global $conn;
    
    try {
        $admin_id = $_SESSION['User_id'] ?? 1; // Default to admin ID 1 if not logged in
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $action = 'Verify';
        
        $stmt = $conn->prepare("INSERT INTO audit_trail (action, User_id, Case_id, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siis", $action, $admin_id, $case_id, $ip_address);
        
        if ($stmt->execute()) {
            return true;
        } else {
            error_log("Audit trail logging failed: " . $stmt->error);
            return false;
        }
    } catch (Exception $e) {
        error_log("Audit trail error: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="DEIV - Digital Evidence Integrity Verification System">
    <meta name="author" content="DEIV">
    <meta name="keywords" content="digital evidence, forensic, investigation, security">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" href="img/icons/icon-48x48.png" />
    <link rel="canonical" href="https://demo-basic.adminkit.io/" />

    <title>User Management | DEIV Admin</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-firestore-compat.js"></script>

    <style>
        .badge-status {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize; 
        }
        .badge-active { background-color: #d1fae5; color: #065f46; }
        .badge-inactive { background-color: #f3f4f6; color: #374151; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-rejected { background-color: #fee2e2; color: #991b1b; }
        .badge-verified { background-color: #dbeafe; color: #1e40af; }

       .filter-section {
    background: #f8f9fa;
    border-radius: 0.375rem;
    border: 1px solid #dee2e6;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 500;
    color: #495057;
}

.input-group-sm {
    border-radius: 0.375rem;
}

.input-group-sm .input-group-text {
    border-radius: 0.375rem 0 0 0.375rem;
    border: 1px solid #ced4da;
}

.input-group-sm .form-control {
    border-radius: 0 0.375rem 0.375rem 0;
    border: 1px solid #ced4da;
    border-left: none;
}

.form-select-sm {
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
}

.form-control-sm {
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.375rem;
}
        /* Loading Row Style */
        .loading-row { text-align: center; padding: 20px; color: #666; font-style: italic; }
        
        button { cursor: pointer; }

        /* Action Buttons Spacing & Layout */
        .action-group .btn { 
            margin-right: 5px; 
            margin-bottom: 5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-group .material-icons {
            font-size: 1.1rem;
        }

        
        
        .search-highlight {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 500;
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
                    <li class="sidebar-header">Navigation</li>
                    <li class="sidebar-item"><a class="sidebar-link" href="index.php"><i class="align-middle material-icons">home</i> <span class="align-middle">Dashboard</span></a></li>
                    <li class="sidebar-item active"><a class="sidebar-link" href="user_management.php"><i class="align-middle material-icons">people</i> <span class="align-middle">User Management</span></a></li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="case_list.php">
                            <i class="align-middle material-icons">folder</i>
                            <span class="align-middle">Case Files</span>
                        </a>
                    </li>
                    <li class="sidebar-item"><a class="sidebar-link" href="evidence_list.php"><i class="align-middle material-icons">inventory_2</i> <span class="align-middle">Evidence Records</span></a></li>
                    <li class="sidebar-item"><a class="sidebar-link" href="metadata_list.php"><i class="align-middle material-icons">list_alt</i> <span class="align-middle">Evidence Metadata</span></a></li>
                    <li class="sidebar-item"><a class="sidebar-link" href="reportlist.php"><i class="align-middle material-icons">folder</i> <span class="align-middle">Report Management</span></a></li>
                    <li class="sidebar-item"><a class="sidebar-link" href="audit_logs.php"><i class="align-middle material-icons">history</i> <span class="align-middle">Audit Logs</span></a></li>
                    <li class="sidebar-item"><a class="sidebar-link" href="logout.php"><i class="align-middle material-icons">logout</i> <span class="align-middle text-danger">Logout</span></a></li>
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
                           </li>
                    </ul>
                </div>
            </nav>

            <main class="content">
                <h1 class="h3 mb-3"><strong>User</strong> Management</h1>
                <div class="container-fluid p-0">
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="material-icons align-middle">check_circle</i>
                            <?= $_SESSION['success'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="material-icons align-middle">error</i>
                            <?= $_SESSION['error'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                   <!-- Replace the existing filter section with this updated version -->
<!-- Replace the existing filter section with this updated version -->
<div class="filter-section">
    <form method="GET" class="row g-3 align-items-end">
        <!-- Search -->
        <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0"><i class="material-icons" style="font-size: 18px;">search</i></span>
                <input type="text" name="search" placeholder="Search name or email..." class="form-control form-control-sm border-start-0" value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        
        <!-- Status -->
        <div class="col-md-2">
            <label class="form-label mb-1 small text-muted">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="rejected" <?= $status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        
        <!-- Role/Type -->
        <div class="col-md-2">
            <label class="form-label mb-1 small text-muted">Role</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">All Roles</option>
                <option value="Admin" <?= $type == 'Admin' ? 'selected' : '' ?>>Admin</option>
                <option value="Law agencies" <?= $type == 'Law agencies' ? 'selected' : '' ?>>Law Agencies</option>
                <option value="Digital Forensic Investigator" <?= $type == 'Digital Forensic Investigator' ? 'selected' : '' ?>>Digital Forensic Investigator</option>
                <option value="Legal Professionals" <?= $type == 'Legal Professionals' ? 'selected' : '' ?>>Legal Professionals</option>
                <option value="Institution" <?= $type == 'Institution' ? 'selected' : '' ?>>Institution</option>
            </select>
        </div>
        
        <!-- From Date -->
        <div class="col-md-2">
            <label class="form-label mb-1 small text-muted">From Date</label>
            <input type="text" name="date_from" placeholder="dd/mm/yyyy" class="form-control form-control-sm datepicker" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        
        <!-- To Date -->
        <div class="col-md-2">
            <label class="form-label mb-1 small text-muted">To Date</label>
            <input type="text" name="date_to" placeholder="dd/mm/yyyy" class="form-control form-control-sm datepicker" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        
        <!-- Filter Button -->
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">
                Filter
            </button>
        </div>
    </form>
    
    <!-- Results Count -->
   
</div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Users List</h5>
                                    <div class="card-actions">
                                        <a href="user_add.php" class="btn btn-primary">
                                            <i class="material-icons align-middle">person_add</i> Add User
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="text-muted" id="user-count">Loading users...</div>
                                        <!-- Around line 151, update the legend: -->
<div class="text-muted">
    <small>Legend: 
        <span class="badge-status badge-pending me-1">Pending</span>
        <span class="badge-status badge-active me-1">Active</span>
        <span class="badge-status badge-inactive me-1">Inactive</span>
        <span class="badge-status badge-rejected">Rejected</span>
        <!-- Removed verified badge -->
    </small>
</div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Name</th>
                                                    <th>Role</th>
                                                    <th>Status</th>
                                                    <th>Organization</th>
                                                    <th style="min-width: 280px;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="user-table-body">
                                                <tr>
                                                    <td colspan="8" class="loading-row">
                                                        <div class="spinner-border text-primary me-2" role="status" style="width: 1rem; height: 1rem;"></div>
                                                        Connecting to Firebase...
                                                    </td>
                                                </tr>
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

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-user-form">
                        <input type="hidden" id="edit-user-id" name="id">

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <input id="edit-first-name" name="first_name" class="form-control form-control-lg" placeholder="First Name">
                            </div>
                            <div class="col-md-6">
                                <input id="edit-last-name" name="last_name" class="form-control form-control-lg" placeholder="Last Name">
                            </div>
                        </div>

                        <div class="mb-3">
                            <input id="edit-username" name="username" class="form-control form-control-lg" placeholder="Username">
                        </div>

                        <div class="mb-3">
                            <input id="edit-email" name="email" type="email" class="form-control form-control-lg" placeholder="Email Address">
                        </div>

                        <div class="mb-3">
                            <input id="edit-organization" name="organization" class="form-control form-control-lg" placeholder="Organization (e.g., UTeM)">
                        </div>

                        <div class="mb-3">
                            <input id="edit-password" name="password" type="password" class="form-control form-control-lg" placeholder="Password (leave blank to keep)">
                        </div>

                        <div class="mb-3">
                            <select id="edit-role" name="role" class="form-select form-select-lg">
                                <option value="">-- Select Role --</option>
                                <option value="Law agencies">Law agencies</option>
                                <option value="Digital Forensic Investigator">Digital Forensic Investigator</option>
                                <option value="Legal Professionals">Legal Professionals</option>
                                <option value="Institution">Institution</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

           <script>
    // --- FIREBASE CONFIGURATION ---
    const firebaseConfig = {
        apiKey: "AIzaSyDpjUo6GfJEnxTwKBjnfzpkQruSzWvov-I",
        authDomain: "deiv-ac114.firebaseapp.com",
        projectId: "deiv-ac114",
        storageBucket: "deiv-ac114.firebasestorage.app",
        messagingSenderId: "846119732034",
        appId: "1:846119732034:web:213dfed9358f6d38773edb",
        measurementId: "G-V591C9RQJ4"
    };

    if (!firebase.apps.length) {
        firebase.initializeApp(firebaseConfig);
    }
    const db = firebase.firestore();
    const tableBody = document.getElementById("user-table-body");
    const countDiv = document.getElementById("user-count");
    
    // Store all users for filtering
    let allUsers = [];
    let filteredUsers = [];
    let totalRecords = 0; // To track total records before deduplication

    // --- REAL-TIME LISTENER ---
    db.collection("users").onSnapshot((snapshot) => {
        totalRecords = snapshot.size; // Store total count
        allUsers = [];
        const uniqueUsers = new Map(); // Use Map to store unique users by email
        
        snapshot.forEach((doc) => {
            const user = doc.data();
            user.id = doc.id;
            
            // Check if this user has an email
            if (user.email) {
                const email = user.email.toLowerCase().trim();
                
                // If we haven't seen this email before, add it
                if (!uniqueUsers.has(email)) {
                    uniqueUsers.set(email, user);
                }
                // If duplicate exists, keep the first one (simplest approach)
                // You can add logic here to decide which duplicate to keep
            } else {
                // If user has no email, add them with their ID as key
                uniqueUsers.set(user.id, user);
            }
        });
        
        // Convert Map values back to array
        allUsers = Array.from(uniqueUsers.values());
        console.log(`Loaded ${allUsers.length} unique users from ${totalRecords} total records`);
        applyFilters();
    }, (error) => {
        console.error("Firebase error:", error);
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-danger py-4">
                    <i class="material-icons align-middle me-2">error</i>
                    Error connecting to Firebase. Please check your connection.
                </td>
            </tr>
        `;
    });

    // --- FILTER FUNCTIONS ---
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            search: params.get('search') || '',
            status: params.get('status') || '',
            type: params.get('type') || '',
            date_from: params.get('date_from') || '',
            date_to: params.get('date_to') || ''
        };
    }

    function applyFilters() {
        const filters = getUrlParams();
        
        filteredUsers = allUsers.filter(user => {
            let match = true;
            
            // Search filter
            if (filters.search) {
                const searchLower = filters.search.toLowerCase();
                const searchFields = [
                    user.username || '',
                    user.email || '',
                    (user.first_name || '') + ' ' + (user.last_name || ''),
                    user.organization || ''
                ].join(' ').toLowerCase();
                
                if (!searchFields.includes(searchLower)) {
                    match = false;
                }
            }
            
            // Status filter
            if (filters.status) {
                const userStatus = (user.status || 'inactive').toLowerCase();
                const filterStatus = filters.status.toLowerCase();
                if (userStatus !== filterStatus) {
                    match = false;
                }
            }
            
            // Type filter (role)
            if (filters.type) {
                const userRole = (user.role || '').trim();
                const filterType = filters.type.trim();
                if (userRole !== filterType) {
                    match = false;
                }
            }
            
            // Date range filter (if created_at exists)
            if ((filters.date_from || filters.date_to) && user.created_at) {
                const userDate = new Date(user.created_at);
                
                if (filters.date_from) {
                    const fromDate = new Date(filters.date_from);
                    fromDate.setHours(0, 0, 0, 0);
                    if (userDate < fromDate) {
                        match = false;
                    }
                }
                
                if (filters.date_to) {
                    const toDate = new Date(filters.date_to);
                    toDate.setHours(23, 59, 59, 999);
                    if (userDate > toDate) {
                        match = false;
                    }
                }
            }
            
            return match;
        });
        
        renderUsers(filteredUsers);
    }

    function renderUsers(users) {
        tableBody.innerHTML = "";
        
        if(countDiv) {
            countDiv.innerHTML = `${users.length} unique user${users.length !== 1 ? 's' : ''} found`;
            if (totalRecords > 0) {
                countDiv.innerHTML += ` (deduplicated from ${totalRecords} total records)`;
            }
        }
        
        if (users.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        ${allUsers.length === 0 ? 'No users found in database' : 'No users matching the current filters'}
                    </td>
                </tr>
            `;
            return;
        }

        users.forEach((user) => {
            const uid = user.id;
            
            // Prepare Data
            const displayID = uid.substring(0, 8) + (uid.length > 8 ? '...' : ''); 
            const username = user.username || "N/A";
            const email = user.email || "No Email";
            
            // Name Logic
            const fName = user.first_name || user.firstName || user.fName || "";
            const lName = user.last_name || user.lastName || user.lName || "";
            let fullName = "Unknown";
            if(fName || lName) {
                fullName = (fName + " " + lName).trim();
            } else if(user.username) {
                fullName = user.username;
            }

            const role = user.role || "User"; 
            const org = user.organization || "-";
            const token = user.fcmToken || "";
            const status = user.status ? user.status.toLowerCase() : "pending";
            
            // Create Row
            const tr = document.createElement("tr");

            // Badge Logic
            let badgeClass = "badge-inactive";
            let displayStatus = "Inactive";
            
            const userStatus = (user.status || "inactive").toLowerCase();
            
            if (userStatus === 'active' || userStatus === 'verified') { 
                badgeClass = "badge-active"; 
                displayStatus = "Active";
            }
            else if (userStatus === 'pending') { 
                badgeClass = "badge-pending"; 
                displayStatus = "Pending"; 
            }
            else if (userStatus === 'rejected') { 
                badgeClass = "badge-rejected"; 
                displayStatus = "Rejected"; 
            }

            // Highlight search terms
            const filters = getUrlParams();
            const searchTerm = filters.search.toLowerCase();
            
            function highlightText(text) {
                if (!searchTerm || !text) return text;
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                return text.replace(regex, '<mark class="search-highlight">$1</mark>');
            }

            // --- ACTION BUTTONS LOGIC ---
            const escEmail = (email + '').replace(/'/g, "\\'");
            const escToken = (token + '').replace(/'/g, "\\'");
            const qEmail = encodeURIComponent(email + '');
            let actionButtons = `<div class="action-group">`;

            // 1. Reject Button (Only for Pending users)
            if (status === 'pending') {
                actionButtons += `
                    <button onclick="rejectUser('${uid}', '${escEmail}', '${escToken}', event)" class="btn btn-danger btn-sm" title="Reject User Registration">
                        <i class="material-icons">block</i> Reject
                    </button>
                `;
            }

            // 2. Approve Button (Only for Pending/Inactive/Rejected users)
            if (status === 'inactive' || status === 'pending' || status === 'rejected') {
                actionButtons += `
                    <button onclick="approveUser('${uid}', '${escEmail}', '${escToken}', event)" class="btn btn-success btn-sm" title="Approve & Verify User">
                        <i class="material-icons">check_circle</i> Approve
                    </button>
                `;
            }

            // 3. Edit Button (for all users)
            actionButtons += `
                <button onclick="window.location.href='user_edit_page.php?uid=${uid}&email=${qEmail}'" class="btn btn-warning btn-sm" title="Edit User">
                    <i class="material-icons">edit</i> Edit
                </button>
            `;

            // 4. Delete Button (Only for Active/Approved users)
            if (status === 'active' || userStatus === 'active') {
                actionButtons += `
                    <button onclick="deleteUser('${uid}', '${escEmail}', event)" class="btn btn-outline-danger btn-sm" title="Permanently Delete User">
                        <i class="material-icons">delete_forever</i> Delete
                    </button>
                `;
            }

            actionButtons += `</div>`;

            // Insert HTML with search highlighting
            tr.innerHTML = `
                <td><strong title="Firebase ID: ${uid}">${displayID}</strong></td>
                <td>${highlightText(username)}</td>
                <td>${highlightText(email)}</td>
                <td>${highlightText(fullName)}</td>
                <td>${highlightText(role)}</td>
                <td><span class="badge-status ${badgeClass}">${displayStatus}</span></td>
                <td>${highlightText(org)}</td>
                <td>${actionButtons}</td>
            `;
            
            tableBody.appendChild(tr);
        });
    }

    // --- APPROVE USER FUNCTION ---
    async function approveUser(uid, email, token, event) {
        if(!confirm("Are you sure you want to approve " + email + "?")) return;

        try {
            const approveBtn = event.target.closest('button');
            const originalText = approveBtn.innerHTML;
            
            // Update button to show processing
            approveBtn.innerHTML = '<i class="material-icons">hourglass_empty</i> Approving...';
            approveBtn.disabled = true;
            
            // 1. Update Firebase to 'active' status
            await db.collection("users").doc(uid).update({
                status: "active",
                approved_at: firebase.firestore.FieldValue.serverTimestamp(),
                verified_at: firebase.firestore.FieldValue.serverTimestamp()
            });

            // 2. Update MySQL status to 'Active'
            try {
                const formData = new FormData();
                formData.append("user_id", uid);
                formData.append("email", email);
                formData.append("status", "Active");
                
                await fetch("update_user_status.php", {
                    method: "POST",
                    body: formData
                });
            } catch (mysqlError) {
                // Silently handle MySQL errors
            }

            // 3. LOG TO AUDIT TRAIL
            try {
                const auditFormData = new FormData();
                auditFormData.append("user_id", uid);
                auditFormData.append("email", email);
                auditFormData.append("action", "Verify");
                auditFormData.append("admin_id", "<?= $_SESSION['User_id'] ?? 1 ?>");
                
                await fetch("log_audit.php", {
                    method: "POST",
                    body: auditFormData
                });
            } catch (auditError) {
                // Silently handle audit errors
            }

            // Show success message
            alert(`✅ User ${email} has been APPROVED`);
            
            // Reset button
            approveBtn.innerHTML = originalText;
            approveBtn.disabled = false;
            
        } catch (error) {
            // Reset button
            const approveBtn = event.target.closest('button');
            approveBtn.innerHTML = '<i class="material-icons">check_circle</i> Approve';
            approveBtn.disabled = false;
            
            alert("❌ Error approving user");
        }
    }

    // --- REJECT USER FUNCTION ---
    async function rejectUser(uid, email, token, event) {
        if(!confirm(`Are you sure you want to REJECT ${email}'s registration?`)) return;

        try {
            const rejectBtn = event.target.closest('button');
            const originalText = rejectBtn.innerHTML;
            
            // Update button to show processing
            rejectBtn.innerHTML = '<i class="material-icons">hourglass_empty</i> Rejecting...';
            rejectBtn.disabled = true;
            
            // 1. Update Firebase to 'rejected' status
            await db.collection("users").doc(uid).update({
                status: "rejected",
                rejected_at: firebase.firestore.FieldValue.serverTimestamp()
            });

            // 2. Update MySQL status to 'Rejected'
            try {
                const formData = new FormData();
                formData.append("user_id", uid);
                formData.append("email", email);
                formData.append("status", "Rejected");
                
                await fetch("update_user_status.php", {
                    method: "POST",
                    body: formData
                });
            } catch (mysqlError) {
                // Silently handle MySQL errors
            }

            // 3. LOG TO AUDIT TRAIL
            try {
                const auditFormData = new FormData();
                auditFormData.append("user_id", uid);
                auditFormData.append("email", email);
                auditFormData.append("action", "Reject");
                auditFormData.append("admin_id", "<?= $_SESSION['User_id'] ?? 1 ?>");
                
                await fetch("log_audit.php", {
                    method: "POST",
                    body: auditFormData
                });
            } catch (auditError) {
                // Silently handle audit errors
            }

            // Show success message
            alert(`✅ User ${email} has been REJECTED`);
            
            // Reset button
            rejectBtn.innerHTML = originalText;
            rejectBtn.disabled = false;
            
        } catch (error) {
            // Reset button
            const rejectBtn = event.target.closest('button');
            rejectBtn.innerHTML = '<i class="material-icons">block</i> Reject';
            rejectBtn.disabled = false;
            
            alert("❌ Error rejecting user");
        }
    }

    // --- DELETE USER FUNCTION ---
    async function deleteUser(uid, email, event) {
        if(!confirm(`Are you sure you want to delete ${email}? This action cannot be undone!`)) return;

        try {
            const deleteBtn = event.target.closest('button');
            const originalText = deleteBtn.innerHTML;
            const originalClass = deleteBtn.className;
            
            // Update button to show deleting state
            deleteBtn.innerHTML = '<i class="material-icons">hourglass_empty</i> Deleting...';
            deleteBtn.disabled = true;
            deleteBtn.className = 'btn btn-secondary btn-sm';
            
            let results = {
                firebase: { success: false, message: '' },
                mysql: { success: false, message: '' },
                audit: { success: false, message: '' }
            };
            
            // 1. Delete from Firebase
            try {
                await db.collection("users").doc(uid).delete();
                results.firebase.success = true;
                results.firebase.message = '✅ Firebase: Deleted successfully';
            } catch (firebaseError) {
                if (firebaseError.code === 'not-found') {
                    results.firebase.message = '⚠️ Firebase: User not found';
                } else {
                    results.firebase.message = '❌ Firebase: Deletion failed';
                }
            }
            
            // 2. Delete from MySQL
            try {
                const response = await fetch('delete_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `user_id=${uid}&email=${encodeURIComponent(email)}`
                });
                
                const result = await response.text();
                
                if (result.includes("success") || result.includes("deleted")) {
                    results.mysql.success = true;
                    results.mysql.message = '✅ MySQL: Deleted successfully';
                } else if (result.includes("not_found")) {
                    results.mysql.message = '⚠️ MySQL: User not found in database';
                } else {
                    results.mysql.message = '⚠️ MySQL: Deletion failed';
                }
            } catch (mysqlError) {
                results.mysql.message = '❌ MySQL: Connection error';
            }
            
            // 3. LOG TO AUDIT TRAIL
            try {
                const auditFormData = new FormData();
                auditFormData.append("user_id", uid);
                auditFormData.append("email", email);
                auditFormData.append("action", "Delete");
                auditFormData.append("admin_id", "<?= $_SESSION['User_id'] ?? 1 ?>");
                
                const auditResponse = await fetch("log_audit.php", {
                    method: "POST",
                    body: auditFormData
                });
                
                const auditResult = await auditResponse.json();
                if (auditResult.success) {
                    results.audit.success = true;
                    results.audit.message = '✅ Audit Trail: Deletion logged';
                } else {
                    results.audit.message = '⚠️ Audit Trail: Logging failed';
                }
            } catch (auditError) {
                results.audit.message = '';
            }
            
            // Show final result
            let finalMessage = "DELETE RESULTS\n\n";
            finalMessage += results.firebase.message + "\n";
            finalMessage += results.mysql.message + "\n";
            finalMessage += results.audit.message + "\n\n";
            
            // Summary
            const allSuccess = results.firebase.success && results.mysql.success && results.audit.success;
            const anySuccess = results.firebase.success || results.mysql.success;
            
            if (allSuccess) {
                finalMessage += "✅ COMPLETE: User deleted from all systems";
            } else if (anySuccess) {
                finalMessage += "";
            } else {
                finalMessage += "";
            }
            
            alert(finalMessage);
            
        } catch (error) {
            // Reset button
            const deleteBtn = event.target.closest('button');
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
            deleteBtn.className = originalClass;
            
            alert("❌ DELETE PROCESS FAILED\n\nPlease try again.");
        }
    }

    // Initialize flatpickr for date inputs
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr('.datepicker', {
            dateFormat: 'd/m/Y',
            allowInput: true,
            locale: {
                firstDayOfWeek: 1
            }
        });
        
        // Apply filters on page load
        applyFilters();
    });
    </script>
</body>
</html>