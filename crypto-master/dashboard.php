<?php
require_once 'includes/config.php';

// Check authentication
if (!is_user_authenticated()) {
    header('Location: login.html');
    exit;
}

set_security_headers();
set_cors_headers();

$user_id = get_user_id();
$user_email = $_SESSION['user_email'] ?? 'user@email.com';
$user_name = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>Dashboard - Crypto Trading Platform</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/themify-icons.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.02" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            background-position: bottom;
            pointer-events: none;
            z-index: 0;
        }
        
        .navbar-dashboard {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 18px 40px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .navbar-dashboard .logo {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
        }
        
        .navbar-dashboard .user-menu {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        
        .navbar-dashboard .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .navbar-dashboard .user-info:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .navbar-dashboard .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .navbar-dashboard .btn-logout {
            padding: 10px 24px;
            background: rgba(220, 53, 69, 0.15);
            border: 1.5px solid rgba(220, 53, 69, 0.3);
            color: #ff6b81;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        
        .navbar-dashboard .btn-logout:hover {
            background: rgba(220, 53, 69, 0.25);
            border-color: rgba(220, 53, 69, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .dashboard-container {
            padding: 40px;
            max-width: 1400px;
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
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 36px;
        }
        
        .summary-card {
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
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.25);
        }
        
        .summary-card h3 {
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 16px;
            letter-spacing: 1.2px;
        }
        
        .summary-card .value {
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        
        .summary-card .change {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }
        
        .summary-card .change.positive {
            color: #10b981;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
        }
        
        .summary-card .change.negative {
            color: #ef4444;
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.3);
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
            transition: all 0.3s ease;
        }
        
        .content-section:hover {
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.3);
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
        
        .btn-add {
            padding: 12px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }
        
        .btn-add:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.5);
            color: white;
        }
        
        .btn-add:active {
            transform: translateY(-2px);
        }
        
        .holdings-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        .holdings-table th {
            background: rgba(255, 255, 255, 0.05);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            border: none;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: sticky;
            top: 0;
        }
        
        .holdings-table td {
            padding: 18px 16px;
            border: none;
            background: rgba(255, 255, 255, 0.03);
            color: #ffffff;
        }
        
        .holdings-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .holdings-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: scale(1.01);
        }
        
        .holdings-table tbody tr:hover td {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .holdings-table tbody tr td:first-child {
            border-radius: 12px 0 0 12px;
        }
        
        .holdings-table tbody tr td:last-child {
            border-radius: 0 12px 12px 0;
        }
        
        .crypto-name {
            font-weight: 600;
            color: #ffffff;
            font-size: 15px;
        }
        
        .crypto-symbol {
            color: rgba(255, 255, 255, 0.5);
            font-size: 13px;
            margin-left: 8px;
            font-weight: 500;
        }
        
        .text-right {
            text-align: right;
        }
        
        .btn-action {
            padding: 8px 16px;
            margin: 0 4px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-edit {
            background: rgba(33, 150, 243, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        
        .btn-edit:hover {
            background: rgba(33, 150, 243, 0.25);
            border-color: rgba(33, 150, 243, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }
        
        .btn-delete {
            background: rgba(220, 53, 69, 0.15);
            color: #ef4444;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .btn-delete:hover {
            background: rgba(220, 53, 69, 0.25);
            border-color: rgba(220, 53, 69, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .empty-state p {
            margin-bottom: 24px;
            font-size: 16px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            padding: 40px;
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: modalSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .modal-header h3 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        
        .close-modal {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 28px;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            transform: rotate(90deg);
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 14px 18px;
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 15px;
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }
        
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.6);
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px;
            }
            
            .navbar-dashboard {
                padding: 15px 20px;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }
        
        .modal-close {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }
        
        .btn-cancel {
            padding: 10px 20px;
            background: #e0e0e0;
            color: #333;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .btn-submit {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
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
    <!-- Navigation -->
    <div class="navbar-dashboard">
        <div class="logo">📊 Crypto Portfolio</div>
        <div class="user-menu">
            <a href="wallet.php" class="alerts-link" style="background: rgba(102, 126, 234, 0.15); padding: 8px 16px; border-radius: 10px; border: 1px solid rgba(102, 126, 234, 0.3);">💰 Wallet</a>
            <a href="withdrawal.php" class="alerts-link" style="background: rgba(102, 126, 234, 0.15); padding: 8px 16px; border-radius: 10px; border: 1px solid rgba(102, 126, 234, 0.3);">⬆️ Withdraw</a>
            <a href="ai-trading-bot.php" class="alerts-link" style="background: rgba(102, 126, 234, 0.15); padding: 8px 16px; border-radius: 10px; border: 1px solid rgba(102, 126, 234, 0.3);">🤖 AI Bots</a>
            <a href="transaction-history.php" class="alerts-link" style="background: rgba(102, 126, 234, 0.15); padding: 8px 16px; border-radius: 10px; border: 1px solid rgba(102, 126, 234, 0.3);">📜 Transactions</a>
            <a href="watchlist.html" class="alerts-link">⭐ Watchlist</a>
            <a href="alerts.html" class="alerts-link">🔔 Alerts</a>
            <a href="referrals.html" class="alerts-link">💰 Referrals</a>
            <a href="charts.html" class="alerts-link">📊 Charts</a>
            <a href="analytics.html" class="alerts-link">📈 Analytics</a>
            <a href="news.html" class="alerts-link">📰 News</a>
            <a href="two-factor-auth.html" class="alerts-link">🔒 2FA</a>
            <a href="email-log.html" class="alerts-link">📧 Emails</a>
            <div class="user-info">
                <div class="user-avatar" id="userInitial"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <div>
                    <span id="userName"><?php echo htmlspecialchars($user_name); ?></span>
                    <div class="user-email-small" id="userEmail"><?php echo htmlspecialchars($user_email); ?></div>
                </div>
            </div>
            <a href="#" class="btn-logout" onclick="logout_user(); return false;">Logout</a>
        </div>
    </div>
    
    <!-- Main Container -->
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Welcome back! 👋</h1>
            <p>Track and manage your cryptocurrency portfolio</p>
        </div>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card" style="border-left-color: #10b981;">
                <h3>Wallet Balance</h3>
                <div class="value" id="walletBalance">$0.00</div>
                <div class="change">
                    <a href="wallet.php" style="color: #10b981; text-decoration: none; font-weight: 600;">
                        <i class="fa fa-plus-circle"></i> Deposit Crypto
                    </a>
                </div>
            </div>
            <div class="summary-card" style="border-left-color: #667eea;">
                <h3>AI Trading Bots</h3>
                <div class="value" id="activeBots">0</div>
                <div class="change" id="botStatus">
                    <a href="ai-trading-bot.php" style="color: #667eea; text-decoration: none; font-weight: 600;">
                        <i class="fa fa-robot"></i> Manage Bots
                    </a>
                </div>
            </div>
            <div class="summary-card">
                <h3>Total Invested</h3>
                <div class="value" id="totalInvested">$0.00</div>
                <div class="change" id="investedChange">In 0 cryptocurrencies</div>
            </div>
            <div class="summary-card">
                <h3>Bot Performance</h3>
                <div class="value" id="botPL">$0.00</div>
                <div class="change" id="botWinRate">0% win rate</div>
            </div>
        </div>
        
        <!-- Portfolio Chart -->
        <div class="content-section" id="portfolioChartSection" style="display: none;">
            <div class="section-header">
                <h2>Portfolio Distribution</h2>
            </div>
            <div style="max-width: 400px; margin: 0 auto;">
                <canvas id="portfolioChart"></canvas>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="content-section" id="quickStatsSection" style="display: none;">
            <div class="section-header">
                <h2>Performance Stats</h2>
            </div>
            <div class="summary-cards">
                <div class="summary-card" style="border-left-color: #4CAF50;">
                    <h3>Top Performer</h3>
                    <div class="value" id="topPerformer" style="font-size: 20px;">-</div>
                    <div class="change positive" id="topPerformerChange">-</div>
                </div>
                <div class="summary-card" style="border-left-color: #dc3545;">
                    <h3>Worst Performer</h3>
                    <div class="value" id="worstPerformer" style="font-size: 20px;">-</div>
                    <div class="change negative" id="worstPerformerChange">-</div>
                </div>
                <div class="summary-card" style="border-left-color: #2196F3;">
                    <h3>Total Holdings</h3>
                    <div class="value" id="totalHoldings">0</div>
                    <div class="change" id="totalHoldingsDetail">cryptocurrencies</div>
                </div>
            </div>
        </div>
        
        <!-- Portfolio Holdings -->
        <div class="content-section">
            <div class="section-header">
                <h2>Your Holdings</h2>
                <div>
                    <button class="btn-add" onclick="exportPortfolio()" style="background: #28a745; margin-right: 10px;">📥 Export CSV</button>
                    <button class="btn-add" onclick="openAddHoldingModal()">+ Add Holding</button>
                </div>
            </div>
            
            <div id="alertBox" class="alert"></div>
            
            <div id="holdingsContainer">
                <div class="empty-state">
                    <i class="ti-wallet"></i>
                    <p>No holdings yet. Start by adding your first cryptocurrency!</p>
                    <button class="btn-add" onclick="openAddHoldingModal()">Add Your First Holding</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Holding Modal -->
    <div class="modal" id="holdingModal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-close" onclick="closeHoldingModal()">&times;</span>
                <span id="modalTitle">Add Holding</span>
            </div>
            
            <form id="holdingForm" method="POST" novalidate>
                <input type="hidden" id="holdingId" name="holding_id" value="">
                <input type="hidden" name="csrf_token" id="csrfToken" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label for="cryptoSelect">Cryptocurrency *</label>
                    <select id="cryptoSelect" name="crypto_id" required>
                        <option value="">Select a cryptocurrency...</option>
                        <option value="bitcoin">Bitcoin (BTC)</option>
                        <option value="ethereum">Ethereum (ETH)</option>
                        <option value="litecoin">Litecoin (LTC)</option>
                        <option value="ripple">XRP (XRP)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" step="0.00000001" required>
                </div>
                
                <div class="form-group">
                    <label for="entryPrice">Entry Price (USD) *</label>
                    <input type="number" id="entryPrice" name="entry_price" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="entryDate">Entry Date *</label>
                    <input type="date" id="entryDate" name="entry_date" required>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Add any notes about this holding..."></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeHoldingModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Holding</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/jquery-3.2.1.min.js"></script>
    <script>
        // Get CSRF token from meta tag
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        
        let portfolioChart = null;
        
        // Check authentication and load dashboard data
        $(document).ready(function() {
            
            // Load portfolio data
            loadPortfolioData();
            
            // Load wallet balance
            loadWalletBalance();
            
            // Load bot stats
            loadBotStats();
            
            // Refresh data every 60 seconds
            setInterval(function() {
                loadPortfolioData();
                loadWalletBalance();
                loadBotStats();
            }, 60000);
            
            // Auto-refresh prices every 60 seconds
            setInterval(function() {
                loadPortfolioData();
                loadWalletBalance();
            }, 60000); // 60 seconds
            
            // Set today's date as default entry date
            const today = new Date().toISOString().split('T')[0];
            $('#entryDate').val(today);
            
            // Form submission
            $('#holdingForm').on('submit', function(e) {
                e.preventDefault();
                saveHolding();
            });
        });
        
        /**
         * Load portfolio data from API
         */
        function loadPortfolioData() {
            $.post('api/portfolio.php', {
                action: 'get_holdings',
                csrf_token: csrfToken
            }, function(response) {
                if (response.success) {
                    displayHoldings(response.holdings);
                    updateSummaryWithLivePrices(response.holdings);
                } else {
                    showAlert('Error loading portfolio', 'error');
                }
            }).fail(function() {
                showAlert('Failed to load portfolio', 'error');
            });
        }
        
        /**
         * Display holdings table
         */
        function displayHoldings(holdings) {
            const container = $('#holdingsContainer');
            
            if (holdings.length === 0) {
                container.html(`
                    <div class="empty-state">
                        <i class="ti-wallet"></i>
                        <p>No holdings yet. Start by adding your first cryptocurrency!</p>
                        <button class="btn-add" onclick="openAddHoldingModal()">Add Your First Holding</button>
                    </div>
                `);
                return;
            }
            
            let html = `
                <table class="holdings-table">
                    <thead>
                        <tr>
                            <th>Cryptocurrency</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Entry Price</th>
                            <th class="text-right">Entry Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            holdings.forEach(holding => {
                const totalValue = (holding.quantity * holding.entry_price).toFixed(2);
                html += `
                    <tr>
                        <td>
                            <span class="crypto-name">${holding.crypto_name}</span>
                            <span class="crypto-symbol">${holding.crypto_symbol}</span>
                        </td>
                        <td class="text-right">${parseFloat(holding.quantity).toFixed(8)}</td>
                        <td class="text-right">$${parseFloat(holding.entry_price).toFixed(2)}</td>
                        <td class="text-right">${formatDate(holding.entry_date)}</td>
                        <td class="text-right">
                            <button class="btn-action btn-edit" onclick="editHolding(${holding.holding_id})">Edit</button>
                            <button class="btn-action btn-delete" onclick="deleteHolding(${holding.holding_id})">Delete</button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            container.html(html);
        }
        
        /**
         * Update summary cards
         */
        function updateSummary(holdings) {
            let totalInvested = 0;
            
            holdings.forEach(holding => {
                totalInvested += holding.quantity * holding.entry_price;
            });
            
            $('#totalInvested').text('$' + totalInvested.toFixed(2));
            $('#investedChange').text(`In ${holdings.length} cryptocurrency${holdings.length !== 1 ? 's' : ''}`);
            
            // Note: Current value would require real-time price data from API
            $('#currentValue').text('$' + totalInvested.toFixed(2));
            $('#valueChange').text('Loading live prices...');
        }
        
        /**
         * Update summary with live cryptocurrency prices
         */
        function updateSummaryWithLivePrices(holdings) {
            if (holdings.length === 0) {
                $('#totalInvested').text('$0.00');
                $('#investedChange').text('No holdings yet');
                $('#currentValue').text('$0.00');
                $('#valueChange').text('Add your first crypto');
                $('#totalPL').text('$0.00');
                $('#plChange').text('0%');
                return;
            }
            
            // Calculate total invested
            let totalInvested = 0;
            holdings.forEach(holding => {
                totalInvested += holding.quantity * holding.entry_price;
            });
            
            $('#totalInvested').text('$' + totalInvested.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#investedChange').text(`In ${holdings.length} cryptocurrency${holdings.length !== 1 ? 's' : ''}`);
            
            // Get unique crypto IDs
            const cryptoIds = [...new Set(holdings.map(h => h.crypto_id))].join(',');
            
            // Fetch live prices from CoinGecko
            $.ajax({
                url: `https://api.coingecko.com/api/v3/simple/price?ids=${cryptoIds}&vs_currencies=usd&include_24hr_change=true`,
                method: 'GET',
                success: function(prices) {
                    let currentValue = 0;
                    
                    // Calculate current value
                    holdings.forEach(holding => {
                        const priceData = prices[holding.crypto_id];
                        if (priceData) {
                            currentValue += holding.quantity * priceData.usd;
                        } else {
                            // Fallback to entry price if live price unavailable
                            currentValue += holding.quantity * holding.entry_price;
                        }
                    });
                    
                    // Calculate profit/loss
                    const profitLoss = currentValue - totalInvested;
                    const profitLossPercent = ((profitLoss / totalInvested) * 100);
                    
                    // Update UI
                    $('#currentValue').text('$' + currentValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    
                    // Update value change indicator
                    if (profitLoss >= 0) {
                        $('#valueChange').html('<i class="ti-arrow-up"></i> +$' + Math.abs(profitLoss).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' today');
                        $('#valueChange').css('color', '#4CAF50');
                    } else {
                        $('#valueChange').html('<i class="ti-arrow-down"></i> -$' + Math.abs(profitLoss).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' today');
                        $('#valueChange').css('color', '#dc2626');
                    }
                    
                    // Update P/L card
                    $('#totalPL').text((profitLoss >= 0 ? '+' : '') + '$' + profitLoss.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#plChange').text((profitLossPercent >= 0 ? '+' : '') + profitLossPercent.toFixed(2) + '%');
                    
                    if (profitLoss >= 0) {
                        $('#totalPL').css('color', '#4CAF50');
                        $('#plChange').css('color', '#4CAF50');
                    } else {
                        $('#totalPL').css('color', '#dc2626');
                        $('#plChange').css('color', '#dc2626');
                    }
                    
                    // Update holdings table with current prices
                    updateHoldingsWithLivePrices(holdings, prices);
                    
                    // Update portfolio chart
                    updatePortfolioChart(holdings, prices);
                    
                    // Update quick stats
                    updateQuickStats(holdings, prices);
                },
                error: function() {
                    $('#currentValue').text('$' + totalInvested.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#valueChange').text('Unable to fetch live prices');
                    $('#totalPL').text('$0.00');
                    $('#plChange').text('N/A');
                }
            });
        }
        
        /**
         * Update holdings table with live prices
         */
        function updateHoldingsWithLivePrices(holdings, prices) {
            const container = $('#holdingsContainer');
            
            if (holdings.length === 0) {
                container.html(`
                    <div class="empty-state">
                        <i class="ti-wallet"></i>
                        <p>No holdings yet. Start by adding your first cryptocurrency!</p>
                        <button class="btn-add" onclick="openAddHoldingModal()">Add Your First Holding</button>
                    </div>
                `);
                return;
            }
            
            let html = `
                <table class="holdings-table">
                    <thead>
                        <tr>
                            <th>Cryptocurrency</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Entry Price</th>
                            <th class="text-right">Current Price</th>
                            <th class="text-right">Value</th>
                            <th class="text-right">P/L</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            holdings.forEach(holding => {
                const priceData = prices[holding.crypto_id];
                const currentPrice = priceData ? priceData.usd : holding.entry_price;
                const currentValue = holding.quantity * currentPrice;
                const investedValue = holding.quantity * holding.entry_price;
                const profitLoss = currentValue - investedValue;
                const profitLossPercent = ((profitLoss / investedValue) * 100);
                const plColor = profitLoss >= 0 ? '#4CAF50' : '#dc2626';
                
                html += `
                    <tr>
                        <td>
                            <span class="crypto-name">${holding.crypto_name}</span>
                            <span class="crypto-symbol">${holding.crypto_symbol}</span>
                        </td>
                        <td class="text-right">${parseFloat(holding.quantity).toFixed(8)}</td>
                        <td class="text-right">$${parseFloat(holding.entry_price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="text-right">$${currentPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="text-right">$${currentValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="text-right" style="color: ${plColor}; font-weight: bold;">
                            ${profitLoss >= 0 ? '+' : ''}$${Math.abs(profitLoss).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            <br><small>(${profitLoss >= 0 ? '+' : ''}${profitLossPercent.toFixed(2)}%)</small>
                        </td>
                        <td class="text-right">
                            <button class="btn-action btn-edit" onclick="editHolding(${holding.holding_id})">Edit</button>
                            <button class="btn-action btn-delete" onclick="deleteHolding(${holding.holding_id})">Delete</button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            container.html(html);
        }
        
        /**
         * Open add holding modal
         */
        function openAddHoldingModal() {
            $('#holdingId').val('');
            $('#holdingForm')[0].reset();
            $('#modalTitle').text('Add Holding');
            $('#cryptoSelect').val('');
            
            const today = new Date().toISOString().split('T')[0];
            $('#entryDate').val(today);
            
            $('#holdingModal').addClass('show');
        }
        
        /**
         * Edit holding
         */
        function editHolding(holdingId) {
            // Find the holding data
            $.post('api/portfolio.php', {
                action: 'get_holding_details',
                holding_id: holdingId,
                csrf_token: csrfToken
            }, function(response) {
                if (response.success && response.holding) {
                    const holding = response.holding;
                    
                    // Populate form
                    $('#holdingId').val(holding.holding_id);
                    $('#cryptoSelect').val(holding.crypto_id);
                    $('#quantity').val(holding.quantity);
                    $('#entryPrice').val(holding.entry_price);
                    $('#entryDate').val(holding.entry_date);
                    $('#notes').val(holding.notes);
                    
                    $('#modalTitle').text('Edit Holding');
                    $('#holdingModal').addClass('show');
                } else {
                    showAlert('Failed to load holding details', 'error');
                }
            }).fail(function() {
                showAlert('Error loading holding details', 'error');
            });
        }
        
        /**
         * Close modal
         */
        function closeHoldingModal() {
            $('#holdingModal').removeClass('show');
        }
        
        /**
         * Save holding
         */
        function saveHolding() {
            const holdingId = $('#holdingId').val();
            const isUpdate = holdingId !== '';
            const cryptoId = $('#cryptoSelect').val();
            const quantity = $('#quantity').val();
            const entryPrice = $('#entryPrice').val();
            const entryDate = $('#entryDate').val();
            const notes = $('#notes').val();
            
            // Get crypto name and symbol
            const cryptoMap = {
                'bitcoin': { symbol: 'BTC', name: 'Bitcoin' },
                'ethereum': { symbol: 'ETH', name: 'Ethereum' },
                'litecoin': { symbol: 'LTC', name: 'Litecoin' },
                'ripple': { symbol: 'XRP', name: 'XRP' }
            };
            
            const crypto = cryptoMap[cryptoId];
            if (!crypto) {
                showAlert('Please select a valid cryptocurrency', 'error');
                return;
            }
            
            const data = {
                action: isUpdate ? 'update_holding' : 'add_holding',
                crypto_id: cryptoId,
                crypto_symbol: crypto.symbol,
                crypto_name: crypto.name,
                quantity: quantity,
                entry_price: entryPrice,
                entry_date: entryDate,
                notes: notes
            };
            
            if (isUpdate) {
                data.holding_id = holdingId;
            }
            
            // Add CSRF token
            data.csrf_token = csrfToken;
            
            $.post('api/portfolio.php', data, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    closeHoldingModal();
                    loadPortfolioData();
                } else {
                    showAlert(response.message || 'Failed to save holding', 'error');
                }
            }).fail(function(xhr) {
                const response = xhr.responseJSON || { message: 'Error saving holding' };
                showAlert(response.message, 'error');
            });
        }
        
        /**
         * Delete holding
         */
        function deleteHolding(holdingId) {
            if (!confirm('Are you sure you want to delete this holding?')) return;
            
            $.post('api/portfolio.php', {
                action: 'remove_holding',
                holding_id: holdingId,
                csrf_token: csrfToken
            }, function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    loadPortfolioData();
                } else {
                    showAlert(response.message || 'Failed to delete holding', 'error');
                }
            }).fail(function() {
                showAlert('Error deleting holding', 'error');
            });
        }
        
        /**
         * Logout user
         */
        function logout_user() {
            $.post('api/login.php', {
                action: 'logout'
            }, function(response) {
                localStorage.removeItem('user_id');
                localStorage.removeItem('user_name');
                localStorage.removeItem('user_email');
                window.location.href = 'index.html';
            });
        }
        
        /**
         * Show alert message
         */
        function showAlert(message, type) {
            const alertBox = $('#alertBox');
            alertBox.removeClass('alert-success alert-error');
            alertBox.addClass(`alert alert-${type} show`);
            alertBox.text(message);
            
            setTimeout(() => {
                alertBox.removeClass('show');
            }, 5000);
        }
        
        /**
         * Format date
         */
        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        /**
         * Update portfolio distribution chart
         */
        function updatePortfolioChart(holdings, prices) {
            if (holdings.length === 0) {
                $('#portfolioChartSection').hide();
                return;
            }
            
            $('#portfolioChartSection').show();
            
            // Prepare chart data
            const labels = [];
            const data = [];
            const backgroundColors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#fee140', '#30cfd0'];
            
            holdings.forEach((holding, index) => {
                const priceData = prices[holding.crypto_id];
                const currentPrice = priceData ? priceData.usd : holding.entry_price;
                const currentValue = holding.quantity * currentPrice;
                
                labels.push(holding.crypto_symbol);
                data.push(currentValue);
            });
            
            // Destroy existing chart
            if (portfolioChart) {
                portfolioChart.destroy();
            }
            
            // Create new chart
            const ctx = document.getElementById('portfolioChart').getContext('2d');
            portfolioChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors.slice(0, holdings.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': $' + value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        /**
         * Update quick performance stats
         */
        function updateQuickStats(holdings, prices) {
            if (holdings.length === 0) {
                $('#quickStatsSection').hide();
                return;
            }
            
            $('#quickStatsSection').show();
            
            let topPerformer = null;
            let worstPerformer = null;
            let maxGain = -Infinity;
            let maxLoss = Infinity;
            
            holdings.forEach(holding => {
                const priceData = prices[holding.crypto_id];
                const currentPrice = priceData ? priceData.usd : holding.entry_price;
                const profitLossPercent = ((currentPrice - holding.entry_price) / holding.entry_price) * 100;
                
                if (profitLossPercent > maxGain) {
                    maxGain = profitLossPercent;
                    topPerformer = {
                        name: holding.crypto_symbol,
                        percent: profitLossPercent
                    };
                }
                
                if (profitLossPercent < maxLoss) {
                    maxLoss = profitLossPercent;
                    worstPerformer = {
                        name: holding.crypto_symbol,
                        percent: profitLossPercent
                    };
                }
            });
            
            if (topPerformer) {
                $('#topPerformer').text(topPerformer.name);
                $('#topPerformerChange').text((topPerformer.percent >= 0 ? '+' : '') + topPerformer.percent.toFixed(2) + '%');
                $('#topPerformerChange').css('color', topPerformer.percent >= 0 ? '#4CAF50' : '#dc2626');
            }
            
            if (worstPerformer) {
                $('#worstPerformer').text(worstPerformer.name);
                $('#worstPerformerChange').text((worstPerformer.percent >= 0 ? '+' : '') + worstPerformer.percent.toFixed(2) + '%');
                $('#worstPerformerChange').css('color', worstPerformer.percent >= 0 ? '#4CAF50' : '#dc2626');
            }
            
            $('#totalHoldings').text(holdings.length);
            $('#totalHoldingsDetail').text(holdings.length === 1 ? 'cryptocurrency' : 'cryptocurrencies');
        }
        
        /**
         * Export portfolio to CSV
         */
        function exportPortfolio() {
            // Get current holdings data
            $.post('api/portfolio.php', {
                action: 'get_holdings',
                csrf_token: csrfToken
            }, function(response) {
                if (response.success && response.holdings.length > 0) {
                    const holdings = response.holdings;
                    
                    // Create CSV content
                    let csv = 'Cryptocurrency,Symbol,Quantity,Entry Price,Entry Date,Notes\n';
                    
                    holdings.forEach(holding => {
                        csv += `"${holding.crypto_name}","${holding.crypto_symbol}",${holding.quantity},${holding.entry_price},"${holding.entry_date}","${(holding.notes || '').replace(/"/g, '""')}"\n`;
                    });
                    
                    // Create download link
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    
                    link.setAttribute('href', url);
                    link.setAttribute('download', 'portfolio_' + new Date().toISOString().split('T')[0] + '.csv');
                    link.style.visibility = 'hidden';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showAlert('Portfolio exported successfully!', 'success');
                } else {
                    showAlert('No holdings to export', 'error');
                }
            }).fail(function() {
                showAlert('Failed to export portfolio', 'error');
            });
        }
        
        /**
         * Load wallet balance
         */
        function loadWalletBalance() {
            $.ajax({
                url: 'api/wallet.php?action=get_balance',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#walletBalance').text(response.formatted);
                    }
                },
                error: function() {
                    console.log('Failed to load wallet balance');
                }
            });
        }

        /**
         * Load AI bot stats
         */
        function loadBotStats() {
            $.ajax({
                url: 'api/trading-bot.php?action=get_bots&status=all',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        const bots = response.bots;
                        const activeBots = bots.filter(b => b.status === 'active').length;
                        const totalPL = bots.reduce((sum, b) => sum + parseFloat(b.total_profit_loss), 0);
                        const totalTrades = bots.reduce((sum, b) => sum + parseInt(b.total_trades), 0);
                        const winningTrades = bots.reduce((sum, b) => sum + parseInt(b.winning_trades), 0);
                        const winRate = totalTrades > 0 ? ((winningTrades / totalTrades) * 100).toFixed(1) : 0;

                        $('#activeBots').text(activeBots + ' Active');
                        $('#botStatus').html(activeBots > 0 
                            ? `<span style="color: #10b981;">● Running</span>` 
                            : `<a href="ai-trading-bot.php" style="color: #667eea; text-decoration: none; font-weight: 600;"><i class="fa fa-robot"></i> Create Bot</a>`
                        );
                        
                        $('#botPL').text('$' + totalPL.toFixed(2));
                        $('#botPL').addClass(totalPL >= 0 ? 'positive' : 'negative');
                        $('#botWinRate').text(winRate + '% win rate');
                    }
                },
                error: function() {
                    console.log('Failed to load bot stats');
                }
            });
        }
    </script>
</body>

</html>
