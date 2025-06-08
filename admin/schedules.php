<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "movie_db";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle schedule deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $scheduleId = $_GET['delete'];
    
    // Check if the schedule exists
    $checkQuery = $conn->prepare("SELECT scheduleID FROM movie_schedules WHERE scheduleID = ?");
    $checkQuery->bind_param("i", $scheduleId);
    $checkQuery->execute();
    $result = $checkQuery->get_result();
    
    if ($result->num_rows > 0) {
        // Check if schedule is used in bookings
        $checkBookingsQuery = $conn->prepare("SELECT COUNT(*) as count FROM bookingtable WHERE scheduleID = ?");
        $checkBookingsQuery->bind_param("i", $scheduleId);
        $checkBookingsQuery->execute();
        $bookingsCount = $checkBookingsQuery->get_result()->fetch_assoc()['count'];
        
        if ($bookingsCount > 0) {
            $errorMessage = "Cannot delete schedule. It is associated with $bookingsCount booking(s).";
        } else {
            // Delete the schedule
            $deleteQuery = $conn->prepare("DELETE FROM movie_schedules WHERE scheduleID = ?");
            $deleteQuery->bind_param("i", $scheduleId);
            
            if ($deleteQuery->execute()) {
                $successMessage = "Schedule deleted successfully!";
            } else {
                $errorMessage = "Error deleting schedule: " . $conn->error;
            }
            
            $deleteQuery->close();
        }
        
        $checkBookingsQuery->close();
    } else {
        $errorMessage = "Schedule not found!";
    }
    
    $checkQuery->close();
}

// Handle schedule addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_schedule'])) {
    $movieId = $_POST['movieId'];
    $hallId = $_POST['hallId'];
    $showDate = $_POST['showDate'];
    $showTime = $_POST['showTime'];
    $price = $_POST['price'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("INSERT INTO movie_schedules (movieID, hallID, showDate, showTime, price, scheduleStatus) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissds", $movieId, $hallId, $showDate, $showTime, $price, $status);
    
    if ($stmt->execute()) {
        $successMessage = "Schedule added successfully!";
    } else {
        $errorMessage = "Error adding schedule: " . $stmt->error;
    }
    
    $stmt->close();
}

// Get all movies for dropdown
$movies = $conn->query("SELECT movieID, movieTitle FROM movietable ORDER BY movieTitle");

// Get all theater halls for dropdown
$halls = $conn->query("
    SELECT h.hallID, h.hallName, h.hallType, t.theaterName 
    FROM theater_halls h
    JOIN theaters t ON h.theaterID = t.theaterID
    WHERE h.hallStatus = 'active'
    ORDER BY t.theaterName, h.hallName
");

// Get all schedules with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$params = [];
$types = '';

if (!empty($search)) {
    $searchCondition = "WHERE m.movieTitle LIKE ? OR t.theaterName LIKE ?";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam];
    $types = "ss";
}

// Count total records for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM movie_schedules ms
    JOIN movietable m ON ms.movieID = m.movieID
    JOIN theater_halls h ON ms.hallID = h.hallID
    JOIN theaters t ON h.theaterID = t.theaterID
    $searchCondition
";

if (!empty($searchCondition)) {
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalRecords = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
} else {
    $totalRecords = $conn->query($countQuery)->fetch_assoc()['total'];
}

$totalPages = ceil($totalRecords / $recordsPerPage);

// Get schedules for current page
$query = "
    SELECT ms.*, m.movieTitle, h.hallName, h.hallType, t.theaterName
    FROM movie_schedules ms
    JOIN movietable m ON ms.movieID = m.movieID
    JOIN theater_halls h ON ms.hallID = h.hallID
    JOIN theaters t ON h.theaterID = t.theaterID
    $searchCondition
    ORDER BY ms.showDate DESC, ms.showTime DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);

if (!empty($searchCondition)) {
    $params[] = $offset;
    $params[] = $recordsPerPage;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $offset, $recordsPerPage);
}

$stmt->execute();
$schedules = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - Showtime Select Admin</title>
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
                <a class="nav-link" href="logout.php">Sign out</a>
            </li>
        </ul>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="movies.php">
                                <i class="fas fa-film"></i>
                                Movies
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
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i>
                                Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Manage Schedules</h1>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addScheduleModal">
                        <i class="fas fa-plus"></i> Add New Schedule
                    </button>
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

                <div class="table-container">
                    <div class="search-box">
                        <form action="" method="GET" class="form-inline">
                            <div class="input-group w-100">
                                <input type="text" name="search" class="form-control" placeholder="Search by movie or theater" value="<?php echo htmlspecialchars($search); ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="schedules.php" class="btn btn-outline-danger">
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
                                <?php if ($schedules->num_rows > 0): ?>
                                    <?php while ($schedule = $schedules->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $schedule['scheduleID']; ?></td>
                                            <td><?php echo $schedule['movieTitle']; ?></td>
                                            <td><?php echo $schedule['theaterName']; ?></td>
                                            <td>
                                                <?php echo $schedule['hallName']; ?> 
                                                <span class="hall-type">(<?php echo str_replace('-', ' ', $schedule['hallType']); ?>)</span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($schedule['showDate'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($schedule['showTime'])); ?></td>
                                            <td>₹<?php echo number_format($schedule['price'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $schedule['scheduleStatus']; ?>">
                                                    <?php echo ucfirst($schedule['scheduleStatus']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="edit_schedule.php?id=<?php echo $schedule['scheduleID']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="schedules.php?delete=<?php echo $schedule['scheduleID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No schedules found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1" role="dialog" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addScheduleModalLabel">Add New Schedule</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="movieId">Movie</label>
                            <select class="form-control" id="movieId" name="movieId" required>
                                <option value="">Select Movie</option>
                                <?php while ($movie = $movies->fetch_assoc()): ?>
                                    <option value="<?php echo $movie['movieID']; ?>">
                                        <?php echo $movie['movieTitle']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="hallId">Theater Hall</label>
                            <select class="form-control" id="hallId" name="hallId" required>
                                <option value="">Select Theater Hall</option>
                                <?php while ($hall = $halls->fetch_assoc()): ?>
                                    <option value="<?php echo $hall['hallID']; ?>">
                                        <?php echo $hall['theaterName'] . ' - ' . $hall['hallName'] . ' (' . str_replace('-', ' ', $hall['hallType']) . ')'; ?>
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>