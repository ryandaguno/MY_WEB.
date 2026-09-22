<?php
require_once __DIR__ . '/../config/config.php';

class SessionGuard {

    /** Call once at the very top of every page to start DB-backed sessions */
    public static function start(): void {
        if (session_status() !== PHP_SESSION_NONE) return;

        // Use database session handler on Railway (when MYSQLHOST is set)
        if (getenv('MYSQLHOST')) {
            self::registerDbHandler();
        }

        ini_set('session.gc_maxlifetime', 86400);       // 24 h
        ini_set('session.cookie_lifetime', 86400);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', 1);
        }
        session_start();
    }

    // ----------------------------------------------------------------
    // DB-BACKED SESSION HANDLER
    // ----------------------------------------------------------------
    private static function registerDbHandler(): void {
        try {
            require_once __DIR__ . '/../config/db.php';
            $db = getDB();

            // Create sessions table if it doesn't exist
            $db->exec("CREATE TABLE IF NOT EXISTS php_sessions (
                session_id  VARCHAR(128) NOT NULL PRIMARY KEY,
                data        MEDIUMTEXT   NOT NULL DEFAULT '',
                last_access INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            session_set_save_handler(
                // open
                function($path, $name) { return true; },
                // close
                function() { return true; },
                // read
                function($id) use ($db) {
                    try {
                        $s = $db->prepare('SELECT data FROM php_sessions WHERE session_id = ? AND last_access > ?');
                        $s->execute([$id, time() - 86400]);
                        $row = $s->fetch(PDO::FETCH_ASSOC);
                        return $row ? $row['data'] : '';
                    } catch (Exception $e) { return ''; }
                },
                // write
                function($id, $data) use ($db) {
                    try {
                        $s = $db->prepare('REPLACE INTO php_sessions (session_id, data, last_access) VALUES (?, ?, ?)');
                        $s->execute([$id, $data, time()]);
                        return true;
                    } catch (Exception $e) { return false; }
                },
                // destroy
                function($id) use ($db) {
                    try {
                        $db->prepare('DELETE FROM php_sessions WHERE session_id = ?')->execute([$id]);
                        return true;
                    } catch (Exception $e) { return false; }
                },
                // gc
                function($maxlifetime) use ($db) {
                    try {
                        $db->prepare('DELETE FROM php_sessions WHERE last_access < ?')->execute([time() - $maxlifetime]);
                        return true;
                    } catch (Exception $e) { return false; }
                }
            );
            register_shutdown_function('session_write_close');
        } catch (Exception $e) {
            // Fall back to file sessions silently
        }
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
