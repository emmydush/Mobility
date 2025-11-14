<?php
/**
 * User functions for the inventory management system
 */

/**
 * Get current user's profile information
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return array|null User profile data or null if not found
 */
function getCurrentUserProfile($conn, $user_id) {
    if (!$conn || !$user_id) {
        return null;
    }
    
    $query = "SELECT u.username, u.role, u.profile_picture, t.business_name FROM users u LEFT JOIN tenants t ON u.tenant_id = t.id WHERE u.id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user_profile;
    }
    
    return null;
}

/**
 * Get current user's profile picture URL
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return string Profile picture URL or empty string
 */
function getCurrentUserProfilePicture($conn, $user_id) {
    $user_profile = getCurrentUserProfile($conn, $user_id);
    
    if ($user_profile && !empty($user_profile['profile_picture'])) {
        $profile_picture = $user_profile['profile_picture'];
        // Check if file exists and is readable
        if (file_exists($profile_picture) && is_readable($profile_picture)) {
            return $profile_picture;
        }
    }
    
    return ''; // Return empty string, caller will use default icon
}

/**
 * Get current tenant information
 * 
 * @param PDO $conn Database connection
 * @return array|null Tenant data or null if not found
 */
function getCurrentTenantInfo($conn) {
    if (!isset($_SESSION['tenant_id']) || !$_SESSION['tenant_id']) {
        return null;
    }
    
    $query = "SELECT * FROM tenants WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
        $stmt->execute();
        $tenant_info = $stmt->fetch(PDO::FETCH_ASSOC);
        return $tenant_info;
    }
    
    return null;
}

/**
 * Check if user has a specific permission
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param string $permission Permission name
 * @return bool True if user has permission, false otherwise
 */
function userHasPermission($conn, $user_id, $permission) {
    // Validate inputs
    if (!$conn || !$user_id || !$permission) {
        return false;
    }
    
    // Admin users have all permissions
    $role_query = "SELECT role FROM users WHERE id = ?";
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $role_stmt->execute();
    $role_result = $role_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($role_result) {
        $user_role = $role_result['role'];
        if ($user_role === 'admin') {
            return true;
        }
    }
    
    // Check specific permission
    $query = "SELECT up.id FROM user_permissions up 
              JOIN permissions p ON up.permission_id = p.id 
              WHERE up.user_id = ? AND p.name = ? AND up.granted = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $permission, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_permission = count($result) > 0;
    
    return $has_permission;
}

/**
 * Check if user has any of the specified permissions
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param array $permissions Array of permission names
 * @return bool True if user has any of the permissions, false otherwise
 */
function userHasAnyPermission($conn, $user_id, $permissions) {
    // Validate inputs
    if (!$conn || !$user_id || !is_array($permissions) || empty($permissions)) {
        return false;
    }
    
    // Admin users have all permissions
    $role_query = "SELECT role FROM users WHERE id = ?";
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $role_stmt->execute();
    $role_result = $role_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($role_result) {
        $user_role = $role_result['role'];
        if ($user_role === 'admin') {
            return true;
        }
    }
    
    // Check if user has any of the specified permissions
    $placeholders = str_repeat('?,', count($permissions) - 1) . '?';
    $query = "SELECT up.id FROM user_permissions up 
              JOIN permissions p ON up.permission_id = p.id 
              WHERE up.user_id = ? AND p.name IN ({$placeholders}) AND up.granted = 1";
    
    $stmt = $conn->prepare($query);
    $params = array_merge([$user_id], $permissions);
    
    // Bind parameters
    for ($i = 0; $i < count($params); $i++) {
        $stmt->bindParam($i + 1, $params[$i], PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_permission = count($result) > 0;
    
    return $has_permission;
}

/**
 * Get all permissions for a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return array List of permission names
 */
function getUserPermissions($conn, $user_id) {
    // Validate inputs
    if (!$conn || !$user_id) {
        return [];
    }
    
    // Admin users have all permissions
    $role_query = "SELECT role FROM users WHERE id = ?";
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $role_stmt->execute();
    $role_result = $role_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($role_result) {
        $user_role = $role_result['role'];
        if ($user_role === 'admin') {
            // Return all permissions for admin
            $all_perms_query = "SELECT name FROM permissions";
            $all_perms_result = $conn->query($all_perms_query);
            $permissions = [];
            while ($row = $all_perms_result->fetch(PDO::FETCH_ASSOC)) {
                $permissions[] = $row['name'];
            }
            return $permissions;
        }
    }
    
    // Get specific user permissions
    $query = "SELECT p.name FROM user_permissions up 
              JOIN permissions p ON up.permission_id = p.id 
              WHERE up.user_id = ? AND up.granted = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $permissions = [];
    foreach ($result as $row) {
        $permissions[] = $row['name'];
    }
    
    return $permissions;
}

/**
 * Assign permissions to a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param array $permissions Array of permission names
 * @return bool True if successful, false otherwise
 */
function assignUserPermissions($conn, $user_id, $permissions) {
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Delete existing permissions for the user
        $delete_query = "DELETE FROM user_permissions WHERE user_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $delete_stmt->execute();
        
        // Insert new permissions
        if (!empty($permissions)) {
            $insert_query = "INSERT INTO user_permissions (user_id, permission_id, granted) 
                             SELECT ?, id, 1 FROM permissions WHERE name = ?";
            $insert_stmt = $conn->prepare($insert_query);
            
            foreach ($permissions as $permission) {
                $insert_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(2, $permission, PDO::PARAM_STR);
                $insert_stmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error assigning permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Hash a password using PHP's password_hash function
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against its hash
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool True if password matches hash, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a random token for session or password reset
 * 
 * @param int $length Length of the token
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Get user's role
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return string|null User role or null if not found
 */
function getUserRole($conn, $user_id) {
    if (!$conn || !$user_id) {
        return null;
    }
    
    $query = "SELECT role FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['role'] : null;
}

/**
 * Check if user is admin
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return bool True if user is admin, false otherwise
 */
function isAdmin($conn, $user_id) {
    $role = getUserRole($conn, $user_id);
    return $role === 'admin';
}

/**
 * Get all users with their roles
 * 
 * @param PDO $conn Database connection
 * @return array List of users
 */
function getAllUsers($conn) {
    $query = "SELECT id, username, email, role, status FROM users ORDER BY username";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update user's last login timestamp
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return bool Success status
 */
function updateUserLastLogin($conn, $user_id) {
    $query = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    return $stmt->execute();
}
?>