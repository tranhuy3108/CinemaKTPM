<?php

class Show
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAllShows()
    {
        $sql = "SELECT s.*, m.title AS movie_title, h.name AS hall_name, c.name AS cinema_name, c.cinema_id AS cinema_id 
                FROM shows s 
                JOIN movies m ON s.movie_id = m.movie_id 
                JOIN halls h ON s.hall_id = h.hall_id
                JOIN cinemas c ON h.cinema_id = c.cinema_id
                ";
        $result = $this->conn->query($sql);

        $shows = [];
        while ($row = $result->fetch_assoc()) {
            $shows[] = $row;
        }

        return $shows;
    }

    public function getShowById($id)
    {
        $sql = "SELECT s.*, m.title AS movie_title, h.name AS hall_name, c.name AS cinema_name
            FROM shows s
            JOIN movies m ON s.movie_id = m.movie_id
            JOIN halls h ON s.hall_id = h.hall_id
            JOIN cinemas c ON h.cinema_id = c.cinema_id
            WHERE s.show_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getShowsByMovieId($movie_id)
    {
        $sql = "SELECT s.*, c.name AS cinema_name, h.name AS hall_name
                FROM shows s 
                JOIN movies m ON s.movie_id = m.movie_id 
                JOIN halls h ON s.hall_id = h.hall_id
                JOIN cinemas c ON h.cinema_id = c.cinema_id
                WHERE s.movie_id = ? AND s.status = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $movie_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $shows = [];
        foreach ($result as $row) {
            $shows[] = $row;
        }

        return $shows;
    }

    public function createShow($movie_id, $hall_id, $show_date, $start_time, $end_time, $base_price)
    {
        // Suất chiếu mới tạo được mở bán ngay để User nhìn thấy và đặt vé.
        // Trạng thái 0 = Chưa mở bán, 1 = Đang mở bán, -1 = Đã kết thúc.
        $sql = "INSERT INTO shows 
            (movie_id, hall_id, show_date, start_time, end_time, base_price, status) 
            VALUES (?, ?, ?, ?, ?, ?, 1)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bind_param(
            "iisssd",
            $movie_id,
            $hall_id,
            $show_date,
            $start_time,
            $end_time,
            $base_price
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        return $this->conn->insert_id;
    }

    public function updateShow($id, $movie_id, $hall_id, $show_date, $start_time, $end_time, $base_price, $status)
    {
        $sql = "UPDATE shows SET movie_id = ?, hall_id = ?, show_date = ?, start_time = ?, end_time = ?, base_price = ?, status = ? WHERE show_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iisssdii", $movie_id, $hall_id, $show_date, $start_time, $end_time, $base_price, $status, $id);
        return $stmt->execute();
    }

    public function deleteShow($id)
    {
        $sql = "DELETE FROM shows WHERE show_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getConnection()
    {
        return $this->conn;
    }
}
