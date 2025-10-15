<?php
include('../config/db.php');
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords match
    if ($password !== $confirm_password) {
        $message = "Error: Passwords do not match!";
    } else {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $message = "Error: Email already registered!";
        } else {
            // Hash password and insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $hashed_password);

            if ($stmt->execute()) {
                $message = "success:Registration successful! <a href='login.php' class='underline font-medium'>Login here</a>";
            } else {
                $message = "Error: " . $stmt->error;
            }
        }
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | SecureBank</title>
    
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
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 4px;
            transition: all 0.3s ease;
        }
        
        .strength-weak {
            background-color: #ef4444;
            width: 25%;
        }
        
        .strength-fair {
            background-color: #f59e0b;
            width: 50%;
        }
        
        .strength-good {
            background-color: #10b981;
            width: 75%;
        }
        
        .strength-strong {
            background-color: #10b981;
            width: 100%;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 min-h-screen flex items-center justify-center p-4">
    <!-- Background Elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-600 rounded-full mix-blend-multiply filter blur-xl opacity-20"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-indigo-500 rounded-full mix-blend-multiply filter blur-xl opacity-20"></div>
    </div>

    <!-- Registration Container -->
    <div class="glass-effect rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-700 p-6 text-white text-center">
            <div class="flex items-center justify-center space-x-3 mb-2">
                <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center">
                    <i class="fas fa-university text-blue-700 text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold">SecureBank</h1>
            </div>
            <p class="text-blue-100">Create your account</p>
        </div>
        
        <!-- Registration Form -->
        <div class="p-8 bg-white">
            <?php if($message): 
                $isSuccess = strpos($message, 'success:') === 0;
                $messageText = $isSuccess ? substr($message, 8) : $message;
            ?>
            <div id="message" class="mb-6 p-4 <?= $isSuccess ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?> rounded-lg flex items-start <?= !$isSuccess ? 'shake' : '' ?>">
                <div class="flex-shrink-0">
                    <i class="fas <?= $isSuccess ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> mt-1"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium <?= $isSuccess ? 'text-green-800' : 'text-red-800' ?>">
                        <?= $isSuccess ? 'Registration Successful' : 'Registration Failed' ?>
                    </h3>
                    <div class="mt-1 text-sm <?= $isSuccess ? 'text-green-700' : 'text-red-700' ?>">
                        <p><?= $isSuccess ? $messageText : $message ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="registerForm" class="space-y-5">
                <!-- Name Field -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" 
                            placeholder="John Doe"
                            required
                            value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"
                        >
                    </div>
                </div>
                
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
                            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                        >
                    </div>
                </div>
                
                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
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
                            autocomplete="new-password"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <span class="password-toggle text-gray-400 hover:text-gray-600" id="togglePassword">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>
                    <div id="password-strength" class="password-strength strength-weak"></div>
                    <div id="password-feedback" class="text-xs text-gray-500 mt-1"></div>
                </div>
                
                <!-- Confirm Password Field -->
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" 
                            placeholder="••••••••"
                            required
                            autocomplete="new-password"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <span class="password-toggle text-gray-400 hover:text-gray-600" id="toggleConfirmPassword">
                                <i class="fas fa-eye" id="toggleConfirmIcon"></i>
                            </span>
                        </div>
                    </div>
                    <div id="password-match" class="text-xs mt-1"></div>
                </div>
                
                <!-- Terms and Conditions -->
                <div class="flex items-start">
                    <input 
                        id="terms" 
                        name="terms" 
                        type="checkbox" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1"
                        required
                    >
                    <label for="terms" class="ml-2 block text-sm text-gray-700">
                        I agree to the <a href="#" class="text-blue-600 hover:text-blue-500">Terms of Service</a> and <a href="#" class="text-blue-600 hover:text-blue-500">Privacy Policy</a>
                    </label>
                </div>
                
                <!-- Submit Button -->
                <button 
                    type="submit" 
                    id="submitButton"
                    class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 btn-transition transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                >
                    <span id="buttonText">Create Account</span>
                    <i class="fas fa-user-plus ml-2"></i>
                </button>
            </form>
            
            <!-- Divider -->
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">Already have an account?</span>
                    </div>
                </div>
                
                <!-- Login Link -->
                <div class="mt-4 text-center">
                    <a href="login.php" class="inline-flex items-center text-blue-600 hover:text-blue-500 font-medium transition-colors">
                        Sign in to your account
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
                    <p class="text-xs text-gray-600">Your information is secured with bank-level encryption. We never share your data with third parties.</p>
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
        
        // Toggle confirm password visibility
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const toggleIcon = document.getElementById('toggleConfirmIcon');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password-strength');
            const feedback = document.getElementById('password-feedback');
            const submitButton = document.getElementById('submitButton');
            
            // Reset
            strengthBar.className = 'password-strength';
            feedback.textContent = '';
            
            if (password.length === 0) {
                return;
            }
            
            // Calculate strength
            let strength = 0;
            let feedbackText = '';
            
            // Length check
            if (password.length >= 8) strength += 1;
            
            // Contains lowercase
            if (/[a-z]/.test(password)) strength += 1;
            
            // Contains uppercase
            if (/[A-Z]/.test(password)) strength += 1;
            
            // Contains numbers
            if (/[0-9]/.test(password)) strength += 1;
            
            // Contains special characters
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update UI based on strength
            switch(strength) {
                case 0:
                case 1:
                    strengthBar.classList.add('strength-weak');
                    feedbackText = 'Weak password';
                    break;
                case 2:
                    strengthBar.classList.add('strength-fair');
                    feedbackText = 'Fair password';
                    break;
                case 3:
                    strengthBar.classList.add('strength-good');
                    feedbackText = 'Good password';
                    break;
                case 4:
                case 5:
                    strengthBar.classList.add('strength-strong');
                    feedbackText = 'Strong password';
                    break;
            }
            
            feedback.textContent = feedbackText;
        });
        
        // Password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchIndicator = document.getElementById('password-match');
            
            if (confirmPassword.length === 0) {
                matchIndicator.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchIndicator.textContent = 'Passwords match';
                matchIndicator.className = 'text-xs text-green-600 mt-1';
            } else {
                matchIndicator.textContent = 'Passwords do not match';
                matchIndicator.className = 'text-xs text-red-600 mt-1';
            }
        });
        
        // Form validation before submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            const buttonText = document.getElementById('buttonText');
            
            if (!terms) {
                e.preventDefault();
                alert('Please agree to the Terms of Service and Privacy Policy');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                document.getElementById('confirm_password').focus();
                return;
            }
            
            // Change button text to show loading state
            buttonText.textContent = 'Creating Account...';
        });
        
        // Auto-hide message after 5 seconds (if not a success message with link)
        const message = document.getElementById('message');
        if (message && !message.innerHTML.includes('success:')) {
            setTimeout(() => {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 500);
            }, 5000);
        }
    </script>
</body>
</html>