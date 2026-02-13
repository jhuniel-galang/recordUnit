<?php
session_start();
require 'auth.php';
requireLogin();
requireAdmin();
require 'config.php';

$result = $conn->query("
    SELECT d.*, u.name AS uploader_name
    FROM documents d
    JOIN users u ON d.user_id = u.id
    WHERE d.status = 0
    ORDER BY d.uploaded_at DESC
");
?>

<h2>Archived Documents</h2>

<div class="container">
<div class="card">
<h3>Archived Documents</h3>

<table border="1" cellpadding="5">
<tr>
    <th>School</th>
    <th>File</th>
    <th>Type</th>
    <th>Remarks</th>
    <th>Uploaded By</th>
    <th>Date</th>
    <th>Action</th>
</tr>


<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['school_name']) ?></td>
    <td><?= htmlspecialchars($row['file_name']) ?></td>
    <td><?= strtoupper($row['file_type']) ?></td>
    <td><?= htmlspecialchars($row['remarks']) ?></td>
    <td><?= htmlspecialchars($row['uploader_name']) ?></td>
    <td><?= $row['uploaded_at'] ?></td>
    <td>
        <a href="delete_permanent.php?id=<?= $row['id'] ?>"
           onclick="return confirm('This will permanently delete the file. Continue?')"
           style="color:red; text-decoration:none;">
           Delete Permanently
        </a>
    </td>
</tr>
<?php endwhile; ?>

</table>
</div>
</div>

<a href="dashboard.php"> Back to Dashboard</a>
