<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('../config/db.php');
$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

// Handle filters
$filter_type = $_GET['type'] ?? '';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';

// Build the base query
$query = "
    SELECT t.*, a.account_no 
    FROM transactions t 
    JOIN accounts a ON t.account_no = a.account_no 
    WHERE a.user_id = ?
";

$params = [$user_id];
$param_types = "i";

// Add filters if provided
if (!empty($filter_type)) {
    $query .= " AND t.type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if (!empty($filter_start_date)) {
    $query .= " AND DATE(t.date) >= ?";
    $params[] = $filter_start_date;
    $param_types .= "s";
}

if (!empty($filter_end_date)) {
    $query .= " AND DATE(t.date) <= ?";
    $params[] = $filter_end_date;
    $param_types .= "s";
}

$query .= " ORDER BY t.date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate summary
$total_deposits = 0;
$total_withdrawals = 0;
$total_transfers = 0;

foreach ($transactions as $transaction) {
    switch ($transaction['type']) {
        case 'deposit':
            $total_deposits += $transaction['amount'];
            break;
        case 'withdraw':
            $total_withdrawals += $transaction['amount'];
            break;
        case 'transfer':
            $total_transfers += $transaction['amount'];
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History | SecureBank</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
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
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
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
        
        .transaction-row {
            transition: all 0.2s ease;
        }
        
        .transaction-row:hover {
            background-color: #f9fafb;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .print-table {
                font-size: 12px;
            }
            body {
                background: white !important;
            }
            .shadow, .rounded-lg {
                box-shadow: none !important;
                border-radius: 0 !important;
            }
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
    <nav class="bg-white shadow-sm border-b border-gray-200 no-print">
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
        <div class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-white border-r border-gray-200 pt-16 no-print">
            <div class="flex-1 flex flex-col min-h-0">
                <nav class="flex-1 px-4 py-4 space-y-1">
                    <a href="dashboard.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                        <i class="fas fa-home mr-3 text-lg text-gray-400"></i>
                        Dashboard
                    </a>
                   
                    <a href="transfer.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                        <i class="fas fa-exchange-alt mr-3 text-lg text-gray-400"></i>
                        Transfer Money
                    </a>

                    <a href="deposit.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg">
                        <i class="fas fa-exchange-alt mr-3 text-lg text-gray-400"></i>
                        Deposit Money
                    </a>
                    
                    <a href="transactions.php" class="sidebar-item active flex items-center px-4 py-3 text-sm font-medium text-gray-900 rounded-lg">
                        <i class="fas fa-history mr-3 text-lg text-primary-600"></i>
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
                                        Transaction History
                                    </h1>
                                    <p class="mt-1 text-sm text-gray-500">View and manage your account transactions</p>
                                </div>
                                <div class="mt-4 flex space-x-3 md:mt-0 md:ml-4 no-print">
                                    <button onclick="printTransactions()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        <i class="fas fa-print mr-2"></i>
                                        Print
                                    </button>
                                    <button onclick="downloadPDF()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        <i class="fas fa-download mr-2"></i>
                                        Download PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Summary -->
                <div class="mt-8 px-4 sm:px-6 lg:px-8 no-print">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        <!-- Filter Card -->
                        <div class="lg:col-span-1">
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Transactions</h3>
                                
                                <form method="GET" action="transactions.php">
                                    <div class="space-y-4">
                                        <!-- Transaction Type -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                                            <select name="type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                                <option value="">All Types</option>
                                                <option value="deposit" <?= $filter_type === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                                                <option value="withdraw" <?= $filter_type === 'withdraw' ? 'selected' : '' ?>>Withdrawal</option>
                                                <option value="transfer" <?= $filter_type === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Date Range -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                            <input type="date" name="start_date" value="<?= $filter_start_date ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                            <input type="date" name="end_date" value="<?= $filter_end_date ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        </div>
                                        
                                        <!-- Buttons -->
                                        <div class="flex space-x-2">
                                            <button type="submit" class="flex-1 bg-primary-600 text-white py-2 px-4 rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                                Apply Filters
                                            </button>
                                            <a href="transactions.php" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-400 text-center">
                                                Clear
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="lg:col-span-3">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Total Deposits -->
                                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg shadow p-6 card-hover">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-arrow-down text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium">Total Deposits</p>
                                            <p class="text-2xl font-bold">$<?= number_format($total_deposits, 2) ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Total Withdrawals -->
                                <div class="bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg shadow p-6 card-hover">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-arrow-up text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium">Total Withdrawals</p>
                                            <p class="text-2xl font-bold">$<?= number_format($total_withdrawals, 2) ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Total Transfers -->
                                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg shadow p-6 card-hover">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-exchange-alt text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium">Total Transfers</p>
                                            <p class="text-2xl font-bold">$<?= number_format($total_transfers, 2) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Transaction Count -->
                            <div class="mt-6 bg-white shadow rounded-lg p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Transaction Summary</h3>
                                        <p class="text-sm text-gray-500">Showing <?= count($transactions) ?> transactions</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-500">Net Flow</p>
                                        <p class="text-lg font-semibold <?= ($total_deposits - $total_withdrawals - $total_transfers) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                            $<?= number_format($total_deposits - $total_withdrawals - $total_transfers, 2) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="mt-8 px-4 sm:px-6 lg:px-8">
                    <div class="bg-white shadow rounded-lg overflow-hidden fade-in">
                        <div class="px-6 py-4 border-b border-gray-200 no-print">
                            <h3 class="text-lg font-medium text-gray-900">All Transactions</h3>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 print-table">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider no-print">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center">
                                                <i class="fas fa-exchange-alt text-4xl text-gray-300 mb-3"></i>
                                                <p class="text-gray-500 text-lg">No transactions found</p>
                                                <p class="text-gray-400 mt-1">Your transactions will appear here</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                        <tr class="transaction-row">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= date('M j, Y', strtotime($transaction['date'])) ?></div>
                                                <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($transaction['date'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?= $transaction['type'] == 'deposit' ? 'bg-green-100 text-green-800' : 
                                                       ($transaction['type'] == 'withdraw' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') ?>">
                                                    <?= ucfirst($transaction['type']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?= $transaction['remark'] ?: 'Transaction' ?></div>
                                                <div class="text-sm text-gray-500">Ref: #<?= $transaction['id'] ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                ****<?= substr($transaction['account_no'], -4) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium 
                                                <?= $transaction['type'] == 'deposit' ? 'text-green-600' : 'text-red-600' ?>">
                                                <?= $transaction['type'] == 'deposit' ? '+' : '-' ?><?= number_format($transaction['amount'], 2) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap no-print">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Completed
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="bg-white border-t border-gray-200 mt-12 no-print">
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
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-10 no-print">
        <div class="grid grid-cols-4 gap-1 p-2">
            <a href="dashboard.php" class="flex flex-col items-center p-2 text-gray-500">
                <i class="fas fa-home mb-1"></i>
                <span class="text-xs">Dashboard</span>
            </a>
            <a href="transfer.php" class="flex flex-col items-center p-2 text-gray-500">
                <i class="fas fa-exchange-alt mb-1"></i>
                <span class="text-xs">Transfer</span>
            </a>
            <a href="transactions.php" class="flex flex-col items-center p-2 text-primary-600">
                <i class="fas fa-history mb-1"></i>
                <span class="text-xs">History</span>
            </a>
            <a href="accounts.php" class="flex flex-col items-center p-2 text-gray-500">
                <i class="fas fa-wallet mb-1"></i>
                <span class="text-xs">Accounts</span>
            </a>
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

        // Print functionality
        function printTransactions() {
            window.print();
        }

        // PDF Download functionality
        function downloadPDF() {
            const { jsPDF } = window.jspdf;
            
            // Show loading
            const originalText = event.target.innerHTML;
            event.target.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating PDF...';
            event.target.disabled = true;

            // Capture the transactions table
            const element = document.querySelector('.print-table');
            
            html2canvas(element, {
                scale: 2,
                useCORS: true,
                logging: false
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const imgWidth = 190;
                const pageHeight = 280;
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 10;

                // Add header
                pdf.setFontSize(16);
                pdf.setTextColor(40, 40, 40);
                pdf.text('Transaction History - SecureBank', 15, 15);
                pdf.setFontSize(10);
                pdf.text(`Generated on: ${new Date().toLocaleDateString()}`, 15, 22);
                pdf.text(`Account Holder: <?= $name ?>`, 15, 28);
                
                // Add the image
                pdf.addImage(imgData, 'PNG', 10, 35, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                // Add new pages if needed
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight + 35;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                // Save the PDF
                pdf.save(`transaction-history-<?= $name ?>-${new Date().toISOString().split('T')[0]}.pdf`);
                
                // Restore button
                event.target.innerHTML = originalText;
                event.target.disabled = false;
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('Error generating PDF. Please try again.');
                event.target.innerHTML = originalText;
                event.target.disabled = false;
            });
        }

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