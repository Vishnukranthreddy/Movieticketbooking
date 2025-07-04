<?php
session_start();

// RBAC: Accessible by Super Admin (roleID 1) and Theater Manager (roleID 2)
if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] != 1 && $_SESSION['admin_role'] != 2)) {
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

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$params = [];
$types = '';

if (!empty($search)) {
    // Search across customer names, email, movie title, and theater name
    $searchParam = "%" . $search . "%";
    $searchCondition = " WHERE b.bookingFName LIKE ? OR b.bookingLName LIKE ? OR b.bookingEmail LIKE ? OR m.movieTitle LIKE ? OR t.theaterName LIKE ?";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    $types = "sssss";
}

// Get total bookings count
$totalQuery = "
    SELECT COUNT(*) as total
    FROM bookingtable b
    LEFT JOIN movietable m ON b.movieID = m.movieID
    LEFT JOIN movie_schedules ms ON b.scheduleID = ms.scheduleID
    LEFT JOIN theater_halls h ON b.hallID = h.hallID
    LEFT JOIN theaters t ON h.theaterID = t.theaterID
    " . $searchCondition;

$stmtCount = $conn->prepare($totalQuery);
if (!empty($searchCondition)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$totalResult = $stmtCount->get_result();
$totalRow = $totalResult->fetch_assoc();
$total = $totalRow['total'];
$pages = ceil($total / $limit);
$stmtCount->close();

// Get bookings with pagination
$bookingsQuery = "
    SELECT b.*, m.movieTitle, m.movieGenre, m.movieDuration,
           ms.showDate, ms.showTime, ms.price as scheduledPrice,
           h.hallName, h.hallType,
           t.theaterName
    FROM bookingtable b
    LEFT JOIN movietable m ON b.movieID = m.movieID
    LEFT JOIN movie_schedules ms ON b.scheduleID = ms.scheduleID
    LEFT JOIN theater_halls h ON b.hallID = h.hallID
    LEFT JOIN theaters t ON h.theaterID = t.theaterID
    " . $searchCondition . "
    ORDER BY b.bookingID DESC
    LIMIT ?, ?";

$stmtBookings = $conn->prepare($bookingsQuery);
// Create a new array for parameters for the main query as bind_param needs references
$query_params = $params;
$query_types = $types;

$query_params[] = $start;
$query_params[] = $limit;
$query_types .= "ii"; // Add integer types for limit and offset

if (!empty($searchCondition)) {
    $stmtBookings->bind_param($query_types, ...$query_params);
} else {
    $stmtBookings->bind_param("ii", $start, $limit);
}
$stmtBookings->execute();
$bookings = $stmtBookings->get_result();
$stmtBookings->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings - Showtime Select Admin</title>
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
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .search-form {
            margin-bottom: 20px;
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
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="dashboard.php">Showtime Select Admin</a>
        <form class="w-100 d-none d-md-block" action="" method="GET">
            <input class="form-control form-control-dark w-100" type="text" name="search" placeholder="Search bookings..." value="<?php echo htmlspecialchars($search); ?>">
        </form>
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
                            <a class="nav-link" href="schedules.php">
                                <i class="fas fa-calendar-alt"></i>
                                Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="bookings.php">
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
                    <h1>Bookings Management</h1>
                    <div class="admin-user-info">
                        <img src="https://via.placeholder.com/40" alt="Admin">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                    </div>
                </div>

                <div class="table-container">
                    <div class="d-block d-md-none mb-3">
                        <form class="search-form" action="" method="GET">
                            <div class="input-group">
                                <input class="form-control" type="text" name="search" placeholder="Search bookings..." value="<?php echo htmlspecialchars($search); ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Order ID</th>
                                    <th>Movie</th>
                                    <th>Customer Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Theater</th>
                                    <th>Hall</th>
                                    <th>Seats</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($bookings->num_rows > 0): ?>
                                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['bookingID']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['ORDERID']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['movieTitle'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($booking['bookingFName'] . ' ' . $booking['bookingLName']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['bookingEmail']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['bookingPNumber']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['showDate'] ? date('Y-m-d', strtotime($booking['showDate'])) : 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($booking['showTime'] ? date('H:i', strtotime($booking['showTime'])) : 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($booking['theaterName'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($booking['hallName'] ?? 'N/A'); ?> (<?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($booking['hallType'] ?? ''))); ?>)</td>
                                            <td><?php echo htmlspecialchars($booking['seats'] ?? 'N/A'); ?></td>
                                            <td>₹<?php echo number_format($booking['amount'] ?? 0, 2); ?></td>
                                            <td>
                                                <a href="view_booking.php?id=<?php echo htmlspecialchars($booking['bookingID']); ?>" class="btn btn-sm btn-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <!-- Add edit/delete links if functionality exists -->
                                                <!-- <a href="edit_booking.php?id=<?php echo htmlspecialchars($booking['bookingID']); ?>" class="btn btn-sm btn-primary" title="Edit Booking">
                                                    <i class="fas fa-edit"></i>
                                                </a> -->
                                                <!-- <a href="delete_booking.php?id=<?php echo htmlspecialchars($booking['bookingID']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this booking?')" title="Delete Booking">
                                                    <i class="fas fa-trash"></i>
                                                </a> -->
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="13" class="text-center">No bookings found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="Next">
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
