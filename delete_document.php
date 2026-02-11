<?php
session_start();
require 'config.php';
require 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}

$id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];


$stmt = $conn->prepare("
    SELECT * FROM documents
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("File not found or no permission");
}

$doc = $result->fetch_assoc();


$archive = $conn->prepare("
    UPDATE documents
    SET status = 0
    WHERE id = ?
");
$archive->bind_param("i", $id);
$archive->execute();


logActivity(
    $conn,
    $user_id,
    "ARCHIVE",
    $id,
    "Archived {$doc['file_name']} from {$doc['school_name']}"
);

header("Location: dashboard.php");
exit;
