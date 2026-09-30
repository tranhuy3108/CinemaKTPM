<?php
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../config/dbConfig.php';
require_once __DIR__ . '/../services/RoleService.php';
require_once __DIR__ . '/../models/Role.php';

class AuthController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Xử lý đăng ký
     */
    public function register()
    {
        try {
            $fullName = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirmPassword'] ?? '';

            $this->authService->register($fullName, $email, $password, $confirmPassword, $phone);

            // Đăng ký thành công -> chuyển về trang login
            header('Location: ../views/auth/login.php?success=registered');
            exit;
        } catch (Exception $e) {
            $errorCode = $e->getMessage();
            header('Location: ../views/auth/register.php?error=' . urlencode($errorCode));
            exit;
        }
    }

    /**
     * Xử lý đăng nhập
     */
    public function login()
    {
        try {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            // AuthService sẽ xác thực + tạo JWT cookie
            $user = $this->authService->login($email, $password);

            //Xử lý phân quyền sau khi đăng nhập thành công
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            $conn = getDBConnection();
            $roleModel = new Role($conn);
            $roleService = new RoleService($roleModel);
            $permissions = $roleService->getAllPermissionsByRole($user['role_id']);
            $_SESSION['user'] = $user;
            $_SESSION['permissions'] = array_column($permissions, 'permission_code'); // Lưu mã quyền vào session

            // Kiểm tra redirect URL (cho booking flow)
            $redirectUrl = isset($_POST['redirect']) ? $_POST['redirect'] : (isset($_GET['redirect']) ? $_GET['redirect'] : '');
            
            // Redirect theo role hoặc redirect URL
            if ($user['role_id'] == 1) {
                header('Location: ../views/admin/index.php');
            } elseif (!empty($redirectUrl)) {
                // Đảm bảo redirect URL an toàn (chỉ cho phép redirect nội bộ)
                $redirectUrl = urldecode($redirectUrl);
                if (strpos($redirectUrl, '/') === 0 || strpos($redirectUrl, 'index.php') !== false) {
                    header('Location: ' . $redirectUrl);
                } else {
                    header('Location: ../views/user/index.php');
                }
            } else {
                header('Location: ../views/user/index.php');
            }
            exit;
        } catch (Exception $e) {
            $errorCode = $e->getMessage();
            header('Location: ../views/auth/login.php?error=' . urlencode($errorCode));
            exit;
        }
    }

    /**
     * Xử lý đăng xuất
     */
    public function logout()
    {
        $this->authService->logout();
        header('Location: ../views/auth/login.php');
        exit;
    }
}
