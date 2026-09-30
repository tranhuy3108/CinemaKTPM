<?php

class Bill
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    /**
     * Lấy tất cả bills với thông tin user
     */
    public function getAllBills()
    {
        $sql = "SELECT b.*, u.full_name, u.email, u.phone
                FROM bills b
                JOIN users u ON b.user_id = u.user_id
                ORDER BY b.created_at DESC";

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new Exception("SQL Error: " . $this->conn->error);
        }

        $bills = [];

        while ($row = $result->fetch_assoc()) {
            $bills[] = $row;
        }

        return $bills;
    }

    /**
     * Lấy bill theo ID
     */
    public function getBillById($billId)
    {
        $sql = "SELECT b.*, u.full_name, u.email, u.phone
                FROM bills b
                JOIN users u ON b.user_id = u.user_id
                WHERE b.bill_id = ?
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $billId);
        $stmt->execute();

        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Lấy danh sách ticket theo ID và khóa row trong transaction.
     *
     * Dùng SELECT ... FOR UPDATE để tránh 2 người cùng lúc
     * đặt cùng một ghế.
     */
    public function getTicketsForBooking($showtimeId, array $ticketIds)
    {
        if (empty($ticketIds)) {
            return [];
        }

        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));

        $sql = "SELECT
                    t.ticket_id,
                    t.show_id,
                    t.seat_id,
                    t.price,
                    t.status,
                    s.show_date,
                    s.start_time,
                    s.status AS show_status,
                    st.row_name,
                    st.seat_number
                FROM tickets t
                INNER JOIN shows s
                    ON t.show_id = s.show_id
                INNER JOIN seats st
                    ON t.seat_id = st.seat_id
                WHERE t.show_id = ?
                  AND t.ticket_id IN ($placeholders)
                FOR UPDATE";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $types = 'i' . str_repeat('i', count($ticketIds));
        $params = array_merge([$showtimeId], $ticketIds);

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();

        $tickets = [];

        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }

        return $tickets;
    }

    /**
     * Lấy combo đang hoạt động và khóa row trong transaction.
     */
    public function getCombosForBooking(array $comboIds)
    {
        if (empty($comboIds)) {
            return [];
        }

        $comboIds = array_values(array_unique(array_map('intval', $comboIds)));

        $placeholders = implode(',', array_fill(0, count($comboIds), '?'));

        $sql = "SELECT combo_id, name, price, status
                FROM combos
                WHERE status = 1
                  AND combo_id IN ($placeholders)
                FOR UPDATE";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $types = str_repeat('i', count($comboIds));

        $stmt->bind_param($types, ...$comboIds);
        $stmt->execute();

        $result = $stmt->get_result();

        $combos = [];

        while ($row = $result->fetch_assoc()) {
            $combos[(int)$row['combo_id']] = $row;
        }

        return $combos;
    }

    /**
     * Tạo bill.
     */
    public function createBill(
        $userId,
        $totalTickets,
        $totalAmount,
        $discountAmount = 0,
        $finalAmount = null
    ) {
        if ($finalAmount === null) {
            $finalAmount = $totalAmount - $discountAmount;
        }

        $sql = "INSERT INTO bills
                    (
                        user_id,
                        total_tickets,
                        total_amount,
                        discount_amount,
                        final_amount,
                        status
                    )
                VALUES (?, ?, ?, ?, ?, 'pending')";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $stmt->bind_param(
            'iiddd',
            $userId,
            $totalTickets,
            $totalAmount,
            $discountAmount,
            $finalAmount
        );

        if (!$stmt->execute()) {
            throw new Exception("Không thể tạo đơn hàng: " . $stmt->error);
        }

        return (int)$this->conn->insert_id;
    }

    /**
     * Gán ticket vào bill và chuyển sang booked.
     *
     * Có điều kiện status = available để đảm bảo ticket chưa bị
     * người khác lấy.
     */
    public function bookTicket($ticketId, $billId)
    {
        $sql = "UPDATE tickets
                SET bill_id = ?, status = 'booked'
                WHERE ticket_id = ?
                  AND status = 'available'";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $stmt->bind_param('ii', $billId, $ticketId);

        if (!$stmt->execute()) {
            throw new Exception("Không thể giữ ghế: " . $stmt->error);
        }

        if ($stmt->affected_rows !== 1) {
            throw new Exception("Ghế đã được người khác đặt. Vui lòng chọn ghế khác.");
        }

        return true;
    }

    /**
     * Thêm combo vào bill.
     *
     * Price phải là giá lấy từ database, không lấy giá từ browser.
     */
    public function insertBillCombo($billId, $comboId, $quantity, $price)
    {
        $sql = "INSERT INTO bill_combos
                    (bill_id, combo_id, quantity, price)
                VALUES (?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $stmt->bind_param(
            'iiid',
            $billId,
            $comboId,
            $quantity,
            $price
        );

        if (!$stmt->execute()) {
            throw new Exception("Không thể thêm combo vào đơn: " . $stmt->error);
        }

        return true;
    }

    /**
     * Lấy bills phân trang.
     */
    public function getPaginated($page = 1, $limit = 10, $status = null, $search = null)
    {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        $offset = ($page - 1) * $limit;

        $sql = "SELECT b.*, u.full_name, u.email, u.phone
                FROM bills b
                JOIN users u ON b.user_id = u.user_id
                WHERE 1=1";

        $params = [];
        $types = "";

        if ($status !== null && $status !== '') {
            $sql .= " AND b.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if ($search !== null && $search !== '') {
            $sql .= " AND (
                        b.bill_id LIKE ?
                        OR u.full_name LIKE ?
                        OR u.email LIKE ?
                    )";

            $searchParam = "%$search%";

            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;

            $types .= "sss";
        }

        $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $types .= "ii";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();

        $bills = [];

        while ($row = $result->fetch_assoc()) {
            $bills[] = $row;
        }

        return $bills;
    }

    /**
     * Đếm tổng bill theo filter.
     */
    public function getTotalCount($status = null, $search = null)
    {
        $sql = "SELECT COUNT(*) AS total
                FROM bills b
                JOIN users u ON b.user_id = u.user_id
                WHERE 1=1";

        $params = [];
        $types = "";

        if ($status !== null && $status !== '') {
            $sql .= " AND b.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if ($search !== null && $search !== '') {
            $sql .= " AND (
                        b.bill_id LIKE ?
                        OR u.full_name LIKE ?
                        OR u.email LIKE ?
                    )";

            $searchParam = "%$search%";

            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;

            $types .= "sss";
        }

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int)($row['total'] ?? 0);
    }

    /**
     * Đếm bill theo status.
     */
    public function getCountByStatus()
    {
        $sql = "SELECT status, COUNT(*) AS count
                FROM bills
                GROUP BY status";

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new Exception("SQL Error: " . $this->conn->error);
        }

        $counts = [
            'pending' => 0,
            'paid' => 0,
            'cancelled' => 0,
            'refunded' => 0,
            'total' => 0
        ];

        while ($row = $result->fetch_assoc()) {
            $status = $row['status'];
            $count = (int)$row['count'];

            if (array_key_exists($status, $counts)) {
                $counts[$status] = $count;
            }

            $counts['total'] += $count;
        }

        return $counts;
    }

    /**
     * Cập nhật status bill.
     */
    public function updateStatus($billId, $status)
    {
        $validStatuses = [
            'pending',
            'paid',
            'cancelled',
            'refunded'
        ];

        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException(
                "Invalid status: $status"
            );
        }

        $sql = "UPDATE bills SET status = ?";

        if ($status === 'paid') {
            $sql .= ", paid_at = CURRENT_TIMESTAMP";
        }

        $sql .= " WHERE bill_id = ?";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $stmt->bind_param('si', $status, $billId);

        if (!$stmt->execute()) {
            throw new Exception("Không thể cập nhật trạng thái đơn hàng.");
        }

        return true;
    }

    /**
     * Lấy tickets của bill.
     */
    public function getTicketsByBillId($billId)
    {
        $sql = "SELECT
                    t.*,
                    s.show_date,
                    s.start_time,
                    s.end_time,
                    m.title AS movie_title,
                    h.name AS hall_name,
                    c.name AS cinema_name,
                    se.row_name,
                    se.seat_number,
                    st.type_name
                FROM tickets t
                JOIN shows s
                    ON t.show_id = s.show_id
                JOIN movies m
                    ON s.movie_id = m.movie_id
                JOIN halls h
                    ON s.hall_id = h.hall_id
                JOIN cinemas c
                    ON h.cinema_id = c.cinema_id
                JOIN seats se
                    ON t.seat_id = se.seat_id
                LEFT JOIN seat_types st
                    ON se.seat_type_id = st.seat_type_id
                WHERE t.bill_id = ?
                ORDER BY se.row_name, se.seat_number";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $stmt->bind_param('i', $billId);
        $stmt->execute();

        $result = $stmt->get_result();

        $tickets = [];

        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }

        return $tickets;
    }

    /**
     * Lấy combos của bill.
     */
    public function getCombosByBillId($billId)
    {
        $sql = "SELECT
                    bc.*,
                    cb.name AS combo_name
                FROM bill_combos bc
                JOIN combos cb
                    ON bc.combo_id = cb.combo_id
                WHERE bc.bill_id = ?
                ORDER BY cb.name";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $stmt->bind_param('i', $billId);
        $stmt->execute();

        $result = $stmt->get_result();

        $combos = [];

        while ($row = $result->fetch_assoc()) {
            $combos[] = $row;
        }

        return $combos;
    }

    /**
     * Hàm cũ vẫn giữ để không làm hỏng các chỗ admin đang gọi.
     *
     * Lưu ý:
     * Trong flow booking mới, BillService sẽ dùng giá combo
     * lấy trực tiếp từ database.
     */
    public function insertBillCombos($billId, $combos)
    {
        if (empty($combos)) {
            return true;
        }

        foreach ($combos as $combo) {
            $comboId = (int)($combo['combo_id'] ?? 0);
            $quantity = (int)($combo['quantity'] ?? 0);
            $price = (float)($combo['price'] ?? 0);

            if ($comboId <= 0 || $quantity <= 0) {
                continue;
            }

            $this->insertBillCombo(
                $billId,
                $comboId,
                $quantity,
                $price
            );
        }

        return true;
    }

    /**
     * Lấy bills của user.
     */
    public function getBillsByUserId($userId)
    {
        $sql = "SELECT
                    b.*,
                    COUNT(t.ticket_id) AS total_tickets
                FROM bills b
                LEFT JOIN tickets t
                    ON t.bill_id = b.bill_id
                WHERE b.user_id = ?
                GROUP BY b.bill_id
                ORDER BY b.created_at DESC";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $this->conn->error);
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}