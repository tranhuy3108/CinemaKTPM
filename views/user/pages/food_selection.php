<?php
// Kiểm tra đăng nhập trước khi đặt vé
if (!isset($_SESSION['user'])) {
    header("Location: /public/index.php?action=login");
    exit;
}

require_once __DIR__ . '/../../../config/dbConfig.php';
require_once __DIR__ . '/../../../models/FoodCombo.php';
require_once __DIR__ . '/../../../services/FoodComboService.php';

$conn = getDbConnection();
$comboModel = new FoodCombo($conn);
$comboService = new FoodComboService($comboModel);

$showtimeId = isset($_POST['showtime_id']) ? (int)$_POST['showtime_id'] : 0;
$ticketIds = isset($_POST['ticket_ids']) && is_array($_POST['ticket_ids']) ? array_values(array_filter(array_map('intval', $_POST['ticket_ids']))) : [];
$seatNames = isset($_POST['seat_names']) && is_array($_POST['seat_names']) ? array_values(array_filter(array_map('trim', $_POST['seat_names']))) : [];
$seatTotal = isset($_POST['seat_total']) ? (float)$_POST['seat_total'] : 0;

$showData = null;
if ($showtimeId > 0) {
    $sql = "SELECT s.show_id, s.show_date, s.start_time, s.end_time, s.base_price,
                   m.title AS movie_title, h.name AS hall_name, c.name AS cinema_name
            FROM shows s
            INNER JOIN movies m ON s.movie_id = m.movie_id
            INNER JOIN halls h ON s.hall_id = h.hall_id
            INNER JOIN cinemas c ON h.cinema_id = c.cinema_id
            WHERE s.show_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $showtimeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $showData = $result ? $result->fetch_assoc() : null;
    }
}

$combos = [];
try {
    $combos = $comboService->getAllCombos();
} catch (Exception $e) {
    $combos = [];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Chọn đồ ăn</title>
    <link rel="stylesheet" href="/Cinemax/public/assets/css/style.css">
    <link rel="stylesheet" href="/Cinemax/public/assets/css/seat-selection.css">
    <link rel="stylesheet" href="/Cinemax/public/assets/css/food-selection.css">
</head>

<body>
    <?php
    ?>
    <nav class="navbar">
        <div class="container">
            <div class="nav-content">
                <div class="logo"><span>Cinema</span></div>

                <div class="booking-steps">
                    <div class="step completed">
                        <span class="step-number">1</span>
                        <span class="step-label">Chọn suất</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">2</span>
                        <span class="step-label">Chọn ghế</span>
                    </div>
                    <div class="step active">
                        <span class="step-number">3</span>
                        <span class="step-label">Đồ ăn</span>
                    </div>
                    <div class="step">
                        <span class="step-number">4</span>
                        <span class="step-label">Thanh toán</span>
                    </div>
                </div>

                <a href="#" class="btn-back">
                    Hủy đơn
                </a>
            </div>
        </div>
    </nav>

    <div class="booking-container">
        <div class="container">
            <div class="booking-layout">

                <!-- FOOD SECTION -->
                <div class="food-section">
                    <div class="section-header">
                        <h2>Combo Bắp Nước & Ưu đãi</h2>
                        <p>Thêm đồ ăn để trải nghiệm điện ảnh trọn vẹn hơn</p>
                    </div>

                    <div class="food-grid">

                        <?php if (empty($combos)): ?>
                            <div style="padding: 16px; color: #aaa; border: 1px dashed #444; border-radius: 10px;">
                                Hiện chưa có combo/bắp nước.
                            </div>
                        <?php else: ?>
                            <?php foreach ($combos as $combo): ?>
                                <?php
                                $comboId = (int)($combo['combo_id'] ?? 0);
                                $comboName = (string)($combo['name'] ?? 'Combo');
                                $comboDesc = (string)($combo['description'] ?? '');
                                $comboPrice = (float)($combo['price'] ?? 0);
                                $comboImage = trim((string)($combo['image_url'] ?? ''));
                                if ($comboImage === '') {
                                    $comboImage = '/Cinemax/public/assets/uploads/combos/no-image.png';
                                } elseif (!preg_match('/^https?:\/\//i', $comboImage)) {
                                    $comboImage = '/Cinemax/' . ltrim($comboImage, '/');
                                }
                                ?>
                                <div class="food-card">
                                    <div class="food-img">
                                        <img src="<?= htmlspecialchars($comboImage) ?>" alt="<?= htmlspecialchars($comboName) ?>">
                                    </div>
                                    <div class="food-info">
                                        <h3><?= htmlspecialchars($comboName) ?></h3>
                                        <p class="desc"><?= htmlspecialchars($comboDesc !== '' ? $comboDesc : 'Combo bắp nước hấp dẫn') ?></p>
                                        <div class="price"><?= number_format($comboPrice, 0, ',', '.') ?> ₫</div>

                                        <div class="qty-control"
                                            data-id="<?= $comboId ?>"
                                            data-price="<?= $comboPrice ?>"
                                            data-name="<?= htmlspecialchars($comboName) ?>">
                                            <button type="button" class="btn-qty minus">-</button>
                                            <span class="qty-val">0</span>
                                            <button type="button" class="btn-qty plus">+</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- SIDEBAR -->
                <div class="booking-sidebar">
                    <div class="booking-summary">
                        <h3>Thông tin đặt vé</h3>

                        <div class="summary-block">
                            <h4 class="movie-title"><?= htmlspecialchars((string)($showData['movie_title'] ?? 'Đang cập nhật')) ?></h4>
                            <div class="info-row"><span>Rạp:</span> <strong><?= htmlspecialchars((string)($showData['cinema_name'] ?? 'Đang cập nhật')) ?></strong></div>
                            <div class="info-row"><span>Suất:</span> <strong><?= htmlspecialchars((string)(($showData['show_date'] ?? '') . ' ' . substr((string)($showData['start_time'] ?? ''), 0, 5))) ?></strong></div>
                            <div class="info-row"><span>Phòng:</span> <strong><?= htmlspecialchars((string)($showData['hall_name'] ?? 'Đang cập nhật')) ?></strong></div>
                            <div class="info-row">
                                <span>Ghế:</span>
                                <strong style="color: var(--primary-color);"><?= htmlspecialchars(!empty($seatNames) ? implode(', ', $seatNames) : 'Chưa chọn') ?></strong>
                            </div>
                        </div>

                        <div class="summary-block"
                            id="selectedFoodContainer"
                            style="display:none; border-top: 1px solid var(--border-color); padding-top: 15px; margin-top: 15px;">
                            <h4 style="font-size: 14px; color: var(--text-secondary); margin-bottom: 10px;">Đồ ăn</h4>
                            <div id="selectedFoodList"></div>
                        </div>

                        <div class="summary-section"
                            style="border-top: 1px solid var(--border-color); margin-top: 20px; padding-top: 20px;">
                            <div class="price-row">
                                <span>Tiền vé:</span>
                                <span id="seatTotalDisplay"><?= number_format($seatTotal, 0, ',', '.') ?> ₫</span>
                            </div>
                            <div class="price-row">
                                <span>Tiền đồ ăn:</span>
                                <span id="foodTotalDisplay">0 ₫</span>
                            </div>
                            <div class="price-row total">
                                <span>Tổng cộng</span>
                                <span id="grandTotalDisplay">0 ₫</span>
                            </div>
                        </div>

                        <form id="finalForm" action="index.php?page=payment" method="POST">
                            <div id="foodInputs"></div>
                            <input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
                            <input type="hidden" name="seat_total" id="seatTotalInput" value="<?= $seatTotal ?>">
                            <input type="hidden" name="seat_names" value="<?= htmlspecialchars(json_encode($seatNames, JSON_UNESCAPED_UNICODE)) ?>">
                            <?php foreach ($ticketIds as $tid): ?>
                                <input type="hidden" name="ticket_ids[]" value="<?= $tid ?>">
                            <?php endforeach; ?>
                            <input type="hidden" name="food_total" id="foodTotalInput" value="0">
                            <input type="hidden" name="grand_total" id="grandTotalInput" value="<?= $seatTotal ?>">
                            <input type="hidden" name="combos" id="foodsJsonInput" value="[]">

                            <button type="submit" class="btn-continue">
                                Xác nhận & Thanh toán
                                <svg width="20" height="20" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m9 18 6-6-6-6"></path>
                                </svg>
                            </button>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>
    <script>
        window.foodSelectionData = {
            seatTotal: <?= json_encode((float)$seatTotal) ?>,
            showtimeId: <?= json_encode($showtimeId) ?>
        };
    </script>
    <script src="/Cinemax/public/assets/js/food-selection.js"></script>
</body>

</html>