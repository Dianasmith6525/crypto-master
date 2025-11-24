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
    <title>AI Trading Bots - CryptoHub</title>
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
        }

        .stat-card .change.positive { color: #10b981; }
        .stat-card .change.negative { color: #ef4444; }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
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

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .filters {
            display: flex;
            gap: 1rem;
        }

        .filter-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .bots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .bot-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .bot-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .bot-name {
            font-size: 18px;
            font-weight: 600;
        }

        .bot-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .bot-status.active {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .bot-status.paused {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .bot-status.stopped {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .bot-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .bot-info-item {
            font-size: 13px;
        }

        .bot-info-item label {
            color: rgba(255, 255, 255, 0.6);
            display: block;
            margin-bottom: 4px;
        }

        .bot-info-item .value {
            font-weight: 600;
            font-size: 16px;
        }

        .bot-performance {
            background: rgba(0, 0, 0, 0.2);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .bot-performance-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .bot-performance-row:last-child {
            margin-bottom: 0;
        }

        .bot-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .bot-actions button {
            flex: 1;
            min-width: 80px;
            padding: 8px 12px;
            font-size: 13px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #1a1a2e;
            border-radius: 20px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h2 {
            font-size: 24px;
        }

        .close-modal {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .strategy-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .strategy-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .strategy-card:hover {
            border-color: #667eea;
        }

        .strategy-card.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .strategy-card h4 {
            margin-bottom: 0.5rem;
            font-size: 16px;
        }

        .strategy-card p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }

        .risk-level-cards {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .risk-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .risk-card:hover {
            transform: translateY(-2px);
        }

        .risk-card.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .risk-card.conservative.selected { border-color: #10b981; }
        .risk-card.moderate.selected { border-color: #fbbf24; }
        .risk-card.aggressive.selected { border-color: #ef4444; }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .bots-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .strategy-cards,
            .risk-level-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">🤖 AI Trading Bots</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="wallet.php">Wallet</a>
                <a href="trade-history.html">Trades</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>AI Trading Bots</h1>
            <p>Automate your trading with AI-powered strategies</p>
        </div>

        <!-- Stats Overview -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <h3>Active Bots</h3>
                <div class="value" id="activeBots">0</div>
                <div class="change">Total bots running</div>
            </div>
            <div class="stat-card">
                <h3>Total Profit/Loss</h3>
                <div class="value" id="totalPL">$0.00</div>
                <div class="change" id="plChange">All time</div>
            </div>
            <div class="stat-card">
                <h3>Win Rate</h3>
                <div class="value" id="winRate">0%</div>
                <div class="change" id="totalTrades">0 trades</div>
            </div>
            <div class="stat-card">
                <h3>Today's Performance</h3>
                <div class="value" id="todayPL">$0.00</div>
                <div class="change" id="todayTrades">0 trades today</div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="filters">
                <button class="filter-btn active" data-filter="all">All Bots</button>
                <button class="filter-btn" data-filter="active">Active</button>
                <button class="filter-btn" data-filter="paused">Paused</button>
                <button class="filter-btn" data-filter="stopped">Stopped</button>
            </div>
            <button class="btn" onclick="openCreateBotModal()">
                + Create New Bot
            </button>
        </div>

        <!-- Bots Grid -->
        <div class="bots-grid" id="botsGrid">
            <!-- Bots will be loaded here -->
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="emptyState" style="display: none;">
            <h3>No Trading Bots Yet</h3>
            <p>Create your first AI trading bot to start automated trading</p>
            <button class="btn" onclick="openCreateBotModal()">Create Your First Bot</button>
        </div>
    </div>

    <!-- Create Bot Modal -->
    <div class="modal" id="createBotModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create AI Trading Bot</h2>
                <button class="close-modal" onclick="closeCreateBotModal()">×</button>
            </div>

            <form id="createBotForm">
                <div class="form-group">
                    <label>Bot Name</label>
                    <input type="text" name="bot_name" placeholder="My Trading Bot" required>
                </div>

                <div class="form-group">
                    <label>Select Strategy</label>
                    <div class="strategy-cards">
                        <div class="strategy-card" data-strategy="scalping">
                            <h4>⚡ Scalping</h4>
                            <p>Quick trades, small profits, high frequency</p>
                        </div>
                        <div class="strategy-card" data-strategy="day_trading">
                            <h4>📊 Day Trading</h4>
                            <p>Intraday positions, moderate frequency</p>
                        </div>
                        <div class="strategy-card" data-strategy="swing_trading">
                            <h4>📈 Swing Trading</h4>
                            <p>Multi-day positions, trend following</p>
                        </div>
                        <div class="strategy-card" data-strategy="grid_trading">
                            <h4>🎯 Grid Trading</h4>
                            <p>Buy low, sell high in ranges</p>
                        </div>
                    </div>
                    <input type="hidden" name="strategy" id="selectedStrategy" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Cryptocurrency</label>
                        <select name="crypto_symbol" id="cryptoSelect" required>
                            <option value="BTC">Bitcoin (BTC)</option>
                            <option value="ETH">Ethereum (ETH)</option>
                            <option value="BNB">Binance Coin (BNB)</option>
                            <option value="SOL">Solana (SOL)</option>
                            <option value="XRP">Ripple (XRP)</option>
                            <option value="ADA">Cardano (ADA)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Investment Amount (USD)</label>
                        <input type="number" name="investment_amount" min="10" step="0.01" placeholder="100.00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Risk Level</label>
                    <div class="risk-level-cards">
                        <div class="risk-card conservative" data-risk="conservative">
                            <h4>🛡️ Conservative</h4>
                            <p>Low risk, stable returns</p>
                        </div>
                        <div class="risk-card moderate" data-risk="moderate">
                            <h4>⚖️ Moderate</h4>
                            <p>Balanced approach</p>
                        </div>
                        <div class="risk-card aggressive" data-risk="aggressive">
                            <h4>🚀 Aggressive</h4>
                            <p>High risk, high reward</p>
                        </div>
                    </div>
                    <input type="hidden" name="risk_level" id="selectedRisk" value="moderate" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Take Profit (%)</label>
                        <input type="number" name="take_profit_percent" min="0.1" step="0.1" value="5" required>
                    </div>

                    <div class="form-group">
                        <label>Stop Loss (%)</label>
                        <input type="number" name="stop_loss_percent" min="0.1" step="0.1" value="3" required>
                    </div>
                </div>

                <input type="hidden" name="crypto_name" id="cryptoName">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <button type="submit" class="btn" style="width: 100%; margin-top: 1rem;">
                    Create Bot
                </button>
            </form>
        </div>
    </div>

    <script>
        let currentFilter = 'all';
        const csrfToken = '<?php echo $csrf_token; ?>';

        // Crypto name mapping
        const cryptoNames = {
            'BTC': 'bitcoin',
            'ETH': 'ethereum',
            'BNB': 'binancecoin',
            'SOL': 'solana',
            'XRP': 'ripple',
            'ADA': 'cardano'
        };

        // Update crypto name when symbol changes
        document.getElementById('cryptoSelect').addEventListener('change', function() {
            document.getElementById('cryptoName').value = cryptoNames[this.value] || this.value.toLowerCase();
        });

        // Strategy selection
        document.querySelectorAll('.strategy-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.strategy-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('selectedStrategy').value = this.dataset.strategy;
            });
        });

        // Risk level selection
        document.querySelectorAll('.risk-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.risk-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('selectedRisk').value = this.dataset.risk;
            });
        });

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                loadBots();
            });
        });

        // Load bots on page load
        loadBots();
        loadStats();
        setInterval(loadBots, 30000); // Refresh every 30 seconds

        function openCreateBotModal() {
            document.getElementById('createBotModal').classList.add('active');
            // Select default strategy and risk
            document.querySelector('.strategy-card[data-strategy="scalping"]').click();
            document.querySelector('.risk-card[data-risk="moderate"]').click();
            // Set default crypto name
            document.getElementById('cryptoName').value = 'bitcoin';
        }

        function closeCreateBotModal() {
            document.getElementById('createBotModal').classList.remove('active');
            document.getElementById('createBotForm').reset();
        }

        // Create bot form submission
        document.getElementById('createBotForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_bot');

            try {
                const response = await fetch('api/trading-bot.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('Bot created successfully!');
                    closeCreateBotModal();
                    loadBots();
                    loadStats();
                } else {
                    alert(data.message || 'Failed to create bot');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        });

        async function loadBots() {
            try {
                const response = await fetch(`api/trading-bot.php?action=get_bots&status=${currentFilter}`);
                const data = await response.json();

                if (data.success) {
                    displayBots(data.bots);
                }
            } catch (error) {
                console.error('Error loading bots:', error);
            }
        }

        function displayBots(bots) {
            const grid = document.getElementById('botsGrid');
            const emptyState = document.getElementById('emptyState');

            if (bots.length === 0) {
                grid.innerHTML = '';
                emptyState.style.display = 'block';
                return;
            }

            emptyState.style.display = 'none';
            grid.innerHTML = bots.map(bot => `
                <div class="bot-card">
                    <div class="bot-header">
                        <div class="bot-name">${bot.bot_name}</div>
                        <span class="bot-status ${bot.status}">${bot.status.toUpperCase()}</span>
                    </div>

                    <div class="bot-info">
                        <div class="bot-info-item">
                            <label>Strategy</label>
                            <div class="value">${formatStrategy(bot.strategy)}</div>
                        </div>
                        <div class="bot-info-item">
                            <label>Cryptocurrency</label>
                            <div class="value">${bot.crypto_symbol}</div>
                        </div>
                        <div class="bot-info-item">
                            <label>Investment</label>
                            <div class="value">$${parseFloat(bot.investment_amount).toFixed(2)}</div>
                        </div>
                        <div class="bot-info-item">
                            <label>Risk Level</label>
                            <div class="value">${formatRisk(bot.risk_level)}</div>
                        </div>
                    </div>

                    <div class="bot-performance">
                        <div class="bot-performance-row">
                            <span>Total P/L:</span>
                            <strong class="${bot.total_profit_loss >= 0 ? 'change positive' : 'change negative'}">
                                ${bot.profit_loss_formatted}
                            </strong>
                        </div>
                        <div class="bot-performance-row">
                            <span>Total Trades:</span>
                            <strong>${bot.total_trades}</strong>
                        </div>
                        <div class="bot-performance-row">
                            <span>Win Rate:</span>
                            <strong>${bot.win_rate}%</strong>
                        </div>
                        <div class="bot-performance-row">
                            <span>Last Trade:</span>
                            <strong>${bot.last_trade_at ? new Date(bot.last_trade_at).toLocaleDateString() : 'Never'}</strong>
                        </div>
                    </div>

                    <div class="bot-actions">
                        ${bot.status === 'paused' || bot.status === 'stopped' ? 
                            `<button class="btn btn-success" onclick="startBot(${bot.bot_id})">Start</button>` : ''}
                        ${bot.status === 'active' ? 
                            `<button class="btn btn-secondary" onclick="pauseBot(${bot.bot_id})">Pause</button>` : ''}
                        ${bot.status !== 'stopped' ? 
                            `<button class="btn btn-danger" onclick="stopBot(${bot.bot_id})">Stop</button>` : ''}
                        <button class="btn btn-secondary" onclick="viewBotDetails(${bot.bot_id})">Details</button>
                    </div>
                </div>
            `).join('');
        }

        async function loadStats() {
            try {
                const response = await fetch('api/trading-bot.php?action=get_bots&status=all');
                const data = await response.json();

                if (data.success) {
                    const bots = data.bots;
                    const activeBots = bots.filter(b => b.status === 'active').length;
                    const totalPL = bots.reduce((sum, b) => sum + parseFloat(b.total_profit_loss), 0);
                    const totalTrades = bots.reduce((sum, b) => sum + parseInt(b.total_trades), 0);
                    const winningTrades = bots.reduce((sum, b) => sum + parseInt(b.winning_trades), 0);
                    const winRate = totalTrades > 0 ? ((winningTrades / totalTrades) * 100).toFixed(2) : 0;

                    document.getElementById('activeBots').textContent = activeBots;
                    document.getElementById('totalPL').textContent = '$' + totalPL.toFixed(2);
                    document.getElementById('winRate').textContent = winRate + '%';
                    document.getElementById('totalTrades').textContent = totalTrades + ' trades';

                    const plChange = document.getElementById('plChange');
                    plChange.className = 'change ' + (totalPL >= 0 ? 'positive' : 'negative');
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        async function startBot(botId) {
            if (!confirm('Start this trading bot?')) return;

            const formData = new FormData();
            formData.append('action', 'start_bot');
            formData.append('bot_id', botId);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('api/trading-bot.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    loadBots();
                    loadStats();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function pauseBot(botId) {
            const formData = new FormData();
            formData.append('action', 'pause_bot');
            formData.append('bot_id', botId);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('api/trading-bot.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    loadBots();
                    loadStats();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function stopBot(botId) {
            if (!confirm('Stop this bot? All open trades will be closed at market price.')) return;

            const formData = new FormData();
            formData.append('action', 'stop_bot');
            formData.append('bot_id', botId);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('api/trading-bot.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    loadBots();
                    loadStats();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function viewBotDetails(botId) {
            window.location.href = `bot-details.php?bot_id=${botId}`;
        }

        function formatStrategy(strategy) {
            return strategy.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }

        function formatRisk(risk) {
            const icons = {
                'conservative': '🛡️',
                'moderate': '⚖️',
                'aggressive': '🚀'
            };
            return icons[risk] + ' ' + risk.charAt(0).toUpperCase() + risk.slice(1);
        }
    </script>
</body>
</html>
