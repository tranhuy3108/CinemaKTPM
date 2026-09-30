<?php

class Show
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Lấy tất cả suất chiếu
     */
    public function getAllShows(): array
    {
        $sql = "
            SELECT
                s.*,
                m.title AS movie_title,
                h.name AS hall_name,
                c.name AS cinema_name,
                c.cinema_id AS cinema_id
            FROM shows s
            JOIN movies m
                ON s.movie_id = m.movie_id
            JOIN halls h
                ON s.hall_id = h.hall_id
            JOIN cinemas c
                ON h.cinema_id = c.cinema_id
            ORDER BY
                s.show_date ASC,
                s.start_time ASC
        ";

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new Exception(
                'Không thể lấy danh sách suất chiếu: '
                . $this->conn->error
            );
        }

        $shows = [];

        while ($row = $result->fetch_assoc()) {
            $shows[] = $row;
        }

        return $shows;
    }

    /**
     * Lấy suất chiếu theo ID
     */
    public function getShowById($id): ?array
    {
        $sql = "
            SELECT
                s.*,
                m.title AS movie_title,
                h.name AS hall_name,
                c.name AS cinema_name,
                c.cinema_id AS cinema_id
            FROM shows s
            JOIN movies m
                ON s.movie_id = m.movie_id
            JOIN halls h
                ON s.hall_id = h.hall_id
            JOIN cinemas c
                ON h.cinema_id = c.cinema_id
            WHERE s.show_id = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị truy vấn suất chiếu: '
                . $this->conn->error
            );
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $result = $stmt->get_result();

        $show = $result->fetch_assoc();

        return $show ?: null;
    }

    /**
     * Lấy các suất chiếu đang hoạt động của một phim
     */
    public function getShowsByMovieId($movie_id): array
    {
        $sql = "
            SELECT
                s.*,
                c.name AS cinema_name,
                h.name AS hall_name
            FROM shows s
            JOIN movies m
                ON s.movie_id = m.movie_id
            JOIN halls h
                ON s.hall_id = h.hall_id
            JOIN cinemas c
                ON h.cinema_id = c.cinema_id
            WHERE
                s.movie_id = ?
                AND s.status = 1
            ORDER BY
                s.show_date ASC,
                s.start_time ASC
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị truy vấn suất chiếu: '
                . $this->conn->error
            );
        }

        $stmt->bind_param('i', $movie_id);
        $stmt->execute();

        $result = $stmt->get_result();

        $shows = [];

        while ($row = $result->fetch_assoc()) {
            $shows[] = $row;
        }

        return $shows;
    }

    /**
     * Tạo suất chiếu
     *
     * Status = 1 ngay sau khi tạo
     */
    public function createShow(
        $movie_id,
        $hall_id,
        $show_date,
        $start_time,
        $end_time,
        $base_price
    ): int {
        $sql = "
            INSERT INTO shows
            (
                movie_id,
                hall_id,
                show_date,
                start_time,
                end_time,
                base_price,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị tạo suất chiếu: '
                . $this->conn->error
            );
        }

        $stmt->bind_param(
            'iisssd',
            $movie_id,
            $hall_id,
            $show_date,
            $start_time,
            $end_time,
            $base_price
        );

        if (!$stmt->execute()) {
            throw new Exception(
                'Không thể tạo suất chiếu: '
                . $stmt->error
            );
        }

        return (int)$this->conn->insert_id;
    }

    /**
     * Cập nhật suất chiếu
     */
    public function updateShow(
        $id,
        $movie_id,
        $hall_id,
        $show_date,
        $start_time,
        $end_time,
        $base_price,
        $status
    ): bool {
        $sql = "
            UPDATE shows
            SET
                movie_id = ?,
                hall_id = ?,
                show_date = ?,
                start_time = ?,
                end_time = ?,
                base_price = ?,
                status = ?
            WHERE show_id = ?
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị cập nhật suất chiếu: '
                . $this->conn->error
            );
        }

        $stmt->bind_param(
            'iisssdii',
            $movie_id,
            $hall_id,
            $show_date,
            $start_time,
            $end_time,
            $base_price,
            $status,
            $id
        );

        return $stmt->execute();
    }

    /**
     * Xóa suất chiếu
     */
    public function deleteShow($id): bool
    {
        $sql = "
            DELETE FROM shows
            WHERE show_id = ?
        ";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                'Không thể chuẩn bị xóa suất chiếu: '
                . $this->conn->error
            );
        }

        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Lấy danh sách rạp + phòng chiếu
     */
    public function getAllHalls_CinemasByShow(): array
    {
        $sql = "
            SELECT
                c.cinema_id,
                c.name AS cinema_name,
                h.hall_id,
                h.name AS hall_name,
                h.total_seats
            FROM cinemas c
            JOIN halls h
                ON h.cinema_id = c.cinema_id
            WHERE
                c.status = 1
                AND h.status = 1
            ORDER BY
                c.name ASC,
                h.name ASC
        ";

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new Exception(
                'Không thể lấy danh sách phòng chiếu: '
                . $this->conn->error
            );
        }

        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Lấy connection
     */
    public function getConnection()
    {
        return $this->conn;
    }
}