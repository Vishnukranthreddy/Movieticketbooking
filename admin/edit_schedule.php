<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Theater Manager (roleID 2)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2)) {
    header("Location: ../admin/index.php"); // Redirect to central admin login
    exit();
}

// Check if schedule ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: schedules.php"); // Redirect back if no valid ID
    exit();
}

$scheduleId = $_GET['id'];

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

$schedule = null;
$errorMessage = '';
$successMessage = '';

// Fetch schedule data
$stmtQuery = "
    SELECT ms.*, m.\"movieTitle\", h.\"hallName\", h.\"hallType\", t.\"theaterName\", t.\"theaterID\"
    FROM movie_schedules ms
    JOIN movietable m ON ms.\"movieID\" = m.\"movieID\"
    JOIN theater_halls h ON ms.\"hallID\" = h.\"hallID\"
    JOIN theaters t ON h.\"theaterID\" = t.\"theaterID\"
    WHERE ms.\"scheduleID\" = $1
";
$stmtResult = pg_query_params($conn, $stmtQuery, array($scheduleId));
if ($stmtResult && pg_num_rows($stmtResult) > 0) {
    $schedule = pg_fetch_assoc($stmtResult);
    // Convert keys to lowercase for consistency with PostgreSQL's default behavior
    $schedule = array_change_key_case($schedule, CASE_LOWER);
} else {
    $errorMessage = "Schedule not found.";
}

// Get all movies for dropdown
$moviesQuery = "SELECT \"movieID\", \"movieTitle\" FROM movietable ORDER BY \"movieTitle\"";
$movies = pg_query($conn, $moviesQuery);
if (!$movies) {
    die("Error fetching movies: " . pg_last_error($conn));
}

// Get all theater halls for dropdown
$hallsQuery = "
    SELECT h.\"hallID\", h.\"hallName\", h.\"hallType\", t.\"theaterName\" 
    FROM theater_halls h
    JOIN theaters t ON h.\"theaterID\" = t.\"theaterID\"
    WHERE h.\"hallStatus\" = 'active'
    ORDER BY t.\"theaterName\", h.\"hallName\"
";
$halls = pg_query($conn, $hallsQuery);
if (!$halls) {
    die("Error fetching halls: " . pg_last_error($conn));
}

// Process form submission for update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_schedule'])) {
    // Ensure schedule was found before attempting update
    if (!$schedule) {
        $errorMessage = "Cannot update: Schedule not found.";
    } else {
        $movieId = $_POST['movieId'];
        $hallId = $_POST['hallId'];
        $showDate = $_POST['showDate'];
        $showTime = $_POST['showTime'];
        $price = $_POST['price'];
        $status = $_POST['status'];

        $updateQuery = "UPDATE movie_schedules SET \"movieID\" = $1, \"hallID\" = $2, \"showDate\" = $3, \"showTime\" = $4, price = $5, \"scheduleStatus\" = $6 WHERE \"scheduleID\" = $7";
        $updateResult = pg_query_params($conn, $updateQuery, array($movieId, $hallId, $showDate, $showTime, $price, $status, $scheduleId));
        
        if ($updateResult) {
            $successMessage = "Schedule updated successfully!";
            // Re-fetch schedule data to display updated info immediately
            $stmtQuery = "
                SELECT ms.*, m.\"movieTitle\", h.\"hallName\", h.\"hallType\", t.\"theaterName\", t.\"theaterID\"
                FROM movie_schedules ms
                JOIN movietable m ON ms.\"movieID\" = m.\"movieID\"
                JOIN theater_halls h ON ms.\"hallID\" = h.\"hallID\"
                JOIN theaters t ON h.\"theaterID\" = t.\"theaterID\"
                WHERE ms.\"scheduleID\" = $1
            ";
            $stmtResult = pg_query_params($conn, $stmtQuery, array($scheduleId));
            $schedule = pg_fetch_assoc($stmtResult);
            $schedule = array_change_key_case($schedule, CASE_LOWER);
        } else {
            $errorMessage = "Error updating schedule: " . pg_last_error($conn);
        }
    }
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../img/sslogo.jpg"> <!-- Path adjusted -->
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
                <a class="nav-link" href="../admin/logout.php">Sign out</a> <!-- Corrected path -->
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
                            <a class="nav-link active" href="schedules.php">
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
                        <?php if ($_SESSION['admin_role'] == 1): // Only Super Admin sees these links in Theater Manager sidebar ?>
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
                    <h1>Edit Schedule ID: <?php echo htmlspecialchars($schedule['scheduleid'] ?? 'N/A'); ?></h1>
                    <a href="schedules.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Schedules
                    </a>
                </div>

                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($schedule): ?>
                    <div class="form-container">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="movieId">Movie</label>
                                <select class="form-control" id="movieId" name="movieId" required>
                                    <?php
                                    // Make sure $movies is a valid result set before data_seek
                                    if (pg_num_rows($movies) > 0) {
                                        pg_result_seek($movies, 0);
                                    }
                                    while ($movie = pg_fetch_assoc($movies)): ?>
                                        <option value="<?php echo htmlspecialchars($movie['movieID']); ?>" <?php echo ($schedule['movieid'] == $movie['movieID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($movie['movieTitle']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="hallId">Theater Hall</label>
                                <select class="form-control" id="hallId" name="hallId" required>
                                    <?php
                                    // Make sure $halls is a valid result set before data_seek
                                    if (pg_num_rows($halls) > 0) {
                                        pg_result_seek($halls, 0);
                                    }
                                    while ($hall = pg_fetch_assoc($halls)): ?>
                                        <option value="<?php echo htmlspecialchars($hall['hallID']); ?>" <?php echo ($schedule['hallid'] == $hall['hallID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hall['theaterName'] . ' - ' . $hall['hallName'] . ' (' . str_replace('-', ' ', $hall['hallType']) . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="showDate">Show Date</label>
                                <input type="date" class="form-control" id="showDate" name="showDate" value="<?php echo htmlspecialchars($schedule['showdate']); ?>" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="showTime">Show Time</label>
                                <input type="time" class="form-control" id="showTime" name="showTime" value="<?php echo htmlspecialchars($schedule['showtime']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="price">Ticket Price (₹)</label>
                                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($schedule['price']); ?>" required min="0">
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="active" <?php echo ($schedule['schedulestatus'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="cancelled" <?php echo ($schedule['schedulestatus'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo ($schedule['schedulestatus'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            
                            <div class="form-group text-center mt-4">
                                <button type="submit" name="update_schedule" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Update Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="text-center text-danger">Schedule details could not be loaded.</p>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
