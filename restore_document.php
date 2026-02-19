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

$id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];


$stmt = $conn->prepare("
    SELECT * FROM documents
    WHERE id = ? AND status = 0
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("Document not found or already restored.");
}

$doc = $result->fetch_assoc();


$restore = $conn->prepare("
    UPDATE documents
    SET status = 1
    WHERE id = ?
");
$restore->bind_param("i", $id);
$restore->execute();


logActivity(
    $conn,
    $user_id,
    "RESTORE",
    $id,
    "Restored {$doc['file_name']} from {$doc['school_name']}"
);


header("Location: archive.php");
exit;
