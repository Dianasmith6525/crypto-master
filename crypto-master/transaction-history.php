<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!is_user_authenticated()) {
    header('Location: login.html');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email'] ?? 'User';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - CryptoHub</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: #fff;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            margin-left: 2rem;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #fff;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 36px;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.6);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-card h3 {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card .change {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
        }

        .filters-bar {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px 12px;
            color: white;
            font-size: 14px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .transactions-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-header h2 {
            font-size: 20px;
            font-weight: 600;
        }

        .transaction-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .transactions-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .transactions-table thead th {
            text-align: left;
            padding: 12px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .transactions-table tbody tr {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            transition: all 0.3s;
        }

        .transactions-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(4px);
        }

        .transactions-table tbody td {
            padding: 16px 12px;
            font-size: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .transactions-table tbody td:first-child {
            border-left: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px 0 0 8px;
        }

        .transactions-table tbody td:last-child {
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 0 8px 8px 0;
        }

        .tx-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .tx-type.deposit {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .tx-type.withdrawal {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .tx-type.trade {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .tx-type.bot-trade {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
        }

        .tx-type.bonus {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .tx-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .tx-status.completed {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .tx-status.pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .tx-status.failed {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .amount-positive {
            color: #10b981;
            font-weight: 600;
        }

        .amount-negative {
            color: #ef4444;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.6);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination button {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .pagination button:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.1);
        }

        .pagination button:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .pagination .page-info {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: rgba(255, 255, 255, 0.6);
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .transactions-table {
                display: block;
                overflow-x: auto;
            }

            .transaction-tabs {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">📜 Transaction History</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="wallet.php">Wallet</a>
                <a href="ai-trading-bot.php">AI Bots</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Transaction History</h1>
            <p>Complete record of all your transactions and activities</p>
        </div>

        <!-- Stats Overview -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <h3>Total Transactions</h3>
                <div class="value" id="totalTx">0</div>
                <div class="change">All time</div>
            </div>
            <div class="stat-card">
                <h3>Total Deposits</h3>
                <div class="value" id="totalDeposits">$0.00</div>
                <div class="change" id="depositCount">0 deposits</div>
            </div>
            <div class="stat-card">
                <h3>Total Withdrawals</h3>
                <div class="value" id="totalWithdrawals">$0.00</div>
                <div class="change" id="withdrawalCount">0 withdrawals</div>
            </div>
            <div class="stat-card">
                <h3>Bot Trading P/L</h3>
                <div class="value" id="botPL">$0.00</div>
                <div class="change" id="botTradeCount">0 trades</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Transaction Type</label>
                    <select id="typeFilter">
                        <option value="all">All Types</option>
                        <option value="deposit">Deposits</option>
                        <option value="withdrawal">Withdrawals</option>
                        <option value="trade">Manual Trades</option>
                        <option value="bot-trade">Bot Trades</option>
                        <option value="bonus">Bonuses</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" id="dateFrom">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" id="dateTo">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn" onclick="applyFilters()">Apply Filters</button>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-secondary" onclick="exportTransactions()">📥 Export CSV</button>
                </div>
            </div>
        </div>

        <!-- Transactions Section -->
        <div class="transactions-section">
            <div class="section-header">
                <h2>All Transactions</h2>
                <div class="transaction-tabs">
                    <button class="tab-btn active" data-tab="all">All</button>
                    <button class="tab-btn" data-tab="wallet">Wallet</button>
                    <button class="tab-btn" data-tab="trading">Trading</button>
                    <button class="tab-btn" data-tab="bots">AI Bots</button>
                </div>
            </div>

            <div id="loadingState" class="loading" style="display: none;">
                Loading transactions...
            </div>

            <div id="transactionsContainer">
                <!-- Transactions will be loaded here -->
            </div>

            <div class="pagination" id="pagination" style="display: none;">
                <button id="prevPage" onclick="previousPage()">← Previous</button>
                <span class="page-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
                <button id="nextPage" onclick="nextPage()">Next →</button>
            </div>
        </div>
    </div>

    <script src="js/jquery-3.2.1.min.js"></script>
    <script>
        const csrfToken = '<?php echo $csrf_token; ?>';
        let currentPage = 1;
        let currentTab = 'all';
        let filters = {
            type: 'all',
            status: 'all',
            dateFrom: '',
            dateTo: ''
        };

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentTab = this.dataset.tab;
                currentPage = 1;
                loadTransactions();
            });
        });

        // Load transactions on page load
        loadTransactions();
        loadStats();

        function applyFilters() {
            filters.type = document.getElementById('typeFilter').value;
            filters.status = document.getElementById('statusFilter').value;
            filters.dateFrom = document.getElementById('dateFrom').value;
            filters.dateTo = document.getElementById('dateTo').value;
            currentPage = 1;
            loadTransactions();
        }

        async function loadTransactions() {
            document.getElementById('loadingState').style.display = 'block';
            document.getElementById('transactionsContainer').innerHTML = '';

            try {
                const response = await fetch(`api/transactions.php?action=get_transactions&page=${currentPage}&tab=${currentTab}&type=${filters.type}&status=${filters.status}&date_from=${filters.dateFrom}&date_to=${filters.dateTo}`);
                const data = await response.json();

                document.getElementById('loadingState').style.display = 'none';

                if (data.success) {
                    displayTransactions(data.transactions);
                    updatePagination(data.pagination);
                } else {
                    showEmptyState();
                }
            } catch (error) {
                document.getElementById('loadingState').style.display = 'none';
                console.error('Error loading transactions:', error);
                showEmptyState();
            }
        }

        function displayTransactions(transactions) {
            const container = document.getElementById('transactionsContainer');

            if (transactions.length === 0) {
                showEmptyState();
                return;
            }

            let html = `
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            transactions.forEach(tx => {
                const typeIcon = getTypeIcon(tx.type);
                const amountClass = tx.amount >= 0 ? 'amount-positive' : 'amount-negative';
                const amountSign = tx.amount >= 0 ? '+' : '';

                html += `
                    <tr>
                        <td>${formatDate(tx.created_at)}</td>
                        <td><span class="tx-type ${tx.type}">${typeIcon} ${formatType(tx.type)}</span></td>
                        <td>${tx.description || '-'}</td>
                        <td class="${amountClass}">${amountSign}$${Math.abs(tx.amount).toFixed(2)}</td>
                        <td><span class="tx-status ${tx.status}">${tx.status.toUpperCase()}</span></td>
                        <td>$${parseFloat(tx.balance_after).toFixed(2)}</td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            container.innerHTML = html;
        }

        function showEmptyState() {
            const container = document.getElementById('transactionsContainer');
            container.innerHTML = `
                <div class="empty-state">
                    <h3>No Transactions Found</h3>
                    <p>You don't have any transactions matching the current filters.</p>
                </div>
            `;
            document.getElementById('pagination').style.display = 'none';
        }

        function updatePagination(pagination) {
            if (pagination.total_pages <= 1) {
                document.getElementById('pagination').style.display = 'none';
                return;
            }

            document.getElementById('pagination').style.display = 'flex';
            document.getElementById('currentPage').textContent = pagination.current_page;
            document.getElementById('totalPages').textContent = pagination.total_pages;
            document.getElementById('prevPage').disabled = pagination.current_page <= 1;
            document.getElementById('nextPage').disabled = pagination.current_page >= pagination.total_pages;
        }

        function previousPage() {
            if (currentPage > 1) {
                currentPage--;
                loadTransactions();
            }
        }

        function nextPage() {
            currentPage++;
            loadTransactions();
        }

        async function loadStats() {
            try {
                const response = await fetch('api/transactions.php?action=get_stats');
                const data = await response.json();

                if (data.success) {
                    const stats = data.stats;
                    document.getElementById('totalTx').textContent = stats.total_transactions;
                    document.getElementById('totalDeposits').textContent = '$' + parseFloat(stats.total_deposits).toFixed(2);
                    document.getElementById('depositCount').textContent = stats.deposit_count + ' deposits';
                    document.getElementById('totalWithdrawals').textContent = '$' + parseFloat(stats.total_withdrawals).toFixed(2);
                    document.getElementById('withdrawalCount').textContent = stats.withdrawal_count + ' withdrawals';
                    document.getElementById('botPL').textContent = '$' + parseFloat(stats.bot_pl).toFixed(2);
                    document.getElementById('botTradeCount').textContent = stats.bot_trade_count + ' trades';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        async function exportTransactions() {
            try {
                const response = await fetch(`api/transactions.php?action=get_transactions&tab=${currentTab}&type=${filters.type}&status=${filters.status}&date_from=${filters.dateFrom}&date_to=${filters.dateTo}&limit=1000`);
                const data = await response.json();

                if (data.success && data.transactions.length > 0) {
                    let csv = 'Date,Type,Description,Amount,Status,Balance\n';

                    data.transactions.forEach(tx => {
                        const amountSign = tx.amount >= 0 ? '+' : '';
                        csv += `"${tx.created_at}","${formatType(tx.type)}","${(tx.description || '').replace(/"/g, '""')}","${amountSign}$${Math.abs(tx.amount).toFixed(2)}","${tx.status}","$${parseFloat(tx.balance_after).toFixed(2)}"\n`;
                    });

                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);

                    link.setAttribute('href', url);
                    link.setAttribute('download', 'transactions_' + new Date().toISOString().split('T')[0] + '.csv');
                    link.style.visibility = 'hidden';

                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    alert('Transactions exported successfully!');
                } else {
                    alert('No transactions to export');
                }
            } catch (error) {
                console.error('Error exporting:', error);
                alert('Failed to export transactions');
            }
        }

        function getTypeIcon(type) {
            const icons = {
                'deposit': '⬇️',
                'withdrawal': '⬆️',
                'trade': '💱',
                'bot-trade': '🤖',
                'bonus': '🎁',
                'transfer': '↔️'
            };
            return icons[type] || '📝';
        }

        function formatType(type) {
            return type.split('-').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return date.toLocaleDateString('en-US', options);
        }
    </script>
</body>
</html>
