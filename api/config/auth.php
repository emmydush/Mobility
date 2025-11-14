<?php
class Auth {
    private $conn;
    private $user_id;
    private $user_role;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->checkSession();
    }

    private function checkSession() {
        session_start();
        $this->user_id = $_SESSION['user_id'] ?? null;
        $this->user_role = $_SESSION['user_role'] ?? null;

        if ($this->user_id) {
            // Update last activity
            $query = "UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP 
                     WHERE user_id = ? AND token = ? AND expires_at > CURRENT_TIMESTAMP";
            $stmt = $this->conn->prepare($query);
            $token = $_SESSION['token'] ?? '';
            $stmt->bindParam(1, $this->user_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $token, PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    public function login($username, $password) {
        $query = "SELECT id, username, password, role, status FROM users WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $username, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($result)) {
            $user = $result[0];
            if ($user['status'] === 'inactive') {
                return ["success" => false, "message" => "Account is inactive"];
            }

            if (password_verify($password, $user['password'])) {
                // Generate session token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Store session
                $session_query = "INSERT INTO user_sessions (user_id, token, device_info, ip_address, expires_at) 
                                VALUES (?, ?, ?, ?, ?)";
                $session_stmt = $this->conn->prepare($session_query);
                $device_info = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                
                $session_stmt->bindParam(1, $user['id'], PDO::PARAM_INT);
                $session_stmt->bindParam(2, $token, PDO::PARAM_STR);
                $session_stmt->bindParam(3, $device_info, PDO::PARAM_STR);
                $session_stmt->bindParam(4, $ip_address, PDO::PARAM_STR);
                $session_stmt->bindParam(5, $expires, PDO::PARAM_STR);
                $session_stmt->execute();

                // Update last login
                $update_query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(1, $user['id'], PDO::PARAM_INT);
                $update_stmt->execute();

                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['token'] = $token;

                return [
                    "success" => true,
                    "user" => [
                        "id" => $user['id'],
                        "username" => $user['username'],
                        "role" => $user['role']
                    ],
                    "token" => $token
                ];
            }
        }

        return ["success" => false, "message" => "Invalid credentials"];
    }

    public function logout() {
        if ($this->user_id) {
            // Remove session from database
            $query = "DELETE FROM user_sessions WHERE user_id = ? AND token = ?";
            $stmt = $this->conn->prepare($query);
            $token = $_SESSION['token'] ?? '';
            $stmt->bindParam(1, $this->user_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $token, PDO::PARAM_STR);
            $stmt->execute();
        }

        // Clear session
        session_destroy();
        return ["success" => true, "message" => "Logged out successfully"];
    }

    public function isAuthenticated() {
        return $this->user_id !== null;
    }

    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Authentication required"]);
            exit;
        }
    }

    public function requireRole($roles) {
        // Removed role restrictions - all authenticated users can access everything
        $this->requireAuth();
        // All roles are now permitted
    }

    public function getUserId() {
        return $this->user_id;
    }

    public function getUserRole() {
        return $this->user_role;
    }

    // Check specific permissions
    public function can($action) {
        // Removed permission restrictions - all authenticated users can perform all actions
        return $this->isAuthenticated();
    }
}
?>