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

$errorMessage = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $theaterName = $_POST['theaterName'];
    $theaterAddress = $_POST['theaterAddress'];
    $theaterCity = $_POST['theaterCity'];
    $theaterState = $_POST['theaterState'];
    $theaterZipcode = $_POST['theaterZipcode'];
    $theaterPhone = $_POST['theaterPhone'];
    $theaterEmail = $_POST['theaterEmail'];
    $theaterStatus = $_POST['theaterStatus'];
    
    // Insert theater data
    $stmt = $conn->prepare("INSERT INTO theaters (theaterName, theaterAddress, theaterCity, theaterState, theaterZipcode, theaterPhone, theaterEmail, theaterStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $theaterName, $theaterAddress, $theaterCity, $theaterState, $theaterZipcode, $theaterPhone, $theaterEmail, $theaterStatus);
    
    if ($stmt->execute()) {
        $theaterId = $conn->insert_id;
        
        // Check if we need to create default halls
        if (isset($_POST['createDefaultHalls']) && $_POST['createDefaultHalls'] == 'yes') {
            $hallStatus = "active"; // Default status for new halls
            
            // Create main hall
            $hallName = "Main Hall";
            $hallType = "main-hall";
            $totalSeats = 120;
            $stmtHall = $conn->prepare("INSERT INTO theater_halls (theaterID, hallName, hallType, totalSeats, hallStatus) VALUES (?, ?, ?, ?, ?)");
            $stmtHall->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
            $stmtHall->execute();
            
            // Create VIP hall
            $hallName = "VIP Hall";
            $hallType = "vip-hall";
            $totalSeats = 80;
            $stmtHall->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
            $stmtHall->execute();
            
            // Create private hall
            $hallName = "Private Hall";
            $hallType = "private-hall";
            $totalSeats = 40;
            $stmtHall->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
            $stmtHall->execute();

            $stmtHall->close();
        }
        
        // Redirect to theaters page after successful addition
        header("Location: theaters.php?success=1");
        exit();
    } else {
        $errorMessage = "Error: " . $stmt->error;
    }
    
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Theater - Showtime Select Admin</title>
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
                    <h1>Add New Theater</h1>
                    <a href="theaters.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Theaters
                    </a>
                </div>

                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <form action="" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="theaterName">Theater Name</label>
                                    <input type="text" class="form-control" id="theaterName" name="theaterName" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="theaterAddress">Address</label>
                                    <input type="text" class="form-control" id="theaterAddress" name="theaterAddress" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="theaterCity">City</label>
                                    <input type="text" class="form-control" id="theaterCity" name="theaterCity" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="theaterState">State</label>
                                    <input type="text" class="form-control" id="theaterState" name="theaterState" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="theaterZipcode">Zipcode</label>
                                    <input type="text" class="form-control" id="theaterZipcode" name="theaterZipcode">
                                </div>
                                
                                <div class="form-group">
                                    <label for="theaterPhone">Phone</label>
                                    <input type="text" class="form-control" id="theaterPhone" name="theaterPhone" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="theaterEmail">Email</label>
                                    <input type="email" class="form-control" id="theaterEmail" name="theaterEmail" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="theaterStatus">Status</label>
                                    <select class="form-control" id="theaterStatus" name="theaterStatus" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="createDefaultHalls" name="createDefaultHalls" value="yes" checked>
                                <label class="custom-control-label" for="createDefaultHalls">Create default halls (Main Hall, VIP Hall, Private Hall)</label>
                            </div>
                        </div>
                        
                        <div class="form-group text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Add Theater
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
