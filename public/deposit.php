<?php
include('../config/db.php');
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$message = '';
$message_type = '';

// Check if user has accounts, if not create one automatically
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

// If user has no accounts, create one automatically
if (empty($accounts)) {
    // Create a new account for the user
    $stmt = $conn->prepare("INSERT INTO accounts (user_id, balance) VALUES (?, 0.00)");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $new_account_no = $stmt->insert_id;
        $accounts[] = ['account_no' => $new_account_no, 'balance' => 0.00];
        $message = "A new account (#$new_account_no) has been created for you!";
        $message_type = 'success';
    }
    $stmt->close();
}

// Fetch recent deposits
$deposit_history = [];
$stmt = $conn->prepare("
    SELECT t.*, a.account_no 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? AND t.type = 'deposit'
    ORDER BY t.date DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $deposit_history[] = $row;
}
$stmt->close();

// Process deposit form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $to_account = $_POST['to_account'];
    $amount = floatval($_POST['amount']);
    $deposit_method = $_POST['deposit_method'];
    $remark = $_POST['remark'] ?? '';

    // Validate inputs
    if ($amount <= 0) {
        $message = "Amount must be greater than zero";
        $message_type = 'error';
    } elseif (!is_numeric($to_account)) {
        $message = "Please select a valid account";
        $message_type = 'error';
    } else {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Verify account belongs to user
            $stmt = $conn->prepare("SELECT account_no FROM accounts WHERE account_no = ? AND user_id = ?");
            $stmt->bind_param("ii", $to_account, $user_id);
            $stmt->execute();
            $account_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$account_data) {
                throw new Exception("Invalid destination account");
            }

            // Add to account
            $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE account_no = ?");
            $stmt->bind_param("di", $amount, $to_account);
            $stmt->execute();
            $stmt->close();

            // Record transaction
            $stmt = $conn->prepare("INSERT INTO transactions (account_no, type, amount, remark) VALUES (?, 'deposit', ?, ?)");
            $deposit_remark = "Deposit - $deposit_method";
            if ($remark) {
                $deposit_remark .= " - $remark";
            }
            $stmt->bind_param("ids", $to_account, $amount, $deposit_remark);
            $stmt->execute();
            $transaction_id = $stmt->insert_id;
            $stmt->close();

            // Commit transaction
            $conn->commit();

            $message = "Deposit completed successfully! Transaction ID: #$transaction_id";
            $message_type = 'success';

            // Refresh page data
            header("Location: deposit.php?success=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Deposit failed: " . $e->getMessage();
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
    <title>Deposit Funds | SecureBank</title>
    
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
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-university text-blue-600 text-2xl mr-2"></i>
                        <span class="text-xl font-bold text-gray-900">SecureBank</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <div class="h-8 w-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-700 flex items-center justify-center text-white font-medium">
                                <?= strtoupper(substr($name, 0, 1)) ?>
                            </div>
                            <span class="ml-2 text-gray-700 font-medium hidden md:block"><?= $name ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8 fade-in">
            <h1 class="text-3xl font-bold text-gray-900">Deposit Funds</h1>
            <p class="mt-2 text-gray-600">Add money to your accounts securely</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg fade-in">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-green-800 font-medium">Deposit completed successfully!</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($message && !isset($_GET['success'])): ?>
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
            <!-- Deposit Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center">
                            <i class="fas fa-hand-holding-usd text-white text-lg"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-bold text-gray-900">Make Deposit</h2>
                            <p class="text-gray-600">Quick and secure deposits</p>
                        </div>
                    </div>

                    <?php if(empty($accounts)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Accounts Found</h3>
                        <p class="text-gray-600 mb-4">We're creating your first account. Please refresh the page.</p>
                        <a href="deposit.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-refresh mr-2"></i>
                            Refresh Page
                        </a>
                    </div>
                    <?php else: ?>
                    <form method="POST" class="space-y-4" id="depositForm">
                        <!-- To Account -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">To Account</label>
                            <select name="to_account" id="to_account" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                <option value="">Select Destination Account</option>
                                <?php foreach($accounts as $account): ?>
                                <option value="<?= $account['account_no'] ?>">
                                    Account <?= $account['account_no'] ?> - $<?= number_format($account['balance'], 2) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="mt-2 text-sm text-gray-500">
                                Total Balance: <span class="font-medium text-green-600">$<?= number_format($total_balance, 2) ?></span>
                            </div>
                        </div>

                        <!-- Deposit Method -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Deposit Method</label>
                            <select name="deposit_method" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                <option value="">Select Deposit Method</option>
                                <option value="Cash Deposit">Cash Deposit</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Mobile Deposit">Mobile Deposit</option>
                                <option value="Direct Deposit">Direct Deposit</option>
                                <option value="Wire Transfer">Wire Transfer</option>
                            </select>
                        </div>

                        <!-- Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500">$</span>
                                </div>
                                <input type="number" step="0.01" name="amount" id="amount" required 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                       placeholder="0.00">
                            </div>
                        </div>

                        <!-- Quick Amount Buttons -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quick Amounts</label>
                            <div class="grid grid-cols-3 gap-2">
                                <?php
                                $quick_amounts = [50, 100, 200, 500, 1000, 5000];
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

                        <!-- Remark -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                            <input type="text" name="remark" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                   placeholder="e.g., Salary, Freelance payment, Gift, etc.">
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 px-4 rounded-lg font-semibold transition-all duration-200 transform hover:-translate-y-0.5 shadow-lg">
                            <i class="fas fa-hand-holding-usd mr-2"></i>
                            Deposit Funds
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deposit History & Statistics -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Account Summary -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Summary</h3>
                    <div class="space-y-3">
                        <?php foreach($accounts as $account): ?>
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div>
                                <p class="text-sm text-blue-600">Account <?= $account['account_no'] ?></p>
                                <p class="text-lg font-bold text-blue-700">$<?= number_format($account['balance'], 2) ?></p>
                            </div>
                            <i class="fas fa-wallet text-blue-500 text-xl"></i>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Deposits -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Deposits</h3>
                        <span class="text-sm text-gray-500">Last 5</span>
                    </div>
                    <div class="space-y-3 max-h-80 overflow-y-auto">
                        <?php if(empty($deposit_history)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-hand-holding-usd text-3xl text-gray-300 mb-2"></i>
                            <p class="text-gray-500">No deposit history</p>
                            <p class="text-sm text-gray-400">Your deposits will appear here</p>
                        </div>
                        <?php else: ?>
                            <?php foreach($deposit_history as $deposit): ?>
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                                        <i class="fas fa-arrow-down text-green-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 truncate" style="max-width: 120px;">
                                            <?= $deposit['remark'] ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= date('M j, Y', strtotime($deposit['date'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-green-600">+$<?= number_format($deposit['amount'], 2) ?></p>
                                    <p class="text-xs text-gray-500">Completed</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="dashboard.php" class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-tachometer-alt mr-2"></i>
                            Back to Dashboard
                        </a>
                        <a href="transfer.php" class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-exchange-alt mr-2"></i>
                            Make Transfer
                        </a>
                        <a href="withdraw.php" class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-money-bill-wave mr-2"></i>
                            Withdraw Funds
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Quick amount buttons
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
        }

        // Form validation
        document.getElementById('depositForm')?.addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const toAccount = document.getElementById('to_account').value;
            const depositMethod = document.querySelector('select[name="deposit_method"]').value;

            if (!toAccount) {
                e.preventDefault();
                alert('Please select a destination account');
                return;
            }

            if (!depositMethod) {
                e.preventDefault();
                alert('Please select a deposit method');
                return;
            }

            if (amount <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount greater than zero');
                return;
            }
        });

        // Add animation to cards on scroll
        const observerOptions = {
            threshold: 0.1
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