<?php
require_once __DIR__ . '/../config/config.php';

class SessionGuard {

    /** Call once at the very top of every page to start DB-backed sessions */
    public static function start(): void {
        if (session_status() !== PHP_SESSION_NONE) return;

        // Extend session lifetime so it survives on Railway
        ini_set('session.gc_maxlifetime', 86400);
        ini_set('session.cookie_lifetime', 86400);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', 1);
        }
        // Store sessions in /tmp which persists within a single container instance
        // and use a fixed save path to avoid Railway's default temp dir issues
        $savePath = sys_get_temp_dir() . '/selah_sessions';
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0700, true);
        }
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        session_start();
    }

    // ----------------------------------------------------------------
    // DB-BACKED SESSION HANDLER (disabled - caused blank pages)
    // ----------------------------------------------------------------
    private static function registerDbHandler(): void {
        // Intentionally empty - file sessions work fine
    }

    public static function requireClient(): void {
        self::start();
        if (($_SESSION['role'] ?? '') !== 'client') {
            header('Location: ' . BASE_URL . '/public/auth/login.php');
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
