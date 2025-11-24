<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// Check admin status
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || !$result['is_admin']) {
    header('Location: dashboard.php');
    exit;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Withdrawal Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: #fff;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
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
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
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
            color: #ffd700;
            background: rgba(255, 215, 0, 0.1);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
        }

        .stat-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
        }

        .stat-pending { border-left: 4px solid #fbbf24; }
        .stat-approved { border-left: 4px solid #34d399; }
        .stat-rejected { border-left: 4px solid #f87171; }
        .stat-total { border-left: 4px solid #60a5fa; }

        /* Tabs */
        .tabs {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 10px;
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
        }

        .tab {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: transparent;
            color: rgba(255, 255, 255, 0.6);
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .tab.active {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #1a1a2e;
        }

        /* Glass Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: rgba(255, 255, 255, 0.05);
        }

        th {
            padding: 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        td {
            padding: 18px 15px;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        tbody tr {
            transition: all 0.3s;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .badge-completed {
            background: rgba(52, 211, 153, 0.2);
            color: #34d399;
        }

        .badge-rejected {
            background: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }

        .badge-cancelled {
            background: rgba(156, 163, 175, 0.2);
            color: #9ca3af;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 8px;
        }

        .btn-approve {
            background: rgba(52, 211, 153, 0.2);
            border: 1px solid rgba(52, 211, 153, 0.4);
            color: #34d399;
        }

        .btn-approve:hover {
            background: rgba(52, 211, 153, 0.3);
        }

        .btn-reject {
            background: rgba(248, 113, 113, 0.2);
            border: 1px solid rgba(248, 113, 113, 0.4);
            color: #f87171;
        }

        .btn-reject:hover {
            background: rgba(248, 113, 113, 0.3);
        }

        .btn-view {
            background: rgba(96, 165, 250, 0.2);
            border: 1px solid rgba(96, 165, 250, 0.4);
            color: #60a5fa;
        }

        .btn-view:hover {
            background: rgba(96, 165, 250, 0.3);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(26, 26, 46, 0.98);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 35px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 25px;
            color: #ffd700;
        }

        .modal-field {
            margin-bottom: 20px;
        }

        .modal-field label {
            display: block;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 8px;
        }

        .modal-field .value {
            font-size: 15px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            word-break: break-all;
        }

        .modal-field textarea {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            resize: vertical;
        }

        .modal-field input {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .modal-actions button {
            flex: 1;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .btn-modal-approve {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            color: #fff;
        }

        .btn-modal-reject {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: #fff;
        }

        .btn-modal-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
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

        /* Loading */
        .loading {
            text-align: center;
            padding: 60px;
            color: rgba(255, 255, 255, 0.5);
        }

        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid #ffd700;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: rgba(255, 255, 255, 0.4);
        }

        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 15px;
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>👨‍💼 Withdrawal Management</h1>
            <div class="nav-links">
                <a href="admin-dashboard.php">📊 Dashboard</a>
                <a href="admin-users.php">👥 Users</a>
                <a href="admin-logs.php">📋 Logs</a>
            </div>
        </div>

        <!-- Alert -->
        <div id="alert" class="alert"></div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card stat-pending">
                <div class="icon">⏳</div>
                <div class="value" id="statPending">0</div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card stat-approved">
                <div class="icon">✅</div>
                <div class="value" id="statApproved">0</div>
                <div class="label">Approved Today</div>
            </div>
            <div class="stat-card stat-rejected">
                <div class="icon">❌</div>
                <div class="value" id="statRejected">0</div>
                <div class="label">Rejected Today</div>
            </div>
            <div class="stat-card stat-total">
                <div class="icon">💰</div>
                <div class="value" id="statTotal">$0</div>
                <div class="label">Total Volume</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" data-status="pending">⏳ Pending</button>
            <button class="tab" data-status="completed">✅ Approved</button>
            <button class="tab" data-status="rejected">❌ Rejected</button>
            <button class="tab" data-status="all">📋 All</button>
        </div>

        <!-- Withdrawals Table -->
        <div class="glass-card">
            <div class="table-container">
                <div id="tableContent">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Loading withdrawals...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✅ Approve Withdrawal</div>
            <div class="modal-field">
                <label>Withdrawal ID</label>
                <div class="value" id="modalWithdrawalId"></div>
            </div>
            <div class="modal-field">
                <label>User</label>
                <div class="value" id="modalUser"></div>
            </div>
            <div class="modal-field">
                <label>Amount</label>
                <div class="value" id="modalAmount"></div>
            </div>
            <div class="modal-field">
                <label>Destination Address</label>
                <div class="value" id="modalAddress" style="font-family: 'Courier New', monospace; font-size: 12px;"></div>
            </div>
            <div class="modal-field">
                <label>Transaction Hash (Optional)</label>
                <input type="text" id="txHash" placeholder="Enter blockchain transaction hash">
            </div>
            <div class="modal-field">
                <label>Admin Notes</label>
                <textarea id="approvalNotes" rows="3" placeholder="Add any notes...">Approved</textarea>
            </div>
            <div class="modal-actions">
                <button class="btn-modal-approve" onclick="confirmApproval()">Approve Withdrawal</button>
                <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">❌ Reject Withdrawal</div>
            <div class="modal-field">
                <label>Withdrawal ID</label>
                <div class="value" id="rejectModalWithdrawalId"></div>
            </div>
            <div class="modal-field">
                <label>User</label>
                <div class="value" id="rejectModalUser"></div>
            </div>
            <div class="modal-field">
                <label>Amount</label>
                <div class="value" id="rejectModalAmount"></div>
            </div>
            <div class="modal-field">
                <label>Reason for Rejection</label>
                <textarea id="rejectionNotes" rows="4" placeholder="Explain why this withdrawal is being rejected...">Security verification required</textarea>
            </div>
            <div class="modal-actions">
                <button class="btn-modal-reject" onclick="confirmRejection()">Reject Withdrawal</button>
                <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentWithdrawalId = null;
        let currentStatus = 'pending';

        $(document).ready(function() {
            loadWithdrawals('pending');
            loadStats();

            // Tab switching
            $('.tab').on('click', function() {
                $('.tab').removeClass('active');
                $(this).addClass('active');
                currentStatus = $(this).data('status');
                loadWithdrawals(currentStatus);
            });

            // Auto-refresh every 30 seconds
            setInterval(function() {
                loadWithdrawals(currentStatus);
                loadStats();
            }, 30000);
        });

        function loadWithdrawals(status) {
            $.ajax({
                url: 'api/admin-withdrawals.php?action=list&status=' + status,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayWithdrawals(response.withdrawals);
                    } else {
                        $('#tableContent').html('<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Failed to load withdrawals</p></div>');
                    }
                },
                error: function() {
                    $('#tableContent').html('<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Network error</p></div>');
                }
            });
        }

        function displayWithdrawals(withdrawals) {
            if (withdrawals.length === 0) {
                $('#tableContent').html('<div class="empty-state"><div class="empty-state-icon">📭</div><p>No withdrawals found</p></div>');
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Crypto</th>
                            <th>Amount</th>
                            <th>USD Value</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            withdrawals.forEach(function(w) {
                const statusClass = 'badge-' + w.status;
                const isPending = w.status === 'pending';

                html += `
                    <tr>
                        <td>#${w.id}</td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">${w.username.charAt(0).toUpperCase()}</div>
                                <div>
                                    <div style="font-weight: 600;">${w.username}</div>
                                    <div style="font-size: 12px; color: rgba(255,255,255,0.5);">${w.email}</div>
                                </div>
                            </div>
                        </td>
                        <td><strong>${w.crypto}</strong></td>
                        <td>${parseFloat(w.amount).toFixed(8)}</td>
                        <td>$${parseFloat(w.usd_amount).toFixed(2)}</td>
                        <td><span class="badge ${statusClass}">${w.status.toUpperCase()}</span></td>
                        <td>${new Date(w.date).toLocaleString()}</td>
                        <td>
                            ${isPending ? `
                                <button class="btn btn-approve" onclick="openApprovalModal(${w.id}, '${w.username}', '${w.crypto}', ${w.amount}, '${w.address}')">Approve</button>
                                <button class="btn btn-reject" onclick="openRejectionModal(${w.id}, '${w.username}', '${w.crypto}', ${w.amount})">Reject</button>
                            ` : `
                                <button class="btn btn-view" onclick="viewDetails(${w.id})">View</button>
                            `}
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            $('#tableContent').html(html);
        }

        function loadStats() {
            $.ajax({
                url: 'api/admin-withdrawals.php?action=stats',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#statPending').text(response.stats.pending_count);
                        $('#statApproved').text(response.stats.approved_today);
                        $('#statRejected').text(response.stats.rejected_today);
                        $('#statTotal').text('$' + parseFloat(response.stats.total_volume).toFixed(2));
                    }
                }
            });
        }

        function openApprovalModal(id, username, crypto, amount, address) {
            currentWithdrawalId = id;
            $('#modalWithdrawalId').text('#' + id);
            $('#modalUser').text(username);
            $('#modalAmount').text(amount + ' ' + crypto + ' (Fee deducted)');
            $('#modalAddress').text(address);
            $('#txHash').val('');
            $('#approvalNotes').val('Approved');
            $('#approvalModal').addClass('active');
        }

        function openRejectionModal(id, username, crypto, amount) {
            currentWithdrawalId = id;
            $('#rejectModalWithdrawalId').text('#' + id);
            $('#rejectModalUser').text(username);
            $('#rejectModalAmount').text(amount + ' ' + crypto);
            $('#rejectionNotes').val('Security verification required');
            $('#rejectionModal').addClass('active');
        }

        function closeModal() {
            $('.modal').removeClass('active');
            currentWithdrawalId = null;
        }

        function confirmApproval() {
            const txHash = $('#txHash').val().trim();
            const notes = $('#approvalNotes').val().trim();

            $.ajax({
                url: 'api/withdrawal.php?action=approve',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    withdrawal_id: currentWithdrawalId,
                    tx_hash: txHash,
                    admin_notes: notes
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Withdrawal approved successfully', 'success');
                        closeModal();
                        loadWithdrawals(currentStatus);
                        loadStats();
                    } else {
                        showAlert(response.message || 'Failed to approve withdrawal', 'error');
                    }
                },
                error: function() {
                    showAlert('Network error. Please try again.', 'error');
                }
            });
        }

        function confirmRejection() {
            const notes = $('#rejectionNotes').val().trim();

            if (!notes) {
                showAlert('Please provide a reason for rejection', 'error');
                return;
            }

            $.ajax({
                url: 'api/withdrawal.php?action=reject',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    withdrawal_id: currentWithdrawalId,
                    admin_notes: notes
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Withdrawal rejected and funds refunded', 'success');
                        closeModal();
                        loadWithdrawals(currentStatus);
                        loadStats();
                    } else {
                        showAlert(response.message || 'Failed to reject withdrawal', 'error');
                    }
                },
                error: function() {
                    showAlert('Network error. Please try again.', 'error');
                }
            });
        }

        function viewDetails(id) {
            alert('Viewing details for withdrawal #' + id + '\nFull details view coming soon!');
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
