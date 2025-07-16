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

// Get theater ID from URL
$theaterId = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : 0;
$theaterName = "N/A";
$halls = null; // Initialize halls variable
$errorMessage = '';
$successMessage = '';

// Fetch theater name if theaterId is provided
if ($theaterId > 0) {
    // Corrected column name to lowercase quoted "theatername"
    $stmtTheaterQuery = "SELECT \"theatername\" FROM theaters WHERE \"theaterid\" = $1";
    $stmtTheaterResult = pg_query_params($conn, $stmtTheaterQuery, array($theaterId));
    if ($stmtTheaterResult && pg_num_rows($stmtTheaterResult) > 0) {
        $rowTheater = pg_fetch_assoc($stmtTheaterResult);
        $theaterName = $rowTheater['theatername'];
    } else {
        $errorMessage = "Theater not found for ID: " . $theaterId;
        $theaterId = 0; // Invalidate theaterId if not found
    }
} else {
    // This message is displayed if no theater_id is passed to schedules.php
    $errorMessage = "No theater ID provided. Please select a theater from the Theaters list to manage its schedules.";
}


// Handle schedule deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $scheduleId = $_GET['delete'];
    
    // Check if the schedule exists - using lowercase quoted "scheduleid"
    $checkQuery = "SELECT \"scheduleid\" FROM movie_schedules WHERE \"scheduleid\" = $1";
    $checkResult = pg_query_params($conn, $checkQuery, array($scheduleId));
    
    if ($checkResult && pg_num_rows($checkResult) > 0) {
        // Check if schedule is used in bookings - using lowercase quoted "scheduleid"
        $checkBookingsQuery = "SELECT COUNT(*) as count FROM bookingtable WHERE \"scheduleid\" = $1";
        $checkBookingsResult = pg_query_params($conn, $checkBookingsQuery, array($scheduleId));
        $bookingsCount = pg_fetch_assoc($checkBookingsResult)['count'];
        
        if ($bookingsCount > 0) {
            $errorMessage = "Cannot delete schedule. It is associated with " . $bookingsCount . " booking(s).";
        } else {
            // Delete the schedule - using lowercase quoted "scheduleid"
            $deleteQuery = "DELETE FROM movie_schedules WHERE \"scheduleid\" = $1";
            $deleteResult = pg_query_params($conn, $deleteQuery, array($scheduleId));
            
            if ($deleteResult) {
                $successMessage = "Schedule deleted successfully!";
            } else {
                $errorMessage = "Error deleting schedule: " . pg_last_error($conn);
            }
        }
    } else {
        $errorMessage = "Schedule not found!";
    }
    // Redirect to prevent re-deletion on refresh and to reflect changes
    header("Location: schedules.php?theater_id=" . $theaterId . "&success=" . urlencode($successMessage) . "&error=" . urlencode($errorMessage));
    exit();
}

// Handle schedule addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_schedule'])) {
    $movieId = $_POST['movieId'];
    $hallId = $_POST['hallId'];
    $showDate = $_POST['showDate'];
    $showTime = $_POST['showTime'];
    $price = $_POST['price'];
    $status = $_POST['status'];
    // The theaterId is already available from the GET parameter, no need to get from POST if it's tied to the page context

    // Insert schedule - using lowercase quoted column names
    $insertQuery = "INSERT INTO movie_schedules (\"movieid\", \"hallid\", \"showdate\", \"showtime\", price, \"schedulestatus\") VALUES ($1, $2, $3, $4, $5, $6)";
    $insertResult = pg_query_params($conn, $insertQuery, array($movieId, $hallId, $showDate, $showTime, $price, $status));
    
    if ($insertResult) {
        $successMessage = "Schedule added successfully!";
    } else {
        $errorMessage = "Error adding schedule: " . pg_last_error($conn);
    }
    // Redirect to prevent re-submission on refresh and to reflect changes
    header("Location: schedules.php?theater_id=" . $theaterId . "&success=" . urlencode($successMessage) . "&error=" . urlencode($errorMessage));
    exit();
}

// Handle schedule update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_schedule'])) {
    $scheduleIdToUpdate = $_POST['edit_scheduleId'];
    $movieId = $_POST['edit_movieId'];
    $hallId = $_POST['edit_hallId'];
    $showDate = $_POST['edit_showDate'];
    $showTime = $_POST['edit_showTime'];
    $price = $_POST['edit_price'];
    $status = $_POST['edit_status'];

    // Update schedule - using lowercase quoted column names
    $updateQuery = "UPDATE movie_schedules SET \"movieid\" = $1, \"hallid\" = $2, \"showdate\" = $3, \"showtime\" = $4, price = $5, \"schedulestatus\" = $6 WHERE \"scheduleid\" = $7";
    $updateResult = pg_query_params($conn, $updateQuery, array($movieId, $hallId, $showDate, $showTime, $price, $status, $scheduleIdToUpdate));

    if ($updateResult) {
        $successMessage = "Schedule updated successfully!";
    } else {
        $errorMessage = "Error updating schedule: " . pg_last_error($conn);
    }
    // Redirect to prevent re-submission on refresh and to reflect changes
    header("Location: schedules.php?theater_id=" . $theaterId . "&success=" . urlencode($successMessage) . "&error=" . urlencode($errorMessage));
    exit();
}

// Display messages from redirect
if (isset($_GET['success'])) {
    $successMessage = $_GET['success'];
}
if (isset($_GET['error'])) {
    $errorMessage = $_GET['error'];
}

// Only fetch data if a valid theaterId is present
if ($theaterId > 0) {
    // Get all movies for dropdown
    // No filtering by theater for movies, as a movie can play in any theater
    $moviesQuery = "SELECT \"movieid\", \"movietitle\" FROM movietable ORDER BY \"movietitle\"";
    $movies = pg_query($conn, $moviesQuery);
    if (!$movies) {
        die("Error fetching movies: " . pg_last_error($conn));
    }

    // Get all theater halls for dropdown, filtered by the current theaterId
    // Corrected column names to lowercase quoted
    $hallsQuery = "
        SELECT h.\"hallid\", h.\"hallname\", h.\"halltype\", t.\"theatername\" 
        FROM theater_halls h
        JOIN theaters t ON h.\"theaterid\" = t.\"theaterid\"
        WHERE h.\"hallstatus\" = 'active' AND h.\"theaterid\" = $1
        ORDER BY t.\"theatername\", h.\"hallname\"
    ";
    $halls = pg_query_params($conn, $hallsQuery, array($theaterId));
    if (!$halls) {
        die("Error fetching halls: " . pg_last_error($conn));
    }

    // Get all schedules with pagination, filtered by the current theaterId
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $recordsPerPage = 10;
    $offset = ($page - 1) * $recordsPerPage;

    // Search functionality
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $searchCondition = '';
    $params = [$theaterId]; // First parameter is always theaterId
    $param_index = 2; // Start index for additional search params

    if (!empty($search)) {
        $searchParam = "%" . $search . "%";
        $searchCondition = "AND (m.\"movietitle\" ILIKE $" . ($param_index++) . " OR t.\"theatername\" ILIKE $" . ($param_index++) . ")";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Count total records for pagination - using lowercase quoted column names
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM movie_schedules ms
        JOIN movietable m ON ms.\"movieid\" = m.\"movieid\"
        JOIN theater_halls h ON ms.\"hallid\" = h.\"hallid\"
        JOIN theaters t ON h.\"theaterid\" = t.\"theaterid\"
        WHERE t.\"theaterid\" = $1
        " . $searchCondition;

    $stmtCountResult = pg_query_params($conn, $countQuery, $params);
    if (!$stmtCountResult) {
        die("Error counting schedules: " . pg_last_error($conn));
    }
    $totalRecords = pg_fetch_assoc($stmtCountResult)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Get schedules for current page - using lowercase quoted column names
    $query = "
        SELECT ms.*, m.\"movietitle\", h.\"hallname\", h.\"halltype\", t.\"theatername\"
        FROM movie_schedules ms
        JOIN movietable m ON ms.\"movieid\" = m.\"movieid\"
        JOIN theater_halls h ON ms.\"hallid\" = h.\"hallid\"
        JOIN theaters t ON h.\"theaterid\" = t.\"theaterid\"
        WHERE t.\"theaterid\" = $1
        " . $searchCondition . "
        ORDER BY ms.\"showdate\" DESC, ms.\"showtime\" DESC
        LIMIT $" . ($param_index++) . " OFFSET $" . ($param_index++) . "";

    $query_params = array_merge($params, [$recordsPerPage, $offset]);
    $schedules = pg_query_params($conn, $query, $query_params);

    if (!$schedules) {
        die("Error fetching schedules: " . pg_last_error($conn));
    }
} else {
    // If theaterId is 0 or invalid, ensure schedules and halls are null
    $schedules = null;
    $halls = null;
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules for <?php echo htmlspecialchars($theaterName); ?> - Showtime Select Admin</title>
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
        .table-container {
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
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-completed {
            background-color: #e2e3e5;
            color: #383d41;
        }
        .hall-type {
            text-transform: capitalize;
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
                    <h1>Manage Schedules for <?php echo htmlspecialchars($theaterName); ?></h1>
                    <div class="d-flex align-items-center">
                        <a href="theaters.php" class="btn btn-secondary mr-2">
                            <i class="fas fa-arrow-left"></i> Back to Theaters
                        </a>
                        <?php if ($theaterId > 0): // Only show "Add New Schedule" if a valid theater is selected ?>
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addScheduleModal">
                                <i class="fas fa-plus"></i> Add New Schedule
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($successMessage) && !empty($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errorMessage) && !empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($theaterId == 0): // Display message if no theater is selected ?>
                    <div class="alert alert-info text-center">
                        Please select a theater from the <a href="theaters.php">Theaters list</a> to manage its schedules.
                    </div>
                <?php elseif ($schedules && pg_num_rows($schedules) > 0): ?>
                    <div class="table-container">
                        <div class="search-box">
                            <form action="" method="GET" class="form-inline">
                                <input type="hidden" name="theater_id" value="<?php echo htmlspecialchars($theaterId); ?>">
                                <div class="input-group w-100">
                                    <input type="text" name="search" class="form-control" placeholder="Search by movie or theater" value="<?php echo htmlspecialchars($search); ?>">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search)): ?>
                                            <a href="schedules.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>" class="btn btn-outline-danger">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Movie</th>
                                        <th>Theater</th>
                                        <th>Hall</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($schedule = pg_fetch_assoc($schedules)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($schedule['scheduleid']); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['movietitle']); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['theatername']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($schedule['hallname']); ?> 
                                                <span class="hall-type">(<?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($schedule['halltype']))); ?>)</span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($schedule['showdate']))); ?></td>
                                            <td><?php echo htmlspecialchars(date('h:i A', strtotime($schedule['showtime']))); ?></td>
                                            <td>₹<?php echo number_format($schedule['price'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars($schedule['schedulestatus']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($schedule['schedulestatus'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning edit-schedule"
                                                        data-id="<?php echo htmlspecialchars($schedule['scheduleid']); ?>"
                                                        data-movieid="<?php echo htmlspecialchars($schedule['movieid']); ?>"
                                                        data-hallid="<?php echo htmlspecialchars($schedule['hallid']); ?>"
                                                        data-showdate="<?php echo htmlspecialchars($schedule['showdate']); ?>"
                                                        data-showtime="<?php echo htmlspecialchars($schedule['showtime']); ?>"
                                                        data-price="<?php echo htmlspecialchars($schedule['price']); ?>"
                                                        data-status="<?php echo htmlspecialchars($schedule['schedulestatus']); ?>"
                                                        data-toggle="modal" data-target="#editScheduleModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="schedules.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>&delete=<?php echo htmlspecialchars($schedule['scheduleid']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this schedule? This will also delete associated bookings!')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?theater_id=<?php echo htmlspecialchars($theaterId); ?>&page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?theater_id=<?php echo htmlspecialchars($theaterId); ?>&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?theater_id=<?php echo htmlspecialchars($theaterId); ?>&page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        No schedules found for <?php echo htmlspecialchars($theaterName); ?>. Click "Add New Schedule" to get started.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1" role="dialog" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addScheduleModalLabel">Add New Schedule for <?php echo htmlspecialchars($theaterName); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="schedules.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="movieId">Movie</label>
                            <select class="form-control" id="movieId" name="movieId" required>
                                <option value="">Select Movie</option>
                                <?php
                                // Reset movie results pointer if already fetched
                                if ($movies && pg_num_rows($movies) > 0) {
                                    pg_result_seek($movies, 0);
                                }
                                while ($movie = pg_fetch_assoc($movies)): ?>
                                    <option value="<?php echo htmlspecialchars($movie['movieid']); ?>">
                                        <?php echo htmlspecialchars($movie['movietitle']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="hallId">Theater Hall</label>
                            <select class="form-control" id="hallId" name="hallId" required>
                                <option value="">Select Theater Hall</option>
                                <?php
                                // Reset hall results pointer if already fetched
                                if ($halls && pg_num_rows($halls) > 0) {
                                    pg_result_seek($halls, 0);
                                }
                                while ($hall = pg_fetch_assoc($halls)): ?>
                                    <option value="<?php echo htmlspecialchars($hall['hallid']); ?>">
                                        <?php echo htmlspecialchars($hall['hallname'] . ' (' . str_replace('-', ' ', $hall['halltype']) . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="showDate">Show Date</label>
                            <input type="date" class="form-control" id="showDate" name="showDate" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="showTime">Show Time</label>
                            <input type="time" class="form-control" id="showTime" name="showTime" required>
                        </div>
                        <div class="form-group">
                            <label for="price">Ticket Price (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" required min="0">
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="add_schedule" class="btn btn-primary">Add Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1" role="dialog" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editScheduleModalLabel">Edit Schedule for <?php echo htmlspecialchars($theaterName); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="schedules.php?theater_id=<?php echo htmlspecialchars($theaterId); ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_scheduleId" name="edit_scheduleId">
                        <div class="form-group">
                            <label for="edit_movieId">Movie</label>
                            <select class="form-control" id="edit_movieId" name="edit_movieId" required>
                                <option value="">Select Movie</option>
                                <?php
                                // Reset movie results pointer for edit modal
                                if ($movies && pg_num_rows($movies) > 0) {
                                    pg_result_seek($movies, 0);
                                }
                                while ($movie = pg_fetch_assoc($movies)): ?>
                                    <option value="<?php echo htmlspecialchars($movie['movieid']); ?>">
                                        <?php echo htmlspecialchars($movie['movietitle']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_hallId">Theater Hall</label>
                            <select class="form-control" id="edit_hallId" name="edit_hallId" required>
                                <option value="">Select Theater Hall</option>
                                <?php
                                // Reset hall results pointer for edit modal
                                if ($halls && pg_num_rows($halls) > 0) {
                                    pg_result_seek($halls, 0);
                                }
                                while ($hall = pg_fetch_assoc($halls)): ?>
                                    <option value="<?php echo htmlspecialchars($hall['hallid']); ?>">
                                        <?php echo htmlspecialchars($hall['hallname'] . ' (' . str_replace('-', ' ', $hall['halltype']) . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_showDate">Show Date</label>
                            <input type="date" class="form-control" id="edit_showDate" name="edit_showDate" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_showTime">Show Time</label>
                            <input type="time" class="form-control" id="edit_showTime" name="edit_showTime" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_price">Ticket Price (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_price" name="edit_price" required min="0">
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status</label>
                            <select class="form-control" id="edit_status" name="edit_status" required>
                                <option value="active">Active</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="update_schedule" class="btn btn-primary">Update Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Fill edit modal with schedule data
        $('.edit-schedule').click(function() {
            var id = $(this).data('id');
            var movieId = $(this).data('movieid');
            var hallId = $(this).data('hallid');
            var showDate = $(this).data('showdate');
            var showTime = $(this).data('showtime');
            var price = $(this).data('price');
            var status = $(this).data('status');

            $('#edit_scheduleId').val(id);
            $('#edit_movieId').val(movieId);
            $('#edit_hallId').val(hallId);
            $('#edit_showDate').val(showDate);
            $('#edit_showTime').val(showTime);
            $('#edit_price').val(price);
            $('#edit_status').val(status);
        });
    </script>
</body>
</html>
