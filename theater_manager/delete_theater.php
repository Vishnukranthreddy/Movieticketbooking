<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Theater Manager (roleID 2)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2)) {
    header("Location: ../admin/index.php"); // Redirect to central admin login
    exit();
}

// Database connection
$host = "dpg-d1gk4s7gi27c73brav8g-a.oregon-postgres.render.com";
$username = "showtime_select_user";
$password = "kbJAnSvfJHodYK7oDCaqaR7OvwlnJQi1";
$database = "showtime_select"; // Ensured to be movie_db
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$theaterId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirectUrl = "theaters.php"; // Default redirect location

if ($theaterId > 0) {
    // IMPORTANT: Foreign key constraints should ideally handle cascading deletes
    // if configured with ON DELETE CASCADE for theater_halls, movie_schedules, and bookingtable.
    // Our combined SQL has ON DELETE CASCADE for theater_halls.theaterID.
    // Ensure cascades are set up correctly on all levels (theaters -> halls -> schedules -> bookings).

    // Delete the theater
    $deleteQuery = $conn->prepare("DELETE FROM theaters WHERE theaterID = ?");
    $deleteQuery->bind_param("i", $theaterId);
    
    if ($deleteQuery->execute()) {
        // If ON DELETE CASCADE is set up correctly, associated halls, schedules, and bookings
        // should be deleted automatically.
        $redirectUrl .= "?success=Theater deleted successfully!";
    } else {
        // Provide a more specific error if the deletion fails, especially due to FKs
        $errorMessage = "Error deleting theater: " . $conn->error;
        if (strpos($errorMessage, "Cannot delete or update a parent row: a foreign key constraint fails") !== false) {
            $errorMessage = "Cannot delete theater. There are still associated halls, schedules, or bookings. Please delete them first or ensure cascading deletes are properly configured in your database schema.";
        }
        $redirectUrl .= "?error=" . urlencode($errorMessage);
    }
    $deleteQuery->close();
} else {
    $redirectUrl .= "?error=Invalid theater ID provided for deletion.";
}

$conn->close();

header("Location: " . $redirectUrl);
exit();
?>
