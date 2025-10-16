<?php
session_start();
include('../config/db.php');

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Hardcoded admin credentials (you can move this to database later)
    $admin_username = 'admin';
    $admin_password = 'admin123'; // In production, use hashed passwords
    
    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_name'] = 'Administrator';
        header("Location: index.php");
        exit;
    } else {
        $message = "Error: Invalid admin credentials!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | SecureBank</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-red-900 via-red-800 to-pink-900 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="bg-gradient-to-r from-red-700 to-pink-700 p-6 text-white text-center">
            <div class="flex items-center justify-center space-x-3 mb-2">
                <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center">
                    <i class="fas fa-university text-red-700 text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold">SecureBank Admin</h1>
            </div>
            <p class="text-red-100">Administrator Access</p>
        </div>
        
        <div class="p-8">
            <?php if($message): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
                <i class="fas fa-exclamation-circle text-red-500 mt-1 mr-3"></i>
                <div class="text-red-800 text-sm"><?= $message ?></div>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input 
                            type="text" 
                            name="username" 
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" 
                            placeholder="Admin username"
                            required
                        >
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            type="password" 
                            name="password" 
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" 
                            placeholder="Admin password"
                            required
                        >
                    </div>
                </div>
                
                <button 
                    type="submit" 
                    class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                >
                    <span>Admin Login</span>
                    <i class="fas fa-sign-in-alt ml-2"></i>
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="../public/" class="text-red-600 hover:text-red-500 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i>
                    Back to Main Site
                </a>
            </div>
        </div>
        
        <div class="bg-gray-50 px-8 py-4 border-t border-gray-200">
            <div class="flex items-start">
                <i class="fas fa-shield-alt text-red-500 mt-0.5 mr-3"></i>
                <p class="text-xs text-gray-600">Restricted access. Authorized personnel only.</p>
            </div>
        </div>
    </div>
</body>
</html>