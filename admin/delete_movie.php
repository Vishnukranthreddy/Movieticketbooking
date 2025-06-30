<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Content Manager (roleID 3)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 3)) {
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

$movieId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirectUrl = "movies.php"; // Default redirect location

if ($movieId > 0) {
    // Before deleting movie, check for dependent records in movie_schedules
    // IMPORTANT: Foreign key constraints should ideally handle cascading deletes
    // if configured with ON DELETE CASCADE. If not, you must delete child records first.
    // Our combined SQL does have ON DELETE CASCADE for movie_schedules.movieID.
    
    // Optional: Get movie image path before deletion to remove the file
    $stmtImg = $conn->prepare("SELECT movieImg FROM movietable WHERE movieID = ?");
    $stmtImg->bind_param("i", $movieId);
    $stmtImg->execute();
    $resultImg = $stmtImg->get_result();
    $movieImgPath = null;
    if ($row = $resultImg->fetch_assoc()) {
        $movieImgPath = $row['movieImg'];
    }
    $stmtImg->close();

    // Delete the movie
    $deleteQuery = $conn->prepare("DELETE FROM movietable WHERE movieID = ?");
    $deleteQuery->bind_param("i", $movieId);
    
    if ($deleteQuery->execute()) {
        // If ON DELETE CASCADE is set up for movie_schedules,
        // then associated schedules and their related bookings (if FK from bookings to schedules)
        // should also be deleted automatically.
        
        // Delete the associated image file if it exists
        if ($movieImgPath && file_exists("../" . $movieImgPath)) {
            unlink("../" . $movieImgPath);
        }
        
        $redirectUrl .= "?success=Movie deleted successfully!";
    } else {
        $redirectUrl .= "?error=Error deleting movie: " . urlencode($conn->error);
    }
    $deleteQuery->close();
} else {
    $redirectUrl .= "?error=Invalid movie ID provided for deletion.";
}

$conn->close();

header("Location: " . $redirectUrl);
exit();
?>
