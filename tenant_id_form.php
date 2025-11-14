<?php
// This file is included by register_user.php when tenant ID is needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Tenant ID - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-green-800 shadow-lg fixed top-0 w-full z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-boxes text-white text-xl mr-2"></i>
                    <span class="text-white text-xl font-bold">Mobility Inventory</span>
                </div>
                <nav>
                    <a href="login.php" class="text-green-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Login</a>
                </nav>
            </div>
        </div>
    </header>
    
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8" style="padding-top: 5rem;">
        <div class="max-w-md w-full space-y-8">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-green-800 px-6 py-4">
                    <h2 class="text-2xl font-bold text-white text-center">Enter Tenant ID</h2>
                    <p class="text-green-200 text-center mt-1">To register as a user</p>
                </div>
                
                <!-- Message Display -->
                <?php if ($message): ?>
                    <div class="p-4 <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                        <div class="container mx-auto">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="px-6 py-6">
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="tenant_id">
                                Tenant ID *
                            </label>
                            <input type="text" id="tenant_id" name="tenant_id" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Enter your tenant ID">
                            <p class="text-gray-500 text-xs mt-1">Provided when your business was registered</p>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="register_tenant.php" class="text-green-600 hover:text-green-800">
                                <i class="fas fa-building mr-1"></i> Register Business
                            </a>
                            <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:shadow-outline">
                                Continue
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center text-gray-600 text-sm">
                <p>Already have an account? <a href="login.php" class="text-green-600 hover:text-green-800">Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>