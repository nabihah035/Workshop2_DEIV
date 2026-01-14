<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['User_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Notifications</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-4">
<h2>Notifications</h2>
<form id="s">
  <div class="mb-3"><textarea name="message" class="form-control" placeholder="Message" required></textarea></div>
  <input type="hidden" name="user_id" value="">
  <button class="btn btn-success">Send</button>
</form>
<hr>
<table class="table" id="tbl"><thead><tr><th>ID</th><th>Message</th><th>User</th><th>Status</th><th>Action</th></tr></thead><tbody></tbody></table>
<script>
async function load() {
  const res = await fetch('/deiv_api/notification/get_notifications.php');
  const data = await res.json();
  const tbody = document.querySelector('#tbl tbody');
  tbody.innerHTML='';
  data.forEach(r=>{
    tbody.innerHTML += `<tr>
      <td>${r.Notification_id}</td>
      <td>${r.message}</td>
      <td>${r.username||''}</td>
      <td>${r.status}</td>
      <td>${r.status=='unread'? `<button class="btn btn-sm btn-primary" onclick="mark(${r.Notification_id})">Mark Read</button>` : ''}</td>
    </tr>`;
  });
}
async function mark(id){
  const f = new FormData(); f.append('id', id);
  await fetch('/deiv_api/notification/mark_read.php', {method:'POST', body: f});
  load();
}
document.getElementById('s').addEventListener('submit', async e=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const res = await fetch('/deiv_api/notification/send_notification.php', {method:'POST', body: fd});
  const j = await res.json();
  if (j.success) { load(); e.target.reset(); } else alert('Error');
});
load();
</script>
</body></html>
