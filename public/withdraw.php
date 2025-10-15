<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('../config/db.php');
$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

$message = '';
$message_type = '';

// Fetch user's accounts from database
$accounts = [];
$stmt = $conn->prepare("SELECT account_no, balance FROM accounts WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}
$stmt->close();

// Fetch withdrawal history with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

$total_withdrawals = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? AND t.type = 'withdraw'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_result = $stmt->get_result()->fetch_assoc();
$total_withdrawals = $total_result['total'];
$stmt->close();

$total_pages = ceil($total_withdrawals / $limit);

$withdrawal_history = [];
$stmt = $conn->prepare("
    SELECT t.*, a.account_no, a.balance as current_balance
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? AND t.type = 'withdraw'
    ORDER BY t.date DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $withdrawal_history[] = $row;
}
$stmt->close();

// Calculate withdrawal statistics
$stats = [
    'total_withdrawals' => $total_withdrawals,
    'this_month' => 0,
    'last_month' => 0,
    'total_amount' => 0,
    'daily_limit_used' => 0,
    'daily_limit' => 2000
];

// Calculate this month and last month withdrawals
$current_month_start = date('Y-m-01');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

$stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? AND t.type = 'withdraw' 
    AND t.date >= ?
");
$stmt->bind_param("is", $user_id, $current_month_start);
$stmt->execute();
$month_result = $stmt->get_result()->fetch_assoc();
$stats['this_month'] = $month_result['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? AND t.type = 'withdraw' 
    AND t.date BETWEEN ? AND ?
");
$stmt->bind_param("iss", $user_id, $last_month_start, $last_month_end);
$stmt->execute();
$last_month_result = $stmt->get_result()->fetch_assoc();
$stats['last_month'] = $last_month_result['total'] ?? 0;
$stmt->close();

// Calculate total withdrawal amount
$stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? AND t.type = 'withdraw'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_amount_result = $stmt->get_result()->fetch_assoc();
$stats['total_amount'] = $total_amount_result['total'] ?? 0;
$stmt->close();

// Calculate today's withdrawals
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? AND t.type = 'withdraw' 
    AND DATE(t.date) = ?
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$today_result = $stmt->get_result()->fetch_assoc();
$stats['daily_limit_used'] = $today_result['total'] ?? 0;
$stmt->close();

// Process withdrawal form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from_account = $_POST['from_account'];
    $amount = floatval($_POST['amount']);
    $withdrawal_method = $_POST['withdrawal_method'];
    $remark = $_POST['remark'] ?? '';

    // Validate inputs
    if ($amount <= 0) {
        $message = "Amount must be greater than zero";
        $message_type = 'error';
    } else {
        // Check daily withdrawal limit
        $remaining_daily_limit = $stats['daily_limit'] - $stats['daily_limit_used'];
        if ($amount > $remaining_daily_limit) {
            $message = "Daily withdrawal limit exceeded. Remaining today: $" . number_format($remaining_daily_limit, 2);
            $message_type = 'error';
        } else {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Verify account belongs to user and has sufficient balance
                $stmt = $conn->prepare("SELECT balance FROM accounts WHERE account_no = ? AND user_id = ?");
                $stmt->bind_param("ii", $from_account, $user_id);
                $stmt->execute();
                $account_data = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$account_data) {
                    throw new Exception("Invalid source account");
                }

                if ($account_data['balance'] < $amount) {
                    throw new Exception("Insufficient funds in source account");
                }

                // Deduct from account
                $stmt = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE account_no = ?");
                $stmt->bind_param("di", $amount, $from_account);
                $stmt->execute();
                $stmt->close();

                // Record transaction
                $stmt = $conn->prepare("INSERT INTO transactions (account_no, type, amount, remark) VALUES (?, 'withdraw', ?, ?)");
                $withdrawal_remark = "Withdrawal - $withdrawal_method";
                if ($remark) {
                    $withdrawal_remark .= " - $remark";
                }
                $stmt->bind_param("ids", $from_account, $amount, $withdrawal_remark);
                $stmt->execute();
                $transaction_id = $stmt->insert_id;
                $stmt->close();

                // Commit transaction
                $conn->commit();

                $message = "Withdrawal completed successfully! Transaction ID: #$transaction_id";
                $message_type = 'success';

                // Refresh page data
                header("Location: withdraw.php?success=1");
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Withdrawal failed: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Funds | SecureBank</title>
    
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
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .withdrawal-item {
            transition: all 0.2s ease;
        }
        
        .withdrawal-item:hover {
            background-color: #f9fafb;
            transform: translateX(5px);
        }
        
        .pulse-glow {
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        
        .method-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .method-card:hover {
            transform: scale(1.05);
        }
        
        .method-card.selected {
            border: 2px solid;
            transform: scale(1.05);
        }
        
        .progress-bar {
            transition: width 1s ease-in-out;
        }
        
        .limit-warning {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
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

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8 fade-in">
            <h1 class="text-3xl font-bold text-gray-900">Withdraw Funds</h1>
            <p class="mt-2 text-gray-600">Withdraw money from your accounts securely</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg fade-in">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-green-800 font-medium">Withdrawal completed successfully!</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($message): ?>
        <div class="mb-6 p-4 <?= $message_type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?> rounded-lg fade-in <?= $message_type === 'error' ? 'limit-warning' : '' ?>">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas <?= $message_type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="<?= $message_type === 'success' ? 'text-green-800' : 'text-red-800' ?> font-medium"><?= $message ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Withdrawal Form & Methods -->
            <div class="lg:col-span-2">
                <!-- Withdrawal Methods -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6 card-hover">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Withdrawal Methods</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php
                        $withdrawal_methods = [
                            [
                                'id' => 'atm',
                                'name' => 'ATM Withdrawal',
                                'icon' => 'fa-money-bill-wave',
                                'color' => 'green',
                                'description' => 'Instant cash from any ATM',
                                'fee' => '$2.50',
                                'time' => 'Instant'
                            ],
                            [
                                'id' => 'branch',
                                'name' => 'Bank Branch',
                                'icon' => 'fa-university',
                                'color' => 'blue',
                                'description' => 'Visit any branch location',
                                'fee' => 'Free',
                                'time' => 'Immediate'
                            ],
                            [
                                'id' => 'transfer',
                                'name' => 'Bank Transfer',
                                'icon' => 'fa-exchange-alt',
                                'color' => 'purple',
                                'description' => 'Transfer to another bank',
                                'fee' => '$1.50',
                                'time' => '1-2 hours'
                            ]
                        ];
                        
                        foreach($withdrawal_methods as $method): 
                        ?>
                        <div class="method-card text-center p-4 rounded-lg border-2 border-gray-200 bg-white"
                             data-method="<?= $method['id'] ?>"
                             onclick="selectMethod('<?= $method['id'] ?>')">
                            <div class="w-12 h-12 rounded-full bg-<?= $method['color'] ?>-100 flex items-center justify-center mx-auto mb-3">
                                <i class="fas <?= $method['icon'] ?> text-<?= $method['color'] ?>-600 text-lg"></i>
                            </div>
                            <h3 class="font-semibold text-gray-900 mb-1"><?= $method['name'] ?></h3>
                            <p class="text-xs text-gray-600 mb-2"><?= $method['description'] ?></p>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>Fee: <?= $method['fee'] ?></span>
                                <span>Time: <?= $method['time'] ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Withdrawal Form -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover pulse-glow">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-red-500 to-red-600 flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-white text-lg"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-bold text-gray-900">Withdraw Funds</h2>
                            <p class="text-gray-600">Quick and secure withdrawals</p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4" id="withdrawalForm">
                        <!-- Selected Method Display -->
                        <div id="selectedMethodDisplay" class="hidden p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-blue-500 mr-2"></i>
                                    <span class="text-blue-700 font-medium" id="selectedMethodText">Method selected</span>
                                </div>
                                <button type="button" onclick="clearMethod()" class="text-blue-500 hover:text-blue-700">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- From Account -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">From Account</label>
                            <select name="from_account" id="from_account" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200">
                                <option value="">Select Source Account</option>
                                <?php foreach($accounts as $account): ?>
                                <option value="<?= $account['account_no'] ?>" data-balance="<?= $account['balance'] ?>">
                                    Account <?= $account['account_no'] ?> - $<?= number_format($account['balance'], 2) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="account_balance" class="mt-2 text-sm text-gray-500 hidden">
                                Available Balance: <span id="balance_amount" class="font-medium"></span>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500">$</span>
                                </div>
                                <input type="number" step="0.01" name="amount" id="amount" required 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200"
                                       placeholder="0.00"
                                       oninput="checkLimits()">
                            </div>
                            <div id="amount_validation" class="mt-2 text-sm hidden"></div>
                        </div>

                        <!-- Daily Limit Progress -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex justify-between text-sm text-yellow-800 mb-2">
                                <span>Daily Withdrawal Limit</span>
                                <span id="limit_used">$<?= number_format($stats['daily_limit_used'], 2) ?></span>
                            </div>
                            <div class="w-full bg-yellow-200 rounded-full h-2">
                                <div id="limit_progress" class="bg-yellow-500 h-2 rounded-full progress-bar" 
                                     style="width: <?= min(100, ($stats['daily_limit_used'] / $stats['daily_limit']) * 100) ?>%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-yellow-600 mt-1">
                                <span>Used: $<?= number_format($stats['daily_limit_used'], 2) ?></span>
                                <span>Limit: $<?= number_format($stats['daily_limit'], 2) ?></span>
                            </div>
                        </div>

                        <!-- Remark -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                            <input type="text" name="remark" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200"
                                   placeholder="e.g., Cash for expenses, Emergency funds, etc.">
                        </div>

                        <!-- Hidden method field -->
                        <input type="hidden" name="withdrawal_method" id="withdrawal_method" value="">

                        <button type="submit" class="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white py-3 px-4 rounded-lg font-semibold transition-all duration-200 transform hover:-translate-y-0.5 shadow-lg">
                            <i class="fas fa-money-bill-wave mr-2"></i>
                            Withdraw Now
                        </button>
                    </form>
                </div>
            </div>

            <!-- Withdrawal History & Statistics -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Quick Statistics -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Withdrawal Statistics</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                            <div>
                                <p class="text-sm text-red-600">This Month</p>
                                <p class="text-xl font-bold text-red-700">$<?= number_format($stats['this_month'], 2) ?></p>
                            </div>
                            <i class="fas fa-calendar text-red-500 text-xl"></i>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                            <div>
                                <p class="text-sm text-orange-600">Last Month</p>
                                <p class="text-xl font-bold text-orange-700">$<?= number_format($stats['last_month'], 2) ?></p>
                            </div>
                            <i class="fas fa-chart-line text-orange-500 text-xl"></i>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                            <div>
                                <p class="text-sm text-purple-600">Total Withdrawn</p>
                                <p class="text-xl font-bold text-purple-700">$<?= number_format($stats['total_amount'], 2) ?></p>
                            </div>
                            <i class="fas fa-receipt text-purple-500 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Quick Amount Buttons -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Withdraw</h3>
                    <div class="grid grid-cols-3 gap-2">
                        <?php
                        $quick_amounts = [50, 100, 200, 500, 1000, 2000];
                        foreach($quick_amounts as $amount):
                        ?>
                        <button type="button" 
                                onclick="setAmount(<?= $amount ?>)" 
                                class="p-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            $<?= number_format($amount) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Withdrawals -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Withdrawals</h3>
                        <span class="text-sm text-gray-500">Last <?= count($withdrawal_history) ?></span>
                    </div>
                    <div class="space-y-3 max-h-80 overflow-y-auto">
                        <?php if(empty($withdrawal_history)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-money-bill-wave text-3xl text-gray-300 mb-2"></i>
                            <p class="text-gray-500">No withdrawal history</p>
                        </div>
                        <?php else: ?>
                            <?php foreach($withdrawal_history as $withdrawal): ?>
                            <div class="withdrawal-item flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                        <i class="fas fa-money-bill-wave text-red-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 truncate" style="max-width: 120px;">
                                            <?= $withdrawal['remark'] ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= date('M j, Y', strtotime($withdrawal['date'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-red-600">-$<?= number_format($withdrawal['amount'], 2) ?></p>
                                    <p class="text-xs text-gray-500">Completed</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Security Notice -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shield-alt text-yellow-500 mt-0.5"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-800">Security Notice</p>
                            <p class="text-xs text-yellow-700 mt-1">
                                For security reasons, large withdrawals may require additional verification. 
                                Keep your transaction details secure.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination for Withdrawal History -->
        <?php if($total_pages > 1): ?>
        <div class="mt-8 bg-white rounded-xl shadow-lg p-6 card-hover">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    Showing <?= count($withdrawal_history) ?> of <?= $total_withdrawals ?> withdrawals
                </div>
                <div class="flex space-x-1">
                    <?php if($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="px-3 py-1 <?= $i == $page ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 border border-gray-300' ?> rounded-md text-sm font-medium hover:bg-gray-50">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle dropdown
        document.getElementById('userMenuBtn').addEventListener('click', function() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenuBtn = document.getElementById('userMenuBtn');
            const userDropdown = document.getElementById('userDropdown');
            
            if (!userMenuBtn.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.add('hidden');
            }
        });

        // Withdrawal method selection
        let selectedMethod = null;

        function selectMethod(methodId) {
            selectedMethod = methodId;
            
            // Update UI
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('selected', 'border-green-500', 'border-blue-500', 'border-purple-500');
            });
            
            const selectedCard = document.querySelector(`[data-method="${methodId}"]`);
            const method = getMethodDetails(methodId);
            selectedCard.classList.add('selected', `border-${method.color}-500`);
            
            // Update hidden field and display
            document.getElementById('withdrawal_method').value = method.name;
            document.getElementById('selectedMethodText').textContent = method.name;
            document.getElementById('selectedMethodDisplay').classList.remove('hidden');
        }

        function clearMethod() {
            selectedMethod = null;
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('selected', 'border-green-500', 'border-blue-500', 'border-purple-500');
            });
            document.getElementById('withdrawal_method').value = '';
            document.getElementById('selectedMethodDisplay').classList.add('hidden');
        }

        function getMethodDetails(methodId) {
            const methods = {
                'atm': { name: 'ATM Withdrawal', color: 'green' },
                'branch': { name: 'Bank Branch', color: 'blue' },
                'transfer': { name: 'Bank Transfer', color: 'purple' }
            };
            return methods[methodId] || { name: 'Unknown', color: 'gray' };
        }

        // Account balance display
        const fromAccount = document.getElementById('from_account');
        const accountBalance = document.getElementById('account_balance');
        const balanceAmount = document.getElementById('balance_amount');

        fromAccount.addEventListener('change', function() {
            const selectedOption = fromAccount.options[fromAccount.selectedIndex];
            if (selectedOption.value) {
                const balance = selectedOption.getAttribute('data-balance');
                balanceAmount.textContent = '$' + parseFloat(balance).toFixed(2);
                accountBalance.classList.remove('hidden');
            } else {
                accountBalance.classList.add('hidden');
            }
        });

        // Quick amount buttons
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
            checkLimits();
        }

        // Limit checking
        function checkLimits() {
            const amountInput = document.getElementById('amount');
            const amount = parseFloat(amountInput.value) || 0;
            const validationDiv = document.getElementById('amount_validation');
            const dailyLimit = <?= $stats['daily_limit'] ?>;
            const dailyUsed = <?= $stats['daily_limit_used'] ?>;
            const remainingDaily = dailyLimit - dailyUsed;

            // Get selected account balance
            const selectedOption = fromAccount.options[fromAccount.selectedIndex];
            const accountBalance = selectedOption.value ? parseFloat(selectedOption.getAttribute('data-balance')) : 0;

            validationDiv.classList.remove('hidden');
            validationDiv.classList.remove('text-green-600', 'text-yellow-600', 'text-red-600');

            if (amount <= 0) {
                validationDiv.innerHTML = '';
                validationDiv.classList.add('hidden');
                return;
            }

            if (amount > accountBalance) {
                validationDiv.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> Amount exceeds account balance`;
                validationDiv.classList.add('text-red-600');
            } else if (amount > remainingDaily) {
                validationDiv.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> Exceeds daily limit. Remaining: $${remainingDaily.toFixed(2)}`;
                validationDiv.classList.add('text-red-600');
            } else if (amount > remainingDaily * 0.8) {
                validationDiv.innerHTML = `<i class="fas fa-info-circle mr-1"></i> Close to daily limit. Remaining: $${remainingDaily.toFixed(2)}`;
                validationDiv.classList.add('text-yellow-600');
            } else {
                validationDiv.innerHTML = `<i class="fas fa-check-circle mr-1"></i> Within limits. Remaining today: $${remainingDaily.toFixed(2)}`;
                validationDiv.classList.add('text-green-600');
            }
        }

        // Form validation
        document.getElementById('withdrawalForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const fromAccountValue = document.getElementById('from_account').value;
            const withdrawalMethod = document.getElementById('withdrawal_method').value;
            const dailyLimit = <?= $stats['daily_limit'] ?>;
            const dailyUsed = <?= $stats['daily_limit_used'] ?>;
            const remainingDaily = dailyLimit - dailyUsed;

            if (!fromAccountValue) {
                e.preventDefault();
                alert('Please select a source account');
                return;
            }

            if (!withdrawalMethod) {
                e.preventDefault();
                alert('Please select a withdrawal method');
                return;
            }

            if (amount <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount greater than zero');
                return;
            }

            // Check if amount exceeds balance
            const selectedOption = fromAccount.options[fromAccount.selectedIndex];
            const balance = parseFloat(selectedOption.getAttribute('data-balance'));

            if (amount > balance) {
                e.preventDefault();
                alert('Insufficient funds in the selected account');
                return;
            }

            if (amount > remainingDaily) {
                e.preventDefault();
                alert(`Daily withdrawal limit exceeded. Remaining today: $${remainingDaily.toFixed(2)}`);
                return;
            }
        });

        // Add animation to cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.card-hover').forEach(card => {
            observer.observe(card);
        });
    </script>
</body>
</html>