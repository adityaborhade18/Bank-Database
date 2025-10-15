<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('../config/db.php');
$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

// Fetch ONLY current user's accounts
$accounts = [];
$total_balance = 0;
$stmt = $conn->prepare("SELECT account_no, balance FROM accounts WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
    $total_balance += $row['balance'];
}
$stmt->close();

// Get primary account (first account of current user)
$primary_account = $accounts[0] ?? null;
$account_balance = $primary_account['balance'] ?? 0;
$account_number = $primary_account ? "**** **** **** " . substr($primary_account['account_no'], -4) : "No Account";

// Fetch recent transactions ONLY for current user
$recent_transactions = [];
if ($primary_account) {
    $stmt = $conn->prepare("
        SELECT * FROM transactions 
        WHERE account_no = ? 
        ORDER BY date DESC 
        LIMIT 4
    ");
    $stmt->bind_param("i", $primary_account['account_no']);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
    $stmt->close();
}

// Calculate monthly income (current month) for current user only
$current_month_start = date('Y-m-01');
$current_month_income = 0;
if ($primary_account) {
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total 
        FROM transactions 
        WHERE account_no = ? AND type = 'deposit' 
        AND date >= ?
    ");
    $stmt->bind_param("is", $primary_account['account_no'], $current_month_start);
    $stmt->execute();
    $income_result = $stmt->get_result()->fetch_assoc();
    $current_month_income = $income_result['total'] ?? 0;
    $stmt->close();
}

// Calculate monthly expenses (current month) for current user only
$current_month_expenses = 0;
if ($primary_account) {
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total 
        FROM transactions 
        WHERE account_no = ? AND type = 'withdraw' 
        AND date >= ?
    ");
    $stmt->bind_param("is", $primary_account['account_no'], $current_month_start);
    $stmt->execute();
    $expenses_result = $stmt->get_result()->fetch_assoc();
    $current_month_expenses = $expenses_result['total'] ?? 0;
    $stmt->close();
}

// Calculate savings goal progress
$savings_goal = 20000;
$savings_progress = min(100, ($total_balance / $savings_goal) * 100);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SecureBank</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .transaction-item {
            transition: all 0.2s ease;
        }
        
        .transaction-item:hover {
            background-color: #f9fafb;
            transform: translateX(5px);
        }
        
        .progress-bar {
            transition: width 1s ease-in-out;
        }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-university text-primary-600 text-2xl mr-2"></i>
                        <span class="text-xl font-bold text-gray-900">SecureBank</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="p-2 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <i class="fas fa-bell"></i>
                            <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-500"></span>
                        </button>
                    </div>
                    
                    <div class="relative">
                        <button id="userMenuBtn" class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <div class="h-8 w-8 rounded-full bg-gradient-to-r from-primary-500 to-primary-700 flex items-center justify-center text-white font-medium">
                                <?= strtoupper(substr($name, 0, 1)) ?>
                            </div>
                            <span class="ml-2 text-gray-700 font-medium hidden md:block"><?= $name ?></span>
                            <i class="fas fa-chevron-down ml-1 text-gray-500 text-xs"></i>
                        </button>
                        
                        <!-- User Dropdown -->
                        <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 border border-gray-200">
                            <a href="dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-tachometer-alt mr-2 text-gray-400"></i>Dashboard
                            </a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2 text-gray-400"></i>Sign out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <div class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-white border-r border-gray-200 pt-16">
            <div class="flex-1 flex flex-col min-h-0">
                <nav class="flex-1 px-4 py-4 space-y-1">
                    <a href="#" class="sidebar-item active flex items-center px-4 py-3 text-sm font-medium text-gray-900 rounded-lg">
                        <i class="fas fa-home mr-3 text-lg text-primary-600"></i>
                        Dashboard
                    </a>
                    
                    <?php if(!empty($accounts)): ?>
                    
                    <?php endif; ?>
                    
                    <a href="transfer.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                        <i class="fas fa-exchange-alt mr-3 text-lg text-gray-400"></i>
                        Transfer Money
                    </a>

                     <a href="deposit.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                        <i class="fas fa-exchange-alt mr-3 text-lg text-gray-400"></i>
                        Deposit Money
                    </a>

                    <a href="transactions.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                        <i class="fas fa-history mr-3 text-lg text-gray-400"></i>
                        Transaction History
                    </a>
                </nav>
                
                <div class="flex-shrink-0 flex border-t border-gray-200 p-4">
                    <div class="w-full bg-primary-50 rounded-lg p-3">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-shield-alt text-primary-600"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-primary-900">Your account is protected</p>
                                <p class="text-xs text-primary-700">Last login: <?= date('M j, Y g:i A') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="md:pl-64 flex flex-col flex-1">
            <main class="flex-1 pb-8">
                <!-- Page header -->
                <div class="bg-white shadow">
                    <div class="px-4 sm:px-6 lg:px-8">
                        <div class="py-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <div class="flex-1 min-w-0">
                                    <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                                        Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= $name ?>
                                    </h1>
                                    <p class="mt-1 text-sm text-gray-500">Welcome to your SecureBank dashboard</p>
                                </div>
                                <div class="mt-4 flex md:mt-0 md:ml-4">
                                    <?php if(!empty($accounts)): ?>
                                    <a href="transfer.php" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        <i class="fas fa-exchange-alt mr-2"></i>
                                        Transfer Money
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats & Balance -->
                <div class="mt-8 px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <!-- Balance Card -->
                        <div class="bg-white overflow-hidden shadow rounded-lg card-hover">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-wallet text-green-500 text-xl"></i>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Total Balance</dt>
                                            <dd>
                                                <div class="text-lg font-medium text-gray-900">$<?= number_format($total_balance, 2) ?></div>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            <?php if(!empty($accounts)): ?>
                            <div class="bg-gray-50 px-5 py-3">
                                <div class="text-sm">
                                    <a href="accounts.php" class="font-medium text-primary-700 hover:text-primary-900">
                                        View <?= count($accounts) ?> Account<?= count($accounts) > 1 ? 's' : '' ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Income Card -->
                        <div class="bg-white overflow-hidden shadow rounded-lg card-hover">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-arrow-down text-green-500 text-xl"></i>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Monthly Income</dt>
                                            <dd>
                                                <div class="text-lg font-medium text-gray-900">$<?= number_format($current_month_income, 2) ?></div>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-5 py-3">
                                <div class="text-sm text-gray-500">
                                    This month's deposits
                                </div>
                            </div>
                        </div>

                        <!-- Expenses Card -->
                        <div class="bg-white overflow-hidden shadow rounded-lg card-hover">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-arrow-up text-red-500 text-xl"></i>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Monthly Expenses</dt>
                                            <dd>
                                                <div class="text-lg font-medium text-gray-900">$<?= number_format($current_month_expenses, 2) ?></div>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-5 py-3">
                                <div class="text-sm text-gray-500">
                                    This month's withdrawals
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="mt-8 px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <!-- Quick Actions -->
                        <div class="bg-white shadow rounded-lg overflow-hidden fade-in">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">Quick Actions</h2>
                                <p class="mt-1 text-sm text-gray-500">Frequently used banking operations</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4 p-6">
                                <a href="transfer.php" class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors card-hover">
                                    <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-md bg-blue-100 text-blue-600">
                                        <i class="fas fa-exchange-alt text-lg"></i>
                                    </div>
                                    <div class="mt-3 text-sm font-medium text-gray-900">Transfer</div>
                                </a>
                                <a href="deposit.php" class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors card-hover">
                                    <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-md bg-green-100 text-green-600">
                                        <i class="fas fa-hand-holding-usd text-lg"></i>
                                    </div>
                                    <div class="mt-3 text-sm font-medium text-gray-900">Deposit</div>
                                </a>
                                <a href="withdraw.php" class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors card-hover">
                                    <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-md bg-red-100 text-red-600">
                                        <i class="fas fa-money-bill-wave text-lg"></i>
                                    </div>
                                    <div class="mt-3 text-sm font-medium text-gray-900">Withdraw</div>
                                </a>
                                <a href="transactions.php" class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors card-hover">
                                    <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-md bg-purple-100 text-purple-600">
                                        <i class="fas fa-history text-lg"></i>
                                    </div>
                                    <div class="mt-3 text-sm font-medium text-gray-900">History</div>
                                </a>
                            </div>
                        </div>

                        <!-- Recent Transactions -->
                        <div class="bg-white shadow rounded-lg overflow-hidden fade-in">
                            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-medium text-gray-900">Recent Transactions</h2>
                                    <p class="mt-1 text-sm text-gray-500">Your latest account activity</p>
                                </div>
                                <a href="transactions.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">View all</a>
                            </div>
                            <div class="divide-y divide-gray-200">
                                <?php if(empty($recent_transactions)): ?>
                                <div class="p-8 text-center">
                                    <i class="fas fa-exchange-alt text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-500">No transactions found</p>
                                    <p class="text-sm text-gray-400 mt-1">Your transactions will appear here</p>
                                </div>
                                <?php else: ?>
                                    <?php foreach($recent_transactions as $transaction): ?>
                                    <div class="transaction-item p-4 flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <?php if($transaction['type'] == 'deposit'): ?>
                                                    <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                                        <i class="fas fa-arrow-down text-green-600"></i>
                                                    </div>
                                                <?php elseif($transaction['type'] == 'withdraw'): ?>
                                                    <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                                                        <i class="fas fa-arrow-up text-red-600"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <i class="fas fa-exchange-alt text-blue-600"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= $transaction['remark'] ?: ucfirst($transaction['type']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500"><?= date('M j, Y', strtotime($transaction['date'])) ?></div>
                                            </div>
                                        </div>
                                        <div class="text-sm font-medium <?= $transaction['type'] == 'deposit' ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $transaction['type'] == 'deposit' ? '+' : '-' ?>$<?= number_format($transaction['amount'], 2) ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Account Summary -->
                        <?php if($primary_account): ?>
                        <div class="bg-white shadow rounded-lg overflow-hidden fade-in">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">Account Summary</h2>
                                <p class="mt-1 text-sm text-gray-500">Your primary account details</p>
                            </div>
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="text-sm font-medium text-gray-500">Account Number</div>
                                    <div class="text-sm text-gray-900"><?= $account_number ?></div>
                                </div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="text-sm font-medium text-gray-500">Account Type</div>
                                    <div class="text-sm text-gray-900">Primary Account</div>
                                </div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="text-sm font-medium text-gray-500">Current Balance</div>
                                    <div class="text-sm font-semibold text-green-600">$<?= number_format($primary_account['balance'], 2) ?></div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="text-sm font-medium text-gray-500">Total Accounts</div>
                                    <div class="text-sm text-green-600"><?= count($accounts) ?></div>
                                </div>
                                <div class="mt-6">
                                    <a href="accounts.php" class="w-full flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        Manage Accounts
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="bg-white shadow rounded-lg overflow-hidden fade-in">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">Account Setup</h2>
                                <p class="mt-1 text-sm text-gray-500">Get started with your first account</p>
                            </div>
                            <div class="p-6 text-center">
                                <i class="fas fa-wallet text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No Accounts Found</h3>
                                <p class="text-gray-500 mb-4">You don't have any bank accounts yet.</p>
                                <a href="deposit.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                                    <i class="fas fa-plus mr-2"></i>
                                    Create First Account
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Financial Overview -->
                        <div class="bg-white shadow rounded-lg overflow-hidden fade-in">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">Financial Overview</h2>
                                <p class="mt-1 text-sm text-gray-500">Your financial health summary</p>
                            </div>
                            <div class="p-6">
                                <!-- Savings Progress -->
                                <div class="mb-6">
                                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                                        <span>Savings Goal Progress</span>
                                        <span><?= number_format($savings_progress, 0) ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full progress-bar" style="width: <?= $savings_progress ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        $<?= number_format($total_balance, 0) ?> of $<?= number_format($savings_goal, 0) ?>
                                    </div>
                                </div>

                                <!-- Monthly Summary -->
                                <div class="grid grid-cols-2 gap-4 text-center">
                                    <div class="p-3 bg-green-50 rounded-lg">
                                        <div class="text-sm text-green-600">Income</div>
                                        <div class="text-lg font-medium text-green-700">$<?= number_format($current_month_income, 0) ?></div>
                                    </div>
                                    <div class="p-3 bg-red-50 rounded-lg">
                                        <div class="text-sm text-red-600">Expenses</div>
                                        <div class="text-lg font-medium text-red-700">$<?= number_format($current_month_expenses, 0) ?></div>
                                    </div>
                                </div>

                                <!-- Net Savings -->
                                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-center">
                                    <div class="text-sm text-blue-600">Net Savings</div>
                                    <div class="text-lg font-medium text-blue-700">$<?= number_format($current_month_income - $current_month_expenses, 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if(empty($accounts)): ?>
                <!-- Welcome Message for New Users -->
                <div class="mt-8 px-4 sm:px-6 lg:px-8">
                    <div class="bg-gradient-to-r from-primary-500 to-primary-600 rounded-lg shadow-lg p-6 text-white fade-in">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-gift text-3xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-xl font-bold">Welcome to SecureBank!</h3>
                                <p class="mt-1 opacity-90">Get started by creating your first account and making a deposit.</p>
                            </div>
                        </div>
                        <div class="mt-4 flex space-x-3">
                            <a href="deposit.php" class="inline-flex items-center px-4 py-2 bg-white text-primary-600 rounded-lg font-medium hover:bg-gray-50">
                                <i class="fas fa-plus mr-2"></i>
                                Create Account
                            </a>
                            <a href="#" class="inline-flex items-center px-4 py-2 border border-white text-white rounded-lg font-medium hover:bg-white hover:bg-opacity-10">
                                Learn More
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>

            <!-- Footer -->
            <footer class="bg-white border-t border-gray-200 mt-12">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <div class="md:flex md:items-center md:justify-between">
                        <div class="flex justify-center md:justify-start space-x-6 md:order-2">
                            <a href="#" class="text-gray-400 hover:text-gray-500">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-gray-500">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-gray-500">
                                <i class="fab fa-linkedin"></i>
                            </a>
                        </div>
                        <div class="mt-4 md:mt-0 md:order-1">
                            <p class="text-center text-sm text-gray-500">&copy; <?= date('Y') ?> SecureBank. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-10">
        <div class="grid grid-cols-4 gap-1 p-2">
            <a href="#" class="flex flex-col items-center p-2 text-primary-600">
                <i class="fas fa-home mb-1"></i>
                <span class="text-xs">Dashboard</span>
            </a>
            <a href="transfer.php" class="flex flex-col items-center p-2 text-gray-500">
                <i class="fas fa-exchange-alt mb-1"></i>
                <span class="text-xs">Transfer</span>
            </a>
            <a href="transactions.php" class="flex flex-col items-center p-2 text-gray-500">
                <i class="fas fa-history mb-1"></i>
                <span class="text-xs">History</span>
            </a>
            <?php if(!empty($accounts)): ?>
            <a href="accounts.php" class="flex flex-col items-center p-2 text-gray-500">
                <i class="fas fa-wallet mb-1"></i>
                <span class="text-xs">Accounts</span>
            </a>
            <?php else: ?>
            <a href="deposit.php" class="flex flex-col items-center p-2 text-gray-500">
                <i class="fas fa-plus mb-1"></i>
                <span class="text-xs">Create</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Toggle dropdowns
        document.getElementById('userMenuBtn').addEventListener('click', function() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const userMenuBtn = document.getElementById('userMenuBtn');
            const userDropdown = document.getElementById('userDropdown');
            
            if (!userMenuBtn.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>