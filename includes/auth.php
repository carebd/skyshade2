<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/db.php';

class Auth {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS'])]);
            session_start();
        }
    }

    public static function login(string $username, string $password): bool|string {
        $user = DB::one("SELECT * FROM cd_users WHERE (username=? OR email=?) AND is_active=1", [$username, $username]);
        if (!$user) return 'Invalid username or password.';
        if (!password_verify($password, $user['password_hash'])) return 'Invalid username or password.';

        $_SESSION['cd_user_id']   = $user['id'];
        $_SESSION['cd_username']  = $user['username'];
        $_SESSION['cd_role']      = $user['role'];
        $_SESSION['cd_login_time'] = time();

        DB::query("UPDATE cd_users SET last_login=NOW() WHERE id=?", [$user['id']]);
        return true;
    }

    public static function logout(): void {
        session_destroy();
        header('Location: login.php'); exit;
    }

    public static function check(): array {
        self::start();
        if (empty($_SESSION['cd_user_id'])) {
            header('Location: login.php'); exit;
        }
        if (time() - ($_SESSION['cd_login_time'] ?? 0) > SESSION_LIFETIME) {
            session_destroy();
            header('Location: login.php?timeout=1'); exit;
        }
        $_SESSION['cd_login_time'] = time(); // rolling session
        return DB::one("SELECT * FROM cd_users WHERE id=?", [$_SESSION['cd_user_id']]) ?? [];
    }

    public static function requireAdmin(): array {
        $user = self::check();
        if ($user['role'] !== 'admin') { header('Location: index.php'); exit; }
        return $user;
    }

    public static function isLoggedIn(): bool {
        self::start();
        return !empty($_SESSION['cd_user_id']);
    }
}
