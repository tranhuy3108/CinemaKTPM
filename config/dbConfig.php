<?php
function getDBConnection()
{
    $host     = "localhost";   // hoặc localhost
    $port     = 3306;          // port MySQL của bạn
    $username = "root";
    $password = "";
    $dbname   = "cinemax";

    try {
        $conn = new mysqli($host, $username, $password, $dbname, $port);

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Set charset để tránh lỗi tiếng Việt
        $conn->set_charset("utf8mb4");

        return $conn;
    } catch (Exception $e) {
        // Trong môi trường thật nên log thay vì echo
        die("Database connection error: " . $e->getMessage());
    }
}
