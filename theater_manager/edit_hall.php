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
$theaterId = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : 0; // Get theater_id to ensure correct context and redirect
$hall = null;
$theaterName = "N/A";
$errorMessage = '';
$successMessage = '';

if ($hallId > 0 && $theaterId > 0) {
    // Fetch theater name for display
    $stmtTheater = $conn->prepare("SELECT theaterName FROM theaters WHERE theaterID = ?");
    $stmtTheater->bind_param("i", $theaterId);
    $stmtTheater->execute();
    $resultTheater = $stmtTheater->get_result();
    if ($rowTheater = $resultTheater->fetch_assoc()) {
        $theaterName = $rowTheater['theaterName'];
    } else {
        $errorMessage = "Associated Theater not found.";
        $theaterId = 0; // Invalidate theaterId if not found
    }
    $stmtTheater->close();

    if ($theaterId > 0) { // Only proceed if theater is valid
        // Fetch current hall details
        $stmt = $conn->prepare("SELECT * FROM theater_halls WHERE hallID = ? AND theaterID = ?");
        $stmt->bind_param("ii", $hallId, $theaterId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $hall = $result->fetch_assoc();
        } else {
            $errorMessage = "Hall not found for this theater.";
        }
        $stmt->close();
    }
} else {
    $errorMessage = "Invalid hall or theater ID provided.";
}

// Process form submission for update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_hall'])) {
    if (!$hall) { // If hall wasn't found initially, don't proceed
        $errorMessage = "Cannot update: Hall not found or not associated with the correct theater.";
    } else {
        $hallName = $_POST['hallName'];
        $hallType = $_POST['hallType'];
        $totalSeats = $_POST['totalSeats'];
        $hallStatus = $_POST['hallStatus'];

        $updateStmt = $conn->prepare("UPDATE theater_halls SET hallName = ?, hallType = ?, totalSeats = ?, hallStatus = ? WHERE hallID = ? AND theaterID = ?");
        $updateStmt->bind_param("ssisii", $hallName, $hallType, $totalSeats, $hallStatus, $hallId, $theaterId);

        if ($updateStmt->execute()) {
            $successMessage = "Hall updated successfully!";
            // Refresh hall data after update
            $stmt = $conn->prepare("SELECT * FROM theater_halls WHERE hallID = ? AND theaterID = ?");
            $stmt->bind_param("ii", $hallId, $theaterId);
            $stmt->execute();
            $result = $stmt->get_result();
            $hall = $result->fetch_assoc(); // Update $hall variable with new data
            $stmt->close();
        } else {
            $errorMessage = "Error updating hall: " . $updateStmt->error;
        }
        $updateStmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Hall for <?php echo htmlspecialchars($theaterName); ?> - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../img/sslogo.jpg">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
            color: #fff;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #ced4da;
            padding: 10px 20px;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
        .navbar .navbar-toggler {
            top: .25rem;
            right: 1rem;
        }
        .navbar .form-control {
            padding: .75rem 1rem;
            border-width: 0;
            border-radius: 0;
        }
        .form-control-dark {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
            border-color: rgba(255, 255, 255, .1);
        }
        .form-control-dark:focus {
            border-color: transparent;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, .25);
        }
        .main-content {
            margin-left: 240px;
            padding: 20px;
        }
        .form-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Admin</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="nav-link" href="../admin/logout.php">Sign out</a>
            </li>
        </ul>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Theater Management</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="theaters.php">
                                <i class="fas fa-building"></i>
                                Theaters
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="locations.php">
                                <i class="fas fa-map-marker-alt"></i>
                                Locations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="schedules.php">
                                <i class="fas fa-calendar-alt"></i>
                                Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="fas fa-ticket-alt"></i>
                                Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Reports
                            </a>
                        </li>
                        <?php if ($_SESSION['admin_role'] == 1): ?>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Admin Functions (Super Admin)</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/dashboard.php">
                                <i class="fas fa-home"></i>
                                Super Admin Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/users.php">
                                <i class="fas fa-users"></i>
                                Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/settings.php">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/reports.php">
                                <i class="fas fa-chart-bar"></i>
                                All Reports
                            </a>
                        </li>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Content Management (Super Admin)</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="../content_manager/movies.php">
                                <i class="fas fa-film"></i>
                                Movies
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Edit Hall for <?php echo htmlspecialchars($theaterName); ?></h1>
                    <a href="theater_halls.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Halls
                    </a>
                </div>

                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($hall): ?>
                    <div class="form-container">
                        <form action="" method="POST">
                            <input type="hidden" name="hallId" value="<?php echo htmlspecialchars($hall['hallID']); ?>">
                            
                            <div class="form-group">
                                <label for="hallName">Hall Name</label>
                                <input type="text" class="form-control" id="hallName" name="hallName" value="<?php echo htmlspecialchars($hall['hallName']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="hallType">Hall Type</label>
                                <select class="form-control" id="hallType" name="hallType" required>
                                    <option value="main-hall" <?php echo ($hall['hallType'] == 'main-hall') ? 'selected' : ''; ?>>Main Hall</option>
                                    <option value="vip-hall" <?php echo ($hall['hallType'] == 'vip-hall') ? 'selected' : ''; ?>>VIP Hall</option>
                                    <option value="private-hall" <?php echo ($hall['hallType'] == 'private-hall') ? 'selected' : ''; ?>>Private Hall</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="totalSeats">Total Seats</label>
                                <input type="number" class="form-control" id="totalSeats" name="totalSeats" value="<?php echo htmlspecialchars($hall['totalSeats']); ?>" required min="1">
                            </div>
                            
                            <div class="form-group">
                                <label for="hallStatus">Status</label>
                                <select class="form-control" id="hallStatus" name="hallStatus" required>
                                    <option value="active" <?php echo ($hall['hallStatus'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($hall['hallStatus'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="form-group text-center mt-4">
                                <button type="submit" name="update_hall" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Update Hall
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
