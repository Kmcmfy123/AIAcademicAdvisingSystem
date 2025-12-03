<?php
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register($email, $password, $firstName, $lastName, $role = 'student') {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        // Check if email exists
        $existing = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));
        
        // Insert user
        $sql = "INSERT INTO users (email, password_hash, role, first_name, last_name, verification_token) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            $userId = $this->db->insert($sql, [
                $email, $passwordHash, $role, $firstName, $lastName, $verificationToken
            ]);
            
            // Send verification email (implement email sending)
            // $this->sendVerificationEmail($email, $verificationToken);
            
            return [
                'success' => true, 
                'message' => 'Registration successful. Please verify your email.',
                'user_id' => $userId
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $password) {
        $sql = "SELECT id, email, password_hash, role, first_name, last_name, is_verified 
                FROM users WHERE email = ?";
        $user = $this->db->fetchOne($sql, [$email]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // if (!$user['is_verified']) {
        //     return ['success' => false, 'message' => 'Please verify your email first'];
        // }
        
        // Create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['logged_in'] = true;
        
        // Log activity
        $this->logActivity($user['id'], 'login', 'User logged in');
        
        return ['success' => true, 'role' => $user['role']];
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/main/login.php');
            exit;
        }
    }
    
    public function requireRole($allowedRoles) {
        $this->requireLogin();
        
        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            header('Location: ' . APP_URL . '/index.php');

            exit;
        }
    }
    
    public function verifyEmail($token) {
        $sql = "UPDATE users SET is_verified = TRUE, verification_token = NULL 
                WHERE verification_token = ?";
        $rows = $this->db->update($sql, [$token]);
        
        return $rows > 0;
    }
    
    private function logActivity($userId, $action, $details) {
        $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)";
        $this->db->insert($sql, [$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}