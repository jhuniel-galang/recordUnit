<?php
session_start();
require 'config.php';
require 'auth.php';
require 'log_activity.php';

requireLogin();
requireAdmin();

if (!isset($_GET['id'])) {
    header("Location: archive.php");
    exit;
}

$id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get document (only archived)
$stmt = $conn->prepare("
    SELECT * FROM documents
    WHERE id = ? AND status = 0
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("Document not found or not archived.");
}

$doc = $result->fetch_assoc();


logActivity(
    $conn,
    $user_id,
    "PERMANENT DELETE",
    $id,
    "Permanently deleted {$doc['file_name']} from {$doc['school_name']}"
);


if (!empty($doc['file_path']) && file_exists($doc['file_path'])) {
    unlink($doc['file_path']);
}


$del = $conn->prepare("DELETE FROM documents WHERE id = ?");
$del->bind_param("i", $id);
$del->execute();

header("Location: archive.php");
exit;
