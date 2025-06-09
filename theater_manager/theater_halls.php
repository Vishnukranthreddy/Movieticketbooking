<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
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

$theaterId = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : 0;
$theaterName = '';
$halls = [];
$successMessage = '';
$errorMessage = '';

// Fetch theater details
if ($theaterId > 0) {
    $stmt = $conn->prepare("SELECT theaterName FROM theaters WHERE theaterID = ?");
    $stmt->bind_param("i", $theaterId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $theaterData = $result->fetch_assoc();
        $theaterName = $theaterData['theaterName'];
    } else {
        $errorMessage = "Theater not found.";
    }
    $stmt->close();
} else {
    $errorMessage = "Invalid Theater ID provided.";
}

// Handle hall addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_hall'])) {
    $hallName = $_POST['hallName'];
    $hallType = $_POST['hallType'];
    $totalSeats = $_POST['totalSeats'];
    $hallStatus = $_POST['hallStatus'];

    if ($theaterId > 0) {
        $stmt = $conn->prepare("INSERT INTO theater_halls (theaterID, hallName, hallType, totalSeats, hallStatus) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
        
        if ($stmt->execute()) {
            $successMessage = "Hall added successfully!";
        } else {
            $errorMessage = "Error adding hall: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errorMessage = "Cannot add hall, Theater ID is missing.";
    }
}

// Handle hall update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_hall'])) {
    $hallID = $_POST['hallID'];
    $hallName = $_POST['hallName'];
    $hallType = $_POST['hallType'];
    $totalSeats = $_POST['totalSeats'];
    $hallStatus = $_POST['hallStatus'];

    $stmt = $conn->prepare("UPDATE theater_halls SET hallName = ?, hallType = ?, totalSeats = ?, hallStatus = ? WHERE hallID = ? AND theaterID = ?");
    $stmt->bind_param("ssiisi", $hallName, $hallType, $totalSeats, $hallStatus, $hallID, $theaterId);
    
    if ($stmt->execute()) {
        $successMessage = "Hall updated successfully!";
    } else {
        $errorMessage = "Error updating hall: " . $stmt->error;
    }
    $stmt->close();
}

// Handle hall deletion
if (isset($_GET['delete_hall']) && is_numeric($_GET['delete_hall'])) {
    $hallIDToDelete = $_GET['delete_hall'];

    // Check if hall has associated schedules/bookings before deleting
    $checkSchedulesQuery = $conn->prepare("SELECT COUNT(*) as count FROM movie_schedules WHERE hallID = ?");
    $checkSchedulesQuery->bind_param("i", $hallIDToDelete);
    $checkSchedulesQuery->execute();
    $schedulesCount = $checkSchedulesQuery->get_result()->fetch_assoc()['count'];
    $checkSchedulesQuery->close();

    if ($schedulesCount > 0) {
        $errorMessage = "Cannot delete hall. It has $schedulesCount associated schedule(s).";
    } else {
        $deleteStmt = $conn->prepare("DELETE FROM theater_halls WHERE hallID = ? AND theaterID = ?");
        $deleteStmt->bind_param("ii", $hallIDToDelete, $theaterId);
        if ($deleteStmt->execute()) {
            $successMessage = "Hall deleted successfully!";
        } else {
            $errorMessage = "Error deleting hall: " . $deleteStmt->error;
        }
        $deleteStmt->close();
    }
}


// Fetch halls for the current theater
if ($theaterId > 0 && empty($errorMessage)) { // Only fetch if theater is valid and no critical error exists
    $stmt = $conn->prepare("SELECT * FROM theater_halls WHERE theaterID = ? ORDER BY hallName");
    $stmt->bind_param("i", $theaterId);
    $stmt->execute();
    $halls = $stmt->get_result();
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Halls for <?php echo htmlspecialchars($theaterName); ?> - Showtime Select Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../../img/sslogo.jpg"> <!-- Path adjusted -->
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
        .table-container, .form-container {
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
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        .hall-type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            font-weight: bold;
            background-color: #007bff;
            color: white;
            text-transform: capitalize;
        }
        .hall-type-badge.main-hall { background-color: #28a745; }
        .hall-type-badge.vip-hall { background-color: #ffc107; color: #343a40;}
        .hall-type-badge.private-hall { background-color: #6f42c1; }

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
                <a class="nav-link" href="../logout.php">Sign out</a>
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
                            <a class="nav-link active" href="theaters.php">
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
                    <h1>Manage Halls for <?php echo htmlspecialchars($theaterName); ?></h1>
                    <a href="theaters.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Theaters
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

                <?php if ($theaterId > 0): ?>
                    <div class="form-container">
                        <h3 class="mb-4">Add New Hall</h3>
                        <form action="theater_halls.php?theater_id=<?php echo $theaterId; ?>" method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="hallName">Hall Name</label>
                                        <input type="text" class="form-control" id="hallName" name="hallName" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="hallType">Hall Type</label>
                                        <select class="form-control" id="hallType" name="hallType" required>
                                            <option value="main-hall">Main Hall</option>
                                            <option value="vip-hall">VIP Hall</option>
                                            <option value="private-hall">Private Hall</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="totalSeats">Total Seats</label>
                                        <input type="number" class="form-control" id="totalSeats" name="totalSeats" required min="1">
                                    </div>
                                    <div class="form-group">
                                        <label for="hallStatus">Status</label>
                                        <select class="form-control" id="hallStatus" name="hallStatus" required>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="add_hall" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Hall
                            </button>
                        </form>
                    </div>

                    <div class="table-container">
                        <h3 class="mb-4">Existing Halls</h3>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Seats</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($halls && $halls->num_rows > 0): ?>
                                        <?php while ($hall = $halls->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $hall['hallID']; ?></td>
                                                <td><?php echo htmlspecialchars($hall['hallName']); ?></td>
                                                <td><span class="hall-type-badge <?php echo htmlspecialchars($hall['hallType']); ?>"><?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($hall['hallType']))); ?></span></td>
                                                <td><?php echo $hall['totalSeats']; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $hall['hallStatus'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo ucfirst($hall['hallStatus']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-warning edit-hall" 
                                                            data-id="<?php echo $hall['hallID']; ?>"
                                                            data-name="<?php echo htmlspecialchars($hall['hallName']); ?>"
                                                            data-type="<?php echo htmlspecialchars($hall['hallType']); ?>"
                                                            data-seats="<?php echo $hall['totalSeats']; ?>"
                                                            data-status="<?php echo htmlspecialchars($hall['hallStatus']); ?>"
                                                            data-toggle="modal" data-target="#editHallModal">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="theater_halls.php?theater_id=<?php echo $theaterId; ?>&delete_hall=<?php echo $hall['hallID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this hall? This will also delete all associated schedules.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No halls found for this theater.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Edit Hall Modal -->
    <div class="modal fade" id="editHallModal" tabindex="-1" role="dialog" aria-labelledby="editHallModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editHallModalLabel">Edit Hall</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="theater_halls.php?theater_id=<?php echo $theaterId; ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_hallID" name="hallID">
                        <div class="form-group">
                            <label for="edit_hallName">Hall Name</label>
                            <input type="text" class="form-control" id="edit_hallName" name="hallName" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_hallType">Hall Type</label>
                            <select class="form-control" id="edit_hallType" name="hallType" required>
                                <option value="main-hall">Main Hall</option>
                                <option value="vip-hall">VIP Hall</option>
                                <option value="private-hall">Private Hall</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_totalSeats">Total Seats</label>
                            <input type="number" class="form-control" id="edit_totalSeats" name="totalSeats" required min="1">
                        </div>
                        <div class="form-group">
                            <label for="edit_hallStatus">Status</label>
                            <select class="form-control" id="edit_hallStatus" name="hallStatus" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="update_hall" class="btn btn-primary">Update Hall</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Fill edit modal with hall data
        $('.edit-hall').click(function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var type = $(this).data('type');
            var seats = $(this).data('seats');
            var status = $(this).data('status');
            
            $('#edit_hallID').val(id);
            $('#edit_hallName').val(name);
            $('#edit_hallType').val(type);
            $('#edit_totalSeats').val(seats);
            $('#edit_hallStatus').val(status);
        });
    </script>
</body>
</html>
