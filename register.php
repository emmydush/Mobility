<?php
session_start();

// Redirect to tenant registration as the primary registration method
header("Location: register_tenant.php");
exit();

// The rest of the file is kept for reference but won't be executed
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .gradient-bg {
            background: linear-gradient(-45deg, #1e3a8a, #3b82f6, #60a5fa, #93c5fd);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        /* Mobile menu styles */
        .mobile-menu {
            display: none;
        }
        
        @media (max-width: 768px) {
            .mobile-menu {
                display: block;
            }
            
            .desktop-menu {
                display: none;
            }
            
            .hero-content {
                padding: 1.5rem;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .hero-text {
                font-size: 1.5rem;
                line-height: 1.3;
            }
            
            .hero-subtext {
                font-size: 1rem;
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Header Navigation -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-white bg-opacity-90 backdrop-blur-sm shadow-sm">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-600 to-green-700 rounded-lg flex items-center justify-center">
                        <i class="fas fa-boxes text-white"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-800">Mobility IMS</h1>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex space-x-8">
                    <a href="#" class="text-gray-600 hover:text-green-600 font-medium">Features</a>
                    <a href="#" class="text-gray-600 hover:text-green-600 font-medium">Solutions</a>
                    <a href="#" class="text-gray-600 hover:text-green-600 font-medium">Pricing</a>
                    <a href="#" class="text-gray-600 hover:text-green-600 font-medium">Contact</a>
                </nav>
                
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-gray-600 hover:text-green-600 font-medium hidden md:block">Login</a>
                    <a href="register.php" class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-300 shadow-md hidden md:block">Sign Up</a>
                    
                    <!-- Mobile menu button -->
                    <div class="md:hidden">
                        <button id="mobile-menu-button" class="text-gray-600 hover:text-green-600">
                            <i class="fas fa-bars text-2xl"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-200">
            <div class="container mx-auto px-4 py-3">
                <div class="flex flex-col space-y-3 pb-3">
                    <a href="#" class="text-gray-600 hover:text-green-600 font-medium py-2">Features</a>
                    <a href="#" class="text-gray-600 hover:text-green-600 font-medium py-2">Solutions</a>
                    <a href="#" class="text-gray-600 hover:text-green-600 font-medium py-2">Pricing</a>
                    <a href="#" class="text-gray-600 hover:text-green-600 font-medium py-2">Contact</a>
                    <div class="flex space-x-4 pt-2">
                        <a href="login.php" class="flex-1 text-center px-4 py-2 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50">Login</a>
                        <a href="register.php" class="flex-1 text-center px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800">Sign Up</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="flex flex-col md:flex-row min-h-screen pt-16">
        <!-- Hero Section (Left) -->
        <div class="gradient-bg w-full md:w-1/2 flex items-center justify-center p-4 md:p-8 relative overflow-hidden">
            <!-- Animated background elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute top-1/4 left-1/4 w-32 h-32 md:w-64 md:h-64 bg-white opacity-10 rounded-full floating"></div>
                <div class="absolute top-3/4 right-1/4 w-24 h-24 md:w-48 md:h-48 bg-green-200 opacity-20 rounded-full floating" style="animation-delay: -2s;"></div>
                <div class="absolute top-1/2 left-3/4 w-16 h-16 md:w-32 md:h-32 bg-green-300 opacity-15 rounded-full floating" style="animation-delay: -4s;"></div>
            </div>
            
            <div class="z-10 text-center text-white hero-content">
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4 md:mb-6 hero-text">Complete Inventory Management System</h1>
                <p class="text-lg md:text-xl mb-6 md:mb-8 opacity-90 hero-subtext">Streamline your inventory operations with our powerful, intuitive platform designed for modern businesses.</p>
                
                <!-- Feature highlights -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6 mb-8 md:mb-10 feature-grid">
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4">
                        <i class="fas fa-chart-line text-xl md:text-2xl mb-2"></i>
                        <h3 class="font-semibold text-sm md:text-base">Real-time Tracking</h3>
                    </div>
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4">
                        <i class="fas fa-bell text-xl md:text-2xl mb-2"></i>
                        <h3 class="font-semibold text-sm md:text-base">Smart Alerts</h3>
                    </div>
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4">
                        <i class="fas fa-file-invoice text-xl md:text-2xl mb-2"></i>
                        <h3 class="font-semibold text-sm md:text-base">Detailed Reports</h3>
                    </div>
                </div>
                
                <!-- Trust indicators -->
                <div class="flex flex-wrap justify-center gap-4 md:gap-8 text-xs md:text-sm">
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt text-green-400 mr-1 md:mr-2"></i>
                        <span>Enterprise Security</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-sync text-green-400 mr-1 md:mr-2"></i>
                        <span>99.9% Uptime</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-users text-purple-400 mr-1 md:mr-2"></i>
                        <span>10K+ Users</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Registration Form (Right) -->
        <div class="w-full md:w-1/2 flex items-center justify-center p-4 md:p-8 bg-gray-50">
            <div class="w-full max-w-md">
                <div class="text-center mb-6 md:mb-8">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">Create Account</h2>
                    <p class="text-gray-600">Join our inventory management system today</p>
                </div>
                
                <?php if ($error_message): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4 md:space-y-6">
                    <div>
                        <label for="reg-username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="reg-username" name="username" required
                            class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-300 text-base">
                    </div>
                    
                    <div>
                        <label for="reg-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="reg-email" name="email" required
                            class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-300 text-base">
                    </div>
                    
                    <div>
                        <label for="reg-role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select id="reg-role" name="role" required
                            class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-300 text-base">
                            <option value="cashier" selected>Cashier</option>
                            <option value="staff">Staff</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                            <option value="supervisor">Supervisor</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Select your role in the system</p>
                    </div>
                    
                    <div>
                        <label for="reg-password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" id="reg-password" name="password" required
                            class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-300 text-base">
                    </div>
                    
                    <div>
                        <label for="reg-confirm-password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" id="reg-confirm-password" name="confirm-password" required
                            class="w-full px-3 py-2 md:px-4 md:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-300 text-base">
                    </div>
                    
                    <div>
                        <button type="submit"
                            class="w-full py-2 md:py-3 px-4 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 shadow-md transition-all duration-300 transform hover:-translate-y-0.5 text-base font-medium">
                            Register
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 md:mt-8 pt-6 border-t border-gray-200 text-center">
                    <p class="text-gray-600 text-sm">
                        Already have an account?
                        <a href="login.php" class="font-medium text-green-600 hover:text-green-800">Sign in</a>
                    </p>
                    
                    <!-- Security badges -->
                    <div class="mt-4 md:mt-6 flex justify-center space-x-4">
                        <div class="flex items-center text-xs md:text-sm text-gray-500">
                            <i class="fas fa-lock text-green-500 mr-1"></i>
                            <span>SSL Encryption</span>
                        </div>
                        <div class="flex items-center text-xs md:text-sm text-gray-500">
                            <i class="fas fa-shield-alt text-green-500 mr-1"></i>
                            <span>GDPR Compliant</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer for mobile view -->
    <footer class="md:hidden bg-white border-t border-gray-200 py-4 md:py-6">
        <div class="container mx-auto px-4 text-center text-gray-600 text-xs md:text-sm">
            <p>© 2025 Mobility Inventory System. All rights reserved.</p>
        </div>
    </footer>
    
    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });
    </script>
</body>
</html>