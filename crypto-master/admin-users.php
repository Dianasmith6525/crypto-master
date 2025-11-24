<?php
require_once 'includes/config.php';

// Require admin access
require_admin();

set_security_headers();
set_cors_headers();

$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT user_id, email, full_name, phone, country, date_created, last_login, status, email_verified, two_factor_enabled 
        FROM users 
        WHERE role = 'user'";

$params = [];
$types = '';

if ($filter_status !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (email LIKE ? OR full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$sql .= " ORDER BY date_created DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Get user statistics
$total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'")->fetch_assoc()['total'];
$active_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user' AND status = 'active'")->fetch_assoc()['total'];
$suspended_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user' AND status = 'suspended'")->fetch_assoc()['total'];
$verified_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user' AND email_verified = TRUE")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>User Management - Admin Panel</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            background-attachment: fixed;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(255, 215, 0, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(102, 126, 234, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .admin-navbar {
            background: rgba(26, 26, 46, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            color: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .admin-navbar .logo {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .admin-navbar .logo i {
            margin-right: 12px;
            color: #ffd700;
            font-size: 26px;
            filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.4));
        }
        
        .admin-navbar .nav-links {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .admin-navbar .nav-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .admin-navbar .nav-links a.active {
            background: rgba(255, 215, 0, 0.15);
            color: #ffd700;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.2);
        }
        
        .admin-navbar .nav-links a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            opacity: 1;
        }
        
        .admin-navbar .btn-logout {
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b81;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            border: 1.5px solid rgba(220, 53, 69, 0.3);
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .admin-navbar .btn-logout:hover {
            background: rgba(220, 53, 69, 0.25);
            border-color: rgba(220, 53, 69, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 53, 69, 0.3);
        }
        
        .admin-container {
            padding: 40px;
            max-width: 1500px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .page-header {
            margin-bottom: 36px;
        }
        
        .page-header h1 {
            color: #ffffff;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -1px;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
        }
        
        .page-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
            font-weight: 400;
        }
        
        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 36px;
        }
        
        .stat-box {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 20px 24px;
            border-radius: 16px;
            flex: 1;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.3);
        }
        
        .stat-box h4 {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            margin: 0 0 12px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .stat-box .value {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .filters {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .filters form {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        
        .filters input, .filters select {
            padding: 12px 16px;
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .filters input:focus, .filters select:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }
        
        .filters input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .filters select option {
            background: #1a1a2e;
            color: #ffffff;
        }
        
        .filters input[type="text"] {
            flex: 1;
        }
        
        .filters button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }
        
        .filters button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.5);
        }
        
        .content-section {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        table th {
            background: rgba(255, 255, 255, 0.05);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            border: none;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        table td {
            padding: 18px 16px;
            border: none;
            background: rgba(255, 255, 255, 0.03);
            color: #ffffff;
        }
        
        table tbody tr {
            transition: all 0.3s ease;
        }
        
        table tbody tr:hover {
            transform: scale(1.01);
        }
        
        table tbody tr:hover td {
            background: rgba(255, 255, 255, 0.08);
        }
        
        table tbody tr td:first-child {
            border-radius: 12px 0 0 12px;
        }
        
        table tbody tr td:last-child {
            border-radius: 0 12px 12px 0;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        .btn-action {
            padding: 5px 10px;
            margin: 0 3px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-view { background: #2196F3; color: white; }
        .btn-suspend { background: #FF9800; color: white; }
        .btn-activate { background: #4CAF50; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: none;
        }
        
        .alert.show {
            display: block;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <div class="admin-navbar">
        <div class="logo">
            <i class="fa fa-shield"></i> Admin Panel
        </div>
        <div class="nav-links">
            <a href="admin-dashboard.php"><i class="fa fa-tachometer"></i> Dashboard</a>
            <a href="admin-users.php" class="active"><i class="fa fa-users"></i> Users</a>
            <a href="admin-portfolios.php"><i class="fa fa-briefcase"></i> Portfolios</a>
            <a href="admin-alerts.php"><i class="fa fa-bell"></i> Alerts</a>
            <a href="admin-logs.php"><i class="fa fa-file-text"></i> Logs</a>
            <a href="admin-settings.php"><i class="fa fa-cog"></i> Settings</a>
        </div>
        <div>
            <span><?php echo htmlspecialchars($admin_name); ?></span>
            <a href="api/login.php?action=logout" class="btn-logout">
                <i class="fa fa-sign-out"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Container -->
    <div class="admin-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>👥 User Management</h1>
            <p>View and manage all platform users</p>
        </div>
        
        <!-- Statistics Bar -->
        <div class="stats-bar">
            <div class="stat-box">
                <h4>Total Users</h4>
                <div class="value"><?php echo number_format($total_users); ?></div>
            </div>
            <div class="stat-box">
                <h4>Active</h4>
                <div class="value" style="color: #4CAF50;"><?php echo number_format($active_users); ?></div>
            </div>
            <div class="stat-box">
                <h4>Suspended</h4>
                <div class="value" style="color: #dc3545;"><?php echo number_format($suspended_users); ?></div>
            </div>
            <div class="stat-box">
                <h4>Email Verified</h4>
                <div class="value" style="color: #2196F3;"><?php echo number_format($verified_users); ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET">
                <input type="text" name="search" placeholder="Search by email or name..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="status">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
                <button type="submit"><i class="fa fa-search"></i> Filter</button>
                <a href="admin-users.php" style="padding: 10px 15px; background: #ccc; color: #333; text-decoration: none; border-radius: 5px;">Clear</a>
            </form>
        </div>
        
        <div id="alertBox" class="alert"></div>
        
        <!-- Users Table -->
        <div class="content-section">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Full Name</th>
                        <th>Country</th>
                        <th>Registered</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th>2FA</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?php echo $user['user_id']; ?>">
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['country'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['date_created'])); ?></td>
                                <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <?php
                                    $status_class = $user['status'] === 'active' ? 'success' : ($user['status'] === 'suspended' ? 'danger' : 'warning');
                                    echo "<span class='badge badge-$status_class'>" . ucfirst($user['status']) . "</span>";
                                    ?>
                                </td>
                                <td>
                                    <?php echo $user['email_verified'] ? "<span class='badge badge-success'>Yes</span>" : "<span class='badge badge-warning'>No</span>"; ?>
                                </td>
                                <td>
                                    <?php echo $user['two_factor_enabled'] ? "<span class='badge badge-info'>Enabled</span>" : "<span class='badge badge-warning'>Disabled</span>"; ?>
                                </td>
                                <td>
                                    <button class="btn-action btn-view" onclick="viewUser(<?php echo $user['user_id']; ?>)">
                                        <i class="fa fa-eye"></i> View
                                    </button>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <button class="btn-action btn-suspend" onclick="suspendUser(<?php echo $user['user_id']; ?>)">
                                            <i class="fa fa-ban"></i> Suspend
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-action btn-activate" onclick="activateUser(<?php echo $user['user_id']; ?>)">
                                            <i class="fa fa-check"></i> Activate
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-action btn-delete" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; color: #999; padding: 30px;">
                                No users found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="js/jquery-3.2.1.min.js"></script>
    <script>
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        
        function showAlert(message, type) {
            const alertBox = $('#alertBox');
            alertBox.removeClass('alert-success alert-error');
            alertBox.addClass(`alert alert-${type} show`);
            alertBox.text(message);
            
            setTimeout(() => {
                alertBox.removeClass('show');
            }, 5000);
        }
        
        function viewUser(userId) {
            window.location.href = `admin-user-details.php?id=${userId}`;
        }
        
        function suspendUser(userId) {
            if (!confirm('Are you sure you want to suspend this user?')) return;
            
            $.post('api/admin.php', {
                action: 'update_user_status',
                user_id: userId,
                status: 'suspended',
                csrf_token: csrfToken
            }, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }).fail(function() {
                showAlert('Error updating user status', 'error');
            });
        }
        
        function activateUser(userId) {
            if (!confirm('Are you sure you want to activate this user?')) return;
            
            $.post('api/admin.php', {
                action: 'update_user_status',
                user_id: userId,
                status: 'active',
                csrf_token: csrfToken
            }, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }).fail(function() {
                showAlert('Error updating user status', 'error');
            });
        }
        
        function deleteUser(userId) {
            if (!confirm('Are you sure you want to DELETE this user? This action cannot be undone!')) return;
            
            const confirm2 = prompt('Type DELETE to confirm:');
            if (confirm2 !== 'DELETE') {
                showAlert('Deletion cancelled', 'error');
                return;
            }
            
            $.post('api/admin.php', {
                action: 'delete_user',
                user_id: userId,
                csrf_token: csrfToken
            }, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }).fail(function() {
                showAlert('Error deleting user', 'error');
            });
        }
    </script>
</body>
</html>
