<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include('../config/db.php');
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// Fetch admin statistics
$total_users = 0;
$total_accounts = 0;
$total_balance = 0;
$recent_transactions = [];

// Get total users
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_users = $row['total'];
$stmt->close();

// Get total accounts
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM accounts");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_accounts = $row['total'];
$stmt->close();

// Get total balance
$stmt = $conn->prepare("SELECT SUM(balance) as total FROM accounts");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_balance = $row['total'] ?? 0;
$stmt->close();

// Get recent transactions
$stmt = $conn->prepare("
    SELECT t.*, u.name as user_name, a.account_no 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    JOIN users u ON a.user_id = u.id 
    ORDER BY t.date DESC 
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
$recent_transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SecureBank</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .sidebar-item {
            transition: all 0.2s ease;
        }
        
        .sidebar-item:hover {
            background-color: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
        }
        
        .sidebar-item.active {
            background-color: rgba(59, 130, 246, 0.15);
            border-left: 4px solid #3b82f6;
            color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-university text-red-600 text-2xl mr-2"></i>
                        <span class="text-xl font-bold text-gray-900">SecureBank Admin</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Welcome, <?= $admin_name ?></span>
                    <a href="logout.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-white min-h-screen border-r border-gray-200">
            <nav class="mt-5 px-4 space-y-2">
                <a href="index.php" class="sidebar-item active flex items-center px-4 py-3 text-sm font-medium text-gray-900 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3 text-lg text-red-600"></i>
                    Dashboard
                </a>
                <a href="users.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                    <i class="fas fa-users mr-3 text-lg text-gray-400"></i>
                    Manage Users
                </a>
                <a href="reports.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                    <i class="fas fa-chart-bar mr-3 text-lg text-gray-400"></i>
                    Reports
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Users</h3>
                            <p class="text-2xl font-semibold text-gray-900"><?= $total_users ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-wallet text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Accounts</h3>
                            <p class="text-2xl font-semibold text-gray-900"><?= $total_accounts ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-money-bill-wave text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Balance</h3>
                            <p class="text-2xl font-semibold text-gray-900">$<?= number_format($total_balance, 2) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Transactions</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remark</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recent_transactions)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No transactions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= $transaction['user_name'] ?></div>
                                        <div class="text-sm text-gray-500">****<?= substr($transaction['account_no'], -4) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?= $transaction['type'] == 'deposit' ? 'bg-green-100 text-green-800' : 
                                               ($transaction['type'] == 'withdraw' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') ?>">
                                            <?= ucfirst($transaction['type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm 
                                        <?= $transaction['type'] == 'deposit' ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $transaction['type'] == 'deposit' ? '+' : '-' ?><?= number_format($transaction['amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y g:i A', strtotime($transaction['date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?= $transaction['remark'] ?: 'N/A' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>