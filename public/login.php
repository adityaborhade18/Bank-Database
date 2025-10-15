<?php
include('../config/db.php');
session_start();
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        header("Location: dashboard.php");
        exit;
    } else {
        $message = "Invalid credentials!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login | Bank Management System</title>
    
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
        
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .shake {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
        .btn-transition {
            transition: all 0.3s ease;
        }
        
        .password-toggle {
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .password-toggle:hover {
            color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 min-h-screen flex items-center justify-center p-4">
    <!-- Background Elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-600 rounded-full mix-blend-multiply filter blur-xl opacity-20"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-indigo-500 rounded-full mix-blend-multiply filter blur-xl opacity-20"></div>
    </div>

    <!-- Login Container -->
    <div class="glass-effect rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-700 p-6 text-white text-center">
            <div class="flex items-center justify-center space-x-3 mb-2">
                <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center">
                    <i class="fas fa-university text-blue-700 text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold">SecureBank</h1>
            </div>
            <p class="text-blue-100">Sign in to your account</p>
        </div>
        
        <!-- Login Form -->
        <div class="p-8 bg-white">
            <?php if($message): ?>
            <div id="error-message" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start shake">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500 mt-1"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Login Failed</h3>
                    <div class="mt-1 text-sm text-red-700">
                        <p><?= $message ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm" class="space-y-6">
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" 
                            placeholder="you@example.com"
                            required
                            autocomplete="email"
                        >
                    </div>
                </div>
                
                <!-- Password Field -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <a href="forgot-password.php" class="text-sm text-blue-600 hover:text-blue-500 transition-colors">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" 
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <span class="password-toggle text-gray-400 hover:text-gray-600" id="togglePassword">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Remember Me -->
                <div class="flex items-center">
                    <input 
                        id="remember" 
                        name="remember" 
                        type="checkbox" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    >
                    <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me for 30 days</label>
                </div>
                
                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 btn-transition transform hover:-translate-y-0.5"
                >
                    <span id="buttonText">Sign in to your account</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
            
            <!-- Divider -->
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">New to SecureBank?</span>
                    </div>
                </div>
                
                <!-- Register Link -->
                <div class="mt-4 text-center">
                    <a href="register.php" class="inline-flex items-center text-blue-600 hover:text-blue-500 font-medium transition-colors">
                        Create your account
                        <i class="fas fa-arrow-right ml-1 text-sm"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Security Notice -->
        <div class="bg-gray-50 px-8 py-4 border-t border-gray-200">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-shield-alt text-green-500 mt-0.5"></i>
                </div>
                <div class="ml-3">
                    <p class="text-xs text-gray-600">We take your security seriously. All data is encrypted and protected.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const buttonText = document.getElementById('buttonText');
            
            if (!email || !password) {
                e.preventDefault();
                return;
            }
            
            // Change button text to show loading state
            buttonText.textContent = 'Signing in...';
        });
        
        // Auto-hide error message after 5 seconds
        const errorMessage = document.getElementById('error-message');
        if (errorMessage) {
            setTimeout(() => {
                errorMessage.style.opacity = '0';
                errorMessage.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 500);
            }, 5000);
        }
    </script>
</body>
</html>