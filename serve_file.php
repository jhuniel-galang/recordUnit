<?php
session_start();
require 'config.php';
require 'auth.php';
requireLogin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get file info and verify permissions
$stmt = $conn->prepare("
    SELECT d.* 
    FROM documents d
    WHERE d.id = ? AND d.status = 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("HTTP/1.0 404 Not Found");
    die("File not found.");
}

$doc = $result->fetch_assoc();

// Check if user has permission (admin or owner)
if (!isAdmin() && $doc['user_id'] != $_SESSION['user_id']) {
    header("HTTP/1.0 403 Forbidden");
    die("Access denied.");
}

$file_path = $doc['file_path'];

if (!file_exists($file_path)) {
    header("HTTP/1.0 404 Not Found");
    die("File not found on server.");
}

// Get file extension
$file_ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));

// Set appropriate headers for viewing only
if ($file_ext === 'pdf') {
    header("Content-Type: application/pdf");
    header("Content-Disposition: inline; filename=\"" . $doc['file_name'] . "\"");
} elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
    $mime_types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp'
    ];
    header("Content-Type: " . $mime_types[$file_ext]);
} else {
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"" . $doc['file_name'] . "\"");
}

// Security headers to prevent download
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src *; media-src *; font-src *;");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Output file
readfile($file_path);
exit;
?>