<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Cinemax</title>

    <link rel="stylesheet" href="../../public/assets/css/style.css">
    <link rel="stylesheet" href="../../public/assets/css/auth.css">
</head>

<body class="auth-body">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <a href="../user/index.php" class="logo" style="text-decoration: none;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2.5 16.5l19-9m-19 0l19 9M7 5v14m10-14v14"></path>
                    </svg>
                    <span>Cinemax</span>
                </a>
                <h1>Đăng nhập</h1>
                <p>Chào mừng bạn quay trở lại</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <?php
                    if ($_GET['error'] == 'empty') echo 'Vui lòng nhập đủ email và mật khẩu!';
                    elseif ($_GET['error'] == 'invalid') echo 'Email hoặc mật khẩu không chính xác!';
                    elseif ($_GET['error'] == 'unauthorized') echo 'Bạn không đủ quyền hạn để vào trang đó!';
                    elseif ($_GET['error'] == 'required') echo 'Bạn cần đăng nhập để thực hiện chức năng này!';
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['redirect']) && strpos($_GET['redirect'], 'seat_selection') !== false): ?>
                <div class="alert alert-info" style="background: #1a3a5c; border-left: 4px solid #3498db; color: #fff; padding: 12px 16px; margin-bottom: 20px; border-radius: 4px;">
                    Vui lòng đăng nhập để tiếp tục đặt vé xem phim.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
                <div class="alert alert-success">
                    Đăng ký tài khoản thành công! Vui lòng đăng nhập.
                </div>
            <?php endif; ?>

            <form id="loginForm" class="auth-form" action="../../public/index.php?action=login" method="POST">
                <?php if (isset($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="name@example.com" required>
                </div>
                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            Hiện/Ẩn
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">
                    <span>Đăng nhập</span>
                </button>
            </form>

            <div class="auth-footer">
                <p>Chưa có tài khoản? <a href="register.php" class="link">Đăng ký ngay</a></p>
            </div>
        </div>
        <div class="auth-image">
        </div>
    </div>

    <script src="../../public/assets/js/all_effects.js"></script>
</body>

</html>