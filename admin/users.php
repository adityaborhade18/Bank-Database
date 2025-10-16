<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include('../config/db.php');
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

$message = '';
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? '';

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_user'])) {
        // Delete user
        $user_id = $_POST['user_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete user's transactions
            $stmt1 = $conn->prepare("DELETE t FROM transactions t JOIN accounts a ON t.account_no = a.account_no WHERE a.user_id = ?");
            $stmt1->bind_param("i", $user_id);
            $stmt1->execute();
            $stmt1->close();
            
            // Delete user's accounts
            $stmt2 = $conn->prepare("DELETE FROM accounts WHERE user_id = ?");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $stmt2->close();
            
            // Delete user
            $stmt3 = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt3->bind_param("i", $user_id);
            $stmt3->execute();
            $stmt3->close();
            
            $conn->commit();
            $message = "success:User deleted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: Failed to delete user - " . $e->getMessage();
        }
    }
}

// Search and filter functionality
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

// Build query for users
$query = "SELECT u.*, 
          COUNT(a.account_no) as account_count,
          COALESCE(SUM(a.balance), 0) as total_balance
          FROM users u 
          LEFT JOIN accounts a ON u.id = a.user_id";

$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= "ss";
}

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

// Prepare and execute
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$total_users = count($users);
$active_users = $total_users; // All users are considered active in this system
$total_balance = 0;
foreach ($users as $user) {
    $total_balance += $user['total_balance'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | SecureBank Admin</title>
    
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
        
        .user-row {
            transition: all 0.2s ease;
        }
        
        .user-row:hover {
            background-color: #f9fafb;
            transform: translateX(2px);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                <a href="users.php" class="sidebar-item active flex items-center px-4 py-3 text-sm font-medium text-gray-900 rounded-lg">
                    <i class="fas fa-users mr-3 text-lg text-red-600"></i>
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
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
                <p class="text-gray-600 mt-2">Manage all registered users and their accounts</p>
            </div>

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
                            <i class="fas fa-user-check text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Active Users</h3>
                            <p class="text-2xl font-semibold text-gray-900"><?= $active_users ?></p>
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

            <!-- Search and Actions -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                        <!-- Search Form -->
                        <form method="GET" class="flex space-x-4">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input 
                                    type="text" 
                                    name="search" 
                                    value="<?= htmlspecialchars($search) ?>" 
                                    class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" 
                                    placeholder="Search users..."
                                >
                            </div>
                            <button 
                                type="submit" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                            >
                                Search
                            </button>
                            <?php if (!empty($search)): ?>
                            <a 
                                href="users.php" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
                            >
                                Clear
                            </a>
                            <?php endif; ?>
                        </form>

                        <!-- Export Button -->
                        <button 
                            onclick="exportUsers()" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                        >
                            <i class="fas fa-download mr-2"></i>
                            Export Users
                        </button>
                    </div>
                </div>
            </div>

            <!-- Message Display -->
            <?php if($message): 
                $isSuccess = strpos($message, 'success:') === 0;
                $messageText = $isSuccess ? substr($message, 8) : $message;
            ?>
            <div class="mb-6 p-4 <?= $isSuccess ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?> rounded-lg flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas <?= $isSuccess ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> mt-1"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium <?= $isSuccess ? 'text-green-800' : 'text-red-800' ?>">
                        <?= $isSuccess ? 'Success' : 'Error' ?>
                    </h3>
                    <div class="mt-1 text-sm <?= $isSuccess ? 'text-green-700' : 'text-red-700' ?>">
                        <p><?= $isSuccess ? $messageText : $message ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Users Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">All Users (<?= $total_users ?>)</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Accounts</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center">
                                        <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                                        <p class="text-gray-500 text-lg">No users found</p>
                                        <p class="text-gray-400 mt-1"><?= empty($search) ? 'No users registered yet' : 'No users match your search' ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr class="user-row">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white font-medium">
                                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                                                <div class="text-sm text-gray-500">ID: <?= $user['id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                                        <div class="text-sm text-gray-500">Registered</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?= $user['account_count'] ?> Account<?= $user['account_count'] != 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        $<?= number_format($user['total_balance'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a 
                                                href="user_details.php?id=<?= $user['id'] ?>" 
                                                class="text-blue-600 hover:text-blue-900"
                                                title="View Details"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button 
                                                onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" 
                                                class="text-red-600 hover:text-red-900"
                                                title="Delete User"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="bg-white rounded-lg shadow-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Confirm Deletion</h3>
                </div>
                <div class="p-6">
                    <p class="text-gray-700 mb-4">Are you sure you want to delete user <span id="userName" class="font-semibold"></span>? This action cannot be undone and will permanently delete all user data including accounts and transactions.</p>
                    
                    <form id="deleteForm" method="POST">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <input type="hidden" name="delete_user" value="1">
                        
                        <div class="flex justify-end space-x-3">
                            <button 
                                type="button" 
                                onclick="closeDeleteModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                            >
                                Delete User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openDeleteModal(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('userName').textContent = userName;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }

        // Export users function
        function exportUsers() {
            // Create CSV content
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "ID,Name,Email,Account Count,Total Balance,Joined Date\n";
            
            <?php foreach ($users as $user): ?>
            csvContent += "<?= $user['id'] ?>,<?= $user['name'] ?>,<?= $user['email'] ?>,<?= $user['account_count'] ?>,<?= $user['total_balance'] ?>,<?= $user['created_at'] ?>\n";
            <?php endforeach; ?>
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "users_export_<?= date('Y-m-d') ?>.csv");
            document.body.appendChild(link);
            
            // Trigger download
            link.click();
            document.body.removeChild(link);
        }

        // Auto-hide message after 5 seconds
        const messageDiv = document.querySelector('.bg-green-50, .bg-red-50');
        if (messageDiv) {
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 500);
            }, 5000);
        }
    </script>
</body>
</html>