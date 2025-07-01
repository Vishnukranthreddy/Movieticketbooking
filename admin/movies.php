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

// Handle movie deletion
$successMessage = '';
$errorMessage = '';
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $movieId = $_GET['delete'];
    
    // Check if the movie exists
    $checkQuery = $conn->prepare("SELECT movieID FROM movietable WHERE movieID = ?");
    $checkQuery->bind_param("i", $movieId);
    $checkQuery->execute();
    $result = $checkQuery->get_result();
    
    if ($result->num_rows > 0) {
        // IMPORTANT: Check for foreign key dependencies before deleting!
        // E.g., check movie_schedules table
        $checkSchedulesQuery = $conn->prepare("SELECT COUNT(*) as count FROM movie_schedules WHERE movieID = ?");
        $checkSchedulesQuery->bind_param("i", $movieId);
        $checkSchedulesQuery->execute();
        $schedulesCount = $checkSchedulesQuery->get_result()->fetch_assoc()['count'];
        $checkSchedulesQuery->close();

        if ($schedulesCount > 0) {
            $errorMessage = "Cannot delete movie. It is associated with $schedulesCount schedule(s). Delete schedules first.";
        } else {
            // Delete the movie
            $deleteQuery = $conn->prepare("DELETE FROM movietable WHERE movieID = ?");
            $deleteQuery->bind_param("i", $movieId);
            
            if ($deleteQuery->execute()) {
                $successMessage = "Movie deleted successfully!";
            } else {
                $errorMessage = "Error deleting movie: " . $conn->error;
            }
            $deleteQuery->close();
        }
    } else {
        $errorMessage = "Movie not found!";
    }
    $checkQuery->close();
}

// Get all movies with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$params = [];
$types = '';

if (!empty($search)) {
    $searchParam = "%" . $search . "%";
    $searchCondition = "WHERE movieTitle LIKE ? OR movieGenre LIKE ? OR movieDirector LIKE ?";
    $params = [$searchParam, $searchParam, $searchParam];
    $types = "sss";
}

// Count total records for pagination
$countQuery = "SELECT COUNT(*) as total FROM movietable $searchCondition";

$stmtCount = $conn->prepare($countQuery);
if (!empty($searchCondition)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$totalRecords = $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

$totalPages = ceil($totalRecords / $recordsPerPage);

// Get movies for current page
$query = "SELECT m.*, l.locationName 
          FROM movietable m 
          LEFT JOIN locations l ON m.locationID = l.locationID 
          $searchCondition 
          ORDER BY m.movieID DESC 
          LIMIT ?, ?";

$stmt = $conn->prepare($query);

// Rebind parameters for the main query
$query_params = $params; // Copy search parameters
$query_types = $types; // Copy search types

$query_params[] = $offset;
$query_params[] = $recordsPerPage;
$query_types .= "ii";

if (!empty($searchCondition)) {
    $stmt->bind_param($query_types, ...$query_params);
} else {
    $stmt->bind_param("ii", $offset, $recordsPerPage);
}

$stmt->execute();
$movies = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Movies - Showtime Select Admin</title>
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
        .movie-image {
            width: 50px;
            height: 70px;
            object-fit: cover;
            border-radius: 3px;
        }
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .search-box {
            margin-bottom: 20px;
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
                            <a class="nav-link active" href="movies.php">
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
                    <h1>Manage Movies</h1>
                    <a href="add_movie.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Movie
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

                <div class="table-container">
                    <div class="search-box">
                        <form action="" method="GET" class="form-inline">
                            <div class="input-group w-100">
                                <input type="text" name="search" class="form-control" placeholder="Search by title, genre, or director" value="<?php echo htmlspecialchars($search); ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="movies.php" class="btn btn-outline-danger">
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
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Genre</th>
                                    <th>Duration</th>
                                    <th>Release Date</th>
                                    <th>Location</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($movies->num_rows > 0): ?>
                                    <?php while ($movie = $movies->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($movie['movieID']); ?></td>
                                            <td>
                                                <img src="<?php echo '../' . htmlspecialchars($movie['movieImg']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/50x70/cccccc/333333?text=No+Img';" alt="<?php echo htmlspecialchars($movie['movieTitle']); ?>" class="movie-image">
                                            </td>
                                            <td><?php echo htmlspecialchars($movie['movieTitle']); ?></td>
                                            <td><?php echo htmlspecialchars($movie['movieGenre']); ?></td>
                                            <td><?php echo htmlspecialchars($movie['movieDuration']); ?> min</td>
                                            <td><?php echo htmlspecialchars($movie['movieRelDate']); ?></td>
                                            <td><?php echo htmlspecialchars($movie['locationName'] ?? 'N/A'); ?></td>
                                            <td>
                                                <a href="edit_movie.php?id=<?php echo htmlspecialchars($movie['movieID']); ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="movies.php?delete=<?php echo htmlspecialchars($movie['movieID']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this movie? This will also delete associated schedules and bookings!')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <a href="../user/movie_details.php?id=<?php echo htmlspecialchars($movie['movieID']); ?>" class="btn btn-sm btn-info" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No movies found</td>
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
