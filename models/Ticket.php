<?php

class Ticket
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

    public function getAllTickets()
    {
        $sql = "SELECT t.*, h.name AS hall_name, c.name AS cinema_name, s.row_name AS seat_name, s.seat_number AS seat_number, st.type_name AS type_name
                FROM tickets t
                JOIN shows sh ON sh.show_id = t.show_id
                JOIN halls h ON h.hall_id = sh.hall_id
                JOIN cinemas c ON c.cinema_id = h.cinema_id
                JOIN seats s ON s.seat_id = t.seat_id
                JOIN seat_types st ON s.seat_type_id = st.seat_type_id
        ";
        $result = $this->conn->query($sql);

        $tickets = [];
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }

        return $tickets;
    }

    public function getTicketByShowId($show_id)
    {
        $sql = "SELECT t.ticket_id, t.status, t.price,
                   s.row_name, s.seat_number, s.seat_type_id,
                   st.type_name, st.price_multiplier,
                   h.name AS hall_name,
                   c.name AS cinema_name
            FROM tickets t
            JOIN shows sh ON sh.show_id = t.show_id
            JOIN halls h ON h.hall_id = sh.hall_id
            JOIN cinemas c ON c.cinema_id = h.cinema_id
            JOIN seats s ON s.seat_id = t.seat_id
            JOIN seat_types st ON s.seat_type_id = st.seat_type_id
            WHERE sh.show_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $show_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $tickets = [];
        foreach ($result as $row) {
            $tickets[] = $row;
        }

        return $tickets;
    }

    public function getallSeatsByShow($show_id)
    {
        $sql = " SELECT 
                s.*,
                st.price_multiplier,
                sh.base_price
            FROM seats s
            JOIN halls h ON s.hall_id = h.hall_id
            JOIN shows sh ON sh.hall_id = h.hall_id
            JOIN seat_types st ON st.seat_type_id = s.seat_type_id
            WHERE sh.show_id = ?
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $show_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $seats = [];
        foreach ($result as $row) {
            $seats[] = $row;
        }

        return $seats;
    }


    public function createTicket($show_id, $seat_id, $bill_id, $price, $status)
    {
        $sql = "INSERT INTO tickets (show_id, seat_id, bill_id, price, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bind_param("iiids", $show_id, $seat_id, $bill_id, $price, $status);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        return true;
    }

    public function updateStatusTicket($ticket_id, $status)
    {
        $sql = "UPDATE tickets SET status = ? WHERE ticket_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $status, $ticket_id);
        return $stmt->execute();
    }

    public function updateStatusByBillId($bill_id, $status)
    {
        $sql = "UPDATE tickets SET status = ? WHERE bill_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $status, $bill_id);
        return $stmt->execute();
    }

    public function getTicketsByBillId($billId)
    {
        $sql = "SELECT t.ticket_id, t.price, t.status,
                   s.row_name, s.seat_number, st.type_name,
                   sh.show_date, sh.start_time,
                   m.title AS movie_title,
                   h.name AS hall_name,
                   c.name AS cinema_name
            FROM tickets t
            JOIN seats s ON s.seat_id = t.seat_id
            JOIN seat_types st ON st.seat_type_id = s.seat_type_id
            JOIN shows sh ON sh.show_id = t.show_id
            JOIN movies m ON m.movie_id = sh.movie_id
            JOIN halls h ON h.hall_id = sh.hall_id
            JOIN cinemas c ON c.cinema_id = h.cinema_id
            WHERE t.bill_id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL Error in getTicketsByBillId: " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("i", $billId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tickets = [];
        while ($row = $result->fetch_assoc()) $tickets[] = $row;
        return $tickets;
    }
}
