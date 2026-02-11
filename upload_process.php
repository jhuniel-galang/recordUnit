<?php
session_start();
require 'config.php';
require 'log_activity.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$school  = $_POST['school_name'];
$remarks = trim($_POST['remarks']);

if (!isset($_FILES['document'])) {
    die("No file uploaded");
}

$file = $_FILES['document'];

$allowedTypes = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$fileName = $file['name'];
$fileTmp  = $file['tmp_name'];
$fileSize = $file['size'];
$fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!array_key_exists($fileExt, $allowedTypes)) {
    die("Invalid file type");
}

if ($fileSize > 5 * 1024 * 1024) {
    die("File too large (Max 5MB)");
}


$newFileName = time() . "_" . uniqid() . "." . $fileExt;
$uploadPath = "uploads/" . $newFileName;

if (!move_uploaded_file($fileTmp, $uploadPath)) {
    die("Upload failed");
}


$stmt = $conn->prepare("
    INSERT INTO documents 
    (user_id, school_name, file_name, file_path, file_type, file_size, remarks)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issssis",
    $user_id,
    $school,
    $fileName,
    $uploadPath,
    $fileExt,
    $fileSize,
    $remarks
);

if ($stmt->execute()) {
    header("Location: dashboard.php");
} else {
    echo "Database error";
}



$document_id = $conn->insert_id;

logActivity(
    $conn,
    $user_id,
    "UPLOAD",
    $document_id,
    "Uploaded {$fileName} for {$school}"
);

