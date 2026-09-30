<?php

/**
 * AuthMiddleware
 * Kiểm tra JWT từ Cookie
 */

require_once __DIR__ . '/../config/JwtHelper.php';
require_once __DIR__ . '/../config/dbConfig.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role_permissions.php';
require_once __DIR__ . '/../models/Role.php';

class AuthMiddleware
{
    private const COOKIE_NAME = 'cinemax_token';

    private const PERMISSION_REFRESH_TIME = 300; // 5 phút

    /**
     * Khởi động session nếu chưa có
     */
    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Kiểm tra HTTPS
     */
    private static function isHttps(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            ||
            (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        );
    }

    /**
     * Đặt JWT cookie
     */
    public static function setAuthCookie(array $user): void
    {
        self::ensureSessionStarted();

        if (
            !isset($user['user_id']) ||
            !isset($user['full_name']) ||
            !isset($user['email']) ||
            !isset($user['role_id'])
        ) {
            throw new InvalidArgumentException(
                'Thông tin user không đầy đủ để tạo JWT.'
            );
        }

        $payload = [
            'user_id' => (int)$user['user_id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role_id' => (int)$user['role_id']
        ];

        $token = JwtHelper::encode($payload);

        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires' => time() + JwtHelper::getDefaultTTL(),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => self::isHttps()
            ]
        );
    }

    /**
     * Xóa JWT cookie
     */
    public static function clearAuthCookie(): void
    {
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => self::isHttps()
            ]
        );
    }

    /**
     * Lấy user hiện tại từ JWT
     */
    public static function getAuthUser(): ?array
    {
        self::ensureSessionStarted();

        $token = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (empty($token)) {
            return null;
        }

        $user = JwtHelper::decode($token);

        if (!$user) {
            return null;
        }

        if (
            !isset($user['user_id']) ||
            !isset($user['role_id'])
        ) {
            return null;
        }

        /*
         * Kiểm tra user còn tồn tại và đang hoạt động.
         * Không chỉ tin hoàn toàn vào dữ liệu trong JWT.
         */
        try {
            $userModel = new User(getDBConnection());

            $dbUser = $userModel->getUserById(
                (int)$user['user_id']
            );

            if (!$dbUser) {
                self::clearAuthCookie();
                return null;
            }

            // Đồng bộ thông tin mới nhất từ DB
            $user['user_id'] = (int)$dbUser['user_id'];
            $user['full_name'] = $dbUser['full_name'];
            $user['email'] = $dbUser['email'];
            $user['role_id'] = (int)$dbUser['role_id'];
        } catch (Throwable $e) {
            return null;
        }

        /*
         * Load permissions
         */
        $shouldReload = false;

        if (!isset($_SESSION['permissions'])) {
            $shouldReload = true;
        }

        if (!isset($_SESSION['permission_last_reload'])) {
            $shouldReload = true;
        }

        if (
            isset($_SESSION['permission_last_reload']) &&
            time() - (int)$_SESSION['permission_last_reload']
            > self::PERMISSION_REFRESH_TIME
        ) {
            $shouldReload = true;
        }

        if ($shouldReload) {
            try {
                $rolePermissionModel =
                    new Role_permissions(getDBConnection());

                $permissions =
                    $rolePermissionModel->getPermissionsByRoleId(
                        (int)$user['role_id']
                    );

                $_SESSION['permissions'] = $permissions;
                $_SESSION['permission_last_reload'] = time();
            } catch (Throwable $e) {
                $_SESSION['permissions'] = [];
                $_SESSION['permission_last_reload'] = time();
            }
        }

        $_SESSION['user'] = $user;

        return $user;
    }

    /**
     * Bắt buộc đăng nhập
     */
    public static function requireLogin(
        string $redirectUrl = '/Cinemax/views/auth/login.php?error=required'
    ): array {
        self::ensureSessionStarted();

        $user = self::getAuthUser();

        if (!$user) {
            self::clearAuthCookie();

            header('Location: ' . $redirectUrl);
            exit;
        }

        return $user;
    }

    /**
     * Bắt buộc quyền Admin
     */
    public static function requireAdmin(): array
    {
        self::ensureSessionStarted();

        $user = self::requireLogin();

        $roleModel = new Role(getDBConnection());

        $role = $roleModel->getRoleById(
            (int)$user['role_id']
        );

        $roleName = strtolower(
            trim($role['role_name'] ?? '')
        );

        /*
         * Hiện tại hệ thống có thể có nhiều role.
         * Chỉ cho các role có tên admin/administrator
         * truy cập khu vực admin.
         */
        if (
            $roleName !== 'admin' &&
            $roleName !== 'administrator'
        ) {
            header(
                'Location: /Cinemax/views/auth/login.php?error=unauthorized'
            );
            exit;
        }

        return $user;
    }

    /**
     * Lấy tên cookie
     */
    public static function getCookieName(): string
    {
        return self::COOKIE_NAME;
    }
}