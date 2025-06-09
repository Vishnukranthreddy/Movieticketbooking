<?php
session_start();

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "movie_db"; // Ensured to be movie_db
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
    GROUP BY
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

// Check if the movie query failed
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
        theaterPanoramaImg
    FROM theaters
    WHERE theaterStatus = 'active'
    ORDER BY theaterName ASC
";
$theaters = $conn->query($theatersQuery);

// Check if the theater query failed
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
        .card {
            background-color: #0f3460; /* Dark blue for cards */
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Pushes content and buttons apart */
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
        }
        .card-image {
            width: 100%;
            height: 300px; /* Fixed height for consistency */
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
        /* Responsive grid for movies and theaters */
        .grid-container {
            display: grid;
            gap: 8; /* Tailwind gap-8 */
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

        .theater-card {
            background-color: #0f3460;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .theater-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
        }
        .theater-card-image {
            width: 100%;
            height: 200px; /* Adjust height for theater preview */
            object-fit: cover;
            border-bottom: 3px solid #e94560;
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
                            <!-- Removed "View Theater" button from movie cards -->
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
