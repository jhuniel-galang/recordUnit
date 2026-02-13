<?php
session_start();
require 'config.php';
require 'auth.php';
requireLogin();
requireAdmin();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$school = $_GET['school'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = ["d.status = 0"]; // Only archived documents
$params = [];
$types = "";

if (!empty($search)) {
    $whereConditions[] = "(d.file_name LIKE ? OR d.remarks LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

if (!empty($school)) {
    $whereConditions[] = "d.school_name = ?";
    $params[] = $school;
    $types .= "s";
}

if (!empty($user_filter)) {
    $whereConditions[] = "d.user_id = ?";
    $params[] = $user_filter;
    $types .= "i";
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(d.uploaded_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(d.uploaded_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get all schools for filter dropdown
$schoolsResult = $conn->query("SELECT DISTINCT school_name FROM documents WHERE school_name IS NOT NULL AND school_name != '' ORDER BY school_name");
$schools = [];
while ($schoolRow = $schoolsResult->fetch_assoc()) {
    $schools[] = $schoolRow['school_name'];
}

// Get all users for filter dropdown
$usersResult = $conn->query("SELECT id, name FROM users ORDER BY name");
$users = [];
while ($userRow = $usersResult->fetch_assoc()) {
    $users[] = $userRow;
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) AS total FROM documents d JOIN users u ON d.user_id = u.id $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $limit);

// Add pagination parameters
$params_pagination = $params;
$types_pagination = $types;
$params_pagination[] = $limit;
$params_pagination[] = $offset;
$types_pagination .= "ii";

// Main query with pagination
$sql = "
    SELECT d.*, u.name AS uploader_name, u.email
    FROM documents d
    JOIN users u ON d.user_id = u.id
    $whereClause
    ORDER BY d.uploaded_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!empty($params_pagination)) {
    $stmt->bind_param($types_pagination, ...$params_pagination);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics for archived documents
$totalArchived = $conn->query("SELECT COUNT(*) as count FROM documents WHERE status = 0")->fetch_assoc()['count'];
$totalSize = $conn->query("SELECT COALESCE(SUM(file_size), 0) as total FROM documents WHERE status = 0")->fetch_assoc()['total'];
$totalUsers = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM documents WHERE status = 0")->fetch_assoc()['count'];
$oldestArchive = $conn->query("SELECT MIN(uploaded_at) as oldest FROM documents WHERE status = 0")->fetch_assoc()['oldest'];

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 0) {
        return $bytes . ' bytes';
    } else {
        return '0 bytes';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Management | Document Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 12px;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo i {
            font-size: 1.8rem;
        }

        .logo h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Main Container */
        .container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-left: 5px solid var(--warning);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-card.total-archived {
            border-left-color: var(--warning);
        }

        .stat-card.total-size {
            border-left-color: var(--info);
        }

        .stat-card.total-users {
            border-left-color: var(--primary);
        }

        .stat-card.oldest {
            border-left-color: var(--gray);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .stat-card.total-archived .stat-icon {
            background: linear-gradient(135deg, var(--warning) 0%, #c2185b 100%);
        }

        .stat-card.total-size .stat-icon {
            background: linear-gradient(135deg, var(--info) 0%, #3a7bc8 100%);
        }

        .stat-card.total-users .stat-icon {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }

        .stat-card.oldest .stat-icon {
            background: linear-gradient(135deg, var(--gray) 0%, #495057 100%);
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-content p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Main Card */
        .main-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: #dee2e6;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #bd2130 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.3);
        }

        /* Search and Filter */
        .filter-section {
            padding: 1.5rem;
            background: var(--light);
            border-bottom: 1px solid var(--light-gray);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .form-control {
            padding: 0.75rem 1rem;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead {
            background: linear-gradient(135deg, var(--warning) 0%, #c2185b 100%);
            color: white;
        }

        .table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .table tbody tr {
            border-bottom: 1px solid var(--light-gray);
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: rgba(247, 37, 133, 0.05);
        }

        .table td {
            padding: 1rem;
            color: var(--dark);
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-archived {
            background: rgba(247, 37, 133, 0.2);
            color: var(--warning);
        }

        .badge-pdf {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .badge-doc {
            background: rgba(13, 110, 253, 0.2);
            color: #0d6efd;
        }

        .badge-xls {
            background: rgba(25, 135, 84, 0.2);
            color: #198754;
        }

        .badge-jpg, .badge-png, .badge-gif {
            background: rgba(111, 66, 193, 0.2);
            color: #6f42c1;
        }

        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar-small {
            width: 32px;
            height: 32px;
            background: rgba(247, 37, 133, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--warning);
            font-weight: 600;
        }

        .file-link {
            color: var(--dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .file-link:hover {
            color: var(--warning);
        }

        .description-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .description-cell:hover {
            white-space: normal;
            overflow: visible;
            position: relative;
            z-index: 10;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 4px;
            padding: 8px;
        }

        .timestamp {
            font-size: 0.85rem;
            color: var(--gray);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.85rem;
        }

        .action-btn.restore {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .action-btn.restore:hover {
            background: #28a745;
            color: white;
        }

        .action-btn.delete {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        .action-btn.delete:hover {
            background: #dc3545;
            color: white;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            gap: 0.5rem;
        }

        .page-link {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: white;
            border: 2px solid var(--light-gray);
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .page-link:hover {
            border-color: var(--warning);
            color: var(--warning);
            transform: translateY(-2px);
        }

        .page-link.active {
            background: var(--warning);
            border-color: var(--warning);
            color: white;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Empty State */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--light-gray);
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
            font-size: 0.9rem;
            border-top: 1px solid var(--light-gray);
            margin-top: 2rem;
        }

        /* Results Info */
        .results-info {
            padding: 1rem 1.5rem;
            background: rgba(247, 37, 133, 0.1);
            border-radius: 8px;
            margin: 0 1.5rem 1rem 1.5rem;
            font-size: 0.9rem;
            color: var(--warning);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--light-gray);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                font-size: 0.9rem;
            }
            
            .table th, .table td {
                padding: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .card-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .card-actions {
                width: 100%;
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <i class="fas fa-folder-open"></i>
            <h1>Record Management System</h1>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
            </div>
            <div>
                <strong><?= htmlspecialchars($_SESSION['name']) ?></strong>
                <div style="font-size: 0.8rem; opacity: 0.9;">
                    Administrator
                </div>
            </div>
            <a href="dashboard.php" class="logout-btn" style="background: rgba(255, 255, 255, 0.1); margin-right: 10px;">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="activity_logs.php" class="logout-btn" style="background: rgba(255, 255, 255, 0.1); margin-right: 10px;">
                <i class="fas fa-history"></i>
                Logs
            </a>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card total-archived">
                <div class="stat-icon">
                    <i class="fas fa-archive"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($totalArchived) ?></h3>
                    <p>Archived Documents</p>
                </div>
            </div>
            
            <div class="stat-card total-size">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-content">
                    <h3><?= formatFileSize($totalSize) ?></h3>
                    <p>Total Storage Used</p>
                </div>
            </div>
            
            <div class="stat-card total-users">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($totalUsers) ?></h3>
                    <p>Users with Archives</p>
                </div>
            </div>
            
            <div class="stat-card oldest">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $oldestArchive ? date('M d, Y', strtotime($oldestArchive)) : 'N/A' ?></h3>
                    <p>Oldest Archive</p>
                </div>
            </div>
        </div>

        <!-- Main Archive Card -->
        <div class="main-card">
            <div class="card-header">
                <h2><i class="fas fa-archive" style="color: var(--warning);"></i> Archived Documents Management</h2>
                <div class="card-actions">
                    
                    <button onclick="clearFilters()" class="btn btn-secondary">
                        <i class="fas fa-filter-circle-xmark"></i> Clear Filters
                    </button>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label><i class="fas fa-search"></i> Search Documents</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by filename, remarks, or uploader..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-school"></i> Filter by School</label>
                        <select name="school" class="form-control">
                            <option value="">All Schools</option>
                            <?php foreach ($schools as $schoolOption): ?>
                                <option value="<?= htmlspecialchars($schoolOption) ?>" 
                                    <?= $school == $schoolOption ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($schoolOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Uploaded By</label>
                        <select name="user" class="form-control">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" 
                                    <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Date From</label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Date To</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary" style="height: 46px;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($search || $school || $user_filter || $date_from || $date_to): ?>
            <div class="results-info">
                <i class="fas fa-info-circle"></i>
                Showing <?= $result->num_rows ?> of <?= $totalCount ?> archived documents matching your criteria
                <?php if ($search || $school || $user_filter || $date_from || $date_to): ?>
                | <a href="archive.php" style="color: var(--warning); text-decoration: none; font-weight: 600;">
                    <i class="fas fa-times"></i> Clear all filters
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Archived Documents Table -->
            <div class="table-container">
                <?php if ($result->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Remarks</th>
                            <th>Uploaded By</th>
                            <th>Archived Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): 
                            $file_ext = strtolower(pathinfo($row['file_name'], PATHINFO_EXTENSION));
                            $badge_class = 'badge-' . $file_ext;
                            if (!in_array($badge_class, ['badge-pdf', 'badge-doc', 'badge-xls', 'badge-jpg', 'badge-png', 'badge-gif'])) {
                                $badge_class = 'badge-archived';
                            }
                        ?>
                        <tr>
                            <td>
                                <span class="badge badge-archived">
                                    <i class="fas fa-school"></i> <?= htmlspecialchars($row['school_name'] ?: 'N/A') ?>
                                </span>
                            </td>
                            <td>
                                <a href="#" onclick="previewDocument('<?= $row['file_path'] ?>', '<?= htmlspecialchars($row['file_name']) ?>')" class="file-link">
                                    <i class="fas fa-file-<?= getFileIcon($file_ext) ?>"></i>
                                    <?= htmlspecialchars($row['file_name']) ?>
                                </a>
                                <div style="font-size: 0.75rem; color: var(--gray); margin-top: 4px;">
                                    <i class="fas fa-weight-hanging"></i> <?= formatFileSize($row['file_size']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $badge_class ?>">
                                    <?= strtoupper($file_ext) ?>
                                </span>
                            </td>
                            <td class="description-cell" title="<?= htmlspecialchars($row['remarks']) ?>">
                                <?= htmlspecialchars($row['remarks'] ?: 'No remarks') ?>
                            </td>
                            <td>
                                <div class="user-info-cell">
                                    <div class="user-avatar-small">
                                        <?= strtoupper(substr($row['uploader_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($row['uploader_name']) ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="timestamp">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= date('M d, Y', strtotime($row['uploaded_at'])) ?>
                                    <br>
                                    <i class="fas fa-clock"></i>
                                    <?= date('h:i A', strtotime($row['uploaded_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="openRestoreModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['file_name'], ENT_QUOTES) ?>')" 
                                            class="action-btn restore">
                                        <i class="fas fa-history"></i> Restore
                                    </button>
                                    <button onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['file_name'], ENT_QUOTES) ?>')" 
                                            class="action-btn delete">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-archive"></i>
                    <h3>No archived documents found</h3>
                    <p>There are no archived documents matching your search criteria.</p>
                    <?php if ($search || $school || $user_filter || $date_from || $date_to): ?>
                    <a href="archive.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <?php else: ?>
                    <p style="margin-top: 1rem; color: var(--gray);">
                        <i class="fas fa-info-circle"></i> 
                        Documents appear here when you archive them from the dashboard.
                    </p>
                    <a href="dashboard.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-arrow-left"></i> Go to Dashboard
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-link">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fas fa-angle-double-left"></i></span>
                    <span class="page-link disabled"><i class="fas fa-angle-left"></i></span>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" class="page-link">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fas fa-angle-right"></i></span>
                    <span class="page-link disabled"><i class="fas fa-angle-double-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="modal" id="restoreModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-history" style="color: #28a745;"></i> Restore Document</h3>
                <button class="modal-close" onclick="closeRestoreModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 1rem;">Are you sure you want to restore this document?</p>
                <div style="background: var(--light); padding: 1rem; border-radius: 8px;">
                    <strong id="restoreFileName"></strong>
                </div>
                <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--gray);">
                    <i class="fas fa-info-circle"></i> 
                    Restored documents will appear in the main dashboard.
                </p>
            </div>
            <div class="modal-footer">
                <button onclick="closeRestoreModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <a href="#" id="restoreConfirmBtn" class="btn btn-success">
                    <i class="fas fa-history"></i> Restore Document
                </a>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Permanently Delete Document</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background: rgba(220, 53, 69, 0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <p style="color: #dc3545; font-weight: 600;">
                        <i class="fas fa-exclamation-circle"></i> 
                        This action cannot be undone!
                    </p>
                </div>
                <p style="margin-bottom: 1rem;">Are you sure you want to permanently delete this document?</p>
                <div style="background: var(--light); padding: 1rem; border-radius: 8px;">
                    <strong id="deleteFileName"></strong>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeDeleteModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete Permanently
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Record Management System <?= date('Y') ?></p>
        <p style="font-size: 0.8rem; margin-top: 0.5rem;">
            <i class="fas fa-archive"></i> Archive Management System
        </p>
    </div>

    <script>
        // Clear all filters
        function clearFilters() {
            window.location.href = 'archive.php';
        }

        // Restore Modal Functions
        function openRestoreModal(id, fileName) {
            document.getElementById('restoreFileName').textContent = fileName;
            document.getElementById('restoreConfirmBtn').href = 'restore_document.php?id=' + id;
            document.getElementById('restoreModal').style.display = 'flex';
        }

        function closeRestoreModal() {
            document.getElementById('restoreModal').style.display = 'none';
        }

        // Delete Modal Functions
        function openDeleteModal(id, fileName) {
            document.getElementById('deleteFileName').textContent = fileName;
            document.getElementById('deleteConfirmBtn').href = 'delete_permanent.php?id=' + id;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Preview Document
        function previewDocument(filePath, fileName) {
            // You can implement your secure PDF/image viewer here
            // For now, open in new tab
            window.open(filePath, '_blank');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats cards on load
            const stats = document.querySelectorAll('.stat-card');
            stats.forEach((stat, index) => {
                setTimeout(() => {
                    stat.style.opacity = '0';
                    stat.style.transform = 'translateY(20px)';
                    stat.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        stat.style.opacity = '1';
                        stat.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });

            // Auto-focus search input if there's a search
            <?php if ($search): ?>
                document.querySelector('input[name="search"]').focus();
            <?php endif; ?>
        });

        // Keyboard shortcut for search (Ctrl+F)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to get icon based on file extension
function getFileIcon($ext) {
    $icons = [
        'pdf' => 'pdf',
        'doc' => 'word',
        'docx' => 'word',
        'xls' => 'excel',
        'xlsx' => 'excel',
        'ppt' => 'powerpoint',
        'pptx' => 'powerpoint',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'txt' => 'alt',
        'zip' => 'archive',
        'rar' => 'archive'
    ];
    
    return $icons[$ext] ?? 'alt';
}
?>