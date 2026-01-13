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

// Database connection
// We keep this because your other admin pages (like Evidence List) might need it.
require_once __DIR__ . '/../../admin/config/db.php';

// Initialize filter variables (Kept for UI consistency)
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$users = []; 
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

        .filter-section {
            background: #fff;
            border-radius: 0.375rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        /* Loading Row Style */
        .loading-row { text-align: center; padding: 20px; color: #666; font-style: italic; }
        
        button { cursor: pointer; }

        /* Action Buttons Spacing & Layout */
        .action-group .btn { 
            margin-right: 5px; 
            margin-bottom: 5px; /* Adds space if buttons wrap */
            display: inline-flex;
            align-items: center;
            gap: 5px; /* Space between icon and text */
        }
        
        /* Ensure icons align nicely with text */
        .action-group .material-icons {
            font-size: 1.1rem;
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
                    <li class="sidebar-item"><a class="sidebar-link" href="evidence_list.php"><i class="align-middle material-icons">inventory_2</i> <span class="align-middle">Evidence Records</span></a></li>
                    <li class="sidebar-item"><a class="sidebar-link" href="metadata_list.php"><i class="align-middle material-icons">list_alt</i> <span class="align-middle">Evidence Metadata</span></a></li>
                    <li class="sidebar-item"><a class="sidebar-link" href="case_list.php"><i class="align-middle material-icons">folder</i> <span class="align-middle">Case Files</span></a></li>
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

                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="material-icons">search</i></span>
                                    <input type="text" name="search" placeholder="Search name or email..." class="form-control">
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="All Statuses">All Statuses</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="All Types">All Types</option>
                                    <option value="Law agencies">Law agencies</option>
                                    <option value="Institution">Institution</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Date Range</label>
                                <div class="row g-2">
                                    <div class="col-6"><input type="text" name="date_from" placeholder="From" class="form-control datepicker"></div>
                                    <div class="col-6"><input type="text" name="date_to" placeholder="To" class="form-control datepicker"></div>
                                </div>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="d-flex gap-2 w-100">
                                    <button type="button" class="btn btn-primary btn-icon w-100" onclick="location.reload()">
                                        <i class="material-icons">filter_alt</i> Apply
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Users List (Firebase Realtime)</h5>
                                    <div class="card-actions">
                                        <a href="user_add.php" class="btn btn-primary">
                                            <i class="material-icons align-middle">person_add</i> Add User
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="text-muted" id="user-count">Loading users...</div>
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

    <script src="js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
    // Initialize date pickers and UI interactions
    document.addEventListener('DOMContentLoaded', function () {
        flatpickr(".datepicker", { dateFormat: "Y-m-d", allowInput: true });

        // Hamburger menu toggle logic
        const sidebarToggle = document.querySelector('.js-sidebar-toggle');
        const sidebar = document.querySelector('#sidebar');
        const main = document.querySelector('.main');
        const hamburger = document.querySelector('.hamburger');

        if (sidebarToggle && sidebar && hamburger) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('collapsed');
                main.classList.toggle('sidebar-hidden');
                hamburger.classList.toggle('active');
                if (window.innerWidth <= 991.98) {
                    if (sidebar.classList.contains('collapsed')) { removeBackdrop(); } else { addBackdrop(); }
                }
            });
        }

        function addBackdrop() {
            const backdrop = document.createElement('div');
            backdrop.className = 'sidebar-backdrop';
            backdrop.style.cssText = `position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1029; display: block;`;
            backdrop.addEventListener('click', function() {
                sidebar.classList.add('collapsed');
                main.classList.add('sidebar-hidden');
                hamburger.classList.remove('active');
                removeBackdrop();
            });
            document.body.appendChild(backdrop);
            document.body.style.overflow = 'hidden';
        }

        function removeBackdrop() {
            const backdrop = document.querySelector('.sidebar-backdrop');
            if (backdrop) backdrop.remove();
            document.body.style.overflow = '';
        }

        function handleResize() {
            if (window.innerWidth <= 991.98) {
                sidebar.classList.add('collapsed');
                main.classList.add('sidebar-hidden');
                hamburger.classList.remove('active');
                removeBackdrop();
            } else {
                sidebar.classList.remove('collapsed');
                main.classList.remove('sidebar-hidden');
                hamburger.classList.remove('active');
                removeBackdrop();
            }
        }
        handleResize();
        window.addEventListener('resize', handleResize);
        
        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991.98) {
                    sidebar.classList.add('collapsed');
                    main.classList.add('sidebar-hidden');
                    hamburger.classList.remove('active');
                    removeBackdrop();
                }
            });
        });
    });
    </script>
        <!-- Edit User Modal -->
        <style>
                /* Modal styling for a modern 'card' edit UI */
                #editUserModal .modal-content { border-radius: 12px; overflow: hidden; }
                #editUserModal .modal-body { background: #f8fafc; padding: 1.5rem; }
                #editUserModal .form-control, #editUserModal .form-select {
                        border-radius: 12px;
                        background: #f0f4f8;
                        border: 1px solid rgba(0,0,0,0.06);
                        height: 48px;
                        padding: .75rem 1rem;
                        box-shadow: none;
                }
                #editUserModal .form-control:focus, #editUserModal .form-select:focus { box-shadow: none; border-color: rgba(58,110,255,0.25); }
                #editUserModal .modal-header { background: transparent; border-bottom: 0; padding: .75rem 1.5rem; }
                #editUserModal .modal-title { font-weight: 600; }
                #editUserModal .btn-primary { background: #0b63d8; border-color: #0b63d8; padding: .6rem 1.1rem; border-radius: 10px; }
                @media (min-width: 768px) {
                        #editUserModal .modal-dialog { max-width: 720px; }
                }
        </style>

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

    <script>
        // --- CONFIGURATION ---
        const firebaseConfig = {
            apiKey: "AIzaSyDpJu...", // ⚠️ IMPORTANT: PASTE YOUR FULL KEY HERE
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

        console.log("Listening for Firebase updates...");

        // --- REAL-TIME LISTENER ---
        db.collection("users").onSnapshot((snapshot) => {
            console.log("Database updated! Found " + snapshot.size + " users.");
            
            if(countDiv) countDiv.innerHTML = snapshot.size + " user(s) found";
            tableBody.innerHTML = ""; 

            if (snapshot.empty) {
                tableBody.innerHTML = "<tr><td colspan='8' class='text-center'>No users found in Firebase.</td></tr>";
                return;
            }

            snapshot.forEach((doc) => {
                const user = doc.data();
                const uid = doc.id;

                // Prepare Data
                const displayID = uid.substring(0, 8); 
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
                
                if(status === 'active') { badgeClass = "badge-active"; displayStatus = "Active"; }
                if(status === 'pending') { badgeClass = "badge-pending"; displayStatus = "Pending"; }
                if(status === 'rejected') { badgeClass = "badge-rejected"; displayStatus = "Rejected"; }

                // --- ACTION BUTTONS LOGIC ---
                // Now includes both Icon AND Text for each button
                // Escape single quotes in values so they are safe inside onclick single-quoted args
                const escEmail = (email + '').replace(/'/g, "\\'");
                const escToken = (token + '').replace(/'/g, "\\'");
                const qEmail = encodeURIComponent(email + '');
                let actionButtons = `<div class="action-group">`;
                
                // 1. Approve Button (Only for Pending/Inactive)
                if (status === 'inactive' || status === 'pending') {
                    actionButtons += `
                        <button onclick="approveUser('${uid}', '${escEmail}', '${escToken}')" class="btn btn-success btn-sm" title="Approve User">
                            <i class="material-icons">check</i> Approve
                        </button>
                    `;
                }

                // 2. Edit Button (navigate to edit page)
                const efName = encodeURIComponent(fName);
                const elName = encodeURIComponent(lName);
                const eUsername = encodeURIComponent(username);
                const eOrg = encodeURIComponent(org);
                const eRole = encodeURIComponent(role);
                actionButtons += `
                    <button onclick="window.location.href='user_edit_page.php?uid=${uid}&email=${qEmail}'" class="btn btn-warning btn-sm" title="Edit User">
                        <i class="material-icons">edit</i> Edit
                    </button>
                `;

                // 3. Remove Button
                actionButtons += `
                    <button onclick="deleteUser('${uid}', '${escEmail}')" class="btn btn-danger btn-sm" title="Remove User">
                        <i class="material-icons">delete</i> Remove
                    </button>
                `;
                
                actionButtons += `</div>`;

                // Insert HTML (show Firebase short id initially, then replace with local MySQL id)
                tr.innerHTML = `
                    <td class="user-id-cell"><strong>${displayID}</strong></td>
                    <td>${username}</td>
                    <td>${email}</td>
                    <td>${fullName}</td>
                    <td>${role}</td>
                    <td><span class="badge-status ${badgeClass}">${displayStatus}</span></td>
                    <td>${org}</td>
                    <td>${actionButtons}</td>
                `;

                // attach email as attribute to find it later when updating the ID
                tr.setAttribute('data-email', email || '');
                tableBody.appendChild(tr);

                // Try to fetch the local XAMPP/MySQL User_id by email and replace the displayed ID
                if (email) {
                    fetch('get_user_by_email.php?email=' + encodeURIComponent(email) + '&testing=1')
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.success && data.user && data.user.User_id) {
                                const userIdCell = tr.querySelector('.user-id-cell');
                                if (userIdCell) {
                                    userIdCell.innerHTML = '<strong>' + data.user.User_id + '</strong>';
                                }
                            }
                        })
                        .catch(err => {
                            // silently ignore - keep showing Firebase short id
                            console.debug('Could not fetch local User_id for', email, err);
                        });
                }
            });
        }, (error) => {
            console.error("Firebase Error:", error);
            tableBody.innerHTML = `<tr><td colspan="8" class="text-danger text-center">Error connecting to database: ${error.message}</td></tr>`;
        });

        // --- 1. APPROVE USER FUNCTION ---
        function approveUser(uid, email, token) {
            if(!confirm("Are you sure you want to approve " + email + "? This will send a notification.")) return;

            const formData = new FormData();
            formData.append("email", email);
            formData.append("token", token);

            // API URL
            const apiUrl = "http://localhost/deiv_api/approve_user.php"; 

            fetch(apiUrl, {
                method: "POST",
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                if(result.includes("success")) {
                    // Update Firebase status to 'active'
                    db.collection("users").doc(uid).update({
                        status: "active"
                    }).then(() => {
                        alert("User approved and notification sent!");
                    });
                } else {
                    alert("Error from PHP: " + result);
                }
            })
            .catch(error => {
                alert("Cannot connect to API.\nError: " + error + "\nMake sure XAMPP is running!");
            });
        }

        // --- 2. DELETE USER FUNCTION ---
        function deleteUser(uid, email) {
            if(confirm("Are you sure you want to PERMANENTLY REMOVE " + email + "? This cannot be undone.")) {
                db.collection("users").doc(uid).delete().then(() => {
                    alert("User removed successfully.");
                }).catch((error) => {
                    alert("Error removing user: " + error);
                });
            }
        }

        // --- 3. EDIT USER FUNCTION ---
        // --- 3. EDIT USER FUNCTION ---
        // Fetch MySQL data and open a modal form for editing
        function editUser(uid, email) {
            fetch('get_user_by_email.php?email=' + encodeURIComponent(email) + '&testing=1')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return alert(data.message || 'Local user not found');
                const user = data.user;

                // Populate modal fields
                document.getElementById('edit-user-id').value = user.User_id;
                document.getElementById('edit-first-name').value = user.first_name || '';
                document.getElementById('edit-last-name').value = user.last_name || '';
                document.getElementById('edit-username').value = user.username || '';
                document.getElementById('edit-email').value = user.email || '';
                document.getElementById('edit-organization').value = user.organization || '';
                document.getElementById('edit-role').value = user.role || '';
                document.getElementById('edit-password').value = '';

                // show modal
                const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
                modal.show();
            })
            .catch(err => {
                console.error(err);
                alert('Error fetching user.');
            });
        }

        // Handle modal form submit
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('edit-user-form');
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(form);
                formData.append('testing', '1');

                fetch('update_user.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return alert('Update failed: ' + (res.message||''));

                    // If email or username changed, sync Firebase
                    if ((res.old_email !== res.new_email) || (res.old_username !== res.new_username)) {
                        db.collection('users').where('email', '==', res.old_email).get().then(snapshot => {
                            snapshot.forEach(doc => {
                                const updates = {};
                                if (res.old_email !== res.new_email) updates.email = res.new_email;
                                if (res.old_username !== res.new_username) updates.username = res.new_username;
                                doc.ref.update(updates).catch(err => console.error('Firebase update error', err));
                            });
                        }).catch(err => console.error('Firebase lookup error', err));
                    }

                    alert('User updated successfully');
                    // close modal
                    const modalEl = document.getElementById('editUserModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                })
                .catch(err => { console.error(err); alert('Error updating user'); });
            });
        });

        // Show modal immediately using Firebase data, then try to refresh from MySQL
        function showEditModalFromFirebase(uid, fNameEnc, lNameEnc, usernameEnc, emailEnc, orgEnc, roleEnc) {
            const fName = decodeURIComponent(fNameEnc || '');
            const lName = decodeURIComponent(lNameEnc || '');
            const username = decodeURIComponent(usernameEnc || '');
            const email = decodeURIComponent(emailEnc || '');
            const org = decodeURIComponent(orgEnc || '');
            const role = decodeURIComponent(roleEnc || '');

            document.getElementById('edit-user-id').value = uid;
            document.getElementById('edit-first-name').value = fName;
            document.getElementById('edit-last-name').value = lName;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-organization').value = org;
            document.getElementById('edit-role').value = role;
            document.getElementById('edit-password').value = '';

            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();

            // Background refresh from MySQL (non-blocking). Use testing flag for local setups.
            fetch('get_user_by_email.php?email=' + encodeURIComponent(email) + '&testing=1')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.user) {
                    const u = data.user;
                    document.getElementById('edit-user-id').value = u.User_id || uid;
                    document.getElementById('edit-first-name').value = u.first_name || fName;
                    document.getElementById('edit-last-name').value = u.last_name || lName;
                    document.getElementById('edit-username').value = u.username || username;
                    document.getElementById('edit-email').value = u.email || email;
                    document.getElementById('edit-organization').value = u.organization || org;
                    document.getElementById('edit-role').value = u.role || role;
                }
            }).catch(err => { /* ignore */ });
        }
    </script>
</body>
</html>