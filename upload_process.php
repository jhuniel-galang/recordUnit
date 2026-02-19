<?php
session_start();
require 'config.php';
require 'log_activity.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$school  = trim($_POST['school_name']);
$remarks = trim($_POST['remarks']);

// Validate school name
if (empty($school)) {
    die("School name is required.");
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    $error_message = "File upload failed. ";
    switch ($_FILES['document']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $error_message .= "File too large.";
            break;
        case UPLOAD_ERR_PARTIAL:
            $error_message .= "File was only partially uploaded.";
            break;
        case UPLOAD_ERR_NO_FILE:
            $error_message .= "No file was selected.";
            break;
        default:
            $error_message .= "Error code: " . $_FILES['document']['error'];
    }
    die($error_message);
}

$file = $_FILES['document'];

// Allowed file types
$allowedTypes = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif'
];

$fileName = $file['name'];
$fileTmp  = $file['tmp_name'];
$fileSize = $file['size'];
$fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Validate file extension
if (!array_key_exists($fileExt, $allowedTypes)) {
    die("Invalid file type. Allowed types: PDF, DOCX, XLSX, JPG, PNG, GIF");
}


$MAX_FILE_SIZE = 100 * 1024 * 1024; 
if ($fileSize > $MAX_FILE_SIZE) {
    $size_in_mb = round($fileSize / (1024 * 1024), 2);
    $max_in_mb = 100;
    die("File too large. Maximum allowed size is {$max_in_mb}MB. Your file is {$size_in_mb}MB.");
}


$cleanFileName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $fileName);


$newFileName = time() . "_" . uniqid() . "_" . $cleanFileName;
$uploadDir  = "uploads/";
$uploadPath = $uploadDir . $newFileName;


if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die("Failed to create upload directory. Please check permissions.");
    }
}


if (!is_writable($uploadDir)) {
    die("Upload directory is not writable. Please check permissions.");
}


if (!move_uploaded_file($fileTmp, $uploadPath)) {
    die("Failed to save uploaded file. Please try again.");
}


chmod($uploadPath, 0644);


$stmt = $conn->prepare("
    INSERT INTO documents 
    (user_id, school_name, file_name, file_path, file_type, file_size, remarks, status, uploaded_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
");

if (!$stmt) {
    
    unlink($uploadPath);
    die("Database error: " . $conn->error);
}

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

if (!$stmt->execute()) {
    
    unlink($uploadPath);
    die("Database error while saving document: " . $stmt->error);
}

$document_id = $conn->insert_id;


logActivity(
    $conn,
    $user_id,
    "UPLOAD",
    $document_id,
    "Uploaded {$fileName} for {$school} (" . round($fileSize / 1024 / 1024, 2) . "MB)"
);


$_SESSION['upload_success'] = "Document uploaded successfully!";


header("Location: dashboard.php?upload=success");
exit;
?>