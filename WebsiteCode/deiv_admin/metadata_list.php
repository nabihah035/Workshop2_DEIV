<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit;
}

include "../deiv_api/db.php";

try {
    $columnQuery = $pdo->query("SHOW COLUMNS FROM evidence");
    $evidenceColumns = $columnQuery->fetchAll(PDO::FETCH_ASSOC);

    $evidenceTitleColumn = null;
    foreach ($evidenceColumns as $col) {
        if (stripos($col['Type'], 'varchar') !== false || stripos($col['Type'], 'text') !== false) {
            $evidenceTitleColumn = $col['Field'];
            break;
        }
    }
    if (!$evidenceTitleColumn) $evidenceTitleColumn = "Evidence_id";

    // search filter
    $search = "";
    $params = [];
    if (!empty($_GET["search"])) {
        $search = trim($_GET["search"]);
        $params[":search"] = "%$search%";
        $sql = "
            SELECT m.Meta_id, m.meta_key, m.meta_value, e.`$evidenceTitleColumn` AS evidence_title
            FROM metadata m
            LEFT JOIN evidence e ON m.Evidence_id = e.Evidence_id
            WHERE m.meta_key LIKE :search
               OR m.meta_value LIKE :search
               OR e.`$evidenceTitleColumn` LIKE :search
            ORDER BY m.Meta_id DESC
        ";
    } else {
        $sql = "
            SELECT m.Meta_id, m.meta_key, m.meta_value, e.`$evidenceTitleColumn` AS evidence_title
            FROM metadata m
            LEFT JOIN evidence e ON m.Evidence_id = e.Evidence_id
            ORDER BY m.Meta_id DESC
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $metadata = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Evidence Metadata</title>

    <!-- Tailwind for Sidebar -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Bootstrap for Table -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

</head>
<body class="bg-gray-50">

<!-- MAIN WRAPPER -->
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
           class="flex items-center p-3 bg-blue-600 text-white rounded-lg">
            <span class="material-icons mr-3">list_alt</span> Evidence Metadata
        </a>

        <a href="case_list.php"
           class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
            <span class="material-icons mr-3">folder</span> Case Files
        </a>

        <a href="audit_logs.php"
           class="flex items-center p-3 hover:bg-gray-100 rounded-lg">
            <span class="material-icons mr-3">history</span> Audit Logs
        </a>

        <a href="logout.php"
           class="flex items-center p-3 hover:bg-gray-100 rounded-lg text-red-600">
            <span class="material-icons mr-3">logout</span> Logout
        </a>

    </nav>
</aside>



<!-- ====== CONTENT AREA ====== -->
<!-- ====== CONTENT AREA ====== -->
<main class="flex-1 p-6">

    <h1 class="text-3xl font-semibold mb-6">Evidence Metadata</h1>

    <div class="d-flex justify-content-between mb-3">
        <a href="metadata_add.php" class="btn btn-primary">Add Metadata</a>

        <form class="d-flex" method="get">
            <input type="text" name="search" class="form-control me-2"
                   placeholder="Search metadata..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary">Search</button>
        </form>
    </div>

    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Meta Key</th>
                <th>Meta Value</th>
                <th>Evidence</th>
                <th width="150">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($metadata)): ?>
                <?php foreach ($metadata as $m): ?>
                    <tr>
                        <td><?= $m['Meta_id'] ?></td>
                        <td><?= htmlspecialchars($m['meta_key']) ?></td>
                        <td><?= htmlspecialchars($m['meta_value']) ?></td>
                        <td><?= $m['evidence_title'] ? htmlspecialchars($m['evidence_title']) : '<i>No Evidence</i>' ?></td>
                        <td>
                            <a href="metadata_edit.php?id=<?= $m['Meta_id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="metadata_delete.php?id=<?= $m['Meta_id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this metadata?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center"><i>No metadata found.</i></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</main>

</div><!-- end flex -->

</body>
</html>
