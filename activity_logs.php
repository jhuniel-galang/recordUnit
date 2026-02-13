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
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$whereConditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $whereConditions[] = "(al.description LIKE ? OR u.name LIKE ? OR al.action LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

if (!empty($action_filter)) {
    $whereConditions[] = "al.action = ?";
    $params[] = $action_filter;
    $types .= "s";
}

if (!empty($user_filter)) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $user_filter;
    $types .= "i";
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$whereClause = "";
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) AS total FROM activity_logs al JOIN users u ON al.user_id = u.id $whereClause";
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
    SELECT al.*, u.name, u.email 
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!empty($params_pagination)) {
    $stmt->bind_param($types_pagination, ...$params_pagination);
}
$stmt->execute();
$result = $stmt->get_result();

// Get unique actions for filter dropdown
$actions_result = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actions = [];
while ($row = $actions_result->fetch_assoc()) {
    $actions[] = $row['action'];
}

// Get all users for filter dropdown
$users_result = $conn->query("SELECT id, name FROM users ORDER BY name");
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Get total logs count for stats
$total_logs = $conn->query("SELECT COUNT(*) as count FROM activity_logs")->fetch_assoc()['count'];
$today_logs = $conn->query("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
$unique_users = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM activity_logs")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs | Document Management System</title>
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
            border-left: 5px solid var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-card.total-logs {
            border-left-color: var(--primary);
        }

        .stat-card.today-logs {
            border-left-color: var(--success);
        }

        .stat-card.unique-users {
            border-left-color: var(--info);
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

        .stat-card.total-logs .stat-icon {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }

        .stat-card.today-logs .stat-icon {
            background: linear-gradient(135deg, var(--success) 0%, #3a9db1 100%);
        }

        .stat-card.unique-users .stat-icon {
            background: linear-gradient(135deg, var(--info) 0%, #3a7bc8 100%);
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

        .badge-login {
            background: rgba(76, 201, 240, 0.2);
            color: #3a9db1;
        }

        .badge-logout {
            background: rgba(247, 37, 133, 0.2);
            color: var(--warning);
        }

        .badge-create {
            background: rgba(67, 97, 238, 0.2);
            color: var(--primary);
        }

        .badge-update {
            background: rgba(255, 154, 0, 0.2);
            color: #ff5e00;
        }

        .badge-delete {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .badge-upload {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .badge-download {
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
            background: rgba(67, 97, 238, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
        }

        .description-cell {
            max-width: 300px;
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
            background: rgba(67, 97, 238, 0.1);
            border-radius: 8px;
            margin: 0 1.5rem 1rem 1.5rem;
            font-size: 0.9rem;
            color: var(--primary);
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
            <div class="stat-card total-logs">
                <div class="stat-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($total_logs) ?></h3>
                    <p>Total Logs</p>
                </div>
            </div>
            
            <div class="stat-card today-logs">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($today_logs) ?></h3>
                    <p>Today's Activities</p>
                </div>
            </div>
            
            <div class="stat-card unique-users">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($unique_users) ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
        </div>

        <!-- Main Activity Logs Card -->
        <div class="main-card">
            <div class="card-header">
                <h2><i class="fas fa-clipboard-list"></i> System Activity Logs</h2>
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
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search in description, user, or action..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> User</label>
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
                        <label><i class="fas fa-bolt"></i> Action</label>
                        <select name="action" class="form-control">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?= htmlspecialchars($action) ?>" 
                                    <?= $action_filter == $action ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($action) ?>
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

            <?php if ($search || $action_filter || $user_filter || $date_from || $date_to): ?>
            <div class="results-info">
                <i class="fas fa-info-circle"></i>
                Showing <?= $result->num_rows ?> of <?= $totalCount ?> logs matching your criteria
                <?php if ($search || $action_filter || $user_filter || $date_from || $date_to): ?>
                | <a href="activity_logs.php" style="color: var(--primary); text-decoration: none;">
                    <i class="fas fa-times"></i> Clear all filters
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Activity Logs Table -->
            <div class="table-container">
                <?php if ($result->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): 
                            // Get badge class based on action
                            $badge_class = 'badge-' . strtolower($row['action']);
                            if (!in_array($badge_class, ['badge-login', 'badge-logout', 'badge-create', 'badge-update', 'badge-delete', 'badge-upload', 'badge-download'])) {
                                $badge_class = 'badge-primary';
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="user-info-cell">
                                    <div class="user-avatar-small">
                                        <?= strtoupper(substr($row['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($row['name']) ?></strong><br>
                                        <small style="color: var(--gray);"><?= htmlspecialchars($row['email'] ?? '') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $badge_class ?>">
                                    <i class="fas fa-<?= getActionIcon($row['action']) ?>"></i>
                                    <?= htmlspecialchars($row['action']) ?>
                                </span>
                            </td>
                            <td class="description-cell" title="<?= htmlspecialchars($row['description']) ?>">
                                <?= htmlspecialchars($row['description']) ?>
                            </td>
                            <td>
                                <div class="timestamp">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= date('M d, Y', strtotime($row['created_at'])) ?>
                                    <br>
                                    <i class="fas fa-clock"></i>
                                    <?= date('h:i A', strtotime($row['created_at'])) ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No activity logs found</h3>
                    <p>There are no logs matching your search criteria.</p>
                    <?php if ($search || $action_filter || $user_filter || $date_from || $date_to): ?>
                    <a href="activity_logs.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-times"></i> Clear Filters
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

    <!-- Footer -->
    <div class="footer">
        <p>Record Management System <?= date('Y') ?></p>
        <p style="font-size: 0.8rem; margin-top: 0.5rem;">
            <i class="fas fa-history"></i> Activity Monitoring System
        </p>
    </div>

    <script>
        function clearFilters() {
            window.location.href = 'activity_logs.php';
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
// Helper function to get icon based on action
function getActionIcon($action) {
    $action_lower = strtolower($action);
    
    $icons = [
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'create' => 'plus-circle',
        'update' => 'edit',
        'delete' => 'trash-alt',
        'download' => 'download',
        'upload' => 'upload',
        'archive' => 'archive',
        'restore' => 'history',
        'view' => 'eye',
        'print' => 'print'
    ];
    
    return $icons[$action_lower] ?? 'history';
}
?>