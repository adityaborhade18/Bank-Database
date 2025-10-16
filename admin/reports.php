<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include('../config/db.php');
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// Date range filtering
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Default to end of current month
$report_type = $_GET['report_type'] ?? 'overview';

// Validate dates
if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Overall Statistics
$total_users = 0;
$total_accounts = 0;
$total_balance = 0;
$total_transactions = 0;

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

// Get total transactions
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_transactions = $row['total'];
$stmt->close();

// Transaction Statistics for date range
$deposit_stats = [];
$withdrawal_stats = [];
$transfer_stats = [];

// Total deposits in date range
$stmt = $conn->prepare("SELECT SUM(amount) as total, COUNT(*) as count FROM transactions WHERE type = 'deposit' AND DATE(date) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$deposit_stats = $result->fetch_assoc();
$stmt->close();

// Total withdrawals in date range
$stmt = $conn->prepare("SELECT SUM(amount) as total, COUNT(*) as count FROM transactions WHERE type = 'withdraw' AND DATE(date) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$withdrawal_stats = $result->fetch_assoc();
$stmt->close();

// Total transfers in date range
$stmt = $conn->prepare("SELECT SUM(amount) as total, COUNT(*) as count FROM transactions WHERE type = 'transfer' AND DATE(date) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$transfer_stats = $result->fetch_assoc();
$stmt->close();

// Monthly growth data (last 6 months)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    
    // Users
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ?");
    $stmt->bind_param("ss", $month_start, $month_end);
    $stmt->execute();
    $user_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Transactions
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as volume FROM transactions WHERE date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $month_start, $month_end);
    $stmt->execute();
    $tx_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Deposits
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE type = 'deposit' AND date BETWEEN ? AND ?");
    $stmt->bind_param("ss", $month_start, $month_end);
    $stmt->execute();
    $deposit_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $monthly_data[$month_name] = [
        'users' => $user_result['count'] ?? 0,
        'transactions' => $tx_result['count'] ?? 0,
        'volume' => $tx_result['volume'] ?? 0,
        'deposits' => $deposit_result['total'] ?? 0
    ];
}

// Top users by balance
$top_users = [];
$stmt = $conn->prepare("
    SELECT u.name, u.email, SUM(a.balance) as total_balance, COUNT(a.account_no) as account_count 
    FROM users u 
    JOIN accounts a ON u.id = a.user_id 
    GROUP BY u.id 
    ORDER BY total_balance DESC 
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
$top_users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent system activity (last 10 transactions)
$recent_activity = [];
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
$recent_activity = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Transaction type distribution
$type_distribution = [];
$stmt = $conn->prepare("SELECT type, COUNT(*) as count, SUM(amount) as total FROM transactions GROUP BY type");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $type_distribution[$row['type']] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | SecureBank Admin</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background-color: #e5e7eb;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
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
                <a href="index.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3 text-lg text-gray-400"></i>
                    Dashboard
                </a>
                <a href="users.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                    <i class="fas fa-users mr-3 text-lg text-gray-400"></i>
                    Manage Users
                </a>
              
                <a href="reports.php" class="sidebar-item active flex items-center px-4 py-3 text-sm font-medium text-gray-900 rounded-lg">
                    <i class="fas fa-chart-bar mr-3 text-lg text-red-600"></i>
                    Reports
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Reports & Analytics</h1>
                <p class="text-gray-600 mt-2">Comprehensive financial reports and system analytics</p>
            </div>

            <!-- Date Filter -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <form method="GET" class="flex flex-col md:flex-row md:items-end space-y-4 md:space-y-0 md:space-x-4">
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input 
                                type="date" 
                                name="start_date" 
                                value="<?= $start_date ?>" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input 
                                type="date" 
                                name="end_date" 
                                value="<?= $end_date ?>" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                            <select 
                                name="report_type" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            >
                                <option value="overview" <?= $report_type == 'overview' ? 'selected' : '' ?>>Overview</option>
                                <option value="financial" <?= $report_type == 'financial' ? 'selected' : '' ?>>Financial</option>
                                <option value="users" <?= $report_type == 'users' ? 'selected' : '' ?>>User Analytics</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button 
                            type="submit" 
                            class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                        >
                            <i class="fas fa-filter mr-2"></i>
                            Apply Filters
                        </button>
                        <a 
                            href="reports.php" 
                            class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
                        >
                            <i class="fas fa-sync mr-2"></i>
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Summary Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Users</h3>
                            <p class="text-2xl font-semibold text-gray-900"><?= number_format($total_users) ?></p>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-wallet text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Accounts</h3>
                            <p class="text-2xl font-semibold text-gray-900"><?= number_format($total_accounts) ?></p>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white rounded-lg shadow p-6">
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

                <div class="stat-card bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <i class="fas fa-exchange-alt text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Transactions</h3>
                            <p class="text-2xl font-semibold text-gray-900"><?= number_format($total_transactions) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Transaction Volume Chart -->
                <div class="chart-container">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Transaction Volume</h3>
                    <canvas id="volumeChart" height="250"></canvas>
                </div>

                <!-- Transaction Type Distribution -->
                <div class="chart-container">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Type Distribution</h3>
                    <canvas id="typeChart" height="250"></canvas>
                </div>
            </div>

            <!-- Financial Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Period Statistics -->
                <div class="col-span-2 bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Period Statistics (<?= date('M j, Y', strtotime($start_date)) ?> - <?= date('M j, Y', strtotime($end_date)) ?>)</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                            <div>
                                <span class="text-sm font-medium text-blue-800">Total Deposits</span>
                                <p class="text-lg font-semibold text-blue-900">$<?= number_format($deposit_stats['total'] ?? 0, 2) ?></p>
                            </div>
                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
                                <?= number_format($deposit_stats['count'] ?? 0) ?> transactions
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg">
                            <div>
                                <span class="text-sm font-medium text-red-800">Total Withdrawals</span>
                                <p class="text-lg font-semibold text-red-900">$<?= number_format($withdrawal_stats['total'] ?? 0, 2) ?></p>
                            </div>
                            <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm">
                                <?= number_format($withdrawal_stats['count'] ?? 0) ?> transactions
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                            <div>
                                <span class="text-sm font-medium text-green-800">Total Transfers</span>
                                <p class="text-lg font-semibold text-green-900">$<?= number_format($transfer_stats['total'] ?? 0, 2) ?></p>
                            </div>
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                <?= number_format($transfer_stats['count'] ?? 0) ?> transactions
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                            <div>
                                <span class="text-sm font-medium text-purple-800">Net Flow</span>
                                <p class="text-lg font-semibold text-purple-900">
                                    $<?= number_format(($deposit_stats['total'] ?? 0) - ($withdrawal_stats['total'] ?? 0) - ($transfer_stats['total'] ?? 0), 2) ?>
                                </p>
                            </div>
                            <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm">
                                Overall
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Top Users -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Users by Balance</h3>
                    <div class="space-y-3">
                        <?php foreach ($top_users as $index => $user): ?>
                        <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white text-sm font-medium">
                                    <?= $index + 1 ?>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= $user['account_count'] ?> accounts</p>
                                </div>
                            </div>
                            <span class="text-sm font-semibold text-green-600">
                                $<?= number_format($user['total_balance'], 2) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent System Activity</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remark</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recent_activity)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No recent activity</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($activity['user_name']) ?></div>
                                        <div class="text-sm text-gray-500">****<?= substr($activity['account_no'], -4) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?= $activity['type'] == 'deposit' ? 'bg-green-100 text-green-800' : 
                                               ($activity['type'] == 'withdraw' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') ?>">
                                            <?= ucfirst($activity['type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm 
                                        <?= $activity['type'] == 'deposit' ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $activity['type'] == 'deposit' ? '+' : '-' ?><?= number_format($activity['amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y g:i A', strtotime($activity['date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?= $activity['remark'] ?: 'N/A' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Export Section -->
            <div class="mt-8 bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Reports</h3>
                <div class="flex flex-wrap gap-4">
                    <button onclick="exportReport('pdf')" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <i class="fas fa-file-pdf mr-2"></i>
                        Export as PDF
                    </button>
                    <button onclick="exportReport('csv')" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <i class="fas fa-file-csv mr-2"></i>
                        Export as CSV
                    </button>
                    <button onclick="exportReport('excel')" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-file-excel mr-2"></i>
                        Export as Excel
                    </button>
                    <button onclick="printReport()" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        <i class="fas fa-print mr-2"></i>
                        Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Transaction Volume Chart
            const volumeCtx = document.getElementById('volumeChart').getContext('2d');
            const volumeChart = new Chart(volumeCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_keys($monthly_data)) ?>,
                    datasets: [{
                        label: 'Transaction Volume ($)',
                        data: <?= json_encode(array_column($monthly_data, 'volume')) ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'New Users',
                        data: <?= json_encode(array_column($monthly_data, 'users')) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Transaction Type Distribution Chart
            const typeCtx = document.getElementById('typeChart').getContext('2d');
            const typeChart = new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Deposits', 'Withdrawals', 'Transfers'],
                    datasets: [{
                        data: [
                            <?= $type_distribution['deposit']['count'] ?? 0 ?>,
                            <?= $type_distribution['withdraw']['count'] ?? 0 ?>,
                            <?= $type_distribution['transfer']['count'] ?? 0 ?>
                        ],
                        backgroundColor: [
                            '#10b981',
                            '#ef4444',
                            '#3b82f6'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });

        // Export functions
        function exportReport(format) {
            const startDate = '<?= $start_date ?>';
            const endDate = '<?= $end_date ?>';
            
            let url = `export_reports.php?format=${format}&start_date=${startDate}&end_date=${endDate}`;
            
            switch(format) {
                case 'pdf':
                    alert('PDF export functionality would be implemented here');
                    // window.open(url, '_blank');
                    break;
                case 'csv':
                    alert('CSV export functionality would be implemented here');
                    // window.open(url, '_blank');
                    break;
                case 'excel':
                    alert('Excel export functionality would be implemented here');
                    // window.open(url, '_blank');
                    break;
            }
        }

        function printReport() {
            window.print();
        }

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            // You can implement auto-refresh here if needed
            // location.reload();
        }, 300000);
    </script>
</body>
</html>