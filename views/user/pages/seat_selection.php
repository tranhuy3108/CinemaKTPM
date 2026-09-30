<?php
// Kiểm tra đăng nhập trước khi đặt vé
if (!isset($_SESSION['user'])) {
    $redirect_url = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /public/index.php?action=login&redirect=" . $redirect_url);
    exit;
}

$show_id = isset($_GET['show_id']) ? (int)$_GET['show_id'] : 0;

if ($show_id <= 0) {
    echo '<div style="text-align:center;padding:60px;color:#e50914;">Không tìm thấy thông tin suất chiếu.</div>';
    return;
}

// Lấy thông tin suất chiếu
$showData = null;
$sqlShow = "SELECT s.show_id, s.show_date, s.start_time, s.end_time, s.base_price,
                   m.title AS movie_title,
                   h.name AS hall_name,
                   c.name AS cinema_name
            FROM shows s
            INNER JOIN movies m ON s.movie_id = m.movie_id
            INNER JOIN halls h ON s.hall_id = h.hall_id
            INNER JOIN cinemas c ON h.cinema_id = c.cinema_id
            WHERE s.show_id = ?
            LIMIT 1";
$stmtShow = $conn->prepare($sqlShow);
if ($stmtShow) {
    $stmtShow->bind_param('i', $show_id);
    $stmtShow->execute();
    $showData = $stmtShow->get_result()->fetch_assoc();
}

if (!$showData) {
    echo '<div style="text-align:center;padding:60px;color:#e50914;">Suất chiếu không tồn tại.</div>';
    return;
}

// Lấy tickets qua controller
$tickets = $showController->getTicketByShowId($show_id);

// Nhóm tickets theo hàng
$ticketsByRow = [];
foreach ($tickets as $ticket) {
    $row = $ticket['row_name'] ?? 'A';
    if (!isset($ticketsByRow[$row])) $ticketsByRow[$row] = [];
    $ticketsByRow[$row][] = $ticket;
}
ksort($ticketsByRow);

// Lấy seat types để render legend
$seatTypes = [];
$sqlTypes = "SELECT DISTINCT st.seat_type_id, st.type_name
             FROM seat_types st
             INNER JOIN seats s ON s.seat_type_id = st.seat_type_id
             INNER JOIN tickets t ON t.seat_id = s.seat_id
             WHERE t.show_id = ?
             ORDER BY st.seat_type_id";
$stmtTypes = $conn->prepare($sqlTypes);
if ($stmtTypes) {
    $stmtTypes->bind_param('i', $show_id);
    $stmtTypes->execute();
    $resTypes = $stmtTypes->get_result();
    while ($row = $resTypes->fetch_assoc()) $seatTypes[] = $row;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Chọn vé</title>
    <link rel="stylesheet" href="/Cinemax/public/assets/css/style.css">
    <link rel="stylesheet" href="/Cinemax/public/assets/css/seat-selection.css">
    <style>
        .seat.type-1 {
            background-color: #7d7d7d;
            border-color: #999;
            color: #fff;
        }

        .seat.type-2 {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            border: 1px solid #ffb300;
            color: #000;
            font-weight: bold;
        }

        .seat.sold {
            background: #b71c1c !important;
            border-color: #d32f2f !important;
            color: #ffcdd2 !important;
            cursor: not-allowed;
            opacity: 0.7;
            background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(0, 0, 0, 0.2) 5px, rgba(0, 0, 0, 0.2) 10px);
        }

        .seat.held {
            background: #0288d1 !important;
            border-color: #0277bd !important;
            cursor: not-allowed;
        }

        .seat.selected {
            background: #46d369 !important;
            border-color: #46d369 !important;
            color: #fff !important;
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(70, 211, 105, 0.5);
            z-index: 10;
        }

        .countdown-box {
            background: #e50914;
            color: #fff;
            padding: 12px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(229, 9, 20, 0.3);
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-content">
                <div class="logo"><span>Cinema</span></div>
                <a href="index.php" class="btn-back">Thoát</a>
            </div>
        </div>
    </nav>

    <main class="booking-container">
        <div class="container">
            <form id="bookingForm" action="index.php?page=food_selection" method="POST">
                <input type="hidden" name="action" value="reserve_seats">
                <input type="hidden" name="showtime_id" value="<?php echo $show_id; ?>">
                <div id="hiddenInputs"></div>

                <div class="booking-layout">
                    <div class="screen-section">
                        <div class="showtime-info">
                            <div class="info-item">
                                <span class="label">Phim:</span>
                                <span class="value"><?php echo htmlspecialchars($showData['movie_title']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Suất:</span>
                                <span class="value"><?php echo htmlspecialchars(date('d/m/Y', strtotime($showData['show_date'])) . ' ' . substr($showData['start_time'], 0, 5)); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Rạp:</span>
                                <span class="value"><?php echo htmlspecialchars($showData['cinema_name'] . ' - ' . $showData['hall_name']); ?></span>
                            </div>
                        </div>

                        <div class="screen-wrapper">
                            <div class="screen"><span>MÀN HÌNH</span></div>

                            <div class="seats-container" id="seatsContainer">
                                <div class="seat-grid" style="display:flex;flex-direction:column;gap:8px;align-items:center;">
                                    <?php if (empty($ticketsByRow)): ?>
                                        <div style="text-align:center;padding:40px;color:#888;">Chưa có vé nào trong suất chiếu này.</div>
                                    <?php else: ?>
                                        <?php foreach ($ticketsByRow as $rowName => $rowTickets): ?>
                                            <?php usort($rowTickets, fn($a, $b) => (int)$a['seat_number'] <=> (int)$b['seat_number']); ?>
                                            <div class="seat-row">
                                                <span class="row-label"><?php echo htmlspecialchars($rowName); ?></span>
                                                <?php foreach ($rowTickets as $ticket):
                                                    $ticketId   = $ticket['ticket_id'];
                                                    $seatNum    = $ticket['seat_number'];
                                                    $seatTypeId = $ticket['seat_type_id'];
                                                    $typeName   = $ticket['type_name'] ?? 'Ghế';
                                                    $price      = (float)$ticket['price'];
                                                    $status     = $ticket['status'] ?? 'available';
                                                    $statusClass = '';
                                                    if ($status === 'paid') {
                                                        $statusClass = 'sold';
                                                    } elseif ($status === 'held') {
                                                        $statusClass = 'held';
                                                    }

                                                    $clickable = $status === 'available';
                                                ?>
                                                    <div class="seat type-<?php echo $seatTypeId; ?> <?php echo $statusClass; ?>"
                                                        data-ticket-id="<?php echo $ticketId; ?>"
                                                        data-seat-name="<?php echo htmlspecialchars($rowName . $seatNum); ?>"
                                                        data-price="<?php echo $price; ?>"
                                                        data-status="<?php echo htmlspecialchars($status); ?>"
                                                        title="<?php echo htmlspecialchars("{$rowName}{$seatNum} - {$typeName} - " . number_format($price, 0, ',', '.') . " ₫"); ?>"
                                                        <?php if ($clickable): ?>onclick="toggleTicket(this)" <?php endif; ?>>
                                                        <?php echo $seatNum; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="seat-legend legend" id="seatLegend">
                                <?php foreach ($seatTypes as $st): ?>
                                    <div class="legend-item">
                                        <div class="seat-demo type-<?php echo $st['seat_type_id']; ?>"></div>
                                        <span><?php echo htmlspecialchars($st['type_name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="legend-item">
                                    <div class="seat-demo selected"></div><span>Đang chọn</span>
                                </div>
                                <div class="legend-item">
                                    <div class="seat-demo sold"></div><span>Đã bán</span>
                                </div>
                                <div class="legend-item">
                                    <div class="seat-demo held"></div><span>Đang giữ</span>
                                </div>
                                <div class="legend-item" style="margin-left:15px;border-left:1px solid #444;padding-left:15px;">👉 Click để chọn vé</div>
                            </div>
                        </div>
                    </div>

                    <div class="booking-sidebar">
                        <div class="booking-summary">
                            <h3>Thông tin đặt vé</h3>
                            <div class="countdown-box">Thời gian giữ vé: <span id="countdown">10:00</span></div>
                            <div class="summary-section">
                                <h4>Vé đã chọn</h4>
                                <div id="selectedSeats" class="selected-seats-list">
                                    <p class="empty-message">Chưa chọn vé</p>
                                </div>
                            </div>
                            <div class="summary-section">
                                <div class="price-row"><span>Tạm tính</span><span id="totalPrice">0 ₫</span></div>
                            </div>
                            <button class="btn-continue" id="btnContinue" type="submit" disabled style="opacity:0.5;cursor:not-allowed;">
                                Tiếp tục chọn đồ ăn
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script src="/Cinemax/public/assets/js/user-seat-selection.js"></script>
    <script>
        function startCountdown(duration) {
            const display = document.getElementById('countdown');
            if (!display) return;
            let timer = duration;
            const interval = setInterval(function() {
                const minutes = String(parseInt(timer / 60)).padStart(2, '0');
                const seconds = String(parseInt(timer % 60)).padStart(2, '0');
                display.textContent = minutes + ':' + seconds;
                if (--timer < 0) {
                    clearInterval(interval);
                    display.textContent = '00:00';
                    alert('Đã hết thời gian giữ vé! Trang sẽ tải lại để cập nhật trạng thái.');
                    window.location.reload();
                }
            }, 1000);
        }
        document.addEventListener('DOMContentLoaded', function() {
            startCountdown(600);
        });
    </script>
</body>

</html>