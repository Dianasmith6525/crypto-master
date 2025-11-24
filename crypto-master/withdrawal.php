<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Funds - CryptoXchange</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            color: #fff;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px 35px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 10px;
        }

        .nav-links a:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        /* Grid Layout */
        .withdrawal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Glass Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.08);
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        /* Balance Display */
        .balance-info {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .balance-info .label {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 5px;
        }

        .balance-info .value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }

        /* Fee Info */
        .fee-info {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .fee-info .label {
            color: rgba(255, 255, 255, 0.6);
        }

        .fee-info .value {
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: rgba(52, 211, 153, 0.2);
            border: 1px solid rgba(52, 211, 153, 0.4);
            color: #34d399;
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.2);
            border: 1px solid rgba(248, 113, 113, 0.4);
            color: #f87171;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Withdrawal History */
        .withdrawal-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 12px;
            transition: all 0.3s;
        }

        .withdrawal-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .withdrawal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .crypto-badge {
            display: inline-block;
            padding: 6px 14px;
            background: rgba(102, 126, 234, 0.2);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #667eea;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .status-completed {
            background: rgba(52, 211, 153, 0.2);
            color: #34d399;
        }

        .status-rejected {
            background: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }

        .status-cancelled {
            background: rgba(156, 163, 175, 0.2);
            color: #9ca3af;
        }

        .withdrawal-details {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
        }

        .withdrawal-details div {
            margin-bottom: 5px;
        }

        .withdrawal-address {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            word-break: break-all;
            margin-top: 8px;
            padding: 8px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 6px;
        }

        .cancel-btn {
            margin-top: 10px;
            padding: 8px 16px;
            background: rgba(248, 113, 113, 0.2);
            border: 1px solid rgba(248, 113, 113, 0.4);
            color: #f87171;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .cancel-btn:hover {
            background: rgba(248, 113, 113, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.4);
        }

        .empty-state-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: rgba(255, 255, 255, 0.5);
        }

        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .withdrawal-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>⬆️ Withdraw Funds</h1>
            <div class="nav-links">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="wallet.php">💰 Wallet</a>
                <a href="transaction-history.php">📜 Transactions</a>
            </div>
        </div>

        <!-- Withdrawal Grid -->
        <div class="withdrawal-grid">
            <!-- Withdrawal Form -->
            <div class="glass-card">
                <h2 class="card-title">💸 Request Withdrawal</h2>
                
                <div id="alert" class="alert"></div>

                <form id="withdrawalForm">
                    <div class="form-group">
                        <label for="cryptoSelect">Cryptocurrency</label>
                        <select id="cryptoSelect" required>
                            <option value="">Select cryptocurrency...</option>
                            <option value="BTC">Bitcoin (BTC)</option>
                            <option value="ETH">Ethereum (ETH)</option>
                            <option value="USDT">Tether (USDT)</option>
                            <option value="BNB">Binance Coin (BNB)</option>
                            <option value="USDC">USD Coin (USDC)</option>
                            <option value="XRP">Ripple (XRP)</option>
                            <option value="ADA">Cardano (ADA)</option>
                            <option value="SOL">Solana (SOL)</option>
                            <option value="DOT">Polkadot (DOT)</option>
                            <option value="DOGE">Dogecoin (DOGE)</option>
                        </select>
                    </div>

                    <div id="balanceDisplay" class="balance-info" style="display: none;">
                        <div class="label">Available Balance</div>
                        <div class="value" id="availableBalance">0.00000000</div>
                    </div>

                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="number" id="amount" step="0.00000001" min="0" placeholder="0.00000000" required>
                    </div>

                    <div class="form-group">
                        <label for="withdrawalAddress">Destination Wallet Address</label>
                        <input type="text" id="withdrawalAddress" placeholder="Enter wallet address" required>
                    </div>

                    <div id="feeCalculation" style="display: none;">
                        <div class="fee-info">
                            <span class="label">Withdrawal Amount:</span>
                            <span class="value" id="withdrawalAmount">0.00000000</span>
                        </div>
                        <div class="fee-info">
                            <span class="label">Network Fee (1%):</span>
                            <span class="value" id="networkFee">0.00000000</span>
                        </div>
                        <div class="fee-info" style="background: rgba(102, 126, 234, 0.1);">
                            <span class="label">You Will Receive:</span>
                            <span class="value" id="finalAmount" style="color: #667eea;">0.00000000</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        Submit Withdrawal Request
                    </button>
                </form>
            </div>

            <!-- Withdrawal History -->
            <div class="glass-card">
                <h2 class="card-title">📋 Recent Withdrawals</h2>
                <div id="withdrawalHistory">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Loading withdrawals...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let userBalances = {};

        $(document).ready(function() {
            loadBalances();
            loadWithdrawals();

            // Handle crypto selection
            $('#cryptoSelect').on('change', function() {
                const crypto = $(this).val();
                if (crypto && userBalances[crypto]) {
                    $('#balanceDisplay').show();
                    $('#availableBalance').text(userBalances[crypto].toFixed(8) + ' ' + crypto);
                } else {
                    $('#balanceDisplay').hide();
                }
                calculateFees();
            });

            // Calculate fees on amount change
            $('#amount').on('input', calculateFees);

            // Handle form submission
            $('#withdrawalForm').on('submit', function(e) {
                e.preventDefault();
                submitWithdrawal();
            });
        });

        function loadBalances() {
            $.ajax({
                url: 'api/withdrawal.php?action=balance',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        userBalances = {};
                        response.balances.forEach(function(item) {
                            userBalances[item.crypto] = item.balance;
                        });
                    }
                },
                error: function() {
                    console.error('Failed to load balances');
                }
            });
        }

        function calculateFees() {
            const crypto = $('#cryptoSelect').val();
            const amount = parseFloat($('#amount').val()) || 0;

            if (crypto && amount > 0) {
                const fee = amount * 0.01; // 1% fee
                const finalAmount = amount - fee;

                $('#withdrawalAmount').text(amount.toFixed(8) + ' ' + crypto);
                $('#networkFee').text(fee.toFixed(8) + ' ' + crypto);
                $('#finalAmount').text(finalAmount.toFixed(8) + ' ' + crypto);
                $('#feeCalculation').show();
            } else {
                $('#feeCalculation').hide();
            }
        }

        function submitWithdrawal() {
            const crypto = $('#cryptoSelect').val();
            const amount = parseFloat($('#amount').val());
            const address = $('#withdrawalAddress').val().trim();

            // Validation
            if (!crypto || amount <= 0 || !address) {
                showAlert('Please fill in all fields', 'error');
                return;
            }

            // Check balance
            if (!userBalances[crypto] || amount > userBalances[crypto]) {
                showAlert('Insufficient balance', 'error');
                return;
            }

            // Disable submit button
            $('#submitBtn').prop('disabled', true).text('Processing...');

            $.ajax({
                url: 'api/withdrawal.php?action=create',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    crypto_symbol: crypto,
                    crypto_amount: amount,
                    withdrawal_address: address
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Withdrawal request submitted! Pending admin approval.', 'success');
                        $('#withdrawalForm')[0].reset();
                        $('#balanceDisplay').hide();
                        $('#feeCalculation').hide();
                        loadBalances();
                        loadWithdrawals();
                    } else {
                        showAlert(response.message || 'Failed to submit withdrawal', 'error');
                    }
                },
                error: function() {
                    showAlert('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $('#submitBtn').prop('disabled', false).text('Submit Withdrawal Request');
                }
            });
        }

        function loadWithdrawals() {
            $.ajax({
                url: 'api/withdrawal.php?action=list',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayWithdrawals(response.withdrawals);
                    } else {
                        $('#withdrawalHistory').html('<div class="empty-state"><div class="empty-state-icon">📭</div><p>Failed to load withdrawals</p></div>');
                    }
                },
                error: function() {
                    $('#withdrawalHistory').html('<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Network error</p></div>');
                }
            });
        }

        function displayWithdrawals(withdrawals) {
            if (withdrawals.length === 0) {
                $('#withdrawalHistory').html('<div class="empty-state"><div class="empty-state-icon">📭</div><p>No withdrawals yet</p></div>');
                return;
            }

            let html = '';
            withdrawals.forEach(function(w) {
                const statusClass = 'status-' + w.status;
                const canCancel = w.status === 'pending';

                html += `
                    <div class="withdrawal-item">
                        <div class="withdrawal-header">
                            <span class="crypto-badge">${w.crypto}</span>
                            <span class="status-badge ${statusClass}">${w.status.toUpperCase()}</span>
                        </div>
                        <div class="withdrawal-details">
                            <div><strong>Amount:</strong> ${parseFloat(w.amount).toFixed(8)} ${w.crypto}</div>
                            <div><strong>Fee:</strong> ${parseFloat(w.fee).toFixed(8)} ${w.crypto}</div>
                            <div><strong>USD Value:</strong> $${parseFloat(w.usd_amount).toFixed(2)}</div>
                            <div><strong>Date:</strong> ${new Date(w.date).toLocaleString()}</div>
                            ${w.processed_date ? `<div><strong>Processed:</strong> ${new Date(w.processed_date).toLocaleString()}</div>` : ''}
                            ${w.admin_notes ? `<div><strong>Notes:</strong> ${w.admin_notes}</div>` : ''}
                        </div>
                        <div class="withdrawal-address">
                            <strong>Address:</strong> ${w.address}
                        </div>
                        ${canCancel ? `<button class="cancel-btn" onclick="cancelWithdrawal(${w.id})">Cancel Request</button>` : ''}
                    </div>
                `;
            });

            $('#withdrawalHistory').html(html);
        }

        function cancelWithdrawal(withdrawalId) {
            if (!confirm('Are you sure you want to cancel this withdrawal? Funds will be refunded to your wallet.')) {
                return;
            }

            $.ajax({
                url: 'api/withdrawal.php?action=cancel',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ withdrawal_id: withdrawalId }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Withdrawal cancelled successfully', 'success');
                        loadBalances();
                        loadWithdrawals();
                    } else {
                        showAlert(response.message || 'Failed to cancel withdrawal', 'error');
                    }
                },
                error: function() {
                    showAlert('Network error. Please try again.', 'error');
                }
            });
        }

        function showAlert(message, type) {
            const alert = $('#alert');
            alert.removeClass('alert-success alert-error');
            alert.addClass('alert-' + type);
            alert.text(message);
            alert.show();

            setTimeout(function() {
                alert.fadeOut();
            }, 5000);
        }
    </script>
</body>
</html>
