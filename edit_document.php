<?php
session_start();
require 'config.php';
require 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}

$id      = intval($_POST['id']);
$school  = $_POST['school_name'];
$remarks = $_POST['remarks'];
$user_id = $_SESSION['user_id'];


$stmt = $conn->prepare("
    UPDATE documents 
    SET school_name = ?, remarks = ?
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ssii", $school, $remarks, $id, $user_id);
$stmt->execute();

logActivity(
    $conn,
    $user_id,
    "EDIT",
    $id,
    "Updated document details (School: {$school})"
);

header("Location: dashboard.php");
exit;
