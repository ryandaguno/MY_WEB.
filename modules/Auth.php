<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/NotificationService.php';

class Auth {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ----------------------------------------------------------------
    // CLIENT REGISTRATION
    // ----------------------------------------------------------------
    public function register(array $data): array {
        $errors = Validator::validateRegistration($data, $this->db);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Auto-add is_approved column if not exists
        try {
            $this->db->exec("ALTER TABLE clients ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 0");
        } catch (PDOException $e) { /* already exists */ }

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400); // 24h
        $hash    = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->db->prepare(
            'INSERT INTO clients (username, email, password_hash, phone, is_verified, is_approved, verification_token, token_expires_at)
             VALUES (?, ?, ?, ?, 0, 0, ?, ?)'
        );
        $stmt->execute([
            Validator::sanitize($data['username']),
            strtolower(trim($data['email'])),
            $hash,
            Validator::sanitize($data['phone']),
            $token,
            $expires,
        ]);

        $ns = new NotificationService();
        $ns->sendEmailVerification(strtolower(trim($data['email'])), $token);

        return ['success' => true];
    }

    // ----------------------------------------------------------------
    // EMAIL VERIFICATION
    // ----------------------------------------------------------------
    public function verifyEmail(string $token): array {
        $stmt = $this->db->prepare(
            'SELECT id, token_expires_at, is_verified FROM clients WHERE verification_token = ?'
        );
        $stmt->execute([$token]);
        $client = $stmt->fetch();

        if (!$client) return ['success' => false, 'message' => 'Invalid or expired link.'];
        if ($client['is_verified']) return ['success' => true, 'message' => 'Email already verified. Please log in.'];
        if (strtotime($client['token_expires_at']) < time()) {
            return ['success' => false, 'message' => 'This link has expired. Please request a new one.'];
        }

        $this->db->prepare(
            'UPDATE clients SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE id = ?'
        )->execute([$client['id']]);

        return ['success' => true, 'message' => 'Email verified! Your account is now pending admin approval. You will be notified once approved.'];
    }

    // ----------------------------------------------------------------
    // CLIENT LOGIN
    // ----------------------------------------------------------------
    public function login(string $email, string $password): array {
        $email = strtolower(trim($email));

        // Accept email OR username
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE email = ? OR username = ?');
        $stmt->execute([$email, $email]);
        $client = $stmt->fetch();

        if (!$client) {
            return ['success' => false, 'message' => 'The email or password is incorrect.'];
        }

        // Lockout check
        if ($client['locked_until'] && strtotime($client['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again later.'];
        }

        if (!$client['is_verified']) {
            return ['success' => false, 'message' => 'Please verify your email address before logging in. Check your inbox for the verification link.'];
        }

        if (isset($client['is_approved']) && !$client['is_approved']) {
            return ['success' => false, 'message' => 'Your account is pending admin approval. Please wait for confirmation.'];
        }

        if (!password_verify($password, $client['password_hash'])) {
            $this->incrementLoginFailures($client['id'], 'clients');
            return ['success' => false, 'message' => 'The email or password is incorrect.'];
        }

        // Reset failures on success
        $this->db->prepare('UPDATE clients SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?')
                 ->execute([$client['id']]);

        return ['success' => true, 'client' => $client];
    }

    // ----------------------------------------------------------------
    // ADMIN LOGIN
    // ----------------------------------------------------------------
    public function adminLogin(string $username, string $password): array {
        // Accept username or email
        $stmt = $this->db->prepare('SELECT * FROM admins WHERE username = ? OR email = ?');
        $stmt->execute([trim($username), trim($username)]);
        $admin = $stmt->fetch();

        if (!$admin) {
            return ['success' => false, 'message' => 'The username or password is incorrect.'];
        }
        if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again later.'];
        }
        if (!password_verify($password, $admin['password_hash'])) {
            $this->incrementLoginFailures($admin['id'], 'admins');
            return ['success' => false, 'message' => 'The username or password is incorrect.'];
        }
        $this->db->prepare('UPDATE admins SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?')
                 ->execute([$admin['id']]);

        return ['success' => true, 'admin' => $admin];
    }

    // ----------------------------------------------------------------
    // FORGOT PASSWORD
    // ----------------------------------------------------------------
    public function sendForgotPassword(string $email): void {
        $email = strtolower(trim($email));
        $stmt  = $this->db->prepare('SELECT id FROM clients WHERE email = ?');
        $stmt->execute([$email]);
        $client = $stmt->fetch();

        if ($client) {
            // Invalidate old tokens
            $this->db->prepare('UPDATE password_resets SET used = 1 WHERE email = ?')->execute([$email]);
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1h
            $this->db->prepare(
                'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)'
            )->execute([$email, $token, $expires]);

            $ns = new NotificationService();
            $ns->sendPasswordReset($email, $token);
        }
        // Always return silently (anti-enumeration)
    }

    // ----------------------------------------------------------------
    // RESET PASSWORD
    // ----------------------------------------------------------------
    public function resetPassword(string $token, string $newPassword): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM password_resets WHERE token = ? AND used = 0'
        );
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) return ['success' => false, 'message' => 'Invalid or already-used reset link.'];
        if (strtotime($reset['expires_at']) < time()) {
            return ['success' => false, 'message' => 'This link has expired. Please request a new one.'];
        }
        $errors = Validator::validatePassword($newPassword);
        if (!empty($errors)) return ['success' => false, 'message' => $errors[0]];

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare('UPDATE clients SET password_hash = ? WHERE email = ?')
                 ->execute([$hash, $reset['email']]);
        $this->db->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')
                 ->execute([$token]);

        return ['success' => true];
    }

    // ----------------------------------------------------------------
    // RESEND VERIFICATION
    // ----------------------------------------------------------------
    public function resendVerification(string $email): void {
        $email = strtolower(trim($email));
        $stmt  = $this->db->prepare('SELECT id, is_verified FROM clients WHERE email = ?');
        $stmt->execute([$email]);
        $client = $stmt->fetch();
        if (!$client || $client['is_verified']) return;

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400);
        $this->db->prepare(
            'UPDATE clients SET verification_token = ?, token_expires_at = ? WHERE email = ?'
        )->execute([$token, $expires, $email]);

        $ns = new NotificationService();
        $ns->sendEmailVerification($email, $token);
    }

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------
    private function incrementLoginFailures(int $id, string $table): void {
        $stmt = $this->db->prepare("SELECT failed_login_attempts FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $attempts = ($row['failed_login_attempts'] ?? 0) + 1;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION_MINUTES * 60);
            $this->db->prepare("UPDATE $table SET failed_login_attempts = ?, locked_until = ? WHERE id = ?")
                     ->execute([$attempts, $lockedUntil, $id]);
        } else {
            $this->db->prepare("UPDATE $table SET failed_login_attempts = ? WHERE id = ?")
                     ->execute([$attempts, $id]);
        }
    }
}
