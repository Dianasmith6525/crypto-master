<?php
require_once 'includes/config.php';

// Require admin access
require_admin();

set_security_headers();
set_cors_headers();

$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Get filter parameters
$log_type = $_GET['type'] ?? 'all';
$limit = intval($_GET['limit'] ?? 50);

// Get admin activity logs
$admin_logs = [];
$sql = "SELECT al.*, u.email, u.full_name 
        FROM admin_activity_logs al 
        JOIN users u ON al.admin_id = u.user_id 
        ORDER BY al.created_at DESC 
        LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $limit);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $admin_logs[] = $row;
}

// Get security logs
$security_logs = [];
$sql = "SELECT * FROM security_logs ORDER BY created_at DESC LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $limit);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $security_logs[] = $row;
}

// Get API activity logs
$api_logs = [];
$sql = "SELECT * FROM api_logs ORDER BY timestamp DESC LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $limit);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $api_logs[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>System Logs - Admin Panel</title>
    
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
        
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.05);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            font-size: 14px;
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
            font-weight: 600;
        }
        
        .tab-btn:hover:not(.active) {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
        
        .content-section h2 {
            color: #ffffff;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        
        .log-entry {
            padding: 20px;
            border-left: 4px solid #667eea;
            background: rgba(255, 255, 255, 0.05);
            margin-bottom: 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .log-entry:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(4px);
        }
        
        .log-entry.success { border-left-color: #10b981; }
        .log-entry.warning { border-left-color: #f59e0b; }
        .log-entry.error { border-left-color: #ef4444; }
        .log-entry.info { border-left-color: #3b82f6; }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        
        .log-action {
            font-weight: 600;
            color: #ffffff;
            font-size: 15px;
        }
        
        .log-time {
            color: rgba(255, 255, 255, 0.5);
            font-size: 13px;
            font-weight: 500;
        }
        
        .log-details {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-top: 8px;
            line-height: 1.6;
        }
        
        .log-meta {
            display: flex;
            gap: 20px;
            margin-top: 12px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .log-meta i {
            margin-right: 6px;
        }
        
        .empty-logs {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
            font-size: 13px;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        
        table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .badge-success { 
            background: rgba(16, 185, 129, 0.15); 
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .badge-error { 
            background: rgba(239, 68, 68, 0.15); 
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .badge-warning { 
            background: rgba(245, 158, 11, 0.15); 
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .badge-info { 
            background: rgba(59, 130, 246, 0.15); 
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
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
            <a href="admin-users.php"><i class="fa fa-users"></i> Users</a>
            <a href="admin-portfolios.php"><i class="fa fa-briefcase"></i> Portfolios</a>
            <a href="admin-alerts.php"><i class="fa fa-bell"></i> Alerts</a>
            <a href="admin-logs.php" class="active"><i class="fa fa-file-text"></i> Logs</a>
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
            <h1>📝 System Logs</h1>
            <p>Monitor platform activity, security events, and admin actions</p>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('admin')">
                <i class="fa fa-shield"></i> Admin Activity
            </button>
            <button class="tab-btn" onclick="showTab('security')">
                <i class="fa fa-lock"></i> Security Logs
            </button>
            <button class="tab-btn" onclick="showTab('api')">
                <i class="fa fa-exchange"></i> API Activity
            </button>
        </div>
        
        <!-- Admin Activity Logs -->
        <div id="admin-tab" class="tab-content active">
            <div class="content-section">
                <h2 style="margin-bottom: 20px;">Admin Activity Logs</h2>
                
                <?php if (count($admin_logs) > 0): ?>
                    <?php foreach ($admin_logs as $log): ?>
                        <div class="log-entry">
                            <div class="log-header">
                                <div class="log-action">
                                    <i class="fa fa-user-secret"></i>
                                    <?php echo htmlspecialchars($log['full_name']); ?> - 
                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($log['action']))); ?>
                                </div>
                                <div class="log-time">
                                    <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                </div>
                            </div>
                            <?php if ($log['details']): ?>
                                <div class="log-details">
                                    <?php echo htmlspecialchars($log['details']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="log-meta">
                                <span><i class="fa fa-envelope"></i> <?php echo htmlspecialchars($log['email']); ?></span>
                                <span><i class="fa fa-globe"></i> <?php echo htmlspecialchars($log['ip_address']); ?></span>
                                <?php if ($log['target_type']): ?>
                                    <span><i class="fa fa-tag"></i> <?php echo ucfirst($log['target_type']); ?> ID: <?php echo $log['target_id']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-logs">
                        <i class="fa fa-info-circle" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>No admin activity logs yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Security Logs -->
        <div id="security-tab" class="tab-content">
            <div class="content-section">
                <h2 style="margin-bottom: 20px;">Security Event Logs</h2>
                
                <?php if (count($security_logs) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>User/IP</th>
                                <th>Details</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($security_logs as $log): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars(str_replace('_', ' ', ucwords($log['event_type']))); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['user_identifier']); ?></td>
                                    <td><?php echo htmlspecialchars($log['event_data'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-logs">
                        <i class="fa fa-shield" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>No security logs yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- API Activity Logs -->
        <div id="api-tab" class="tab-content">
            <div class="content-section">
                <h2 style="margin-bottom: 20px;">API Activity Logs</h2>
                
                <?php if (count($api_logs) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Endpoint</th>
                                <th>Method</th>
                                <th>User ID</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($api_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['endpoint']); ?></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($log['method']); ?></span></td>
                                    <td><?php echo $log['user_id'] ?? 'N/A'; ?></td>
                                    <td>
                                        <?php
                                        $status_class = $log['response_code'] < 300 ? 'success' : ($log['response_code'] < 500 ? 'warning' : 'error');
                                        echo "<span class='badge badge-$status_class'>{$log['response_code']}</span>";
                                        ?>
                                    </td>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-logs">
                        <i class="fa fa-exchange" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>No API activity logs yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="js/jquery-3.2.1.min.js"></script>
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
