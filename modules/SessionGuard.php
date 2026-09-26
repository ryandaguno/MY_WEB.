<?php
require_once __DIR__ . '/../config/config.php';

class SessionGuard {

    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function requireClient(): void {
        self::start();
        if (($_SESSION['role'] ?? '') !== 'client') {
            // Save the current URL so we can redirect back after login
            $returnTo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                      . '://' . ($_SERVER['HTTP_HOST'] ?? '')
                      . ($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . BASE_URL . '/public/auth/login.php?return=' . urlencode($returnTo));
            exit;
        }
        self::checkInactivity(SESSION_TIMEOUT_CLIENT, BASE_URL . '/public/auth/login.php');
    }

    public static function requireAdmin(): void {
        self::start();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            header('Location: ' . BASE_URL . '/admin/auth/login.php');
            exit;
        }
        self::checkInactivity(SESSION_TIMEOUT_ADMIN, BASE_URL . '/admin/auth/login.php');
    }

    public static function checkInactivity(int $timeoutSeconds, string $redirectTo): void {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $timeoutSeconds) {
                self::destroySession();
                header('Location: ' . $redirectTo . '?timeout=1');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }

    public static function destroySession(): void {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function isClientLoggedIn(): bool {
        self::start();
        return ($_SESSION['role'] ?? '') === 'client';
    }

    public static function isAdminLoggedIn(): bool {
        self::start();
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    public static function setClientSession(array $client): void {
        $_SESSION['role']          = 'client';
        $_SESSION['client_id']     = (int) $client['id'];
        $_SESSION['username']      = $client['username'];
        $_SESSION['email']         = $client['email'];
        $_SESSION['last_activity'] = time();
    }

    public static function setAdminSession(array $admin): void {
        $_SESSION['role']          = 'admin';
        $_SESSION['admin_id']      = (int) $admin['id'];
        $_SESSION['username']      = $admin['username'];
        $_SESSION['email']         = $admin['email'];
        $_SESSION['last_activity'] = time();
    }

    public static function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token']) &&
               hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function flashMessage(string $key, string $message): void {
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}
