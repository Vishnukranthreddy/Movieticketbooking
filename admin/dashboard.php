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

// Get counts for dashboard
$movieCount = $conn->query("SELECT COUNT(*) as count FROM movietable")->fetch_assoc()['count'];
$bookingCount = $conn->query("SELECT COUNT(*) as count FROM bookingtable")->fetch_assoc()['count'];
$userCount = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$theaterCount = $conn->query("SELECT COUNT(*) as count FROM theaters")->fetch_assoc()['count'];

// Get recent bookings
$recentBookings = $conn->query("
    SELECT b.*, m.movieTitle, u.name as userName 
    FROM bookingtable b
    LEFT JOIN movietable m ON b.movieID = m.movieID
    LEFT JOIN users u ON b.bookingEmail = u.username
    ORDER BY bookingID DESC LIMIT 5
");

// Get recent movies
$recentMovies = $conn->query("
    SELECT * FROM movietable
    ORDER BY movieID DESC LIMIT 5
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Showtime Select</title>
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
        .card-counter {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            padding: 20px 10px;
            background-color: #fff;
            height: 100px;
            border-radius: 5px;
            transition: .3s linear all;
        }
        .card-counter:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            transition: .3s linear all;
        }
        .card-counter i {
            font-size: 4em;
            opacity: 0.5;
        }
        .card-counter .count-numbers {
            position: absolute;
            right: 35px;
            top: 20px;
            font-size: 32px;
            display: block;
        }
        .card-counter .count-name {
            position: absolute;
            right: 35px;
            top: 65px;
            font-style: italic;
            text-transform: capitalize;
            opacity: 0.7;
            display: block;
            font-size: 18px;
        }
        .card-counter.primary {
            background-color: #007bff;
            color: #FFF;
        }
        .card-counter.danger {
            background-color: #ef5350;
            color: #FFF;
        }
        .card-counter.success {
            background-color: #66bb6a;
            color: #FFF;
        }
        .card-counter.info {
            background-color: #26c6da;
            color: #FFF;
        }
        .table-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .table-container h4 {
            margin-bottom: 20px;
            color: #343a40;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Admin</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="btn btn-danger" href="logout.php">Sign out</a>
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
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i>
                                Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Reports
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
                    <h1>Dashboard</h1>
                    <div class="admin-user-info">
                        <img src="https://via.placeholder.com/40" alt="Admin">
                        <span>Welcome, <?php echo $_SESSION['admin_name']; ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="card-counter primary">
                            <i class="fas fa-film"></i>
                            <span class="count-numbers"><?php echo $movieCount; ?></span>
                            <span class="count-name">Movies</span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card-counter danger">
                            <i class="fas fa-ticket-alt"></i>
                            <span class="count-numbers"><?php echo $bookingCount; ?></span>
                            <span class="count-name">Bookings</span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card-counter success">
                            <i class="fas fa-users"></i>
                            <span class="count-numbers"><?php echo $userCount; ?></span>
                            <span class="count-name">Users</span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card-counter info">
                            <i class="fas fa-building"></i>
                            <span class="count-numbers"><?php echo $theaterCount; ?></span>
                            <span class="count-name">Theaters</span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="table-container">
                            <h4>Recent Bookings</h4>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Movie</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentBookings->num_rows > 0): ?>
                                        <?php while ($booking = $recentBookings->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $booking['bookingID']; ?></td>
                                                <td><?php echo $booking['movieTitle']; ?></td>
                                                <td><?php echo $booking['bookingFName'] . ' ' . $booking['bookingLName']; ?></td>
                                                <td><?php echo $booking['bookingDate']; ?></td>
                                                <td>
                                                    <a href="view_booking.php?id=<?php echo $booking['bookingID']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No bookings found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <a href="bookings.php" class="btn btn-primary btn-sm">View All Bookings</a>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="table-container">
                            <h4>Recent Movies</h4>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Genre</th>
                                        <th>Duration</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentMovies->num_rows > 0): ?>
                                        <?php while ($movie = $recentMovies->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $movie['movieID']; ?></td>
                                                <td><?php echo $movie['movieTitle']; ?></td>
                                                <td><?php echo $movie['movieGenre']; ?></td>
                                                <td><?php echo $movie['movieDuration']; ?> min</td>
                                                <td>
                                                    <a href="edit_movie.php?id=<?php echo $movie['movieID']; ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No movies found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <a href="movies.php" class="btn btn-primary btn-sm">View All Movies</a>
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