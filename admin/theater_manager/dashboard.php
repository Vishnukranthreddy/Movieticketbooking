<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php"); // Redirect to main admin login
    exit();
}

// Ensure user has Theater Manager role (roleID = 2)
if ($_SESSION['admin_role'] != 2) {
    header("Location: ../dashboard.php"); // Redirect to main admin dashboard or access denied page
    exit();
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "movie_db"; // Ensure consistent database
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get counts for dashboard relevant to Theater Manager
$theaterCount = $conn->query("SELECT COUNT(*) as count FROM theaters")->fetch_assoc()['count'];
$activeSchedulesCount = $conn->query("SELECT COUNT(*) as count FROM movie_schedules WHERE scheduleStatus = 'active'")->fetch_assoc()['count'];
$upcomingMoviesCount = $conn->query("SELECT COUNT(*) as count FROM movietable WHERE movieRelDate >= CURDATE()")->fetch_assoc()['count'];

// Get recent schedules
$recentSchedules = $conn->query("
    SELECT ms.*, m.movieTitle, h.hallName, t.theaterName
    FROM movie_schedules ms
    JOIN movietable m ON ms.movieID = m.movieID
    JOIN theater_halls h ON ms.hallID = h.hallID
    JOIN theaters t ON h.theaterID = t.theaterID
    WHERE ms.showDate >= CURDATE()
    ORDER BY ms.showDate ASC, ms.showTime ASC LIMIT 5
");

// Get recent theaters added
$recentTheaters = $conn->query("
    SELECT * FROM theaters
    ORDER BY theaterID DESC LIMIT 5
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theater Manager Dashboard - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../../img/sslogo.jpg"> <!-- Path adjusted for theater_manager folder -->
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
        .summary-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }
        .summary-card h4 {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .summary-card .count {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .summary-card.theaters .count { color: #6f42c1; }
        .summary-card.schedules .count { color: #fd7e14; }
        .summary-card.movies .count { color: #28a745; }

        .dashboard-section {
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
        .table-responsive {
            margin-top: 15px;
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
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Theater Manager</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="btn btn-signout" href="../logout.php">Sign out</a>
            </li>
        </ul>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
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
                            <a class="nav-link" href="schedules.php">
                                <i class="fas fa-calendar-alt"></i>
                                Schedules
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content">
                <div class="admin-header">
                    <h1>Theater Manager Dashboard</h1>
                    <div class="admin-user-info">
                        <img src="https://via.placeholder.com/40" alt="Admin Avatar">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Theater Manager'); ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="summary-card theaters">
                            <h4>Total Theaters Managed</h4>
                            <div class="count"><?php echo $theaterCount; ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card schedules">
                            <h4>Active Schedules</h4>
                            <div class="count"><?php echo $activeSchedulesCount; ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card movies">
                            <h4>Upcoming Movies</h4>
                            <div class="count"><?php echo $upcomingMoviesCount; ?></div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-section">
                    <h3>Recent Schedules</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Schedule ID</th>
                                    <th>Movie</th>
                                    <th>Theater</th>
                                    <th>Hall</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentSchedules->num_rows > 0): ?>
                                    <?php while ($schedule = $recentSchedules->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $schedule['scheduleID']; ?></td>
                                            <td><?php echo $schedule['movieTitle']; ?></td>
                                            <td><?php echo $schedule['theaterName']; ?></td>
                                            <td><?php echo $schedule['hallName']; ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($schedule['showDate'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($schedule['showTime'])); ?></td>
                                            <td>
                                                <a href="edit_schedule.php?id=<?php echo $schedule['scheduleID']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No recent schedules</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="schedules.php" class="btn btn-primary btn-sm mt-3">View All Schedules</a>
                </div>

                <div class="dashboard-section">
                    <h3>Recent Theaters Added</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>City</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentTheaters->num_rows > 0): ?>
                                    <?php while ($theater = $recentTheaters->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $theater['theaterID']; ?></td>
                                            <td><?php echo $theater['theaterName']; ?></td>
                                            <td><?php echo $theater['theaterCity']; ?></td>
                                            <td><span class="badge badge-<?php echo $theater['theaterStatus'] == 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($theater['theaterStatus']); ?></span></td>
                                            <td>
                                                <a href="edit_theater.php?id=<?php echo $theater['theaterID']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No recent theaters</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <a href="theaters.php" class="btn btn-primary btn-sm mt-3">View All Theaters</a>
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
