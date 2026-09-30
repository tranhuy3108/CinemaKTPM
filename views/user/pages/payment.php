<?php

require_once __DIR__ . '/../../../config/dbConfig.php';
require_once __DIR__ . '/../../../services/AuthMiddleware.php';

// =========================
// SESSION
// =========================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// =========================
// KIỂM TRA ĐĂNG NHẬP
// =========================

$authUser = AuthMiddleware::getAuthUser();

if (!$authUser) {
    header("Location: /Cinemax/public/index.php?action=login");
    exit;
}

$userId = (int)($authUser['user_id'] ?? 0);

if ($userId <= 0) {
    header("Location: /Cinemax/public/index.php?action=login");
    exit;
}


// =========================
// DATABASE
// =========================

$conn = getDbConnection();


// =========================
// NHẬN SHOWTIME
// =========================

$showtimeId = isset($_POST['showtime_id'])
        ? (int)$_POST['showtime_id']
        : 0;


// =========================
// NHẬN TICKET IDS
// =========================

$ticketIds = [];

if (
        isset($_POST['ticket_ids']) &&
        is_array($_POST['ticket_ids'])
) {
    foreach ($_POST['ticket_ids'] as $ticketId) {

        $ticketId = (int)$ticketId;

        if ($ticketId > 0) {
            $ticketIds[] = $ticketId;
        }
    }

    $ticketIds = array_values(
            array_unique($ticketIds)
    );
}


// =========================
// NHẬN COMBO
// =========================
//
// Chỉ nhận:
// combo_id
// quantity
//
// KHÔNG tin price từ trình duyệt.
//

$selectedCombos = [];

if (
        isset($_POST['combos']) &&
        is_array($_POST['combos'])
) {
    foreach ($_POST['combos'] as $combo) {

        if (!is_array($combo)) {
            continue;
        }

        $comboId = isset($combo['combo_id'])
                ? (int)$combo['combo_id']
                : 0;

        $quantity = isset($combo['quantity'])
                ? (int)$combo['quantity']
                : 0;

        if ($comboId <= 0 || $quantity <= 0) {
            continue;
        }

        // Không cho vượt quá 20 combo / loại
        $quantity = min($quantity, 20);

        $selectedCombos[] = [
                'combo_id' => $comboId,
                'quantity' => $quantity
        ];
    }
}


// =========================
// KIỂM TRA DỮ LIỆU
// =========================

if ($showtimeId <= 0) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Không tìm thấy thông tin suất chiếu.
        </div>
    ';

    return;
}


if (empty($ticketIds)) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Chưa có ghế được chọn.
        </div>
    ';

    return;
}


// =========================
// LẤY THÔNG TIN SUẤT CHIẾU
// =========================

$showData = null;

$sqlShow = "
    SELECT
        s.show_id,
        s.movie_id,
        s.hall_id,
        s.show_date,
        s.start_time,
        s.end_time,
        s.base_price,
        s.status,

        m.title AS movie_title,

        h.name AS hall_name,

        c.name AS cinema_name

    FROM shows s

    INNER JOIN movies m
        ON s.movie_id = m.movie_id

    INNER JOIN halls h
        ON s.hall_id = h.hall_id

    INNER JOIN cinemas c
        ON h.cinema_id = c.cinema_id

    WHERE s.show_id = ?

    LIMIT 1
";

$stmtShow = $conn->prepare($sqlShow);

if (!$stmtShow) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Không thể tải thông tin suất chiếu.
        </div>
    ';

    return;
}

$stmtShow->bind_param(
        'i',
        $showtimeId
);

$stmtShow->execute();

$resultShow = $stmtShow->get_result();

$showData = $resultShow->fetch_assoc();

$stmtShow->close();


if (!$showData) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Suất chiếu không tồn tại.
        </div>
    ';

    return;
}


// =========================
// KIỂM TRA STATUS SUẤT CHIẾU
// =========================

if ((int)$showData['status'] !== 1) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Suất chiếu này hiện không còn hoạt động.
        </div>
    ';

    return;
}


// =========================
// LẤY TICKET TỪ DATABASE
// =========================

$placeholders = implode(
        ',',
        array_fill(0, count($ticketIds), '?')
);

$sqlTickets = "
    SELECT
        t.ticket_id,
        t.show_id,
        t.seat_id,
        t.price,
        t.status,

        s.row_name,
        s.seat_number,

        st.type_name

    FROM tickets t

    INNER JOIN seats s
        ON t.seat_id = s.seat_id

    LEFT JOIN seat_types st
        ON s.seat_type_id = st.seat_type_id

    WHERE t.show_id = ?

      AND t.ticket_id IN ($placeholders)

    ORDER BY
        s.row_name ASC,
        s.seat_number ASC
";

$stmtTickets = $conn->prepare($sqlTickets);

if (!$stmtTickets) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Không thể tải thông tin ghế.
        </div>
    ';

    return;
}


$types = 'i' . str_repeat(
                'i',
                count($ticketIds)
        );

$params = [
        $showtimeId
];

foreach ($ticketIds as $ticketId) {
    $params[] = $ticketId;
}

$stmtTickets->bind_param(
        $types,
        ...$params
);

$stmtTickets->execute();

$resultTickets = $stmtTickets->get_result();

$tickets = [];

while ($ticket = $resultTickets->fetch_assoc()) {
    $tickets[] = $ticket;
}

$stmtTickets->close();


// =========================
// KIỂM TRA ĐỦ TICKET
// =========================

if (count($tickets) !== count($ticketIds)) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Một hoặc nhiều ghế không thuộc suất chiếu này.
            <br>
            Vui lòng quay lại chọn ghế.
        </div>
    ';

    return;
}


// =========================
// TÍNH TIỀN VÉ TỪ DATABASE
// =========================

$seatTotal = 0;

$seatNames = [];

$seatError = false;

foreach ($tickets as $ticket) {

    $price = (float)$ticket['price'];

    $seatTotal += $price;

    $seatNames[] =
            ($ticket['row_name'] ?? '') .
            ($ticket['seat_number'] ?? '');

    if (
            ($ticket['status'] ?? '') !== 'available'
    ) {
        $seatError = true;
    }
}


// =========================
// GHẾ KHÔNG CÒN AVAILABLE
// =========================

if ($seatError) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Một hoặc nhiều ghế vừa được người khác chọn.
            <br>
            Vui lòng quay lại và chọn ghế khác.
        </div>
    ';

    return;
}


// =========================
// LẤY COMBO TỪ DATABASE
// =========================

$comboDetails = [];

$foodTotal = 0;


if (!empty($selectedCombos)) {

    $comboIds = [];

    foreach ($selectedCombos as $combo) {
        $comboIds[] = (int)$combo['combo_id'];
    }

    $comboIds = array_values(
            array_unique($comboIds)
    );


    if (!empty($comboIds)) {

        $comboPlaceholders = implode(
                ',',
                array_fill(
                        0,
                        count($comboIds),
                        '?'
                )
        );


        $sqlCombos = "
            SELECT
                combo_id,
                name,
                description,
                image_url,
                price,
                status

            FROM combos

            WHERE combo_id IN ($comboPlaceholders)

              AND status = 1
        ";


        $stmtCombos = $conn->prepare(
                $sqlCombos
        );


        if (!$stmtCombos) {

            echo '
                <div style="
                    text-align:center;
                    padding:60px;
                    color:#e50914;
                ">
                    Không thể tải thông tin đồ ăn.
                </div>
            ';

            return;
        }


        $comboTypes = str_repeat(
                'i',
                count($comboIds)
        );


        $stmtCombos->bind_param(
                $comboTypes,
                ...$comboIds
        );


        $stmtCombos->execute();

        $resultCombos =
                $stmtCombos->get_result();


        $comboMap = [];


        while (
        $combo =
                $resultCombos->fetch_assoc()
        ) {

            $comboMap[
            (int)$combo['combo_id']
            ] = $combo;
        }


        $stmtCombos->close();


        foreach (
                $selectedCombos
                as $selectedCombo
        ) {

            $comboId =
                    (int)$selectedCombo['combo_id'];

            $quantity =
                    (int)$selectedCombo['quantity'];


            if (
                    !isset(
                            $comboMap[$comboId]
                    )
            ) {

                echo '
                    <div style="
                        text-align:center;
                        padding:60px;
                        color:#e50914;
                    ">
                        Một hoặc nhiều combo
                        không còn khả dụng.
                    </div>
                ';

                return;
            }


            $combo =
                    $comboMap[$comboId];


            // GIÁ LẤY TỪ DATABASE
            $price =
                    (float)$combo['price'];


            $subtotal =
                    $price * $quantity;


            $foodTotal += $subtotal;


            $comboDetails[] = [

                    'combo_id' =>
                            $comboId,

                    'name' =>
                            $combo['name'],

                    'price' =>
                            $price,

                    'quantity' =>
                            $quantity,

                    'subtotal' =>
                            $subtotal
            ];
        }
    }
}


// =========================
// TỔNG TIỀN
// =========================

$grandTotal =
        $seatTotal +
        $foodTotal;

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
    >

    <title>Thanh toán</title>

    <link
            rel="stylesheet"
            href="/Cinemax/public/assets/css/style.css"
    >

    <link
            rel="stylesheet"
            href="/Cinemax/public/assets/css/seat-selection.css"
    >

    <link
            rel="stylesheet"
            href="/Cinemax/public/assets/css/food-selection.css"
    >

    <link
            rel="stylesheet"
            href="/Cinemax/public/assets/css/payment.css"
    >

</head>


<body>


<nav class="navbar">

    <div class="container">

        <div class="nav-content">


            <div class="logo">
                <span>Cinema</span>
            </div>


            <div class="booking-steps">

                <div class="step completed">

                    <span class="step-number">
                        1
                    </span>

                    <span class="step-label">
                        Suất chiếu
                    </span>

                </div>


                <div class="step completed">

                    <span class="step-number">
                        2
                    </span>

                    <span class="step-label">
                        Ghế
                    </span>

                </div>


                <div class="step completed">

                    <span class="step-number">
                        3
                    </span>

                    <span class="step-label">
                        Đồ ăn
                    </span>

                </div>


                <div class="step active">

                    <span class="step-number">
                        4
                    </span>

                    <span class="step-label">
                        Thanh toán
                    </span>

                </div>

            </div>


            <a
                    href="index.php?page=home"
                    class="btn-back"
                    onclick="
                    return confirm('Hủy đơn hàng này?');
                "
            >
                Hủy đơn
            </a>


        </div>

    </div>

</nav>



<div class="booking-container">

    <div class="container">

        <div class="booking-layout">


            <!-- =========================
                 PAYMENT METHOD
            ========================== -->

            <div class="payment-section">

                <h2>
                    Chọn phương thức thanh toán
                </h2>


                <form
                        id="paymentForm"
                        action="index.php?page=payment&action=confirm_payment"
                        method="POST"
                >


                    <input
                            type="hidden"
                            name="action"
                            value="confirm_payment"
                    >


                    <input
                            type="hidden"
                            name="showtime_id"
                            value="<?= $showtimeId ?>"
                    >


                    <?php foreach ($ticketIds as $ticketId): ?>

                        <input
                                type="hidden"
                                name="ticket_ids[]"
                                value="<?= $ticketId ?>"
                        >

                    <?php endforeach; ?>


                    <?php foreach (
                            $selectedCombos
                            as $index => $combo
                    ): ?>

                        <input
                                type="hidden"
                                name="combos[<?= $index ?>][combo_id]"
                                value="<?= (int)$combo['combo_id'] ?>"
                        >

                        <input
                                type="hidden"
                                name="combos[<?= $index ?>][quantity]"
                                value="<?= (int)$combo['quantity'] ?>"
                        >

                    <?php endforeach; ?>


                    <div class="method-list">


                        <!-- ATM -->

                        <label class="method-item active">

                            <input
                                    type="radio"
                                    name="payment_method"
                                    value="ATM"
                                    checked
                            >

                            <img
                                    src="https://cdn-icons-png.flaticon.com/512/2534/2534204.png"
                                    class="method-icon"
                                    alt="ATM"
                            >

                            <div class="method-info">

                                <h4>
                                    Thẻ ATM nội địa /
                                    Internet Banking
                                </h4>

                                <p>
                                    Hỗ trợ tất cả các ngân hàng
                                    tại Việt Nam
                                </p>

                            </div>

                            <div class="radio-custom"></div>

                        </label>


                        <!-- MOMO -->

                        <label class="method-item">

                            <input
                                    type="radio"
                                    name="payment_method"
                                    value="MOMO"
                            >

                            <img
                                    src="https://upload.wikimedia.org/wikipedia/vi/f/fe/MoMo_Logo.png"
                                    class="method-icon"
                                    alt="MoMo"
                            >

                            <div class="method-info">

                                <h4>
                                    Ví điện tử MoMo
                                </h4>

                                <p>
                                    Quét mã QR để thanh toán
                                    nhanh chóng
                                </p>

                            </div>

                            <div class="radio-custom"></div>

                        </label>


                        <!-- VISA -->

                        <label class="method-item">

                            <input
                                    type="radio"
                                    name="payment_method"
                                    value="VISA"
                            >

                            <img
                                    src="https://cdn-icons-png.flaticon.com/512/349/349221.png"
                                    class="method-icon"
                                    alt="Visa"
                            >

                            <div class="method-info">

                                <h4>
                                    Thẻ Quốc tế
                                    (Visa / Master / JCB)
                                </h4>

                                <p>
                                    Thanh toán an toàn,
                                    bảo mật
                                </p>

                            </div>

                            <div class="radio-custom"></div>

                        </label>


                    </div>


                    <div class="note-box">

                        ⚠️

                        <strong>Lưu ý:</strong>

                        Sau khi bấm
                        "Thanh toán",
                        hệ thống sẽ kiểm tra lại
                        tình trạng ghế và tính lại
                        toàn bộ số tiền từ
                        cơ sở dữ liệu trước khi
                        tạo đơn hàng.

                    </div>


                </form>

            </div>



            <!-- =========================
                 BOOKING SUMMARY
            ========================== -->

            <div class="booking-sidebar">

                <div class="booking-summary">


                    <h3 style="
                        border-bottom:1px solid #444;
                        padding-bottom:15px;
                        margin-bottom:15px;
                    ">

                        Thông tin đặt vé

                    </h3>


                    <!-- MOVIE -->

                    <div class="summary-block">

                        <h4 style="
                            color:#fff;
                            margin-bottom:10px;
                            font-size:18px;
                        ">

                            <?= htmlspecialchars(
                                    (string)(
                                            $showData['movie_title']
                                            ?? ''
                                    )
                            ) ?>

                        </h4>


                        <div class="info-row">

                            <span>
                                Rạp:
                            </span>

                            <strong>

                                <?= htmlspecialchars(
                                        (string)(
                                                $showData[
                                                'cinema_name'
                                                ]
                                                ?? 'Đang cập nhật'
                                        )
                                ) ?>

                            </strong>

                        </div>


                        <div class="info-row">

                            <span>
                                Suất:
                            </span>

                            <strong>

                                <?= htmlspecialchars(
                                        date(
                                                'd/m/Y',
                                                strtotime(
                                                        $showData[
                                                        'show_date'
                                                        ]
                                                )
                                        )
                                        . ' '
                                        .
                                        substr(
                                                $showData[
                                                'start_time'
                                                ],
                                                0,
                                                5
                                        )
                                ) ?>

                            </strong>

                        </div>


                        <div class="info-row">

                            <span>
                                Phòng:
                            </span>

                            <strong>

                                <?= htmlspecialchars(
                                        (string)(
                                                $showData[
                                                'hall_name'
                                                ]
                                                ?? 'Đang cập nhật'
                                        )
                                ) ?>

                            </strong>

                        </div>


                        <div class="info-row">

                            <span>
                                Ghế:
                            </span>

                            <strong
                                    style="
                                    color:
                                    var(--primary-color);
                                "
                            >

                                <?= htmlspecialchars(
                                        implode(
                                                ', ',
                                                $seatNames
                                        )
                                ) ?>

                            </strong>

                        </div>

                    </div>



                    <!-- COMBO -->

                    <?php if (
                            !empty($comboDetails)
                    ): ?>

                        <div style="
                            margin-top:20px;
                        ">

                            <h4 style="
                                color:#fff;
                                margin-bottom:10px;
                            ">

                                Đồ ăn / Combo

                            </h4>


                            <?php foreach (
                                    $comboDetails
                                    as $combo
                            ): ?>

                                <div
                                        class="info-row"
                                        style="
                                        align-items:
                                        flex-start;
                                    "
                                >

                                    <span>

                                        <?= htmlspecialchars(
                                                $combo['name']
                                        ) ?>

                                        ×

                                        <?= $combo[
                                        'quantity'
                                        ] ?>

                                    </span>


                                    <span>

                                        <?= number_format(
                                                $combo[
                                                'subtotal'
                                                ],
                                                0,
                                                ',',
                                                '.'
                                        ) ?>

                                        ₫

                                    </span>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>



                    <!-- TOTAL -->

                    <div style="
                        margin-top:20px;
                    ">


                        <div class="info-row">

                            <span>
                                Tổng vé:
                            </span>

                            <span>

                                <?= number_format(
                                        $seatTotal,
                                        0,
                                        ',',
                                        '.'
                                ) ?>

                                ₫

                            </span>

                        </div>


                        <div class="info-row">

                            <span>
                                Tổng đồ ăn:
                            </span>

                            <span>

                                <?= number_format(
                                        $foodTotal,
                                        0,
                                        ',',
                                        '.'
                                ) ?>

                                ₫

                            </span>

                        </div>


                        <div style="
                            border-top:
                            1px dashed #555;

                            margin:
                            15px 0;
                        "></div>


                        <div
                                class="info-row"
                                style="
                                align-items:center;
                            "
                        >

                            <span style="
                                font-size:16px;
                                font-weight:bold;
                                color:#fff;
                            ">

                                Tổng thanh toán:

                            </span>


                            <span class="final-total">

                                <?= number_format(
                                        $grandTotal,
                                        0,
                                        ',',
                                        '.'
                                ) ?>

                                ₫

                            </span>

                        </div>

                    </div>



                    <button
                            type="button"
                            onclick="submitPayment()"
                            class="btn-continue"
                    >

                        THANH TOÁN NGAY

                    </button>


                </div>

            </div>


        </div>

    </div>

</div>



<script>

    // =========================
    // SUBMIT PAYMENT
    // =========================

    function submitPayment() {

        const confirmed = confirm(
            'Xác nhận thanh toán đơn hàng này?'
        );

        if (!confirmed) {
            return;
        }

        const form =
            document.getElementById(
                'paymentForm'
            );

        if (form) {
            form.submit();
        }

    }


    // =========================
    // PAYMENT METHOD
    // =========================

    const methods =
        document.querySelectorAll(
            '.method-item'
        );


    methods.forEach(function(method) {

        method.addEventListener(
            'click',
            function() {

                methods.forEach(
                    function(item) {

                        item.classList.remove(
                            'active'
                        );

                    }
                );


                method.classList.add(
                    'active'
                );


                const radio =
                    method.querySelector(
                        'input[type="radio"]'
                    );


                if (radio) {
                    radio.checked = true;
                }

            }
        );

    });

</script>


</body>

</html>