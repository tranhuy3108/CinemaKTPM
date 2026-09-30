<!DOCTYPE html>
<html lang="vi">

<?php

// =========================================================
// DATABASE
// =========================================================
require_once __DIR__ . '/../../config/dbConfig.php';


// =========================================================
// MODELS
// =========================================================
require_once __DIR__ . '/../../models/Movie.php';
require_once __DIR__ . '/../../models/Genre.php';
require_once __DIR__ . '/../../models/Promotion.php';
require_once __DIR__ . '/../../models/Show.php';
require_once __DIR__ . '/../../models/Ticket.php';
require_once __DIR__ . '/../../models/Bill.php';
require_once __DIR__ . '/../../models/User.php';


// =========================================================
// SERVICES
// =========================================================
require_once __DIR__ . '/../../services/AuthMiddleware.php';
require_once __DIR__ . '/../../services/MovieService.php';
require_once __DIR__ . '/../../services/PromotionService.php';
require_once __DIR__ . '/../../services/ShowService.php';
require_once __DIR__ . '/../../services/TicketService.php';
require_once __DIR__ . '/../../services/BillService.php';
require_once __DIR__ . '/../../services/UserService.php';


// =========================================================
// CONTROLLERS
// =========================================================
require_once __DIR__ . '/../../controllers/ShowController.php';
require_once __DIR__ . '/../../controllers/UserController.php';


// =========================================================
// SESSION
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// =========================================================
// AUTH
// =========================================================
// User trang chủ không bắt buộc đăng nhập.
// Chỉ lấy thông tin nếu đã đăng nhập.
$authUser = AuthMiddleware::getAuthUser();


// =========================================================
// DATABASE CONNECTION
// =========================================================
$conn = getDbConnection();


// =========================================================
// KHỞI TẠO MODELS
// =========================================================
$movieModel = new Movie($conn);
$genreModel = new Genre($conn);
$promotionModel = new Promotion($conn);
$showModel = new Show($conn);
$ticketModel = new Ticket($conn);
$billModel = new Bill($conn);
$userModel = new User($conn);


// =========================================================
// KHỞI TẠO SERVICES
// =========================================================
$promotionService = new PromotionService($promotionModel);

$movieService = new MovieService(
        $movieModel,
        $genreModel
);

$ticketService = new TicketService(
        $ticketModel
);

$showService = new ShowService(
        $showModel,
        $ticketService
);

$billService = new BillService(
        $billModel,
        $ticketService
);

$userService = new UserService(
        $userModel
);


// =========================================================
// KHỞI TẠO CONTROLLERS
// =========================================================
$showController = new ShowController(
        $showService
);

$userController = new UserController(
        $userService,
        $billService
);


// =========================================================
// PAGE WHITELIST
// =========================================================
// Chỉ cho phép các page nằm trong danh sách này.
$allowedPages = [
        'home',
        'movie_detail',
        'theaters',
        'movies',
        'promotions',
        'showtimes',
        'seat_selection',
        'food_selection',
        'payment',
        'booking_success',
        'my_bookings',
        '404'
];

$page = $_GET['page'] ?? 'home';
$action = $_GET['action'] ?? null;

if (!in_array($page, $allowedPages, true)) {
    $page = '404';
}


// =========================================================
// CONFIRM PAYMENT / CREATE BOOKING
// =========================================================
//
// Flow:
//
// seat_selection
//      ↓
// food_selection
//      ↓
// payment
//      ↓
// confirm_payment
//      ↓
// BillService::createBooking()
//      ↓
// Server kiểm tra ghế + giá vé + combo
//      ↓
// Tạo bill pending
//      ↓
// Giữ ghế booked
//      ↓
// Lưu combo
//      ↓
// booking_success
//
// QUAN TRỌNG:
// Không sử dụng grand_total từ browser.
// Không sử dụng price combo từ browser.
// Không sử dụng price ticket từ browser.
// =========================================================

if ($page === 'payment' && $action === 'confirm_payment') {

    // -----------------------------------------------------
    // User hiện tại
    // -----------------------------------------------------
    $userId = isset($authUser['user_id'])
            ? (int)$authUser['user_id']
            : 0;


    // -----------------------------------------------------
    // Show ID
    // -----------------------------------------------------
    $showtimeId = isset($_POST['showtime_id'])
            ? (int)$_POST['showtime_id']
            : 0;


    // -----------------------------------------------------
    // Ticket IDs
    // -----------------------------------------------------
    $ticketIds = [];

    if (
            isset($_POST['ticket_ids'])
            && is_array($_POST['ticket_ids'])
    ) {
        $ticketIds = array_values(
                array_unique(
                        array_filter(
                                array_map(
                                        'intval',
                                        $_POST['ticket_ids']
                                ),
                                function ($id) {
                                    return $id > 0;
                                }
                        )
                )
        );
    }


    // -----------------------------------------------------
    // Combo
    // -----------------------------------------------------
    $selectedCombos = [];

    if (
            isset($_POST['combos'])
            && is_array($_POST['combos'])
    ) {
        $selectedCombos = $_POST['combos'];
    }


    // -----------------------------------------------------
    // Kiểm tra đăng nhập
    // -----------------------------------------------------
    if ($userId <= 0) {

        header(
                'Location: index.php?page=payment&error=login_required'
        );

        exit;
    }


    // -----------------------------------------------------
    // Kiểm tra show
    // -----------------------------------------------------
    if ($showtimeId <= 0) {

        header(
                'Location: index.php?page=showtimes&error=invalid_show'
        );

        exit;
    }


    // -----------------------------------------------------
    // Kiểm tra ticket
    // -----------------------------------------------------
    if (empty($ticketIds)) {

        header(
                'Location: index.php?page=seat_selection&show_id=' .
                $showtimeId .
                '&error=no_seats'
        );

        exit;
    }


    // -----------------------------------------------------
    // CREATE BOOKING
    // -----------------------------------------------------
    try {

        /**
         * BillService sẽ:
         *
         * 1. Kiểm tra show.
         * 2. Kiểm tra show chưa bắt đầu.
         * 3. Lock ticket bằng FOR UPDATE.
         * 4. Kiểm tra ticket thuộc đúng show.
         * 5. Kiểm tra ticket còn available.
         * 6. Lấy giá vé từ DB.
         * 7. Lấy giá combo từ DB.
         * 8. Tự tính tổng tiền.
         * 9. Tạo bill pending.
         * 10. Chuyển ticket sang booked.
         * 11. Lưu bill_combos.
         * 12. Commit transaction.
         */
        $booking = $billService->createBooking(
                $userId,
                $showtimeId,
                $ticketIds,
                $selectedCombos
        );


        // -------------------------------------------------
        // Lấy Bill ID
        // -------------------------------------------------
        $billId = (int)$booking['bill_id'];


        // -------------------------------------------------
        // Thành công
        // -------------------------------------------------
        header(
                'Location: index.php?page=booking_success&bill_id=' .
                $billId
        );

        exit;

    } catch (Throwable $e) {

        /**
         * createBooking() đã tự rollback transaction
         * khi xảy ra lỗi.
         *
         * Ở đây chỉ xử lý redirect.
         */

        $errorMessage = $e->getMessage();

        if ($errorMessage === '') {
            $errorMessage = 'Không thể tạo đơn hàng.';
        }


        // -------------------------------------------------
        // Trở lại payment
        // -------------------------------------------------
        header(
                'Location: index.php?page=payment&error=' .
                urlencode($errorMessage)
        );

        exit;
    }
}


// =========================================================
// LOAD PAGE
// =========================================================
$contentPath = __DIR__ . "/pages/$page.php";

?>

<head>

    <?php include __DIR__ . '/partials/head.php'; ?>

</head>


<body>

<?php include __DIR__ . '/partials/header.php'; ?>


<main>

    <?php include $contentPath; ?>

</main>


<?php include __DIR__ . '/partials/footer.php'; ?>

</body>

</html>