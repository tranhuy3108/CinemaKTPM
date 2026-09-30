<?php

require_once __DIR__ . '/../../../config/dbConfig.php';
require_once __DIR__ . '/../../../services/AuthMiddleware.php';
require_once __DIR__ . '/../../../models/Show.php';
require_once __DIR__ . '/../../../models/Ticket.php';
require_once __DIR__ . '/../../../services/TicketService.php';
require_once __DIR__ . '/../../../services/ShowService.php';
require_once __DIR__ . '/../../../controllers/ShowController.php';


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

    $redirectUrl = urlencode(
            $_SERVER['REQUEST_URI'] ?? '/Cinemax/public/index.php'
    );

    header(
            'Location: /Cinemax/public/index.php?action=login&redirect=' .
            $redirectUrl
    );

    exit;
}


// =========================
// DATABASE
// =========================

$conn = getDbConnection();


// =========================
// KHỞI TẠO MODEL / SERVICE
// =========================

$ticketModel = new Ticket($conn);

$ticketService = new TicketService(
        $ticketModel
);

$showModel = new Show($conn);

$showService = new ShowService(
        $showModel,
        $ticketService
);

$showController = new ShowController(
        $showService
);


// =========================
// SHOW ID
// =========================

$show_id = isset($_GET['show_id'])
        ? (int)$_GET['show_id']
        : 0;


if ($show_id <= 0) {

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
        $show_id
);

$stmtShow->execute();

$resultShow =
        $stmtShow->get_result();

$showData =
        $resultShow->fetch_assoc();

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
// KIỂM TRA STATUS
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
// KIỂM TRA SUẤT CHIẾU CHƯA BẮT ĐẦU
// =========================

date_default_timezone_set(
        'Asia/Ho_Chi_Minh'
);

$showDateTime = new DateTime(
        $showData['show_date'] .
        ' ' .
        $showData['start_time']
);

$now = new DateTime();

if ($showDateTime <= $now) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Suất chiếu này đã bắt đầu hoặc đã kết thúc.
        </div>
    ';

    return;
}


// =========================
// LẤY TICKETS
// =========================

try {

    $tickets =
            $showController->getTicketByShowId(
                    $show_id
            );

} catch (Throwable $e) {

    echo '
        <div style="
            text-align:center;
            padding:60px;
            color:#e50914;
        ">
            Không thể tải sơ đồ ghế.
        </div>
    ';

    return;
}


if (!is_array($tickets)) {
    $tickets = [];
}


// =========================
// NHÓM TICKET THEO HÀNG
// =========================

$ticketsByRow = [];


foreach ($tickets as $ticket) {

    $row =
            $ticket['row_name'] ?? 'A';


    if (
            !isset(
                    $ticketsByRow[$row]
            )
    ) {

        $ticketsByRow[$row] = [];

    }


    $ticketsByRow[$row][] =
            $ticket;
}


ksort($ticketsByRow);


// =========================
// LẤY LOẠI GHẾ
// =========================

$seatTypes = [];


$sqlTypes = "
    SELECT DISTINCT
        st.seat_type_id,
        st.type_name

    FROM seat_types st

    INNER JOIN seats s
        ON s.seat_type_id =
           st.seat_type_id

    INNER JOIN tickets t
        ON t.seat_id =
           s.seat_id

    WHERE t.show_id = ?

    ORDER BY
        st.seat_type_id
";


$stmtTypes =
        $conn->prepare($sqlTypes);


if ($stmtTypes) {

    $stmtTypes->bind_param(
            'i',
            $show_id
    );

    $stmtTypes->execute();

    $resultTypes =
            $stmtTypes->get_result();


    while (
    $row =
            $resultTypes->fetch_assoc()
    ) {

        $seatTypes[] =
                $row;
    }


    $stmtTypes->close();
}

?>

<!DOCTYPE html>

<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
    >

    <title>Chọn vé</title>

    <link
            rel="stylesheet"
            href="/Cinemax/public/assets/css/style.css"
    >

    <link
            rel="stylesheet"
            href="/Cinemax/public/assets/css/seat-selection.css"
    >

    <style>

        .seat.type-1 {

            background-color: #7d7d7d;

            border-color: #999;

            color: #fff;
        }


        .seat.type-2 {

            background:
                    linear-gradient(
                            135deg,
                            #ffc107 0%,
                            #ff9800 100%
                    );

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

            background-image:
                    repeating-linear-gradient(
                            45deg,
                            transparent,
                            transparent 5px,
                            rgba(0, 0, 0, 0.2) 5px,
                            rgba(0, 0, 0, 0.2) 10px
                    );
        }


        .seat.held {

            background: #0288d1 !important;

            border-color: #0277bd !important;

            color: #fff !important;

            cursor: not-allowed;
        }


        .seat.booked {

            background: #b71c1c !important;

            border-color: #d32f2f !important;

            color: #ffcdd2 !important;

            cursor: not-allowed;

            opacity: 0.7;
        }


        .seat.selected {

            background: #46d369 !important;

            border-color: #46d369 !important;

            color: #fff !important;

            transform: scale(1.1);

            box-shadow:
                    0 0 10px
                    rgba(70, 211, 105, 0.5);

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

            box-shadow:
                    0 4px 10px
                    rgba(229, 9, 20, 0.3);
        }


        .expired-message {

            display: none;

            background: #b71c1c;

            color: #fff;

            padding: 12px;

            border-radius: 8px;

            margin-bottom: 15px;

            text-align: center;

            font-weight: bold;
        }

    </style>

</head>


<body>


<nav class="navbar">

    <div class="container">

        <div class="nav-content">

            <div class="logo">

                <span>Cinema</span>

            </div>


            <a
                    href="index.php?page=home"
                    class="btn-back"
                    onclick="
                    return confirm('Thoát khỏi quá trình đặt vé?');
                "
            >
                Thoát
            </a>

        </div>

    </div>

</nav>



<main class="booking-container">

    <div class="container">


        <form
                id="bookingForm"
                action="index.php?page=food_selection"
                method="POST"
        >


            <input
                    type="hidden"
                    name="action"
                    value="reserve_seats"
            >


            <input
                    type="hidden"
                    name="showtime_id"
                    value="<?= $show_id ?>"
            >


            <div id="hiddenInputs"></div>


            <div class="booking-layout">


                <!-- =========================
                     SEAT MAP
                ========================== -->

                <div class="screen-section">


                    <div class="showtime-info">


                        <div class="info-item">

                            <span class="label">
                                Phim:
                            </span>

                            <span class="value">

                                <?= htmlspecialchars(
                                        (string)
                                        $showData[
                                        'movie_title'
                                        ]
                                ) ?>

                            </span>

                        </div>


                        <div class="info-item">

                            <span class="label">
                                Suất:
                            </span>

                            <span class="value">

                                <?= htmlspecialchars(
                                        date(
                                                'd/m/Y',
                                                strtotime(
                                                        $showData[
                                                        'show_date'
                                                        ]
                                                )
                                        )
                                        .
                                        ' '
                                        .
                                        substr(
                                                $showData[
                                                'start_time'
                                                ],
                                                0,
                                                5
                                        )
                                ) ?>

                            </span>

                        </div>


                        <div class="info-item">

                            <span class="label">
                                Rạp:
                            </span>

                            <span class="value">

                                <?= htmlspecialchars(
                                        $showData[
                                        'cinema_name'
                                        ]
                                        .
                                        ' - '
                                        .
                                        $showData[
                                        'hall_name'
                                        ]
                                ) ?>

                            </span>

                        </div>


                    </div>



                    <div class="screen-wrapper">


                        <div class="screen">

                            <span>
                                MÀN HÌNH
                            </span>

                        </div>



                        <div
                                class="seats-container"
                                id="seatsContainer"
                        >


                            <div
                                    class="seat-grid"
                                    style="
                                    display:flex;
                                    flex-direction:column;
                                    gap:8px;
                                    align-items:center;
                                "
                            >


                                <?php if (
                                        empty($ticketsByRow)
                                ): ?>


                                    <div
                                            style="
                                            text-align:center;
                                            padding:40px;
                                            color:#888;
                                        "
                                    >

                                        Chưa có vé nào
                                        trong suất chiếu này.

                                    </div>


                                <?php else: ?>


                                    <?php foreach (
                                            $ticketsByRow
                                            as $rowName =>
                                            $rowTickets
                                    ): ?>


                                        <?php

                                        usort(
                                                $rowTickets,
                                                function (
                                                        $a,
                                                        $b
                                                ) {

                                                    return
                                                            (int)
                                                            $a[
                                                            'seat_number'
                                                            ]
                                                            <=>
                                                            (int)
                                                            $b[
                                                            'seat_number'
                                                            ];
                                                }
                                        );

                                        ?>


                                        <div class="seat-row">


                                            <span
                                                    class="row-label"
                                            >

                                                <?= htmlspecialchars(
                                                        $rowName
                                                ) ?>

                                            </span>


                                            <?php foreach (
                                                    $rowTickets
                                                    as $ticket
                                            ): ?>


                                                <?php

                                                $ticketId =
                                                        (int)
                                                        (
                                                                $ticket[
                                                                'ticket_id'
                                                                ]
                                                                ?? 0
                                                        );


                                                $seatNum =
                                                        $ticket[
                                                        'seat_number'
                                                        ]
                                                        ?? '';


                                                $seatTypeId =
                                                        (int)
                                                        (
                                                                $ticket[
                                                                'seat_type_id'
                                                                ]
                                                                ?? 0
                                                        );


                                                $typeName =
                                                        $ticket[
                                                        'type_name'
                                                        ]
                                                        ??
                                                        'Ghế';


                                                $price =
                                                        (float)
                                                        (
                                                                $ticket[
                                                                'price'
                                                                ]
                                                                ?? 0
                                                        );


                                                $status =
                                                        strtolower(
                                                                trim(
                                                                        (string)
                                                                        (
                                                                                $ticket[
                                                                                'status'
                                                                                ]
                                                                                ??
                                                                                'available'
                                                                        )
                                                                )
                                                        );


                                                $statusClass =
                                                        '';


                                                if (
                                                        $status ===
                                                        'paid'
                                                ) {

                                                    $statusClass =
                                                            'sold';

                                                } elseif (
                                                        $status ===
                                                        'booked'
                                                ) {

                                                    $statusClass =
                                                            'booked';

                                                } elseif (
                                                        $status ===
                                                        'held'
                                                ) {

                                                    $statusClass =
                                                            'held';

                                                } elseif (
                                                        $status ===
                                                        'used'
                                                ) {

                                                    $statusClass =
                                                            'sold';

                                                } elseif (
                                                        $status ===
                                                        'cancelled'
                                                ) {

                                                    $statusClass =
                                                            'sold';
                                                }


                                                $clickable =
                                                        (
                                                                $status ===
                                                                'available'
                                                        );

                                                ?>


                                                <div
                                                        class="
                                                        seat
                                                        type-<?=
                                                        $seatTypeId
                                                        ?>
                                                        <?=
                                                        $statusClass
                                                        ?>
                                                    "

                                                        data-ticket-id="<?=
                                                        $ticketId
                                                        ?>"

                                                        data-seat-name="<?=
                                                        htmlspecialchars(
                                                                $rowName .
                                                                $seatNum,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                        )
                                                        ?>"

                                                        data-price="<?=
                                                        htmlspecialchars(
                                                                (string)$price,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                        )
                                                        ?>"

                                                        data-status="<?=
                                                        htmlspecialchars(
                                                                $status,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                        )
                                                        ?>"

                                                        title="<?=
                                                        htmlspecialchars(
                                                                $rowName .
                                                                $seatNum .
                                                                ' - ' .
                                                                $typeName .
                                                                ' - ' .
                                                                number_format(
                                                                        $price,
                                                                        0,
                                                                        ',',
                                                                        '.'
                                                                ) .
                                                                ' ₫',
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                        )
                                                        ?>"

                                                        <?php if (
                                                                $clickable
                                                        ): ?>

                                                            onclick="
                                                            toggleTicket(this)
                                                        "

                                                        <?php endif; ?>

                                                >

                                                    <?= htmlspecialchars(
                                                            (string)
                                                            $seatNum
                                                    ) ?>

                                                </div>


                                            <?php endforeach; ?>


                                        </div>


                                    <?php endforeach; ?>


                                <?php endif; ?>


                            </div>


                        </div>



                        <!-- LEGEND -->

                        <div
                                class="seat-legend legend"
                                id="seatLegend"
                        >


                            <?php foreach (
                                    $seatTypes
                                    as $st
                            ): ?>


                                <div class="legend-item">

                                    <div
                                            class="
                                            seat-demo
                                            type-<?=
                                            (int)
                                            $st[
                                            'seat_type_id'
                                            ]
                                            ?>
                                    "></div>

                                    <span>

                                        <?= htmlspecialchars(
                                                $st[
                                                'type_name'
                                                ]
                                        ) ?>

                                    </span>

                                </div>


                            <?php endforeach; ?>


                            <div class="legend-item">

                                <div
                                        class="
                                        seat-demo
                                        selected
                                    "
                                ></div>

                                <span>
                                    Đang chọn
                                </span>

                            </div>


                            <div class="legend-item">

                                <div
                                        class="
                                        seat-demo
                                        sold
                                    "
                                ></div>

                                <span>
                                    Đã bán
                                </span>

                            </div>


                            <div class="legend-item">

                                <div
                                        class="
                                        seat-demo
                                        booked
                                    "
                                ></div>

                                <span>
                                    Đang đặt
                                </span>

                            </div>


                            <div class="legend-item">

                                <div
                                        class="
                                        seat-demo
                                        held
                                    "
                                ></div>

                                <span>
                                    Đang giữ
                                </span>

                            </div>


                            <div
                                    class="legend-item"
                                    style="
                                    margin-left:15px;
                                    border-left:1px solid #444;
                                    padding-left:15px;
                                "
                            >

                                👉 Click để chọn vé

                            </div>


                        </div>


                    </div>


                </div>



                <!-- =========================
                     SIDEBAR
                ========================== -->

                <div class="booking-sidebar">


                    <div class="booking-summary">


                        <h3>
                            Thông tin đặt vé
                        </h3>


                        <div class="countdown-box">

                            Thời gian giữ vé:

                            <span id="countdown">
                                10:00
                            </span>

                        </div>


                        <div
                                id="expiredMessage"
                                class="expired-message"
                        >

                            Thời gian chọn vé đã hết.
                            Vui lòng tải lại trang.

                        </div>


                        <div class="summary-section">


                            <h4>
                                Vé đã chọn
                            </h4>


                            <div
                                    id="selectedSeats"
                                    class="selected-seats-list"
                            >

                                <p class="empty-message">
                                    Chưa chọn vé
                                </p>

                            </div>


                        </div>



                        <div class="summary-section">


                            <div class="price-row">

                                <span>
                                    Tạm tính
                                </span>

                                <span id="totalPrice">
                                    0 ₫
                                </span>

                            </div>


                        </div>



                        <button
                                class="btn-continue"
                                id="btnContinue"
                                type="submit"
                                disabled
                                style="
                                opacity:0.5;
                                cursor:not-allowed;
                            "
                        >

                            Tiếp tục chọn đồ ăn

                        </button>


                    </div>


                </div>


            </div>


        </form>


    </div>

</main>



<script
        src="/Cinemax/public/assets/js/user-seat-selection.js"
></script>


<script>

    let countdownInterval = null;


    // =========================
    // COUNTDOWN
    // =========================

    function startCountdown(duration) {

        const display =
            document.getElementById(
                'countdown'
            );

        const expiredMessage =
            document.getElementById(
                'expiredMessage'
            );

        const btnContinue =
            document.getElementById(
                'btnContinue'
            );


        if (!display) {
            return;
        }


        let timer =
            Number(duration);


        function updateDisplay() {

            const minutes =
                Math.floor(
                    timer / 60
                );


            const seconds =
                timer % 60;


            display.textContent =
                String(minutes)
                    .padStart(2, '0')
                +
                ':'
                +
                String(seconds)
                    .padStart(2, '0');


            if (timer <= 0) {

                clearInterval(
                    countdownInterval
                );


                display.textContent =
                    '00:00';


                if (expiredMessage) {

                    expiredMessage.style.display =
                        'block';

                }


                if (btnContinue) {

                    btnContinue.disabled =
                        true;

                    btnContinue.style.opacity =
                        '0.5';

                    btnContinue.style.cursor =
                        'not-allowed';

                }


                return;
            }


            timer--;
        }


        updateDisplay();


        countdownInterval =
            setInterval(
                updateDisplay,
                1000
            );
    }


    // =========================
    // DOM READY
    // =========================

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            startCountdown(600);


            const bookingForm =
                document.getElementById(
                    'bookingForm'
                );


            if (!bookingForm) {
                return;
            }


            bookingForm.addEventListener(
                'submit',
                function(event) {

                    const btnContinue =
                        document.getElementById(
                            'btnContinue'
                        );


                    if (
                        btnContinue &&
                        btnContinue.disabled
                    ) {

                        event.preventDefault();

                        return;
                    }


                    if (
                        typeof selectedTickets !==
                        'undefined' &&
                        selectedTickets.length === 0
                    ) {

                        event.preventDefault();

                        alert(
                            'Vui lòng chọn ít nhất một ghế.'
                        );

                        return;
                    }

                }
            );

        }
    );

</script>


</body>

</html>