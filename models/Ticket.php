<?php

class Ticket
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Lấy database connection
     */
    public function getConnection()
    {
        return $this->conn;
    }

    /**
     * Lấy tất cả ticket
     */
    public function getAllTickets(): array
    {
        $sql = "
            SELECT
                t.*,
                sh.show_date,
                sh.start_time,
                sh.end_time,
                m.title AS movie_title,
                h.name AS hall_name,
                c.name AS cinema_name,
                s.row_name AS seat_name,
                s.seat_number AS seat_number,
                st.type_name AS type_name
            FROM tickets t
            JOIN shows sh
                ON sh.show_id = t.show_id
            JOIN movies m
                ON m.movie_id = sh.movie_id
            JOIN halls h
                ON h.hall_id = sh.hall_id
            JOIN cinemas c
                ON c.cinema_id = h.cinema_id
            JOIN seats s
                ON s.seat_id = t.seat_id
            JOIN seat_types st
                ON st.seat_type_id = s.seat_type_id
            ORDER BY
                sh.show_date DESC,
                sh.start_time ASC,
                s.row_name ASC,
                s.seat_number ASC
        ";

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new Exception(
                'Không thể lấy danh sách ticket: '
                . $this->conn->error
            );
        }

        $tickets = [];

        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }

        return $tickets;
    }

    /**
     * Lấy ticket theo show
     */
    public function getTicketByShowId($show_id): array
    {
        $sql = "
            SELECT
                t.ticket_id,
                t.show_id,
                t.seat_id,
                t.bill_id,
                t.price,
                t.status,
                t.created_at,

                s.row_name,
                s.seat_number,
                s.seat_type_id,

                st.type_name,
                st.price_multiplier,

                sh.show_date,
                sh.start_time,
                sh.end_time,
                sh.base_price,

                h.name AS hall_name,
                c.name AS cinema_name
            FROM tickets t

            JOIN shows sh
                ON sh.show_id = t.show_id

            JOIN halls h
                ON h.hall_id = sh.hall_id

            JOIN cinemas c
                ON c.cinema_id = h.cinema_id

            JOIN seats s
                ON s.seat_id = t.seat_id

            JOIN seat_types st
                ON st.seat_type_id = s.seat_type_id

            WHERE t.show_id = ?

            ORDER BY
                s.row_name ASC,
                s.seat_number ASC
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị truy vấn ticket: '
                . $this->conn->error
            );
        }

        $stmt->bind_param('i', $show_id);
        $stmt->execute();

        $result = $stmt->get_result();

        $tickets = [];

        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }

        return $tickets;
    }

    /**
     * Lấy toàn bộ ghế của một suất chiếu
     *
     * QUAN TRỌNG:
     * Tên method thống nhất là getAllSeatsByShow()
     */
    public function getAllSeatsByShow($show_id): array
    {
        $sql = "
            SELECT
                s.*,
                st.type_name,
                st.price_multiplier,
                sh.base_price,
                sh.show_id
            FROM seats s

            JOIN halls h
                ON h.hall_id = s.hall_id

            JOIN shows sh
                ON sh.hall_id = h.hall_id

            JOIN seat_types st
                ON st.seat_type_id = s.seat_type_id

            WHERE sh.show_id = ?

            ORDER BY
                s.row_name ASC,
                s.seat_number ASC
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị truy vấn ghế: '
                . $this->conn->error
            );
        }

        $stmt->bind_param('i', $show_id);
        $stmt->execute();

        $result = $stmt->get_result();

        $seats = [];

        while ($row = $result->fetch_assoc()) {
            $seats[] = $row;
        }

        return $seats;
    }

    /**
     * Tạo ticket
     */
    public function createTicket(
        $show_id,
        $seat_id,
        $bill_id,
        $price,
        $status
    ): bool {
        /*
         * bill_id có thể NULL khi ticket mới được tạo.
         * Vì vậy xử lý riêng trường hợp NULL thay vì bind
         * integer trực tiếp.
         */
        if ($bill_id === null) {
            $sql = "
                INSERT INTO tickets
                (
                    show_id,
                    seat_id,
                    bill_id,
                    price,
                    status
                )
                VALUES (?, ?, NULL, ?, ?)
            ";

            $stmt = $this->conn->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    'Không thể chuẩn bị tạo ticket: '
                    . $this->conn->error
                );
            }

            $stmt->bind_param(
                'iids',
                $show_id,
                $seat_id,
                $price,
                $status
            );
        } else {
            $sql = "
                INSERT INTO tickets
                (
                    show_id,
                    seat_id,
                    bill_id,
                    price,
                    status
                )
                VALUES (?, ?, ?, ?, ?)
            ";

            $stmt = $this->conn->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    'Không thể chuẩn bị tạo ticket: '
                    . $this->conn->error
                );
            }

            $stmt->bind_param(
                'iiids',
                $show_id,
                $seat_id,
                $bill_id,
                $price,
                $status
            );
        }

        if (!$stmt->execute()) {
            throw new Exception(
                'Không thể tạo ticket: '
                . $stmt->error
            );
        }

        return true;
    }

    /**
     * Cập nhật trạng thái ticket
     */
    public function updateStatusTicket(
        $ticket_id,
        $status
    ): bool {
        $sql = "
            UPDATE tickets
            SET status = ?
            WHERE ticket_id = ?
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị cập nhật ticket: '
                . $this->conn->error
            );
        }

        $stmt->bind_param(
            'si',
            $status,
            $ticket_id
        );

        return $stmt->execute();
    }

    /**
     * Cập nhật trạng thái ticket theo bill
     */
    public function updateStatusByBillId(
        $bill_id,
        $status
    ): bool {
        $sql = "
            UPDATE tickets
            SET status = ?
            WHERE bill_id = ?
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị cập nhật ticket theo bill: '
                . $this->conn->error
            );
        }

        $stmt->bind_param(
            'si',
            $status,
            $bill_id
        );

        return $stmt->execute();
    }

    /**
     * Lấy ticket theo bill
     */
    public function getTicketsByBillId($billId): array
    {
        $sql = "
            SELECT
                t.ticket_id,
                t.show_id,
                t.seat_id,
                t.bill_id,
                t.price,
                t.status,
                t.created_at,

                s.row_name,
                s.seat_number,

                st.type_name,

                sh.show_date,
                sh.start_time,
                sh.end_time,

                m.title AS movie_title,

                h.name AS hall_name,

                c.name AS cinema_name

            FROM tickets t

            JOIN seats s
                ON s.seat_id = t.seat_id

            JOIN seat_types st
                ON st.seat_type_id = s.seat_type_id

            JOIN shows sh
                ON sh.show_id = t.show_id

            JOIN movies m
                ON m.movie_id = sh.movie_id

            JOIN halls h
                ON h.hall_id = sh.hall_id

            JOIN cinemas c
                ON c.cinema_id = h.cinema_id

            WHERE t.bill_id = ?

            ORDER BY
                s.row_name ASC,
                s.seat_number ASC
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị truy vấn ticket theo bill: '
                . $this->conn->error
            );
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
}