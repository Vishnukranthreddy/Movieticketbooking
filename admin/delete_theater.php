<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Theater Manager (roleID 2)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2)) {
    header("Location: ../admin/index.php"); // Redirect to central admin login
    exit();
}

// Database connection details for PostgreSQL
$host = "dpg-d1gk4s7gi27c73brav8g-a.oregon-postgres.render.com";
$username = "showtime_select_user";
$password = "kbJAnSvfJHodYK7oDCaqaR7OvwlnJQi1";
$database = "showtime_select";
$port = "5432";

// Construct the connection string
$conn_string = "host={$host} port={$port} dbname={$database} user={$username} password={$password} sslmode=require";
// Establish PostgreSQL connection
$conn = pg_connect($conn_string);

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

$theaterId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirectUrl = "theaters.php"; // Default redirect location

if ($theaterId > 0) {
    // IMPORTANT: Foreign key constraints should ideally handle cascading deletes
    // if configured with ON DELETE CASCADE for theater_halls, movie_schedules, and bookingtable.
    // Ensure cascades are set up correctly on all levels (theaters -> halls -> schedules -> bookings).

    // Check for dependencies (halls, schedules, bookings) before deleting theater
    // This is a more robust check if ON DELETE CASCADE is not fully configured or understood.
    $hasHallsQuery = "SELECT COUNT(*) as count FROM theater_halls WHERE \"theaterID\" = $1";
    $hasHallsResult = pg_query_params($conn, $hasHallsQuery, array($theaterId));
    $hallsCount = pg_fetch_assoc($hasHallsResult)['count'];

    $hasSchedulesQuery = "SELECT COUNT(*) as count FROM movie_schedules ms JOIN theater_halls th ON ms.\"hallID\" = th.\"hallID\" WHERE th.\"theaterID\" = $1";
    $hasSchedulesResult = pg_query_params($conn, $hasSchedulesQuery, array($theaterId));
    $schedulesCount = pg_fetch_assoc($hasSchedulesResult)['count'];

    $hasBookingsQuery = "SELECT COUNT(*) as count FROM bookingtable b JOIN movie_schedules ms ON b.\"scheduleID\" = ms.\"scheduleID\" JOIN theater_halls th ON ms.\"hallID\" = th.\"hallID\" WHERE th.\"theaterID\" = $1";
    $hasBookingsResult = pg_query_params($conn, $hasBookingsQuery, array($theaterId));
    $bookingsCount = pg_fetch_assoc($hasBookingsResult)['count'];

    if ($hallsCount > 0 || $schedulesCount > 0 || $bookingsCount > 0) {
        $errorMessage = "Cannot delete theater. It has associated records:";
        if ($hallsCount > 0) $errorMessage .= " " . $hallsCount . " hall(s),";
        if ($schedulesCount > 0) $errorMessage .= " " . $schedulesCount . " schedule(s),";
        if ($bookingsCount > 0) $errorMessage .= " " . $bookingsCount . " booking(s),";
        $errorMessage = rtrim($errorMessage, ',') . ". Please delete them first.";
        $redirectUrl .= "?error=" . urlencode($errorMessage);
    } else {
        // Delete the theater
        $deleteQuery = "DELETE FROM theaters WHERE \"theaterID\" = $1";
        $deleteResult = pg_query_params($conn, $deleteQuery, array($theaterId));
        
        if ($deleteResult) {
            $redirectUrl .= "?success=Theater deleted successfully!";
        } else {
            $errorMessage = "Error deleting theater: " . pg_last_error($conn);
            $redirectUrl .= "?error=" . urlencode($errorMessage);
        }
    }
} else {
    $redirectUrl .= "?error=Invalid theater ID provided for deletion.";
}

pg_close($conn);

header("Location: " . $redirectUrl);
exit();
?>
