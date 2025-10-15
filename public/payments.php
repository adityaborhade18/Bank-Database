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

// Predefined billers/categories
$bill_categories = [
    'utilities' => [
        'name' => 'Utilities',
        'icon' => 'fa-bolt',
        'color' => 'yellow',
        'bills' => [
            ['id' => 'ELECTRIC', 'name' => 'Electricity Bill', 'provider' => 'State Electric Co.'],
            ['id' => 'WATER', 'name' => 'Water Bill', 'provider' => 'City Water Board'],
            ['id' => 'GAS', 'name' => 'Gas Bill', 'provider' => 'Natural Gas Inc.'],
            ['id' => 'INTERNET', 'name' => 'Internet Bill', 'provider' => 'Broadband Services']
        ]
    ],
    'telecom' => [
        'name' => 'Telecom',
        'icon' => 'fa-phone',
        'color' => 'blue',
        'bills' => [
            ['id' => 'MOBILE', 'name' => 'Mobile Phone', 'provider' => 'Telecom Plus'],
            ['id' => 'LANDLINE', 'name' => 'Landline', 'provider' => 'Home Phone Co.'],
            ['id' => 'CABLE', 'name' => 'Cable TV', 'provider' => 'Entertainment Network']
        ]
    ],
    'insurance' => [
        'name' => 'Insurance',
        'icon' => 'fa-shield-alt',
        'color' => 'green',
        'bills' => [
            ['id' => 'HEALTH', 'name' => 'Health Insurance', 'provider' => 'HealthCare Inc.'],
            ['id' => 'CAR', 'name' => 'Car Insurance', 'provider' => 'AutoSecure'],
            ['id' => 'HOME', 'name' => 'Home Insurance', 'provider' => 'HomeProtect']
        ]
    ],
    'subscriptions' => [
        'name' => 'Subscriptions',
        'icon' => 'fa-star',
        'color' => 'purple',
        'bills' => [
            ['id' => 'STREAMING', 'name' => 'Streaming Service', 'provider' => 'StreamMax'],
            ['id' => 'GYM', 'name' => 'Gym Membership', 'provider' => 'Fitness Plus'],
            ['id' => 'MAGAZINE', 'name' => 'Magazine', 'provider' => 'ReadMore Publications']
        ]
    ],
    'education' => [
        'name' => 'Education',
        'icon' => 'fa-graduation-cap',
        'color' => 'indigo',
        'bills' => [
            ['id' => 'TUITION', 'name' => 'Tuition Fees', 'provider' => 'University'],
            ['id' => 'COURSE', 'name' => 'Online Course', 'provider' => 'LearnOnline']
        ]
    ]
];

// Fetch payment history
$payment_history = [];
$stmt = $conn->prepare("
    SELECT t.*, a.account_no 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ? AND t.type = 'withdraw' 
    AND t.remark LIKE '%Bill Payment%'
    ORDER BY t.date DESC 
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $payment_history[] = $row;
}
$stmt->close();

// Process bill payment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from_account = $_POST['from_account'];
    $bill_type = $_POST['bill_type'];
    $bill_provider = $_POST['bill_provider'];
    $consumer_id = $_POST['consumer_id'];
    $amount = floatval($_POST['amount']);
    $due_date = $_POST['due_date'];
    $remark = $_POST['remark'] ?? '';

    // Validate inputs
    if ($amount <= 0) {
        $message = "Amount must be greater than zero";
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
            $payment_remark = "Bill Payment: $bill_type - $bill_provider (ID: $consumer_id)";
            if ($remark) {
                $payment_remark .= " - $remark";
            }
            if ($due_date) {
                $payment_remark .= " - Due: $due_date";
            }
            $stmt->bind_param("ids", $from_account, $amount, $payment_remark);
            $stmt->execute();
            $stmt->close();

            // Commit transaction
            $conn->commit();

            $message = "Bill payment completed successfully!";
            $message_type = 'success';

            // Refresh page data
            header("Location: payment.php?success=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Payment failed: " . $e->getMessage();
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
    <title>Bill Payments | SecureBank</title>
    
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
        
        .payment-item {
            transition: all 0.2s ease;
        }
        
        .payment-item:hover {
            background-color: #f9fafb;
            transform: translateX(5px);
        }
        
        .category-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .category-card:hover {
            transform: scale(1.05);
        }
        
        .category-card.active {
            border: 2px solid;
            transform: scale(1.05);
        }
        
        .pulse-glow {
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        
        .bill-option {
            transition: all 0.2s ease;
        }
        
        .bill-option:hover {
            background-color: #f3f4f6;
        }
        
        .bill-option.selected {
            background-color: #dbeafe;
            border-color: #3b82f6;
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
            <h1 class="text-3xl font-bold text-gray-900">Bill Payments</h1>
            <p class="mt-2 text-gray-600">Pay your utility bills, subscriptions, and other payments securely</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg fade-in">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-green-800 font-medium">Bill payment completed successfully!</p>
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
            <!-- Bill Categories & Payment Form -->
            <div class="lg:col-span-2">
                <!-- Bill Categories -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6 card-hover">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Select Bill Category</h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <?php foreach($bill_categories as $category_id => $category): ?>
                        <div class="category-card text-center p-4 rounded-lg border-2 border-gray-200 bg-white"
                             data-category="<?= $category_id ?>"
                             onclick="selectCategory('<?= $category_id ?>')">
                            <div class="w-12 h-12 rounded-full bg-<?= $category['color'] ?>-100 flex items-center justify-center mx-auto mb-2">
                                <i class="fas <?= $category['icon'] ?> text-<?= $category['color'] ?>-600 text-lg"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700"><?= $category['name'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover pulse-glow">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center">
                            <i class="fas fa-file-invoice-dollar text-white text-lg"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-bold text-gray-900">Pay Bill</h2>
                            <p class="text-gray-600">Quick and secure bill payments</p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4" id="paymentForm">
                        <!-- Bill Type Selection -->
                        <div id="billSelectionSection">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Bill Type</label>
                            <div id="billOptions" class="space-y-2 max-h-60 overflow-y-auto p-2 border border-gray-200 rounded-lg">
                                <p class="text-gray-500 text-center py-4">Select a category to view available bills</p>
                            </div>
                        </div>

                        <!-- Consumer ID -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Consumer/Account Number</label>
                            <input type="text" name="consumer_id" id="consumer_id" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200"
                                   placeholder="Enter your account number with the provider">
                        </div>

                        <!-- From Account -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pay From Account</label>
                            <select name="from_account" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200">
                                <option value="">Select Payment Account</option>
                                <?php foreach($accounts as $account): ?>
                                <option value="<?= $account['account_no'] ?>">
                                    Account <?= $account['account_no'] ?> - $<?= number_format($account['balance'], 2) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Amount and Due Date -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Due Date (Optional)</label>
                                <input type="date" name="due_date" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200">
                            </div>
                        </div>

                        <!-- Remark -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                            <input type="text" name="remark" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200"
                                   placeholder="e.g., Monthly bill, Quarter payment, etc.">
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white py-3 px-4 rounded-lg font-semibold transition-all duration-200 transform hover:-translate-y-0.5 shadow-lg">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Pay Bill Now
                        </button>
                    </form>
                </div>
            </div>

            <!-- Payment History & Quick Stats -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Quick Stats -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Statistics</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div>
                                <p class="text-sm text-blue-600">This Month</p>
                                <p class="text-xl font-bold text-blue-700">$347.50</p>
                            </div>
                            <i class="fas fa-calendar text-blue-500 text-xl"></i>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                            <div>
                                <p class="text-sm text-green-600">Last Month</p>
                                <p class="text-xl font-bold text-green-700">$412.30</p>
                            </div>
                            <i class="fas fa-chart-line text-green-500 text-xl"></i>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                            <div>
                                <p class="text-sm text-purple-600">Total Paid</p>
                                <p class="text-xl font-bold text-purple-700">$2,847.90</p>
                            </div>
                            <i class="fas fa-receipt text-purple-500 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Scheduled Payments -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Scheduled Payments</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900">Electricity Bill</p>
                                <p class="text-sm text-gray-500">Due: 15th Monthly</p>
                            </div>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">Auto-pay</span>
                        </div>
                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900">Internet Bill</p>
                                <p class="text-sm text-gray-500">Due: 20th Monthly</p>
                            </div>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Active</span>
                        </div>
                    </div>
                    <button class="w-full mt-4 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Scheduled Payment
                    </button>
                </div>

                <!-- Payment History -->
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Payments</h3>
                        <span class="text-sm text-gray-500">Last 10</span>
                    </div>
                    <div class="space-y-3 max-h-60 overflow-y-auto">
                        <?php if(empty($payment_history)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-receipt text-3xl text-gray-300 mb-2"></i>
                            <p class="text-gray-500">No payment history</p>
                        </div>
                        <?php else: ?>
                            <?php foreach($payment_history as $payment): ?>
                            <div class="payment-item flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                        <i class="fas fa-file-invoice text-red-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 truncate" style="max-width: 150px;">
                                            <?= $payment['remark'] ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= date('M j, Y', strtotime($payment['date'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-red-600">-$<?= number_format($payment['amount'], 2) ?></p>
                                    <p class="text-xs text-gray-500">Paid</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Featured Billers Section -->
        <div class="mt-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Featured Billers</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php 
                $featured_billers = [
                    ['name' => 'Electric Co.', 'icon' => 'fa-bolt', 'color' => 'yellow'],
                    ['name' => 'Water Board', 'icon' => 'fa-tint', 'color' => 'blue'],
                    ['name' => 'Gas Services', 'icon' => 'fa-fire', 'color' => 'orange'],
                    ['name' => 'Internet Pro', 'icon' => 'fa-wifi', 'color' => 'purple']
                ];
                foreach($featured_billers as $biller): 
                ?>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center card-hover">
                    <div class="w-16 h-16 rounded-full bg-<?= $biller['color'] ?>-100 flex items-center justify-center mx-auto mb-4">
                        <i class="fas <?= $biller['icon'] ?> text-<?= $biller['color'] ?>-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2"><?= $biller['name'] ?></h3>
                    <p class="text-gray-600 text-sm mb-4">Instant payment processing</p>
                    <button class="px-4 py-2 bg-<?= $biller['color'] ?>-500 text-white rounded-lg text-sm font-medium hover:bg-<?= $biller['color'] ?>-600 transition-colors">
                        Pay Now
                    </button>
                </div>
                <?php endforeach; ?>
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

        // Bill category selection
        let selectedCategory = null;

        function selectCategory(categoryId) {
            selectedCategory = categoryId;
            
            // Update UI
            document.querySelectorAll('.category-card').forEach(card => {
                card.classList.remove('active', 'border-primary-500', 'border-blue-500', 'border-green-500', 'border-yellow-500', 'border-purple-500', 'border-indigo-500');
            });
            
            const selectedCard = document.querySelector(`[data-category="${categoryId}"]`);
            const color = getCategoryColor(categoryId);
            selectedCard.classList.add('active', `border-${color}-500`);
            
            // Populate bill options
            populateBillOptions(categoryId);
        }

        function getCategoryColor(categoryId) {
            const colors = {
                'utilities': 'yellow',
                'telecom': 'blue',
                'insurance': 'green',
                'subscriptions': 'purple',
                'education': 'indigo'
            };
            return colors[categoryId] || 'blue';
        }

        function populateBillOptions(categoryId) {
            const billOptions = document.getElementById('billOptions');
            const category = <?= json_encode($bill_categories) ?>[categoryId];
            
            let html = '';
            category.bills.forEach(bill => {
                const color = getCategoryColor(categoryId);
                html += `
                    <div class="bill-option p-3 border border-gray-200 rounded-lg cursor-pointer"
                         onclick="selectBill('${bill.id}', '${bill.name}', '${bill.provider}')">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-${color}-100 flex items-center justify-center">
                                <i class="fas ${category.icon} text-${color}-600"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">${bill.name}</p>
                                <p class="text-sm text-gray-500">${bill.provider}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            billOptions.innerHTML = html;
        }

        function selectBill(billId, billName, billProvider) {
            // Update UI
            document.querySelectorAll('.bill-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            const selectedOption = event.currentTarget;
            selectedOption.classList.add('selected');
            
            // Create hidden inputs for form submission
            let billTypeInput = document.querySelector('input[name="bill_type"]');
            let billProviderInput = document.querySelector('input[name="bill_provider"]');
            
            if (!billTypeInput) {
                billTypeInput = document.createElement('input');
                billTypeInput.type = 'hidden';
                billTypeInput.name = 'bill_type';
                document.getElementById('paymentForm').appendChild(billTypeInput);
            }
            
            if (!billProviderInput) {
                billProviderInput = document.createElement('input');
                billProviderInput.type = 'hidden';
                billProviderInput.name = 'bill_provider';
                document.getElementById('paymentForm').appendChild(billProviderInput);
            }
            
            billTypeInput.value = billName;
            billProviderInput.value = billProvider;
        }

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.querySelector('input[name="amount"]').value);
            const fromAccount = document.querySelector('select[name="from_account"]').value;
            const consumerId = document.getElementById('consumer_id').value;
            const billType = document.querySelector('input[name="bill_type"]');
            
            if (!billType || !billType.value) {
                e.preventDefault();
                alert('Please select a bill type');
                return;
            }
            
            if (!consumerId) {
                e.preventDefault();
                alert('Please enter your consumer/account number');
                return;
            }
            
            if (!fromAccount) {
                e.preventDefault();
                alert('Please select a payment account');
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