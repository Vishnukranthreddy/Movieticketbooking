<?php
session_start();

// RBAC: Accessible only by Super Admin (roleID 1)
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 1) {
    header("Location: index.php");
    exit();
}

// Database connection
$host = "dpg-d1gk4s7gi27c73brav8g-a.oregon-postgres.render.com";
$username = "showtime_select_user";
$password = "kbJAnSvfJHodYK7oDCaqaR7OvwlnJQi1";
$database = "showtime_select"; // Ensured to be movie_db
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form submission
$errorMessage = '';
$successMessage = '';
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
    
    $theaterPanoramaImg = null; // Initialize panorama image path to null
    $uploadOk = 1; // Flag for panorama image upload status (1=OK, 0=Error)

    // Handle panorama image upload if provided
    if (isset($_FILES["theaterPanoramaImage"]) && $_FILES["theaterPanoramaImage"]["error"] == UPLOAD_ERR_OK) {
        $targetDir = "../img/panoramas/"; // Path relative to admin/ folder
        // Ensure target directory exists and is writable
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true); // Create recursively if needed
        }

        $fileName = basename($_FILES["theaterPanoramaImage"]["name"]);
        $uniqueFileName = uniqid() . "_" . $fileName; // Add unique prefix to prevent overwriting
        $targetFilePath = $targetDir . $uniqueFileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        // Basic image validation
        $check = @getimagesize($_FILES["theaterPanoramaImage"]["tmp_name"]); // Use @ to suppress warnings for invalid images
        if($check === false) { $errorMessage = "Panorama file is not a valid image."; $uploadOk = 0; }
        if($_FILES["theaterPanoramaImage"]["size"] > 15000000) { $errorMessage = "Sorry, panorama file is too large (max 15MB)."; $uploadOk = 0; } // Larger size for panoramas
        if($fileType != "jpg" && $fileType != "png" && $fileType != "jpeg" ) { $errorMessage = "Sorry, only JPG, JPEG, and PNG files are allowed for panoramas."; $uploadOk = 0; }

        if ($uploadOk == 0) {
            // Error message already set by validation checks above
        } else {
            if (move_uploaded_file($_FILES["theaterPanoramaImage"]["tmp_name"], $targetFilePath)) {
                $theaterPanoramaImg = "img/panoramas/" . $uniqueFileName; // Path to store in database (relative to project root)
            } else {
                $errorMessage = "Sorry, there was an error uploading the panorama file to the server.";
                $uploadOk = 0; // Set uploadOk to 0 if move fails
            }
        }
    } else if (isset($_FILES["theaterPanoramaImage"]) && $_FILES["theaterPanoramaImage"]["error"] != UPLOAD_ERR_NO_FILE) {
        // Handle other file upload errors (e.g., UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE)
        $errorMessage = "File upload error for panorama: " . $_FILES["theaterPanoramaImage"]["error"];
        $uploadOk = 0;
    }

    // Only proceed with database insert if panorama upload (if attempted) was successful or no file was selected
    if ($uploadOk !== 0) {
        // Insert theater data using prepared statement
        $stmt = $conn->prepare("INSERT INTO theaters (theaterName, theaterAddress, theaterCity, theaterState, theaterZipcode, theaterPhone, theaterEmail, theaterStatus, theaterPanoramaImg) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        // 'sssssssss' for 8 strings + 1 potential string for panorama image
        $stmt->bind_param("sssssssss", $theaterName, $theaterAddress, $theaterCity, $theaterState, $theaterZipcode, $theaterPhone, $theaterEmail, $theaterStatus, $theaterPanoramaImg);
        
        if ($stmt->execute()) {
            $theaterId = $conn->insert_id; // Get the ID of the newly inserted theater
            
            // Check if we need to create default halls
            if (isset($_POST['createDefaultHalls']) && $_POST['createDefaultHalls'] == 'yes') {
                // Prepared statement for hall insertion
                // Note: We don't have hall panorama upload here as it's for specific halls, not default ones.
                $hallStmt = $conn->prepare("INSERT INTO theater_halls (theaterID, hallName, hallType, totalSeats, hallStatus) VALUES (?, ?, ?, ?, ?)");
                $hallStatus = "active";

                // Create Main Hall
                $hallName = "Main Hall";
                $hallType = "main-hall";
                $totalSeats = 120;
                if ($hallStmt) { // Check if prepare was successful
                    $hallStmt->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
                    $hallStmt->execute();
                }
                
                // Create VIP Hall
                $hallName = "VIP Hall";
                $hallType = "vip-hall";
                $totalSeats = 80;
                if ($hallStmt) {
                    $hallStmt->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
                    $hallStmt->execute();
                }
                
                // Create Private Hall
                $hallName = "Private Hall";
                $hallType = "private-hall";
                $totalSeats = 40;
                if ($hallStmt) {
                    $hallStmt->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
                    $hallStmt->execute();
                }
                if ($hallStmt) $hallStmt->close(); // Close the hall insertion statement
            }
            
            $successMessage = "Theater added successfully!";
            // Redirect to theaters page after successful addition
            header("Location: theaters.php?success=1");
            exit();
        } else {
            $errorMessage = "Error adding theater to database: " . $stmt->error;
            // If DB insert fails, try to delete the uploaded panorama file to clean up
            if ($theaterPanoramaImg && file_exists("../" . $theaterPanoramaImg)) {
                unlink("../" . $theaterPanoramaImg);
            }
        }
        $stmt->close();
    }
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
        .preview-image {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            display: block;
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
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Admin Functions</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
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
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Reports
                            </a>
                        </li>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Theater Management</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/theaters.php">
                                <i class="fas fa-building"></i>
                                Theaters
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/locations.php">
                                <i class="fas fa-map-marker-alt"></i>
                                Locations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/schedules.php">
                                <i class="fas fa-calendar-alt"></i>
                                Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/bookings.php">
                                <i class="fas fa-ticket-alt"></i>
                                Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../theater_manager/reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Theater Reports
                            </a>
                        </li>
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Content Management</span>
                        </h6>
                        <li class="nav-item">
                            <a class="nav-link" href="../content_manager/movies.php">
                                <i class="fas fa-film"></i>
                                Movies
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

                <?php if (isset($errorMessage) && !empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <form action="" method="POST" enctype="multipart/form-data">
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

                                <div class="form-group">
                                    <label for="theaterPanoramaImage">Theater Panorama Image (Optional)</label>
                                    <input type="file" class="form-control-file" id="theaterPanoramaImage" name="theaterPanoramaImage" onchange="previewTheaterPanorama(this)">
                                    <img id="theaterPanoramaPreview" class="preview-image" style="display: none;" src="#" alt="Panorama Preview">
                                    <small class="form-text text-muted">Upload a 360-degree panorama image for the theater (JPG, PNG).</small>
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
    <script>
        function previewTheaterPanorama(input) {
            var preview = document.getElementById('theaterPanoramaPreview');
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
                preview.src = '#';
            }
        }
    </script>
</body>
</html>
