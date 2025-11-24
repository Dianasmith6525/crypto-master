<?php
require_once 'includes/config.php';

// Require admin access
require_admin();

set_security_headers();
set_cors_headers();

$admin_name = $_SESSION['user_name'] ?? 'Admin';
$admin_email = $_SESSION['user_email'] ?? '';

// Get platform statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Active users (logged in within last 30 days)
$result = $conn->query("SELECT COUNT(*) as active FROM users WHERE role = 'user' AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['active_users'] = $result->fetch_assoc()['active'];

// New users (registered in last 7 days)
$result = $conn->query("SELECT COUNT(*) as new_users FROM users WHERE role = 'user' AND date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['new_users'] = $result->fetch_assoc()['new_users'];

// Total portfolio holdings value
$result = $conn->query("SELECT SUM(quantity * entry_price) as total_value FROM portfolio_holdings");
$row = $result->fetch_assoc();
$stats['total_portfolio_value'] = $row['total_value'] ?? 0;

// Total price alerts
$result = $conn->query("SELECT COUNT(*) as total FROM price_alerts WHERE is_active = TRUE");
$stats['total_alerts'] = $result->fetch_assoc()['total'];

// Total trades
$result = $conn->query("SELECT COUNT(*) as total FROM trade_history");
$stats['total_trades'] = $result->fetch_assoc()['total'];

// Newsletter subscribers
$result = $conn->query("SELECT COUNT(*) as total FROM newsletter_subscribers WHERE is_subscribed = TRUE");
$stats['newsletter_subscribers'] = $result->fetch_assoc()['total'];

// Trading bots
$result = $conn->query("SELECT COUNT(*) as total FROM trading_bots WHERE is_active = TRUE");
$stats['active_bots'] = $result->fetch_assoc()['total'];

// Recent users (last 10)
$recent_users = [];
$result = $conn->query("SELECT user_id, email, full_name, date_created, last_login, status FROM users WHERE role = 'user' ORDER BY date_created DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_users[] = $row;
}

// Most popular cryptocurrencies
$popular_cryptos = [];
$result = $conn->query("SELECT crypto_symbol, crypto_name, COUNT(*) as holders, SUM(quantity) as total_quantity FROM portfolio_holdings GROUP BY crypto_id, crypto_symbol, crypto_name ORDER BY holders DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $popular_cryptos[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>Admin Dashboard - Crypto Trading Platform</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
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
        
        .admin-navbar .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.15);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.25);
        }
        
        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stat-card.purple::before { background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
        .stat-card.red::before { background: linear-gradient(90deg, #ef4444, #dc2626); }
        
        .stat-card h3 {
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 16px;
            letter-spacing: 1.2px;
        }
        
        .stat-card .value {
            color: #ffffff;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -1px;
        }
        
        .stat-card .label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            font-weight: 500;
        }
        
        .stat-card .icon {
            position: absolute;
            top: 24px;
            right: 24px;
            font-size: 44px;
            color: rgba(255, 255, 255, 0.1);
        }
        
        .content-section {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 36px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .section-header h2 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            letter-spacing: -0.5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.5);
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
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <div class="admin-navbar">
        <div class="logo">
            <i class="fa fa-shield"></i> Admin Panel
        </div>
        <div class="nav-links">
            <a href="admin-dashboard.php" class="active"><i class="fa fa-tachometer"></i> Dashboard</a>
            <a href="admin-users.php"><i class="fa fa-users"></i> Users</a>
            <a href="admin-withdrawals.php"><i class="fa fa-arrow-up"></i> Withdrawals</a>
            <a href="admin-portfolios.php"><i class="fa fa-briefcase"></i> Portfolios</a>
            <a href="admin-alerts.php"><i class="fa fa-bell"></i> Alerts</a>
            <a href="admin-logs.php"><i class="fa fa-file-text"></i> Logs</a>
            <a href="admin-settings.php"><i class="fa fa-cog"></i> Settings</a>
        </div>
        <div class="user-info">
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
            <h1>📊 Platform Overview</h1>
            <p>Monitor and manage your cryptocurrency trading platform</p>
        </div>
        
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <i class="fa fa-users icon"></i>
                <h3>Total Users</h3>
                <div class="value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="label"><?php echo number_format($stats['active_users']); ?> active this month</div>
            </div>
            
            <div class="stat-card green">
                <i class="fa fa-user-plus icon"></i>
                <h3>New Users (7 days)</h3>
                <div class="value"><?php echo number_format($stats['new_users']); ?></div>
                <div class="label">Recent registrations</div>
            </div>
            
            <div class="stat-card purple">
                <i class="fa fa-money icon"></i>
                <h3>Total Portfolio Value</h3>
                <div class="value">$<?php echo number_format($stats['total_portfolio_value'], 2); ?></div>
                <div class="label">Across all users</div>
            </div>
            
            <div class="stat-card orange">
                <i class="fa fa-bell icon"></i>
                <h3>Active Alerts</h3>
                <div class="value"><?php echo number_format($stats['total_alerts']); ?></div>
                <div class="label">Price alerts set</div>
            </div>
            
            <div class="stat-card red">
                <i class="fa fa-exchange icon"></i>
                <h3>Total Trades</h3>
                <div class="value"><?php echo number_format($stats['total_trades']); ?></div>
                <div class="label">Trade history records</div>
            </div>
            
            <div class="stat-card">
                <i class="fa fa-envelope icon"></i>
                <h3>Newsletter</h3>
                <div class="value"><?php echo number_format($stats['newsletter_subscribers']); ?></div>
                <div class="label">Active subscribers</div>
            </div>
            
            <div class="stat-card green">
                <i class="fa fa-robot icon"></i>
                <h3>Trading Bots</h3>
                <div class="value"><?php echo number_format($stats['active_bots']); ?></div>
                <div class="label">Active bots running</div>
            </div>
            
            <div class="stat-card blue">
                <i class="fa fa-user-secret icon"></i>
                <h3>Admin Users</h3>
                <div class="value">
                    <?php
                    $admin_count = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'")->fetch_assoc()['total'];
                    echo $admin_count;
                    ?>
                </div>
                <div class="label">System administrators</div>
            </div>
        </div>
        
        <!-- Popular Cryptocurrencies -->
        <div class="content-section">
            <div class="section-header">
                <h2>Most Popular Cryptocurrencies</h2>
            </div>
            
            <?php if (count($popular_cryptos) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Cryptocurrency</th>
                            <th>Symbol</th>
                            <th>Total Holders</th>
                            <th>Total Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popular_cryptos as $crypto): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($crypto['crypto_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($crypto['crypto_symbol']); ?></td>
                                <td><?php echo number_format($crypto['holders']); ?> users</td>
                                <td><?php echo number_format($crypto['total_quantity'], 8); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px;">No portfolio data yet</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Users -->
        <div class="content-section">
            <div class="section-header">
                <h2>Recent User Registrations</h2>
                <a href="admin-users.php" class="btn-primary">View All Users</a>
            </div>
            
            <?php if (count($recent_users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Full Name</th>
                            <th>Registered</th>
                            <th>Last Login</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['date_created'])); ?></td>
                                <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <?php
                                    $status_class = $user['status'] === 'active' ? 'success' : ($user['status'] === 'suspended' ? 'danger' : 'warning');
                                    echo "<span class='badge badge-$status_class'>" . ucfirst($user['status']) . "</span>";
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px;">No users yet</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="js/jquery-3.2.1.min.js"></script>
</body>
</html>
