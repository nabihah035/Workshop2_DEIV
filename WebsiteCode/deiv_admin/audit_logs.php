<?php 
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit;
}
?>


<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audit Logs</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Bootstrap ONLY for table -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

<div class="flex">

    <!-- ====== SIDEBAR ====== -->
    <aside class="w-72 bg-white shadow-lg h-screen p-6">
        <h2 class="text-xl font-bold mb-8">DEIV ADMIN</h2>

        <nav class="space-y-2">

            <a href="dashboard.php"
               class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">home</span> Dashboard
            </a>

             <a href="user_list.php"
                class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">group</span> User Management
            </a>
            
            <a href="evidence_list.php"
               class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">inventory_2</span> Evidence Records
            </a>

            <a href="metadata_list.php"
               class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">list_alt</span> Evidence Metadata
            </a>

            <a href="case_list.php"
               class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
                <span class="material-icons mr-3">folder</span> Case Files
            </a>

            <a href="audit_logs.php"
               class="flex items-center p-3 bg-blue-600 text-white rounded-lg">
                <span class="material-icons mr-3">history</span> Audit Logs
            </a>

            <a href="logout.php"
               class="flex items-center p-3 hover:bg-gray-100 rounded-lg text-red-600">
                <span class="material-icons mr-3">logout</span> Logout
            </a>

        </nav>
    </aside>

    <!-- ====== MAIN CONTENT ====== -->
    <main class="flex-1 p-8">

        <h2 class="text-3xl font-semibold mb-6">Audit Logs</h2>

        <div class="bg-white p-6 rounded-xl shadow">
            <table class="table table-striped" id="tbl">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>IP</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </main>
</div>

<script>
// ====== LOAD AUDIT LOG DATA ======
async function load() {
    const r = await fetch('/deiv_api/audit/get_audit.php');
    const data = await r.json();
    const tbody = document.querySelector('#tbl tbody');

    tbody.innerHTML = '';

    data.forEach(row => {
        tbody.innerHTML += `
            <tr>
                <td>${row.Audit_id}</td>
                <td>${row.action}</td>
                <td>${row.username || ''}</td>
                <td>${row.ip_address}</td>
                <td>${row.date_time}</td>
            </tr>
        `;
    });
}

load();
</script>

</body>
</html>
