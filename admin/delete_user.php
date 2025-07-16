<?php
session_start();

// Initialize a message variable
$statusMessage = '';
$messageType = 'error'; // Default message type

// RBAC: Accessible only by Super Admin (roleID 1)
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 1) {
    $statusMessage = "Access Denied: You do not have permission to delete users.";
    header("Location: users.php?message=" . urlencode($statusMessage) . "&type=" . $messageType);
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
    $statusMessage = "Database connection failed: " . pg_last_error();
    error_log("DB Connection Error in delete_user.php: " . pg_last_error());
    header("Location: users.php?message=" . urlencode($statusMessage) . "&type=" . $messageType);
    exit();
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId > 0) {
    // Prevent self-deletion for the logged-in admin
    if ($userId == $_SESSION['admin_id']) {
        $statusMessage = "You cannot delete your own admin account.";
        $messageType = "danger"; // Use danger for critical errors
    } else {
        // Check if the user is associated with any bookings (via bookingEmail or username)
        // Assuming `users.username` corresponds to `bookingtable.bookingEmail`
        $checkBookingsQuery = "SELECT COUNT(*) as count FROM bookingtable WHERE \"bookingEmail\" = (SELECT username FROM users WHERE id = $1)";
        $checkBookingsResult = pg_query_params($conn, $checkBookingsQuery, array($userId));
        
        if (!$checkBookingsResult) {
            $statusMessage = "Failed to check user bookings: " . pg_last_error($conn);
        } else {
            $bookingsCount = pg_fetch_assoc($checkBookingsResult)['count'];

            if ($bookingsCount > 0) {
                $statusMessage = "Cannot delete user. This user has " . $bookingsCount . " booking(s). Please delete their bookings first.";
                $messageType = "warning"; // Use warning for non-critical user-fixable issues
            } else {
                // Proceed with deletion
                $deleteQuery = "DELETE FROM users WHERE id = $1";
                $deleteResult = pg_query_params($conn, $deleteQuery, array($userId));
                
                if ($deleteResult) {
                    $statusMessage = "User deleted successfully!";
                    $messageType = "success";
                } else {
                    $statusMessage = "Error deleting user: " . pg_last_error($conn);
                    error_log("DB Delete Error in delete_user.php: " . pg_last_error($conn));
                }
            }
        }
    }
} else {
    $statusMessage = "Invalid user ID provided for deletion.";
}

pg_close($conn);

// Redirect back to users.php with the status message
header("Location: users.php?message=" . urlencode($statusMessage) . "&type=" . $messageType);
exit();
?>
