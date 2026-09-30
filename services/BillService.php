<?php

require_once __DIR__ . '/../models/Bill.php';
require_once __DIR__ . '/../services/TicketService.php';

class BillService
{
    private $billModel;
    private $ticketService;

    public function __construct($billModel, $ticketService)
    {
        $this->billModel = $billModel;
        $this->ticketService = $ticketService;
    }

    public function getAllBills()
    {
        return $this->billModel->getAllBills();
    }

    public function getBillById($billId)
    {
        if (empty($billId) || !is_numeric($billId)) {
            throw new InvalidArgumentException("Bill ID không hợp lệ.");
        }

        return $this->billModel->getBillById((int)$billId);
    }

    public function getPaginated(
        $page = 1,
        $limit = 10,
        $status = null,
        $search = null
    ) {
        $total = $this->billModel->getTotalCount(
            $status,
            $search
        );

        $totalPages = max(
            1,
            (int)ceil($total / $limit)
        );

        $page = max(
            1,
            min((int)$page, $totalPages)
        );

        $data = $this->billModel->getPaginated(
            $page,
            $limit,
            $status,
            $search
        );

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages
        ];
    }

    public function getStats()
    {
        return $this->billModel->getCountByStatus();
    }

    /**
     * Tạo booking từ dữ liệu người dùng gửi lên.
     *
     * TUYỆT ĐỐI KHÔNG tin:
     * - grand_total
     * - seat_total
     * - food_total
     * - ticket price từ browser
     * - combo price từ browser
     *
     * Server tự lấy lại toàn bộ giá từ database.
     */
    public function createBooking(
        $userId,
        $showtimeId,
        array $ticketIds,
        array $selectedCombos = []
    ) {
        $userId = (int)$userId;
        $showtimeId = (int)$showtimeId;

        if ($userId <= 0) {
            throw new InvalidArgumentException(
                "Người dùng không hợp lệ."
            );
        }

        if ($showtimeId <= 0) {
            throw new InvalidArgumentException(
                "Suất chiếu không hợp lệ."
            );
        }

        /**
         * Chuẩn hóa ticket IDs.
         */
        $ticketIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $ticketIds),
                    function ($id) {
                        return $id > 0;
                    }
                )
            )
        );

        if (empty($ticketIds)) {
            throw new InvalidArgumentException(
                "Vui lòng chọn ít nhất một ghế."
            );
        }

        /**
         * Giới hạn tối đa 8 vé.
         */
        if (count($ticketIds) > 8) {
            throw new InvalidArgumentException(
                "Bạn chỉ được đặt tối đa 8 vé."
            );
        }

        $conn = $this->billModel->getConnection();

        try {
            $conn->begin_transaction();

            /**
             * Kiểm tra suất chiếu.
             */
            $sqlShow = "SELECT
                            show_id,
                            movie_id,
                            hall_id,
                            show_date,
                            start_time,
                            end_time,
                            base_price,
                            status
                        FROM shows
                        WHERE show_id = ?
                        LIMIT 1
                        FOR UPDATE";

            $stmtShow = $conn->prepare($sqlShow);

            if (!$stmtShow) {
                throw new Exception(
                    "Không thể kiểm tra suất chiếu."
                );
            }

            $stmtShow->bind_param(
                'i',
                $showtimeId
            );

            $stmtShow->execute();

            $showResult = $stmtShow->get_result();
            $show = $showResult->fetch_assoc();

            if (!$show) {
                throw new InvalidArgumentException(
                    "Suất chiếu không tồn tại."
                );
            }

            if ((int)$show['status'] !== 1) {
                throw new InvalidArgumentException(
                    "Suất chiếu hiện không hoạt động."
                );
            }

            /**
             * Không cho đặt suất chiếu đã bắt đầu.
             */
            date_default_timezone_set('Asia/Ho_Chi_Minh');

            $showDateTime = strtotime(
                $show['show_date'] . ' ' . $show['start_time']
            );

            if ($showDateTime !== false && $showDateTime <= time()) {
                throw new InvalidArgumentException(
                    "Suất chiếu này đã bắt đầu hoặc đã qua."
                );
            }

            /**
             * Lấy ticket thực tế từ DB + FOR UPDATE.
             */
            $tickets = $this->billModel->getTicketsForBooking(
                $showtimeId,
                $ticketIds
            );

            /**
             * Phải tìm đủ số ticket.
             */
            if (count($tickets) !== count($ticketIds)) {
                throw new InvalidArgumentException(
                    "Một hoặc nhiều ghế không thuộc suất chiếu này."
                );
            }

            /**
             * Kiểm tra từng ticket.
             */
            $seatTotal = 0;

            foreach ($tickets as $ticket) {
                if ($ticket['status'] !== 'available') {
                    throw new InvalidArgumentException(
                        "Ghế " .
                        $ticket['row_name'] .
                        $ticket['seat_number'] .
                        " vừa được người khác đặt. Vui lòng chọn ghế khác."
                    );
                }

                $seatTotal += (float)$ticket['price'];
            }

            /**
             * Chuẩn hóa combo.
             *
             * Browser chỉ được gửi:
             * combo_id + quantity.
             *
             * Price browser gửi lên sẽ bị bỏ qua.
             */
            $normalizedCombos = [];

            foreach ($selectedCombos as $combo) {
                if (!is_array($combo)) {
                    continue;
                }

                $comboId = (int)($combo['combo_id'] ?? 0);
                $quantity = (int)($combo['quantity'] ?? 0);

                if ($comboId <= 0 || $quantity <= 0) {
                    continue;
                }

                /**
                 * Tránh duplicate combo.
                 */
                if (!isset($normalizedCombos[$comboId])) {
                    $normalizedCombos[$comboId] = 0;
                }

                $normalizedCombos[$comboId] += $quantity;
            }

            /**
             * Không cho quantity vô lý.
             */
            foreach ($normalizedCombos as $comboId => $quantity) {
                if ($quantity > 20) {
                    throw new InvalidArgumentException(
                        "Số lượng combo không hợp lệ."
                    );
                }
            }

            $foodTotal = 0;
            $comboRows = [];

            if (!empty($normalizedCombos)) {
                $comboMap = $this->billModel->getCombosForBooking(
                    array_keys($normalizedCombos)
                );

                foreach ($normalizedCombos as $comboId => $quantity) {
                    if (!isset($comboMap[$comboId])) {
                        throw new InvalidArgumentException(
                            "Một trong các combo đã ngừng bán."
                        );
                    }

                    $combo = $comboMap[$comboId];

                    $price = (float)$combo['price'];
                    $itemTotal = $price * $quantity;

                    $foodTotal += $itemTotal;

                    $comboRows[] = [
                        'combo_id' => $comboId,
                        'quantity' => $quantity,
                        'price' => $price
                    ];
                }
            }

            /**
             * SERVER TỰ TÍNH tổng tiền.
             */
            $totalAmount = $seatTotal + $foodTotal;
            $discountAmount = 0;
            $finalAmount = $totalAmount;

            /**
             * Tạo bill pending.
             */
            $billId = $this->billModel->createBill(
                $userId,
                count($tickets),
                $totalAmount,
                $discountAmount,
                $finalAmount
            );

            /**
             * Gán ticket vào bill.
             */
            foreach ($tickets as $ticket) {
                $this->billModel->bookTicket(
                    (int)$ticket['ticket_id'],
                    $billId
                );
            }

            /**
             * Lưu combo với GIÁ DATABASE.
             */
            foreach ($comboRows as $combo) {
                $this->billModel->insertBillCombo(
                    $billId,
                    $combo['combo_id'],
                    $combo['quantity'],
                    $combo['price']
                );
            }

            $conn->commit();

            return [
                'bill_id' => $billId,
                'seat_total' => $seatTotal,
                'food_total' => $foodTotal,
                'total_amount' => $totalAmount,
                'final_amount' => $finalAmount,
                'total_tickets' => count($tickets)
            ];
        } catch (Throwable $e) {
            if ($conn->in_transaction) {
                $conn->rollback();
            }

            throw $e;
        }
    }

    /**
     * Xác nhận thanh toán.
     *
     * Admin dùng hàm này để chuyển:
     * pending -> paid
     *
     * Đồng thời:
     * booked -> paid
     */
    public function confirmPayment($billId)
    {
        $billId = (int)$billId;

        if ($billId <= 0) {
            throw new InvalidArgumentException(
                "Bill ID không hợp lệ."
            );
        }

        $conn = $this->billModel->getConnection();

        try {
            $conn->begin_transaction();

            $bill = $this->billModel->getBillById($billId);

            if (!$bill) {
                throw new InvalidArgumentException(
                    "Đơn hàng không tồn tại."
                );
            }

            if ($bill['status'] !== 'pending') {
                throw new InvalidArgumentException(
                    "Chỉ có thể xác nhận đơn hàng đang chờ thanh toán."
                );
            }

            $this->billModel->updateStatus(
                $billId,
                'paid'
            );

            $this->ticketService->updateStatusByBillId(
                $billId,
                'paid'
            );

            $conn->commit();

            return true;
        } catch (Throwable $e) {
            if ($conn->in_transaction) {
                $conn->rollback();
            }

            throw $e;
        }
    }

    /**
     * Hủy đơn hàng.
     */
    public function cancelBill($billId)
    {
        $billId = (int)$billId;

        $bill = $this->billModel->getBillById($billId);

        if (!$bill) {
            throw new InvalidArgumentException(
                "Đơn hàng không tồn tại."
            );
        }

        if ($bill['status'] === 'cancelled') {
            throw new InvalidArgumentException(
                "Đơn hàng đã bị hủy trước đó."
            );
        }

        if ($bill['status'] === 'paid') {
            throw new InvalidArgumentException(
                "Không thể hủy đơn hàng đã thanh toán. Vui lòng hoàn tiền."
            );
        }

        $conn = $this->billModel->getConnection();

        try {
            $conn->begin_transaction();

            $result = $this->billModel->updateStatus(
                $billId,
                'cancelled'
            );

            /**
             * Trả ghế về available.
             */
            $sql = "UPDATE tickets
                    SET status = 'available',
                        bill_id = NULL
                    WHERE bill_id = ?
                      AND status = 'booked'";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    "Không thể giải phóng ghế."
                );
            }

            $stmt->bind_param('i', $billId);
            $stmt->execute();

            $conn->commit();

            return $result;
        } catch (Throwable $e) {
            if ($conn->in_transaction) {
                $conn->rollback();
            }

            throw $e;
        }
    }

    /**
     * Hoàn tiền.
     */
    public function refundBill($billId)
    {
        $billId = (int)$billId;

        $bill = $this->billModel->getBillById($billId);

        if (!$bill) {
            throw new InvalidArgumentException(
                "Đơn hàng không tồn tại."
            );
        }

        if ($bill['status'] !== 'paid') {
            throw new InvalidArgumentException(
                "Chỉ có thể hoàn tiền đơn hàng đã thanh toán."
            );
        }

        return $this->billModel->updateStatus(
            $billId,
            'refunded'
        );
    }

    /**
     * Lấy chi tiết bill.
     */
    public function getBillDetails($billId)
    {
        $billId = (int)$billId;

        if ($billId <= 0) {
            return null;
        }

        $bill = $this->billModel->getBillById($billId);

        if (!$bill) {
            return null;
        }

        $bill['tickets'] =
            $this->billModel->getTicketsByBillId($billId);

        $bill['combos'] =
            $this->billModel->getCombosByBillId($billId);

        return $bill;
    }

    /**
     * Giữ compatibility với code cũ.
     */
    public function saveBillCombos($billId, $combos)
    {
        if (empty($combos)) {
            return true;
        }

        return $this->billModel->insertBillCombos(
            $billId,
            $combos
        );
    }

    public function getBillsByUserId($userId)
    {
        $userId = (int)$userId;

        if ($userId <= 0) {
            throw new InvalidArgumentException(
                "User không tồn tại."
            );
        }

        return $this->billModel->getBillsByUserId($userId);
    }
}