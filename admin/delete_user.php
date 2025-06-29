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

// Database connection
$host = "dpg-d1gk4s7gi27c73brav8g-a.oregon-postgres.render.com";
$username = "showtime_select_user";
$password = "kbJAnSvfJHodYK7oDCaqaR7OvwlnJQi1";
$database = "showtime_select"; // Ensured to be movie_db
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    $statusMessage = "Database connection failed: " . $conn->connect_error;
    // Log error for debugging on server side if possible
    error_log("DB Connection Error in delete_user.php: " . $conn->connect_error);
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
        $checkBookingsQuery = $conn->prepare("SELECT COUNT(*) as count FROM bookingtable WHERE bookingEmail = (SELECT username FROM users WHERE id = ?)");
        if ($checkBookingsQuery === false) {
             $statusMessage = "Failed to prepare booking check query: " . $conn->error;
        } else {
            $checkBookingsQuery->bind_param("i", $userId);
            $checkBookingsQuery->execute();
            $bookingsResult = $checkBookingsQuery->get_result();
            $bookingsCount = $bookingsResult->fetch_assoc()['count'];
            $checkBookingsQuery->close();

            if ($bookingsCount > 0) {
                $statusMessage = "Cannot delete user. This user has " . $bookingsCount . " booking(s). Please delete their bookings first.";
                $messageType = "warning"; // Use warning for non-critical user-fixable issues
            } else {
                // Proceed with deletion
                $deleteQuery = $conn->prepare("DELETE FROM users WHERE id = ?");
                if ($deleteQuery === false) {
                    $statusMessage = "Failed to prepare user deletion query: " . $conn->error;
                } else {
                    $deleteQuery->bind_param("i", $userId);
                    
                    if ($deleteQuery->execute()) {
                        $statusMessage = "User deleted successfully!";
                        $messageType = "success";
                    } else {
                        $statusMessage = "Error deleting user: " . $conn->error;
                        // Log specific DB error for server-side debugging
                        error_log("DB Delete Error in delete_user.php: " . $conn->error);
                    }
                    $deleteQuery->close();
                }
            }
        }
    }
} else {
    $statusMessage = "Invalid user ID provided for deletion.";
}

$conn->close();

// Redirect back to users.php with the status message
header("Location: users.php?message=" . urlencode($statusMessage) . "&type=" . $messageType);
exit();
?>
