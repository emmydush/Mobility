<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection with robust path resolution
$rootPath = __DIR__;
$databasePath = $rootPath . '/api/config/database.php';

// If the direct path doesn't work, try alternative paths
if (!file_exists($databasePath)) {
    $databasePath = $rootPath . '/../api/config/database.php';
}

if (!file_exists($databasePath)) {
    $databasePath = $rootPath . '/../../api/config/database.php';
}

if (file_exists($databasePath)) {
    include_once $databasePath;
} else {
    die("Database configuration file not found");
}

// Check if user has a tenant assigned
if (!isset($_SESSION['tenant_id']) || !$_SESSION['tenant_id']) {
    die("No business account assigned to your user. Please contact support.");
}

// Include language functions
include_once 'api/config/languages.php';

// Set language if specified
if (isset($_GET['lang'])) {
    setCurrentLanguage($_GET['lang']);
}

// Get current language
$current_lang = getCurrentLanguage();

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'cashier';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = $_POST['name'] ?? '';
                $contact_person = $_POST['contact_person'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $address = $_POST['address'] ?? '';
                $status = $_POST['status'] ?? 'active';
                
                // Validate required fields
                if (!empty($name)) {
                    $conn->begin_transaction();
                    try {
                        // Insert supplier record with tenant_id
                        $query = "INSERT INTO suppliers (tenant_id, name, contact_person, email, phone, address, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $stmt->bindParam(2, $name, PDO::PARAM_STR);
                            $stmt->bindParam(3, $contact_person, PDO::PARAM_STR);
                            $stmt->bindParam(4, $email, PDO::PARAM_STR);
                            $stmt->bindParam(5, $phone, PDO::PARAM_STR);
                            $stmt->bindParam(6, $address, PDO::PARAM_STR);
                            $stmt->bindParam(7, $status, PDO::PARAM_STR);
                            $stmt->bindParam(8, $_SESSION['user_id'], PDO::PARAM_INT);
                            if ($stmt->execute()) {
                                $conn->commit();
                                $message = "Supplier added successfully!";
                                $message_type = "success";
                            } else {
                                throw new Exception("Error adding supplier: " . $conn->errorInfo()[2]);
                            }
                            $stmt = null;
                        } else {
                            throw new Exception("Error preparing supplier statement: " . $conn->errorInfo()[2]);
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error adding supplier: " . $e->getMessage();
                        $message_type = "error";
                    }
                } else {
                    $message = "Please fill in all required fields.";
                    $message_type = "error";
                }
                break;
                
            case 'edit':
                $id = $_POST['id'] ?? 0;
                $name = $_POST['name'] ?? '';
                $contact_person = $_POST['contact_person'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $address = $_POST['address'] ?? '';
                $status = $_POST['status'] ?? 'active';
                
                // Validate required fields
                if (!empty($id) && !empty($name)) {
                    $conn->begin_transaction();
                    try {
                        // Update supplier record with tenant filtering
                        $query = "UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bindParam(1, $name, PDO::PARAM_STR);
                            $stmt->bindParam(2, $contact_person, PDO::PARAM_STR);
                            $stmt->bindParam(3, $email, PDO::PARAM_STR);
                            $stmt->bindParam(4, $phone, PDO::PARAM_STR);
                            $stmt->bindParam(5, $address, PDO::PARAM_STR);
                            $stmt->bindParam(6, $status, PDO::PARAM_STR);
                            $stmt->bindParam(7, $id, PDO::PARAM_INT);
                            $stmt->bindParam(8, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            if ($stmt->execute()) {
                                $conn->commit();
                                $message = "Supplier updated successfully!";
                                $message_type = "success";
                            } else {
                                throw new Exception("Error updating supplier: " . $conn->errorInfo()[2]);
                            }
                            $stmt = null;
                        } else {
                            throw new Exception("Error preparing supplier update statement: " . $conn->errorInfo()[2]);
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error updating supplier: " . $e->getMessage();
                        $message_type = "error";
                    }
                } else {
                    $message = "Please fill in all required fields.";
                    $message_type = "error";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                if (is_numeric($id) && $id > 0) {
                    $conn->begin_transaction();
                    try {
                        // Delete supplier with tenant filtering
                        $query = "DELETE FROM suppliers WHERE id = ? AND tenant_id = ?";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bindParam(1, $id, PDO::PARAM_INT);
                            $stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            if ($stmt->execute()) {
                                $stmt = null;
                                $conn->commit();
                                $message = "Supplier deleted successfully!";
                                $message_type = "success";
                            } else {
                                throw new Exception("Error deleting supplier: " . $conn->errorInfo()[2]);
                            }
                        } else {
                            throw new Exception("Error preparing delete statement: " . $conn->errorInfo()[2]);
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error deleting supplier: " . $e->getMessage();
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid supplier ID.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all suppliers for current tenant only
$suppliers = array();
$query = "SELECT * FROM suppliers WHERE tenant_id = ? ORDER BY name";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($result)) {
        $suppliers[] = $row;
    }
    $stmt = null;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-green-700 to-green-900 shadow-lg">
            <div class="p-4 border-b border-green-600">
                <h1 class="text-xl font-bold text-white">IMS</h1>
                <p class="text-sm text-blue-200">Inventory Management</p>
            </div>
            <nav class="mt-4">
                <a href="dashboard.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span><?php echo t('dashboard'); ?></span>
                </a>
                <a href="products.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-box mr-3"></i>
                    <span>Products</span>
                </a>
                <a href="categories.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-tags mr-3"></i>
                    <span>Categories</span>
                </a>
                <a href="purchases.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-shopping-cart mr-3"></i>
                    <span>Purchases</span>
                </a>
                <a href="expenses.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-file-invoice-dollar mr-3"></i>
                    <span>Expenses</span>
                </a>
                <a href="suppliers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-300">
                    <i class="fas fa-truck mr-3"></i>
                    <span>Suppliers</span>
                </a>
                <a href="stock-movements.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-exchange-alt mr-3"></i>
                    <span>Stock Movements</span>
                </a>
                <a href="customers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-users mr-3"></i>
                    <span>Customers</span>
                </a>
                <a href="sales.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-cash-register mr-3"></i>
                    <span>Sales</span>
                </a>
                <a href="reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-chart-bar mr-3"></i>
                    <span><?php echo t('reports'); ?></span>
                </a>
                <a href="advanced_reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-chart-line mr-3"></i>
                    <span>Advanced Reports</span>
                </a>
                <a href="users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-user mr-3"></i>
                    <span><?php echo t('users'); ?></span>
                </a>
                <a href="settings.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-cog mr-3"></i>
                    <span><?php echo t('settings'); ?></span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="flex items-center justify-between p-4 bg-gradient-to-r from-green-600 to-green-800 shadow">
                <h2 class="text-xl font-semibold text-white">Supplier Management</h2>
                <div class="flex items-center space-x-4">
                    <!-- Language Selector -->
                    <div class="relative">
                        <select onchange="window.location.href=this.value" class="bg-green-600 text-white rounded px-2 py-1 text-sm">
                            <option value="?lang=en" <?php echo ($current_lang == 'en') ? 'selected' : ''; ?>>English</option>
                            <option value="?lang=fr" <?php echo ($current_lang == 'fr') ? 'selected' : ''; ?>>Français</option>
                            <option value="?lang=rw" <?php echo ($current_lang == 'rw') ? 'selected' : ''; ?>>Kinyarwanda</option>
                        </select>
                    </div>
                    <button id="add-supplier-btn" class="px-4 py-2 text-sm bg-white text-green-600 rounded hover:bg-green-50 transition duration-200">
                        <i class="fas fa-plus mr-1"></i> Add Supplier
                    </button>
                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-2 text-white focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div class="text-left hidden md:block">
                                <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($username); ?></p>
                                <p class="text-xs text-green-100 capitalize"><?php echo htmlspecialchars($role); ?></p>
                            </div>
                            <i class="fas fa-chevron-down text-green-200 text-xs"></i>
                        </button>
                        
                        <!-- Dropdown menu -->
                        <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden z-50">
                            <div class="px-4 py-2 border-b border-gray-200">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($username); ?></p>
                                <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($role); ?></p>
                            </div>
                            <a href="profile.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-circle mr-2"></i><?php echo t('profile'); ?>
                            </a>
                            <a href="settings.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-2"></i><?php echo t('settings'); ?>
                            </a>
                            <a href="logout.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i><?php echo t('logout'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="p-4 <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <div class="container mx-auto">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Suppliers Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">Supplier List</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Person</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($suppliers) > 0): ?>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($supplier['email'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $supplier['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo ucfirst($supplier['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="edit-supplier-btn text-blue-600 hover:text-blue-900 mr-3" 
                                                    data-id="<?php echo $supplier['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($supplier['name']); ?>"
                                                    data-contact-person="<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>"
                                                    data-email="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>"
                                                    data-phone="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>"
                                                    data-address="<?php echo htmlspecialchars($supplier['address'] ?? ''); ?>"
                                                    data-status="<?php echo $supplier['status']; ?>">
                                                    Edit
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this supplier?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $supplier['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            No suppliers found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add/Edit Supplier Modal -->
    <div id="supplier-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-4 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="modal-title">Add New Supplier</h3>
                <form method="POST" id="supplier-form">
                    <input type="hidden" name="action" value="add" id="form-action">
                    <input type="hidden" name="id" value="" id="supplier-id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="name">
                                Supplier Name *
                            </label>
                            <input type="text" id="name" name="name" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="contact-person">
                                Contact Person
                            </label>
                            <input type="text" id="contact-person" name="contact_person"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="email">
                                Email
                            </label>
                            <input type="email" id="email" name="email"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="phone">
                                Phone
                            </label>
                            <input type="text" id="phone" name="phone"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-1" for="address">
                            Address
                        </label>
                        <textarea id="address" name="address" rows="2"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-1" for="status">
                            Status
                        </label>
                        <select id="status" name="status"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="flex items-center justify-between mt-6">
                        <button type="button" id="cancel-supplier-btn"
                            class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                            Save Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Simple JavaScript for modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('supplier-modal');
            const addSupplierBtn = document.getElementById('add-supplier-btn');
            const cancelBtn = document.getElementById('cancel-supplier-btn');
            const formAction = document.getElementById('form-action');
            const modalTitle = document.getElementById('modal-title');
            
            addSupplierBtn.addEventListener('click', function() {
                // Reset form for adding new supplier
                document.getElementById('supplier-form').reset();
                formAction.value = 'add';
                document.getElementById('supplier-id').value = '';
                modalTitle.textContent = 'Add New Supplier';
                modal.classList.remove('hidden');
            });
            
            cancelBtn.addEventListener('click', function() {
                modal.classList.add('hidden');
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.add('hidden');
                }
            });
            
            // Edit supplier functionality
            const editButtons = document.querySelectorAll('.edit-supplier-btn');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const contactPerson = this.getAttribute('data-contact-person');
                    const email = this.getAttribute('data-email');
                    const phone = this.getAttribute('data-phone');
                    const address = this.getAttribute('data-address');
                    const status = this.getAttribute('data-status');
                    
                    // Populate form with existing data
                    document.getElementById('supplier-id').value = id;
                    document.getElementById('name').value = name;
                    document.getElementById('contact-person').value = contactPerson;
                    document.getElementById('email').value = email;
                    document.getElementById('phone').value = phone;
                    document.getElementById('address').value = address;
                    document.getElementById('status').value = status;
                    
                    // Change form to edit mode
                    formAction.value = 'edit';
                    modalTitle.textContent = 'Edit Supplier';
                    modal.classList.remove('hidden');
                });
            });
            
            // User profile dropdown toggle
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');
            
            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('hidden');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function() {
                    userDropdown.classList.add('hidden');
                });
                
                // Prevent closing when clicking inside dropdown
                userDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });
    </script>
</body>
</html>