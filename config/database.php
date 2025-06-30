<?php
// Database configuration for PostgreSQL on Render.com
$host = "dpg-d1gk4s7gi27c73brav8g-a.oregon-postgres.render.com";
$username = "showtime_select_user";
$password = "kbJAnSvfJHodYK7oDCaqaR7OvwlnJQi1";
$database = "showtime_select";
$port = 5432; // PostgreSQL default port

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;sslmode=require";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 30 // 30 seconds timeout
    ]);
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

// Helper function to execute queries with better error handling
function executeQuery($conn, $sql, $params = []) {
    try {
        if (empty($params)) {
            return $conn->query($sql);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
    } catch (PDOException $e) {
        error_log("Database Query Error: " . $e->getMessage() . " | SQL: " . $sql);
        die("Database query failed. Please try again later.");
    }
}
?>