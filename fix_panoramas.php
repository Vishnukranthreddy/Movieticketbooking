<?php
// This script will help fix panorama image paths in the database

// Database connection
$host = "sql12.freesqldatabase.com";
$username = "sql12784044";
$password = "Whcw9IFzSV";
$database = "sql12784044";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to check if a file exists in any of our panorama directories
function findPanoramaFile($filename) {
    $directories = [
        __DIR__ . '/img/panoramas/',
        __DIR__ . '/uploads/panoramas/',
        sys_get_temp_dir() . '/movie_uploads/'
    ];
    
    foreach ($directories as $dir) {
        $fullPath = $dir . $filename;
        if (file_exists($fullPath) && is_readable($fullPath)) {
            // Return the relative path from project root
            if (strpos($dir, __DIR__ . '/img/panoramas/') === 0) {
                return 'img/panoramas/' . $filename;
            } elseif (strpos($dir, __DIR__ . '/uploads/panoramas/') === 0) {
                return 'uploads/panoramas/' . $filename;
            } else {
                // For temp directory, we need to copy the file to a permanent location
                $destPath = __DIR__ . '/img/panoramas/' . $filename;
                if (copy($fullPath, $destPath)) {
                    return 'img/panoramas/' . $filename;
                }
            }
        }
    }
    
    return null; // File not found in any directory
}

// Function to fix paths in a table
function fixPathsInTable($tableName, $idColumn, $nameColumn, $imageColumn) {
    global $conn;
    
    echo "<h2>Fixing paths in $tableName table</h2>";
    
    // Get all records with panorama images
    $query = "SELECT $idColumn, $nameColumn, $imageColumn FROM $tableName WHERE $imageColumn IS NOT NULL";
    $result = $conn->query($query);
    
    if (!$result) {
        echo "<p style='color:red'>Error querying $tableName: " . $conn->error . "</p>";
        return;
    }
    
    if ($result->num_rows == 0) {
        echo "<p>No records with panorama images found in $tableName.</p>";
        return;
    }
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Original Path</th><th>New Path</th><th>Status</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $id = $row[$idColumn];
        $name = $row[$nameColumn];
        $originalPath = $row[$imageColumn];
        $status = "";
        $newPath = $originalPath;
        
        // Check if the file exists at the current path
        $fullPath = __DIR__ . '/' . $originalPath;
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            // File doesn't exist at the current path, try to find it
            $filename = basename($originalPath);
            $foundPath = findPanoramaFile($filename);
            
            if ($foundPath) {
                // Update the path in the database
                $updateQuery = "UPDATE $tableName SET $imageColumn = ? WHERE $idColumn = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("si", $foundPath, $id);
                
                if ($stmt->execute()) {
                    $status = "Updated";
                    $newPath = $foundPath;
                } else {
                    $status = "Error: " . $stmt->error;
                }
                
                $stmt->close();
            } else {
                $status = "File not found";
            }
        } else {
            $status = "File exists at original path";
        }
        
        echo "<tr>";
        echo "<td>$id</td>";
        echo "<td>$name</td>";
        echo "<td>$originalPath</td>";
        echo "<td>$newPath</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Check if we need to create sample panorama images
if (isset($_GET['create_samples']) && $_GET['create_samples'] == 'yes') {
    echo "<h1>Creating Sample Panorama Images</h1>";
    
    // Ensure the directories exist
    $panoramaDir = __DIR__ . '/img/panoramas/';
    if (!file_exists($panoramaDir)) {
        mkdir($panoramaDir, 0777, true);
    }
    
    // Create sample panorama images using placeholder service
    $sampleImages = [
        'sample_theater_1.jpg' => 'https://placehold.co/1920x960/0f3460/e0e0e0?text=Theater+Panorama+1',
        'sample_theater_2.jpg' => 'https://placehold.co/1920x960/0f3460/e0e0e0?text=Theater+Panorama+2',
        'sample_hall_1.jpg' => 'https://placehold.co/1920x960/3f0f60/e0e0e0?text=Hall+Panorama+1',
        'sample_hall_2.jpg' => 'https://placehold.co/1920x960/603f0f/e0e0e0?text=Hall+Panorama+2'
    ];
    
    foreach ($sampleImages as $filename => $url) {
        $imageData = file_get_contents($url);
        if ($imageData !== false) {
            file_put_contents($panoramaDir . $filename, $imageData);
            echo "<p>Created sample image: $filename</p>";
        } else {
            echo "<p style='color:red'>Failed to create sample image: $filename</p>";
        }
    }
    
    // Update database with sample images
    $updateTheaters = "UPDATE theaters SET theaterPanoramaImg = 'img/panoramas/sample_theater_1.jpg' WHERE theaterID = 1";
    if ($conn->query($updateTheaters)) {
        echo "<p>Updated theater 1 with sample panorama</p>";
    }
    
    $updateTheaters2 = "UPDATE theaters SET theaterPanoramaImg = 'img/panoramas/sample_theater_2.jpg' WHERE theaterID = 2";
    if ($conn->query($updateTheaters2)) {
        echo "<p>Updated theater 2 with sample panorama</p>";
    }
    
    $updateHalls = "UPDATE theater_halls SET hallPanoramaImg = 'img/panoramas/sample_hall_1.jpg' WHERE hallID = 1";
    if ($conn->query($updateHalls)) {
        echo "<p>Updated hall 1 with sample panorama</p>";
    }
    
    $updateHalls2 = "UPDATE theater_halls SET hallPanoramaImg = 'img/panoramas/sample_hall_2.jpg' WHERE hallID = 2";
    if ($conn->query($updateHalls2)) {
        echo "<p>Updated hall 2 with sample panorama</p>";
    }
    
    echo "<p>Sample panoramas created and database updated. <a href='fix_panoramas.php'>Continue to fix paths</a></p>";
    
} else {
    // Main script to fix panorama paths
    echo "<h1>Panorama Path Fixer</h1>";
    echo "<p><a href='fix_panoramas.php?create_samples=yes'>Create Sample Panorama Images</a> | <a href='check_panoramas.php'>Check Panorama Files</a></p>";
    
    // Fix paths in theater_halls table
    fixPathsInTable('theater_halls', 'hallID', 'hallName', 'hallPanoramaImg');
    
    // Fix paths in theaters table
    fixPathsInTable('theaters', 'theaterID', 'theaterName', 'theaterPanoramaImg');
    
    echo "<p>Path fixing complete. <a href='check_panoramas.php'>Check Panorama Files</a></p>";
}

$conn->close();
?>