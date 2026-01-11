<?php
session_start();
// Minimal access check (uncomment/adjust as needed)
/*
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: index.php");
    exit;
}
*/

$email = $_GET['email'] ?? '';
$uid = $_GET['uid'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit User | DEIV Admin</title>
    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
<div class="container" style="max-width:820px;margin:40px auto;">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Edit User</h5>
            <div>
                <a href="user_management.php" class="btn btn-secondary">Back</a>
            </div>
        </div>
        <div class="card-body">
            <form id="edit-user-form">
                <input type="hidden" id="edit-user-id" name="id" value="">

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

                <div class="d-flex justify-content-end gap-2">
                    <a href="user_management.php" class="btn btn-outline-secondary">Back</a>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
    const params = new URLSearchParams(window.location.search);
    const emailParam = params.get('email') || '';
    const uidParam = params.get('uid') || '';

    const form = document.getElementById('edit-user-form');
    const inputId = document.getElementById('edit-user-id');
    const inputF = document.getElementById('edit-first-name');
    const inputL = document.getElementById('edit-last-name');
    const inputU = document.getElementById('edit-username');
    const inputE = document.getElementById('edit-email');
    const inputO = document.getElementById('edit-organization');
    const inputR = document.getElementById('edit-role');

    // Prefill from query string while we fetch authoritative data
    inputE.value = decodeURIComponent(emailParam);
    inputId.value = uidParam;

    // Try to fetch MySQL-backed user record for more complete data
    if (emailParam) {
        fetch('get_user_by_email.php?email=' + encodeURIComponent(emailParam) + '&testing=1')
            .then(r => r.json())
            .then(data => {
                if (data && data.success && data.user) {
                    const u = data.user;
                    inputId.value = u.User_id || uidParam;
                    inputF.value = u.first_name || '';
                    inputL.value = u.last_name || '';
                    inputU.value = u.username || '';
                    inputE.value = u.email || decodeURIComponent(emailParam) || '';
                    inputO.value = u.organization || '';
                    inputR.value = u.role || '';
                }
            }).catch(err => { console.warn('Could not fetch local user:', err); });
    }

    form.addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(form);
        fd.append('testing', '1');

        fetch('update_user.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.success) return alert('Update failed: ' + (res.message || ''));
                alert('User updated successfully');
                // After saving, return to management page
                window.location.href = 'user_management.php';
            }).catch(err => { console.error(err); alert('Error updating user'); });
    });
})();
</script>
</body>
</html>
