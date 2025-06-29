<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Theater Manager (roleID 2)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2)) {
    header("Location: ../admin/index.php"); // Redirect to central admin login
    exit();
}

// Database connection
$host = "dpg-d1gk4s7gi27c73brav8g-a";
$username = "showtime_select_user";
$password = "kbJAnSvfJHodYK7oDCaqaR7OvwlnJQi1";
$database = "showtime_select"; // Ensured to be movie_db
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all data for reports
$revenueByMovieResult = $conn->query("
    SELECT m.movieID, m.movieTitle, COUNT(b.bookingID) as bookingCount, SUM(b.amount) as totalRevenue
    FROM movietable m
    LEFT JOIN bookingtable b ON m.movieID = b.movieID
    GROUP BY m.movieID, m.movieTitle
    ORDER BY totalRevenue DESC
    LIMIT 10
");

$revenueByDateResult = $conn->query("
    SELECT DATE_FORMAT(ms.showDate, '%Y-%m-%d') as showDateFormatted,
           COUNT(b.bookingID) as bookingCount,
           SUM(b.amount) as dailyRevenue
    FROM bookingtable b
    JOIN movie_schedules ms ON b.scheduleID = ms.scheduleID
    GROUP BY showDateFormatted
    ORDER BY showDateFormatted DESC
    LIMIT 30
");

$revenueByTheaterResult = $conn->query("
    SELECT b.bookingTheatre, COUNT(b.bookingID) as bookingCount, SUM(b.amount) as totalRevenue
    FROM bookingtable b
    GROUP BY b.bookingTheatre
    ORDER BY totalRevenue DESC
");

$popularTimesResult = $conn->query("
    SELECT ms.showTime, COUNT(b.bookingID) as bookingCount
    FROM bookingtable b
    JOIN movie_schedules ms ON b.scheduleID = ms.scheduleID
    GROUP BY ms.showTime
    ORDER BY bookingCount DESC
    LIMIT 10
");

// Total revenue and bookings
$totalRevenue = $conn->query("SELECT SUM(amount) as totalRevenue FROM bookingtable")->fetch_assoc()['totalRevenue'] ?? 0;
$totalBookings = $conn->query("SELECT COUNT(*) as totalBookings FROM bookingtable")->fetch_assoc()['totalBookings'] ?? 0;
$avgRevenue = $totalBookings > 0 ? $totalRevenue / $totalBookings : 0;

// Prepare data for Chart.js in PHP variables
$revenueByMovieLabels = [];
$revenueByMovieData = [];
if ($revenueByMovieResult->num_rows > 0) {
    while($row = $revenueByMovieResult->fetch_assoc()) {
        $revenueByMovieLabels[] = $row['movieTitle'];
        $revenueByMovieData[] = $row['totalRevenue'];
    }
}

$revenueByDateLabels = [];
$revenueByDateData = [];
if ($revenueByDateResult->num_rows > 0) {
    while($row = $revenueByDateResult->fetch_assoc()) {
        $revenueByDateLabels[] = $row['showDateFormatted'];
        $revenueByDateData[] = $row['dailyRevenue'];
    }
}

$revenueByTheaterLabels = [];
$revenueByTheaterData = [];
if ($revenueByTheaterResult->num_rows > 0) {
    while($row = $revenueByTheaterResult->fetch_assoc()) {
        $revenueByTheaterLabels[] = ucfirst(str_replace('-', ' ', $row['bookingTheatre']));
        $revenueByTheaterData[] = $row['totalRevenue'];
    }
}

$popularTimesLabels = [];
$popularTimesData = [];
if ($popularTimesResult->num_rows > 0) {
    while($row = $popularTimesResult->fetch_assoc()) {
        $popularTimesLabels[] = date('h:i A', strtotime($row['showTime']));
        $popularTimesData[] = $row['bookingCount'];
    }
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../img/sslogo.jpg">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .report-container {
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
        .stats-card {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-card h3 {
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        .stats-card p {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        .stats-card.primary p {
            color: #007bff;
        }
        .stats-card.success p {
            color: #28a745;
        }
        .stats-card.info p {
            color: #17a2b8;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
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
            .chart-container {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Admin</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="btn btn-signout" href="../admin/logout.php">Sign out</a>
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
                            <a class="nav-link active" href="reports.php"> <!-- This is the reports page itself for TM -->
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
                            <a class="nav-link" href="../admin/reports.php"> <!-- Super Admin's comprehensive reports -->
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
                    <h1>Reports & Analytics</h1>
                    <div class="admin-user-info">
                        <img src="https://placehold.co/40x40/cccccc/333333?text=Admin" alt="Admin">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card primary">
                            <h3>Total Revenue</h3>
                            <p>₹<?php echo number_format($totalRevenue, 2); ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card success">
                            <h3>Total Bookings</h3>
                            <p><?php echo number_format($totalBookings); ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card info">
                            <h3>Avg. Revenue per Booking</h3>
                            <p>₹<?php echo number_format($avgRevenue, 2); ?></p>
                        </div>
                    </div>
                </div>

                <div class="report-container">
                    <h3>Revenue by Movie</h3>
                    <div class="chart-container">
                        <canvas id="revenueByMovieChart"></canvas>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Movie</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($movie = $revenueByMovieResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($movie['movieTitle']); ?></td>
                                        <td><?php echo htmlspecialchars($movie['bookingCount']); ?></td>
                                        <td>₹<?php echo number_format($movie['totalRevenue'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="report-container">
                    <h3>Revenue by Date</h3>
                    <div class="chart-container">
                        <canvas id="revenueByDateChart"></canvas>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="report-container">
                            <h3>Revenue by Theater</h3>
                            <div class="chart-container">
                                <canvas id="revenueByTheaterChart"></canvas>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Theater</th>
                                            <th>Bookings</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($theater = $revenueByTheaterResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $theater['bookingTheatre']))); ?></td>
                                                <td><?php echo htmlspecialchars($theater['bookingCount']); ?></td>
                                                <td>₹<?php echo number_format($theater['totalRevenue'], 2); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="report-container">
                            <h3>Popular Show Times</h3>
                            <div class="chart-container">
                                <canvas id="popularTimesChart"></canvas>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Bookings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($time = $popularTimesResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(date('h:i A', strtotime($time['showTime']))); ?></td>
                                                <td><?php echo htmlspecialchars($time['bookingCount']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Data passed from PHP to JavaScript
        const revenueByMovieLabels = <?php echo json_encode($revenueByMovieLabels); ?>;
        const revenueByMovieData = <?php echo json_encode($revenueByMovieData); ?>;
        const revenueByDateLabels = <?php echo json_encode($revenueByDateLabels); ?>;
        const revenueByDateData = <?php echo json_encode($revenueByDateData); ?>;
        const revenueByTheaterLabels = <?php echo json_encode($revenueByTheaterLabels); ?>;
        const revenueByTheaterData = <?php echo json_encode($revenueByTheaterData); ?>;
        const popularTimesLabels = <?php echo json_encode($popularTimesLabels); ?>;
        const popularTimesData = <?php echo json_encode($popularTimesData); ?>;

        // Revenue by Movie Chart
        const revenueByMovieCtx = document.getElementById('revenueByMovieChart').getContext('2d');
        const revenueByMovieChart = new Chart(revenueByMovieCtx, {
            type: 'bar',
            data: {
                labels: revenueByMovieLabels,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: revenueByMovieData,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₹' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });

        // Revenue by Date Chart
        const revenueByDateCtx = document.getElementById('revenueByDateChart').getContext('2d');
        const revenueByDateChart = new Chart(revenueByDateCtx, {
            type: 'line',
            data: {
                labels: revenueByDateLabels,
                datasets: [{
                    label: 'Daily Revenue (₹)',
                    data: revenueByDateData,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₹' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });

        // Revenue by Theater Chart
        const revenueByTheaterCtx = document.getElementById('revenueByTheaterChart').getContext('2d');
        const revenueByTheaterChart = new Chart(revenueByTheaterCtx, {
            type: 'pie',
            data: {
                labels: revenueByTheaterLabels,
                datasets: [{
                    data: revenueByTheaterData,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(255, 159, 64, 0.5)',
                        'rgba(199, 199, 199, 0.5)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(199, 199, 199, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += '₹' + context.parsed.toFixed(2);
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Popular Times Chart
        const popularTimesCtx = document.getElementById('popularTimesChart').getContext('2d');
        const popularTimesChart = new Chart(popularTimesCtx, {
            type: 'bar',
            data: {
                labels: popularTimesLabels,
                datasets: [{
                    label: 'Number of Bookings',
                    data: popularTimesData,
                    backgroundColor: 'rgba(153, 102, 255, 0.5)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1 // Ensure integer steps for counts
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
