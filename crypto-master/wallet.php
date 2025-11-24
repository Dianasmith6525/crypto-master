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

// Get wallet balance
$stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ? AND currency = 'USD'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wallet = $result->fetch_assoc();
$balance = $wallet['balance'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>Wallet - Crypto Trading Platform</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    
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
        }
        
        .navbar-dashboard .logo {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .navbar-dashboard .nav-menu {
            display: flex;
            gap: 24px;
            align-items: center;
        }
        
        .navbar-dashboard .nav-menu a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .navbar-dashboard .nav-menu a:hover {
            color: #ffffff;
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
        }
        
        .navbar-dashboard .btn-logout:hover {
            background: rgba(220, 53, 69, 0.25);
            transform: translateY(-2px);
        }
        
        .wallet-container {
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
        }
        
        .page-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
        }
        
        .balance-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 36px;
            text-align: center;
        }
        
        .balance-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 16px;
        }
        
        .balance-amount {
            color: #ffffff;
            font-size: 56px;
            font-weight: 700;
            letter-spacing: -2px;
            margin-bottom: 24px;
        }
        
        .balance-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
        }
        
        .btn-action {
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }
        
        .btn-action:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.5);
        }
        
        .btn-action.secondary {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: none;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 36px;
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
        
        .section-header {
            color: #ffffff;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .deposit-list, .transaction-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .deposit-item, .transaction-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 16px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .deposit-item:hover, .transaction-item:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(4px);
        }
        
        .item-info {
            color: #ffffff;
        }
        
        .item-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 4px;
        }
        
        .item-value {
            font-size: 16px;
            font-weight: 600;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-pending {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .badge-completed {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .badge-failed {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
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
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(30px);
            padding: 40px;
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .modal-header h3 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
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
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
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
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }
        
        .form-group select option {
            background: #1a1a2e;
            color: #ffffff;
        }
        
        .deposit-address {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin: 20px 0;
        }
        
        .deposit-address img {
            margin-bottom: 16px;
            border-radius: 8px;
        }
        
        .address-text {
            color: #ffffff;
            font-family: monospace;
            font-size: 13px;
            word-break: break-all;
            padding: 12px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            margin-top: 12px;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .wallet-container {
                padding: 20px;
            }
            
            .balance-amount {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <div class="navbar-dashboard">
        <div class="logo">💰 Wallet</div>
        <div class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="wallet.php" style="color: #ffffff; font-weight: 600;">Wallet</a>
            <a href="withdrawal.php">Withdraw</a>
            <a href="trade.html">Trade</a>
            <a href="watchlist.html">Watchlist</a>
        </div>
        <a href="api/login.php?action=logout" class="btn-logout">
            <i class="fa fa-sign-out"></i> Logout
        </a>
    </div>
    
    <!-- Main Container -->
    <div class="wallet-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>💰 My Wallet</h1>
            <p>Manage your crypto deposits and view your balance</p>
        </div>
        
        <!-- Balance Card -->
        <div class="balance-card">
            <div class="balance-label">Available Balance</div>
            <div class="balance-amount" id="walletBalance">$<?php echo number_format($balance, 2); ?></div>
            <div class="balance-actions">
                <button class="btn-action" onclick="openDepositModal()">
                    <i class="fa fa-plus"></i> Deposit Crypto
                </button>
                <button class="btn-action secondary" onclick="refreshBalance()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Deposits -->
            <div class="content-section">
                <div class="section-header">📥 Recent Deposits</div>
                <div class="deposit-list" id="depositList">
                    <div class="empty-state">
                        <i class="fa fa-inbox"></i>
                        <p>No deposits yet</p>
                    </div>
                </div>
            </div>
            
            <!-- Transaction History -->
            <div class="content-section">
                <div class="section-header">📊 Transaction History</div>
                <div class="transaction-list" id="transactionList">
                    <div class="empty-state">
                        <i class="fa fa-history"></i>
                        <p>No transactions yet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Deposit Modal -->
    <div id="depositModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>💎 Deposit Cryptocurrency</h3>
                <button class="close-modal" onclick="closeDepositModal()">&times;</button>
            </div>
            
            <form id="depositForm" onsubmit="submitDeposit(event)">
                <div class="form-group">
                    <label>Select Cryptocurrency</label>
                    <select id="cryptoSelect" onchange="getDepositAddress()" required>
                        <option value="">Choose crypto...</option>
                        <option value="BTC,bitcoin">Bitcoin (BTC)</option>
                        <option value="ETH,ethereum">Ethereum (ETH)</option>
                        <option value="USDT,tether">Tether (USDT)</option>
                        <option value="BNB,binancecoin">Binance Coin (BNB)</option>
                        <option value="USDC,usd-coin">USD Coin (USDC)</option>
                        <option value="XRP,ripple">Ripple (XRP)</option>
                        <option value="ADA,cardano">Cardano (ADA)</option>
                        <option value="SOL,solana">Solana (SOL)</option>
                        <option value="DOT,polkadot">Polkadot (DOT)</option>
                        <option value="DOGE,dogecoin">Dogecoin (DOGE)</option>
                    </select>
                </div>
                
                <div id="depositAddressSection" style="display: none;">
                    <div class="deposit-address">
                        <img id="qrCode" src="" alt="QR Code" width="200" height="200">
                        <div style="color: rgba(255, 255, 255, 0.8); margin-top: 12px;">Scan QR Code or Copy Address</div>
                        <div class="address-text" id="depositAddress"></div>
                        <button type="button" onclick="copyAddress()" style="margin-top: 12px; padding: 8px 20px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white; border-radius: 8px; cursor: pointer;">
                            <i class="fa fa-copy"></i> Copy Address
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label>Amount to Deposit</label>
                        <input type="number" id="depositAmount" step="0.00000001" placeholder="0.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Transaction Hash (Optional)</label>
                        <input type="text" id="txHash" placeholder="Enter transaction hash after sending">
                    </div>
                    
                    <input type="hidden" id="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <button type="submit" class="btn-submit">
                        <i class="fa fa-check"></i> Submit Deposit
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/jquery-3.2.1.min.js"></script>
    <script>
        let currentCrypto = '';
        let currentCryptoName = '';
        let depositAddress = '';
        
        // Load deposits and transactions on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadDeposits();
            loadTransactions();
        });
        
        function openDepositModal() {
            document.getElementById('depositModal').classList.add('show');
        }
        
        function closeDepositModal() {
            document.getElementById('depositModal').classList.remove('show');
            document.getElementById('depositForm').reset();
            document.getElementById('depositAddressSection').style.display = 'none';
        }
        
        function getDepositAddress() {
            const select = document.getElementById('cryptoSelect');
            const value = select.value;
            
            if (!value) return;
            
            const [crypto, cryptoName] = value.split(',');
            currentCrypto = crypto;
            currentCryptoName = cryptoName;
            
            const formData = new FormData();
            formData.append('action', 'get_deposit_address');
            formData.append('crypto', crypto);
            formData.append('network', crypto === 'BTC' ? 'Bitcoin' : crypto === 'ETH' ? 'Ethereum' : 'Default');
            
            fetch('api/wallet.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('qrCode').src = data.qr_code;
                    document.getElementById('depositAddress').textContent = data.address;
                    depositAddress = data.address;
                    document.getElementById('depositAddressSection').style.display = 'block';
                }
            });
        }
        
        function copyAddress() {
            navigator.clipboard.writeText(depositAddress);
            alert('Address copied to clipboard!');
        }
        
        function submitDeposit(event) {
            event.preventDefault();
            
            const amount = document.getElementById('depositAmount').value;
            const txHash = document.getElementById('txHash').value;
            const csrfToken = document.getElementById('csrf_token').value;
            
            const formData = new FormData();
            formData.append('action', 'create_deposit');
            formData.append('crypto', currentCrypto);
            formData.append('crypto_name', currentCryptoName);
            formData.append('amount', amount);
            formData.append('tx_hash', txHash);
            formData.append('csrf_token', csrfToken);
            
            fetch('api/wallet.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeDepositModal();
                    loadDeposits();
                    loadTransactions();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function loadDeposits() {
            fetch('api/wallet.php?action=get_deposits&limit=5')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.deposits.length > 0) {
                    const html = data.deposits.map(d => `
                        <div class="deposit-item">
                            <div class="item-info">
                                <div class="item-label">${d.crypto_symbol} Deposit</div>
                                <div class="item-value">${d.amount} ${d.crypto_symbol} ($${parseFloat(d.usd_value).toFixed(2)})</div>
                                <div class="item-label" style="margin-top: 4px;">${new Date(d.deposit_date).toLocaleDateString()}</div>
                            </div>
                            <span class="badge badge-${d.status}">${d.status}</span>
                        </div>
                    `).join('');
                    document.getElementById('depositList').innerHTML = html;
                }
            });
        }
        
        function loadTransactions() {
            fetch('api/wallet.php?action=get_transactions&limit=5')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.transactions.length > 0) {
                    const html = data.transactions.map(t => {
                        const sign = t.type === 'deposit' ? '+' : '-';
                        const color = t.type === 'deposit' ? '#10b981' : '#ef4444';
                        return `
                            <div class="transaction-item">
                                <div class="item-info">
                                    <div class="item-label">${t.type.toUpperCase()}</div>
                                    <div class="item-value">${t.description || 'Transaction'}</div>
                                    <div class="item-label" style="margin-top: 4px;">${new Date(t.created_at).toLocaleDateString()}</div>
                                </div>
                                <div style="color: ${color}; font-weight: 600; font-size: 16px;">
                                    ${sign}$${parseFloat(t.amount).toFixed(2)}
                                </div>
                            </div>
                        `;
                    }).join('');
                    document.getElementById('transactionList').innerHTML = html;
                }
            });
        }
        
        function refreshBalance() {
            fetch('api/wallet.php?action=get_balance')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('walletBalance').textContent = data.formatted;
                }
            });
        }
    </script>
</body>
</html>
