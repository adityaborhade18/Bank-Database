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

// Fetch ALL transactions for the user with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total_transactions = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_result = $stmt->get_result()->fetch_assoc();
$total_transactions = $total_result['total'];
$stmt->close();

$total_pages = ceil($total_transactions / $limit);

$all_transactions = [];
$stmt = $conn->prepare("
    SELECT t.*, a.account_no, a.user_id,
           (SELECT balance FROM accounts WHERE account_no = a.account_no) as current_balance
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? 
    ORDER BY t.date DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $all_transactions[] = $row;
}
$stmt->close();

// Calculate transaction statistics
$stats = [
    'total_transactions' => $total_transactions,
    'total_deposits' => 0,
    'total_withdrawals' => 0,
    'total_transfers' => 0,
    'current_balance' => 0
];

foreach ($accounts as $account) {
    $stats['current_balance'] += $account['balance'];
}

$stmt = $conn->prepare("
    SELECT type, COUNT(*) as count 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? 
    GROUP BY type
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['type'] == 'deposit') $stats['total_deposits'] = $row['count'];
    if ($row['type'] == 'withdraw') $stats['total_withdrawals'] = $row['count'];
    if ($row['type'] == 'transfer') $stats['total_transfers'] = $row['count'];
}
$stmt->close();

// Process transfer form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from_account = $_POST['from_account'];
    $to_account = $_POST['to_account'];
    $amount = floatval($_POST['amount']);
    $remark = $_POST['remark'] ?? '';

    // Validate inputs
    if ($amount <= 0) {
        $message = "Amount must be greater than zero";
        $message_type = 'error';
    } elseif ($from_account == $to_account) {
        $message = "Cannot transfer to the same account";
        $message_type = 'error';
    } else {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Verify from account belongs to user and has sufficient balance
            $stmt = $conn->prepare("SELECT balance FROM accounts WHERE account_no = ? AND user_id = ?");
            $stmt->bind_param("ii", $from_account, $user_id);
            $stmt->execute();
            $from_account_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$from_account_data) {
                throw new Exception("Invalid source account");
            }

            if ($from_account_data['balance'] < $amount) {
                throw new Exception("Insufficient funds in source account");
            }

            // Verify destination account exists and get user info
            $stmt = $conn->prepare("SELECT a.account_no, u.name FROM accounts a JOIN users u ON a.user_id = u.id WHERE a.account_no = ?");
            $stmt->bind_param("i", $to_account);
            $stmt->execute();
            $to_account_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$to_account_data) {
                throw new Exception("Destination account not found");
            }

            // Update balances
            // Deduct from source account
            $stmt = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE account_no = ?");
            $stmt->bind_param("di", $amount, $from_account);
            $stmt->execute();
            $stmt->close();

            // Add to destination account
            $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE account_no = ?");
            $stmt->bind_param("di", $amount, $to_account);
            $stmt->execute();
            $stmt->close();

            // Record transaction for source account (transfer type)
            $stmt = $conn->prepare("INSERT INTO transactions (account_no, type, amount, remark) VALUES (?, 'transfer', ?, ?)");
            $remark_withdraw = $remark ? "Transfer to A/C: $to_account (".$to_account_data['name'].") - $remark" : "Transfer to A/C: $to_account (".$to_account_data['name'].")";
            $stmt->bind_param("ids", $from_account, $amount, $remark_withdraw);
            $stmt->execute();
            $stmt->close();

            // Record transaction for destination account (deposit type)
            $stmt = $conn->prepare("INSERT INTO transactions (account_no, type, amount, remark) VALUES (?, 'deposit', ?, ?)");
            $stmt->bind_param("ids", $to_account, $amount, $remark_withdraw);
            $stmt->execute();
            $stmt->close();

            // Commit transaction
            $conn->commit();

            $message = "Transfer completed successfully to " . $to_account_data['name'] . "!";
            $message_type = 'success';

            // Refresh page data
            header("Location: transfer.php?success=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Transfer failed: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer & Transactions | SecureBank</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        .transaction-item {
            transition: all 0.2s ease;
        }
        
        .transaction-item:hover {
            background-color: #f9fafb;
            transform: translateX(5px);
        }
        
        .pulse-glow {
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        
        .type-badge {
            transition: all 0.3s ease;
        }
        
        .type-badge:hover {
            transform: scale(1.05);
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
            <h1 class="text-3xl font-bold text-gray-900">Money Transfer</h1>
            <p class="mt-2 text-gray-600">Securely transfer funds and view your transaction history</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg fade-in">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-green-800 font-medium">Transfer completed successfully!</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($message): ?>
        <div class="mb-6 p-4 <?= $message_type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?> rounded-lg fade-in">
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
            <!-- Transfer Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover pulse-glow">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center">
                            <i class="fas fa-paper-plane text-white text-lg"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-bold text-gray-900">Quick Transfer</h2>
                            <p class="text-gray-600">Send money instantly</p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4">
                        <!-- From Account -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">From Account</label>
                            <select name="from_account" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200">
                                <option value="">Select Source Account</option>
                                <?php foreach($accounts as $account): ?>
                                <option value="<?= $account['account_no'] ?>">
                                    Account <?= $account['account_no'] ?> - $<?= number_format($account['balance'], 2) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- To Account -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">To Account Number</label>
                            <input type="number" name="to_account" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200"
                                   placeholder="Enter account number">
                        </div>

                        <!-- Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500">$</span>
                                </div>
                                <input type="number" step="0.01" name="amount" required 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200"
                                       placeholder="0.00">
                            </div>
                        </div>

                        <!-- Remark -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                            <input type="text" name="remark" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200"
                                   placeholder="e.g., Rent payment, Gift, etc.">
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 px-4 rounded-lg font-semibold transition-all duration-200 transform hover:-translate-y-0.5 shadow-lg">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Transfer Now
                        </button>
                    </form>
                </div>

                <!-- Transaction Statistics -->
                <div class="mt-6 bg-white rounded-xl shadow-lg p-6 card-hover">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Statistics</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600"><?= $stats['total_transactions'] ?></div>
                            <div class="text-sm text-blue-600">Total Transactions</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600"><?= $stats['total_deposits'] ?></div>
                            <div class="text-sm text-green-600">Deposits</div>
                        </div>
                        <div class="text-center p-4 bg-red-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-600"><?= $stats['total_withdrawals'] ?></div>
                            <div class="text-sm text-red-600">Withdrawals</div>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600"><?= $stats['total_transfers'] ?></div>
                            <div class="text-sm text-purple-600">Transfers</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Transaction History</h2>
                                <p class="text-gray-600">Your complete financial activity</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500">Total Balance:</span>
                                <span class="text-lg font-bold text-green-600">$<?= number_format($stats['current_balance'], 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction Type Filters -->
                    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                        <div class="flex space-x-2">
                            <button class="type-badge px-3 py-1 bg-primary-100 text-primary-700 rounded-full text-sm font-medium">
                                All (<?= $stats['total_transactions'] ?>)
                            </button>
                            <button class="type-badge px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                                Deposits (<?= $stats['total_deposits'] ?>)
                            </button>
                            <button class="type-badge px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                                Withdrawals (<?= $stats['total_withdrawals'] ?>)
                            </button>
                            <button class="type-badge px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm font-medium">
                                Transfers (<?= $stats['total_transfers'] ?>)
                            </button>
                        </div>
                    </div>

                    <!-- Transactions List -->
                    <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                        <?php if(empty($all_transactions)): ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-exchange-alt text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">No transactions found</p>
                            <p class="text-sm text-gray-400 mt-1">Your transactions will appear here</p>
                        </div>
                        <?php else: ?>
                            <?php foreach($all_transactions as $transaction): ?>
                            <div class="transaction-item p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <?php if($transaction['type'] == 'deposit'): ?>
                                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                                                <i class="fas fa-arrow-down text-green-600"></i>
                                            </div>
                                        <?php elseif($transaction['type'] == 'withdraw'): ?>
                                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                                                <i class="fas fa-arrow-up text-red-600"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                                                <i class="fas fa-exchange-alt text-purple-600"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <div class="font-medium text-gray-900">
                                                <?= ucfirst($transaction['type']) ?> 
                                                <span class="text-xs text-gray-500 ml-2">A/C: <?= $transaction['account_no'] ?></span>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= date('M j, Y g:i A', strtotime($transaction['date'])) ?>
                                            </div>
                                            <?php if($transaction['remark']): ?>
                                            <div class="text-xs text-gray-400 mt-1">
                                                <?= $transaction['remark'] ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right">
                                        <div class="text-lg font-semibold <?= $transaction['type'] == 'deposit' ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $transaction['type'] == 'deposit' ? '+' : '-' ?><?= number_format($transaction['amount'], 2) ?>
                                        </div>
                                        <div class="text-xs text-gray-500 capitalize">
                                            <?= $transaction['type'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500">
                                Showing <?= count($all_transactions) ?> of <?= $total_transactions ?> transactions
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

                <!-- Financial Insights -->
                <div class="mt-6 bg-white rounded-xl shadow-lg p-6 card-hover">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Financial Insights</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-4 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-blue-100">Monthly Spending</p>
                                    <p class="text-2xl font-bold">$2,847</p>
                                </div>
                                <i class="fas fa-chart-bar text-2xl opacity-80"></i>
                            </div>
                        </div>
                        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-4 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-green-100">Savings Goal Progress</p>
                                    <p class="text-2xl font-bold">68%</p>
                                </div>
                                <i class="fas fa-bullseye text-2xl opacity-80"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

        // Filter transactions by type
        document.querySelectorAll('.type-badge').forEach(button => {
            button.addEventListener('click', function() {
                const type = this.textContent.split(' ')[0].toLowerCase();
                // In a real application, this would filter the transactions
                alert('Filtering by: ' + type);
            });
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