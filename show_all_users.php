<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once 'api/config/database.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    // Redirect to dashboard if not admin
    header("Location: dashboard.php");
    exit();
}

// Fetch all users with session information for the current tenant
$users = array();
$query = "SELECT u.*, COUNT(us.id) as active_sessions 
          FROM users u 
          LEFT JOIN user_sessions us ON u.id = us.user_id AND us.expires_at > NOW()
          WHERE u.tenant_id = ?
          GROUP BY u.id 
          ORDER BY u.username";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $_SESSION['tenant_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
}

// Fetch user statistics
$total_users = count($users);
$active_users = 0;
$inactive_users = 0;
$admin_users = 0;
$manager_users = 0;
$cashier_users = 0;
$supervisor_users = 0;
$staff_users = 0;

foreach ($users as $user) {
    if ($user['status'] === 'active') {
        $active_users++;
    } else {
        $inactive_users++;
    }
    
    switch ($user['role']) {
        case 'admin':
            $admin_users++;
            break;
        case 'manager':
            $manager_users++;
            break;
        case 'cashier':
            $cashier_users++;
            break;
        case 'supervisor':
            $supervisor_users++;
            break;
        case 'staff':
            $staff_users++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users - Mobility Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">All Users</h1>
            <a href="users.php" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                <i class="fas fa-arrow-left mr-2"></i>Back to User Management
            </a>
        </div>
        
        <!-- User Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-4 text-white">
                <div class="text-green-100 text-sm">Total Users</div>
                <div class="text-2xl font-bold"><?php echo $total_users; ?></div>
            </div>
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-4 text-white">
                <div class="text-green-100 text-sm">Active Users</div>
                <div class="text-2xl font-bold"><?php echo $active_users; ?></div>
            </div>
            <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow p-4 text-white">
                <div class="text-red-100 text-sm">Inactive Users</div>
                <div class="text-2xl font-bold"><?php echo $inactive_users; ?></div>
            </div>
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow p-4 text-white">
                <div class="text-purple-100 text-sm">Admins</div>
                <div class="text-2xl font-bold"><?php echo $admin_users; ?></div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-4 text-white">
                <div class="text-indigo-100 text-sm">Managers</div>
                <div class="text-2xl font-bold"><?php echo $manager_users; ?></div>
            </div>
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg shadow p-4 text-white">
                <div class="text-yellow-100 text-sm">Cashiers</div>
                <div class="text-2xl font-bold"><?php echo $cashier_users; ?></div>
            </div>
            <div class="bg-gradient-to-r from-teal-500 to-teal-600 rounded-lg shadow p-4 text-white">
                <div class="text-teal-100 text-sm">Supervisors</div>
                <div class="text-2xl font-bold"><?php echo $supervisor_users; ?></div>
            </div>
            <div class="bg-gradient-to-r from-gray-500 to-gray-600 rounded-lg shadow p-4 text-white">
                <div class="text-gray-100 text-sm">Staff</div>
                <div class="text-2xl font-bold"><?php echo $staff_users; ?></div>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-800">User List</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <div class="text-sm text-gray-500">
                                            ID: <?php echo $user['id']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div><?php echo htmlspecialchars($user['email']); ?></div>
                                        <?php if (!empty($user['phone'])): ?>
                                            <div><?php echo htmlspecialchars($user['phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 
                                                  ($user['role'] === 'manager' ? 'bg-green-100 text-green-800' : 
                                                   ($user['role'] === 'supervisor' ? 'bg-yellow-100 text-yellow-800' : 
                                                    ($user['role'] === 'staff' ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800'))); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo $user['active_sessions']; ?> active
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No users found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>