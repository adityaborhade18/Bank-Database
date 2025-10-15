<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SecureBank | Modern Banking Solutions</title>
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    
    .animate-float {
      animation: float 5s ease-in-out infinite;
    }
    
    .glass-effect {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
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
          },
          animation: {
            'fade-in-up': 'fadeInUp 0.5s ease-out',
            'fade-in-down': 'fadeInDown 0.5s ease-out',
          },
          keyframes: {
            fadeInUp: {
              '0%': { opacity: '0', transform: 'translateY(20px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' },
            },
            fadeInDown: {
              '0%': { opacity: '0', transform: 'translateY(-20px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' },
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 min-h-screen flex flex-col">
  <!-- Background Elements -->
  <div class="absolute inset-0 overflow-hidden">
    <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-600 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-float"></div>
    <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-primary-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-float" style="animation-delay: 2s;"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-primary-400 rounded-full mix-blend-multiply filter blur-xl opacity-10 animate-float" style="animation-delay: 4s;"></div>
  </div>

  <!-- Navigation -->
  <nav class="relative z-10 py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
      <div class="flex items-center space-x-2">
        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center">
          <i class="fas fa-university text-primary-700 text-xl"></i>
        </div>
        <span class="text-white text-xl font-bold">SecureBank</span>
      </div>
      
      <div class="hidden md:flex space-x-8">
        <a href="#" class="text-white hover:text-primary-200 transition-colors">Home</a>
        <a href="#" class="text-white hover:text-primary-200 transition-colors">Services</a>
        <a href="#" class="text-white hover:text-primary-200 transition-colors">About</a>
        <a href="#" class="text-white hover:text-primary-200 transition-colors">Contact</a>
      </div>
      
      <div class="flex space-x-4">
        <a href="login.php" class="text-white hover:text-primary-200 transition-colors">Login</a>
        <a href="register.php" class="bg-white text-primary-700 hover:bg-primary-50 px-4 py-2 rounded-lg font-medium transition-colors">Register</a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="flex-grow flex items-center justify-center px-4 sm:px-6 lg:px-8 relative z-10">
    <div class="max-w-4xl mx-auto text-center">
      <!-- Hero Section -->
      <div class="mb-16 animate-fade-in-down">
        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
          Modern Banking
          <span class="block text-transparent bg-clip-text bg-gradient-to-r from-primary-200 to-primary-400">Made Simple</span>
        </h1>
        <p class="text-xl text-primary-100 max-w-2xl mx-auto mb-10">
          Manage your finances securely with our cutting-edge digital banking platform. 
          Send money, pay bills, and track spending all in one place.
        </p>
        
        <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6">
          <a href="register.php" class="bg-white text-primary-700 hover:bg-primary-50 px-8 py-4 rounded-xl font-semibold text-lg shadow-lg transition-all transform hover:-translate-y-1">
            Get Started Free
          </a>
          <a href="login.php" class="glass-effect text-white hover:bg-white/20 px-8 py-4 rounded-xl font-semibold text-lg border border-white/30 transition-all transform hover:-translate-y-1">
            Sign In
          </a>
        </div>
      </div>
      
      <!-- Features Section -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-16 animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="glass-effect p-6 rounded-2xl backdrop-blur-lg">
          <div class="w-14 h-14 rounded-full bg-primary-500/20 flex items-center justify-center mb-4 mx-auto">
            <i class="fas fa-shield-alt text-primary-300 text-2xl"></i>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">Bank-Grade Security</h3>
          <p class="text-primary-200">Your financial data is protected with enterprise-level encryption and security protocols.</p>
        </div>
        
        <div class="glass-effect p-6 rounded-2xl backdrop-blur-lg">
          <div class="w-14 h-14 rounded-full bg-primary-500/20 flex items-center justify-center mb-4 mx-auto">
            <i class="fas fa-bolt text-primary-300 text-2xl"></i>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">Instant Transfers</h3>
          <p class="text-primary-200">Send and receive money instantly with our real-time payment processing system.</p>
        </div>
        
        <div class="glass-effect p-6 rounded-2xl backdrop-blur-lg">
          <div class="w-14 h-14 rounded-full bg-primary-500/20 flex items-center justify-center mb-4 mx-auto">
            <i class="fas fa-chart-line text-primary-300 text-2xl"></i>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">Smart Insights</h3>
          <p class="text-primary-200">Get personalized financial insights and recommendations to help you reach your goals.</p>
        </div>
      </div>
      
      <!-- Stats Section -->
      <div class="mt-20 grid grid-cols-2 md:grid-cols-4 gap-8 animate-fade-in-up" style="animation-delay: 0.4s;">
        <div class="text-center">
          <div class="text-3xl font-bold text-white">2M+</div>
          <div class="text-primary-200">Happy Customers</div>
        </div>
        <div class="text-center">
          <div class="text-3xl font-bold text-white">$50B+</div>
          <div class="text-primary-200">Assets Managed</div>
        </div>
        <div class="text-center">
          <div class="text-3xl font-bold text-white">99.9%</div>
          <div class="text-primary-200">Uptime</div>
        </div>
        <div class="text-center">
          <div class="text-3xl font-bold text-white">24/7</div>
          <div class="text-primary-200">Customer Support</div>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="relative z-10 py-8 text-center text-primary-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <p>© <?= date("Y") ?> SecureBank. All rights reserved. | Designed with <i class="fas fa-heart text-red-400"></i> for modern banking</p>
      <div class="mt-4 flex justify-center space-x-6">
        <a href="#" class="text-primary-200 hover:text-white transition-colors">
          <i class="fab fa-twitter"></i>
        </a>
        <a href="#" class="text-primary-200 hover:text-white transition-colors">
          <i class="fab fa-facebook"></i>
        </a>
        <a href="#" class="text-primary-200 hover:text-white transition-colors">
          <i class="fab fa-linkedin"></i>
        </a>
        <a href="#" class="text-primary-200 hover:text-white transition-colors">
          <i class="fab fa-instagram"></i>
        </a>
      </div>
    </div>
  </footer>
</body>
</html>