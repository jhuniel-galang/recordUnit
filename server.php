<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    die('Access denied. Please login.');
}

// Check permissions
$allowed_files = [
    'image.png' => true,
    'letter.pdf' => true
];

$file = $_GET['file'] ?? '';
if (!isset($allowed_files[$file])) {
    die('File not found or access denied.');
}

// Serve file with proper headers (view only, no download)
$filepath = '/secure_storage/' . $file;
$mime_type = mime_content_type($filepath);

header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . $file . '"'); // "inline" for preview
header('Content-Length: ' . filesize($filepath));
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN'); // Prevent embedding in other sites

readfile($filepath);
?>