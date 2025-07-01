<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Theater Manager (roleID 2)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2)) {
    header("Location: ../admin/index.php"); // Redirect to central admin login
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

$hallId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$theaterId = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : 0; // Needed to redirect back correctly
$redirectUrl = "theater_halls.php?theater_id=" . $theaterId; // Default redirect

if ($hallId > 0 && $theaterId > 0) {
    // Check for dependencies (schedules) before deleting hall
    $checkSchedulesQuery = $conn->prepare("SELECT COUNT(*) as count FROM movie_schedules WHERE hallID = ?");
    $checkSchedulesQuery->bind_param("i", $hallId);
    $checkSchedulesQuery->execute();
    $schedulesCount = $checkSchedulesQuery->get_result()->fetch_assoc()['count'];
    $checkSchedulesQuery->close();

    if ($schedulesCount > 0) {
        $errorMessage = "Cannot delete hall. It has " . $schedulesCount . " schedule(s) associated. Please delete all associated schedules first.";
        $redirectUrl .= "&error=" . urlencode($errorMessage);
    } else {
        // Delete the hall
        $deleteQuery = $conn->prepare("DELETE FROM theater_halls WHERE hallID = ?");
        $deleteQuery->bind_param("i", $hallId);
        
        if ($deleteQuery->execute()) {
            $redirectUrl .= "&success=Hall deleted successfully!";
        } else {
            $errorMessage = "Error deleting hall: " . $conn->error;
            $redirectUrl .= "&error=" . urlencode($errorMessage);
        }
        $deleteQuery->close();
    }
} else {
    $redirectUrl = "theaters.php?error=" . urlencode("Invalid hall or theater ID provided for deletion.");
}

$conn->close();

header("Location: " . $redirectUrl);
exit();
?>
