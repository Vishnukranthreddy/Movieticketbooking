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
        $targetDir = "../img/panoramas/"; // Path relative to theater_manager/ folder
        // Ensure target directory exists and is writable
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true); // Create recursively if needed
        }

        $fileName = basename($_FILES["theaterPanoramaImage"]["name"]);
        $uniqueFileName = uniqid() . "_" . $fileName; // Add unique prefix
        $targetFilePath = $targetDir . $uniqueFileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        // Basic image validation
        $check = @getimagesize($_FILES["theaterPanoramaImage"]["tmp_name"]);
        if($check === false) { $errorMessage = "Panorama file is not a valid image."; $uploadOk = 0; }
        if($_FILES["theaterPanoramaImage"]["size"] > 15000000) { $errorMessage = "Sorry, panorama file is too large (max 15MB)."; $uploadOk = 0; }
        if($fileType != "jpg" && $fileType != "png" && $fileType != "jpeg" ) { $errorMessage = "Sorry, only JPG, JPEG, and PNG files are allowed for panoramas."; $uploadOk = 0; }

        if ($uploadOk == 0) {
            // Error message already set
        } else {
            if (move_uploaded_file($_FILES["theaterPanoramaImage"]["tmp_name"], $targetFilePath)) {
                $theaterPanoramaImg = "img/panoramas/" . $uniqueFileName; // Path to store in database (relative to project root)
            } else {
                $errorMessage = "Sorry, there was an error uploading the panorama file to the server.";
                $uploadOk = 0;
            }
        }
    } else if (isset($_FILES["theaterPanoramaImage"]) && $_FILES["theaterPanoramaImage"]["error"] != UPLOAD_ERR_NO_FILE) {
        $errorMessage = "File upload error for panorama: " . $_FILES["theaterPanoramaImage"]["error"];
        $uploadOk = 0;
    }

    // Only proceed with database insert if panorama upload (if attempted) was successful or no file was selected
    if ($uploadOk !== 0) {
        // Insert theater data using prepared statement
        $stmt = $conn->prepare("INSERT INTO theaters (theaterName, theaterAddress, theaterCity, theaterState, theaterZipcode, theaterPhone, theaterEmail, theaterStatus, theaterPanoramaImg) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $theaterName, $theaterAddress, $theaterCity, $theaterState, $theaterZipcode, $theaterPhone, $theaterEmail, $theaterStatus, $theaterPanoramaImg);
        
        if ($stmt->execute()) {
            $theaterId = $conn->insert_id; // Get the ID of the newly inserted theater
            
            // Check if we need to create default halls
            if (isset($_POST['createDefaultHalls']) && $_POST['createDefaultHalls'] == 'yes') {
                $hallStmt = $conn->prepare("INSERT INTO theater_halls (theaterID, hallName, hallType, totalSeats, hallStatus) VALUES (?, ?, ?, ?, ?)");
                $hallStatus = "active";

                if ($hallStmt) {
                    $hallName = "Main Hall"; $hallType = "main-hall"; $totalSeats = 120;
                    $hallStmt->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
                    $hallStmt->execute();
                    
                    $hallName = "VIP Hall"; $hallType = "vip-hall"; $totalSeats = 80;
                    $hallStmt->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
                    $hallStmt->execute();
                    
                    $hallName = "Private Hall"; $hallType = "private-hall"; $totalSeats = 40;
                    $hallStmt->bind_param("issss", $theaterId, $hallName, $hallType, $totalSeats, $hallStatus);
                    $hallStmt->execute();

                    $hallStmt->close();
                }
            }
            
            $successMessage = "Theater added successfully!";
            // Redirect to theaters page after successful addition
            header("Location: theaters.php?success=1");
            exit();
        } else {
            $errorMessage = "Error adding theater to database: " . $stmt->error;
            if ($theaterPanoramaImg && file_exists("../" . $theaterPanoramaImg)) {
                unlink("../" . $theaterPanoramaImg); // Clean up uploaded file on DB error
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
    <link rel="icon" type="image/png" href="../img/sslogo.jpg"> <!-- Adjusted path -->
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
                                    <small class="form-text text-muted">Upload a 360-degree panorama image for the theater (JPG, PNG, max 15MB).</small>
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
```
You are absolutely correct. My apologies for missing the detail about having "View Theater" as a separate option below movies, and for not addressing the fact that the image placeholders in your screenshots show "No Movie Image."

Let's refine the `user/index.php` to achieve exactly what you want:

1.  **"Now Playing Movies" Section (No "View Theater" here):** This section will display movie cards. The "View Theater" button will be *removed* from these movie cards. The `onerror` for the image path will be robust.
2.  **Separate "Our Theaters" Section:** A new section will be added below the movies. This section will feature cards for each theater.
3.  **Theater Card Details:** Each theater card will display the theater's name, city, and a "View Theater" button.
4.  **Panorama Image per Theater:** The "View Theater" button will only be active if `theaterPanoramaImg` is present for that specific theater. This means `theaterPanoramaImg` must be a column in your `theaters` table and populated with image paths.
5.  **Image Paths:** Ensure all image paths (`movieImg` and `theaterPanoramaImg`) are correctly relative from `user/index.php` to the `img/` folder (which is `../img/`).

**Database Schema Requirement (re-confirming):**

For this to work, your `theaters` table must have a column to store the panorama image path. If you haven't already, run this SQL:

```sql
ALTER TABLE `theaters` ADD `theaterPanoramaImg` VARCHAR(255) NULL AFTER `theaterEmail`;
```

And remember to put actual 360 images in `your_project_root/img/panoramas/` and store their paths (e.g., `img/panoramas/lobby.jpg`) in the `theaterPanoramaImg` column.

Here is the updated `user/index.php`:


```php
<?php
session_start();

// Database connection
$host = "dpg-d1gk4s7gi27c73brav8g-a";
$username = "showtime_select_user";
$password = "kbJAnSvfJHodYK7oDCaqaR7OvwlnJQi1";
$database = "showtime_select"; // Ensured to be movie_db
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query for "Now Playing Movies" section
// Still joining with locations to show location name for the movie
$moviesQuery = "
    SELECT
        m.movieID,
        m.movieImg,
        m.movieTitle,
        m.movieGenre,
        m.movieDuration,
        m.movieRelDate,
        l.locationName,
        l.locationID
    FROM movietable m
    LEFT JOIN locations l ON m.locationID = l.locationID
    WHERE m.movieID IS NOT NULL
    GROUP BY -- Grouping by all non-aggregated columns to satisfy ONLY_FULL_GROUP_BY
        m.movieID,
        m.movieImg,
        m.movieTitle,
        m.movieGenre,
        m.movieDuration,
        m.movieRelDate,
        l.locationName,
        l.locationID
    ORDER BY m.movieRelDate DESC, m.movieTitle ASC
";
$movies = $conn->query($moviesQuery);

// Check if the movie query failed and report the specific SQL error
if ($movies === false) {
    die("Movie Query failed: " . $conn->error . "<br>SQL: " . htmlspecialchars($moviesQuery));
}

// Query for "Our Theaters" section
// Fetch all active theaters, including their panorama image path
$theatersQuery = "
    SELECT
        theaterID,
        theaterName,
        theaterCity,
        theaterPanoramaImg -- This column must exist in your 'theaters' table
    FROM theaters
    WHERE theaterStatus = 'active'
    ORDER BY theaterName ASC
";
$theaters = $conn->query($theatersQuery);

// Check if the theater query failed and report the specific SQL error
if ($theaters === false) {
    die("Theater Query failed: " . $conn->error . "<br>SQL: " . htmlspecialchars($theatersQuery));
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Showtime Select - Home</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS for 21stdev classic look -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #1a1a2e; /* Darker background */
            color: #e0e0e0; /* Light text */
            line-height: 1.6;
        }
        .header-bg {
            background-color: #16213e; /* Slightly lighter dark for header */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .nav-link {
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            color: #e94560; /* Accent color on hover */
        }
        .card, .theater-card { /* Combined styles for both card types */
            background-color: #0f3460; /* Dark blue for cards */
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Pushes content and buttons apart */
        }
        .card:hover, .theater-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
        }
        .card-image {
            width: 100%;
            height: 300px; /* Fixed height for movie posters */
            object-fit: cover;
            border-bottom: 3px solid #e94560; /* Accent border */
        }
        .theater-card-image {
            width: 100%;
            height: 200px; /* Fixed height for theater panoramas */
            object-fit: cover;
            border-bottom: 3px solid #e94560; /* Accent border */
        }
        .btn-primary {
            background-color: #e94560;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            text-align: center;
        }
        .btn-primary:hover {
            background-color: #b82e4a;
        }
        .btn-secondary-custom { /* Custom class for secondary button style */
            background-color: #3f5f8a; /* A complementary blue */
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            text-align: center;
        }
        .btn-secondary-custom:hover {
            background-color: #304a6c;
        }
        .card-buttons {
            padding: 1.5rem; /* Padding matches card-body */
            display: flex;
            flex-direction: column;
            gap: 0.75rem; /* Space between buttons */
            margin-top: auto; /* Push buttons to the bottom */
        }
        .footer-bg {
            background-color: #16213e;
        }
        .logo-text {
            color: #e94560; /* Accent color for logo */
            font-weight: 700;
        }
        /* Responsive grid for movies and theaters */
        .grid-container {
            display: grid;
            gap: 2rem; /* Tailwind gap-8 for 2rem */
        }
        @media (min-width: 640px) {
            .grid-cols-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1024px) {
            .grid-cols-4 {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body class="antialiased">
    <!-- Header -->
    <header class="header-bg shadow-lg py-4">
        <div class="container mx-auto flex justify-between items-center px-4">
            <a href="index.php" class="text-2xl font-bold logo-text">Showtime Select</a>
            <nav>
                <ul class="flex space-x-6">
                    <li><a href="index.php" class="nav-link text-white hover:text-red-500">Home</a></li>
                    <li><a href="schedule.php" class="nav-link text-white hover:text-red-500">Schedule</a></li>
                    <li><a href="contact-us.php" class="nav-link text-white hover:text-red-500">Contact Us</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php" class="nav-link text-white hover:text-red-500">Profile</a></li>
                        <li><a href="logout.php" class="nav-link text-white hover:text-red-500">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="nav-link text-white hover:text-red-500">Login</a></li>
                        <li><a href="register.php" class="nav-link text-white hover:text-red-500">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Now Playing Movies Section -->
        <h1 class="text-4xl font-bold text-center mb-10 text-white">Now Playing Movies</h1>
        <?php if ($movies->num_rows > 0): ?>
            <div class="grid-container grid-cols-2 lg:grid-cols-4">
                <?php while ($movie = $movies->fetch_assoc()): ?>
                    <div class="card">
                        <img src="../<?php echo htmlspecialchars($movie['movieImg']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/300x450/cccccc/333333?text=No+Movie+Image';" alt="<?php echo htmlspecialchars($movie['movieTitle']); ?>" class="card-image">
                        <div class="p-6">
                            <h2 class="text-xl font-semibold text-white mb-2"><?php echo htmlspecialchars($movie['movieTitle']); ?></h2>
                            <p class="text-sm text-gray-400 mb-1"><strong>Genre:</strong> <?php echo htmlspecialchars($movie['movieGenre']); ?></p>
                            <p class="text-sm text-gray-400 mb-1"><strong>Duration:</strong> <?php echo htmlspecialchars($movie['movieDuration']); ?> min</p>
                            <p class="text-sm text-gray-400 mb-4"><strong>Location:</strong> <?php echo htmlspecialchars($movie['locationName'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="card-buttons">
                            <a href="movie_details.php?id=<?php echo htmlspecialchars($movie['movieID']); ?>" class="btn-primary">View Details</a>
                            <!-- "View Theater" button removed from movie cards -->
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-center text-xl text-gray-400 mb-12">No movies currently available.</p>
        <?php endif; ?>

        <!-- Our Theaters Section -->
        <h1 class="text-4xl font-bold text-center mt-16 mb-10 text-white">Our Theaters</h1>
        <?php if ($theaters->num_rows > 0): ?>
            <div class="grid-container grid-cols-2 lg:grid-cols-4">
                <?php while ($theater = $theaters->fetch_assoc()): ?>
                    <div class="theater-card">
                        <img src="../<?php echo htmlspecialchars($theater['theaterPanoramaImg'] ?? 'img/placeholders/default_theater_panorama.jpg'); ?>" onerror="this.onerror=null;this.src='https://placehold.co/400x200/0f3460/e0e0e0?text=No+Panorama';" alt="<?php echo htmlspecialchars($theater['theaterName']); ?>" class="theater-card-image">
                        <div class="p-6">
                            <h2 class="text-xl font-semibold text-white mb-2"><?php echo htmlspecialchars($theater['theaterName']); ?></h2>
                            <p class="text-sm text-gray-400 mb-4"><strong>City:</strong> <?php echo htmlspecialchars($theater['theaterCity'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="card-buttons">
                            <?php if (!empty($theater['theaterPanoramaImg'])): ?>
                                <a href="view_theater.php?theater_id=<?php echo htmlspecialchars($theater['theaterID']); ?>" class="btn-primary">View Theater</a>
                            <?php else: ?>
                                <span class="btn-secondary-custom opacity-50 cursor-not-allowed">No Panorama</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-center text-xl text-gray-400">No theaters currently listed.</p>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer-bg text-gray-400 py-8 mt-12">
        <div class="container mx-auto text-center px-4">
            <p>&copy; <?php echo date('Y'); ?> Showtime Select. All rights reserved.</p>
            <p class="text-sm">Designed for educational purpose </p>
        </div>
    </footer>
</body>
</html>
