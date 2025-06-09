<?php
session_start();

// RBAC: Accessible only by Super Admin (roleID 1)
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 1) {
    header("Location: index.php");
    exit();
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "movie_db"; // Ensured to be movie_db
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirectUrl = "users.php"; // Default redirect location

if ($userId > 0) {
    // Prevent self-deletion for the logged-in admin (optional but good practice)
    if ($userId == $_SESSION['admin_id']) {
        $redirectUrl .= "?error=" . urlencode("You cannot delete your own admin account.");
        header("Location: " . $redirectUrl);
        exit();
    }

    // Check if the user is associated with any bookings (via bookingEmail or username)
    // Assuming `users.username` corresponds to `bookingtable.bookingEmail`
    $checkBookingsQuery = $conn->prepare("SELECT COUNT(*) as count FROM bookingtable WHERE bookingEmail = (SELECT username FROM users WHERE id = ?)");
    $checkBookingsQuery->bind_param("i", $userId);
    $checkBookingsQuery->execute();
    $bookingsCount = $checkBookingsQuery->get_result()->fetch_assoc()['count'];
    $checkBookingsQuery->close();

    if ($bookingsCount > 0) {
        $errorMessage = "Cannot delete user. This user has " . $bookingsCount . " booking(s). Please delete their bookings first.";
        $redirectUrl .= "?error=" . urlencode($errorMessage);
    } else {
        // Proceed with deletion
        $deleteQuery = $conn->prepare("DELETE FROM users WHERE id = ?");
        $deleteQuery->bind_param("i", $userId);
        
        if ($deleteQuery->execute()) {
            $redirectUrl .= "?success=User deleted successfully!";
        } else {
            $redirectUrl .= "?error=Error deleting user: " . urlencode($conn->error);
        }
        $deleteQuery->close();
    }
} else {
    $redirectUrl .= "?error=Invalid user ID provided for deletion.";
}

$conn->close();

header("Location: " . $redirectUrl);
exit();
?>
