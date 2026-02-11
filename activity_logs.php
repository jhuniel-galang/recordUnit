<?php
session_start();
require 'config.php';
require 'auth.php';
requireLogin();
requireAdmin();

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}

$result = $conn->query("
    SELECT 
        al.*, u.name 
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
");
?>

<h2>Activity Logs</h2>

<div class="container">
<div class="card">
<h3>Activity Logs</h3>
<table border="1" cellpadding="5">
<tr>
    <th>User</th>
    <th>Action</th>
    <th>Description</th>
    <th>Date</th>
</tr>

<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td><?= htmlspecialchars($row['action']) ?></td>
    <td><?= htmlspecialchars($row['description']) ?></td>
    <td><?= $row['created_at'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>
