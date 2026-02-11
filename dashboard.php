<?php
session_start();

require 'config.php';
require 'auth.php';

requireLogin();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get all schools for filter dropdown
$schoolsResult = $conn->query("SELECT DISTINCT school_name FROM documents WHERE school_name IS NOT NULL AND school_name != '' ORDER BY school_name");
$schools = [];
while ($schoolRow = $schoolsResult->fetch_assoc()) {
    $schools[] = $schoolRow['school_name'];
}

// SEARCH, FILTER, PAGINATION
$search = $_GET['search'] ?? '';
$school = $_GET['school'] ?? '';
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

// BASE CONDITIONS
$whereConditions = [];
$params = [];
$types = "";

// Always show active documents
$whereConditions[] = "d.status = 1";

// Role condition
if (!isAdmin()) {
    $whereConditions[] = "d.user_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}

// Search condition
if (!empty($search)) {
    $whereConditions[] = "(d.file_name LIKE ? OR d.remarks LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

// School filter
if (!empty($school)) {
    $whereConditions[] = "d.school_name = ?";
    $params[] = $school;
    $types .= "s";
}

// Build WHERE clause
$whereClause = "";
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Get total count for pagination
$countSql = "
    SELECT COUNT(*) AS total 
    FROM documents d
    " . (isAdmin() ? "JOIN users u ON d.user_id = u.id" : "") . "
    $whereClause
";

$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $limit);

// Main query with pagination
$sql = "
    SELECT d.*, " . (isAdmin() ? "u.name AS uploader" : "'N/A' AS uploader") . "
    FROM documents d
    " . (isAdmin() ? "JOIN users u ON d.user_id = u.id" : "") . "
    $whereClause
    ORDER BY d.uploaded_at DESC
    LIMIT ? OFFSET ?
";

// Add pagination parameters
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Statistics (updated to consider filters)
$totalDocsStmt = $conn->prepare("SELECT COUNT(*) AS total FROM documents WHERE status = 1" . (!isAdmin() ? " AND user_id = ?" : ""));
if (!isAdmin()) {
    $totalDocsStmt->bind_param("i", $_SESSION['user_id']);
}
$totalDocsStmt->execute();
$totalDocsResult = $totalDocsStmt->get_result();
$totalDocs = $totalDocsResult->fetch_assoc()['total'];

// My uploads
$myUploadsStmt = $conn->prepare("SELECT COUNT(*) AS total FROM documents WHERE user_id = ? AND status = 1");
$myUploadsStmt->bind_param("i", $_SESSION['user_id']);
$myUploadsStmt->execute();
$myUploadsResult = $myUploadsStmt->get_result();
$myUploads = $myUploadsResult->fetch_assoc()['total'];

// Total archived (admin only)
$totalArchived = 0;
if (isAdmin()) {
    $totalArchivedResult = $conn->query("SELECT COUNT(*) AS total FROM documents WHERE status = 0");
    $totalArchived = $totalArchivedResult->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management System | Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
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

        /* Stats Cards */
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
            border-left: 5px solid var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-card.active {
            border-left-color: var(--success);
        }

        .stat-card.my-uploads {
            border-left-color: var(--info);
        }

        .stat-card.archived {
            border-left-color: var(--warning);
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

        .stat-card .stat-icon {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }

        .stat-card.active .stat-icon {
            background: linear-gradient(135deg, var(--success) 0%, #3a9db1 100%);
        }

        .stat-card.my-uploads .stat-icon {
            background: linear-gradient(135deg, var(--info) 0%, #3a7bc8 100%);
        }

        .stat-card.archived .stat-icon {
            background: linear-gradient(135deg, var(--warning) 0%, #c2185b 100%);
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

        .btn-warning {
            background: linear-gradient(135deg, #ff9a00 0%, #ff5e00 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--warning) 0%, #c2185b 100%);
            color: white;
        }

        /* Search and Filter */
        .filter-section {
            padding: 1.5rem;
            background: var(--light);
            border-bottom: 1px solid var(--light-gray);
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto;
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
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
            background: rgba(67, 97, 238, 0.05);
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

        .badge-success {
            background: rgba(76, 201, 240, 0.2);
            color: #3a9db1;
        }

        .badge-primary {
            background: rgba(67, 97, 238, 0.2);
            color: var(--primary);
        }

        .badge-warning {
            background: rgba(247, 37, 133, 0.2);
            color: var(--warning);
        }

        .file-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .file-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            background: var(--light-gray);
            color: var(--dark);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn.delete:hover {
            background: var(--warning);
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
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .page-link.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Results Info */
        .results-info {
            padding: 1rem 1.5rem;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 8px;
            margin: 0 1.5rem 1rem 1.5rem;
            font-size: 0.9rem;
            color: var(--primary);
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
                    <?= isAdmin() ? 'Administrator' : 'User' ?>
                </div>
            </div>
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
            <div class="stat-card active">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $totalDocs ?></h3>
                    <p>Active Documents</p>
                </div>
            </div>
            
            <div class="stat-card my-uploads">
                <div class="stat-icon">
                    <i class="fas fa-upload"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $myUploads ?></h3>
                    <p>My Uploads</p>
                </div>
            </div>
            
            <?php if (isAdmin()): ?>
            <div class="stat-card archived">
                <div class="stat-icon">
                    <i class="fas fa-archive"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $totalArchived ?></h3>
                    <p>Archived Documents</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main Documents Card -->
        <div class="main-card">
            <div class="card-header">
                <h2><i class="fas fa-documents"></i> Document Management</h2>
                <div class="card-actions">
                    <?php if (isAdmin()): ?>
                    <a href="activity_logs.php" class="btn btn-secondary">
                        <i class="fas fa-history"></i> Activity Logs
                    </a>
                    <a href="archive.php" class="btn btn-warning">
                        <i class="fas fa-archive"></i> View Archive
                    </a>
                    <?php endif; ?>
                    <button onclick="openUpload()" class="btn btn-primary">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Document
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
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary" style="height: 46px;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <?php if ($search || $school): ?>
                        <a href="dashboard.php" class="btn btn-secondary" style="height: 46px; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>



            <!-- Documents Table -->
            <div class="table-container">
                <?php if ($result->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <?php if (isAdmin()): ?><th>Uploader</th><?php endif; ?>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Remarks</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="badge badge-primary">
                                    <i class="fas fa-school"></i> <?= htmlspecialchars($row['school_name']) ?>
                                </span>
                            </td>
                            <?php if (isAdmin()): ?>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 32px; height: 32px; background: rgba(67, 97, 238, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user" style="color: var(--primary);"></i>
                                    </div>
                                    <?= htmlspecialchars($row['uploader']) ?>
                                </div>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a href="<?= $row['file_path'] ?>" target="_blank" class="file-link">
                                    <i class="fas fa-file"></i>
                                    <?= $row['file_name'] ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <?= strtoupper($row['file_type']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($row['remarks']) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i>
                                    <?= date('M d, Y', strtotime($row['uploaded_at'])) ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--gray);">
                                    <?= date('h:i A', strtotime($row['uploaded_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn" onclick="openEdit(
                                        '<?= $row['id'] ?>',
                                        '<?= htmlspecialchars($row['school_name'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['remarks'], ENT_QUOTES) ?>'
                                    )" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="delete_document.php?id=<?= $row['id'] ?>" 
                                       class="action-btn delete" 
                                       onclick="return confirm('Are you sure you want to archive this document?')"
                                       title="Archive">
                                        <i class="fas fa-archive"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-excel"></i>
                    <h3>No documents found</h3>
                    <p>There are no documents matching your search criteria.</p>
                    <?php if ($search || $school): ?>
                    <a href="dashboard.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <?php else: ?>
                    <button onclick="openUpload()" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Your First Document
                    </button>
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

    <!-- Footer -->
    <div class="footer">
        <p>Record Management System  <?= date('Y') ?></p>
        <p style="font-size: 0.8rem; margin-top: 0.5rem;">
            <i class="fas fa-shield-alt"></i> Secure Document Management
        </p>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Document</h3>
                <button class="modal-close" onclick="closeEdit()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="edit_document.php">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="form-group">
                        <label>School</label>
                        <select name="school_name" id="edit_school" class="form-control" required>
                            <?php foreach ($schools as $schoolOption): ?>
                                <option value="<?= htmlspecialchars($schoolOption) ?>">
                                    <?= htmlspecialchars($schoolOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" id="edit_remarks" class="form-control" rows="4" placeholder="Add remarks about this document..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" onclick="closeEdit()" class="btn btn-secondary" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-cloud-upload-alt"></i> Upload Document</h3>
                <button class="modal-close" onclick="closeUpload()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="upload_process.php" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>School</label>
                        <select name="school_name" class="form-control" required>
                            <option value="">-- Select School --</option>
                            <?php foreach ($schools as $schoolOption): ?>
                                <option value="<?= htmlspecialchars($schoolOption) ?>">
                                    <?= htmlspecialchars($schoolOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Document File</label>
                        <div style="border: 2px dashed var(--light-gray); border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer;" 
                             onclick="document.querySelector('#fileInput').click()" 
                             id="dropZone">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--gray); margin-bottom: 1rem;"></i>
                            <p>Click to select or drag and drop</p>
                            <p style="font-size: 0.85rem; color: var(--gray);">Max file size: 10MB</p>
                            <input type="file" name="document" id="fileInput" required 
                                   style="display: none;" 
                                   onchange="document.querySelector('#fileName').textContent = this.files[0].name">
                            <div id="fileName" style="margin-top: 1rem; font-weight: 600;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4" placeholder="Add remarks about this document..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-upload"></i> Upload Document
                        </button>
                        <button type="button" onclick="closeUpload()" class="btn btn-secondary" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions
        function openEdit(id, school, remarks) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_school').value = school;
            document.getElementById('edit_remarks').value = remarks;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEdit() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openUpload() {
            document.getElementById('uploadModal').style.display = 'flex';
        }

        function closeUpload() {
            document.getElementById('uploadModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Drag and drop for file upload
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                dropZone.style.borderColor = 'var(--primary)';
                dropZone.style.background = 'rgba(67, 97, 238, 0.05)';
            });
            
            dropZone.addEventListener('dragleave', function() {
                dropZone.style.borderColor = 'var(--light-gray)';
                dropZone.style.background = '';
            });
            
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                dropZone.style.borderColor = 'var(--primary)';
                dropZone.style.background = 'rgba(67, 97, 238, 0.05)';
                
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    document.querySelector('#fileName').textContent = e.dataTransfer.files[0].name;
                }
            });
        });

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
        });
    </script>
</body>
</html>