<?php
session_start();

// RBAC: Accessible by all logged-in admins (roleID 1, 2, 3)
// Role IDs: 1=Super Admin, 2=Theater Manager, 3=Content Manager
if (!isset($_SESSION['admin_id'])) {
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

// Get counts for dashboard
$movieCount = $conn->query("SELECT COUNT(*) as count FROM movietable")->fetch_assoc()['count'];
$bookingCount = $conn->query("SELECT COUNT(*) as count FROM bookingtable")->fetch_assoc()['count'];
$userCount = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$theaterCount = $conn->query("SELECT COUNT(*) as count FROM theaters")->fetch_assoc()['count'];
$scheduleCount = $conn->query("SELECT COUNT(*) as count FROM movie_schedules")->fetch_assoc()['count'];

// Get recent bookings (more comprehensive join)
$recentBookingsQuery = "
    SELECT b.bookingID, b.bookingFName, b.bookingLName, b.bookingEmail, b.bookingPNumber, b.seats, b.amount,
           m.movieTitle, m.movieImg,
           ms.showDate, ms.showTime,
           t.theaterName, h.hallName
    FROM bookingtable b
    LEFT JOIN movietable m ON b.movieID = m.movieID
    LEFT JOIN movie_schedules ms ON b.scheduleID = ms.scheduleID
    LEFT JOIN theater_halls h ON b.hallID = h.hallID
    LEFT JOIN theaters t ON h.theaterID = t.theaterID
    ORDER BY b.bookingID DESC LIMIT 5
";
$recentBookings = $conn->query($recentBookingsQuery);

// Get recent movies
$recentMoviesQuery = "
    SELECT m.movieID, m.movieTitle, m.movieGenre, m.movieDuration, m.movieImg, l.locationName
    FROM movietable m
    LEFT JOIN locations l ON m.locationID = l.locationID
    ORDER BY m.movieID DESC LIMIT 5
";
$recentMovies = $conn->query($recentMoviesQuery);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Showtime Select Admin</title>
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
        .dashboard-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }
        .dashboard-card h4 {
            font-size: 1.2rem;
            color: #6c757d;
        }
        .dashboard-card p {
            font-size: 2.5rem;
            font-weight: bold;
            margin-top: 10px;
            color: #343a40;
        }
        .recent-table-container {
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
            margin-bottom: 30px;
        }
        .admin-user-info {
            display: flex;
            align-items: center;
        }
        .admin-user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .admin-user-info span {
            font-weight: bold;
        }
        .btn-signout {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-signout:hover {
            background-color: #c82333;
        }
        .movie-image-mini {
            width: 40px;
            height: 60px;
            object-fit: cover;
            border-radius: 3px;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                position: static;
                height: auto;
                padding: 0;
            }
            .sidebar-sticky {
                height: auto;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .admin-user-info {
                margin-top: 15px;
            }
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Admin</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="btn btn-signout" href="logout.php">Sign out</a>
            </li>
        </ul>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Admin Functions</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
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
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php"> <!-- Corrected: Stays in admin/ for Super Admin's comprehensive reports -->
                                <i class="fas fa-chart-bar"></i>
                                Reports
                            </a>
                        </li>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Theater Management</span>
                        </h6>
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
                            <a class="nav-link" href="../theater_manager/reports.php"> <!-- Theater Manager's specific reports -->
                                <i class="fas fa-chart-bar"></i>
                                Theater Reports
                            </a>
                        </li>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Content Management</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="movies.php">
                                <i class="fas fa-film"></i>
                                Movies
                            </a>
                        </li>
                        
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>System Maintenance</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="process_temp_files.php">
                                <i class="fas fa-sync"></i>
                                Process Temp Files
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../check_upload_permissions.php" target="_blank">
                                <i class="fas fa-check-circle"></i>
                                Check Permissions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../fix_permissions.php" target="_blank">
                                <i class="fas fa-wrench"></i>
                                Fix Permissions
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Dashboard</h1>
                    <div class="admin-user-info">
                        <img src="https://via.placeholder.com/40" alt="Admin">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <h4>Total Movies</h4>
                            <p><?php echo $movieCount; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <h4>Total Bookings</h4>
                            <p><?php echo $bookingCount; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <h4>Total Users</h4>
                            <p><?php echo $userCount; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <h4>Total Theaters</h4>
                            <p><?php echo $theaterCount; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <h4>Total Schedules</h4>
                            <p><?php echo $scheduleCount; ?></p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="recent-table-container">
                            <h3 class="mb-3">Recent Bookings</h3>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Movie</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recentBookings->num_rows > 0): ?>
                                            <?php while ($booking = $recentBookings->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($booking['bookingID']); ?></td>
                                                    <td><?php echo htmlspecialchars($booking['movieTitle'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($booking['bookingFName'] . ' ' . $booking['bookingLName']); ?></td>
                                                    <td><?php echo htmlspecialchars($booking['showDate'] ? date('Y-m-d', strtotime($booking['showDate'])) : 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($booking['showTime'] ? date('H:i', strtotime($booking['showTime'])) : 'N/A'); ?></td>
                                                    <td>₹<?php echo number_format($booking['amount'] ?? 0, 2); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No recent bookings</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="../theater_manager/bookings.php" class="btn btn-primary btn-sm">View All Bookings</a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="recent-table-container">
                            <h3 class="mb-3">Recent Movies</h3>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Image</th>
                                            <th>Title</th>
                                            <th>Genre</th>
                                            <th>Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recentMovies->num_rows > 0): ?>
                                            <?php while ($movie = $recentMovies->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($movie['movieID']); ?></td>
                                                    <td>
                                                        <img src="<?php echo '../' . htmlspecialchars($movie['movieImg']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/40x60/cccccc/333333?text=No+Img';" alt="<?php echo htmlspecialchars($movie['movieTitle']); ?>" class="movie-image-mini">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($movie['movieTitle']); ?></td>
                                                    <td><?php echo htmlspecialchars($movie['movieGenre']); ?></td>
                                                    <td><?php echo htmlspecialchars($movie['movieDuration']); ?> min</td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No movies found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="../content_manager/movies.php" class="btn btn-primary btn-sm">View All Movies</a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
